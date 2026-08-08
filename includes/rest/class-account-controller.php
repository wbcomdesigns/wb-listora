<?php
/**
 * REST Account Controller — self-serve account deactivation and deletion.
 *
 * Ships the two endpoints Apple App Store Guideline 5.1.1(v) requires an app
 * with account creation to offer, and the reactivation path that makes the
 * reversible one genuinely reversible:
 *
 *   POST   /listora/v1/me/deactivate   — reversible. Hides the profile,
 *                                        unpublishes listings, retains all data.
 *   POST   /listora/v1/me/reactivate   — undoes the above, exactly.
 *   DELETE /listora/v1/me              — irreversible. Runs the erasure map to
 *                                        completion, then wp_delete_user().
 *
 * ---------------------------------------------------------------------------
 * SELF-ONLY — structurally, not by check
 * ---------------------------------------------------------------------------
 * Every handler resolves the subject with `get_current_user_id()` and NEVER
 * reads a user ID from the request. There is no `id` parameter to tamper with,
 * so there is no IDOR surface to get wrong: it is impossible to aim these
 * endpoints at another member's account, including as an administrator. That
 * mirrors `Dashboard_Controller::update_profile()`, Free's existing `/me`-shaped
 * write. Admin-initiated deletion already exists in wp-admin and is not this
 * endpoint's job.
 *
 * ---------------------------------------------------------------------------
 * WHY DELETE NEEDS AN EXPLICIT CONFIRMATION
 * ---------------------------------------------------------------------------
 * `DELETE /me` is unrecoverable. A stray link prefetch, a double-submit, a
 * mis-wired client, or a CSRF'd cookie session must not be able to destroy an
 * account, so the caller must also post `confirm=DELETE`. This is defence in
 * depth on top of the REST nonce — the nonce proves the request came from our
 * UI; the confirmation proves the human meant it.
 *
 * @package WBListora\REST
 * @since   1.2.3
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WBListora\Privacy\Account_Manager;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Account lifecycle endpoints for the currently-authenticated member.
 */
