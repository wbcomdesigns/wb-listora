<?php
/**
 * Import helper functions — public extension surface.
 *
 * Pro consumes these functions instead of referencing internal helper
 * classes directly. The functions are the documented Free→Pro contract;
 * the implementation classes (`\WBListora\ImportExport\Term_Helper` etc.) are
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

// Bootstrap the background-import subsystem. This file is require_once'd at
// plugin load (see wb-listora.php), so binding the Action Scheduler handlers
// here keeps the wiring inside the import-export module without editing the
// central plugin bootstrap. init() is idempotent (add_action de-dupes the
// same callback), so a double-include is harmless.
add_action(
	'init',
	static function () {
		if ( class_exists( '\WBListora\ImportExport\Background_Import' ) ) {
			\WBListora\ImportExport\Background_Import::init();
		}
	},
	5
);

if ( ! function_exists( 'wb_listora_set_taxonomy_terms' ) ) {
	/**
	 * Set taxonomy terms on a listing, creating terms that do not yet exist.
	 *
	 * Used by Free's CSV / JSON / GeoJSON file importers AND by Pro's
	 * competitor migrators + visual importer. Free's universal-format
	 * importers may call `\WBListora\ImportExport\Term_Helper::set_terms()`
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
		return \WBListora\ImportExport\Term_Helper::set_terms( $post_id, $terms, $taxonomy );
	}
}

if ( ! function_exists( 'wb_listora_set_location_terms' ) ) {
	/**
	 * Assign hierarchical Country > State > City location terms to a listing
	 * from an address array.
	 *
	 * The canonical way to populate the `listora_listing_location` taxonomy so a
	 * listing is reachable via the location filter. Reads `country`/`state`/`city`
	 * from the same address array that becomes the `_listora_address` meta and the
	 * geo row, keeping location terms, map coordinates, and displayed address in
	 * agreement. Used by Free's demo seeder and Pro's Google Places + visual
	 * importers (the documented surface — Pro never calls Term_Helper directly).
	 *
	 * @since 1.1.0
	 *
	 * @param int                  $post_id Listing post ID.
	 * @param array<string, mixed> $address Address array with optional `country`/`state`/`city` keys.
	 * @return int[] Assigned location term IDs.
	 */
	function wb_listora_set_location_terms( int $post_id, array $address ): array {
		return \WBListora\ImportExport\Term_Helper::set_location_terms( $post_id, $address );
	}
}

if ( ! function_exists( 'wb_listora_queue_demo_import' ) ) {
	/**
	 * Queue a demo-pack import on Action Scheduler batches (group `wb-listora`).
	 *
	 * The documented surface for the setup wizard, CLI, and Pro to trigger a
	 * resumable demo import. Tiny single packs fall back to a synchronous run
	 * inside {@see \WBListora\ImportExport\Background_Import}. Poll progress via
	 * `wb_listora_get_import_progress()` or the REST endpoint
	 * `/{namespace}/v1/import/progress/{run_id}`.
	 *
	 * @since 1.2.0
	 *
	 * @param string[] $packs Demo pack slugs to run, in order.
	 * @return string The run_id (empty string if the subsystem is unavailable).
	 */
	function wb_listora_queue_demo_import( array $packs ): string {
		if ( ! class_exists( '\WBListora\ImportExport\Background_Import' ) ) {
			return '';
		}
		return \WBListora\ImportExport\Background_Import::queue_demo( $packs );
	}
}

if ( ! function_exists( 'wb_listora_queue_file_import' ) ) {
	/**
	 * Queue a large CSV/JSON file import on Action Scheduler batches.
	 *
	 * The source file is staged into the uploads dir so it survives past the
	 * request. Idempotent + resumable: a retried batch resumes from the stored
	 * cursor and skips rows already created this run.
	 *
	 * @since 1.2.0
	 *
	 * @param string                   $kind      'csv' | 'json'.
	 * @param string                   $file_path Readable path to the source file.
	 * @param string                   $type_slug Listing type slug for new listings.
	 * @param array<int|string,string> $mapping   Column/field mapping (CSV only).
	 * @param int                      $total     Pre-counted row total (for progress UI).
	 * @return string|\WP_Error The run_id, or WP_Error if the file is unusable.
	 */
	function wb_listora_queue_file_import( string $kind, string $file_path, string $type_slug, array $mapping = array(), int $total = 0 ) {
		if ( ! class_exists( '\WBListora\ImportExport\Background_Import' ) ) {
			return new \WP_Error( 'listora_bg_import_unavailable', __( 'Background import is not available.', 'wb-listora' ) );
		}
		return \WBListora\ImportExport\Background_Import::queue_file( $kind, $file_path, $type_slug, $mapping, $total );
	}
}

if ( ! function_exists( 'wb_listora_get_import_progress' ) ) {
	/**
	 * Read the progress of a background-import run.
	 *
	 * @since 1.2.0
	 *
	 * @param string $run_id Run identifier returned by a queue_* function.
	 * @return array<string,mixed>|null Progress payload, or null if unknown.
	 */
	function wb_listora_get_import_progress( string $run_id ): ?array {
		if ( ! class_exists( '\WBListora\ImportExport\Background_Import' ) ) {
			return null;
		}
		return \WBListora\ImportExport\Background_Import::get_progress( $run_id );
	}
}
