<?php
/**
 * Search Engine — orchestrates the two-phase search.
 *
 * @package WBListora\Search
 */

namespace WBListora\Search;

use WBListora\Contracts\Search_Engine_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Main search engine. Handles keyword search, field filtering,
 * geo queries, facets, and sorting.
 *
 * Implements {@see Search_Engine_Interface} so Pro / extensions can resolve
 * it via wb_listora_service( 'search_engine' ).
 */
class Search_Engine implements Search_Engine_Interface {

	/**
	 * Hard cap on the number of rows the phase-1 candidate query is
	 * allowed to pull into PHP, regardless of user pagination.
	 *
	 * Without this cap, a broad keyword on a 500k-listing index would
	 * load every matching row into memory just to paginate / facet over
	 * the first 20 results. The cap is a SAFETY ceiling, not the
	 * user-visible page size — `per_page` still governs what the API
	 * returns. The 5,000 cap is large enough that real users never see
	 * it (relevance + filters narrow well below it), and small enough
	 * to keep PHP memory + sort cost bounded.
	 *
	 * Reference: SKILL.md Part 2.3 / scale-and-cache.md §2.1.
	 */
	const MAX_PHASE_1_CANDIDATES = 5000;

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

		// Fast path: when nothing downstream needs the full candidate array, let
		// the database count, sort and paginate. The materialising path below
		// caps candidates at MAX_PHASE_1_CANDIDATES, so on a plain browse it
		// reports a capped `total` and cannot reach past that many rows.
		if ( ! $this->needs_materialised_candidates( $args ) ) {
			$paged = $this->sql_paginated_candidates( $args );

			$result = array(
				'listing_ids' => $paged['listing_ids'],
				'total'       => $paged['total'],
				'pages'       => $paged['pages'],
				'facets'      => array(),
				'distances'   => $paged['distances'],
			);

			$this->cache_result( $cache_key, $result, $args );
			$this->fire_search_resolved( $args, $result );

			return $result;
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
			$this->fire_search_resolved( $args, $result );
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

		$this->fire_search_resolved( $args, $result );

		return $result;
	}

	/**
	 * Fire the post-resolution extensibility action.
	 *
	 * Runs once for EVERY resolved search query, regardless of caller — the
	 * REST search endpoint, the SSR listing-grid / listing-featured block
	 * renders, and any extension that resolves the engine via
	 * `wb_listora_service( 'search_engine' )` all flow through
	 * {@see self::search()}, so firing here covers all paths with no
	 * per-caller wiring and no coupling for listeners.
	 *
	 * Listeners (e.g. Pro search-analytics) observe the resolved query
	 * without mutating it — this is an action, not a filter. The result
	 * has already been cached before this fires, so a listener that runs
	 * its own queries cannot poison the search response.
	 *
	 * NOTE: distinct from the REST-only `wb_listora_search_results`
	 * FILTER (`$response_data, $args, $request`) in the search controller,
	 * which mutates the REST payload. This action is the read-only,
	 * all-paths resolution signal.
	 *
	 * @param array<string, mixed> $args   Parsed search arguments for the resolved query.
	 * @param array<string, mixed> $result Resolved result set (listing_ids, total, pages,
	 *                                      facets, distances).
	 * @return void
	 */
	private function fire_search_resolved( array $args, array $result ) {
		$total = isset( $result['total'] ) ? (int) $result['total'] : 0;

		/**
		 * Contextual data accompanying a resolved search query.
		 *
		 * Filterable so a caller can label the resolution source (REST vs
		 * a specific block) before listeners observe it. The engine itself
		 * has no caller awareness, so `source` defaults to `unknown`.
		 *
		 * @param array $context Resolution context.
		 * @param array $args    Parsed search arguments.
		 * @param array $result  Resolved result set.
		 */
		$context = apply_filters(
			'wb_listora_search_resolved_context',
			array(
				'source'       => 'unknown',
				'pages'        => isset( $result['pages'] ) ? (int) $result['pages'] : 0,
				'page'         => isset( $args['page'] ) ? (int) $args['page'] : 1,
				'per_page'     => isset( $args['per_page'] ) ? (int) $args['per_page'] : 0,
				'result_count' => isset( $result['listing_ids'] ) ? count( (array) $result['listing_ids'] ) : 0,
			),
			$args,
			$result
		);

		/**
		 * Fires after a search query resolves, covering every search path
		 * (REST endpoint + SSR/block renders).
		 *
		 * Additive, read-only extensibility surface so Pro/extensions
		 * (e.g. search analytics) can observe resolved queries with no
		 * coupling to the engine internals. Do NOT mutate state the search
		 * response depends on — the result is already cached when this
		 * fires.
		 *
		 * @since 1.2.0
		 *
		 * @param array $args    Parsed search arguments for the resolved query.
		 * @param int   $total   Total matching listings for the query.
		 * @param array $context Resolution context (source, paging, result_count).
		 */
		do_action( 'wb_listora_search_resolved', $args, $total, $context );
	}

