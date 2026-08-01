<?php
/**
 * Auth REST controller — how the mobile app gets its first credential.
 *
 * One route: `POST /listora/v1/auth/app-password`, which trades a member's
 * ordinary WordPress login for a WP core Application Password. Every guard
 * lives in `WBListora\Auth\App_Credentials`; this controller owns only the
 * request shape, the throttle that must run BEFORE a credential is read, and
 * the `no-store` response.
 *
 * @package WBListora\REST
 */

namespace WBListora\REST;

defined( 'ABSPATH' ) || exit;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WBListora\Auth\App_Credentials;
use WBListora\Rate_Limiter;

/**
 * Serves the app-password exchange.
 *
 * @since 1.4.0
 */
class Auth_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'auth';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		// POST /auth/app-password — the member's FIRST credential, so there is
		// nothing to authenticate with yet and the route is public by
		// necessity (Rule-2 allowlisted). Core will not do this exchange: its
		// Basic auth accepts application passwords only, so the core route
		// that mints them already requires one, and every core path to a first
		// credential runs through wp-admin.
		//
		// Guarded in App_Credentials: owner switch, TLS gate, uniform
		// failures, and a 409 rather than a silent 2FA bypass. Rate limiting
		// happens in the callback BEFORE any credential is read.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/app-password',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'issue_app_password' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'username' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Your email address or username.', 'wb-listora' ),
						),
						'password' => array(
							'required'    => true,
							'type'        => 'string',
							'description' => __( 'Your account password.', 'wb-listora' ),
						),
						'app_name' => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Name shown beside this credential in your profile.', 'wb-listora' ),
						),
						'app_id'   => array(
							'required'          => false,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'description'       => __( 'Stable per-install id, so a repeat sign-in replaces the row instead of adding another.', 'wb-listora' ),
						),
					),
				),
			)
		);
	}

	/**
	 * POST /auth/app-password — hand back the credential the app will hold.
	 *
	 * The account password is read from the request, passed straight to
	 * `wp_authenticate()`, and never stored, logged or echoed. Only the minted
	 * Application Password comes back, with `no-store` so nothing on the path
	 * keeps a copy.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response|WP_Error
	 */
	public function issue_app_password( WP_REST_Request $request ) {
		// Throttle BEFORE a credential is read. Two failure buckets, because
		// one alone is not enough: the IP bucket stops one host grinding
		// through passwords, the username bucket stops a distributed run at a
		// single account. Only rejected CREDENTIALS count against them.
		$username = (string) $request->get_param( 'username' );
		$ip       = ! empty( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: 'unknown';
		$buckets  = array( 'ip:' . $ip, 'user:' . strtolower( $username ) );

		foreach ( $buckets as $bucket ) {
			if ( App_Credentials::is_locked_out( $bucket ) ) {
				return new WP_Error(
					'wb_listora_too_many_attempts',
					__( 'Too many sign-in attempts. Please wait a few minutes and try again.', 'wb-listora' ),
					array( 'status' => 429 )
				);
			}
		}

		// The plugin's own per-IP cap on top — every attempt counts here,
		// bounding even a slow distributed probe. `app_password` is a real key
		// in Rate_Limiter::DEFAULTS; an UNKNOWN action returns true (unlimited)
		// from config_for(), so the registry entry is what makes this enforce.
		$coarse = Rate_Limiter::check( 'app_password' );
		if ( is_wp_error( $coarse ) ) {
			return $coarse;
		}

		$app_name = (string) $request->get_param( 'app_name' );
		$app_id   = (string) $request->get_param( 'app_id' );

		$result = App_Credentials::exchange(
			$username,
			(string) $request->get_param( 'password' ),
			'' !== $app_name ? $app_name : __( 'Listora app', 'wb-listora' ),
			$app_id
		);

		if ( is_wp_error( $result ) ) {
			if ( 'wb_listora_login_failed' === $result->get_error_code() ) {
				foreach ( $buckets as $bucket ) {
					App_Credentials::record_failure( $bucket );
				}
			}

			return $result;
		}

		foreach ( $buckets as $bucket ) {
			App_Credentials::clear_failures( $bucket );
		}

		$response = new WP_REST_Response( $result, 200 );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
