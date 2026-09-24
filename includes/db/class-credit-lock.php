<?php
/**
 * Per-user credit lock.
 *
 * @package WBListora\DB
 */

namespace WBListora\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Serialises every credit spend for one user.
 *
 * The credits SDK's hold() inserts a ledger row without re-reading the
 * balance, so every spend path is "read balance, then hold" in two steps. Two
 * requests from the same member at the same moment (two tabs, two listings, a
 * webhook top-up racing an auto-resume) both passed the balance check and both
 * charged, driving the balance negative. Reproduced on plan activation, need
 * responses and Featured upgrades.
 *
 * run() holds a MySQL named lock for the user while the callback checks the
 * balance, places its hold and commits it. The hold row is committed before
 * the lock is released, so the next request's balance read already includes
 * it. Different users never wait on each other.
 *
 * Calls nest: auto-resume activates several listings in one request, each
 * activation taking the lock again. Only the outermost call takes and releases
 * the database lock, so a nested call never deadlocks or releases early.
 *
 * Access through wb_listora_with_credits_lock(); Pro calls that function, not
 * this class.
 *
 * @since 1.9.0
 */
final class Credit_Lock {

	/**
	 * Seconds to wait for another spend by the same user to finish.
	 */
	const WAIT_SECONDS = 5;

	/**
	 * Nesting depth per lock name in this request.
	 *
	 * @var array<string, int>
	 */
	private static $depth = array();

	/**
	 * Whether the outermost call for a lock name actually holds the DB lock.
	 *
	 * @var array<string, bool>
	 */
	private static $held = array();

	/**
	 * Run a callback while holding the user's credit lock.
	 *
	 * GET_LOCK returns 0 on timeout (another spend is still running) and NULL
	 * when the server cannot take named locks. Only the timeout is refused, so
	 * a database without named locks keeps working exactly as before instead
	 * of blocking every paid action.
	 *
	 * @param int      $user_id  User whose credits the callback spends.
	 * @param callable $callback Work to run; its return value is passed through.
	 * @return mixed|\WP_Error The callback's return value, or a `listora_credits_busy`
	 *                         WP_Error (status 409) when the lock timed out.
	 */
	public static function run( $user_id, callable $callback ) {
		$name = 'wb_listora_credits_' . (int) $user_id;

		if ( empty( self::$depth[ $name ] ) ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, %d )', $name, self::WAIT_SECONDS ) );

			if ( '0' === (string) $locked ) {
				return new \WP_Error(
					'listora_credits_busy',
					__( 'Another payment on your account is still being processed. Please try again in a moment.', 'wb-listora' ),
					array( 'status' => 409 )
				);
			}

			self::$depth[ $name ] = 0;
			self::$held[ $name ]  = ( '1' === (string) $locked );
		}

		++self::$depth[ $name ];

		try {
			return $callback();
		} finally {
			--self::$depth[ $name ];

			if ( 0 === self::$depth[ $name ] ) {
				if ( self::$held[ $name ] ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $name ) );
				}
				unset( self::$depth[ $name ], self::$held[ $name ] );
			}
		}
	}
}
