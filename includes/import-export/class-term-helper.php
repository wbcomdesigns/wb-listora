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
 * @package WBListora\Import
 * @since   1.1.0
 */

namespace WBListora\Import;

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
	 * runtime, so `\WBListora\Import\Term_Helper` is always callable
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
}
