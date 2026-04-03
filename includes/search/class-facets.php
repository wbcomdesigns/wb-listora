<?php
/**
 * Facets — calculates filter counts for search results.
 *
 * @package WBListora\Search
 */

namespace WBListora\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Provides cached facet calculations.
 * Used by Search_Engine::phase_4_facets but also available standalone.
 */
class Facets {

	/**
	 * Get cached facets for a search result set.
	 *
	 * @param string $type_slug    Listing type slug.
	 * @param int[]  $listing_ids  Candidate listing IDs.
	 * @return array Field key => [value => count] map.
	 */
	public static function get_cached( $type_slug, array $listing_ids ) {
		if ( empty( $listing_ids ) ) {
			return array();
		}

		$cache_key = 'listora_facets_' . $type_slug . '_' . md5( implode( ',', $listing_ids ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$facets = self::calculate( $type_slug, $listing_ids );

		$ttl = (int) wb_listora_get_setting( 'facet_cache_ttl', 30 ) * MINUTE_IN_SECONDS;
		set_transient( $cache_key, $facets, $ttl );

		return $facets;
	}

	/**
	 * Calculate facet counts.
	 *
	 * @param string $type_slug   Listing type slug.
	 * @param int[]  $listing_ids Listing IDs.
	 * @return array
	 */
	public static function calculate( $type_slug, array $listing_ids ) {
		$registry = \WBListora\Core\Listing_Type_Registry::instance();
		$type     = $registry->get( $type_slug );

		if ( ! $type ) {
			return array();
		}

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$facets = array();

		$placeholders = implode( ',', array_fill( 0, count( $listing_ids ), '%d' ) );

		// Field-based facets.
		foreach ( $type->get_filterable_fields() as $field ) {
			$ftype = $field->get_type();

			// Skip continuous/complex types.
			if ( in_array( $ftype, array( 'number', 'price', 'business_hours', 'map_location', 'gallery', 'wysiwyg' ), true ) ) {
				continue;
			}

			$fkey = $field->get_key();

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT field_value, COUNT(DISTINCT listing_id) as cnt
				FROM {$prefix}field_index
				WHERE listing_id IN ({$placeholders})
				AND field_key = %s AND field_value != ''
				GROUP BY field_value ORDER BY cnt DESC LIMIT 50",
				...array_merge( $listing_ids, array( $fkey ) )
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $sql, ARRAY_A );

			$facets[ $fkey ] = array();
			foreach ( $rows as $row ) {
				$facets[ $fkey ][ $row['field_value'] ] = (int) $row['cnt'];
			}
		}

		// Taxonomy facets (categories, features).
		$facets = array_merge( $facets, self::taxonomy_facets( $listing_ids ) );

		return $facets;
	}

	/**
	 * Calculate taxonomy-based facets.
	 *
	 * @param int[] $listing_ids Listing IDs.
	 * @return array
	 */
	private static function taxonomy_facets( array $listing_ids ) {
		global $wpdb;

		$facets       = array();
		$placeholders = implode( ',', array_fill( 0, count( $listing_ids ), '%d' ) );
		$taxonomies   = array(
			'listora_listing_cat'     => 'category',
			'listora_listing_feature' => 'feature',
		);

		foreach ( $taxonomies as $taxonomy => $facet_key ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$sql = $wpdb->prepare(
				"SELECT t.term_id, t.slug, t.name, COUNT(DISTINCT tr.object_id) as cnt
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
				INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
				WHERE tr.object_id IN ({$placeholders}) AND tt.taxonomy = %s
				GROUP BY t.term_id ORDER BY cnt DESC LIMIT 30",
				...array_merge( $listing_ids, array( $taxonomy ) )
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $sql, ARRAY_A );

			$facets[ $facet_key ] = array();
			foreach ( $rows as $row ) {
				$facets[ $facet_key ][ $row['slug'] ] = array(
					'id'    => (int) $row['term_id'],
					'name'  => $row['name'],
					'count' => (int) $row['cnt'],
				);
			}
		}

		return $facets;
	}

	/**
	 * Get numeric range for a field (min/max values across listings).
	 * Used for range slider bounds.
	 *
	 * @param string $field_key   Field key.
	 * @param int[]  $listing_ids Listing IDs (optional, empty = all).
	 * @return array { min: float, max: float }
	 */
	public static function get_range( $field_key, array $listing_ids = array() ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$where  = 'field_key = %s AND numeric_value IS NOT NULL';
		$params = array( $field_key );

		if ( ! empty( $listing_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $listing_ids ), '%d' ) );
			$where       .= " AND listing_id IN ({$placeholders})";
			$params       = array_merge( $params, $listing_ids );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT MIN(numeric_value) as min_val, MAX(numeric_value) as max_val
			FROM {$prefix}field_index WHERE {$where}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			...$params
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $sql, ARRAY_A );

		return array(
			'min' => $row ? (float) $row['min_val'] : 0,
			'max' => $row ? (float) $row['max_val'] : 0,
		);
	}
}
