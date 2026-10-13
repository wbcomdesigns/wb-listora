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
	 * Filter that add-ons (Pro features) use to add their recurring jobs.
	 *
	 * @var string
	 */
	const JOBS_FILTER = 'wb_listora_cron_jobs';

	/**
	 * Option holding the fingerprint of the last armed job list and when.
	 *
	 * @var string
	 */
	const ARMED_OPTION = 'wb_listora_cron_armed';

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
	 * Every recurring job, keyed by hook.
	 *
	 * The one list arming, the duplicate sweep and deactivation read. Free's
	 * jobs are listed here; a Pro feature adds its own through
	 * `wb_listora_cron_jobs` while it is switched on, so a job exists only
	 * while something handles it.
	 *
	 * @since 2.0.0
	 *
	 * @return array<string, array{schedule: string, group: string, first_at: int, delay: int}>
	 */
	public static function jobs(): array {
		$jobs = array(
			'wb_listora_check_expirations'   => array( 'schedule' => 'twicedaily' ),
			'wb_listora_draft_reminder_cron' => array( 'schedule' => 'twicedaily' ),
			'wb_listora_review_reminder_cron' => array( 'schedule' => 'daily' ),
			'wb_listora_daily_cleanup'       => array( 'schedule' => 'daily' ),
			\WBListora\Core\Featured::CRON_HOOK => array(
				'schedule' => 'daily',
				'delay'    => HOUR_IN_SECONDS,
			),
			Email_Verification::CRON_HOOK    => array(
				'schedule' => 'daily',
				'delay'    => HOUR_IN_SECONDS,
			),
			Notifications::PRUNE_HOOK        => array( 'schedule' => 'daily' ),
		);

		/**
		 * Recurring jobs Listora keeps scheduled.
		 *
		 * Add `hook => array( 'schedule' => hourly|twicedaily|daily|weekly,
		 * 'group' => Action Scheduler group, 'delay' => seconds before the
		 * first run, or 'first_at' => timestamp )`. Add a job only while the
		 * feature that handles it is on; it is unscheduled with that feature.
		 *
		 * @since 2.0.0
		 *
		 * @param array<string, array<string, mixed>> $jobs Hook => job.
		 */
		$jobs = (array) apply_filters( self::JOBS_FILTER, $jobs );

		$intervals = self::schedule_intervals();
		$out       = array();
		foreach ( $jobs as $hook => $job ) {
			if ( ! is_string( $hook ) || '' === $hook || ! is_array( $job ) ) {
				continue;
			}
			$schedule     = (string) ( $job['schedule'] ?? '' );
			$group        = (string) ( $job['group'] ?? '' );
			$out[ $hook ] = array(
				'schedule' => isset( $intervals[ $schedule ] ) ? $schedule : 'daily',
				'group'    => '' !== $group ? $group : self::GROUP,
				'first_at' => (int) ( $job['first_at'] ?? 0 ),
				'delay'    => (int) ( $job['delay'] ?? 0 ),
			);
		}
		ksort( $out );
		return $out;
	}

	/**
	 * Arm every job in {@see jobs()} and collapse duplicates, when due.
	 *
	 * Checking a job costs Action Scheduler queries, about 45 for the whole
	 * list, so the check runs only when the list changed (an update, a
	 * feature toggle) or the last check is stale: hourly for the cron
	 * runner, daily for wp-admin. Never on a visitor's request.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function arm_jobs(): void {
		if ( ! self::is_scheduling_request() ) {
			return;
		}
		$jobs  = self::jobs();
		$shape = array();
		foreach ( $jobs as $hook => $job ) {
			$shape[ $hook ] = $job['schedule'] . '|' . $job['group'];
		}
		$hash  = md5( (string) wp_json_encode( $shape ) . '|' . WB_LISTORA_VERSION );
		$state = get_option( self::ARMED_OPTION );
		$stale = ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
		if ( is_array( $state ) && ( $state['hash'] ?? '' ) === $hash && (int) ( $state['at'] ?? 0 ) > time() - $stale ) {
			return;
		}

		$by_group = array();
		foreach ( $jobs as $hook => $job ) {
			$first = $job['first_at'] > 0 ? $job['first_at'] : time() + $job['delay'];
			self::schedule_recurring( $job['schedule'], $hook, $first, $job['group'] );
			$by_group[ $job['group'] ][] = $hook;
		}
		// Two requests arming at once can both insert a job; keep the earliest.
		if ( self::has_action_scheduler() ) {
			foreach ( $by_group as $group => $hooks ) {
				self::dedupe_pending_batch( $hooks, $group );
			}
		}

		update_option(
			self::ARMED_OPTION,
			array(
				'hash' => $hash,
				'at'   => time(),
			),
			true
		);
	}

	/**
	 * Make the next scheduling request re-arm every job (activation,
	 * deactivation, a job removed by hand).
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function forget_armed(): void {
		delete_option( self::ARMED_OPTION );
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
	 * Schedule a one-off job, Action Scheduler first, WP-Cron as fallback.
	 *
	 * The one way to queue single work (deliveries, batches, reindex ticks),
	 * so no caller repeats the AS / WP-Cron choice. A no-op when the same
	 * hook + args is already pending.
	 *
	 * @since 2.0.0
	 *
	 * @param string            $hook  Hook name to fire.
	 * @param array<int, mixed> $args  Positional args passed to the hook.
	 * @param int               $delay Seconds from now; 0 runs as soon as possible.
	 * @param string            $group Action Scheduler group.
	 * @return bool True if scheduled (or already pending), false on failure.
	 */
	public static function schedule_single( $hook, array $args = array(), $delay = 0, $group = self::GROUP ) {
		$args  = array_values( $args );
		$delay = max( 0, (int) $delay );
		if ( 0 === $delay ) {
			return self::enqueue_async( $hook, $args, $group );
		}

		if ( self::has_action_scheduler() && function_exists( 'as_schedule_single_action' ) ) {
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
			return as_schedule_single_action( time() + $delay, $hook, $args, $group ) > 0;
		}

		if ( ! wp_next_scheduled( $hook, $args ) ) {
			return false !== wp_schedule_single_event( time() + $delay, $hook, $args );
		}
		return true;
	}

	/**
	 * Clear every Listora job: each group in {@see jobs()} (Pro's too: it
	 * cannot run without Free) and the WP-Cron copies of the listed hooks.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $one_off_hooks One-off hooks to clear from WP-Cron as well.
	 * @return void
	 */
	public static function unschedule_all_jobs( array $one_off_hooks = array() ): void {
		$jobs   = self::jobs();
		$groups = array_unique( array_merge( array( self::GROUP ), array_column( $jobs, 'group' ) ) );
		foreach ( $groups as $group ) {
			self::unschedule_all( array(), $group );
		}
		self::unschedule_all( array_merge( array_keys( $jobs ), $one_off_hooks ), self::GROUP );
		self::forget_armed();
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
		// Let a later call in this request schedule it again.
		unset( self::$scheduled_in_request[ $hook . '|' . $group ] );
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
		// Let a later call in this request schedule the group again.
		foreach ( array_keys( self::$scheduled_in_request ) as $request_key ) {
			if ( str_ends_with( $request_key, '|' . $group ) ) {
				unset( self::$scheduled_in_request[ $request_key ] );
			}
		}
		if ( self::has_action_scheduler() ) {
			as_unschedule_all_actions( null, array(), $group );
		}
		foreach ( $hooks as $hook ) {
			// Every event of the hook, whatever its args (one-off jobs carry ids).
			wp_unschedule_hook( $hook );
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
