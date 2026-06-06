<?php
/**
 * Cron Scheduler — abstraction over Action Scheduler with WP-Cron fallback.
 *
 * Action Scheduler is bundled by the Pro plugin (and by WooCommerce /
 * other ecosystem plugins). When AS is available, recurring jobs are
 * scheduled via {@see as_schedule_recurring_action()} so they survive
 * low-traffic windows and benefit from retry semantics. When AS is NOT
 * loaded — typically a Free-only install — we fall back to
 * {@see wp_schedule_event()} so cron continues to function.
 *
 * Migration semantics: each scheduling call clears any pre-existing
 * WP-Cron event for the same hook before scheduling via AS, ensuring
 * a one-time, idempotent transition when Pro is later activated.
 *
 * @package WBListora\Workflow
 *
 * @since 1.0.0
 */

declare(strict_types=1);

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules recurring cron jobs via Action Scheduler when available,
 * with a graceful WP-Cron fallback for Free-only installs.
 */
class Cron_Scheduler {

	/**
	 * Default Action Scheduler group for Free's cron jobs.
	 *
	 * @var string
	 */
	const GROUP = 'wb-listora';

	/**
	 * Hooks already (re)scheduled in THIS request, keyed by "hook|group".
	 *
	 * Action Scheduler's `as_next_scheduled_action()` does not reflect an
	 * insert made earlier in the same request until it is committed, so two
	 * `schedule_recurring()` calls for the same hook in one bootstrap (e.g.
	 * activation followed by `init` in the same request) both pass the guard
	 * and create duplicate recurring actions (consecutive action_ids, identical
	 * schedule) that then double-fire. This request-static guard makes a second
	 * call in the same request a no-op.
	 *
	 * @var array<string, bool>
	 */
	private static $scheduled_in_request = array();

	/**
	 * Map of WP-Cron schedule names to interval seconds. AS uses raw
	 * second intervals, so we translate WP-Cron schedules through this
	 * map. Falls back to DAY_IN_SECONDS for unknown names.
	 *
	 * @return array<string, int>
	 */
	private static function schedule_intervals() {
		return array(
			'hourly'     => HOUR_IN_SECONDS,
			'twicedaily' => 12 * HOUR_IN_SECONDS,
			'daily'      => DAY_IN_SECONDS,
			'weekly'     => 7 * DAY_IN_SECONDS,
		);
	}

	/**
	 * Whether Action Scheduler's API is loaded AND its data store is ready.
	 *
	 * `function_exists()` alone returns true the moment AS is autoloaded, but
	 * AS's data store is not initialized until its `init` listener at priority 1
	 * runs. Calling `as_*()` functions before that point emits a `_doing_it_wrong`
	 * notice for each call. The `did_action( 'action_scheduler_init' )` check
	 * here is AS's documented "data store ready" signal — it's fired AFTER the
	 * tables are confirmed to exist and the runtime is bootstrapped.
	 *
	 * Callers that schedule before AS is ready fall through to the WP-Cron
	 * branch in {@see schedule_recurring()}; the next call after AS is ready
	 * automatically migrates via the existing "clear WP-Cron then schedule AS"
	 * path, so there is no functional regression — only an absence of debug.log
	 * notice spam during plugin bootstrap.
	 *
	 * @return bool
	 */
	public static function has_action_scheduler() {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_next_scheduled_action' )
			&& function_exists( 'as_unschedule_all_actions' )
			&& did_action( 'action_scheduler_init' ) > 0;
	}

	/**
	 * Whether a recurring job is currently scheduled (AS or WP-Cron).
	 *
	 * @param string $hook  Cron hook name.
	 * @param string $group AS group (ignored for WP-Cron).
	 * @return bool
	 */
	public static function has_scheduled( $hook, $group = self::GROUP ) {
		if ( self::has_action_scheduler() && as_next_scheduled_action( $hook, array(), $group ) ) {
			return true;
		}
		return (bool) wp_next_scheduled( $hook );
	}

