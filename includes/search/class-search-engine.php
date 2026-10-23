<?php
/**
 * Search Engine — orchestrates the two-phase search.
 *
 * @package WBListora\Search
 */

namespace WBListora\Search;

use WBListora\Contracts\Search_Engine_Interface;
use WBListora\Core\Cache;

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
	 * Arguments that narrow the result set. Paging, sort, facets and units do
	 * not; nor does anything else on the URL (utm_source and the like). A
	 * custom-field filter only applies with a `type`, which is itself here.
	 *
	 * @since 2.0.0
	 */
	/**
	 * InnoDB's default FULLTEXT stop words of 3+ letters (INNODB_FT_DEFAULT_STOPWORD).
	 */
	const FT_STOPWORDS = array( 'about', 'are', 'com', 'for', 'from', 'how', 'that', 'the', 'this', 'was', 'what', 'when', 'where', 'who', 'will', 'with', 'und', 'www' );

	const FILTER_KEYS = array( 'keyword', 'author', 'type', 'category', 'location', 'features', 'tags', 'lat', 'lng', 'bounds', 'min_rating', 'open_now', 'featured_only', 'verified_only', 'field_filters', 'date_filter', 'date_from', 'date_to' );

	/**
	 * Most listing IDs read into PHP in one query: the facet sample, and each
	 * batch of the Open-now scan. Results, totals and pages are never capped;
	 * the database counts and pages them.
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

		// Check transient cache. Gated on the same TTL as the write: a site
		// that turned caching off must not keep serving the permanent rows a
		// pre-1.6.0 install wrote while TTL was 0.
		$cache_key = $this->build_cache_key( $args );
		if ( Cache::ttl( 'search_cache_ttl', 15 ) > 0 ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached ) {
				return $cached;
			}
		}

		// Open now resolves each listing's timezone in PHP, so it is answered
		// first and reaches the SQL below as a list of ids.
		if ( ! empty( $args['open_now'] ) ) {
			$args['_open_ids'] = $this->open_now_listing_ids( $args );
		}

		// Every filter is a WHERE predicate, so the database counts, sorts and
		// pages the whole filtered set.
		$paged  = $this->sql_paginated_candidates( $args );
		$facets = empty( $args['facets'] ) ? array() : $this->phase_4_facets( $this->facet_candidate_ids( $args ), $args );

		$result = array(
			'listing_ids' => $paged['listing_ids'],
			'total'       => $paged['total'],
			'pages'       => $paged['pages'],
			'facets'      => $facets,
			'distances'   => $paged['distances'],
		);

		$result = $this->cache_result( $cache_key, $result, $args );
		$this->fire_search_resolved( $args, $result );

		return $result;
	}

	/**
	 * Fire the post-resolution extensibility action.
	 *
	 * Every caller (REST, block renders, `wb_listora_service( 'search_engine' )`)
	 * flows through {@see self::search()}, so this covers all paths. It is a
	 * read-only action fired after caching, unlike the REST-only
	 * `wb_listora_search_results` filter.
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
		// engine raw (editor JS min constraints don't bind the server):
		// page/per_page of 0 fatals on the page-count
		// division below; negatives corrupt the array_slice offset.
		$args['page']     = max( 1, (int) $args['page'] );
		$args['per_page'] = max( 1, (int) $args['per_page'] );

		/**
		 * Filter the parsed search arguments.
		 *
		 * The seam an extension actually needs. Everything that reads this
		 * engine goes through here - `search()`, the map's cluster query, and
		 * the listing-grid, listing-map and listing-featured block renders -
		 * so one listener scopes them all at once.
		 *
		 * There is an older `wb_listora_search_args` filter, and it is NOT this
		 * one: it lives in the REST controller and carries a `$request`, so the
		 * three blocks that call the engine directly bypass it entirely. An
		 * extension hooking that filter changed the REST route and left every
		 * grid on the site unfiltered.
		 *
		 * Fires BEFORE the cache key is built, so a listener that narrows the
		 * results gets its own cache entry rather than poisoning the shared one.
		 *
		 * @since 1.8.0
		 *
		 * @param array<string, mixed> $args Parsed and defaulted search args.
		 */
		return (array) apply_filters( 'wb_listora_search_parse_args', $args );
	}

	/**
	 * Convert a user keyword into MySQL FULLTEXT BOOLEAN MODE syntax.
	 *
	 * Each token gets `+` (BOOLEAN MODE defaults to OR) and `*` (prefix
	 * match), plus the quoted phrase so exact matches rank higher. Operator
	 * characters are stripped so input cannot change the semantics or break
	 * the parser, and tokens under 3 chars are dropped (InnoDB's default
	 * `innodb_ft_min_token_size`), since they would match nothing.
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
				// "no results" when one token is too short. InnoDB also never
				// indexes its default stop words, so a required "+the*" would
				// make "the best pizza" match nothing.
				return mb_strlen( (string) $t ) >= 3 && ! in_array( mb_strtolower( (string) $t ), self::FT_STOPWORDS, true );
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
	 * ONE source of truth for the candidate query: the result page
	 * ({@see self::sql_paginated_candidates()}), the facet sample, the Open-now
	 * scan and the map clusters all call this, so they never disagree on which
	 * rows match. Every filter is a predicate here.
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

		$this->add_taxonomy_where( $args, $where, $params );
		$this->add_field_where( $args, $where, $params );
		$this->add_date_where( $args, $where, $params );

		if ( isset( $args['_open_ids'] ) ) {
			$open_ids = (array) $args['_open_ids'];
			$where[]  = $open_ids ? 's.listing_id IN (' . implode( ',', array_fill( 0, count( $open_ids ), '%d' ) ) . ')' : '1 = 0';
			array_push( $params, ...$open_ids );
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

			// The box's corners lie outside the radius.
			$where[] = Geo_Query::distance_sql( 's', $args['radius_unit'] ) . ' <= %f';
			array_push( $params, (float) $args['lat'], (float) $args['lat'], (float) $args['lng'], (float) $args['radius'] );
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
		/**
		 * Filter the WHERE clauses of the candidate query.
		 *
		 * The ARRAY, not the joined string, so a listener can append a
		 * predicate without having to parse what is already there. Every
		 * placeholder you add must have a matching value appended through
		 * `wb_listora_search_where_params`, in the same order - the two filters
		 * are one seam in two halves.
		 *
		 * @since 1.8.0
		 *
		 * @param string[]             $where SQL fragments, joined with AND.
		 * @param array<string, mixed> $args  Parsed search args.
		 */
		$where = (array) apply_filters( 'wb_listora_search_where_clauses', $where, $args );

		/**
		 * Filter the bound parameters for the WHERE clauses.
		 *
		 * @since 1.8.0
		 *
		 * @param array<int, mixed>    $params Values bound to the placeholders.
		 * @param array<string, mixed> $args   Parsed search args.
		 */
		$params = (array) apply_filters( 'wb_listora_search_where_params', $params, $args );

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
	 * A map cannot page, so the database groups the whole result set by rounded
	 * coordinates at constant cost. Single-listing cells come back as points
	 * (tappable); the rest as counted clusters at their members' centroid.
	 * Uses {@see self::build_candidate_query()}, so counts match `/search`.
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
		$zoom      = $args['zoom'] ?? 10;
		$args = $this->parse_args( $args );
		if ( ! empty( $args['open_now'] ) ) {
			$args['_open_ids'] = $this->open_now_listing_ids( $args );
		}
		$built     = $this->build_candidate_query( $args );
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

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API, only the site table prefix and fixed SQL fragments are interpolated, values bound by prepare()
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
				 GROUP BY cell_lat, cell_lng", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WHERE is fixed fragments with placeholders, values bound by prepare().
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

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API, table name is the site table prefix
			$coords = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT listing_id, lat, lng FROM {$built['prefix']}search_index
					 WHERE listing_id IN (" . implode( ',', array_fill( 0, count( $single_ids ), '%d' ) ) . ')', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN list is generated placeholders, values bound by prepare().
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
	 * A `s.listing_id DESC` tiebreak is always appended, because the sort
	 * columns are low-cardinality and LIMIT/OFFSET would otherwise repeat or
	 * skip rows between pages.
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
	 * Filter the ORDER BY body for a search, after the default is resolved.
	 *
	 * The return value is interpolated into SQL, so a listener owns its own
	 * safety: return column expressions over the `s` alias, never request data.
	 * Add placeholders by appending to `$order_params` in the same order.
	 *
	 * @since 1.8.0
	 *
	 * @param string               $order_by     ORDER BY body, no keyword.
	 * @param array<string, mixed> $args         Parsed search args.
	 * @param array<int, mixed>    $order_params Bound values, by reference.
	 * @return string
	 */
	private function filtered_order_clause( string $order_by, array $args, array &$order_params ): string {
		/** This filter is documented in this method's docblock. */
		$filtered = apply_filters( 'wb_listora_search_orderby', $order_by, $args, $order_params );

		return is_string( $filtered ) && '' !== trim( $filtered ) ? $filtered : $order_by;
	}

	/**
	 * Resolve a page of results entirely in SQL.
	 *
	 * COUNT(*) plus ORDER BY + LIMIT/OFFSET in the database, at constant
	 * memory, so `total` and the last page hold at any directory size.
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API, only the site table prefix and fixed SQL fragments are interpolated, values bound by prepare()
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}search_index s WHERE {$built['where_sql']}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WHERE is fixed fragments with placeholders, values bound by prepare().
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
		$order_sql    = $this->filtered_order_clause( $order_sql, $args, $order_params );

		// Placeholder order follows SQL text order: SELECT, WHERE, ORDER BY, LIMIT.
		$page_params = array_merge(
			$built['select_params'],
			$built['params'],
			$order_params,
			array( $per_page, $offset )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API, only the site table prefix and fixed SQL fragments are interpolated, values bound by prepare()
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT {$built['select']} FROM {$prefix}search_index s WHERE {$built['where_sql']} ORDER BY {$order_sql} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- SELECT, WHERE and ORDER BY are fixed fragments, values bound by prepare().
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
	 * The listing IDs facet counts are taken from.
	 *
	 * ponytail: the newest MAX_PHASE_1_CANDIDATES matches, so on a larger result
	 * the counts are a sample; a GROUP BY over the full WHERE is the upgrade if
	 * exact counts matter there.
	 *
	 * @param array<string,mixed> $args Parsed search args.
	 * @return int[]
	 */
	private function facet_candidate_ids( array $args ): array {
		global $wpdb;

		$built = $this->build_candidate_query( $args );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT s.listing_id FROM {$built['prefix']}search_index s WHERE {$built['where_sql']} ORDER BY s.listing_id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WHERE is fixed fragments with placeholders, values bound by prepare().
				...array_merge( $built['params'], array( self::MAX_PHASE_1_CANDIDATES ) )
			)
		);

		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Every listing in this search that is open right now.
	 *
	 * The Open-now rule resolves each listing's own timezone, through the
	 * `wb_listora_listing_timezone` filter, in PHP. So the matches are read in
	 * ID order, MAX_PHASE_1_CANDIDATES at a time, and each batch goes through
	 * that one rule. Only listings with an open hours row are read.
	 *
	 * @param array<string,mixed> $args Parsed search args.
	 * @return int[]
	 */
	private function open_now_listing_ids( array $args ): array {
		global $wpdb;

		unset( $args['_open_ids'] );
		$built = $this->build_candidate_query( $args );
		$open  = array();
		$after = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API
			$batch = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT s.listing_id FROM {$built['prefix']}search_index s WHERE {$built['where_sql']} AND s.listing_id > %d AND EXISTS ( SELECT 1 FROM {$built['prefix']}hours h WHERE h.listing_id = s.listing_id AND h.is_closed = 0 ) ORDER BY s.listing_id LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- WHERE is fixed fragments with placeholders, values bound by prepare().
					...array_merge( $built['params'], array( $after, self::MAX_PHASE_1_CANDIDATES ) )
				)
			);
			$batch = array_map( 'intval', (array) $batch );
			if ( empty( $batch ) ) {
				break;
			}

			$open  = array_merge( $open, \WBListora\Core\Business_Hours::open_now_ids( $batch ) );
			$after = (int) end( $batch );
		} while ( count( $batch ) === self::MAX_PHASE_1_CANDIDATES );

		return $open;
	}

	/**
	 * Add the category, location, feature and tag filters to the candidate WHERE.
	 *
	 * They run in SQL so the candidate cap, COUNT and LIMIT all see the filtered
	 * set; filtering in PHP after the cap hid every match older than the newest
	 * MAX_PHASE_1_CANDIDATES rows. Features and tags need ALL selected terms, a
	 * term includes its child terms, and an unknown term matches nothing.
	 *
	 * @param array<string,mixed> $args   Parsed search args.
	 * @param string[]            $where  WHERE fragments, appended to.
	 * @param array<int,mixed>    $params Bound values, appended to.
	 * @return void
	 */
	private function add_taxonomy_where( array $args, array &$where, array &$params ) {
		global $wpdb;

		$has_term = static function ( string $taxonomy, int $term_id ) use ( $wpdb, &$where, &$params ) {
			if ( $term_id <= 0 ) {
				$where[] = '1 = 0';
				return;
			}

			$term_ids = array( $term_id );
			$children = get_term_children( $term_id, $taxonomy );
			if ( ! is_wp_error( $children ) ) {
				$term_ids = array_merge( $term_ids, array_map( 'intval', $children ) );
			}

			$where[] = "EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				WHERE tr.object_id = s.listing_id AND tt.taxonomy = %s
				AND tt.term_id IN (" . implode( ',', array_fill( 0, count( $term_ids ), '%d' ) ) . ') )';
			array_push( $params, $taxonomy, ...$term_ids );
		};

		if ( ! empty( $args['category'] ) ) {
			$has_term( 'listora_listing_cat', $this->resolve_term_id( $args['category'], 'listora_listing_cat' ) );
		}

		// A location that names a term with listings filters by the term. Any
		// other text (a city, a postcode, a seeded term nobody is filed under)
		// matches the listing's address instead.
		if ( ! empty( $args['location'] ) ) {
			$location_id = $this->resolve_term_id( $args['location'], 'listora_listing_location' );
			if ( $location_id > 0 && $this->term_has_listings( $location_id, 'listora_listing_location' ) ) {
				$has_term( 'listora_listing_location', $location_id );
			} else {
				$text = trim( (string) $args['location'] );
				if ( '' === $text ) {
					$where[] = '1 = 0';
				} else {
					$like    = '%' . $wpdb->esc_like( $text ) . '%';
					$where[] = "EXISTS ( SELECT 1 FROM {$wpdb->prefix}listora_geo g WHERE g.listing_id = s.listing_id
						AND ( g.city LIKE %s OR g.state LIKE %s OR g.country LIKE %s OR g.postal_code LIKE %s OR g.address LIKE %s ) )";
					array_push( $params, $like, $like, $like, $like, $like );
				}
			}
		}

		$all_of = array(
			'features' => 'listora_listing_feature',
			'tags'     => 'listora_listing_tag',
		);
		foreach ( $all_of as $key => $taxonomy ) {
			if ( empty( $args[ $key ] ) ) {
				continue;
			}
			$values = is_array( $args[ $key ] )
				? $args[ $key ]
				: preg_split( '/[\s,]+/', (string) $args[ $key ], -1, PREG_SPLIT_NO_EMPTY );

			foreach ( (array) $values as $value ) {
				$has_term( $taxonomy, $this->resolve_term_id( $value, $taxonomy ) );
			}
		}
	}

	/**
	 * Add the custom-field filters to the candidate WHERE.
	 *
	 * Every filtered field must match; a list of values matches any of them;
	 * `min` / `max` bound the numeric value. A cleared value (`cuisine[]=`) is
	 * no filter.
	 *
	 * @param array<string,mixed> $args   Parsed search args.
	 * @param string[]            $where  WHERE fragments, appended to.
	 * @param array<int,mixed>    $params Bound values, appended to.
	 * @return void
	 */
	private function add_field_where( array $args, array &$where, array &$params ) {
		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'field_index';

		foreach ( (array) $args['field_filters'] as $field_key => $value ) {
			$is_range = is_array( $value ) && ( isset( $value['min'] ) || isset( $value['max'] ) );
			if ( is_array( $value ) && ! $is_range ) {
				$value = array_values( array_filter( array_map( 'strval', $value ), static fn ( string $v ): bool => '' !== $v ) );
			}
			if ( array() === $value || '' === $value || null === $value ) {
				continue;
			}

			$conds  = array( 'fi.field_key = %s' );
			$values = array( (string) $field_key );

			if ( $is_range ) {
				if ( isset( $value['min'] ) && '' !== $value['min'] ) {
					$conds[]  = 'fi.numeric_value >= %f';
					$values[] = (float) $value['min'];
				}
				if ( isset( $value['max'] ) && '' !== $value['max'] ) {
					$conds[]  = 'fi.numeric_value <= %f';
					$values[] = (float) $value['max'];
				}
			} elseif ( is_array( $value ) ) {
				$conds[] = 'fi.field_value IN (' . implode( ',', array_fill( 0, count( $value ), '%s' ) ) . ')';
				$values  = array_merge( $values, $value );
			} else {
				$conds[]  = 'fi.field_value = %s';
				$values[] = (string) $value;
			}

			$where[] = "EXISTS ( SELECT 1 FROM {$table} fi WHERE fi.listing_id = s.listing_id AND " . implode( ' AND ', $conds ) . ' )';
			array_push( $params, ...$values );
		}
	}

	/**
	 * Add the event date filters to the candidate WHERE.
	 *
	 * A `date_filter` (today, weekend, happening_now) wins over a from/to
	 * range. An event with no end date runs from its start date on, except for
	 * "happening now", which needs both.
	 *
	 * @param array<string,mixed> $args   Parsed search args.
	 * @param string[]            $where  WHERE fragments, appended to.
	 * @param array<int,mixed>    $params Bound values, appended to.
	 * @return void
	 */
	private function add_date_where( array $args, array &$where, array &$params ) {
		global $wpdb;

		$event = "EXISTS ( SELECT 1 FROM {$wpdb->postmeta} ps
			LEFT JOIN {$wpdb->postmeta} pe ON pe.post_id = ps.post_id AND pe.meta_key = '_listora_end_date'
			WHERE ps.post_id = s.listing_id AND ps.meta_key = '_listora_start_date' AND ps.meta_value != ''";

		// The event overlaps [ $from, $to ]: it starts by $to and has not ended before $from.
		$overlaps = static function ( string $from, string $to, bool $needs_end ) use ( $event, &$where, &$params ) {
			$ends    = $needs_end
				? "pe.meta_value != '' AND pe.meta_value >= %s"
				: "( pe.meta_value IS NULL OR pe.meta_value = '' OR pe.meta_value >= %s )";
			$where[] = "{$event} AND ps.meta_value <= %s AND {$ends} )";
			array_push( $params, $to, $from );
		};

		if ( ! empty( $args['date_filter'] ) ) {
			$today = current_time( 'Y-m-d' );
			switch ( $args['date_filter'] ) {
				case 'today':
					$overlaps( $today, $today, false );
					break;
				case 'weekend':
					$day_of_wk = (int) current_time( 'w' ); // 0=Sun, 6=Sat.
					if ( 0 === $day_of_wk ) {
						$saturday = gmdate( 'Y-m-d', strtotime( '-1 day', strtotime( $today ) ) );
					} elseif ( 6 === $day_of_wk ) {
						$saturday = $today;
					} else {
						$saturday = gmdate( 'Y-m-d', strtotime( '+' . ( 6 - $day_of_wk ) . ' days', strtotime( $today ) ) );
					}
					$overlaps( $saturday, gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( $saturday ) ) ), false );
					break;
				case 'happening_now':
					$now = current_time( 'Y-m-d H:i:s' );
					$overlaps( $now, $now, true );
					break;
			}
			return;
		}

		if ( ! empty( $args['date_from'] ) || ! empty( $args['date_to'] ) ) {
			$range = '';
			if ( ! empty( $args['date_from'] ) ) {
				$range   .= ' AND ps.meta_value >= %s';
				$params[] = sanitize_text_field( (string) $args['date_from'] );
			}
			if ( ! empty( $args['date_to'] ) ) {
				$range   .= ' AND ps.meta_value <= %s';
				$params[] = sanitize_text_field( (string) $args['date_to'] );
			}
			$where[] = "{$event}{$range} )";
		}
	}

	/**
	 * Whether a term, or any of its child terms, has published listings.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy slug.
	 * @return bool
	 */
	private function term_has_listings( int $term_id, string $taxonomy ): bool {
		$terms    = array( $term_id );
		$children = get_term_children( $term_id, $taxonomy );
		if ( ! is_wp_error( $children ) ) {
			$terms = array_merge( $terms, $children );
		}

		foreach ( $terms as $id ) {
			$term = get_term( (int) $id, $taxonomy );
			if ( $term instanceof \WP_Term && $term->count > 0 ) {
				return true;
			}
		}

		return false;
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
		// This method must not return early when `type` is empty: an untyped
		// query is the default browse state, and what a native client asks for
		// first, and `facets: {}` would force it to fall back to global
		// listing-type counts that ignore the current query.
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
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- site table prefix plus generated IN placeholders
			$sql = $wpdb->prepare(
				"SELECT field_key, field_value, COUNT(DISTINCT listing_id) as cnt
				FROM {$prefix}field_index
				WHERE listing_id IN ({$placeholders})
				AND field_key IN ({$key_placeholders})
				AND field_value != ''
				GROUP BY field_key, field_value
				ORDER BY field_key, cnt DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN lists are generated placeholders, values bound by prepare().
				...array_merge( $candidate_ids, $field_keys )
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API, query string came from prepare() above
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

		// No candidates means no facet counts. Without this the placeholder list
		// collapses to '' and the query emits `IN ()`.
		if ( empty( $candidate_ids ) ) {
			return $facets;
		}

		$taxonomies       = array( 'listora_listing_cat', 'listora_listing_feature', 'listora_listing_tag' );
		$placeholders     = implode( ',', array_fill( 0, count( $candidate_ids ), '%d' ) );
		$tax_placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );

		// Single query for all taxonomy facets instead of one per taxonomy.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- site table prefix plus generated IN placeholders
		$sql = $wpdb->prepare(
			"SELECT tt.taxonomy, t.slug, t.name, COUNT(DISTINCT tr.object_id) as cnt
			FROM {$wpdb->term_relationships} tr
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE tr.object_id IN ({$placeholders})
			AND tt.taxonomy IN ({$tax_placeholders})
			GROUP BY tt.taxonomy, t.term_id
			ORDER BY tt.taxonomy, cnt DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- IN lists are generated placeholders, values bound by prepare().
			...array_merge( $candidate_ids, $taxonomies )
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom plugin table, no WP API, query string came from prepare() above
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
	 * @return array<string, mixed> The result after `wb_listora_search_result`.
	 */
	private function cache_result( $key, array $result, array $args ) {
		/**
		 * Filter a search result before it is returned or cached.
		 *
		 * The last seam: use it to drop, reorder or annotate results that no
		 * argument or WHERE clause can express. Every caller goes through here
		 * - `search()`, the map's clusters, and the grid, map and featured
		 * blocks - so a listener cannot reach one surface and miss another.
		 *
		 * The filtered result is what gets cached, which is why the filter runs
		 * here rather than at each return: the cache key is built from the args
		 * AFTER `wb_listora_search_parse_args`, so a listener that narrows by an
		 * arg already has its own entry. A listener that varies on something
		 * NOT in the args - the current user, say - must add that to the args in
		 * `wb_listora_search_parse_args` too, or it will serve one visitor's
		 * results to another.
		 *
		 * @since 1.8.0
		 *
		 * @param array<string, mixed> $result Result array: listing_ids, total,
		 *                                     pages, facets, distances.
		 * @param array<string, mixed> $args   Parsed search args.
		 */
		$result = (array) apply_filters( 'wb_listora_search_result', $result, $args );

		$ttl = Cache::ttl( 'search_cache_ttl', 15 );

		// 0 disables caching; passing 0 through would write a never-expiring
		// transient. See Cache::ttl().
		if ( $ttl > 0 ) {
			set_transient( $key, $result, $ttl );
		}

		return $result;
	}
}
