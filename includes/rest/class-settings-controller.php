<?php
/**
 * REST Settings Controller.
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

/**
 * Provides settings endpoints for admin and public map config.
 */
class Settings_Controller extends WP_REST_Controller {

	protected $namespace = WB_LISTORA_REST_NAMESPACE;
	protected $rest_base = 'settings';

	/**
	 * Version of the /settings/app-config payload contract.
	 *
	 * Bump when the payload changes shape in a way a shipped client could not
	 * tolerate (a key removed or re-typed). Additive keys do NOT need a bump —
	 * clients ignore what they don't know. Native apps read this to decide
	 * whether they understand the response.
	 *
	 * @since 1.2.3
	 * @var int
	 */
	const APP_CONTRACT_VERSION = 1;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// GET /settings — all settings (admin only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_all_settings' ),
					'permission_callback' => array( $this, 'manage_settings_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'manage_settings_permissions' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'reset_settings' ),
					'permission_callback' => array( $this, 'manage_settings_permissions' ),
				),
			)
		);

		// GET /settings/maps — public map config (provider, API key for frontend).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/maps',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_map_settings' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /settings/app-config — public bootstrap payload for native apps.
		// Returns only non-sensitive keys an app needs at startup so it doesn't
		// hardcode per_page, currency, or mode.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/app-config',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_app_config' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// GET /settings/export — download settings as JSON.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'export_settings' ),
					'permission_callback' => array( $this, 'manage_settings_permissions' ),
				),
			)
		);

		// POST /settings/import — upload JSON to replace settings.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'import_settings' ),
					'permission_callback' => array( $this, 'manage_settings_permissions' ),
				),
			)
		);

		// POST /settings/notifications/test — admin-only test send.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/notifications/test',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'send_test_notification' ),
					'permission_callback' => array( $this, 'manage_settings_permissions' ),
					'args'                => array(
						'event_key'       => array(
							'type'     => 'string',
							'required' => true,
						),
						'recipient_email' => array(
							'type'     => 'string',
							'required' => false,
						),
					),
				),
			)
		);

		// GET /settings/notifications/log — paginated send activity.
		// DELETE /settings/notifications/log — clear the log.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/notifications/log',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_notification_log' ),
					'permission_callback' => array( $this, 'manage_settings_permissions' ),
					'args'                => array(
						'page'     => array(
							'type'              => 'integer',
							'default'           => 1,
							'sanitize_callback' => 'absint',
						),
						'per_page' => array(
							'type'              => 'integer',
							'default'           => 25,
							'sanitize_callback' => 'absint',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_notification_log' ),
					'permission_callback' => array( $this, 'manage_settings_permissions' ),
				),
			)
		);

		// GET /settings/notifications/log/export — full log as CSV download.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/notifications/log/export',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'export_notification_log' ),
				'permission_callback' => array( $this, 'manage_settings_permissions' ),
			)
		);

		// POST /settings/notifications/log/retention — update retention policy.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/notifications/log/retention',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'set_notification_retention' ),
				'permission_callback' => array( $this, 'manage_settings_permissions' ),
				'args'                => array(
					'days' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission check: manage_listora_settings.
	 *
	 * Returns WP_Error (not bare false) so the JSON error envelope matches
	 * other Listora REST controllers — `listora_unauthorized` 401 for anon,
	 * `listora_forbidden` 403 for logged-in users without the cap. Same shape
	 * as Import_Export_Controller::manage_options_permissions. Headless / mobile
	 * clients reading error.code branch on a single enum across the API.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return true|WP_Error
	 */
	public function manage_settings_permissions( $request ) {
		unset( $request );
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'listora_unauthorized',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 401 )
			);
		}
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return new WP_Error(
				'listora_forbidden',
				__( 'You do not have permission to perform this action.', 'wb-listora' ),
				array( 'status' => 403 )
			);
		}
		return true;
	}

	/**
	 * Send a test notification (admin only).
	 *
	 * Bypasses admin/user gates so admins can verify wiring even when an event
	 * is globally disabled. Result is captured from the rolling email log.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function send_test_notification( WP_REST_Request $request ): WP_REST_Response {
		$event_key = sanitize_key( (string) $request->get_param( 'event_key' ) );
		$recipient = (string) $request->get_param( 'recipient_email' );

		if ( '' === $recipient ) {
			$current   = wp_get_current_user();
			$recipient = $current && $current->ID ? $current->user_email : (string) get_option( 'admin_email' );
		}

		$recipient = sanitize_email( $recipient );

		$notifications = new \WBListora\Workflow\Notifications();
		$result        = $notifications->send_test( $event_key, $recipient );

		return new WP_REST_Response( $result, $result['sent'] ? 200 : 400 );
	}

	/**
	 * Read the rolling email log (admin only).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function get_notification_log( WP_REST_Request $request ): WP_REST_Response {
		$page     = (int) $request->get_param( 'page' );
		$per_page = (int) $request->get_param( 'per_page' );

		$page_data = \WBListora\Workflow\Notifications::get_log_paginated(
			array(
				'page'     => $page > 0 ? $page : 1,
				'per_page' => $per_page > 0 ? $per_page : 25,
			)
		);

		return new WP_REST_Response(
			array(
				'enabled'        => (bool) apply_filters( 'wb_listora_notification_log_enabled', true ),
				'retention_days' => \WBListora\Workflow\Notifications::get_retention_days(),
				'entries'        => $page_data['entries'],
				'total'          => $page_data['total'],
				'page'           => $page_data['page'],
				'per_page'       => $page_data['per_page'],
				'pages'          => $page_data['pages'],
			),
			200
		);
	}

	/**
	 * Clear the rolling email log (admin only).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function clear_notification_log( WP_REST_Request $request ): WP_REST_Response {
		\WBListora\Workflow\Notifications::clear_log();
		return new WP_REST_Response( array( 'cleared' => true ), 200 );
	}

	/**
	 * Stream the rolling email log as a CSV download.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|void Empty 204 on success — body is the CSV stream.
	 */
	public function export_notification_log( WP_REST_Request $request ) {
		unset( $request );

		$entries = \WBListora\Workflow\Notifications::get_log();

		$timestamp = gmdate( 'Y-m-d-Hi' );
		$filename  = sprintf( 'listora-email-log-%s.csv', $timestamp );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( sprintf( 'Content-Disposition: attachment; filename="%s"', $filename ) );

		$output = fopen( 'php://output', 'w' );
		if ( $output ) {
			fputcsv( $output, array( 'sent_at_utc', 'event_key', 'recipient', 'subject', 'success', 'error' ) );
			foreach ( $entries as $row ) {
				fputcsv(
					$output,
					array(
						(string) ( $row['sent_at'] ?? '' ),
						(string) ( $row['event_key'] ?? '' ),
						(string) ( $row['recipient'] ?? '' ),
						(string) ( $row['subject'] ?? '' ),
						! empty( $row['success'] ) ? 'success' : 'failed',
						(string) ( $row['error'] ?? '' ),
					)
				);
			}
			// php://output is a streaming I/O wrapper, not a filesystem path — WP_Filesystem does not apply.
			fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		exit;
	}

	/**
	 * Update the email-log retention policy (admin only).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function set_notification_retention( WP_REST_Request $request ): WP_REST_Response {
		$days    = (int) $request->get_param( 'days' );
		$choices = \WBListora\Workflow\Notifications::retention_choices();

		if ( ! array_key_exists( $days, $choices ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 'invalid_retention_days',
					'message' => __( 'Retention must be one of: 7, 15, 30, 0 (lifetime).', 'wb-listora' ),
				),
				400
			);
		}

		update_option( \WBListora\Workflow\Notifications::RETENTION_OPTION_KEY, $days, false );

		// Trigger an immediate prune so the new policy applies right away.
		$dropped = \WBListora\Workflow\Notifications::prune_log();

		return new WP_REST_Response(
			array(
				'retention_days' => $days,
				'entries_pruned' => $dropped,
			),
			200
		);
	}

	/**
	 * Get all settings (admin).
	 */
	public function get_all_settings( $request ) {
		$settings = get_option( 'wb_listora_settings', array() );
		$defaults = wb_listora_get_default_settings();

		return new WP_REST_Response( wp_parse_args( $settings, $defaults ), 200 );
	}

	/**
	 * Update settings (admin).
	 */
	public function update_settings( $request ) {
		$params = $request->get_params();

		// Route through the ONE canonical sanitizer that the wp-admin options.php
		// path uses. This controller previously ran its own type-switch that only
		// handled bool/int/float and fell through to sanitize_text_field((string)
		// $value) for everything else — so array-typed settings
		// (listing_limits_per_role, reviews, notifications) were stored as the
		// literal 'Array', and a blank listing_limits_default int-cast to 0 (which
		// blocks every non-admin submission). Settings_Page::sanitize() has the
		// array-typed handlers, the Listing_Limits guards, and the legal-field URL/
		// email sanitizers, and it starts from the stored option so unsubmitted keys
		// are preserved (PATCH semantics). One sanitizer for both write paths
		// (AUDIT-M — headless/SPA corruption).
		$sanitized = \WBListora\Admin\Settings_Page::sanitize( $params );

		// autoload=false: this option holds ~40 keys including secrets and is
		// only read on admin/REST requests, never on every front-end page load.
		update_option( 'wb_listora_settings', $sanitized, false );

		// Flush rewrite rules if slugs changed.
		if ( isset( $params['listing_slug'] ) || isset( $params['category_slug'] ) ) {
			flush_rewrite_rules();
		}

		return new WP_REST_Response( array( 'updated' => true ), 200 );
	}

	/**
	 * Get public map settings (no auth required).
	 */
	public function get_map_settings( $request ) {
		$provider = (string) wb_listora_get_setting( 'map_provider', 'osm' );
		$tiles    = function_exists( 'wb_listora_get_map_tiles' )
			? wb_listora_get_map_tiles( $provider )
			: array(
				'url'         => '',
				'attribution' => '',
			);

		return new WP_REST_Response(
			array(
				'provider'         => $provider,
				'default_lat'      => (float) wb_listora_get_setting( 'map_default_lat', 40.7128 ),
				'default_lng'      => (float) wb_listora_get_setting( 'map_default_lng', -74.0060 ),
				'default_zoom'     => (int) wb_listora_get_setting( 'map_default_zoom', 12 ),
				'clustering'       => (bool) wb_listora_get_setting( 'map_clustering', true ),
				// Native clients must not hardcode a tile source (OSM's usage
				// policy forbids it), so the server names it. Empty for `google`,
				// where the client uses the Google SDK instead of a raster layer.
				'tile_url'         => (string) $tiles['url'],
				'tile_attribution' => (string) $tiles['attribution'],
			),
			200
		);
	}

	/**
	 * Public bootstrap payload for native apps and third-party clients.
	 *
	 * Returns only non-sensitive keys an app needs at startup — defaults for
	 * pagination, distance units, currency, moderation mode, feature toggles.
	 * Never leaks API keys, secrets, or admin-only settings.
	 *
	 * @param WP_REST_Request $request Full request data.
	 * @return WP_REST_Response
	 */
	public function get_app_config( WP_REST_Request $request ): WP_REST_Response {
		// Published as an ordered list of { slug, label } rather than a map, so
		// clients keep the site's own ordering and can render a filter control
		// straight from it.
		$listing_statuses = array();
		if ( class_exists( '\\WBListora\\Workflow\\Status_Manager' ) ) {
			foreach ( \WBListora\Workflow\Status_Manager::get_statuses() as $status_slug => $status_label ) {
				$listing_statuses[] = array(
					'slug'  => (string) $status_slug,
					'label' => (string) $status_label,
				);
			}
		}

		$currency_format = function_exists( 'wb_listora_get_currency_format' )
			? wb_listora_get_currency_format()
			: array(
				'code'     => (string) wb_listora_get_setting( 'currency', 'USD' ),
				'symbol'   => '',
				'position' => 'before',
				'decimals' => 2,
			);

		$data = array(
			'contract_version'        => self::APP_CONTRACT_VERSION,
			'plugin_version'          => defined( 'WB_LISTORA_VERSION' ) ? WB_LISTORA_VERSION : '',
			'rest_namespace'          => WB_LISTORA_REST_NAMESPACE,
			'directory_url'           => function_exists( 'wb_listora_get_directory_url' ) ? wb_listora_get_directory_url() : home_url( '/' ),
			'submit_url'              => function_exists( 'wb_listora_get_submit_url' ) ? wb_listora_get_submit_url() : '',
			'dashboard_url'           => function_exists( 'wb_listora_get_dashboard_url' ) ? wb_listora_get_dashboard_url() : '',
			'per_page'                => (int) wb_listora_get_setting( 'per_page', 20 ),
			'distance_unit'           => (string) wb_listora_get_setting( 'distance_unit', 'km' ),
			'currency'                => (string) $currency_format['code'],
			// Native clients cannot derive these from the ISO code alone —
			// without them `Intl.NumberFormat` renders "US$35.00" instead of the
			// site's "$35.00", and any custom symbol / suffix position /
			// zero-decimal currency is silently ignored. Same source as
			// wb_listora_format_currency(), so app and web agree by construction.
			'currency_symbol'         => (string) $currency_format['symbol'],
			'currency_position'       => (string) $currency_format['position'],
			'decimals'                => (int) $currency_format['decimals'],
			// Listing statuses and their labels, from the same canonical map
			// that drives register_post_status(). Clients previously carried
			// their own copy and it drifted: the app shipped `expired` where the
			// real slug is `listora_expired`, and rendered `listora_payment` as
			// "Payment" where the site says "Awaiting Credits" — a word instead
			// of an instruction for someone whose listing is parked for credits.
			'listing_statuses'        => $listing_statuses,
			'default_country'         => (string) wb_listora_get_setting( 'default_country', '' ),
			'moderation'              => (string) wb_listora_get_setting( 'moderation', 'manual' ),
			'enable_claiming'         => (bool) wb_listora_feature_enabled( 'claims' ),
			// `enable_guest_submission` was removed from the app-config contract:
			// guest listing submission no longer exists (submitting requires an
			// account), so advertising the flag misled native clients into
			// rendering a guest path that always 401s. Clients must treat
			// submission as login-required.
			'enable_reviews'          => (bool) wb_listora_feature_enabled( 'reviews' ),
			'enable_favorites'        => (bool) wb_listora_feature_enabled( 'favorites' ),
			'enable_captcha'          => class_exists( '\\WBListora\\Captcha' ) && \WBListora\Captcha::is_enabled(),
			'captcha_provider'        => (string) wb_listora_get_setting( 'captcha_provider', 'none' ),
			'is_pro_active'           => function_exists( 'wb_listora_is_pro_active' ) && wb_listora_is_pro_active(),

			/*
			 * The contact endpoint this site actually serves, as a path
			 * template the client fills in.
			 *
			 * There are two routes and only one renders on any given site:
			 * Pro's `/listings/{id}/contact` when the `lead_form` feature is
			 * on, and Free's `/listings/{id}/contact-form` otherwise — Free's
			 * `Contact_Form::should_render()` suppresses itself when Pro's is
			 * active. A native client cannot see which one the web rendered,
			 * so it inferred the route from the `lead_form` feature flag, and
			 * a wrong inference is a 404 or a silently dead button.
			 *
			 * A full path rather than a route NAME, deliberately: the client
			 * substitutes `{id}` and posts, so a future third door changes one
			 * server-side string instead of silently breaking every client at
			 * once. That is the ask on BC 10202831497.
			 *
			 * Free declares its own route; Pro overrides through the
			 * `wb_listora_app_config` filter when `lead_form` is enabled.
			 *
			 * Both routes enforce member blocking, so this says which endpoint
			 * EXISTS — never which one is safe.
			 */
			'contact_path'            => '/listings/{id}/contact-form',

			/*
			 * Pro-only gate for the native app.
			 *
			 * The mobile app is a Pro benefit, so Free always declares false.
			 * WB Listora Pro flips this true via the `wb_listora_app_config`
			 * filter, and ONLY when the site holds a valid Pro license. When
			 * false the app shows a "requires WB Listora Pro" screen and
			 * refuses to sign in, so it never runs against a free-only or
			 * unlicensed install. Fail closed.
			 *
			 * This gates the CLIENT, not the DATA. It is a public boolean and
			 * therefore trivially spoofable — it is a licensing/product gate,
			 * never an authorization gate. Per-user authorization stays in the
			 * REST permission callbacks.
			 */
			'app_enabled'             => false,

			/*
			 * Minimum app build the site will serve. Lets us retire a broken
			 * client: below this the app shows a force-upgrade screen. Free
			 * ships a permissive floor; site owners raise it via the filter.
			 */
			'min_app_version'         => '0.0.0',

			/*
			 * Branding for the app shell. Free has no branding settings of its
			 * own, so it declares the shape and leaves it empty — Pro's white
			 * label feature fills it through the filter. Free never reads Pro's
			 * options (INV-1: no runtime dependency on Pro).
			 *
			 * Every field is optional. Clients MUST provide their own fallback.
			 */
			'branding'                => array(
				'accent_color' => '',
				'logo_url'     => '',
				'login_bg_url' => '',
			),

			/*
			 * Legal surface. Apple requires reachable privacy/terms links in
			 * the app itself. `privacy_policy_url` reuses core's page when the
			 * site has one set; the rest are filterable.
			 */
			'legal'                   => array(
				'privacy_policy_url'        => (string) get_privacy_policy_url(),
				'terms_url'                 => (string) esc_url_raw( wb_listora_get_terms_url() ),
				'community_guidelines_url'  => (string) esc_url_raw( (string) wb_listora_get_setting( 'legal_community_guidelines_url', '' ) ),
				'abuse_contact_email'       => (string) sanitize_email( (string) wb_listora_get_setting( 'legal_abuse_contact_email', '' ) ),
			),

			/*
			 * Canonical feature registry — every free toggle, under its real
			 * key. The `enable_*` keys above are the pre-1.2.3 spelling and are
			 * kept for back-compat; new clients read `features`.
			 *
			 * Pro merges its own 20 toggles in via the filter. A client must
			 * gate on these: a free toggle that is off returns 403, and a Pro
			 * toggle that is off UNREGISTERS its routes entirely (404), so an
			 * ungated screen renders controls that cannot work.
			 */
			'features'                => $this->free_feature_flags(),

			/*
			 * How this SITE signs a member into the app — the Wbcom App Auth
			 * standard block, so ONE reader in the app serves every Wbcom
			 * product. On sites where BuddyNext runs alongside Listora,
			 * `connect_url` is BuddyNext's connect bridge (it owns site auth
			 * there); standalone it is empty and the app routes through core's
			 * authorize screen or the credentials exchange below.
			 */
			'auth'                    => \WBListora\Auth\App_Connect::auth_block(),

			/*
			 * May a member sign in by typing their WordPress password
			 * (POST /auth/app-password), or must they go through the
			 * interactive approval flow? Owner switch, default on. The app
			 * needs to know BEFORE it renders the control, so it never offers
			 * a path this site will refuse.
			 */
			'password_login'          => \WBListora\Auth\App_Credentials::is_enabled(),

			'languages'               => array(
				'current' => get_locale(),
				'site'    => get_bloginfo( 'language' ),
			),
			'timezone'                => array(
				'string' => wp_timezone_string(),
				'offset' => (float) get_option( 'gmt_offset' ),
			),
		);

		/**
		 * Filter the public app-config payload. Use this to add plugin-specific
		 * startup data, flip `app_enabled` for a licensed Pro site, merge extra
		 * feature flags, or supply white-label branding.
		 *
		 * NEVER add secrets here — the endpoint is publicly readable. In
		 * particular do not expose license keys, customer emails, or API keys.
		 *
		 * @since 1.2.3 The `$request` argument was added.
		 *
		 * @param array           $data    Config payload.
		 * @param WP_REST_Request $request The config request.
		 */
		$data = (array) apply_filters( 'wb_listora_app_config', $data, $request );

		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Every FREE feature toggle, keyed by its canonical registry key.
	 *
	 * Read from the live registry rather than a hardcoded list so a toggle
	 * added to the registry appears here automatically and cannot drift out of
	 * the contract.
	 *
	 * Only rows Free actually STORES (`store => 'free'`) are resolved here. Pro
	 * registers its own rows onto Free's shared Features screen tagged
	 * `store => 'wb_listora_pro_features'`; those values live in a different
	 * option, so resolving them with Free's reader silently reports the
	 * registry default instead of the real value. Pro merges its own flags
	 * through the `wb_listora_app_config` filter, where it reads its own option.
	 *
	 * @since 1.2.3
	 *
	 * @return array<string,bool>
	 */
	private function free_feature_flags(): array {
		$flags = array();

		if ( ! function_exists( 'wb_listora_features_registry' ) || ! function_exists( 'wb_listora_feature_enabled' ) ) {
			return $flags;
		}

		foreach ( (array) wb_listora_features_registry() as $key => $config ) {
			$store = isset( $config['store'] ) ? (string) $config['store'] : 'free';

			if ( 'free' !== $store ) {
				continue;
			}

			$flags[ (string) $key ] = (bool) wb_listora_feature_enabled( $key );
		}

		return $flags;
	}

	/**
	 * Reset all settings to defaults (DELETE /settings).
	 *
	 * Deletes the stored option so defaults regenerate automatically.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function reset_settings( $request ) {
		// Free's own option is the baseline reset.
		$option_keys = array( 'wb_listora_settings' );

		/**
		 * Filters the list of option keys deleted on Reset to Defaults.
		 *
		 * Pro and 3rd-party extensions register their own option keys here so
		 * tabs whose data lives outside `wb_listora_settings` (white_label,
		 * visibility, notification_mode, etc.) actually reset when the admin
		 * clicks "Reset to Defaults". Without this hook the button used to
		 * be a silent no-op for every tab whose data lived in a sibling
		 * option (Basecamp 9847401498).
		 *
		 * Re-runs activator's set_default_options afterwards, so options
		 * deleted here get re-seeded with the same defaults a fresh install
		 * would have.
		 *
		 * @param string[] $option_keys Default ['wb_listora_settings'].
		 */
		$option_keys = (array) apply_filters( 'wb_listora_reset_option_keys', $option_keys );

		foreach ( array_unique( array_filter( array_map( 'strval', $option_keys ) ) ) as $key ) {
			delete_option( $key );
		}

		/**
		 * Fires after Reset to Defaults wipes Listora options. Activators
		 * (Free + Pro) can re-seed defaults here so sites don't sit with
		 * empty options until the next page load that auto-creates them.
		 */
		do_action( 'wb_listora_after_reset_settings', $option_keys );

		$defaults = wb_listora_get_default_settings();

		return new WP_REST_Response(
			array(
				'reset'    => true,
				'settings' => $defaults,
				'cleared'  => array_values( $option_keys ),
			),
			200
		);
	}

	/**
	 * Export settings as JSON (GET /settings/export).
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function export_settings( $request ) {
		$settings = get_option( 'wb_listora_settings', array() );
		$defaults = wb_listora_get_default_settings();

		$response = new WP_REST_Response(
			array(
				'plugin'   => 'wb-listora',
				'version'  => defined( 'WB_LISTORA_VERSION' ) ? WB_LISTORA_VERSION : '1.0.0',
				'exported' => gmdate( 'Y-m-d\TH:i:s\Z' ),
				'settings' => wp_parse_args( $settings, $defaults ),
			),
			200
		);

		$response->header( 'Content-Disposition', 'attachment; filename=wb-listora-settings.json' );

		return $response;
	}

	/**
	 * Import settings from JSON (POST /settings/import).
	 *
	 * Accepts a JSON body with a "settings" key containing settings to merge.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response
	 */
	public function import_settings( $request ) {
		$body = $request->get_json_params();

		if ( empty( $body['settings'] ) || ! is_array( $body['settings'] ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'Invalid import data. Expected a JSON object with a "settings" key.', 'wb-listora' ) ),
				400
			);
		}

		$imported = $body['settings'];
		$defaults = wb_listora_get_default_settings();
		$clean    = array();

		// Only import keys that exist in defaults (type-safe).
		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $imported ) ) {
				$clean[ $key ] = $default;
				continue;
			}

			$value = $imported[ $key ];

			if ( is_bool( $default ) ) {
				$clean[ $key ] = (bool) $value;
			} elseif ( is_int( $default ) ) {
				$clean[ $key ] = (int) $value;
			} elseif ( is_float( $default ) ) {
				$clean[ $key ] = (float) $value;
			} else {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		// Import nested arrays (notifications, reviews) if present.
		if ( isset( $imported['notifications'] ) && is_array( $imported['notifications'] ) ) {
			$clean['notifications'] = array_map( 'absint', $imported['notifications'] );
		}

		if ( isset( $imported['reviews'] ) && is_array( $imported['reviews'] ) ) {
			$clean['reviews'] = array(
				'auto_approve'    => ! empty( $imported['reviews']['auto_approve'] ),
				'require_login'   => ! empty( $imported['reviews']['require_login'] ),
				'min_length'      => isset( $imported['reviews']['min_length'] ) ? absint( $imported['reviews']['min_length'] ) : 20,
				'one_per_listing' => ! empty( $imported['reviews']['one_per_listing'] ),
				'allow_reply'     => ! empty( $imported['reviews']['allow_reply'] ),
			);
		}

		// autoload=false — see update_settings(); never read on the public front end.
		update_option( 'wb_listora_settings', $clean, false );

		return new WP_REST_Response(
			array(
				'imported' => true,
				'settings' => $clean,
			),
			200
		);
	}
}
