<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName -- PHPUnit bootstrap, not a class.
/**
 * PHPUnit bootstrap for WB Listora.
 *
 * Loads the WordPress test suite and activates the plugin.
 *
 * @package WBListora\Tests
 */

// Composer autoloader (for PHPUnit itself, if installed locally).
$_composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $_composer_autoload ) ) {
	require_once $_composer_autoload;
}

// Tell the WP test suite where to find yoast/phpunit-polyfills — required
// so that WP's phpunit6/compat.php does not try to stub PHPUnit 10-removed
// classes (Error\Deprecated, Error\Notice, Error\Warning) on its own.
// Must be defined before loading the WP test bootstrap.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	$_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
	if ( ! $_polyfills_path ) {
		$_polyfills_path = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills';
	}
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_polyfills_path );
}

// Determine the WordPress test suite location.
// 1. WP_TESTS_DIR env var  2. WP_DEVELOP_DIR env var  3. common Local-by-Flywheel path.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_develop_dir = getenv( 'WP_DEVELOP_DIR' );
	if ( $_develop_dir ) {
		$_tests_dir = $_develop_dir . '/tests/phpunit';
	}
}

// Fallback: look relative to the WP install (Local / wp-env convention).
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test suite.\n";
	echo "Set WP_TESTS_DIR or WP_DEVELOP_DIR environment variable.\n";
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin for testing.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__ ) . '/wb-listora.php';
	}
);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Simulate plugin activation. WP's test lib loads the plugin file but never
// fires register_activation_hook — so the custom tables (search_index, geo,
// reviews, claims, favorites, services, etc.) and the default listing types
// wouldn't exist in the test DB without this. Everything downstream relies
// on Activator having run at least once.
if ( class_exists( '\\WBListora\\Activator' ) ) {
	\WBListora\Activator::activate();
}

// Capabilities need to be re-added because Activator::add_caps runs against
// the role instance but WP's test setup may have reset roles between the
// plugin load and here.
if ( class_exists( '\\WBListora\\Core\\Capabilities' ) ) {
	( new \WBListora\Core\Capabilities() )->add_caps();
}

// Listing_Type_Registry only registers default types when the
// wb_listora_needs_defaults option is set AND init fires after. Give it a
// nudge so the tests can reference the built-in types (restaurant, hotel,
// etc.) without a fresh activation cycle.
if ( class_exists( '\\WBListora\\Core\\Listing_Type_Registry' ) ) {
	do_action( 'init' );
}

// Load shared test factories.
require_once __DIR__ . '/factories/class-factories.php';
