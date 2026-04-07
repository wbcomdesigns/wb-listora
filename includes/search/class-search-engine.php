<?php
/**
 * Search Engine — orchestrates the two-phase search.
 *
 * @package WBListora\Search
 */

namespace WBListora\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Main search engine. Handles keyword search, field filtering,
 * geo queries, facets, and sorting.
 */
class Search_Engine {

	/**
	 * Execute a search query.
	 *
	 * @param array $args Search arguments.
	 * @return array {
	 *     @type int[]  $listing_ids  Matched listing IDs (paginated).
	 *     @type int    $total        Total matching count.
	 *     @type int    $pages        Total pages.
	 *     @type array  $facets       Facet counts (if requested).
	 *     @type array  $distances    Distance per listing (if geo search).
	 * }
	 */
	public function search( array $args ) {
		$args = $this->parse_args( $args );

		// Check transient cache.
		$cache_key = $this->build_cache_key( $args );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// Phase 1: Candidate selection from search_index.
		$candidates = $this->phase_1_candidates( $args );

		if ( empty( $candidates['ids'] ) ) {
			$result = array(
				'listing_ids' => array(),
				'total'       => 0,
				'pages'       => 0,
				'facets'      => array(),
				'distances'   => array(),
			);
			$this->cache_result( $cache_key, $result, $args );
			return $result;
		}

		// Phase 1.5: Open Now filter (if requested).
		if ( ! empty( $args['open_now'] ) ) {
			$candidates['ids'] = $this->filter_open_now( $candidates['ids'] );
		}

		// Phase 1.55: Date filters (if requested).
		if ( ! empty( $args['date_filter'] ) ) {
			switch ( $args['date_filter'] ) {
				case 'today':
					$candidates['ids'] = $this->filter_today( $candidates['ids'] );
					break;
				case 'weekend':
					$candidates['ids'] = $this->filter_this_weekend( $candidates['ids'] );
					break;
				case 'happening_now':
					$candidates['ids'] = $this->filter_happening_now( $candidates['ids'] );
					break;
			}
		} elseif ( ! empty( $args['date_from'] ) || ! empty( $args['date_to'] ) ) {
			$candidates['ids'] = $this->filter_date_range(
				$candidates['ids'],
				$args['date_from'],
				$args['date_to']
			);
		}

		// Phase 1.6: Taxonomy filters (category, location, features).
		$candidates['ids'] = $this->filter_taxonomies( $candidates['ids'], $args );

		// Phase 2: Custom field filtering.
		if ( ! empty( $args['field_filters'] ) ) {
			$candidates['ids'] = $this->phase_2_field_filter( $candidates['ids'], $args['field_filters'] );
		}

		$total = count( $candidates['ids'] );
		$pages = (int) ceil( $total / $args['per_page'] );

		// Sort.
		$sorted_ids = $this->sort_results( $candidates, $args );

		// Paginate.
		$offset      = ( $args['page'] - 1 ) * $args['per_page'];
		$listing_ids = array_slice( $sorted_ids, $offset, $args['per_page'] );

		// Phase 4: Facets (if requested).
		$facets = array();
		if ( ! empty( $args['facets'] ) ) {
			$facets = $this->phase_4_facets( $candidates['ids'], $args );
		}

		$result = array(
			'listing_ids' => $listing_ids,
			'total'       => $total,
			'pages'       => $pages,
			'facets'      => $facets,
			'distances'   => $candidates['distances'] ?? array(),
		);

		$this->cache_result( $cache_key, $result, $args );

		return $result;
	}

	/**
	 * Parse and normalize search arguments.
	 *
	 * @param array $args Raw args.
	 * @return array
	 */
	private function parse_args( array $args ) {
		return wp_parse_args(
			$args,
			array(
				'keyword'       => '',
				'type'          => '',
				'category'      => 0,
				'location'      => 0,
				'features'      => array(),
				'lat'           => null,
				'lng'           => null,
				'radius'        => 0,
				'radius_unit'   => wb_listora_get_setting( 'distance_unit', 'km' ),
				'bounds'        => null,
				'min_rating'    => 0,
				'open_now'      => false,
				'featured_only' => false,
				'verified_only' => false,
				'field_filters' => array(),
				'date_filter'   => '',
				'date_from'     => '',
				'date_to'       => '',
				'sort'          => 'featured',
				'page'          => 1,
				'per_page'      => (int) wb_listora_get_setting( 'per_page', 20 ),
				'facets'        => false,
				'author'        => 0,
			)
		);
	}

