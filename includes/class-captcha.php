<?php
/**
 * CAPTCHA verification helper.
 *
 * Supports reCAPTCHA v3 and Cloudflare Turnstile.
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CAPTCHA verification for forms.
 */
class Captcha {

	/**
	 * Get the active CAPTCHA provider.
	 *
	 * @return string 'none', 'recaptcha_v3', or 'cloudflare_turnstile'.
	 */
	public static function get_provider() {
		return wb_listora_get_setting( 'captcha_provider', 'none' );
	}

	/**
	 * Check if CAPTCHA is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$provider = self::get_provider();
		if ( 'none' === $provider ) {
			return false;
		}

		$site_key   = wb_listora_get_setting( 'captcha_site_key', '' );
		$secret_key = wb_listora_get_setting( 'captcha_secret_key', '' );

		return ! empty( $site_key ) && ! empty( $secret_key );
	}

	/**
	 * Enqueue CAPTCHA scripts on the frontend.
	 */
	public static function enqueue_scripts() {
		if ( ! self::is_enabled() ) {
			return;
		}

		$provider = self::get_provider();
		$site_key = wb_listora_get_setting( 'captcha_site_key', '' );

		if ( 'recaptcha_v3' === $provider ) {
			// phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- Google reCAPTCHA must load from Google's CDN.
			wp_enqueue_script(
				'google-recaptcha-v3',
				'https://www.google.com/recaptcha/api.js?render=' . esc_attr( $site_key ),
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External script.
				true
			);
		} elseif ( 'cloudflare_turnstile' === $provider ) {
			wp_enqueue_script(
				'cloudflare-turnstile',
				'https://challenges.cloudflare.com/turnstile/v0/api.js', // phpcs:ignore PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent -- must load from Cloudflare CDN.
				array(),
				null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- External script.
				true
			);
		}
	}

	/**
	 * Render the CAPTCHA widget HTML.
	 *
	 * @param string $form_id Unique form identifier for multiple forms on one page.
	 */
	public static function render_widget( $form_id = 'default' ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$provider = self::get_provider();
		$site_key = wb_listora_get_setting( 'captcha_site_key', '' );

		if ( 'recaptcha_v3' === $provider ) {
			// reCAPTCHA v3 is invisible — just a hidden input for the token.
			echo '<input type="hidden" name="listora_captcha_token" id="listora-captcha-token-' . esc_attr( $form_id ) . '" value="" />';
			echo '<input type="hidden" name="listora_captcha_provider" value="recaptcha_v3" />';
		} elseif ( 'cloudflare_turnstile' === $provider ) {
			echo '<div class="listora-captcha-widget">';
			echo '<div class="cf-turnstile" data-sitekey="' . esc_attr( $site_key ) . '" data-callback="listoraOnTurnstileSuccess" data-theme="auto"></div>';
			echo '<input type="hidden" name="listora_captcha_token" id="listora-captcha-token-' . esc_attr( $form_id ) . '" value="" />';
			echo '<input type="hidden" name="listora_captcha_provider" value="cloudflare_turnstile" />';
			echo '</div>';
		}

		/**
		 * Fires after the CAPTCHA widget, allowing third-party CAPTCHA plugins to add their own.
		 *
		 * @param string $form_id The form identifier.
		 */
		do_action( 'wb_listora_submission_captcha', $form_id );
	}