	/**
	 * Parse and normalize search arguments.
	 *
	 * @param array $args Raw args.
	 * @return array
	 */
	private function parse_args( array $args ) {
		$args = wp_parse_args(
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
				'has_geo'       => false,
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

		// Floor-guard untrusted pagination inputs. Block attributes reach this
		// engine raw (editor JS min constraints don't bind the server — BC
		// #9989784605 family): page/per_page of 0 fatals on the page-count
		// division below; negatives corrupt the array_slice offset.
		$args['page']     = max( 1, (int) $args['page'] );
		$args['per_page'] = max( 1, (int) $args['per_page'] );

		return $args;
	}

	/**
	 * Convert a user keyword into MySQL FULLTEXT BOOLEAN MODE syntax.
	 *
	 * BOOLEAN MODE defaults to OR for unprefixed terms — typing
	 * "Amalfi Coast Italian" matches any document containing "Amalfi" OR
	 * "Coast" OR "Italian", which surfaces unrelated Italian restaurants
	 * when the user wanted the one Amalfi Coast restaurant. We prefix
	 * each token with `+` so all terms are required (AND), and append `*`
	 * so partial typing still matches ("amalf" → "amalfi"). The full
	 * phrase is also added in quotes so an exact-phrase match outranks
	 * a scattered-token match in relevance scoring.
	 *
	 * Special chars meaningful to BOOLEAN MODE (`+ - > < ( ) ~ * " @`)
	 * are stripped from the input first — otherwise a stray `+` inside
	 * the keyword could change the operator semantics or produce a SQL
	 * syntax error inside the FULLTEXT parser. Tokens shorter than 3
	 * chars are dropped because InnoDB's default `innodb_ft_min_token_size`
	 * is 3; sending shorter tokens would return zero matches even for
	 * valid documents.
	 *
	 * @param string $keyword Raw user input.
	 * @return string BOOLEAN MODE expression, or '' when nothing usable remained.
	 */
	private static function build_boolean_keyword( $keyword ) {
		$keyword = trim( $keyword );
		if ( '' === $keyword ) {
			return '';
		}

		// Strip BOOLEAN MODE operators so user input can't change semantics
		// or break the FULLTEXT parser.
		$cleaned = preg_replace( '/[+\-><()~*"@]/u', ' ', $keyword );
		$cleaned = trim( (string) $cleaned );
		if ( '' === $cleaned ) {
			return '';
		}

		$tokens = preg_split( '/\s+/u', $cleaned ) ?: array();
		$tokens = array_filter(
			$tokens,
			static function ( $t ) {
				// Match InnoDB's default ft_min_token_size to avoid silent
				// "no results" when one token is too short.
				return mb_strlen( (string) $t ) >= 3;
			}
		);

		if ( empty( $tokens ) ) {
			// Every token is below ft_min_token_size (e.g. searching "NY").
			// Return the cleaned bare keyword — it carries no '+' prefix, which
			// is the signal build_keyword_clause() uses to route short terms to
			// the LIKE fallback instead of FULLTEXT (which would match nothing).
			return $cleaned;
		}

		// Each token: required + prefix-matchable.
		$required = array();
		foreach ( $tokens as $tok ) {
			$required[] = '+' . $tok . '*';
		}

		// Boost exact-phrase matches when the query has multiple tokens —
		// "Amalfi Coast Italian" should rank the literal phrase above
		// scattered matches that just happen to share the same words.
		if ( count( $tokens ) > 1 ) {
			$required[] = '"' . implode( ' ', $tokens ) . '"';
		}

		return implode( ' ', $required );
	}

	/**
	 * Classify a user keyword into the right index-query strategy.
	 *
	 * Returns one of:
	 *  - [ 'type' => 'boolean', 'value' => '+foo* ...' ] — normal FULLTEXT path
	 *    when at least one token meets InnoDB's ft_min_token_size.
	 *  - [ 'type' => 'like', 'value' => 'ny' ] — short-term LIKE fallback when
	 *    every token is too short for FULLTEXT (which would silently return zero).
	 *  - [ 'type' => 'none', 'value' => '' ] — nothing usable remained.
	 *
	 * @param string $keyword Raw user input.
	 * @return array{type:string,value:string}
	 */
	private static function build_keyword_clause( $keyword ) {
		$boolean = self::build_boolean_keyword( $keyword );
		if ( '' === $boolean ) {
			return array(
				'type'  => 'none',
				'value' => '',
			);
		}

		// build_boolean_keyword() prefixes every FULLTEXT-eligible token with
		// '+'. If none is present, every token was below ft_min_token_size and
		// BOOLEAN MODE would match nothing — use the LIKE fallback instead.
		if ( false !== strpos( $boolean, '+' ) ) {
			return array(
				'type'  => 'boolean',
				'value' => $boolean,
			);
		}

		/**
		 * Filters whether short (1-3 char) search terms fall back to a LIKE
		 * scan when FULLTEXT can't match them. Return false to keep the
		 * FULLTEXT-only behavior (short terms return no results).
		 *
		 * @param bool   $enable  Whether to use the LIKE fallback. Default true.
		 * @param string $keyword The raw keyword.
		 */
		$enable = (bool) apply_filters( 'wb_listora_search_short_term_like_fallback', true, $keyword );
		if ( ! $enable ) {
			return array(
				'type'  => 'none',
				'value' => '',
			);
		}

		return array(
			'type'  => 'like',
			'value' => $boolean,
		);
	}

	/**
	 * Build the shared candidate SELECT + WHERE for a search.
	 *
	 * ONE source of truth for the candidate query. Both the materialising path
	 * ({@see self::phase_1_candidates()}) and the SQL-paginated path
	 * ({@see self::sql_paginated_candidates()}) call this, so the two can never
	 * drift apart on which rows they consider — the failure mode the two
	 * divergent facet implementations already demonstrate in this class.
	 *
	 * @since 1.5.0
	 *
	 * @param array<string,mixed> $args Parsed search args.
	 * @return array{select:string,select_params:array<int,mixed>,where_sql:string,params:array<int,mixed>,has_relevance:bool,prefix:string}
	 */
	private function build_candidate_query( array $args ) {
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

		// Mappable listings only.
		//
		// The map plots the current search, so it must page over listings that
		// HAVE coordinates. Filtering for coordinates after paging instead
		// silently shrinks the map: the first page of a 2,800-listing result
		// contained 73 of the 99 mappable rows, and the other 26 ranked past
		// the cap and simply vanished from the map with no signal.
		//
		// `lat = 0` is the plugin's established "no coordinates" sentinel
		// (Null Island is not a listing), matching the geo table's `lat != 0`.
		if ( ! empty( $args['has_geo'] ) ) {
			$where[] = 's.lat != 0';
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
		//
		// We rewrite the user input into MySQL BOOLEAN MODE syntax so multi-word
		// queries behave like every other search engine on the planet — i.e.
		// require all terms instead of OR-ing them. Without the rewrite, typing
		// "Amalfi Coast Italian" returns every Italian restaurant in the index
		// because BOOLEAN MODE defaults to OR for unprefixed terms.
		$select_params = array();
		$has_relevance = false;
		if ( ! empty( $args['keyword'] ) ) {
			$keyword_clause = self::build_keyword_clause( (string) $args['keyword'] );
			if ( 'boolean' === $keyword_clause['type'] ) {
				$select         .= ', MATCH(s.title, s.content_text, s.meta_text) AGAINST(%s IN BOOLEAN MODE) AS relevance_score';
				$select_params[] = $keyword_clause['value'];
				$where[]         = 'MATCH(s.title, s.content_text, s.meta_text) AGAINST(%s IN BOOLEAN MODE)';
				$params[]        = $keyword_clause['value'];
				$has_relevance   = true;
			} elseif ( 'like' === $keyword_clause['type'] ) {
				// Short single-token query (below InnoDB ft_min_token_size).
				// BOOLEAN MODE silently drops it, so fall back to a LIKE scan
				// on the indexed text columns. Bounded by the other WHERE
				// filters + the phase-1 LIMIT cap below.
				$like     = '%' . $wpdb->esc_like( $keyword_clause['value'] ) . '%';
				$where[]  = '( s.title LIKE %s OR s.content_text LIKE %s OR s.meta_text LIKE %s )';
				$params[] = $like;
				$params[] = $like;
				$params[] = $like;
			}
		}
		return array(
			'select'        => $select,
			'select_params' => $select_params,
			'where_sql'     => implode( ' AND ', $where ),
			'params'        => $params,
			'has_relevance' => $has_relevance,
			'prefix'        => $prefix,
		);
	}

	/**
	 * Grid precision, in decimal places of latitude, for a map zoom level.
	 *
	 * One decimal place is roughly 11 km, two is 1.1 km, three is 110 m. The
	 * steps are chosen so a cluster stays a few dozen pixels wide across the
	 * zoom range rather than collapsing to one blob or exploding into pins.
	 *
	 * @since 1.5.0
	 *
	 * @param int $zoom Map zoom level.
	 * @return int Decimal places for ROUND().
	 */
	private static function cluster_precision( $zoom ) {
		$zoom = max( 0, min( 22, (int) $zoom ) );

		if ( $zoom <= 4 ) {
			return 0;
		}
		if ( $zoom <= 7 ) {
			return 1;
		}
		if ( $zoom <= 10 ) {
			return 2;
		}
		if ( $zoom <= 13 ) {
			return 3;
		}

		return 4;
	}

	/**
	 * Aggregate listings into map clusters, in one query.
	 *
	 * A map cannot page. `/search` caps `per_page` at 100, so a directory of any
	 * size could only ever put an arbitrary hundred pins on the map — verified
	 * here against 4,895 geocoded listings — and the client was left grouping
	 * whatever it happened to receive, which is a picture of the page rather
	 * than of the data.
	 *
	 * Grouping by rounded coordinates lets the database answer "how many, and
	 * roughly where" for the whole result set at constant cost. Cells holding a
	 * single listing come back as real points so they stay tappable; the rest
	 * come back as counted clusters positioned on their members' centroid, not
	 * on the cell's corner.
	 *
	 * Uses {@see self::build_candidate_query()}, so the rows counted here are
	 * exactly the rows `/search` would return for the same filters.
	 *
	 * @since 1.5.0
	 *
	 * @param array<string,mixed> $args Parsed search args. `zoom` selects the grid precision.
	 * @return array{clusters:array<int,array<string,mixed>>,points:array<int,array<string,mixed>>,total:int,precision:int}
	 */
	public function map_clusters( array $args ) {
		global $wpdb;

		// Same normalisation search() applies. Without it the candidate builder
		// reads keys that were never set and the filters resolve to nonsense.
		$zoom  = $args['zoom'] ?? 10;
		$args  = $this->parse_args( $args );
		$built = $this->build_candidate_query( $args );
		$precision = self::cluster_precision( $zoom );

		// Rows without coordinates cannot be placed, and would otherwise all
		// collapse into a single phantom cluster at (0,0).
		$where_sql = $built['where_sql'] . ' AND s.lat IS NOT NULL AND s.lng IS NOT NULL';

		// Placeholder order follows SQL text order. The two ROUND() precisions
		// sit in the SELECT, so they bind BEFORE the WHERE params. The builder's
		// own select_params belong to a SELECT this method replaces — the
		// keyword MATCH placeholder is carried in the WHERE params.
		$params = array_merge(
			array( $precision, $precision ),
			$built['params']
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ROUND(s.lat, %d) AS cell_lat,
				        ROUND(s.lng, %d) AS cell_lng,
				        COUNT(*)          AS cnt,
				        AVG(s.lat)        AS centre_lat,
				        AVG(s.lng)        AS centre_lng,
				        MIN(s.listing_id) AS sample_id
				 FROM {$built['prefix']}search_index s
				 WHERE {$where_sql}
				 GROUP BY cell_lat, cell_lng", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$params
			),
			ARRAY_A
		);

		$clusters   = array();
		$single_ids = array();
		$total      = 0;

		foreach ( (array) $rows as $row ) {
			$count  = (int) $row['cnt'];
			$total += $count;

			if ( 1 === $count ) {
				// Resolved into a real point below so it can be tapped.
				$single_ids[] = (int) $row['sample_id'];
				continue;
			}

			$clusters[] = array(
				'lat'   => round( (float) $row['centre_lat'], 6 ),
				'lng'   => round( (float) $row['centre_lng'], 6 ),
				'count' => $count,
			);
		}

		$points = array();
		if ( ! empty( $single_ids ) ) {
			$cards = function_exists( 'wb_listora_get_listing_cards' )
				? wb_listora_get_listing_cards( $single_ids )
				: array();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$coords = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT listing_id, lat, lng FROM {$built['prefix']}search_index
					 WHERE listing_id IN (" . implode( ',', array_fill( 0, count( $single_ids ), '%d' ) ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					...$single_ids
				),
				ARRAY_A
			);

			foreach ( (array) $coords as $coord ) {
				$listing_id = (int) $coord['listing_id'];
				$card       = $cards[ $listing_id ] ?? array();

				$points[] = array(
					'id'             => $listing_id,
					'lat'            => round( (float) $coord['lat'], 6 ),
					'lng'            => round( (float) $coord['lng'], 6 ),
					'title'          => $card['title'] ?? get_the_title( $listing_id ),
					'link'           => $card['link'] ?? get_permalink( $listing_id ),
					'featured_image' => $card['featured_image'] ?? null,
					'rating'         => $card['rating'] ?? null,
					'listing_type'   => $card['listing_type'] ?? '',
				);
			}
		}

		return array(
			'clusters'  => $clusters,
			'points'    => $points,
			'total'     => $total,
			'precision' => $precision,
		);
	}

