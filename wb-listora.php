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
					printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $error ) );
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
