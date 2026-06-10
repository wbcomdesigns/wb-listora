<?php
/**
 * Background Import — runs demo packs and large CSV/JSON imports on
 * Action Scheduler batches instead of one long synchronous request.
 *
 * A "run" is a self-contained import job identified by an opaque `run_id`.
 * Its full state (kind, source, cursor, totals, created post IDs, status)
 * lives in a single autoload-off option, `wb_listora_bg_import_{run_id}`,
 * so the REST progress endpoint can read it without touching the queue and
 * a re-fired batch can resume exactly where the last one stopped.
 *
 * Pipeline (all Action Scheduler single-actions in group `wb-listora`):
 *
 *   wb_listora_bg_import_batch( run_id )    ← processes ONE chunk, then
 *                                             self-queues the next chunk while
 *                                             rows remain.
 *   wb_listora_bg_import_finalize( run_id ) ← queued once the source is
 *                                             exhausted; rebuilds the search
 *                                             index for the run's new posts.
 *
 * Resumability + idempotency:
 *   - The cursor (`offset`) is persisted after every committed chunk. Action
 *     Scheduler does not retry failed actions on its own, so a failed batch
 *     re-queues itself (bounded by {@see self::MAX_CHUNK_RETRIES} consecutive
 *     failures, then the run is marked failed); the retry re-reads the SAME
 *     offset and continues.
 *   - Every source row is fingerprinted (kind + title + a stable field hash).
 *     The run remembers `hash → post_id`; a row whose hash is already mapped
 *     is skipped, so retrying a half-finished chunk never double-creates a
 *     listing. Images reuse the importers' existing sideload/dedupe path.
 *
 * Synchronous fallback:
 *   When the total item count is at or below {@see self::SYNC_THRESHOLD} (or
 *   Action Scheduler is not available at all), the run executes inline and
 *   returns `done` immediately — tiny demo packs shouldn't pay the latency
 *   of a cron round-trip.
 *
 * Reuses the 1.1.0 image dedupe / timeout work via the existing
 * CSV_Importer / JSON_Importer / Demo_Seeder code paths — this class only
 * orchestrates batching, it does not re-implement listing creation.
 *
 * @package WBListora\ImportExport
 *
 * @since 1.2.0
 */

declare(strict_types=1);

namespace WBListora\ImportExport;

defined( 'ABSPATH' ) || exit;

use WBListora\Workflow\Cron_Scheduler;

/**
 * Orchestrates resumable, idempotent imports over Action Scheduler batches.
 */
class Background_Import {

	/**
	 * Action Scheduler group — shared with the rest of Free's jobs so the
	 * deactivation sweep ({@see Cron_Scheduler::unschedule_all()}) clears
	 * import jobs too.
	 *
	 * @var string
	 */
	const GROUP = Cron_Scheduler::GROUP;

	/**
	 * AS hook that processes a single chunk of a run.
	 *
	 * @var string
	 */
	const HOOK_BATCH = 'wb_listora_bg_import_batch';

	/**
	 * AS hook that rebuilds the search index once a run's rows are exhausted.
	 *
	 * @var string
	 */
	const HOOK_FINALIZE = 'wb_listora_bg_import_finalize';

	/**
	 * Option-name prefix for per-run state. The opaque run_id is appended.
	 *
	 * @var string
	 */
	const STATE_PREFIX = 'wb_listora_bg_import_';

	/**
	 * Rows processed per chunk for file-based (CSV/JSON) imports.
	 *
	 * @var int
	 */
	const ROW_CHUNK = 25;

	/**
	 * At or below this item count the run executes synchronously rather than
	 * queueing AS jobs. Tiny packs (a handful of listings) finish faster
	 * inline than via a cron round-trip.
	 *
	 * @var int
	 */
	const SYNC_THRESHOLD = 10;

	/**
	 * Consecutive failed attempts at the same chunk before the run is marked
	 * failed. Action Scheduler does NOT retry failed actions by itself —
	 * {@see self::run_batch()} re-queues the chunk explicitly and uses this
	 * cap as the terminal-failure threshold.
	 *
	 * @var int
	 */
	const MAX_CHUNK_RETRIES = 3;

	/**
	 * Run statuses.
	 */
	const STATUS_QUEUED   = 'queued';
	const STATUS_RUNNING  = 'running';
	const STATUS_FINALIZE = 'finalizing';
	const STATUS_DONE     = 'done';
	const STATUS_FAILED   = 'failed';