	/**
	 * Schedule a recurring job.
	 *
	 * Idempotent — if the job is already scheduled (via AS or WP-Cron),
	 * this is a no-op. When AS is available and a WP-Cron event for the
	 * same hook exists, the WP-Cron event is cleared before AS takes
	 * over, so a one-shot migration occurs the first time the new code
	 * path runs.
	 *
	 * @param string $schedule WP-Cron schedule name (hourly|twicedaily|daily|weekly).
	 * @param string $hook     Cron hook name to fire.
	 * @param int    $first_at Optional Unix timestamp for first run. Default: time().
	 * @param string $group    Optional AS group. Default: self::GROUP.
	 * @return bool True if scheduled (or already scheduled), false on failure.
	 */
	public static function schedule_recurring( $schedule, $hook, $first_at = 0, $group = self::GROUP ) {
		$first_at = $first_at > 0 ? (int) $first_at : time();

		if ( self::has_action_scheduler() ) {
			// Migrate: clear any leftover WP-Cron event for the same hook so
			// AS becomes the sole scheduler. Without this the job would
			// double-fire whenever both schedulers think they own it.
			if ( wp_next_scheduled( $hook ) ) {
				wp_clear_scheduled_hook( $hook );
			}

			// Same-request dedup. A second call for this hook in the same
			// bootstrap cannot see the first (not-yet-committed) insert through
			// as_next_scheduled_action(), which is how duplicate recurring
			// actions were created. Mark the hook handled for this request.
			$request_key = $hook . '|' . $group;
			if ( isset( self::$scheduled_in_request[ $request_key ] ) ) {
				return true;
			}
			self::$scheduled_in_request[ $request_key ] = true;

			if ( as_next_scheduled_action( $hook, array(), $group ) ) {
				return true;
			}

			$intervals = self::schedule_intervals();
			$interval  = isset( $intervals[ $schedule ] ) ? $intervals[ $schedule ] : DAY_IN_SECONDS;

			$action_id = as_schedule_recurring_action(
				$first_at,
				$interval,
				$hook,
				array(),
				$group
			);

			return $action_id > 0;
		}

		// Fallback: WP-Cron.
		if ( ! wp_next_scheduled( $hook ) ) {
			$result = wp_schedule_event( $first_at, $schedule, $hook );
			return false !== $result;
		}

		return true;
	}

	/**
	 * Enqueue a one-off async action via Action Scheduler, with a WP-Cron
	 * single-event fallback.
	 *
	 * Used by the background importer to chain batch → next-batch → finalize
	 * jobs. Idempotent on the AS path: if an identical pending action (same
	 * hook + args + group) already exists, this is a no-op so a re-fired chunk
	 * cannot double-queue itself. When AS is unavailable, falls back to a
	 * near-future `wp_schedule_single_event` so Free-only installs still chain.
	 *
	 * @param string            $hook  Hook name to fire.
	 * @param array<int, mixed> $args  Positional args passed to the hook.
	 * @param string            $group Optional AS group. Default: self::GROUP.
	 * @return bool True if enqueued (or already pending), false on failure.
	 */
	public static function enqueue_async( $hook, array $args = array(), $group = self::GROUP ) {
		// Normalize to a positional list so both Action Scheduler and WP-Cron
		// receive the args in the shape they expect.
		$args = array_values( $args );

		// `has_action_scheduler()` already proves the recurring API is loaded;
		// guard the one-off enqueue the same way (it's not in that check's set)
		// before calling it. AS is a Pro/WooCommerce dependency absent at
		// analyse time, so these direct calls are covered by phpstan-baseline
		// the same way the rest of this class's AS calls are.
		if ( self::has_action_scheduler() && function_exists( 'as_enqueue_async_action' ) ) {
			if ( as_next_scheduled_action( $hook, $args, $group ) ) {
				return true;
			}
			$action_id = as_enqueue_async_action( $hook, $args, $group );
			return $action_id > 0;
		}

		// Fallback: WP-Cron single event a minute out. WP-Cron keys events by
		// hook + args, so a duplicate schedule for the same args is itself a
		// no-op — matching the AS idempotency above.
		if ( ! wp_next_scheduled( $hook, $args ) ) {
			$result = wp_schedule_single_event( time() + MINUTE_IN_SECONDS, $hook, $args );
			return false !== $result;
		}

		return true;
	}

