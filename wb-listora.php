<?php
/**
 * Plugin Name: WB Listora
 * Plugin URI:  https://wblistora.com
 * Description: The complete WordPress directory plugin. Create any type of listing directory — business, restaurant, hotel, real estate, jobs, events, and more.
 * Version:     1.0.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author:      WBCom
 * Author URI:  https://wblistora.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wb-listora
 * Domain Path: /languages
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'WB_LISTORA_VERSION', '1.0.0' );
define( 'WB_LISTORA_DB_VERSION', '1.0.0' );
define( 'WB_LISTORA_PLUGIN_FILE', __FILE__ );
define( 'WB_LISTORA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WB_LISTORA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WB_LISTORA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WB_LISTORA_TABLE_PREFIX', 'listora_' );
define( 'WB_LISTORA_META_PREFIX', '_listora_' );
define( 'WB_LISTORA_REST_NAMESPACE', 'listora/v1' );
define( 'WB_LISTORA_INTERACTIVITY_NS', 'listora/directory' );

// Minimum requirements.
define( 'WB_LISTORA_MIN_PHP', '7.4' );
define( 'WB_LISTORA_MIN_WP', '6.9' );

/**
 * Check environment requirements before loading.
 *
 * @return bool
 */
function wb_listora_check_requirements() {
	$errors = array();

	if ( version_compare( PHP_VERSION, WB_LISTORA_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version */
			__( 'WB Listora requires PHP %1$s or higher. You are running PHP %2$s.', 'wb-listora' ),
			WB_LISTORA_MIN_PHP,
			PHP_VERSION
		);
	}

	if ( version_compare( get_bloginfo( 'version' ), WB_LISTORA_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WP version, 2: Current WP version */
			__( 'WB Listora requires WordPress %1$s or higher. You are running %2$s.', 'wb-listora' ),
			WB_LISTORA_MIN_WP,
			get_bloginfo( 'version' )
		);
	}

	if ( ! empty( $errors ) ) {
		add_action(
			'admin_notices',
			function () use ( $errors ) {
				foreach ( $errors as $error ) {
					printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $error ) );
				}
			}
		);
		return false;
	}

	return true;
}

/**
 * Load the Composer autoloader.
 *
 * @return bool
 */
function wb_listora_load_autoloader() {
	$autoloader = WB_LISTORA_PLUGIN_DIR . 'vendor/autoload.php';

	if ( file_exists( $autoloader ) ) {
		require_once $autoloader;
		return true;
	}

	// Fallback: manual class loading via spl_autoload_register.
	spl_autoload_register( 'wb_listora_autoload' );
	return true;
}

/**
 * PSR-4 fallback autoloader for when Composer is not available.
 *
 * Maps WBListora\ namespace to includes/ directory.
 * Converts PascalCase class names to WordPress file naming: Class_Name → class-class-name.php
 *
 * @param string $class_name Fully qualified class name.
 */
function wb_listora_autoload( $class_name ) {
	$namespace = 'WBListora\\';

	if ( 0 !== strpos( $class_name, $namespace ) ) {
		return;
	}

	$relative_class = substr( $class_name, strlen( $namespace ) );
	$parts          = explode( '\\', $relative_class );
	$class_file     = array_pop( $parts );

	// Convert PascalCase to kebab-case: PostTypes → post-types.
	$class_file = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_file ) );
	$class_file = str_replace( '_', '-', $class_file );
	$class_file = 'class-' . $class_file . '.php';

	// Build subdirectory path: Core\PostTypes → core/.
	$subdir = '';
	if ( ! empty( $parts ) ) {
		$subdir = strtolower( implode( '/', $parts ) ) . '/';
	}

	$file = WB_LISTORA_PLUGIN_DIR . 'includes/' . $subdir . $class_file;

	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

/**
 * Check if WB Listora Pro is active.
 *
 * @return bool
 */
function wb_listora_is_pro_active() {
	return defined( 'WB_LISTORA_PRO_VERSION' ) && did_action( 'wb_listora_pro_loaded' );
}

/**
 * Get the URL where users buy credits.
 *
 * Always resolves to the dashboard Credits tab when the dashboard page is
 * configured. Falls back to the legacy `wb_listora_credit_purchase_url`
 * option if set (for themes/devs that want to override with a custom page).
 *
 * The option has no UI anymore — the dashboard Credits tab is the single
 * source of truth — but the option + filter remain for extension points.
 *
 * @return string
 */
