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
	}

	/**
	 * Require an authenticated member.
	 *
	 * @return bool|\WP_Error
	 */
	public function logged_in_permissions() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'listora_unauthorized',
				__( 'You must be logged in to manage your account.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}

		return true;
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
