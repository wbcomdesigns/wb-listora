<?php
/**
 * Term Helper — shared taxonomy-term setter for every Listora importer.
 *
 * Replaces 3 byte-identical copies of `set_taxonomy_terms()` that lived
 * in `class-geojson-importer.php` (Free), `class-json-importer.php`
 * (Free), and `class-visual-importer.php` (Pro). Consolidated in 1.1.0:
 * both Free's universal file importers AND Pro's competitor
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

		// The country: resolved to its ISO code (from a geocoder's
		// country_code, or the name / code / alias written), so "US", "USA"
		// and "United States" are one term (card 10337180588).
		$written = isset( $address['country'] ) ? self::normalize_name( (string) $address['country'] ) : '';
		$code    = \WBListora\Core\Countries::code_for( isset( $address['country_code'] ) && '' !== trim( (string) $address['country_code'] ) ? (string) $address['country_code'] : $written );
		$parent  = self::country_term( $written, $code );
		if ( ! $parent ) {
			return array();
		}
		$term_ids[] = $parent;

		foreach ( array( 'state', 'city' ) as $level ) {
			$name = isset( $address[ $level ] ) ? self::normalize_name( (string) $address[ $level ] ) : '';
			if ( 'state' === $level ) {
				$name = \WBListora\Core\Countries::state_name( $name, $code );
				if ( '' === $name ) {
					// No region (Singapore; "Paris, France"): the city goes
					// straight under the country instead of being dropped.
					continue;
				}
			}
			if ( '' === $name ) {
				break;
			}

			$existing = term_exists( $name, $taxonomy, $parent );
			if ( ! $existing ) {
				$existing = wp_insert_term( $name, $taxonomy, array( 'parent' => $parent ) );
			}
			if ( is_wp_error( $existing ) ) {
				break;
			}

			$parent     = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
			$term_ids[] = $parent;
		}

		wp_set_object_terms( $post_id, $term_ids, $taxonomy );

		return $term_ids;
	}

	/**
	 * Location terms from one line of text, as import files write it:
	 * "City, State, Country" or "City, Country" (most specific first).
	 *
	 * The importers used to split the line and create each part as its own
	 * top-level term, so "New York, NY, USA" became three unrelated roots
	 * (card 10337180588). A single value is still treated as a plain term
	 * name, as before.
	 *
	 * @since 1.9.0
	 *
	 * @param int    $post_id Listing post ID.
	 * @param string $text    The location column.
	 * @return void
	 */
	public static function set_location_from_text( int $post_id, string $text ): void {
		$parts = array_values(
			array_filter(
				array_map( 'trim', explode( ',', $text ) ),
				static function ( $part ) {
					return '' !== $part;
				}
			)
		);

		/**
		 * Whether an import's comma-separated location is read as a place
		 * (City, State, Country) rather than a list of separate terms.
		 *
		 * Return false to keep the pre-1.9.0 behaviour of one term per part.
		 *
		 * @since 1.9.0
		 *
		 * @param bool   $hierarchy Default true.
		 * @param string $text      The location column.
		 * @param int    $post_id   Listing post ID.
		 */
		if ( count( $parts ) < 2 || ! apply_filters( 'wb_listora_import_location_as_place', true, $text, $post_id ) ) {
			self::set_terms( $post_id, $parts, 'listora_listing_location' );
			return;
		}
		$country = array_pop( $parts );
		$city    = array_shift( $parts );
		self::set_location_terms(
			$post_id,
			array(
				'country' => $country,
				'state'   => (string) array_shift( $parts ),
				'city'    => $city,
			)
		);
	}

	/**
	 * The country root term for an address, created when missing.
	 *
	 * A recognised country is found by its code (term meta), then by name
	 * among ROOT terms only - the unscoped lookup could reuse a state called
	 * Georgia as the country Georgia - and is created with its readable name.
	 * An unrecognised name is matched and created as written.
	 *
	 * @since 1.9.0
	 *
	 * @param string $written Country as written in the address.
	 * @param string $code    ISO code, or '' when not recognised.
	 * @return int Term ID, or 0.
	 */
	public static function country_term( $written, $code ) {
		$taxonomy = 'listora_listing_location';
		if ( '' !== $code ) {
			$found = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'parent'     => 0,
					'fields'     => 'ids',
					'number'     => 1,
					'meta_key'   => \WBListora\Core\Countries::TERM_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				)
			);
			if ( ! is_wp_error( $found ) && $found ) {
				return (int) $found[0];
			}
		}

		$name = '' !== $code ? \WBListora\Core\Countries::name( $code ) : $written;
		if ( '' === $name ) {
			return 0;
		}
		// An older root with this name (or the code as its name) predates the
		// code meta: adopt it rather than creating a second root.
		$existing = term_exists( $name, $taxonomy, 0 );
		if ( ! $existing && '' !== $code ) {
			$existing = term_exists( $code, $taxonomy, 0 );
		}
		if ( ! $existing ) {
			$existing = wp_insert_term( $name, $taxonomy );
		}
		if ( is_wp_error( $existing ) ) {
			return 0;
		}
		$term_id = (int) ( is_array( $existing ) ? $existing['term_id'] : $existing );
		if ( '' !== $code ) {
			update_term_meta( $term_id, \WBListora\Core\Countries::TERM_META, $code );
		}
		return $term_id;
	}
}
