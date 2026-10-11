<?php
/**
 * Import helper functions — public extension surface.
 *
 * These functions are the documented Free→Pro contract: Pro calls them
 * instead of internal classes such as `\WBListora\ImportExport\Term_Helper`,
 * which may be refactored freely. See
 * `audit/architecture/pro-coupling-contract.md`.
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
	 * Pro calls this documented surface; Free may call Term_Helper directly.
	 * Existing terms are matched by name (case-insensitive per `term_exists()`).
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
	 * Reads the same address array that feeds `_listora_address` and the geo
	 * row, so location terms agree with the map and displayed address.
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

if ( ! function_exists( 'wb_listora_get_demo_packs' ) ) {
	/**
	 * Every demo pack slug, in import order. Each maps to demo/{slug}-pack.php.
	 *
	 * The one list the setup wizard, the Settings re-import and WP-CLI read;
	 * three hand-kept copies had drifted (Settings imported 6 of the 9).
	 *
	 * @since 1.9.1
	 *
	 * @return string[]
	 */
	function wb_listora_get_demo_packs(): array {
		return array( 'restaurant', 'hotel', 'real-estate', 'job-board', 'general', 'classified', 'education', 'healthcare', 'place', 'event' );
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

/**
 * Queue a competitor migration on Action Scheduler batches (group `wb-listora`).
 *
 * The documented surface Pro's migration screen uses. Poll progress with
 * wb_listora_get_import_progress() or GET /import/progress/{run_id}.
 *
 * @since 1.9.1
 *
 * @param string $source Migrator source slug (see wb_listora_get_migrators()).
 * @param int    $total  Source listing count, for the progress bar.
 * @return string|\WP_Error The run_id, or WP_Error for an unknown source.
 */
function wb_listora_queue_migration_import( $source, $total = 0 ) {
	return \WBListora\ImportExport\Background_Import::queue_migration( (string) $source, (int) $total );
}

if ( ! function_exists( 'wb_listora_queue_file_import' ) ) {
	/**
	 * Queue a large CSV/JSON file import on Action Scheduler batches.
	 *
	 * The file is staged into uploads so it outlives the request; retried
	 * batches resume from the stored cursor.
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
