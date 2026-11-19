<?php
/**
 * The one way Listora (and Listora Pro) spends a member's credits.
 *
 * Every priced action (Featured, renewal, need response, plan activation) used to
 * carry its own copy of balance check, hold, work, deduct and cancel, and the copies
 * drifted: one failed open when the credits library was missing, one charged the
 * moderator instead of the listing owner, two answered a short balance with different
 * error codes. This class is the single copy: callers say WHAT to charge and WHAT to do,
 * and never touch the ledger. Ledger, lock and unit rules live in the bundled Credits SDK
 * (`*_money()`, MAJOR units) and `wb_listora_with_credits_lock()`.
 *
 * @package WBListora
 * @since   2.1.1
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Hold, work, deduct, with a cancel on any failure.
 */
final class Charge {

	/**
	 * Charge the listing owner and run the work, or do neither.
	 * @param array<string, mixed> $args {
	 *     @type int      $listing_id        Listing the charge is for. The payer is its author.
	 *     @type float    $amount            Price in MAJOR units (the unit of the settings). 0 or less runs the work free.
	 *     @type string   $note              Ledger note.
	 *     @type callable $work              The state change. Return true or a WP_Error, or throw, to abort.
	 *     @type int      $actor_id          Who is acting. Default: the current user.
	 *     @type int      $payer_id          Who pays. Default: the listing's author. A vendor answering a need pays as themselves.
	 *     @type int      $item_id           Ledger reference for the hold. Default: the listing. Need responses use the need.
	 *     @type string   $insufficient_code Error code for a short balance. Default `insufficient_credits`.
	 *     @type string   $failure_message   Message when the work throws. Default: the exception message.
	 * }
	 * @return array{charged: float, hold_id: int}|\WP_Error What was actually charged (0 when free), or the reason nothing happened.
	 */
	public static function run( array $args ) {
		$listing_id = (int) ( $args['listing_id'] ?? 0 );
		$actor_id   = (int) ( $args['actor_id'] ?? get_current_user_id() );
		$amount     = (float) ( $args['amount'] ?? 0 );
		$payer_id   = (int) ( $args['payer_id'] ?? 0 ) ?: (int) get_post_field( 'post_author', $listing_id );
		$payer_id   = $payer_id ? $payer_id : $actor_id;

		// A moderator acting on someone else's listing is an editorial decision, not a
		// purchase: nobody pays. The owner pays only for what the owner asks for.
		if ( $amount > 0 && $actor_id !== $payer_id && wb_listora_user_can_moderate_listings( $actor_id )
			/**
			 * Whether a staff action on a member's listing is free. Return false to bill the listing owner.
			 *
			 * @since 2.1.1
			 *
			 * @param bool  $free       Default true.
			 * @param int   $listing_id Listing ID.
			 * @param int   $actor_id   The acting staff member.
			 * @param float $amount     Price that would have been charged.
			 */
			&& apply_filters( 'wb_listora_staff_action_is_free', true, $listing_id, $actor_id, $amount ) ) {
			$amount = 0.0;
		}

		return wb_listora_with_credits_lock(
			$payer_id,
			function () use ( $args, $listing_id, $payer_id, $amount ) {
				return self::locked( $args, $listing_id, $payer_id, $amount );
			}
		);
	}