	/**
	 * Unschedule a recurring job from BOTH AS and WP-Cron.
	 *
	 * Safe to call regardless of which scheduler currently owns the
	 * job — clears whichever is active. Used by deactivation handlers.
	 *
	 * @param string $hook  Cron hook name.
	 * @param string $group Optional AS group. Default: self::GROUP.
	 * @return void
	 */
	public static function unschedule( $hook, $group = self::GROUP ) {
		if ( self::has_action_scheduler() ) {
			as_unschedule_all_actions( $hook, array(), $group );
		}
		wp_clear_scheduled_hook( $hook );
	}

	/**
	 * Unschedule every recurring job in a given AS group AND any matching
	 * WP-Cron events for the hooks listed.
	 *
	 * @param string[] $hooks Hooks to clear from WP-Cron.
	 * @param string   $group AS group whose actions should be cleared.
	 * @return void
	 */
	public static function unschedule_all( array $hooks, $group = self::GROUP ) {
		if ( self::has_action_scheduler() ) {
			as_unschedule_all_actions( null, array(), $group );
		}
		foreach ( $hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	/**
	 * Detect + remove duplicate pending recurring actions for a given hook.
	 *
	 * The in-request guard in {@see schedule_recurring()} prevents same-request
	 * double-creation, but a narrow cross-request race during plugin activation
	 * can still slip through: two simultaneous requests both hit
	 * `as_next_scheduled_action()` before either has committed. Each then
	 * inserts a recurring action with the same hook+group — five Free crons
	 * were observed with this pattern in BC 9910208588.
	 *
	 * This method queries pending actions for the hook and, when more than one
	 * exists, keeps the FIRST (earliest scheduled) and cancels the rest. Safe
	 * to call repeatedly — a no-op when the count is already 1.
	 *
	 * @param string $hook  Cron hook name to deduplicate.
	 * @param string $group Optional AS group. Default: self::GROUP.
	 * @return int Number of duplicate actions cancelled (0 means already clean).
	 */
	public static function dedupe_pending( $hook, $group = self::GROUP ) {
		if ( ! self::has_action_scheduler() ) {
			return 0;
		}
		if ( ! function_exists( 'as_get_scheduled_actions' ) ) {
			return 0;
		}

		$action_ids = as_get_scheduled_actions(
			array(
				'hook'     => $hook,
				'group'    => $group,
				'status'   => 'pending',
				'orderby'  => 'date',
				'order'    => 'ASC',
				'per_page' => 50,
			),
			'ids'
		);

		if ( ! is_array( $action_ids ) || count( $action_ids ) <= 1 ) {
			return 0;
		}

		// Keep the first (earliest), cancel the rest via the public AS API.
		$keep      = array_shift( $action_ids );
		$cancelled = 0;
		foreach ( $action_ids as $action_id ) {
			try {
				$store = \ActionScheduler::store();
				if ( $store && method_exists( $store, 'cancel_action' ) ) {
					$store->cancel_action( (int) $action_id );
					++$cancelled;
				}
			} catch ( \Throwable $e ) {
				// Best-effort — a single failed cancellation shouldn't block
				// the rest of the cleanup.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( '[Listora cron dedupe] failed to cancel action ' . (int) $action_id . ': ' . $e->getMessage() );
				}
			}
		}
		unset( $keep ); // Suppress unused-var warning; $keep documents intent.
		return $cancelled;
	}

	/**
	 * Sweep all known Free recurring hooks for duplicates. One-shot cleanup
	 * triggered on bootstrap (idempotent — no-op when the registry is clean).
	 *
	 * @param string[] $hooks Hooks to sweep.
	 * @param string   $group Optional AS group. Default: self::GROUP.
	 * @return array<string, int> hook => number of duplicates cancelled.
	 */
	public static function dedupe_pending_batch( array $hooks, $group = self::GROUP ) {
		$report = array();
		foreach ( $hooks as $hook ) {
			$report[ $hook ] = self::dedupe_pending( $hook, $group );
		}
		return $report;
	}
}