	/**
	 * Register the Action Scheduler handlers.
	 *
	 * Bound once on `init` by {@see self::init()}. The progress REST route is
	 * registered separately via the `wb_listora_rest_api_init` extension hook
	 * so it shares the plugin's REST bootstrap.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( self::HOOK_BATCH, array( __CLASS__, 'run_batch' ), 10, 1 );
		add_action( self::HOOK_FINALIZE, array( __CLASS__, 'run_finalize' ), 10, 1 );

		// Self-register the progress endpoint inside the plugin's REST cycle.
		add_action( 'wb_listora_rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
	}

	// ─── Public API (called by the setup wizard / admin / CLI) ───

	/**
	 * Queue (or synchronously run) a demo-pack import.
	 *
	 * @param string[] $packs       Pack slugs to run, in order.
	 * @param int      $est_per_pack Estimated listings per pack (for progress UI).
	 * @return string The run_id. Poll {@see self::get_progress()} with it.
	 */
	public static function queue_demo( array $packs, int $est_per_pack = 20 ): string {
		$packs = array_values( array_filter( array_map( 'sanitize_key', $packs ) ) );
		$total = max( 1, count( $packs ) * max( 1, $est_per_pack ) );

		$run_id = self::new_run(
			array(
				'kind'   => 'demo',
				'packs'  => $packs,
				'units'  => $packs,            // One AS unit per pack.
				'cursor' => 0,                 // Index into `units`.
				'total'  => $total,
			)
		);

		return self::dispatch( $run_id );
	}

	/**
	 * Queue (or synchronously run) a file-based import (CSV or JSON).
	 *
	 * The uploaded temp file is copied into the uploads dir so it survives
	 * past the request that received it (PHP deletes the multipart tmp file
	 * at request end, but AS jobs run in later requests).
	 *
	 * @param string                   $kind      'csv' | 'json'.
	 * @param string                   $file_path Path to the readable source file.
	 * @param string                   $type_slug Listing type slug for new listings.
	 * @param array<int|string,string> $mapping Column/field mapping (CSV only).
	 * @param int                      $total     Pre-counted row total (for progress UI).
	 * @return string|\WP_Error The run_id, or WP_Error if the file is unusable.
	 */
	public static function queue_file( string $kind, string $file_path, string $type_slug, array $mapping = array(), int $total = 0 ) {
		$kind = in_array( $kind, array( 'csv', 'json' ), true ) ? $kind : 'csv';

		$stored = self::stash_source_file( $file_path, $kind );
		if ( is_wp_error( $stored ) ) {
			return $stored;
		}

		$run_id = self::new_run(
			array(
				'kind'      => $kind,
				'source'    => $stored,
				'type_slug' => sanitize_text_field( $type_slug ),
				'mapping'   => self::sanitize_mapping( $mapping ),
				'cursor'    => 0,              // Row offset.
				'total'     => max( 0, (int) $total ),
			)
		);

		return self::dispatch( $run_id );
	}

	/**
	 * Read the progress of a run for the polling UI.
	 *
	 * @param string $run_id Run identifier.
	 * @return array<string,mixed>|null Progress payload, or null if unknown.
	 */
	public static function get_progress( string $run_id ): ?array {
		$state = self::get_state( $run_id );
		if ( null === $state ) {
			return null;
		}

		$total     = (int) ( $state['total'] ?? 0 );
		$processed = (int) ( $state['processed'] ?? 0 );
		$percent   = $total > 0 ? (int) floor( min( 100, ( $processed / $total ) * 100 ) ) : 0;

		// A done run reads 100% even if the estimate undershot the real count.
		if ( self::STATUS_DONE === ( $state['status'] ?? '' ) ) {
			$percent = 100;
		}

		return array(
			'run_id'    => $run_id,
			'kind'      => (string) ( $state['kind'] ?? '' ),
			'status'    => (string) ( $state['status'] ?? self::STATUS_QUEUED ),
			'total'     => $total,
			'processed' => $processed,
			'imported'  => (int) ( $state['imported'] ?? 0 ),
			'skipped'   => (int) ( $state['skipped'] ?? 0 ),
			'errors'    => (int) ( $state['errors'] ?? 0 ),
			'percent'   => $percent,
			'messages'  => array_slice( (array) ( $state['messages'] ?? array() ), -10 ),
			'done'      => in_array( $state['status'] ?? '', array( self::STATUS_DONE, self::STATUS_FAILED ), true ),
		);
	}

	// ─── Action Scheduler handlers ───

