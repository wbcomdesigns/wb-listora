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
 * The credits SDK's hold() does not re-read the balance, so concurrent
 * "read balance, then hold" requests from one member could both pass and
 * overdraw. run() holds a MySQL named lock per user while the callback checks
 * the balance and commits its hold, so the next request sees it. Different
 * users never wait on each other. Calls nest: only the outermost takes and
 * releases the lock. Pro uses wb_listora_with_credits_lock(), not this class.
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
		global $wpdb;

		// Named locks are server-wide, so the table prefix keeps two sites on one
		// MySQL server from sharing a member's lock. Hashed to fit MySQL's 64
		// characters without cutting off the user id.
		$name = 'listora_credits_' . md5( $wpdb->prefix ) . '_' . (int) $user_id;

		if ( empty( self::$depth[ $name ] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- MySQL named locks have no WP API and must not be cached
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

			// A balance read earlier in this request (a pre-check) is cached
			// by the SDK; inside the lock it must be read from the ledger, or
			// a request that waited here spends against the stale figure
			// (reproduced: two submissions, credits for one, both charged).
			if ( is_callable( array( '\\Wbcom\\Credits\\Credits', 'invalidate_cache' ) ) ) {
				\Wbcom\Credits\Credits::invalidate_cache( 'wb-listora', (int) $user_id );
			}
		}

		++self::$depth[ $name ];

		try {
			return $callback();
		} finally {
			--self::$depth[ $name ];

			if ( 0 === self::$depth[ $name ] ) {
				if ( self::$held[ $name ] ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- MySQL named locks have no WP API and must not be cached
					$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $name ) );
				}
				unset( self::$depth[ $name ], self::$held[ $name ] );
			}
		}
	}
}
