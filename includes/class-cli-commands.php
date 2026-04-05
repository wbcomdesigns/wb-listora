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
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$row = $wpdb->get_row( "SHOW TABLE STATUS LIKE '{$prefix}{$table}'", ARRAY_A );
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
				'Default'    => $type->is_default() ? 'Yes' : 'No',
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'Slug', 'Name', 'Fields', 'Filterable', 'Schema', 'Default' ) );
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
	 *     wp listora migrate --from=listingpro
	 *
	 * @subcommand migrate
	 */
	public function migrate( $args, $assoc_args ) {
		$source     = $assoc_args['from'] ?? '';
		$dry_run    = isset( $assoc_args['dry-run'] );
		$batch_size = (int) ( $assoc_args['batch-size'] ?? 50 );

		if ( ! $source ) {
			\WP_CLI::error( 'Please specify --from=<source>. Available sources: directorist, geodirectory, bdp, listingpro.' );
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
				sprintf( 'Unknown source: %s. Available sources: directorist, geodirectory, bdp, listingpro.', $source )
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
	 * Manage demo content.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : Action: remove.
	 *
	 * ## EXAMPLES
	 *
	 *     wp listora demo remove
	 *
	 * @subcommand demo
	 */
	public function demo( $args, $assoc_args ) {
		$action = $args[0] ?? '';

		if ( 'remove' !== $action ) {
			\WP_CLI::error( 'Usage: wp listora demo remove' );
		}

		$demos = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_listora_demo_content',
				'meta_value'     => '1',
				'fields'         => 'ids',
			)
		);

		if ( empty( $demos ) ) {
			\WP_CLI::log( 'No demo content found.' );
			return;
		}

		$count = count( $demos );
		foreach ( $demos as $id ) {
			wp_delete_post( $id, true );
		}

		\WP_CLI::success( sprintf( 'Removed %d demo listings.', $count ) );
	}
}

\WP_CLI::add_command( 'listora', __NAMESPACE__ . '\\CLI_Commands' );
