<?php
/**
 * Member suspension — the owner's tool for stopping an abusive member.
 *
 * WHY THIS EXISTS
 *
 * Before this, an owner had no way to stop a member writing. Removing their
 * capabilities did not work: the REST controllers gate on `is_user_logged_in()`
 * and ownership, not on the `edit_posts`-family caps, so a capability-stripped
 * member kept posting reviews, reports and listings. The admin UI implied a
 * control that did not exist, which is worse than having none.
 *
 * WHY IT IS ONE CHOKE POINT AND NOT 45 PERMISSION CALLBACKS
 *
 * There are ~45 member-facing write endpoints across Free and Pro. Gating each
 * one means the 46th — added next year, by someone who never read this file —
 * is silently ungated. So the block lives in two places that no new endpoint
 * can bypass:
 *
 *   1. `rest_request_before_callbacks` — every write to this plugin's REST
 *      namespace, whatever registers it, including Pro's.
 *   2. `user_has_cap` — the non-REST paths: wp-admin, classic form posts, and
 *      anything a theme calls `current_user_can()` on.
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 *
 * - It does not touch existing content. A suspension stops future writes; it
 *   does not retroactively hide reviews or unpublish listings. Nothing has to
 *   be undone if the suspension is lifted or was a mistake, and a listing's
 *   rating does not silently move because of a moderation action.
 * - It does not block reading. A suspended member can still browse and can
 *   read the explanation, instead of meeting silent failures.
 * - It does not block account deletion. A suspended member must still be able
 *   to erase themselves; blocking that would turn a moderation tool into a
 *   data-protection problem.
 *
 * TWO SEPARATE STATES, ON PURPOSE
 *
 * `_listora_member_suspended` (owner's decision) is distinct from
 * `_listora_account_deactivated` (the member's own choice, via
 * `POST /me/deactivate`). If they shared one flag a suspended member could
 * call `/me/reactivate` and lift their own suspension. Both block writes; only
 * the member's own state can be cleared by the member.
 *
 * @package WBListora\Core
 * @since   1.5.0
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Owner-initiated member suspension, and enforcement of both non-writing states.
 */
class Member_Suspension {

	/**
	 * User meta: the member is suspended by the site owner.
	 */
	const META_SUSPENDED = '_listora_member_suspended';

	/**
	 * User meta: owner-supplied reason, shown back to the member.
	 */
	const META_REASON = '_listora_member_suspended_reason';

	/**
	 * User meta: when the suspension was applied (mysql, GMT).
	 */
	const META_SINCE = '_listora_member_suspended_at';

