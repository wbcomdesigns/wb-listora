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
	 * Whether Action Scheduler's API is loaded.
	 *
	 * @return bool
	 */
	public static function has_action_scheduler() {
		return function_exists( 'as_schedule_recurring_action' )
			&& function_exists( 'as_next_scheduled_action' )
			&& function_exists( 'as_unschedule_all_actions' );
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
}
