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
	 * Normalize a term name before lookup or insertion.
	 *
	 * Decodes HTML entities (so a CSV cell exported as `B&amp;B` lands as
	 * `B&B`), strips slashes (defensive against `wp_slash`-wrapped input),
	 * then sanitize_text_field()s. Without the entity decode upstream callers
	 * that handed us already-escaped text would store the literal entity
	 * sequence in the term name — the term then renders as `B&amp;amp;B` in
	 * any HTML surface because the template's `esc_html()` re-escapes the
	 * stored `&` into `&amp;`. Basecamp #9927392446.
	 *
	 * @since 1.0.5
	 *
	 * @param string $raw Untrusted term name.
	 * @return string Normalized term name (may be empty).
	 */
	public static function normalize_name( string $raw ): string {
		$decoded = wp_specialchars_decode( $raw, ENT_QUOTES );
		$decoded = wp_unslash( $decoded );
		return sanitize_text_field( $decoded );
	}

	/**
	 * One-shot repair of existing terms that already carry HTML entities in
	 * their `name` column from earlier inserts. Runs once per site (guarded
	 * by the `wb_listora_term_entity_repair_done` option) on the first
	 * admin pageload after the 1.0.5 upgrade. Idempotent — re-running is a
	 * cheap option-read.
	 *
	 * Walks every Listora-owned taxonomy: listora_listing_cat,
	 * listora_listing_type, listora_listing_location, listora_listing_feature,
	 * listora_listing_tag, listora_service_cat. For each term whose raw name
	 * contains one of the regression patterns (`&amp;`, `&quot;`, `&#039;`,
	 * `&lt;`, `&gt;`), updates the term to the entity-decoded equivalent.
	 *
	 * Filterable via `wb_listora_repair_term_taxonomies` so site owners can
	 * extend (or skip) the list. Returns the number of terms repaired so
	 * tests can assert the migration ran.
	 *
	 * @since 1.0.5
	 *
	 * @return int Number of repaired terms (0 if migration already ran).
	 */
	public static function repair_entity_encoded_term_names(): int {
		if ( '1' === (string) get_option( 'wb_listora_term_entity_repair_done', '' ) ) {
			return 0;
		}

		/**
		 * Filters the list of Listora-owned taxonomies repaired on upgrade.
		 *
		 * @since 1.0.5
		 *
		 * @param string[] $taxonomies Taxonomy slugs.
		 */
		$taxonomies = (array) apply_filters(
			'wb_listora_repair_term_taxonomies',
			array(
				'listora_listing_cat',
				'listora_listing_type',
				'listora_listing_location',
				'listora_listing_feature',
				'listora_listing_tag',
				'listora_service_cat',
			)
		);

		global $wpdb;
		$repaired = 0;
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'number'     => 0,
				)
			);
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				continue;
			}
			foreach ( $terms as $term ) {
				$normalized = self::normalize_name( (string) $term->name );
				if ( '' === $normalized || $normalized === $term->name ) {
					continue;
				}
				// Direct $wpdb update — bypass wp_update_term() which routes the
				// new name through sanitize_term_field('name', ..., 'db') →
				// wp_filter_kses(), and KSES auto-encodes stray ampersands
				// (`B&B` → `B&amp;B`). That would re-create the very bug we're
				// repairing. We trust normalize_name() to have already produced
				// a safe value (decoded entities, stripped slashes,
				// sanitize_text_field()'d).
				$ok = $wpdb->update(
					$wpdb->terms,
					array( 'name' => $normalized ),
					array( 'term_id' => (int) $term->term_id ),
					array( '%s' ),
					array( '%d' )
				);
				if ( false !== $ok ) {
					clean_term_cache( (int) $term->term_id, $taxonomy );
					++$repaired;
				}
			}
		}

		update_option( 'wb_listora_term_entity_repair_done', '1', false );
		return $repaired;
	}

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
		$term_ids = self::resolve_terms( $terms, $taxonomy );

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, $taxonomy );
		}

		return $term_ids;
	}

	/**
	 * Resolve a list of term names to term IDs, creating missing terms, WITHOUT
	 * assigning them to any post.
	 *
	 * The lookup/insert/normalize core shared by {@see set_terms()} (which adds
	 * the post assignment) and by callers that perform their own
	 * `wp_set_object_terms()` afterwards (Free's competitor migrators resolve a
	 * full taxonomy => IDs map before a single assignment per taxonomy). Names
	 * are normalized via {@see normalize_name()} so the entity-decode fix
	 * (`B&amp;B` → `B&B`, Basecamp #9927392446) applies on every path.
	 *
	 * @since 1.1.0
	 *
	 * @param string[] $terms    Array of term names.
	 * @param string   $taxonomy Taxonomy slug.
	 * @return int[] Resolved term IDs (after any creates).
	 */
	public static function resolve_terms( array $terms, string $taxonomy ): array {
		$term_ids = array();

		foreach ( $terms as $term_name ) {
			$term_name = self::normalize_name( (string) $term_name );
			if ( '' === $term_name ) {
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
			$name = isset( $address[ $level ] ) ? self::normalize_name( (string) $address[ $level ] ) : '';
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
