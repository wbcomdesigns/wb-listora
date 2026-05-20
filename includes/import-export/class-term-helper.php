<?php
/**
 * Term Helper — shared taxonomy-term setter for every Listora importer.
 *
 * Replaces 3 byte-identical copies of `set_taxonomy_terms()` that lived
 * in `class-geojson-importer.php` (Free), `class-json-importer.php`
 * (Free), and `class-visual-importer.php` (Pro). Per the 2026-05-18
 * migrator-consolidation plan (plan/migrator-consolidation-1.1.0.md
 * §Phase 2), both Free's universal file importers AND Pro's competitor
 * migrators consume this single canonical implementation.
 *
 * @package WBListora\ImportExport
 * @since   1.1.0
 */

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

/**
 * Static helper for assigning taxonomy terms to a listing.
 *
 * `final` so consumers can't subclass — there's a single canonical
 * implementation per design, and consumers route through the static
 * method.
 */
final class Term_Helper {

	/**
	 * Set taxonomy terms on a listing, creating terms that do not yet exist.
	 *
	 * Used by Free's CSV / JSON / GeoJSON file importers AND by Pro's
	 * competitor migrators + visual importer (Pro requires Free at
	 * runtime, so `\WBListora\ImportExport\Term_Helper` is always callable
	 * from Pro).
	 *
	 * Term names are passed through `sanitize_text_field()` before lookup.
	 * Missing terms are created via `wp_insert_term()`. Existing terms are
	 * matched by name (case-insensitive per WP's `term_exists()`).
	 *
	 * @since 1.1.0
	 *
	 * @param int      $post_id  Listing post ID.
	 * @param string[] $terms    Array of term names (whitespace-sensitive but case-insensitive on match).
	 * @param string   $taxonomy Taxonomy slug.
	 * @return int[] Resolved term IDs (after any creates).
	 */
	public static function set_terms( int $post_id, array $terms, string $taxonomy ): array {
		$term_ids = array();

		foreach ( $terms as $term_name ) {
			$term_name = sanitize_text_field( $term_name );
			if ( empty( $term_name ) ) {
				continue;
			}

			$existing = term_exists( $term_name, $taxonomy );

			if ( ! $existing ) {
				$existing = wp_insert_term( $term_name, $taxonomy );
			}

			if ( ! is_wp_error( $existing ) ) {
				$term_ids[] = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}

		return $term_ids;
	}

	/**
	 * Build and assign hierarchical Country > State > City location terms from
	 * an address array.
	 *
	 * The canonical way to populate the `listora_listing_location` taxonomy so
	 * a listing is reachable via the location filter. Reads `country`, `state`,
	 * and `city` from the same address array that becomes the `_listora_address`
	 * meta and the geo-table row, so the location terms always agree with the
	 * map coordinates and displayed address. Each level is created as a child of
	 * the previous (state under country, city under state); a missing level
	 * stops the chain (a city cannot be parented to a missing state).
	 *
	 * Used by Free's demo seeder AND by Pro's Google Places + visual importers
	 * (Pro requires Free at runtime). Idempotent — existing terms are reused.
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $post_id Listing post ID.
	 * @param array<string, mixed> $address Address array with optional `country`/`state`/`city` keys.
	 * @return int[] Assigned location term IDs (country, then state, then city).
	 */
	public static function set_location_terms( int $post_id, array $address ): array {
		$taxonomy = 'listora_listing_location';
		$term_ids = array();
		$parent   = 0;

		foreach ( array( 'country', 'state', 'city' ) as $level ) {
			$name = isset( $address[ $level ] ) ? sanitize_text_field( (string) $address[ $level ] ) : '';
			if ( '' === $name ) {
				break;
			}

			$existing = $parent
				? term_exists( $name, $taxonomy, $parent )
				: term_exists( $name, $taxonomy );

			if ( ! $existing ) {
				$existing = wp_insert_term( $name, $taxonomy, $parent ? array( 'parent' => $parent ) : array() );
			}

			if ( is_wp_error( $existing ) ) {
				break;
			}

			$parent     = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
			$term_ids[] = $parent;
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}

		return $term_ids;
	}
}