function wb_listora_get_credits_purchase_url() {
	// Legacy override: if an admin/theme has explicitly set a custom URL or
	// page ID, respect it.
	$override = get_option( 'wb_listora_credit_purchase_url', '' );
	if ( ! empty( $override ) ) {
		if ( is_numeric( $override ) ) {
			$override = (string) get_permalink( (int) $override );
		} else {
			$override = (string) $override;
		}
		/** This filter is documented in wb-listora.php */
		return (string) apply_filters( 'wb_listora_credits_purchase_url', $override );
	}

	// Auto-resolve: dashboard page with ?tab=credits fragment.
	$dashboard_page_id = (int) wb_listora_get_setting( 'dashboard_page', 0 );
	$url               = '';

	if ( $dashboard_page_id > 0 ) {
		$permalink = get_permalink( $dashboard_page_id );
		if ( $permalink ) {
			$url = add_query_arg( 'tab', 'credits', $permalink ) . '#listora-credit-packs';
		}
	}

	if ( '' === $url ) {
		// Last-ditch: try to find a page at /dashboard/.
		$page = get_page_by_path( 'dashboard' );
		if ( $page instanceof \WP_Post ) {
			$permalink = get_permalink( $page );
			if ( $permalink ) {
				$url = add_query_arg( 'tab', 'credits', $permalink ) . '#listora-credit-packs';
			}
		}
	}

	/**
	 * Filter the "buy credits" URL used across the directory.
	 *
	 * @param string $url Fully-resolved purchase URL.
	 */
	return (string) apply_filters( 'wb_listora_credits_purchase_url', $url );
}

/**
 * Get a plugin setting value.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value if setting not found.
 * @return mixed
 */