	/**
	 * User meta: which user applied it, for the audit trail.
	 */
	const META_BY = '_listora_member_suspended_by';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'rest_request_before_callbacks', array( $this, 'block_rest_writes' ), 10, 3 );
		add_filter( 'user_has_cap', array( $this, 'strip_write_caps' ), 10, 4 );
	}

	/**
	 * Whether a member is suspended by the site owner.
	 *
	 * The canonical check — every read site routes through this rather than
	 * reading the meta key, so the storage shape stays private. Mirrors
	 * `wb_listora_is_account_deactivated()`.
	 *
	 * @param int $user_id User to test. Defaults to the current user.
	 * @return bool
	 */
	public static function is_suspended( int $user_id = 0 ): bool {
		$user_id = $user_id ? $user_id : get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		$suspended = (bool) get_user_meta( $user_id, self::META_SUSPENDED, true );

		/**
		 * Filters whether a member counts as suspended.
		 *
		 * Lets a site integrate an external moderation system without writing
		 * to this plugin's meta.
		 *
		 * @since 1.5.0
		 *
		 * @param bool $suspended Whether the member is suspended.
		 * @param int  $user_id   User being tested.
		 */
		return (bool) apply_filters( 'wb_listora_is_member_suspended', $suspended, $user_id );
	}

	/**
	 * Whether this member is barred from writing, for any reason.
	 *
	 * Suspension (owner) OR self-deactivation (member). Both stop writes; they
	 * differ only in who can clear them and what the member is told.
	 *
	 * @param int $user_id User to test. Defaults to the current user.
	 * @return bool
	 */
	public static function is_write_blocked( int $user_id = 0 ): bool {
		$user_id = $user_id ? $user_id : get_current_user_id();

		if ( $user_id <= 0 ) {
			return false;
		}

		if ( self::is_suspended( $user_id ) ) {
			return true;
		}

		return function_exists( 'wb_listora_is_account_deactivated' )
			&& wb_listora_is_account_deactivated( $user_id );
	}

	/**
	 * The reason to show the member, and the machine code for the app.
	 *
	 * @param int $user_id User to describe. Defaults to the current user.
	 * @return array{code:string,message:string,reason:string}|null Null when not blocked.
	 */
	public static function block_details( int $user_id = 0 ): ?array {
		$user_id = $user_id ? $user_id : get_current_user_id();

		if ( self::is_suspended( $user_id ) ) {
			$reason = (string) get_user_meta( $user_id, self::META_REASON, true );

			return array(
				'code'    => 'listora_member_suspended',
				// Says what happened, who can fix it, and what still works.
				// A member who cannot act on a message will contact support,
				// so the message has to point them at the right person.
				'message' => __( 'Your account has been suspended, so you cannot post or edit content on this site. You can still browse. Contact the site administrator if you think this is a mistake.', 'wb-listora' ),
				'reason'  => $reason,
			);
		}

		if ( function_exists( 'wb_listora_is_account_deactivated' ) && wb_listora_is_account_deactivated( $user_id ) ) {
			return array(
				'code'    => 'listora_account_deactivated',
				// This one the member CAN fix, so say so.
				'message' => __( 'Your account is deactivated, so you cannot post or edit content. Reactivate your account from your profile to start posting again.', 'wb-listora' ),
				'reason'  => '',
			);
		}

		return null;
	}

	/**
	 * Suspend a member.
	 *
	 * @param int    $user_id User to suspend.
	 * @param string $reason  Optional owner-supplied reason.
	 * @param int    $by      Who applied it. Defaults to the current user.
	 * @return bool True when the state changed.
	 */
	public static function suspend( int $user_id, string $reason = '', int $by = 0 ): bool {
		if ( $user_id <= 0 || ! get_user_by( 'id', $user_id ) ) {
			return false;
		}

		update_user_meta( $user_id, self::META_SUSPENDED, 1 );
		update_user_meta( $user_id, self::META_REASON, sanitize_textarea_field( $reason ) );
		update_user_meta( $user_id, self::META_SINCE, current_time( 'mysql', true ) );
		update_user_meta( $user_id, self::META_BY, $by ? $by : get_current_user_id() );

		/**
		 * Fires after a member is suspended.
		 *
		 * Pro's audit log listens here; a site can use it to notify the member.
		 *
		 * @since 1.5.0
		 *
		 * @param int    $user_id Suspended member.
		 * @param string $reason  Owner-supplied reason (may be empty).
		 * @param int    $by      User who applied the suspension.
		 */
		do_action( 'wb_listora_member_suspended', $user_id, $reason, $by ? $by : get_current_user_id() );

		return true;
	}

	/**
	 * Lift a suspension.
	 *
	 * @param int $user_id User to reinstate.
	 * @return bool True when the state changed.
	 */
	public static function unsuspend( int $user_id ): bool {
		if ( $user_id <= 0 || ! self::is_suspended( $user_id ) ) {
			return false;
		}

		delete_user_meta( $user_id, self::META_SUSPENDED );
		delete_user_meta( $user_id, self::META_REASON );
		delete_user_meta( $user_id, self::META_SINCE );
		delete_user_meta( $user_id, self::META_BY );

		/**
		 * Fires after a suspension is lifted.
		 *
		 * @since 1.5.0
		 *
		 * @param int $user_id Reinstated member.
		 */
		do_action( 'wb_listora_member_unsuspended', $user_id );

		return true;
	}

	/**
	 * Deny every write to this plugin's REST namespace for a blocked member.
	 *
	 * Runs after the permission callback has passed, so it cannot be skipped by
	 * an endpoint that forgot to check — which is the whole point. Returning a
	 * WP_Error here short-circuits the handler.
	 *
	 * @param mixed                $response Result so far (WP_Error when a permission check failed).
	 * @param array<string,mixed>  $handler  Route handler.
	 * @param \WP_REST_Request $request  The request.
	 * @return mixed
	 */
	public function block_rest_writes( $response, $handler, $request ) {
		// Something already failed — leave that error alone, it is more specific.
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! $request instanceof \WP_REST_Request ) {
			return $response;
		}

		$route = (string) $request->get_route();

		if ( 0 !== strpos( ltrim( $route, '/' ), 'listora/v1' ) ) {
			return $response;
		}

		if ( ! in_array( strtoupper( (string) $request->get_method() ), array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return $response;
		}

		if ( ! self::is_write_blocked() ) {
			return $response;
		}

		if ( self::is_route_allowed_while_blocked( $route, $request ) ) {
			return $response;
		}

		$details = self::block_details();

		if ( null === $details ) {
			return $response;
		}

		return new \WP_Error(
			$details['code'],
			$details['message'],
			array(
				'status' => 403,
				'reason' => $details['reason'],
			)
		);
	}

	/**
	 * Writes a blocked member may still make.
	 *
	 * Deliberately tiny. Account deletion must never be blocked — a suspension
	 * is a moderation decision and must not become a data-protection one. A
	 * self-deactivated member keeps `/me/reactivate` because that is the
	 * control that fixes their own state; a SUSPENDED member does not, or they
	 * would lift their own suspension.
	 *
	 * @param string           $route   Requested route.
	 * @param \WP_REST_Request $request The request.
	 * @return bool
	 */
	private static function is_route_allowed_while_blocked( string $route, \WP_REST_Request $request ): bool {
		$route = '/' . ltrim( $route, '/' );

		// Erasing yourself is always allowed.
		if ( '/listora/v1/me' === $route && 'DELETE' === strtoupper( (string) $request->get_method() ) ) {
			return true;
		}

		// Self-deactivated members may reactivate; suspended members may not.
		if ( '/listora/v1/me/reactivate' === $route && ! self::is_suspended() ) {
			return true;
		}

		/**
		 * Filters the routes a blocked member may still write to.
		 *
		 * @since 1.5.0
		 *
		 * @param bool             $allowed Whether this write is permitted.
		 * @param string           $route   Requested route.
		 * @param \WP_REST_Request $request The request.
		 */
		return (bool) apply_filters( 'wb_listora_blocked_member_route_allowed', false, $route, $request );
	}

	/**
	 * Strip content-writing capabilities from a blocked member.
	 *
	 * Covers everything REST does not: wp-admin, classic form handlers, and any
	 * theme or plugin that asks `current_user_can()` before showing a control.
	 * Without this a suspended member could still be handed an edit form.
	 *
	 * Only content caps are removed. `read` stays, so the member is not locked
	 * out of the site and can see why.
	 *
	 * @param array<string,bool> $allcaps All capabilities for the user.
	 * @param string[]           $caps    Required primitive caps.
	 * @param array<int,mixed>   $args    Args passed to has_cap.
	 * @param \WP_User           $user    The user.
	 * @return array<string,bool>
	 */
	public function strip_write_caps( $allcaps, $caps, $args, $user ) {
		if ( ! $user instanceof \WP_User || $user->ID <= 0 ) {
			return $allcaps;
		}

		if ( ! self::is_write_blocked( (int) $user->ID ) ) {
			return $allcaps;
		}

		/**
		 * Filters the capabilities removed from a blocked member.
		 *
		 * @since 1.5.0
		 *
		 * @param string[] $caps    Capabilities to remove.
		 * @param int      $user_id Blocked member.
		 */
		$blocked = (array) apply_filters(
			'wb_listora_blocked_member_capabilities',
			array(
				'edit_posts',
				'publish_posts',
				'delete_posts',
				'upload_files',
				'edit_published_posts',
				'delete_published_posts',
				'submit_listora_listing',
			),
			(int) $user->ID
		);

		foreach ( $blocked as $cap ) {
			unset( $allcaps[ $cap ] );
		}

		return $allcaps;
	}
}
