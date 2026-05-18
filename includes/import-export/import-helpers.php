<?php
/**
 * Import helper functions — public extension surface.
 *
 * Pro consumes these functions instead of referencing internal helper
 * classes directly. The functions are the documented Free→Pro contract;
 * the implementation classes (`\WBListora\Import\Term_Helper` etc.) are
 * internal and may be refactored without breaking Pro.
 *
 * Per the architecture contract at
 * `audit/architecture/pro-coupling-contract.md`, Pro reaches Free's
 * services through:
 *   1. Documented hooks
 *   2. `\WBListora\Contracts\*` interfaces
 *   3. `wb_listora_service($name)` locator
 *   4. **Public extension functions** like the ones in this file
 *
 * Never direct refs to `\WBListora\Core\*` or `\WBListora\Import\*` etc.
 *
 * @package WBListora
 * @since   1.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'wb_listora_set_taxonomy_terms' ) ) {
	/**
	 * Set taxonomy terms on a listing, creating terms that do not yet exist.
	 *
	 * Used by Free's CSV / JSON / GeoJSON file importers AND by Pro's
	 * competitor migrators + visual importer. Free's universal-format
	 * importers may call `\WBListora\Import\Term_Helper::set_terms()`
	 * directly (Free is the owner of the class); Pro calls THIS function
	 * (the documented surface).
	 *
	 * Term names are passed through `sanitize_text_field()` before lookup.
	 * Missing terms are created via `wp_insert_term()`. Existing terms are
	 * matched by name (case-insensitive per WP's `term_exists()`).
	 *
	 * @since 1.1.0
	 *
	 * @param int      $post_id  Listing post ID.
	 * @param string[] $terms    Array of term names.
	 * @param string   $taxonomy Taxonomy slug.
	 * @return int[] Resolved term IDs (after any creates).
	 */
	function wb_listora_set_taxonomy_terms( int $post_id, array $terms, string $taxonomy ): array {
		return \WBListora\Import\Term_Helper::set_terms( $post_id, $terms, $taxonomy );
	}
}