class Account_Controller extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = WB_LISTORA_REST_NAMESPACE;

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'me';

	/**
	 * The literal a caller must send to confirm irreversible deletion.
	 */
	const DELETE_CONFIRMATION = 'DELETE';

	/**
	 * Register the account routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// POST /me/deactivate — reversible, retains everything.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/deactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'deactivate_account' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(),
				),
			)
		);

		// POST /me/reactivate — the way back in.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reactivate',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'reactivate_account' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(),
				),
			)
		);

		// DELETE /me — irreversible.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_account' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(
						'confirm' => array(
							'required'          => true,
							'type'              => 'string',
							'description'       => __( 'Must be the literal string "DELETE" to confirm permanent, irreversible account deletion.', 'wb-listora' ),
							'validate_callback' => array( $this, 'validate_delete_confirmation' ),
						),
					),
				),
			)
		);

		/*
		 * Blocking lives under /me because it is a property of the VIEWER, not
		 * of the person being blocked: "who do I not want to see". Putting it on
		 * /members/{id}/block would read as an action against that member and
		 * invite the assumption that it does something to them. It does not —
		 * they keep posting normally.
		 */
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/blocks',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_blocks' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'add_block' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(
						'user_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'minimum'           => 1,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
							'description'       => __( 'The member to block.', 'wb-listora' ),
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/blocks/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_block' ),
					'permission_callback' => array( $this, 'logged_in_permissions' ),
					'args'                => array(
						'user_id' => array(
							'required'          => true,
							'type'              => 'integer',
							'minimum'           => 1,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'absint',
							'description'       => __( 'The member to unblock.', 'wb-listora' ),
						),
					),
				),
			)
		);
	}

	/**
	 * GET /me/blocks — who this member has blocked.
	 *
	 * Returns only the OUTGOING list, deliberately. A member must be able to see
	 * and undo their own decisions; telling them who has blocked THEM would turn
	 * a safety tool into a notification, which is exactly what someone escaping
	 * harassment does not need.
	 *
	 * @return \WP_REST_Response
	 */
	public function list_blocks() {
		$ids = \WBListora\Core\Member_Blocks::blocked_by( get_current_user_id() );

		if ( $ids ) {
			// One primed query for the page rather than one per row.
			cache_users( $ids );
		}

		$items = array();

		foreach ( $ids as $id ) {
			$user = get_user_by( 'id', $id );

			if ( ! $user ) {
				// Deleted since the block was made. Skipped rather than shown as
				// a blank row; the deleted_user hook purges these, so this is
				// only a window between deletion and that running.
				continue;
			}

			$items[] = array(
				'id'           => (int) $user->ID,
				'display_name' => $user->display_name,
				'avatar'       => get_avatar_url( $user->ID, array( 'size' => 96 ) ),
			);
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => count( $items ),
			)
		);
	}

	/**
	 * POST /me/blocks — block a member.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function add_block( $request ) {
		$target = (int) $request->get_param( 'user_id' );
		$result = \WBListora\Core\Member_Blocks::block( get_current_user_id(), $target );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			array(
				'blocked' => true,
				'user_id' => $target,
				// Said plainly so the client can show it without composing its
				// own wording, and so every surface says the same thing.
				'message' => __( 'You will no longer see this member’s reviews, and they cannot contact you.', 'wb-listora' ),
			),
			201
		);
	}

	/**
	 * DELETE /me/blocks/{user_id} — unblock a member.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function remove_block( $request ) {
		$target = (int) $request->get_param( 'user_id' );
		$result = \WBListora\Core\Member_Blocks::unblock( get_current_user_id(), $target );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'blocked' => false,
				'user_id' => $target,
			)
		);
	}

	/**
	 * Require an authenticated member.
	 *
	 * @return bool|\WP_Error
	 */
	public function logged_in_permissions() {
		// Delegate to the one canonical gate (includes/class-template-helpers.php).
		// This body was copy-pasted into 5 controllers; each copy also said 'You do
		// not have permission', which is wrong for a 401 — it is a login problem,
		// not a permission problem. The helper says so correctly, and it is the
		// natural place a ban/suspension gate will later hook (BC 10100523205).
		return wb_listora_require_logged_in();
	}

	/**
	 * Validate the deletion confirmation literal.
	 *
	 * Rejected at the argument layer, so a request without it never reaches the
	 * handler and cannot delete anything.
	 *
	 * @param mixed $value Submitted value.
	 * @return bool|\WP_Error
	 */
	public function validate_delete_confirmation( $value ) {
		if ( self::DELETE_CONFIRMATION !== $value ) {
			return new \WP_Error(
				'listora_confirmation_required',
				sprintf(
					/* translators: %s: the literal confirmation string the caller must send. */
					__( 'Account deletion is permanent and cannot be undone. Send confirm="%s" to proceed.', 'wb-listora' ),
					self::DELETE_CONFIRMATION
				),
				array( 'status' => 400 )
			);
		}

		return true;
	}

	/**
	 * POST /me/deactivate — reversible deactivation.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function deactivate_account( $request ) {
		$rate_check = \WBListora\Rate_Limiter::check( 'account_deactivate' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$result = Account_Manager::deactivate( get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * POST /me/reactivate — restore a deactivated account.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function reactivate_account( $request ) {
		$rate_check = \WBListora\Rate_Limiter::check( 'account_deactivate' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$result = Account_Manager::reactivate( get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * DELETE /me — permanent, irreversible account deletion.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function delete_account( $request ) {
		$rate_check = \WBListora\Rate_Limiter::check( 'account_delete' );
		if ( is_wp_error( $rate_check ) ) {
			return $rate_check;
		}

		$user_id = get_current_user_id();

		/**
		 * Filters whether a member may delete their own account. Return a
		 * WP_Error to abort.
		 *
		 * The escape hatch for sites that must block self-deletion while an
		 * obligation is outstanding — an open dispute, an unsettled balance, a
		 * regulator hold. Aborting here stops the deletion before ANY data is
		 * touched.
		 *
		 * @since 1.2.3
		 *
		 * @param bool|\WP_Error  $allowed True to allow, WP_Error to abort.
		 * @param int             $user_id Member requesting deletion.
		 * @param WP_REST_Request $request The REST request.
		 */
		$allowed = apply_filters( 'wb_listora_before_account_delete', true, $user_id, $request );
		if ( is_wp_error( $allowed ) ) {
			return $allowed;
		}

		$result = Account_Manager::delete( $user_id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// The account is gone; the cookie that authenticated this request now
		// points at nothing. Clear it so the browser isn't left holding an auth
		// cookie for a deleted user.
		wp_destroy_current_session();
		wp_clear_auth_cookie();

		return new WP_REST_Response( $result, 200 );
	}
}