	/**
	 * The ORDER BY for a sort, as SQL over the search-index alias.
	 *
	 * Every sort this engine supports keys off a `search_index` column, so all
	 * of them — including distance and relevance — can be resolved by the
	 * database rather than by sorting a materialised candidate array in PHP.
	 * Mirrors {@see self::sort_results()} case for case; the two must stay in
	 * step, so change them together.
	 *
	 * A `s.listing_id DESC` tiebreak is appended to every clause. Without a
	 * deterministic total order, LIMIT/OFFSET can repeat or skip rows between
	 * pages whenever the sort column ties — which it does constantly here, as
	 * `avg_rating`, `price_value` and `is_featured` are all low-cardinality.
	 *
	 * @since 1.5.0
	 *
	 * @param array<string,mixed> $args          Parsed search args.
	 * @param bool                $has_relevance Whether the query produced a relevance score.
	 * @param array<int,mixed>    $order_params  Receives bound params for the clause.
	 * @return string ORDER BY body (without the `ORDER BY` keyword).
	 */
	private function sql_order_clause( array $args, $has_relevance, array &$order_params ) {
		$order_params = array();
		$tiebreak     = ', s.listing_id DESC';

		switch ( $args['sort'] ) {
			case 'relevance':
				// Only meaningful on the FULLTEXT path; otherwise fall through to
				// the default ordering rather than ordering by nothing.
				if ( $has_relevance ) {
					return 'relevance_score DESC' . $tiebreak;
				}
				break;

			case 'newest':
				return 's.created_at DESC' . $tiebreak;

			case 'rating':
				return 's.avg_rating DESC' . $tiebreak;

			case 'distance':
				if ( ! empty( $args['lat'] ) && ! empty( $args['lng'] ) ) {
					$expr           = Geo_Query::distance_sql( 's', $args['radius_unit'] ?? 'km' );
					$order_params[] = (float) $args['lat'];
					$order_params[] = (float) $args['lat'];
					$order_params[] = (float) $args['lng'];
					return $expr . ' ASC' . $tiebreak;
				}
				break;

			case 'price_asc':
				return 's.price_value ASC' . $tiebreak;

			case 'price_desc':
				return 's.price_value DESC' . $tiebreak;

			case 'most_reviewed':
				return 's.review_count DESC' . $tiebreak;

			case 'alphabetical':
				// search_index carries the title, so this needs no join back to
				// wp_posts — which is exactly what the PHP path pre-loads it for.
				return 's.title ASC' . $tiebreak;
		}

		// 'featured' and the default: featured first, then rating.
		return 's.is_featured DESC, s.avg_rating DESC' . $tiebreak;
	}