	/**
	 * The charge itself, running inside the payer's credit lock.
	 *
	 * @param array<string, mixed> $args       See run().
	 * @param int       $listing_id Listing ID.
	 * @param int       $payer_id   User charged.
	 * @param float|int $amount     Price after the staff rule.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function locked( array $args, int $listing_id, int $payer_id, $amount ) {
		$work = $args['work'];

		if ( $amount <= 0 ) {
			try {
				$done = $work();
			} catch ( \Throwable $e ) {
				$done = new \WP_Error( 'listora_charge_failed', (string) ( $args['failure_message'] ?? $e->getMessage() ), array( 'status' => 500 ) );
			}
			return is_wp_error( $done ) ? $done : array(
				'charged' => 0.0,
				'hold_id' => 0,
			);
		}

		// A priced action never runs unpaid because the credits library is missing or old.
		if ( ! wb_listora_credits_ready() ) {
			return new \WP_Error(
				'listora_credits_unavailable',
				__( 'This needs the credit system, which is not active.', 'wb-listora' ),
				array( 'status' => 503 )
			);
		}

		// Decide in integer ledger units: floats make 0.1 + 0.2 fall short of 0.30, and a price
		// below the currency's smallest unit (0.4 of a won) must not run the work for nothing.
		$balance = wb_listora_credit_balance( $payer_id );
		$minor   = wb_listora_credit_to_minor( (float) $amount );
		if ( $minor <= 0 ) {
			return self::locked( $args, $listing_id, $payer_id, 0 );
		}
		if ( wb_listora_credit_to_minor( $balance ) < $minor ) {
			return new \WP_Error(
				(string) ( $args['insufficient_code'] ?? 'insufficient_credits' ),
				sprintf(
					/* translators: 1: credits required, 2: credits available */
					__( 'You need %1$s credits for this (you have %2$s).', 'wb-listora' ),
					wb_listora_format_credits( $amount ),
					wb_listora_format_credits( $balance )
				),
				array(
					'status'       => 402,
					'required'     => $amount,
					'balance'      => $balance,
					'purchase_url' => (string) \Wbcom\Credits\Credits::get_purchase_url( WB_LISTORA_CREDITS_SLUG ),
				)
			);
		}

		$note    = (string) ( $args['note'] ?? '' );
		$item_id = (int) ( $args['item_id'] ?? 0 ) ?: $listing_id;
		$hold_id = \Wbcom\Credits\Credits::hold_money( WB_LISTORA_CREDITS_SLUG, $payer_id, $amount, $item_id, '', $note );
		if ( false === $hold_id ) {
			return new \WP_Error(
				'listora_hold_failed',
				__( 'Could not reserve credits. Please try again.', 'wb-listora' ),
				array( 'status' => 500 )
			);
		}

		try {
			$done = $work();
			// Settle THIS hold by id: deduct_money() settles the newest open hold on the listing,
			// which is another feature's when one lands while the work runs.
			if ( ! is_wp_error( $done ) && false === \Wbcom\Credits\Credits::settle_hold( WB_LISTORA_CREDITS_SLUG, $payer_id, (int) $hold_id, 0, $note ) ) {
				$done = new \WP_Error( 'listora_charge_failed', __( 'The charge could not be completed.', 'wb-listora' ), array( 'status' => 500 ) );
			}
		} catch ( \Throwable $e ) {
			$done = new \WP_Error(
				'listora_charge_failed',
				(string) ( $args['failure_message'] ?? $e->getMessage() ),
				array( 'status' => 500 )
			);
		}

		if ( is_wp_error( $done ) ) {
			// This attempt's hold only, by id. The broad cancel by listing also deleted the
			// listing's earlier committed holds, silently refunding them.
			\Wbcom\Credits\Credits::cancel_hold_by_id( WB_LISTORA_CREDITS_SLUG, $payer_id, (int) $hold_id );
			return $done;
		}

		return array(
			'charged' => (float) $amount,
			'hold_id' => (int) $hold_id,
		);
	}

	/**
	 * Announce a submitted listing, unless it is held for credits.
	 *
	 * Fires `wb_listora_listing_submitted`, the event that sends the "New listing submitted" email, assigns a
	 * moderator, writes the audit entry and posts the activity. A listing parked in `listora_payment` has not
	 * been paid for, so it is not announced; Pro calls this again when the hold lifts. Every submit path
	 * (create, submit a saved draft, email verification, mark verified, resume) ends here.
	 *
	 * @since 2.1.2
	 *
	 * @param int                   $post_id Listing ID.
	 * @param \WP_REST_Request|null $request Request for listeners (an empty one when null).
	 * @return bool True when the event fired.
	 */
	public static function announce_submitted( int $post_id, $request = null ): bool {
		$status = get_post_status( $post_id );
		if ( false === $status || 'listora_payment' === $status ) {
			return false;
		}

		/**
		 * Fires after a listing is submitted and is not held for credits.
		 *
		 * @param int             $post_id Post ID.
		 * @param string|false    $status  Post status.
		 * @param \WP_REST_Request $request Request.
		 * @param array           $context Empty for a member's own submission;
		 *                                 migrators pass 'source' => 'migration'.
		 */
		do_action( 'wb_listora_listing_submitted', $post_id, $status, $request instanceof \WP_REST_Request ? $request : new \WP_REST_Request(), array() );
		return true;
	}

	/**
	 * Hold a listing's submission cost, under the author's credit lock.
	 *
	 * Fires `wb_listora_listing_submission_charge`, which the credits SDK's listing_submission
	 * consumer holds on, after checking the balance in the same lock so two submissions at once
	 * cannot both pass. A listing that is already live has no approval step left, so its hold is
	 * settled at once. This is the one copy: the submit form, the edit form and email
	 * verification all pay through it.
	 *
	 * @since 2.1.1
	 *
	 * @param int $post_id Listing ID.
	 * @return bool False when the balance does not cover the cost.
	 */
	public static function submission( int $post_id ): bool {
		$user_id = (int) get_post_field( 'post_author', $post_id );

		$result = wb_listora_with_credits_lock(
			$user_id,
			static function () use ( $post_id, $user_id ) {
				$cost = wb_listora_listing_submission_cost( $post_id );
				if ( $cost <= 0 ) {
					return true;
				}
				if ( ! wb_listora_credits_ready() || wb_listora_credit_to_minor( wb_listora_credit_balance( $user_id ) ) < wb_listora_credit_to_minor( (float) $cost ) ) {
					return false;
				}

				/**
				 * Fires when a listing's submission cost is due (it is going to
				 * review or live). The credits SDK holds the cost on it.
				 *
				 * @since 1.9.0
				 *
				 * @param int $post_id Listing ID.
				 */
				do_action( 'wb_listora_listing_submission_charge', $post_id );

				if ( 'publish' === get_post_status( $post_id ) ) {
					/** This action is documented in wb-listora.php */
					do_action( 'wb_listora_after_approve_listing', $post_id );
				}
				return true;
			}
		);

		return true === $result;
	}
}