function wb_listora_get_setting( $key, $default = null ) {
	static $settings = null;

	if ( null === $settings ) {
		$settings = get_option( 'wb_listora_settings', array() );
	}

	$defaults = wb_listora_get_default_settings();

	if ( null === $default && isset( $defaults[ $key ] ) ) {
		$default = $defaults[ $key ];
	}

	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Get default plugin settings.
 *
 * @return array
 */
function wb_listora_get_default_settings() {
	return array(
		'per_page'            => 20,
		'default_type'        => 'business',
		'default_sort'        => 'featured',
		'listing_slug'        => 'listing',
		'category_slug'       => 'listing-category',
		'location_slug'       => 'listing-location',
		'feature_slug'        => 'listing-feature',
		'tag_slug'            => 'listing-tag',
		'currency'            => 'USD',
		'distance_unit'       => 'km',
		'enable_expiration'   => true,
		'default_expiration'  => 365,
		'enable_claiming'     => true,
		'map_provider'        => 'osm',
		'map_default_lat'     => 40.7128,
		'map_default_lng'     => -74.0060,
		'map_default_zoom'    => 12,
		'map_clustering'      => true,
		'map_search_on_drag'  => true,
		'map_max_markers'     => 500,
		'google_maps_key'     => '',
		'enable_submission'   => true,
		'moderation'          => 'manual',
		'max_upload_size'     => 5,
		'max_gallery_images'  => 20,
		'submission_page'     => 0,
		'dashboard_page'      => 0,
		'enable_schema'       => true,
		'enable_breadcrumbs'  => true,
		'enable_sitemap'      => true,
		'enable_opengraph'    => true,
		'delete_on_uninstall'    => false,
		'search_cache_ttl'       => 15,
		'facet_cache_ttl'        => 30,
		'debug_logging'          => false,
		'setup_complete'         => false,
		'captcha_provider'       => 'none',
		'captcha_site_key'       => '',
		'captcha_secret_key'     => '',
		'enable_guest_submission' => false,
	);
}

// Load autoloader immediately (needed for activation hooks).
wb_listora_load_autoloader();

// ─── Wbcom Credits SDK ───
// Register with the shared credit engine BEFORE including the SDK.
// Pattern: same as WP Career Board, EDD license library.
add_action(
	'wbcom_credits_sdk_registry',
	static function ( \Wbcom\Credits\Registry $registry ): void {
		$registry->register(
			array(
				'slug'      => 'wb-listora',
				// SDK uses this to namespace its ledger table: {wp_prefix}{prefix}_credit_ledger.
				// Use 'listora' (no trailing underscore — SDK adds its own separator).
				'prefix'    => 'listora',
				'version'   => WB_LISTORA_VERSION,
				'file'      => WB_LISTORA_PLUGIN_FILE,
				'user_type' => 'listing_owner',
				'consumers' => array(
					array(
						'id'        => 'listing_submission',
						'label'     => __( 'Listing Submission', 'wb-listora' ),
						'cost'      => static function ( int $item_id ): int {
							// Cost comes from the pricing plan assigned to this submission.
							$plan_id = (int) get_post_meta( $item_id, '_listora_plan_id', true );
							if ( $plan_id <= 0 ) {
								return 0; // Free submission.
							}
							return (int) get_post_meta( $plan_id, '_listora_plan_credit_cost', true );
						},
						// SDK's on_hold expects (int $post_id). Hook fires after the listing is created
						// with $post_id as first arg. Hold is placed when listing enters pending state.
						'hold_on'   => 'wb_listora_after_create_listing',
						// Settle hold when admin approves (post status → publish).
						'deduct_on' => 'wb_listora_after_approve_listing',
						// Release hold when admin rejects or user deletes.
						'refund_on' => 'wb_listora_after_reject_listing',
					),
					array(
						'id'        => 'featured_upgrade',
						'label'     => __( 'Featured Listing', 'wb-listora' ),
						'cost'      => static function (): int {
							return (int) wb_listora_get_setting( 'featured_credit_cost', 0 );
						},
						'hold_on'   => 'wb_listora_before_feature_listing',
						'deduct_on' => 'wb_listora_after_feature_listing',
					),
				),
				'settings'  => array(
					'low_threshold'       => (int) get_option( 'wb_listora_low_credit_threshold', 5 ),
					'purchase_url'        => wb_listora_get_credits_purchase_url(),
					'admin_settings_hook' => 'wb_listora_settings_tab_content',
				),
			)
		);
	}
);

// Include the bundled SDK (multi-version safe — latest wins across all plugins).
if ( file_exists( WB_LISTORA_PLUGIN_DIR . 'vendor/wbcom-credits-sdk/wbcom-credits-sdk.php' ) ) {
	require_once WB_LISTORA_PLUGIN_DIR . 'vendor/wbcom-credits-sdk/wbcom-credits-sdk.php';
}

// Fire approve/reject lifecycle actions based on post status transitions.
// The SDK listens to these hooks to settle/refund credit holds.
add_action(
	'transition_post_status',
	static function ( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || 'listora_listing' !== $post->post_type ) {
			return;
		}
		// Only fire once per transition (not on initial create).
		if ( $new_status === $old_status || 'new' === $old_status || 'auto-draft' === $old_status ) {
			return;
		}
		// Approval: any status → publish.
		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			do_action( 'wb_listora_after_approve_listing', $post->ID );
		}
		// Rejection/trash: → rejected, deactivated, or trash.
		if ( in_array( $new_status, array( 'listora_rejected', 'listora_deactivated', 'trash' ), true ) ) {
			do_action( 'wb_listora_after_reject_listing', $post->ID );
		}
	},
	10,
	3
);

// Bridge: allow themes/plugins to get Listora credit balance via filter.
add_filter(
	'wb_listora_user_credit_balance',
	static function ( int $balance, int $user_id ): int {
		if ( class_exists( '\Wbcom\Credits\Credits' ) ) {
			return \Wbcom\Credits\Credits::get_balance( 'wb-listora', $user_id );
		}
		return $balance;
	},
	10,
	2
);

// Load template helper functions (used by block render.php files).
require_once WB_LISTORA_PLUGIN_DIR . 'includes/class-template-helpers.php';

// Load WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WB_LISTORA_PLUGIN_DIR . 'includes/class-cli-commands.php';
}

// Activation and deactivation hooks (must be registered at file load time).
register_activation_hook( __FILE__, array( 'WBListora\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WBListora\\Deactivator', 'deactivate' ) );

// Boot the plugin on plugins_loaded.
add_action( 'plugins_loaded', 'wb_listora_init', 10 );

/**
 * Initialize the plugin.
 */
function wb_listora_init() {
	if ( ! wb_listora_check_requirements() ) {
		return;
	}

	// Initialize the main plugin instance.
	\WBListora\Plugin::instance();
}
