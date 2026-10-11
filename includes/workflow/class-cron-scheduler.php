<?php
/**
 * Cron Scheduler — abstraction over Action Scheduler with WP-Cron fallback.
 *
 * Uses Action Scheduler when loaded (bundled by Pro, WooCommerce, ...) for
 * retries and low-traffic reliability, else wp_schedule_event(). Each call
 * clears any WP-Cron event for the hook before scheduling via AS, so the move
 * to AS when Pro activates is one-time and idempotent.
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
	 * Is this a request where recurring schedules should be checked and armed?
	 *
	 * Only the cron runner, wp-admin page loads and WP-CLI: never a front-end,
	 * REST or AJAX request (each check is a query, paid on every request).
	 *
	 * @return bool
	 */
	public static function is_scheduling_request() {
		return wp_doing_cron()
			|| ( is_admin() && ! wp_doing_ajax() )
			|| ( defined( 'WP_CLI' ) && WP_CLI );
	}

	/**
	 * Whether Action Scheduler's API is loaded AND its data store is ready.
	 *
	 * function_exists() is true as soon as AS autoloads, but as_*() calls
	 * before `action_scheduler_init` emit _doing_it_wrong notices. Callers
	 * before then use WP-Cron and migrate to AS on the next call.
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
		// Checking a schedule is a database query; on init it was paid by every page,
		// REST call, image and heartbeat. Every caller arms from init, so the cron
		// runner (or the next admin page) arms or re-arms the job within one tick.
		if ( ! self::is_scheduling_request() ) {
			return true;
		}
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
	 * Chains importer batches. A no-op when an identical PENDING action (hook +
	 * args + group) exists. Only PENDING is checked, not as_next_scheduled_action():
	 * that also matches the RUNNING action, so a batch could never requeue itself.
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
			// Guard against double-pending-enqueue using a PENDING-only query.
			// We must NOT use as_next_scheduled_action() here because it returns
			// truthy for STATUS_RUNNING actions (AS checks running first), which
			// causes the self-requeue deadlock described in the docblock above.
			// Use the 'pending' string literal rather than ActionScheduler_Store::STATUS_PENDING
			// to avoid a class-not-found error when AS is not on the analyse path.
			$pending = function_exists( 'as_get_scheduled_actions' )
				? as_get_scheduled_actions(
					array(
						'hook'     => $hook,
						'args'     => $args,
						'group'    => $group,
						'status'   => 'pending',
						'per_page' => 1,
						'fields'   => 'ids',
					)
				)
				: array();

			if ( ! empty( $pending ) ) {
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
	 * Two simultaneous requests during activation can both pass the
	 * as_next_scheduled_action() check and insert the same recurring action.
	 * Keeps the earliest pending action and cancels the rest; idempotent.
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
				wb_listora_log( 'Cron dedupe: failed to cancel action ' . (int) $action_id . ': ' . $e->getMessage() );
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