	/**
	 * Phase 1: Query search_index for candidates.
	 *
	 * @param array $args Parsed search args.
	 * @return array { ids: int[], scores: float[], distances: float[] }
	 */
	private function phase_1_candidates( array $args ) {
		global $wpdb;

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$where  = array( 's.status = %s' );
		$params = array( 'publish' );

		// Type filter.
		if ( ! empty( $args['type'] ) ) {
			$where[]  = 's.listing_type = %s';
			$params[] = $args['type'];
		}

		// Rating filter.
		if ( $args['min_rating'] > 0 ) {
			$where[]  = 's.avg_rating >= %f';
			$params[] = (float) $args['min_rating'];
		}

		// Featured only.
		if ( $args['featured_only'] ) {
			$where[] = 's.is_featured = 1';
		}

		// Verified only.
		if ( $args['verified_only'] ) {
			$where[] = 's.is_verified = 1';
		}

		// Author filter.
		if ( $args['author'] > 0 ) {
			$where[]  = 's.author_id = %d';
			$params[] = (int) $args['author'];
		}

		// Geo: bounding box.
		if ( ! empty( $args['bounds'] ) ) {
			$bounds   = $args['bounds'];
			$where[]  = 's.lat BETWEEN %f AND %f';
			$params[] = (float) $bounds['sw_lat'];
			$params[] = (float) $bounds['ne_lat'];
			$where[]  = 's.lng BETWEEN %f AND %f';
			$params[] = (float) $bounds['sw_lng'];
			$params[] = (float) $bounds['ne_lng'];
		} elseif ( ! empty( $args['lat'] ) && ! empty( $args['lng'] ) && $args['radius'] > 0 ) {
			// Calculate bounding box from center + radius.
			$bbox     = Geo_Query::bounding_box(
				(float) $args['lat'],
				(float) $args['lng'],
				(float) $args['radius'],
				$args['radius_unit']
			);
			$where[]  = 's.lat BETWEEN %f AND %f';
			$params[] = $bbox['min_lat'];
			$params[] = $bbox['max_lat'];
			$where[]  = 's.lng BETWEEN %f AND %f';
			$params[] = $bbox['min_lng'];
			$params[] = $bbox['max_lng'];
		}

		// Build SELECT.
		$select = 's.listing_id, s.is_featured, s.avg_rating, s.review_count, s.price_value, s.created_at, s.lat, s.lng';

		// Keyword: FULLTEXT match — collect SELECT params separately to maintain
		// correct placeholder ordering (SELECT %s must come before WHERE %s).
		$select_params = array();
		if ( ! empty( $args['keyword'] ) ) {
			$select         .= ', MATCH(s.title, s.content_text, s.meta_text) AGAINST(%s IN BOOLEAN MODE) AS relevance_score';
			$select_params[] = $args['keyword'];
			$where[]         = 'MATCH(s.title, s.content_text, s.meta_text) AGAINST(%s IN BOOLEAN MODE)';
			$params[]        = $args['keyword'];
		}

		$where_sql = implode( ' AND ', $where );

		// Merge params in SQL placeholder order: SELECT params first, then WHERE params.
		$all_params = array_merge( $select_params, $params );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT {$select} FROM {$prefix}search_index s WHERE {$where_sql}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$all_params
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		if ( empty( $rows ) ) {
			return array(
				'ids'       => array(),
				'rows'      => array(),
				'distances' => array(),
			);
		}

		$ids       = array();
		$rows_map  = array();
		$distances = array();

		foreach ( $rows as $row ) {
			$id              = (int) $row['listing_id'];
			$ids[]           = $id;
			$rows_map[ $id ] = $row;
		}

		// Calculate exact distances if geo search with center point.
		if ( ! empty( $args['lat'] ) && ! empty( $args['lng'] ) ) {
			foreach ( $rows_map as $id => $row ) {
				$dist = Geo_Query::haversine_distance(
					(float) $args['lat'],
					(float) $args['lng'],
					(float) $row['lat'],
					(float) $row['lng'],
					$args['radius_unit']
				);

				$distances[ $id ] = round( $dist, 2 );

				// Post-filter by exact radius.
				if ( $args['radius'] > 0 && $dist > (float) $args['radius'] ) {
					unset( $rows_map[ $id ] );
					$key = array_search( $id, $ids, true );
					if ( false !== $key ) {
						unset( $ids[ $key ] );
					}
					unset( $distances[ $id ] );
				}
			}
			$ids = array_values( $ids );
		}

		return array(
			'ids'       => $ids,
			'rows'      => $rows_map,
			'distances' => $distances,
		);
	}