	/**
	 * Process one chunk of a run, then self-queue the next chunk while rows
	 * remain. Resumable: reads the persisted cursor, so an AS retry continues
	 * from the last committed position rather than restarting.
	 *
	 * @param string $run_id Run identifier.
	 * @return void
	 *
	 * @throws \Throwable Re-thrown when a chunk fails so Action Scheduler
	 *                    records the failure. The method self-queues a bounded
	 *                    retry from the persisted cursor and marks the run
	 *                    failed after {@see self::MAX_CHUNK_RETRIES}
	 *                    consecutive failures.
	 */
	public static function run_batch( $run_id ): void {
		$run_id = (string) $run_id;
		$state  = self::get_state( $run_id );
		if ( null === $state ) {
			return;
		}

		// FAILED and DONE are terminal — a stray duplicate action must not
		// resurrect a finished run back to RUNNING.
		if ( in_array( $state['status'] ?? '', array( self::STATUS_FAILED, self::STATUS_DONE ), true ) ) {
			return;
		}

		$state['status'] = self::STATUS_RUNNING;
		self::put_state( $run_id, $state );

		try {
			$has_more = ( 'demo' === ( $state['kind'] ?? '' ) )
				? self::process_demo_unit( $run_id, $state )
				: self::process_file_chunk( $run_id, $state );
		} catch ( \Throwable $e ) {
			// Action Scheduler does NOT retry failed actions on its own, so a
			// thrown chunk must self-recover: re-queue the same hook+arg (the
			// persisted cursor lets the retry resume from the last committed
			// position) and count consecutive failures. Once the cap is hit,
			// mark the run failed so the progress UI surfaces a terminal state
			// instead of polling a dead RUNNING import forever.
			self::append_message( $run_id, $e->getMessage() );
			self::bump( $run_id, 'errors', 1 );

			$state = self::get_state( $run_id );
			if ( null !== $state ) {
				$retries                = (int) ( $state['chunk_retries'] ?? 0 ) + 1;
				$state['chunk_retries'] = $retries;

				if ( $retries >= self::MAX_CHUNK_RETRIES ) {
					$state['status'] = self::STATUS_FAILED;
					self::put_state( $run_id, $state );
					self::append_message(
						$run_id,
						sprintf(
							/* translators: %d: number of failed attempts at the same import chunk. */
							__( 'Import failed after %d attempts at the same chunk.', 'wb-listora' ),
							$retries
						)
					);
				} else {
					$state['status'] = self::STATUS_QUEUED;
					self::put_state( $run_id, $state );
					self::enqueue_batch( $run_id ); // PENDING-only dedupe permits this self-requeue.
				}
			}

			throw $e; // Re-throw so AS records the failure on this action.
		}

		// A chunk processor may have marked the run failed mid-chunk (e.g.
		// missing source file) — do not let the finalize hand-off overwrite
		// that terminal state with FINALIZE → DONE.
		$state = self::get_state( $run_id ) ?? $state;
		if ( self::STATUS_FAILED === ( $state['status'] ?? '' ) ) {
			return;
		}

		// A committed chunk clears the consecutive-failure counter so a long
		// import with scattered transient errors is not cut short.
		if ( ! empty( $state['chunk_retries'] ) ) {
			$state['chunk_retries'] = 0;
			self::put_state( $run_id, $state );
		}

		if ( $has_more ) {
			self::enqueue_batch( $run_id );
			return;
		}

		// Source exhausted — hand off to the finalize (index rebuild) job.
		$state           = self::get_state( $run_id ) ?? $state;
		$state['status'] = self::STATUS_FINALIZE;
		self::put_state( $run_id, $state );
		self::enqueue_finalize( $run_id );
	}

	/**
	 * Rebuild the search index for the run's newly created listings, then
	 * mark the run done and clean up the stashed source file.
	 *
	 * @param string $run_id Run identifier.
	 * @return void
	 */
	public static function run_finalize( $run_id ): void {
		$run_id = (string) $run_id;
		$state  = self::get_state( $run_id );
		if ( null === $state ) {
			return;
		}

		$post_ids = array_values( array_unique( array_map( 'intval', (array) ( $state['created'] ?? array() ) ) ) );

		if ( ! empty( $post_ids ) && class_exists( '\WBListora\Search\Search_Indexer' ) ) {
			$indexer = new \WBListora\Search\Search_Indexer();
			foreach ( $post_ids as $post_id ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$indexer->index_listing( $post_id, $post );
				}
			}
		}

		// Drop the stashed source file — it's no longer needed.
		if ( ! empty( $state['source'] ) && file_exists( $state['source'] ) ) {
			wp_delete_file( $state['source'] );
		}