	/**
	 * Verify a CAPTCHA token from a request.
	 *
	 * @param string $token    The CAPTCHA response token.
	 * @param string $provider The provider ('recaptcha_v3' or 'cloudflare_turnstile').
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function verify( $token, $provider = '' ) {
		if ( ! self::is_enabled() ) {
			return true;
		}

		/**
		 * Allow trusted requests to bypass CAPTCHA. Native apps, scripted
		 * integrations, and WP-CLI flows cannot solve a browser-based
		 * reCAPTCHA challenge, so we skip verification when the request is
		 * authenticated via a mechanism that proves identity another way:
		 *
		 *  - WordPress application passwords (native app / API clients)
		 *  - WP-CLI (no HTTP request context)
		 *  - Cron (not a user-driven request)
		 *
		 * Filter `wb_listora_captcha_bypass` lets third-party plugins extend
		 * this list (e.g. JWT auth, OAuth, signed internal calls).
		 */
		$bypass = ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() )
			|| self::is_application_password_request();

		/**
		 * Filters whether the current request may skip CAPTCHA verification.
		 *
		 * @param bool   $bypass   Whether to skip.
		 * @param string $provider Active provider.
		 */
		if ( apply_filters( 'wb_listora_captcha_bypass', $bypass, $provider ) ) {
			return true;
		}

		if ( empty( $provider ) ) {
			$provider = self::get_provider();
		}

		if ( 'none' === $provider || empty( $token ) ) {
			if ( self::is_enabled() && empty( $token ) ) {
				return new \WP_Error(
					'listora_captcha_missing',
					__( 'CAPTCHA verification is required. Please try again.', 'wb-listora' ),
					array( 'status' => 400 )
				);
			}
			return true;
		}

		$secret_key = wb_listora_get_setting( 'captcha_secret_key', '' );

		if ( empty( $secret_key ) ) {
			return true;
		}

		if ( 'recaptcha_v3' === $provider ) {
			return self::verify_recaptcha_v3( $token, $secret_key );
		} elseif ( 'cloudflare_turnstile' === $provider ) {
			return self::verify_turnstile( $token, $secret_key );
		}

		return true;
	}

	/**
	 * Detect whether the current REST request authenticated via a WordPress
	 * application password. Application passwords are the native auth
	 * mechanism for mobile apps and scripted integrations, so they implicitly
	 * prove the client is not a bot.
	 *
	 * @return bool
	 */
	private static function is_application_password_request(): bool {
		// Fastest path — WordPress sets this global once app-password auth
		// succeeds (see WP_Application_Passwords::authenticate).
		if ( ! empty( $GLOBALS['wp_application_password_used'] ) ) {
			return true;
		}

		// Fallback: inspect the current request header. We can't reuse WP's
		// own parser here because it runs before our verify() call, but we
		// can at least recognise a Basic auth header intended for an app
		// password. Header is read-only — we never echo it.
		$auth_header = isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
		if ( '' !== $auth_header && 0 === stripos( $auth_header, 'basic ' ) ) {
			// Basic auth present + REST context = almost certainly an app-password flow.
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Verify reCAPTCHA v3 token.
	 *
	 * @param string $token      Response token.
	 * @param string $secret_key Secret key.
	 * @return true|\WP_Error
	 */
	private static function verify_recaptcha_v3( $token, $secret_key ) {
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'body' => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => self::get_client_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'listora_captcha_error',
				__( 'Unable to verify CAPTCHA. Please try again.', 'wb-listora' ),
				array( 'status' => 500 )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['success'] ) || ( isset( $body['score'] ) && (float) $body['score'] < 0.5 ) ) {
			return new \WP_Error(
				'listora_captcha_failed',
				__( 'CAPTCHA verification failed. Please try again.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Verify Cloudflare Turnstile token.
	 *
	 * @param string $token      Response token.
	 * @param string $secret_key Secret key.
	 * @return true|\WP_Error
	 */
	private static function verify_turnstile( $token, $secret_key ) {
		$response = wp_remote_post(
			'https://challenges.cloudflare.com/turnstile/v0/siteverify',
			array(
				'body' => array(
					'secret'   => $secret_key,
					'response' => $token,
					'remoteip' => self::get_client_ip(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'listora_captcha_error',
				__( 'Unable to verify CAPTCHA. Please try again.', 'wb-listora' ),
				array( 'status' => 500 )
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['success'] ) ) {
			return new \WP_Error(
				'listora_captcha_failed',
				__( 'CAPTCHA verification failed. Please try again.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Get the client's IP address.
	 *
	 * @return string
	 */
	private static function get_client_ip() {
		$ip = '';

		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}
}