	/**
	 * Phase 1.5: Filter by "Open Now" using hours table.
	 *
	 * @param int[] $ids Candidate IDs.
	 * @return int[]
	 */
	private function filter_open_now( array $ids ) {
		if ( empty( $ids ) ) {
			return $ids;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// Get current UTC time — we'll compare per listing's timezone.
		// For simplicity in v1, compare against UTC and adjust later.
		$now_day  = (int) current_time( 'w' ); // 0=Sun, 6=Sat — matches our day_of_week.
		$now_time = current_time( 'H:i:s' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare(
			"SELECT DISTINCT listing_id FROM {$prefix}hours
			WHERE listing_id IN ({$placeholders})
			AND day_of_week = %d
			AND is_closed = 0
			AND (is_24h = 1 OR (open_time <= %s AND close_time >= %s))",
			...array_merge( $ids, array( $now_day, $now_time, $now_time ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$open_ids = $wpdb->get_col( $sql );

		return array_map( 'intval', $open_ids );
	}

	/**
	 * Filter events by a custom date range.
	 *
	 * Events whose start_date falls between the given start and end dates.
	 * If only start is provided, filters from that date onwards.
	 * If only end is provided, filters up to that date.
	 *
	 * @param int[]  $ids   Candidate listing IDs.
	 * @param string $start Start date (Y-m-d format).
	 * @param string $end   End date (Y-m-d format).
	 * @return int[]
	 */
	private function filter_date_range( array $ids, $start, $end ) {
		if ( empty( $ids ) ) {
			return $ids;
		}

		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$meta_key     = '_listora_start_date';
		$conditions   = array();
		$params       = $ids;

		$params[] = $meta_key;

		if ( ! empty( $start ) ) {
			$conditions[] = 'pm.meta_value >= %s';
			$params[]     = sanitize_text_field( $start );
		}

		if ( ! empty( $end ) ) {
			$conditions[] = 'pm.meta_value <= %s';
			$params[]     = sanitize_text_field( $end );
		}

		$where_extra = '';
		if ( ! empty( $conditions ) ) {
			$where_extra = ' AND ' . implode( ' AND ', $conditions );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
			WHERE pm.post_id IN ({$placeholders})
			AND pm.meta_key = %s
			AND pm.meta_value != ''{$where_extra}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$params
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$matched = $wpdb->get_col( $sql );

		return array_map( 'intval', $matched );
	}

	/**
	 * Filter events happening today.
	 *
	 * Matches events where start_date <= today AND (end_date >= today OR end_date is empty/null).
	 *
	 * @param int[] $ids Candidate listing IDs.
	 * @return int[]
	 */
	private function filter_today( array $ids ) {
		if ( empty( $ids ) ) {
			return $ids;
		}

		global $wpdb;

		$today        = current_time( 'Y-m-d' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare(
			"SELECT DISTINCT pm_start.post_id FROM {$wpdb->postmeta} pm_start
			LEFT JOIN {$wpdb->postmeta} pm_end
				ON pm_start.post_id = pm_end.post_id AND pm_end.meta_key = '_listora_end_date'
			WHERE pm_start.post_id IN ({$placeholders})
			AND pm_start.meta_key = '_listora_start_date'
			AND pm_start.meta_value != ''
			AND pm_start.meta_value <= %s
			AND (pm_end.meta_value IS NULL OR pm_end.meta_value = '' OR pm_end.meta_value >= %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...array_merge( $ids, array( $today, $today ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$matched = $wpdb->get_col( $sql );

		return array_map( 'intval', $matched );
	}

	/**
	 * Filter events happening this weekend (Saturday and Sunday).
	 *
	 * @param int[] $ids Candidate listing IDs.
	 * @return int[]
	 */
	private function filter_this_weekend( array $ids ) {
		if ( empty( $ids ) ) {
			return $ids;
		}

		global $wpdb;

		// Calculate this Saturday and Sunday dates.
		$today     = current_time( 'Y-m-d' );
		$day_of_wk = (int) current_time( 'w' ); // 0=Sun, 6=Sat.

		if ( 0 === $day_of_wk ) {
			// Today is Sunday — weekend is today.
			$saturday = gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );
			$sunday   = $today;
		} elseif ( 6 === $day_of_wk ) {
			// Today is Saturday — weekend is today and tomorrow.
			$saturday = $today;
			$sunday   = gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( $today ) ) );
		} else {
			// Mon-Fri — next Saturday.
			$days_until_sat = 6 - $day_of_wk;
			$saturday       = gmdate( 'Y-m-d', strtotime( "+{$days_until_sat} days", strtotime( $today ) ) );
			$sunday         = gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( $saturday ) ) );
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// Events that overlap with the weekend range:
		// start_date <= Sunday AND (end_date >= Saturday OR end_date is empty/null).
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare(
			"SELECT DISTINCT pm_start.post_id FROM {$wpdb->postmeta} pm_start
			LEFT JOIN {$wpdb->postmeta} pm_end
				ON pm_start.post_id = pm_end.post_id AND pm_end.meta_key = '_listora_end_date'
			WHERE pm_start.post_id IN ({$placeholders})
			AND pm_start.meta_key = '_listora_start_date'
			AND pm_start.meta_value != ''
			AND pm_start.meta_value <= %s
			AND (pm_end.meta_value IS NULL OR pm_end.meta_value = '' OR pm_end.meta_value >= %s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...array_merge( $ids, array( $sunday, $saturday ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$matched = $wpdb->get_col( $sql );

		return array_map( 'intval', $matched );
	}

	/**
	 * Filter events currently in progress (happening now).
	 *
	 * Matches events where start_date <= current datetime AND end_date >= current datetime.
	 *
	 * @param int[] $ids Candidate listing IDs.
	 * @return int[]
	 */
	private function filter_happening_now( array $ids ) {
		if ( empty( $ids ) ) {
			return $ids;
		}

		global $wpdb;

		$now          = current_time( 'Y-m-d H:i:s' );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $wpdb->prepare(
			"SELECT DISTINCT pm_start.post_id FROM {$wpdb->postmeta} pm_start
			INNER JOIN {$wpdb->postmeta} pm_end
				ON pm_start.post_id = pm_end.post_id AND pm_end.meta_key = '_listora_end_date'
			WHERE pm_start.post_id IN ({$placeholders})
			AND pm_start.meta_key = '_listora_start_date'
			AND pm_start.meta_value != ''
			AND pm_start.meta_value <= %s
			AND pm_end.meta_value != ''
			AND pm_end.meta_value >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...array_merge( $ids, array( $now, $now ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$matched = $wpdb->get_col( $sql );

		return array_map( 'intval', $matched );
	}

	/**
	 * Phase 1.6: Filter by taxonomy terms (category, location, features).
	 *
	 * @param int[] $ids  Candidate IDs.
	 * @param array $args Search args.
	 * @return int[]
	 */
	private function filter_taxonomies( array $ids, array $args ) {
		if ( empty( $ids ) ) {
			return $ids;
		}

		// Category filter.
		if ( ! empty( $args['category'] ) ) {
			$ids = $this->filter_by_taxonomy( $ids, 'listora_listing_cat', $args['category'] );
		}

		// Location filter.
		if ( ! empty( $args['location'] ) ) {
			$ids = $this->filter_by_taxonomy( $ids, 'listora_listing_location', $args['location'] );
		}

		// Features filter (must have ALL selected features).
		if ( ! empty( $args['features'] ) ) {
			foreach ( (array) $args['features'] as $feature_id ) {
				$ids = $this->filter_by_taxonomy( $ids, 'listora_listing_feature', $feature_id );
			}
		}

		return $ids;
	}

	/**
	 * Filter IDs by a taxonomy term.
	 *
	 * @param int[]  $ids      Post IDs.
	 * @param string $taxonomy Taxonomy name.
	 * @param int    $term_id  Term ID.
	 * @return int[]
	 */
	private function filter_by_taxonomy( array $ids, $taxonomy, $term_id ) {
		global $wpdb;

		$term_id      = (int) $term_id;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		// Include child terms for hierarchical taxonomies.
		$term_ids = array( $term_id );
		$children = get_term_children( $term_id, $taxonomy );
		if ( ! is_wp_error( $children ) ) {
			$term_ids = array_merge( $term_ids, $children );
		}

		$term_placeholders = implode( ',', array_fill( 0, count( $term_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT DISTINCT tr.object_id FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tr.object_id IN ({$placeholders})
			AND tt.term_id IN ({$term_placeholders})
			AND tt.taxonomy = %s",
			...array_merge( $ids, $term_ids, array( $taxonomy ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$matched = $wpdb->get_col( $sql );

		return array_map( 'intval', $matched );
	}

	/**
	 * Phase 2: Filter candidates by custom field values.
	 *
	 * @param int[] $ids           Candidate IDs.
	 * @param array $field_filters Field filter conditions. Format:
	 *                             [ 'cuisine' => ['Italian', 'Chinese'], 'bedrooms' => ['min' => 3] ]
	 * @return int[]
	 */
	private function phase_2_field_filter( array $ids, array $field_filters ) {
		if ( empty( $ids ) || empty( $field_filters ) ) {
			return $ids;
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$conditions   = array();
		$params       = $ids; // Start with IDs for IN clause.
		$filter_count = 0;
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		foreach ( $field_filters as $field_key => $value ) {
			++$filter_count;

			if ( is_array( $value ) && isset( $value['min'] ) ) {
				// Range filter: { min: 3, max: 10 }
				$sub_conds = array( 'field_key = %s' );
				$params[]  = $field_key;

				if ( isset( $value['min'] ) && '' !== $value['min'] ) {
					$sub_conds[] = 'numeric_value >= %f';
					$params[]    = (float) $value['min'];
				}
				if ( isset( $value['max'] ) && '' !== $value['max'] ) {
					$sub_conds[] = 'numeric_value <= %f';
					$params[]    = (float) $value['max'];
				}

				$conditions[] = '(' . implode( ' AND ', $sub_conds ) . ')';

			} elseif ( is_array( $value ) ) {
				// Multi-value filter: ['Italian', 'Chinese'] — match ANY.
				$value_placeholders = implode( ',', array_fill( 0, count( $value ), '%s' ) );
				$conditions[]       = "(field_key = %s AND field_value IN ({$value_placeholders}))";
				$params[]           = $field_key;
				$params             = array_merge( $params, $value );

			} else {
				// Exact match filter.
				$conditions[] = '(field_key = %s AND field_value = %s)';
				$params[]     = $field_key;
				$params[]     = (string) $value;
			}
		}

		if ( empty( $conditions ) ) {
			return $ids;
		}

		$or_conditions = implode( ' OR ', $conditions );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT listing_id FROM {$prefix}field_index
			WHERE listing_id IN ({$placeholders})
			AND ({$or_conditions})
			GROUP BY listing_id
			HAVING COUNT(DISTINCT field_key) >= %d",
			...array_merge( $params, array( $filter_count ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$matched = $wpdb->get_col( $sql );

		return array_map( 'intval', $matched );
	}

	/**
	 * Sort results by the specified sort order.
	 *
	 * @param array $candidates Candidate data (ids, rows, distances).
	 * @param array $args       Search args.
	 * @return int[] Sorted listing IDs.
	 */
	private function sort_results( array $candidates, array $args ) {
		$ids  = $candidates['ids'];
		$rows = $candidates['rows'] ?? array();
		$dist = $candidates['distances'] ?? array();

		switch ( $args['sort'] ) {
			case 'relevance':
				// Already sorted by FULLTEXT relevance if keyword search.
				// Rows are in relevance order from MySQL.
				break;

			case 'newest':
				usort(
					$ids,
					function ( $a, $b ) use ( $rows ) {
						$da = $rows[ $a ]['created_at'] ?? '';
						$db = $rows[ $b ]['created_at'] ?? '';
						return strcmp( $db, $da ); // DESC.
					}
				);
				break;

			case 'rating':
				usort(
					$ids,
					function ( $a, $b ) use ( $rows ) {
						$ra = (float) ( $rows[ $a ]['avg_rating'] ?? 0 );
						$rb = (float) ( $rows[ $b ]['avg_rating'] ?? 0 );
						return $rb <=> $ra; // DESC.
					}
				);
				break;

			case 'distance':
				usort(
					$ids,
					function ( $a, $b ) use ( $dist ) {
						$da = $dist[ $a ] ?? PHP_FLOAT_MAX;
						$db = $dist[ $b ] ?? PHP_FLOAT_MAX;
						return $da <=> $db; // ASC.
					}
				);
				break;

			case 'price_asc':
				usort(
					$ids,
					function ( $a, $b ) use ( $rows ) {
						$pa = (float) ( $rows[ $a ]['price_value'] ?? 0 );
						$pb = (float) ( $rows[ $b ]['price_value'] ?? 0 );
						return $pa <=> $pb;
					}
				);
				break;

			case 'price_desc':
				usort(
					$ids,
					function ( $a, $b ) use ( $rows ) {
						$pa = (float) ( $rows[ $a ]['price_value'] ?? 0 );
						$pb = (float) ( $rows[ $b ]['price_value'] ?? 0 );
						return $pb <=> $pa;
					}
				);
				break;

			case 'most_reviewed':
				usort(
					$ids,
					function ( $a, $b ) use ( $rows ) {
						$ra = (int) ( $rows[ $a ]['review_count'] ?? 0 );
						$rb = (int) ( $rows[ $b ]['review_count'] ?? 0 );
						return $rb <=> $ra;
					}
				);
				break;

			case 'alphabetical':
				// Pre-load all titles in a single query to avoid N+1 get_the_title() calls.
				$title_map = array();
				if ( ! empty( $ids ) ) {
					global $wpdb;
					$id_placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$title_rows = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT ID, post_title FROM {$wpdb->posts} WHERE ID IN ({$id_placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
							...$ids
						),
						ARRAY_A
					);
					foreach ( $title_rows as $trow ) {
						$title_map[ (int) $trow['ID'] ] = $trow['post_title'];
					}
				}
				usort(
					$ids,
					function ( $a, $b ) use ( $title_map ) {
						$ta = $title_map[ $a ] ?? '';
						$tb = $title_map[ $b ] ?? '';
						return strcasecmp( $ta, $tb );
					}
				);
				break;

			case 'featured':
			default:
				// Featured first, then by rating.
				usort(
					$ids,
					function ( $a, $b ) use ( $rows ) {
						$fa = (int) ( $rows[ $a ]['is_featured'] ?? 0 );
						$fb = (int) ( $rows[ $b ]['is_featured'] ?? 0 );
						if ( $fa !== $fb ) {
							return $fb <=> $fa; // Featured first.
						}
						$ra = (float) ( $rows[ $a ]['avg_rating'] ?? 0 );
						$rb = (float) ( $rows[ $b ]['avg_rating'] ?? 0 );
						return $rb <=> $ra;
					}
				);
				break;
		}

		return $ids;
	}

	/**
	 * Phase 4: Calculate facet counts for filter fields.
	 *
	 * @param int[] $candidate_ids All matched IDs (before pagination).
	 * @param array $args          Search args.
	 * @return array Field key => [value => count] map.
	 */
	private function phase_4_facets( array $candidate_ids, array $args ) {
		if ( empty( $candidate_ids ) || empty( $args['type'] ) ) {
			return array();
		}

		// Get filterable fields for this type.
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get( $args['type'] );
		if ( ! $type ) {
			return array();
		}

		$filterable = $type->get_filterable_fields();
		if ( empty( $filterable ) ) {
			return array();
		}

		global $wpdb;
		$prefix       = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$placeholders = implode( ',', array_fill( 0, count( $candidate_ids ), '%d' ) );
		$facets       = array();

		// Collect all eligible field keys, then run a single grouped query.
		$field_keys = array();
		foreach ( $filterable as $field ) {
			$field_type = $field->get_type();

			// Skip range fields (number, price) — facets don't make sense for continuous values.
			if ( in_array( $field_type, array( 'number', 'price', 'business_hours', 'map_location' ), true ) ) {
				continue;
			}

			$field_keys[] = $field->get_key();
		}

		if ( ! empty( $field_keys ) ) {
			$key_placeholders = implode( ',', array_fill( 0, count( $field_keys ), '%s' ) );

			// Single query for all field facets instead of one query per field.
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT field_key, field_value, COUNT(DISTINCT listing_id) as cnt
				FROM {$prefix}field_index
				WHERE listing_id IN ({$placeholders})
				AND field_key IN ({$key_placeholders})
				AND field_value != ''
				GROUP BY field_key, field_value
				ORDER BY field_key, cnt DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...array_merge( $candidate_ids, $field_keys )
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $sql, ARRAY_A );

			// Initialize all field keys.
			foreach ( $field_keys as $fk ) {
				$facets[ $fk ] = array();
			}

			foreach ( $rows as $row ) {
				$facets[ $row['field_key'] ][ $row['field_value'] ] = (int) $row['cnt'];
			}
		}

		// Also add taxonomy facets.
		$facets = $this->add_taxonomy_facets( $facets, $candidate_ids, $args );

		return $facets;
	}

	/**
	 * Add taxonomy-based facets (categories, features).
	 *
	 * @param array $facets       Existing facets.
	 * @param int[] $candidate_ids Candidate IDs.
	 * @param array $args          Search args.
	 * @return array
	 */
	private function add_taxonomy_facets( array $facets, array $candidate_ids, array $args ) {
		global $wpdb;

		$taxonomies       = array( 'listora_listing_cat', 'listora_listing_feature' );
		$placeholders     = implode( ',', array_fill( 0, count( $candidate_ids ), '%d' ) );
		$tax_placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );

		// Single query for all taxonomy facets instead of one per taxonomy.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT tt.taxonomy, t.slug, t.name, COUNT(DISTINCT tr.object_id) as cnt
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE tr.object_id IN ({$placeholders})
			AND tt.taxonomy IN ({$tax_placeholders})
			GROUP BY tt.taxonomy, t.term_id
			ORDER BY tt.taxonomy, cnt DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...array_merge( $candidate_ids, $taxonomies )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		// Initialize keys.
		foreach ( $taxonomies as $taxonomy ) {
			$key            = str_replace( 'listora_listing_', '', $taxonomy );
			$facets[ $key ] = array();
		}

		foreach ( $rows as $row ) {
			$key                            = str_replace( 'listora_listing_', '', $row['taxonomy'] );
			$facets[ $key ][ $row['slug'] ] = array(
				'name'  => $row['name'],
				'count' => (int) $row['cnt'],
			);
		}

		return $facets;
	}

	/**
	 * Build a transient cache key for search results.
	 *
	 * @param array $args Search args.
	 * @return string
	 */
	private function build_cache_key( array $args ) {
		$type = ! empty( $args['type'] ) ? $args['type'] : 'all';

		// Normalize for consistent cache keys.
		$normalized = $args;
		if ( isset( $normalized['lat'] ) ) {
			$normalized['lat'] = round( (float) $normalized['lat'], 3 );
		}
		if ( isset( $normalized['lng'] ) ) {
			$normalized['lng'] = round( (float) $normalized['lng'], 3 );
		}

		$hash = md5( wp_json_encode( $normalized ) );
		return "listora_search_{$type}_{$hash}";
	}

	/**
	 * Cache search results with selective invalidation key.
	 *
	 * @param string $key    Cache key.
	 * @param array  $result Result data.
	 * @param array  $args   Original args (for TTL).
	 */
	private function cache_result( $key, array $result, array $args ) {
		$ttl = (int) wb_listora_get_setting( 'search_cache_ttl', 15 ) * MINUTE_IN_SECONDS;
		set_transient( $key, $result, $ttl );
	}
}