		$state['status']      = self::STATUS_DONE;
		$state['finished_at'] = time();
		self::put_state( $run_id, $state );
	}

	// ─── Chunk processors ───

	/**
	 * Run a single demo pack (one AS unit). Demo packs are idempotent on their
	 * own (they re-detect already-seeded content), so a retry is safe.
	 *
	 * @param string              $run_id Run identifier.
	 * @param array<string,mixed> $state  Current run state.
	 * @return bool True if more units remain after this one.
	 */
	private static function process_demo_unit( string $run_id, array $state ): bool {
		$units  = array_values( (array) ( $state['units'] ?? array() ) );
		$cursor = (int) ( $state['cursor'] ?? 0 );

		if ( ! isset( $units[ $cursor ] ) ) {
			return false;
		}

		$pack   = sanitize_key( $units[ $cursor ] );
		$before = self::count_listings();

		self::ensure_demo_seeder();

		// First unit also provisions the shared demo users.
		if ( 0 === $cursor && class_exists( '\WBListora\Demo\Demo_Seeder' ) ) {
			\WBListora\Demo\Demo_Seeder::ensure_test_users();
		}

		$pack_file = WB_LISTORA_PLUGIN_DIR . 'demo/' . $pack . '-pack.php';
		if ( file_exists( $pack_file ) ) {
			require $pack_file; // Self-contained + idempotent (see wizard guard).
		} else {
			self::append_message(
				$run_id,
				/* translators: %s: demo pack slug */
				sprintf( __( 'Demo pack "%s" not found, skipped.', 'wb-listora' ), $pack )
			);
		}

		$created = max( 0, self::count_listings() - $before );

		// Advance cursor + counters atomically against the freshest state.
		$state              = self::get_state( $run_id ) ?? $state;
		$state['cursor']    = $cursor + 1;
		$state['processed'] = (int) ( $state['processed'] ?? 0 ) + max( 1, $created );
		$state['imported']  = (int) ( $state['imported'] ?? 0 ) + $created;
		self::put_state( $run_id, $state );

		return isset( $units[ $cursor + 1 ] );
	}

	/**
	 * Process one chunk of rows from a CSV/JSON source. Each row is
	 * fingerprinted and skipped if its hash is already mapped for this run,
	 * so a retried chunk never double-creates listings.
	 *
	 * @param string              $run_id Run identifier.
	 * @param array<string,mixed> $state  Current run state.
	 * @return bool True if more rows remain after this chunk.
	 */
	private static function process_file_chunk( string $run_id, array $state ): bool {
		$kind   = (string) ( $state['kind'] ?? 'csv' );
		$source = (string) ( $state['source'] ?? '' );
		$offset = (int) ( $state['cursor'] ?? 0 );
		$limit  = self::ROW_CHUNK;

		if ( '' === $source || ! file_exists( $source ) ) {
			self::append_message( $run_id, __( 'Import source file is missing.', 'wb-listora' ) );
			self::mark_failed( $run_id );
			return false;
		}

		$rows = self::read_rows( $kind, $source, $offset, $limit );
		if ( empty( $rows ) ) {
			return false;
		}

		$type_slug = (string) ( $state['type_slug'] ?? '' );
		$mapping   = (array) ( $state['mapping'] ?? array() );
		$seen      = (array) ( $state['seen'] ?? array() ); // hash => post_id.
		$created   = (array) ( $state['created'] ?? array() );

		$imported = 0;
		$skipped  = 0;
		$errors   = 0;

		foreach ( $rows as $row ) {
			$data = ( 'csv' === $kind )
				? self::map_csv_row( $row, $mapping )
				: self::map_json_row( $row );

			if ( empty( $data['title'] ) ) {
				++$skipped;
				continue;
			}

			$hash = self::row_hash( $kind, $type_slug, $data );

			// Idempotency: a hash already created this run is a retry — skip.
			if ( isset( $seen[ $hash ] ) ) {
				++$skipped;
				continue;
			}

			$post_id = self::create_listing( $data, $type_slug );
			if ( is_wp_error( $post_id ) ) {
				++$errors;
				self::append_message( $run_id, $post_id->get_error_message() );
				continue;
			}

			$seen[ $hash ] = (int) $post_id;
			$created[]     = (int) $post_id;
			++$imported;
		}

		// Persist the advanced cursor + idempotency map BEFORE returning so a
		// crash after this point resumes from the next chunk, not this one.
		$state              = self::get_state( $run_id ) ?? $state;
		$state['cursor']    = $offset + count( $rows );
		$state['processed'] = (int) ( $state['processed'] ?? 0 ) + count( $rows );
		$state['imported']  = (int) ( $state['imported'] ?? 0 ) + $imported;
		$state['skipped']   = (int) ( $state['skipped'] ?? 0 ) + $skipped;
		$state['errors']    = (int) ( $state['errors'] ?? 0 ) + $errors;
		$state['seen']      = $seen;
		$state['created']   = $created;
		self::put_state( $run_id, $state );

		return count( $rows ) >= $limit;
	}

	// ─── Row reading + mapping ───

	/**
	 * Read a bounded window of rows from a source file. CSV rows come back as
	 * indexed arrays; JSON rows as associative arrays.
	 *
	 * @param string $kind   'csv' | 'json'.
	 * @param string $path   Source file path.
	 * @param int    $offset Rows to skip.
	 * @param int    $limit  Max rows to read.
	 * @return array<int,array<int|string,mixed>>
	 */
	private static function read_rows( string $kind, string $path, int $offset, int $limit ): array {
		if ( 'json' === $kind ) {
			$raw = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			if ( false === $raw ) {
				return array();
			}
			$decoded = json_decode( $raw, true );
			if ( ! is_array( $decoded ) ) {
				return array();
			}
			// Accept either a top-level array or a { listings: [...] } envelope.
			if ( isset( $decoded['listings'] ) && is_array( $decoded['listings'] ) ) {
				$decoded = $decoded['listings'];
			}
			return array_slice( array_values( $decoded ), $offset, $limit );
		}

		// CSV: stream past the header + the already-processed offset.
		$rows   = array();
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $handle ) {
			return array();
		}

		fgetcsv( $handle ); // Discard header row.

		$skipped = 0;
		while ( $skipped < $offset && false !== fgetcsv( $handle ) ) {
			++$skipped;
		}

		$read = 0;
		while ( $read < $limit ) {
			$row = fgetcsv( $handle );
			if ( false === $row ) {
				break;
			}
			$rows[] = $row;
			++$read;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		return $rows;
	}

	/**
	 * Map an indexed CSV row to listing data using the column mapping.
	 *
	 * @param array<int|string,mixed>  $row     CSV row values (int-indexed at runtime).
	 * @param array<int|string,string> $mapping Column index => field key.
	 * @return array<string,mixed>
	 */
	private static function map_csv_row( array $row, array $mapping ): array {
		$data = array();
		foreach ( $mapping as $col_idx => $field_key ) {
			$col_idx = (int) $col_idx;
			if ( '_skip' === $field_key || ! isset( $row[ $col_idx ] ) ) {
				continue;
			}
			$value = trim( (string) $row[ $col_idx ] );
			if ( '' === $value ) {
				continue;
			}
			$data[ $field_key ] = $value;
		}
		return $data;
	}

	/**
	 * Map a JSON object to listing data. Mirrors the core field set the
	 * JSON_Importer accepts so a single source format works on both paths.
	 *
	 * @param array<int|string,mixed> $item JSON listing object (string-keyed at runtime).
	 * @return array<string,mixed>
	 */
	private static function map_json_row( array $item ): array {
		$data = array();

		if ( ! empty( $item['title'] ) ) {
			$data['title'] = (string) $item['title'];
		}
		if ( ! empty( $item['description'] ) ) {
			$data['description'] = (string) $item['description'];
		}
		if ( ! empty( $item['category'] ) ) {
			$data['category'] = (string) $item['category'];
		} elseif ( ! empty( $item['categories'] ) && is_array( $item['categories'] ) ) {
			$data['category'] = implode( ',', array_map( 'strval', $item['categories'] ) );
		}
		if ( ! empty( $item['tags'] ) ) {
			$data['tags'] = is_array( $item['tags'] ) ? implode( ',', array_map( 'strval', $item['tags'] ) ) : (string) $item['tags'];
		}
		if ( ! empty( $item['location'] ) ) {
			$data['location'] = is_array( $item['location'] ) ? implode( ',', array_map( 'strval', $item['location'] ) ) : (string) $item['location'];
		}
		if ( ! empty( $item['image_url'] ) ) {
			$data['image_url'] = (string) $item['image_url'];
		}
		if ( ! empty( $item['meta'] ) && is_array( $item['meta'] ) ) {
			foreach ( $item['meta'] as $key => $value ) {
				$data[ 'meta_' . sanitize_key( $key ) ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			}
		}

		return $data;
	}

	/**
	 * Create a listing from mapped data. Delegates to the same logic the
	 * synchronous importers use (term resolution, meta, image sideload),
	 * reusing the 1.1.0 image dedupe/timeout work rather than duplicating it.
	 *
	 * @param array<string,mixed> $data      Mapped listing data.
	 * @param string              $type_slug Listing type slug.
	 * @return int|\WP_Error Post ID or error.
	 */
	private static function create_listing( array $data, string $type_slug ) {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'listora_listing',
				'post_title'   => sanitize_text_field( (string) $data['title'] ),
				'post_content' => sanitize_textarea_field( (string) ( $data['description'] ?? '' ) ),
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id(),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_object_terms( $post_id, $type_slug, 'listora_listing_type' );

		if ( ! empty( $data['category'] ) ) {
			$cats = array_map( 'trim', explode( ',', (string) $data['category'] ) );
			Term_Helper::set_terms( $post_id, $cats, 'listora_listing_cat' );
		}
		if ( ! empty( $data['tags'] ) ) {
			$tags = array_map( 'trim', explode( ',', (string) $data['tags'] ) );
			Term_Helper::set_terms( $post_id, $tags, 'listora_listing_tag' );
		}
		if ( ! empty( $data['location'] ) ) {
			$locs = array_map( 'trim', explode( ',', (string) $data['location'] ) );
			Term_Helper::set_terms( $post_id, $locs, 'listora_listing_location' );
		}

		foreach ( $data as $key => $value ) {
			if ( 0 === strpos( (string) $key, 'meta_' ) && class_exists( '\WBListora\Core\Meta_Handler' ) ) {
				\WBListora\Core\Meta_Handler::set_value( $post_id, substr( (string) $key, 5 ), $value );
			}
		}

		if ( ! empty( $data['image_url'] ) ) {
			$image_id = self::sideload_image( (string) $data['image_url'], (int) $post_id );
			if ( $image_id && ! is_wp_error( $image_id ) ) {
				set_post_thumbnail( $post_id, $image_id );
			}
		}

		return (int) $post_id;
	}

	/**
	 * Sideload a remote image to a post. Mirrors CSV_Importer::sideload_image
	 * so the batched path inherits the same media handling.
	 *
	 * @param string $url     Image URL.
	 * @param int    $post_id Post ID to attach to.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private static function sideload_image( string $url, int $post_id ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file_array = array(
			'name'     => basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		$id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
		}

		return $id;
	}

	// ─── REST progress endpoint ───

	/**
	 * Register the read-only progress route. Self-registered on the plugin's
	 * `wb_listora_rest_api_init` hook so the route lives inside the import
	 * subsystem rather than the shared controller list.
	 *
	 * @return void
	 */
	public static function register_rest_routes(): void {
		register_rest_route(
			WB_LISTORA_REST_NAMESPACE,
			'/import/progress/(?P<run_id>[A-Za-z0-9]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( __CLASS__, 'rest_progress' ),
					'permission_callback' => array( __CLASS__, 'progress_permissions' ),
					'args'                => array(
						'run_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( __CLASS__, 'sanitize_run_id' ),
							'description'       => __( 'Background import run identifier.', 'wb-listora' ),
						),
					),
				),
			)
		);

		// POST /import/queue/csv — stage an uploaded CSV and queue a resumable,
		// idempotent background import. Returns the run_id the admin UI polls
		// against the progress route above. Self-registered here (rather than in
		// the synchronous Import_Export_Controller) so the whole background
		// pipeline — queue + progress — lives inside this subsystem.
		register_rest_route(
			WB_LISTORA_REST_NAMESPACE,
			'/import/queue/csv',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( __CLASS__, 'rest_queue_csv' ),
					'permission_callback' => array( __CLASS__, 'progress_permissions' ),
					'args'                => array(
						'type_slug' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Listing type slug for imported listings.', 'wb-listora' ),
						),
						'mapping'   => array(
							// JSON-encoded column->field map carried inside the
							// multipart body (FormData cannot nest objects), so
							// accept a string and decode it in the handler.
							'type'        => array( 'string', 'object' ),
							'required'    => true,
							'description' => __( 'Column index to field key mapping, JSON-encoded.', 'wb-listora' ),
						),
					),
				),
			)
		);
	}

	/**
	 * REST handler: stage an uploaded CSV and queue a background import run.
	 *
	 * Mirrors the validation the synchronous Import_Export_Controller applies
	 * (file presence, MIME allowlist, upload-error guard, mapping decode) so
	 * the two import paths reject the same malformed input identically, then
	 * hands off to {@see self::queue_file()} and returns the run_id + total.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_queue_csv( $request ) {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) || empty( $files['file']['tmp_name'] ) ) {
			return new \WP_Error(
				'listora_import_no_file',
				__( 'No CSV file uploaded. Send the file as "file" in multipart/form-data.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		$file = $files['file'];

		$mime_types = array( 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel' );
		if ( ! empty( $file['type'] ) && ! in_array( $file['type'], $mime_types, true ) ) {
			return new \WP_Error(
				'listora_import_invalid_type',
				__( 'Invalid file type. Please upload a CSV file.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $file['error'] ) ) {
			return new \WP_Error(
				'listora_import_upload_error',
				/* translators: %d: PHP upload error code */
				sprintf( __( 'File upload error (code %d).', 'wb-listora' ), (int) $file['error'] ),
				array( 'status' => 400 )
			);
		}

		$type_slug = sanitize_text_field( (string) $request->get_param( 'type_slug' ) );

		$mapping = $request->get_param( 'mapping' );
		if ( is_string( $mapping ) ) {
			$decoded = json_decode( $mapping, true );
			$mapping = is_array( $decoded ) ? $decoded : array();
		}
		if ( ! is_array( $mapping ) || empty( $mapping ) ) {
			return new \WP_Error(
				'listora_import_invalid_mapping',
				__( 'Invalid or empty column mapping. Map at least one column to a listing field.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		$total  = self::count_csv_rows( (string) $file['tmp_name'] );
		$run_id = self::queue_file( 'csv', (string) $file['tmp_name'], $type_slug, $mapping, $total );

		if ( is_wp_error( $run_id ) ) {
			return $run_id;
		}

		$progress = self::get_progress( $run_id );

		return new \WP_REST_Response(
			array(
				'run_id'   => $run_id,
				'total'    => $total,
				'status'   => is_array( $progress ) ? ( $progress['status'] ?? self::STATUS_QUEUED ) : self::STATUS_QUEUED,
				'done'     => is_array( $progress ) ? (bool) ( $progress['done'] ?? false ) : false,
				'imported' => is_array( $progress ) ? (int) ( $progress['imported'] ?? 0 ) : 0,
			),
			200
		);
	}

	/**
	 * Count data rows in a CSV (excluding the header) for the progress total.
	 *
	 * @param string $path Path to the readable CSV file.
	 * @return int
	 */
	private static function count_csv_rows( string $path ): int {
		if ( '' === $path || ! is_readable( $path ) ) {
			return 0;
		}
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( false === $handle ) {
			return 0;
		}
		fgetcsv( $handle ); // Discard header.
		$count = 0;
		while ( false !== fgetcsv( $handle ) ) {
			++$count;
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $count;
	}

	/**
	 * REST handler for the progress poll.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function rest_progress( $request ) {
		$run_id   = self::sanitize_run_id( (string) $request->get_param( 'run_id' ) );
		$progress = self::get_progress( $run_id );

		if ( null === $progress ) {
			return new \WP_Error(
				'listora_import_run_not_found',
				__( 'Import run not found. It may have completed and been cleaned up.', 'wb-listora' ),
				array( 'status' => 404 )
			);
		}

		return new \WP_REST_Response( $progress, 200 );
	}

	/**
	 * Permission check for the progress endpoint — same `manage_options`
	 * gate as the import controller, returning WP_Error so headless clients
	 * branch on a single error-code enum.
	 *
	 * @return true|\WP_Error
	 */
	public static function progress_permissions() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	// ─── State helpers ───

	/**
	 * Create a new run option and return its run_id.
	 *
	 * @param array<string,mixed> $seed Initial state fields.
	 * @return string The run_id.
	 */
	private static function new_run( array $seed ): string {
		$run_id = self::generate_run_id();

		$state = wp_parse_args(
			$seed,
			array(
				'run_id'     => $run_id,
				'status'     => self::STATUS_QUEUED,
				'processed'  => 0,
				'imported'   => 0,
				'skipped'    => 0,
				'errors'     => 0,
				'created'    => array(),
				'seen'       => array(),
				'messages'   => array(),
				'started_at' => time(),
			)
		);

		self::put_state( $run_id, $state );

		return $run_id;
	}

	/**
	 * Decide between the AS-batched path and the synchronous fallback, then
	 * kick off whichever applies. Returns the run_id either way.
	 *
	 * @param string $run_id Run identifier.
	 * @return string
	 */
	private static function dispatch( string $run_id ): string {
		$state = self::get_state( $run_id );
		if ( null === $state ) {
			return $run_id;
		}

		$total     = (int) ( $state['total'] ?? 0 );
		$use_async = Cron_Scheduler::has_action_scheduler() && $total > self::SYNC_THRESHOLD;

		/**
		 * Filter whether a given run executes asynchronously over Action
		 * Scheduler. Returning false forces the synchronous fallback.
		 *
		 * @param bool                $use_async Whether to run async.
		 * @param array<string,mixed> $state     The run state.
		 */
		$use_async = (bool) apply_filters( 'wb_listora_bg_import_use_async', $use_async, $state );

		if ( $use_async ) {
			self::enqueue_batch( $run_id );
			return $run_id;
		}

		// Synchronous fallback — drain the run inline, then finalize.
		self::run_synchronously( $run_id );
		return $run_id;
	}

	/**
	 * Drain a run inline (no AS), used for tiny packs or AS-less installs.
	 *
	 * @param string $run_id Run identifier.
	 * @return void
	 */
	private static function run_synchronously( string $run_id ): void {
		$guard = 0;
		do {
			$state = self::get_state( $run_id );
			if ( null === $state ) {
				return;
			}
			$state['status'] = self::STATUS_RUNNING;
			self::put_state( $run_id, $state );

			try {
				$has_more = ( 'demo' === ( $state['kind'] ?? '' ) )
					? self::process_demo_unit( $run_id, $state )
					: self::process_file_chunk( $run_id, $state );
			} catch ( \Throwable $e ) {
				self::append_message( $run_id, $e->getMessage() );
				self::bump( $run_id, 'errors', 1 );
				self::mark_failed( $run_id );
				return;
			}
			++$guard;
		} while ( $has_more && $guard < 10000 );

		// A chunk processor may have marked the run failed mid-chunk (e.g.
		// missing source file) — don't overwrite that with DONE.
		$state = self::get_state( $run_id );
		if ( null !== $state && self::STATUS_FAILED === ( $state['status'] ?? '' ) ) {
			return;
		}

		self::run_finalize( $run_id );
	}

	/**
	 * Queue the next batch action for a run (idempotent — won't double-queue).
	 *
	 * @param string $run_id Run identifier.
	 * @return void
	 */
	private static function enqueue_batch( string $run_id ): void {
		// Delegates to the Cron_Scheduler abstraction, which is idempotent on
		// the AS path and falls back to a WP-Cron single event when Action
		// Scheduler is absent — so a chained batch still advances on a
		// Free-only install rather than stalling.
		Cron_Scheduler::enqueue_async( self::HOOK_BATCH, array( $run_id ), self::GROUP );
	}

	/**
	 * Queue the finalize (index rebuild) action for a run.
	 *
	 * @param string $run_id Run identifier.
	 * @return void
	 */
	private static function enqueue_finalize( string $run_id ): void {
		Cron_Scheduler::enqueue_async( self::HOOK_FINALIZE, array( $run_id ), self::GROUP );
	}

	/**
	 * Read run state.
	 *
	 * @param string $run_id Run identifier.
	 * @return array<string,mixed>|null
	 */
	private static function get_state( string $run_id ): ?array {
		$run_id = self::sanitize_run_id( $run_id );
		if ( '' === $run_id ) {
			return null;
		}
		$state = get_option( self::STATE_PREFIX . $run_id, null );
		return is_array( $state ) ? $state : null;
	}

	/**
	 * Write run state. Stored with autoload off — these are large, transient
	 * blobs that must not bloat the alloptions cache.
	 *
	 * @param string              $run_id Run identifier.
	 * @param array<string,mixed> $state  Full state to persist.
	 * @return void
	 */
	private static function put_state( string $run_id, array $state ): void {
		$run_id = self::sanitize_run_id( $run_id );
		if ( '' === $run_id ) {
			return;
		}
		update_option( self::STATE_PREFIX . $run_id, $state, false );
	}

	/**
	 * Increment a numeric counter in the freshest run state.
	 *
	 * @param string $run_id Run identifier.
	 * @param string $key    Counter key.
	 * @param int    $by     Amount to add.
	 * @return void
	 */
	private static function bump( string $run_id, string $key, int $by ): void {
		$state = self::get_state( $run_id );
		if ( null === $state ) {
			return;
		}
		$state[ $key ] = (int) ( $state[ $key ] ?? 0 ) + $by;
		self::put_state( $run_id, $state );
	}

	/**
	 * Append a capped message to the run log (keeps the last 50).
	 *
	 * @param string $run_id  Run identifier.
	 * @param string $message Message text.
	 * @return void
	 */
	private static function append_message( string $run_id, string $message ): void {
		$state = self::get_state( $run_id );
		if ( null === $state ) {
			return;
		}
		$messages          = (array) ( $state['messages'] ?? array() );
		$messages[]        = sanitize_text_field( $message );
		$state['messages'] = array_slice( $messages, -50 );
		self::put_state( $run_id, $state );
	}

	/**
	 * Mark a run failed.
	 *
	 * @param string $run_id Run identifier.
	 * @return void
	 */
	private static function mark_failed( string $run_id ): void {
		$state = self::get_state( $run_id );
		if ( null === $state ) {
			return;
		}
		$state['status'] = self::STATUS_FAILED;
		self::put_state( $run_id, $state );
	}

	// ─── Small utilities ───

	/**
	 * Generate a URL-safe opaque run identifier.
	 *
	 * @return string
	 */
	private static function generate_run_id(): string {
		return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 20 );
	}

	/**
	 * Sanitize a run_id to the generated alphabet (alphanumeric).
	 *
	 * @param string $run_id Raw run_id.
	 * @return string
	 */
	public static function sanitize_run_id( $run_id ): string {
		return preg_replace( '/[^A-Za-z0-9]/', '', (string) $run_id );
	}

	/**
	 * Deterministic fingerprint for a source row, used for idempotency.
	 *
	 * @param string              $kind      Import kind.
	 * @param string              $type_slug Listing type slug.
	 * @param array<string,mixed> $data      Mapped row data.
	 * @return string
	 */
	private static function row_hash( string $kind, string $type_slug, array $data ): string {
		ksort( $data );
		return md5( $kind . '|' . $type_slug . '|' . wp_json_encode( $data ) );
	}

	/**
	 * Count published + draft listings (used to estimate demo-pack output).
	 *
	 * @return int
	 */
	private static function count_listings(): int {
		$counts = (array) wp_count_posts( 'listora_listing' );
		$total  = 0;
		foreach ( array( 'publish', 'draft', 'pending', 'private' ) as $status ) {
			$total += (int) ( $counts[ $status ] ?? 0 );
		}
		return $total;
	}

	/**
	 * Copy a transient upload into the uploads dir so it survives past the
	 * request. Returns the stable path or a WP_Error.
	 *
	 * @param string $file_path Source (often a PHP upload tmp file).
	 * @param string $kind      Import kind (for the extension).
	 * @return string|\WP_Error
	 */
	private static function stash_source_file( string $file_path, string $kind ) {
		if ( '' === $file_path || ! is_readable( $file_path ) ) {
			return new \WP_Error(
				'listora_import_unreadable_source',
				__( 'The import source file could not be read.', 'wb-listora' ),
				array( 'status' => 400 )
			);
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'listora_import_no_uploads', (string) $uploads['error'], array( 'status' => 500 ) );
		}

		$dir = trailingslashit( $uploads['basedir'] ) . 'wb-listora-import';
		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error(
				'listora_import_mkdir_failed',
				__( 'Could not create the import staging directory.', 'wb-listora' ),
				array( 'status' => 500 )
			);
		}

		$ext  = ( 'json' === $kind ) ? 'json' : 'csv';
		$dest = trailingslashit( $dir ) . 'src-' . self::generate_run_id() . '.' . $ext;

		if ( ! @copy( $file_path, $dest ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			return new \WP_Error(
				'listora_import_copy_failed',
				__( 'Could not stage the import file for background processing.', 'wb-listora' ),
				array( 'status' => 500 )
			);
		}

		return $dest;
	}

	/**
	 * Normalize a column/field mapping to int keys + sanitized values.
	 *
	 * @param array<int|string,string> $mapping Raw mapping.
	 * @return array<int,string>
	 */
	private static function sanitize_mapping( array $mapping ): array {
		$clean = array();
		foreach ( $mapping as $col => $field ) {
			$clean[ (int) $col ] = sanitize_text_field( (string) $field );
		}
		return $clean;
	}

	/**
	 * Ensure the demo seeder class is loaded (it lives outside the autoload
	 * tree under demo/).
	 *
	 * @return void
	 */
	private static function ensure_demo_seeder(): void {
		if ( class_exists( '\WBListora\Demo\Demo_Seeder' ) ) {
			return;
		}
		$file = WB_LISTORA_PLUGIN_DIR . 'demo/class-demo-seeder.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
