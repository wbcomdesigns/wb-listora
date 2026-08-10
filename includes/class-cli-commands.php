<?php
/**
 * WP-CLI Commands for WB Listora.
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage WB Listora directory listings.
 *
 * ## EXAMPLES
 *
 *     wp listora stats
 *     wp listora reindex
 *     wp listora listing-types
 *
 * @package WBListora
 */
class CLI_Commands extends \WP_CLI_Command {

	/**
	 * Show directory statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora stats
	 *
	 * @subcommand stats
	 */
	public function stats( $args, $assoc_args ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$counts = wp_count_posts( 'listora_listing' );

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Directory Statistics' );
		\WP_CLI::log( str_repeat( '─', 40 ) );

		$published = (int) ( $counts->publish ?? 0 );
		$pending   = (int) ( $counts->pending ?? 0 );
		$draft     = (int) ( $counts->draft ?? 0 );
		$expired   = (int) ( $counts->listora_expired ?? 0 );
		$rejected  = (int) ( $counts->listora_rejected ?? 0 );

		\WP_CLI::log( sprintf( 'Listings:     %d total', $published + $pending + $draft + $expired + $rejected ) );
		\WP_CLI::log( sprintf( '  Published:  %d', $published ) );
		\WP_CLI::log( sprintf( '  Pending:    %d', $pending ) );
		\WP_CLI::log( sprintf( '  Draft:      %d', $draft ) );
		\WP_CLI::log( sprintf( '  Expired:    %d', $expired ) );
		\WP_CLI::log( sprintf( '  Rejected:   %d', $rejected ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$review_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$review_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews WHERE status = 'pending'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$fav_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}favorites" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$claims_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}claims WHERE status = 'pending'" );

		\WP_CLI::log( sprintf( 'Reviews:      %d (%d pending)', $review_total, $review_pending ) );
		\WP_CLI::log( sprintf( 'Favorites:    %d', $fav_total ) );
		\WP_CLI::log( sprintf( 'Claims:       %d pending', $claims_pending ) );

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Index Health' );
		\WP_CLI::log( str_repeat( '─', 40 ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$idx_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}search_index WHERE status = 'publish'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$geo_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}geo" );

		$sync_pct = $published > 0 ? round( ( $idx_count / $published ) * 100, 1 ) : 100;
		$geo_pct  = $published > 0 ? round( ( $geo_count / $published ) * 100, 1 ) : 100;

		\WP_CLI::log( sprintf( 'Search index: %d / %d (%s%% synced)', $idx_count, $published, $sync_pct ) );
		\WP_CLI::log( sprintf( 'Geo index:    %d / %d (%s%%)', $geo_count, $published, $geo_pct ) );

		\WP_CLI::log( '' );
		\WP_CLI::log( 'Database Size' );
		\WP_CLI::log( str_repeat( '─', 40 ) );

		$tables     = array( 'search_index', 'field_index', 'geo', 'reviews', 'review_votes', 'favorites', 'hours', 'claims', 'analytics', 'payments' );
		$total_size = 0;

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$row = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $prefix . $table ), ARRAY_A );
			if ( $row ) {
				$size        = ( (int) $row['Data_length'] + (int) $row['Index_length'] ) / 1024 / 1024;
				$total_size += $size;
				\WP_CLI::log( sprintf( '  %-20s %s MB', $table, number_format( $size, 1 ) ) );
			}
		}

		\WP_CLI::log( sprintf( '  %-20s %s MB', 'Total', number_format( $total_size, 1 ) ) );
		\WP_CLI::log( '' );
	}

	/**
	 * Rebuild search indexes.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Only reindex listings of this type.
	 *
	 * [--batch-size=<size>]
	 * : Number of listings per batch. Default 500.
	 *
	 * [--dry-run]
	 * : Preview without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora reindex
	 *     wp listora reindex --type=restaurant
	 *     wp listora reindex --dry-run
	 *
	 * @subcommand reindex
	 */
	public function reindex( $args, $assoc_args ) {
		$type       = $assoc_args['type'] ?? '';
		$batch_size = (int) ( $assoc_args['batch-size'] ?? 500 );
		$dry_run    = isset( $assoc_args['dry-run'] );

		if ( $dry_run ) {
			\WP_CLI::log( 'Dry run — no data will be written.' );
		}

		$msg = $type
			? sprintf( 'Reindexing %s listings...', $type )
			: 'Reindexing all listings...';

		\WP_CLI::log( $msg );

		$indexer = new Search\Search_Indexer();
		$stats   = $indexer->batch_reindex(
			array(
				'type'       => $type,
				'batch_size' => $batch_size,
				'dry_run'    => $dry_run,
			)
		);

		\WP_CLI::success(
			sprintf(
				'Done. %d indexed, %d skipped, %d errors.',
				$stats['indexed'],
				$stats['skipped'],
				$stats['errors']
			)
		);
	}

	/**
	 * Send a sample notification email to verify template rendering and mail delivery.
	 *
	 * ## OPTIONS
	 *
	 * [<template>]
	 * : The notification event to send. Omit to list the available templates.
	 *
	 * [--to=<email>]
	 * : Recipient address. Defaults to the site admin email.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora test-email listing_approved --to=you@example.com
	 *     wp listora test-email
	 *
	 * @subcommand test-email
	 *
	 * @param list<string>          $args       Positional CLI arguments.
	 * @param array<string, string> $assoc_args Associative CLI flags.
	 */
	public function test_email( array $args, array $assoc_args ): void {
		$event_key = isset( $args[0] ) ? sanitize_key( $args[0] ) : '';
		$recipient = ! empty( $assoc_args['to'] ) ? $assoc_args['to'] : get_option( 'admin_email' );

		// Mirrors the event keys validated by Notifications::send_test().
		$templates = array(
			'listing_submitted',
			'listing_approved',
			'listing_rejected',
			'listing_expired',
			'listing_expiring_soon',
			'listing_renewed',
			'listing_pending_admin',
			'review_received',
			'review_reply',
			'review_helpful',
			'claim_submitted',
			'claim_approved',
			'claim_rejected',
			'draft_reminder',
			'review_reminder',
			'listing_verify_email',
		);

		if ( '' === $event_key ) {
			\WP_CLI::log( 'Available templates:' );
			foreach ( $templates as $key ) {
				\WP_CLI::log( '  - ' . $key );
			}
			\WP_CLI::error( 'Specify a template: wp listora test-email <template> --to=<email>' );
		}

		if ( ! in_array( $event_key, $templates, true ) ) {
			\WP_CLI::error( sprintf( 'Unknown template "%s". Run `wp listora test-email` to list valid templates.', $event_key ) );
		}

		$notifications = new Workflow\Notifications();
		$result        = $notifications->send_test( $event_key, $recipient );

		if ( ! empty( $result['sent'] ) ) {
			\WP_CLI::success(
				sprintf(
					'Sent "%1$s" to %2$s (subject: %3$s).',
					$event_key,
					$recipient,
					isset( $result['subject'] ) ? $result['subject'] : ''
				)
			);
			return;
		}

		// Reaching here means the template rendered without fatal but the mail
		// transport reported a failed delivery. Warn rather than hard-fail so a
		// missing local mail transport doesn't mask an otherwise healthy render
		// (a broken template throws inside send_test and surfaces as a fatal).
		\WP_CLI::warning(
			sprintf(
				'Template "%1$s" rendered, but delivery to %2$s failed%3$s. Check the site mail configuration.',
				$event_key,
				$recipient,
				! empty( $result['error'] ) ? ': ' . $result['error'] : ''
			)
		);
	}

	/**
	 * Run the housekeeping the daily cron performs: email-log retention,
	 * analytics retention, and removal of stale unverified listings.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora cleanup
	 *
	 * @subcommand cleanup
	 *
	 * @param list<string>          $args       Positional CLI arguments.
	 * @param array<string, string> $assoc_args Associative CLI flags.
	 */
	public function cleanup( array $args, array $assoc_args ): void {
		\WP_CLI::log( 'Running WB Listora cleanup tasks...' );

		// Email-log retention prune (static; returns the number of rows removed).
		$pruned_log = (int) Workflow\Notifications::prune_log();
		\WP_CLI::log( sprintf( '  Email log retention: %d row(s) pruned.', $pruned_log ) );

		// Fire the real cron listeners (already registered at bootstrap) rather
		// than re-constructing the classes — same work the daily cron runs.
		do_action( 'wb_listora_daily_cleanup' );
		\WP_CLI::log( '  Analytics retention prune: done.' );

		do_action( Workflow\Email_Verification::CRON_HOOK );
		\WP_CLI::log( '  Stale unverified listings: cleaned up.' );

		// Backfill for BC 10156782139: purge rows whose listing was hard-
		// deleted before the 1.4.1 delete cascade existed. Fires the
		// wb_listora_purge_orphaned_listing_data action so Pro sweeps its
		// own listing-scoped tables in the same run.
		$orphans = ( new Core\Listing_Data_Eraser() )->purge_orphans();
		$total   = array_sum( $orphans );

		// The eraser deliberately skips the four index tables (search_index,
		// field_index, geo, hours) — Search_Indexer owns those. Sweep them here
		// too, otherwise a stale search_index row keeps its old status and goes
		// on inflating search totals and pagination.
		$index_orphans = ( new Search\Search_Indexer() )->purge_orphans();
		$total        += array_sum( $index_orphans );

		\WP_CLI::log( sprintf( '  Orphaned listing rows purged: %d.', $total ) );

		\WP_CLI::success( 'Cleanup complete.' );
	}

	/**
	 * Report listings whose price meta was emptied by the pre-1.4.2 sanitizer.
	 *
	 * Before 1.4.2 the `price` field routed through `Field::sanitize_json()`.
	 * The submission form and the wp-admin fields metabox both post a bare
	 * scalar, `json_decode( "275" )` returned an int, the `is_array()` test
	 * failed, and the sanitizer returned `array()` — so every price save wrote
	 * an empty array and the amount was lost (Basecamp 10171941201).
	 *
	 * The amounts are NOT recoverable: the number never reached the database.
	 * This command produces the re-entry worklist. It only reads.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora audit-prices
	 *     wp listora audit-prices --format=csv > prices-to-reenter.csv
	 *
	 * @subcommand audit-prices
	 *
	 * @param list<string>          $args       Positional CLI arguments.
	 * @param array<string, string> $assoc_args Associative CLI flags.
	 */
	public function audit_prices( array $args, array $assoc_args ): void {
		global $wpdb;

		$format = $assoc_args['format'] ?? 'table';

		// Every price-typed meta key in use, not just `price` — hotel uses
		// `price_per_night`, events `ticket_price`, healthcare
		// `consultation_fee`, and owner-defined types add their own.
		$price_keys = array();
		foreach ( Core\Listing_Type_Registry::instance()->get_all() as $type ) {
			foreach ( $type->get_all_fields() as $field ) {
				if ( 'price' === $field->get_type() ) {
					$price_keys[ WB_LISTORA_META_PREFIX . $field->get_key() ] = true;
				}
			}
		}

		if ( ! $price_keys ) {
			\WP_CLI::success( 'No price fields are registered on any listing type.' );
			return;
		}

		$keys         = array_keys( $price_keys );
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		// `a:0:{}` is the exact shape the pre-1.4.2 sanitizer persisted.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT p.ID, p.post_title, p.post_status, pm.meta_key
				 FROM {$wpdb->postmeta} pm
				 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				 WHERE p.post_type = 'listora_listing'
				   AND pm.meta_key IN ({$placeholders})
				   AND pm.meta_value = 'a:0:{}'
				 ORDER BY p.ID ASC",
				...$keys
			),
			ARRAY_A
		);

		if ( ! $rows ) {
			\WP_CLI::success( 'No listings with an emptied price were found.' );
			return;
		}

		$items = array_map(
			static function ( array $row ): array {
				return array(
					'id'       => (int) $row['ID'],
					'title'    => $row['post_title'],
					'status'   => $row['post_status'],
					'meta_key' => $row['meta_key'],
					// Built directly rather than via get_edit_post_link(), which
					// capability-checks against a current user CLI does not have
					// and would return an empty string for every row.
					'edit_url' => admin_url( 'post.php?post=' . (int) $row['ID'] . '&action=edit' ),
				);
			},
			$rows
		);

		\WP_CLI\Utils\format_items( $format, $items, array( 'id', 'title', 'status', 'meta_key', 'edit_url' ) );

		if ( 'count' !== $format ) {
			\WP_CLI::warning(
				sprintf(
					'%d listing(s) lost a price to the pre-1.4.2 sanitizer. The amounts cannot be recovered — they were never stored. Re-enter them on the listings above; saving now persists correctly.',
					count( $items )
				)
			);
		}
	}

	/**
	 * List registered listing types.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora listing-types
	 *
	 * @subcommand listing-types
	 */
	public function listing_types( $args, $assoc_args ) {
		$registry = Core\Listing_Type_Registry::instance();
		$types    = $registry->get_all();

		$rows = array();
		foreach ( $types as $type ) {
			$rows[] = array(
				'Slug'       => $type->get_slug(),
				'Name'       => $type->get_name(),
				'Fields'     => count( $type->get_all_fields() ),
				'Filterable' => count( $type->get_filterable_fields() ),
				'Schema'     => $type->get_schema_type(),
				// Column renamed from "Default" — it reports whether the type
				// ships with the plugin, and the old header read as "this is
				// the default type for submissions", which it never was.
				'Built-in'   => $type->is_builtin() ? 'Yes' : 'No',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'Slug', 'Name', 'Fields', 'Filterable', 'Schema', 'Built-in' ) );
	}

	/**
	 * Import listings from a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to CSV file.
	 *
	 * --type=<type>
	 * : Listing type slug for imported listings.
	 *
	 * [--dry-run]
	 * : Preview without importing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora import listings.csv --type=restaurant
	 *     wp listora import listings.csv --type=restaurant --dry-run
	 *
	 * @subcommand import
	 */
	public function import( $args, $assoc_args ) {
		$file    = $args[0];
		$type    = $assoc_args['type'] ?? '';
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( ! $type ) {
			\WP_CLI::error( 'Please specify --type=<slug>.' );
		}

		if ( ! file_exists( $file ) ) {
			\WP_CLI::error( "File not found: {$file}" );
		}

		// Parse preview.
		$preview = ImportExport\CSV_Importer::parse_preview( $file );
		if ( is_wp_error( $preview ) ) {
			\WP_CLI::error( $preview->get_error_message() );
		}

		\WP_CLI::log( sprintf( 'CSV: %d columns, %d data rows.', count( $preview['headers'] ), $preview['total'] ) );
		\WP_CLI::log( 'Headers: ' . implode( ', ', $preview['headers'] ) );

		// Auto-map columns by header name.
		$fields  = ImportExport\CSV_Importer::get_mappable_fields( $type );
		$mapping = array();

		foreach ( $preview['headers'] as $idx => $header ) {
			$header_lower = strtolower( trim( $header ) );
			$matched      = false;

			foreach ( $fields as $field_key => $field_label ) {
				if ( strtolower( $field_label ) === $header_lower || $field_key === $header_lower || 'meta_' . $header_lower === $field_key ) {
					$mapping[ $idx ] = $field_key;
					$matched         = true;
					\WP_CLI::log( sprintf( '  Column "%s" → %s', $header, $field_label ) );
					break;
				}
			}

			if ( ! $matched ) {
				$mapping[ $idx ] = '_skip';
				\WP_CLI::log( sprintf( '  Column "%s" → SKIPPED', $header ) );
			}
		}

		if ( $dry_run ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Dry run — no data imported.' );
			\WP_CLI::success( sprintf( 'Would import %d listings.', $preview['total'] ) );
			return;
		}

		\WP_CLI::log( '' );
		$stats = ImportExport\CSV_Importer::import( $file, $type, $mapping );

		foreach ( $stats['messages'] as $msg ) {
			\WP_CLI::log( '  ' . $msg );
		}

		\WP_CLI::success(
			sprintf(
				'Import complete: %d imported, %d skipped, %d errors.',
				$stats['imported'],
				$stats['skipped'],
				$stats['errors']
			)
		);
	}

	/**
	 * Export listings to CSV.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Filter by listing type.
	 *
	 * [--output=<file>]
	 * : Output file path. Default: listora-export.csv.
	 *
	 * [--status=<status>]
	 * : Post status filter. Default: publish.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora export --type=restaurant --output=restaurants.csv
	 *
	 * @subcommand export
	 */
	public function export( $args, $assoc_args ) {
		$type   = $assoc_args['type'] ?? '';
		$output = $assoc_args['output'] ?? 'listora-export-' . gmdate( 'Y-m-d' ) . '.csv';
		$status = $assoc_args['status'] ?? 'publish';

		$file_path = ImportExport\CSV_Exporter::export(
			array(
				'type'   => $type,
				'status' => $status,
			)
		);

		if ( is_wp_error( $file_path ) ) {
			\WP_CLI::error( $file_path->get_error_message() );
		}

		// Copy to requested output path.
		if ( $output !== $file_path ) {
			copy( $file_path, $output );
			wp_delete_file( $file_path );
		}

		\WP_CLI::success( sprintf( 'Exported to: %s', $output ) );
	}

	/**
	 * Run database health check and repair.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Preview without repairing.
	 *
	 * @subcommand repair
	 */
	public function db_repair( $args, $assoc_args ) {
		global $wpdb;
		$prefix  = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$dry_run = isset( $assoc_args['dry-run'] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Find orphaned search_index rows (post deleted but index remains).
		$orphaned = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}search_index si
			LEFT JOIN {$wpdb->posts} p ON si.listing_id = p.ID
			WHERE p.ID IS NULL"
		);

		\WP_CLI::log( sprintf( 'Orphaned search_index rows: %d', $orphaned ) );

		if ( $orphaned > 0 && ! $dry_run ) {
			$wpdb->query(
				"DELETE si FROM {$prefix}search_index si
				LEFT JOIN {$wpdb->posts} p ON si.listing_id = p.ID
				WHERE p.ID IS NULL"
			);
			\WP_CLI::log( 'Cleaned.' );
		}

		// Find orphaned geo rows.
		$orphaned_geo = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$prefix}geo g
			LEFT JOIN {$wpdb->posts} p ON g.listing_id = p.ID
			WHERE p.ID IS NULL"
		);

		\WP_CLI::log( sprintf( 'Orphaned geo rows: %d', $orphaned_geo ) );

		if ( $orphaned_geo > 0 && ! $dry_run ) {
			$wpdb->query(
				"DELETE g FROM {$prefix}geo g
				LEFT JOIN {$wpdb->posts} p ON g.listing_id = p.ID
				WHERE p.ID IS NULL"
			);
			\WP_CLI::log( 'Cleaned.' );
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		\WP_CLI::success( $dry_run ? 'Dry run complete.' : 'Repair complete.' );
	}

	/**
	 * Migrate listings from a competitor directory plugin.
	 *
	 * ## OPTIONS
	 *
	 * --from=<source>
	 * : Source plugin to migrate from. One of: directorist, geodirectory, bdp, listingpro.
	 *
	 * [--dry-run]
	 * : Preview without importing. Shows what would be migrated.
	 *
	 * [--batch-size=<size>]
	 * : Number of listings per batch. Default 50.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora migrate --from=directorist
	 *     wp listora migrate --from=geodirectory --dry-run
	 *     wp listora migrate --from=bdp --batch-size=25
	 *     wp listora migrate --from=hivepress
	 *     wp listora migrate --from=listingpro
	 *
	 * @subcommand migrate
	 */
	public function migrate( $args, $assoc_args ) {
		$source     = $assoc_args['from'] ?? '';
		$dry_run    = isset( $assoc_args['dry-run'] );
		$batch_size = (int) ( $assoc_args['batch-size'] ?? 50 );

		if ( ! $source ) {
			\WP_CLI::error( 'Please specify --from=<source>. Available sources: directorist, geodirectory, bdp, listingpro, hivepress.' );
		}

		$migrators = ImportExport\Migration_Base::get_migrators();
		$target    = null;

		foreach ( $migrators as $migrator ) {
			if ( $migrator->get_source_slug() === $source ) {
				$target = $migrator;
				break;
			}
		}

		if ( ! $target ) {
			\WP_CLI::error(
				sprintf( 'Unknown source: %s. Available sources: directorist, geodirectory, bdp, listingpro, hivepress.', $source )
			);
		}

		if ( ! $target->detect() ) {
			\WP_CLI::error(
				sprintf( '%s data not found on this site.', $target->get_source_name() )
			);
		}

		$total = $target->get_source_count();

		if ( 0 === $total ) {
			\WP_CLI::warning( 'No listings found to migrate.' );
			return;
		}

		if ( $dry_run ) {
			\WP_CLI::log( 'Dry run -- no data will be written.' );
		}

		\WP_CLI::log(
			sprintf(
				'Migrating %d listings from %s...',
				$total,
				$target->get_source_name()
			)
		);

		$progress = \WP_CLI\Utils\make_progress_bar(
			sprintf( 'Migrating from %s', $target->get_source_name() ),
			$total
		);

		$stats = $target->migrate_all(
			$dry_run,
			function () use ( $progress ) {
				$progress->tick();
			}
		);

		$progress->finish();

		// Show stats.
		\WP_CLI::log( '' );
		\WP_CLI::log( 'Migration Results' );
		\WP_CLI::log( str_repeat( '-', 40 ) );
		\WP_CLI::log( sprintf( '  Imported: %d', $stats['imported'] ) );
		\WP_CLI::log( sprintf( '  Skipped:  %d', $stats['skipped'] ) );
		\WP_CLI::log( sprintf( '  Errors:   %d', $stats['errors'] ) );
		\WP_CLI::log( sprintf( '  Total:    %d', $stats['total'] ) );

		if ( ! empty( $stats['errors'] ) && ! empty( $stats['messages'] ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Error Details:' );
			$error_msgs = array_filter(
				$stats['messages'],
				function ( $msg ) {
					return false !== strpos( $msg, 'error' ) || false !== strpos( $msg, 'Error' ) || false !== strpos( $msg, 'not found' );
				}
			);
			foreach ( array_slice( $error_msgs, 0, 20 ) as $msg ) {
				\WP_CLI::log( '  ' . $msg );
			}
			if ( count( $error_msgs ) > 20 ) {
				\WP_CLI::log( sprintf( '  ... and %d more errors.', count( $error_msgs ) - 20 ) );
			}
		}

		if ( $stats['errors'] > 0 ) {
			\WP_CLI::warning(
				sprintf(
					'Migration completed with %d errors.',
					$stats['errors']
				)
			);
		} else {
			\WP_CLI::success(
				$dry_run
					? sprintf( 'Dry run complete. %d listings would be imported.', $stats['imported'] )
					: sprintf( 'Migration complete. %d listings imported.', $stats['imported'] )
			);
		}
	}

	/**
	 * All demo packs available to the CLI seeder.
	 *
	 * @var string[]
	 */
	private const DEMO_PACKS = array(
		'restaurant',
		'hotel',
		'real-estate',
		'job-board',
		'general',
		'classified',
		'education',
		'healthcare',
		'place',
	);

	/**
	 * Manage demo content.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Action: seed | remove | reseed.
	 *
	 * [--pack=<pack>]
	 * : Comma-separated pack slugs (or 'all'). Default: all. Available: restaurant, hotel, real-estate, job-board, general, classified, education, healthcare, place.
	 *
	 * [--with-users]
	 * : Also create the four default test users (contributor1, author1, subscriber2, subscriber3).
	 *
	 * [--skip-images]
	 * : Skip image sideloading (useful for CI / slow networks).
	 *
	 * [--reindex]
	 * : Run Search_Indexer rebuild after seeding.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora demo seed --pack=restaurant
	 *     wp listora demo seed --pack=restaurant,hotel
	 *     wp listora demo seed --pack=all --with-users --reindex
	 *     wp listora demo seed --pack=classified --skip-images
	 *     wp listora demo remove
	 *     wp listora demo reseed --pack=restaurant
	 *
	 * @subcommand demo
	 */
	public function demo( $args, $assoc_args ) {
		$action = $args[0] ?? '';

		switch ( $action ) {
			case 'seed':
				$this->demo_seed( $assoc_args );
				return;

			case 'remove':
				$this->demo_remove();
				return;

			case 'reseed':
				// Wipe existing demo content, then re-run the seeder with the
				// same flags. Useful after the seeder is updated (e.g., new
				// image sources) or to refresh a stale demo dataset.
				\WP_CLI::log( 'Reseed: removing existing demo content first…' );
				$this->demo_remove();
				\WP_CLI::log( 'Reseed: now seeding…' );
				$this->demo_seed( $assoc_args );
				return;

			default:
				\WP_CLI::error( 'Usage: wp listora demo <seed|remove|reseed> [--pack=...] [--with-users] [--skip-images] [--reindex]' );
		}
	}

	/**
	 * Run one or more demo packs.
	 *
	 * @param array $assoc_args CLI flags from `demo seed`.
	 */
	private function demo_seed( $assoc_args ) {
		// Resolve packs to run.
		$pack_arg     = $assoc_args['pack'] ?? 'all';
		$with_users   = isset( $assoc_args['with-users'] );
		$skip_images  = isset( $assoc_args['skip-images'] );
		$reindex_flag = isset( $assoc_args['reindex'] );

		if ( 'all' === $pack_arg ) {
			$packs = self::DEMO_PACKS;
		} else {
			$packs = array_filter( array_map( 'trim', explode( ',', (string) $pack_arg ) ) );
		}

		$invalid = array_diff( $packs, self::DEMO_PACKS );
		if ( ! empty( $invalid ) ) {
			\WP_CLI::error(
				sprintf(
					'Unknown pack(s): %s. Available: %s.',
					implode( ', ', $invalid ),
					implode( ', ', self::DEMO_PACKS )
				)
			);
		}

		if ( empty( $packs ) ) {
			\WP_CLI::error( 'No packs selected. Use --pack=all or --pack=<slug>.' );
		}

		// Make sure the seeder class is loaded.
		require_once WB_LISTORA_PLUGIN_DIR . 'demo/class-demo-seeder.php';

		\WBListora\Demo\Demo_Seeder::set_skip_images( $skip_images );

		// Always make sure the test users exist when --with-users is passed.
		// We also create them by default so claims/favorites have real authors.
		$user_ids = \WBListora\Demo\Demo_Seeder::ensure_test_users();
		if ( $with_users ) {
			\WP_CLI::log( sprintf( 'Test users ready: %s', implode( ', ', array_keys( $user_ids ) ) ) );
		}

		\WP_CLI::log(
			sprintf(
				'Seeding %d pack(s): %s%s%s',
				count( $packs ),
				implode( ', ', $packs ),
				$skip_images ? ' [images skipped]' : '',
				$reindex_flag ? ' [will reindex]' : ''
			)
		);

		$total_before = $this->count_demo_listings();

		foreach ( $packs as $pack ) {
			$file = WB_LISTORA_PLUGIN_DIR . 'demo/' . $pack . '-pack.php';
			if ( ! file_exists( $file ) ) {
				\WP_CLI::warning( sprintf( 'Pack file missing: %s', $file ) );
				continue;
			}

			\WP_CLI::log( sprintf( '  → Running %s pack...', $pack ) );

			try {
				// Each pack is a top-level script; require_once keeps it idempotent across multiple calls within the same request, while seed_listing()'s title check guards across requests.
				require $file;
			} catch ( \Throwable $e ) {
				\WP_CLI::warning( sprintf( '  Error in %s pack: %s', $pack, $e->getMessage() ) );
			}
		}

		$total_after = $this->count_demo_listings();
		$created     = max( 0, $total_after - $total_before );

		\WP_CLI::log( sprintf( 'Created %d new demo listings (total demo: %d).', $created, $total_after ) );

		// Optional reindex.
		if ( $reindex_flag ) {
			\WP_CLI::log( 'Reindexing search...' );
			$indexer = new Search\Search_Indexer();
			$stats   = $indexer->batch_reindex( array( 'batch_size' => 500 ) );
			\WP_CLI::log(
				sprintf(
					'Reindex done — %d indexed, %d skipped, %d errors.',
					$stats['indexed'],
					$stats['skipped'],
					$stats['errors']
				)
			);
		}

		\WP_CLI::success( sprintf( 'Demo seed complete: %d packs.', count( $packs ) ) );
	}

	/**
	 * Remove all demo content (listings + attached gallery + featured images).
	 */
	private function demo_remove() {
		// Delegate to the single canonical remover so CLI + the admin
		// "Delete demo data" button share one implementation (card 10020109923).
		require_once WB_LISTORA_PLUGIN_DIR . 'demo/class-demo-seeder.php';

		if ( ! class_exists( '\WBListora\Demo\Demo_Seeder' ) ) {
			\WP_CLI::error( 'Demo tools are not available.' );
			return;
		}

		$deleted = \WBListora\Demo\Demo_Seeder::remove_all();

		if ( 0 === $deleted['listings'] ) {
			\WP_CLI::log( 'No demo listings found.' );
		} else {
			\WP_CLI::log( sprintf( 'Removed %d demo listings.', $deleted['listings'] ) );
		}

		if ( $deleted['attachments'] > 0 ) {
			\WP_CLI::log( sprintf( 'Removed %d demo attachments.', $deleted['attachments'] ) );
		}

		\WP_CLI::success( 'Demo content removed.' );
	}

	/**
	 * Count current demo listings.
	 *
	 * @return int
	 */
	private function count_demo_listings() {
		$ids = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'any',
				'posts_per_page' => 500, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => '_listora_demo_content',
				'meta_value'     => '1',
				'fields'         => 'ids',
			)
		);
		return count( $ids );
	}
}

\WP_CLI::add_command( 'listora', __NAMESPACE__ . '\\CLI_Commands' );