	/**
	 * Whether this query needs the whole candidate set held in memory.
	 *
	 * These narrowing steps run in PHP after phase 1, and facets aggregate over
	 * the full candidate list — none can be answered from a single page, so
	 * those queries keep the materialising path.
	 *
	 * @since 1.5.0
	 *
	 * @param array<string,mixed> $args Parsed search args.
	 * @return bool
	 */
	private function needs_materialised_candidates( array $args ) {
		return ! empty( $args['open_now'] )
			|| ! empty( $args['date_filter'] )
			|| ! empty( $args['date_from'] )
			|| ! empty( $args['date_to'] )
			|| ! empty( $args['category'] )
			|| ! empty( $args['location'] )
			|| ! empty( $args['features'] )
			|| ! empty( $args['field_filters'] )
			|| ! empty( $args['facets'] );
	}

	/**
	 * Resolve a page of results entirely in SQL.
	 *
	 * The materialising path caps the candidate set at MAX_PHASE_1_CANDIDATES,
	 * counts it with `count()` and slices it with `array_slice()`. On an
	 * unfiltered browse that makes `total` understate the directory and puts the
	 * oldest listings out of reach: verified on 5,515 listings, `/search`
	 * reported 5,000 across 250 pages, and the oldest listing was absent from
	 * the candidate set entirely.
	 *
	 * This path answers the same query with a dedicated COUNT(*) and a
	 * database-side ORDER BY + LIMIT/OFFSET, so `total` is the truth and every
	 * row is reachable, at constant memory regardless of directory size.
	 *
	 * Used only when nothing downstream needs the full candidate array — see
	 * {@see self::needs_materialised_candidates()}.
	 *
	 * @since 1.5.0
	 *
	 * @param array<string,mixed> $args Parsed search args.
	 * @return array{listing_ids:int[],total:int,pages:int,distances:array<int,float>}
	 */
	private function sql_paginated_candidates( array $args ) {
		global $wpdb;

		$built  = $this->build_candidate_query( $args );
		$prefix = $built['prefix'];

		// COUNT(*) binds the WHERE params only — the SELECT-side MATCH param
		// does not exist on a count query.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}search_index s WHERE {$built['where_sql']}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$built['params']
			)
		);

		$per_page = max( 1, (int) $args['per_page'] );
		$pages    = (int) ceil( $total / $per_page );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		if ( 0 === $total || $offset >= $total ) {
			return array(
				'listing_ids' => array(),
				'total'       => $total,
				'pages'       => $pages,
				'distances'   => array(),
			);
		}

		$order_params = array();
		$order_sql    = $this->sql_order_clause( $args, $built['has_relevance'], $order_params );

		// Placeholder order follows SQL text order: SELECT, WHERE, ORDER BY, LIMIT.
		$page_params = array_merge(
			$built['select_params'],
			$built['params'],
			$order_params,
			array( $per_page, $offset )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$built['select']} FROM {$prefix}search_index s WHERE {$built['where_sql']} ORDER BY {$order_sql} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$page_params
			),
			ARRAY_A
		);

		$listing_ids = array();
		$distances   = array();
		$has_centre  = ! empty( $args['lat'] ) && ! empty( $args['lng'] );

		foreach ( (array) $rows as $row ) {
			$id            = (int) $row['listing_id'];
			$listing_ids[] = $id;

			// Distances for the PAGE only, not the whole candidate set — same
			// values, a fraction of the work.
			if ( $has_centre && null !== $row['lat'] && null !== $row['lng'] ) {
				$distances[ $id ] = Geo_Query::haversine_distance(
					(float) $args['lat'],
					(float) $args['lng'],
					(float) $row['lat'],
					(float) $row['lng'],
					$args['radius_unit'] ?? 'km'
				);
			}
		}

		return array(
			'listing_ids' => $listing_ids,
			'total'       => $total,
			'pages'       => $pages,
			'distances'   => $distances,
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

		$built         = $this->build_candidate_query( $args );
		$prefix        = $built['prefix'];
		$select        = $built['select'];
		$select_params = $built['select_params'];
		$where_sql     = $built['where_sql'];
		$params        = $built['params'];
		$has_relevance = $built['has_relevance'];

		// Order the candidate set so the LIMIT cap below is DETERMINISTIC.
		// - Keyword search: order by FULLTEXT relevance so the cap keeps the
		//   most relevant rows (we lose long-tail matches, not the head).
		// - Non-keyword: order by listing_id DESC. Newer listings win the cap;
		//   PHP-side sort_results() then re-orders by the user's chosen sort.
		// In both cases the cap is a safety ceiling — production results
		// almost always narrow well below MAX_PHASE_1_CANDIDATES via filters.
		if ( $has_relevance ) {
			// relevance_score is only SELECTed on the FULLTEXT path; the LIKE
			// fallback and non-keyword queries order by recency instead.
			$order_sql = ' ORDER BY relevance_score DESC, s.listing_id DESC';
		} else {
			$order_sql = ' ORDER BY s.listing_id DESC';
		}

		// Merge params in SQL placeholder order: SELECT params first, then
		// WHERE params, then the LIMIT cap.
		$all_params   = array_merge( $select_params, $params );
		$all_params[] = self::MAX_PHASE_1_CANDIDATES;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT {$select} FROM {$prefix}search_index s WHERE {$where_sql}{$order_sql} LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
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

		/*
		 * "Open now" has to be evaluated in EACH LISTING'S OWN timezone, and it
		 * has to understand a span that crosses midnight. The previous
		 * implementation did neither, and was wrong on every site:
		 *
		 *  1. It compared against `current_time()` — the SITE's clock. A New
		 *     York venue on a UTC site was judged in UTC.
		 *  2. Its predicate was `open_time <= now AND close_time >= now`, which
		 *     CANNOT be true for an overnight span: a venue open 06:00–01:00
		 *     has close_time (01:00) < open_time (06:00), so it read CLOSED all
		 *     day, every day.
		 *  3. It only looked at today's row, so a span that began YESTERDAY and
		 *     is still running (00:30 now, opened 06:00 yesterday, closes 01:00
		 *     today) was missed even once (2) was fixed.
		 *
		 * The timezone lives on the geo row (`{prefix}geo.timezone`), not the
		 * hours row, so group the candidates by their effective timezone and
		 * evaluate each group at its own local day+time. Listings with no
		 * timezone fall back to the site's — the old single-timezone behaviour,
		 * which stays correct for a single-timezone directory.
		 */
		$tz_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, timezone FROM {$prefix}geo WHERE listing_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				...$ids
			),
			ARRAY_A
		);

		$site_tz = wp_timezone_string();
		$by_tz   = array();

		foreach ( $ids as $id ) {
			$by_tz[ $site_tz ][] = (int) $id;
		}

		foreach ( $tz_rows as $tz_row ) {
			$tz = trim( (string) $tz_row['timezone'] );
			// Empty AND the bare 'UTC' sentinel (schema/index default for a
			// listing with no explicit zone) stay in the site-timezone bucket,
			// so Open-now matches the detail badge + REST resolver instead of
			// silently evaluating those listings against UTC.
			if ( '' === $tz || 'UTC' === $tz ) {
				continue;
			}

			$lid = (int) $tz_row['listing_id'];

			// Move it out of the site-default bucket into its own.
			$pos = array_search( $lid, $by_tz[ $site_tz ], true );
			if ( false !== $pos ) {
				unset( $by_tz[ $site_tz ][ $pos ] );
			}

			$by_tz[ $tz ][] = $lid;
		}

		$open_ids = array();

		foreach ( $by_tz as $tz => $tz_ids ) {
			$tz_ids = array_values( array_filter( $tz_ids ) );
			if ( empty( $tz_ids ) ) {
				continue;
			}

			try {
				$now = new \DateTime( 'now', new \DateTimeZone( $tz ) );
			} catch ( \Exception $e ) {
				// A malformed stored timezone must not fatal a public search.
				$now = new \DateTime( 'now', wp_timezone() );
			}

			$now_day   = (int) $now->format( 'w' ); // 0=Sun..6=Sat — matches day_of_week.
			$now_time  = $now->format( 'H:i:s' );
			$prev_day  = ( $now_day + 6 ) % 7;
			$tz_places = implode( ',', array_fill( 0, count( $tz_ids ), '%d' ) );

			/*
			 * Four ways to be open right now:
			 *   a) today, flagged 24h
			 *   b) today, a normal span containing now   (close > open)
			 *   c) today, an overnight span already started (close <= open, now >= open)
			 *   d) yesterday, an overnight span still running (close <= open, now < close)
			 *
			 * `close_time > %s` not `>=`: at exactly closing time it is shut.
			 */
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$sql = $wpdb->prepare(
				"SELECT DISTINCT listing_id FROM {$prefix}hours
				WHERE listing_id IN ({$tz_places})
				AND is_closed = 0
				AND (
					( day_of_week = %d AND is_24h = 1 )
					OR ( day_of_week = %d AND is_24h = 0 AND close_time > open_time AND open_time <= %s AND close_time > %s )
					OR ( day_of_week = %d AND is_24h = 0 AND close_time <= open_time AND open_time <= %s )
					OR ( day_of_week = %d AND is_24h = 0 AND close_time <= open_time AND close_time > %s )
				)",
				...array_merge(
					$tz_ids,
					array(
						$now_day,
						$now_day,
						$now_time,
						$now_time,
						$now_day,
						$now_time,
						$prev_day,
						$now_time,
					)
				)
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$open_ids = array_merge( $open_ids, $wpdb->get_col( $sql ) );
		}

		$open_ids = array_map( 'intval', array_unique( $open_ids ) );

		// Preserve the caller's ordering — the candidate set is already sorted.
		return array_values( array_intersect( array_map( 'intval', $ids ), $open_ids ) );
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

		// Category filter — accepts either a numeric term ID (from a
		// select/autocomplete) or a slug/name string (typed in the
		// search bar). Resolves the string to a term ID before
		// dispatching to filter_by_taxonomy, which is integer-only.
		if ( ! empty( $args['category'] ) ) {
			$category_term_id = $this->resolve_term_id( $args['category'], 'listora_listing_cat' );
			if ( $category_term_id > 0 ) {
				$ids = $this->filter_by_taxonomy( $ids, 'listora_listing_cat', $category_term_id );
			} else {
				// Unknown category → zero matches (don't silently
				// fall back to "all listings", which is what the
				// integer-coerced contract used to do).
				$ids = array();
			}
		}

		// Location filter — same dual contract as category, plus a
		// geo-text fallback for free-form input.
		//
		// Order matters: try the taxonomy first (cheap term lookup +
		// indexed term_relationships join), then fall back to LIKE
		// against the geo table when (a) no term matches the typed
		// string, or (b) the matching term has zero listings linked
		// to it. Without (b) a typed "Brooklyn" silently returns
		// nothing on a fresh install where the term tree is seeded
		// but listings are pinned by lat/lng + city, not by term.
		if ( ! empty( $args['location'] ) && ! empty( $ids ) ) {
			$location_term_id = $this->resolve_term_id( $args['location'], 'listora_listing_location' );
			$matched          = array();

			if ( $location_term_id > 0 ) {
				$matched = $this->filter_by_taxonomy( $ids, 'listora_listing_location', $location_term_id );
			}

			if ( empty( $matched ) ) {
				$matched = $this->filter_by_geo_text( $ids, (string) $args['location'] );
			}

			$ids = $matched;
		}

		// Features filter (must have ALL selected features) — same
		// dual contract as category/location. Accepts a comma- or
		// space-separated list of slugs / numeric IDs (the search bar
		// posts slugs from checkboxes; an integer term ID also works
		// for clients that already resolved to one). Each entry is
		// resolved via resolve_term_id so a typo or unknown slug
		// fail-closes the filter (correct) instead of silently letting
		// every listing through.
		if ( ! empty( $args['features'] ) ) {
			$feature_input = is_array( $args['features'] )
				? $args['features']
				: preg_split( '/[\s,]+/', (string) $args['features'], -1, PREG_SPLIT_NO_EMPTY );

			foreach ( (array) $feature_input as $feature_value ) {
				$feature_term_id = $this->resolve_term_id( $feature_value, 'listora_listing_feature' );
				if ( $feature_term_id > 0 ) {
					$ids = $this->filter_by_taxonomy( $ids, 'listora_listing_feature', $feature_term_id );
				} else {
					$ids = array();
				}
			}
		}

		return $ids;
	}

	/**
	 * Resolve a category/location filter value to a term ID.
	 *
	 * Accepts either a numeric ID (already a term), a slug, or a
	 * human-readable name. Numeric strings are treated as term IDs
	 * if a term with that ID exists in the taxonomy; otherwise they
	 * fall through to slug/name resolution (so a term literally
	 * named "10001" still matches by name, not by ID 10001).
	 *
	 * @param mixed  $value    Term ID, slug, or name.
	 * @param string $taxonomy Taxonomy slug.
	 * @return int Term ID, or 0 when nothing matches.
	 */
	private function resolve_term_id( $value, $taxonomy ) {
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			$candidate = (int) $value;
			if ( $candidate > 0 ) {
				$term = get_term( $candidate, $taxonomy );
				if ( $term && ! is_wp_error( $term ) ) {
					return (int) $term->term_id;
				}
			}
		}

		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return 0;
		}

		$by_slug = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
		if ( $by_slug && ! is_wp_error( $by_slug ) ) {
			return (int) $by_slug->term_id;
		}

		$by_name = get_term_by( 'name', $value, $taxonomy );
		if ( $by_name && ! is_wp_error( $by_name ) ) {
			return (int) $by_name->term_id;
		}

		return 0;
	}

	/**
	 * Filter listing IDs whose geo row matches a free-form text query.
	 *
	 * The location search bar accepts plain text — a city, state,
	 * zip, country, or partial address — without requiring a matching
	 * taxonomy term. We match LIKE against the indexed text columns
	 * on listora_geo so a typed "Brooklyn" or "10001" actually narrows
	 * results, not silently passes through.
	 *
	 * @param int[]  $ids   Candidate post IDs (already narrowed to publish + visible).
	 * @param string $query Free-form location text.
	 * @return int[] Subset of $ids whose geo row contains $query in any text column.
	 */
	private function filter_by_geo_text( array $ids, $query ) {
		global $wpdb;

		$query = trim( $query );
		if ( '' === $query || empty( $ids ) ) {
			return array();
		}

		$table        = $wpdb->prefix . 'listora_geo';
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$like         = '%' . $wpdb->esc_like( $query ) . '%';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT listing_id FROM {$table}
			WHERE listing_id IN ({$placeholders})
			AND (
				city        LIKE %s OR
				state       LIKE %s OR
				country     LIKE %s OR
				postal_code LIKE %s OR
				address     LIKE %s
			)",
			...array_merge( $ids, array( $like, $like, $like, $like, $like ) )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$matched = $wpdb->get_col( $sql );

		return array_map( 'intval', $matched );
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
		if ( empty( $candidate_ids ) ) {
			return array();
		}

		// Category and feature facets are taxonomy-wide — they need candidates,
		// not a listing type. Only the custom-FIELD facets below are per-type,
		// because the filterable field set is defined on the type.
		//
		// This whole method used to return early whenever `type` was empty, so
		// an untyped query — which is the default browse state, and what a
		// native client asks for first — got `facets: {}` and had to fall back
		// to global listing-type counts that ignore the current query.
		$registry   = \WBListora\Core\Listing_Type_Registry::instance();
		$type       = ! empty( $args['type'] ) ? $registry->get( $args['type'] ) : null;
		$filterable = $type ? $type->get_filterable_fields() : array();

		if ( empty( $filterable ) ) {
			// No type, or a type with nothing filterable: taxonomy facets still
			// stand on their own.
			return $this->add_taxonomy_facets( array(), $candidate_ids, $args );
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
	 * Embeds the listings-group last-changed incrementor so the key
	 * auto-orphans whenever any listing/review write fires. No manual
	 * `delete_transient` / LIKE-DELETE needed — see Cache::bump_listings()
	 * and SKILL.md Part 2.7.
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
		$base = "listora_search_{$type}_{$hash}";

		// Append the listings-group incrementor so writes orphan keys.
		if ( class_exists( '\\WBListora\\Core\\Cache' ) ) {
			return \WBListora\Core\Cache::key( \WBListora\Core\Cache::GROUP_LISTINGS, $base );
		}

		return $base;
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
