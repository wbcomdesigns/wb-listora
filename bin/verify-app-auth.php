<?php
/**
 * Verification script for WB Listora's mobile-app authentication surface.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/wb-listora/bin/verify-app-auth.php
 *
 * Proves on the running site that:
 *
 *   1. The app-config publishes the Wbcom App Auth standard `auth` block the
 *      fleet-wide app reader parses, plus the `password_login` switch.
 *   2. The scheme seams work: `listoraapp` in the site's own allowlist, the
 *      `wb_listora_app_connect_schemes` sibling seam, and — on a combined
 *      site — BuddyNext's allowlist accepting `listoraapp` (one door per site).
 *   3. Standalone, `connect_url` is EMPTY by decision: Listora builds no
 *      bridge; core's repaired authorize screen is its interactive door. The
 *      `wb_listora_app_connect_bridge` filter can redirect the door.
 *   4. The kses repair is SCOPED: the app scheme is added on the authorize
 *      screen and nowhere else.
 *   5. `POST /auth/app-password` mints a working Basic credential for a good
 *      password and refuses a wrong one with the uniform 401.
 *   6. Reconnect replaces on EVERY door: two mints with one app_id leave one
 *      row; a hand-made row (no app_id) is never touched.
 *
 * Listora has no ban/suspension concept (verified 2026-08-01: no is_banned /
 * suspended state in the permission layer), so there is no ban-gate case here.
 * If suspension ever ships, this suite MUST grow one — a suspended member must
 * not be able to mint a fresh credential.
 *
 * Exits non-zero if any case fails, so it is CI-friendly.
 *
 * @package WBListora\Tools
 */

defined( 'ABSPATH' ) || exit( 1 );

use WBListora\Auth\App_Authorize_Access;
use WBListora\Auth\App_Connect;

/**
 * Record and print one check result, and report the running tally.
 *
 * Counters are function-static, NOT globals: `wp eval-file` executes this file
 * inside a function scope, so a top-level `$pass` is a local there and a
 * `global $pass` in here would bind to a different, always-zero variable. That
 * mistake makes the suite exit 0 no matter what fails — a check that cannot
 * fail is not a check.
 *
 * @param string|null $id     Check id, or null to read the tally.
 * @param bool        $ok     Whether it passed.
 * @param string      $detail Optional failure detail.
 * @return array{pass:int,fail:int} The running tally.
 */
function listora_auth_check( $id = null, $ok = false, $detail = '' ) {
	static $pass = 0;
	static $fail = 0;

	if ( null === $id ) {
		return array(
			'pass' => $pass,
			'fail' => $fail,
		);
	}

	if ( $ok ) {
		++$pass;
		echo "  PASS  {$id}\n";
	} else {
		++$fail;
		echo "  FAIL  {$id}" . ( $detail ? " — {$detail}" : '' ) . "\n";
	}

	return array(
		'pass' => $pass,
		'fail' => $fail,
	);
}

/**
 * Loopback request against the Listora REST namespace.
 *
 * @param string $method HTTP method.
 * @param string $path   Path under the namespace.
 * @param array  $body   Request body.
 * @param string $basic  Optional "user:pass" for HTTP Basic.
 * @return array [ status, decoded-body ]
 */
function listora_auth_request( $method, $path, $body = array(), $basic = '' ) {
	$base = untrailingslashit( home_url() ) . '/wp-json/' . WB_LISTORA_REST_NAMESPACE;

	$args = array(
		'method'    => $method,
		'timeout'   => 15,
		'sslverify' => false, // Local dev cert.
		'headers'   => array(),
	);

	if ( $body ) {
		$args['body'] = $body;
	}

	if ( '' !== $basic ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic auth encoding.
		$args['headers']['Authorization'] = 'Basic ' . base64_encode( $basic );
	}

	$response = wp_remote_request( $base . $path, $args );

	if ( is_wp_error( $response ) ) {
		return array( 0, array( 'error' => $response->get_error_message() ) );
	}

	$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );

	return array( (int) wp_remote_retrieve_response_code( $response ), is_array( $decoded ) ? $decoded : array() );
}

echo "WB Listora app-auth contract suite\n";

$listora_bn_active = class_exists( '\BuddyNext\App\AppConnectService' );
echo $listora_bn_active
	? "  (topology: BuddyNext ACTIVE — combined)\n"
	: "  (topology: standalone — no BuddyNext)\n";

// ── 1. auth block + password_login on the live app-config ────────────────
list( $listora_status, $listora_config ) = listora_auth_request( 'GET', '/settings/app-config' );
$listora_auth_block                      = isset( $listora_config['auth'] ) ? $listora_config['auth'] : null;

listora_auth_check( 'config.200', 200 === $listora_status, "status {$listora_status}" );
listora_auth_check( 'config.auth-present', is_array( $listora_auth_block ), 'no auth block on app-config' );
listora_auth_check( 'config.password-login-flag', isset( $listora_config['password_login'] ) && true === $listora_config['password_login'] );

if ( is_array( $listora_auth_block ) ) {
	foreach ( array( 'social_providers', 'twofactor', 'register', 'app_passwords_available', 'connect_url', 'connect_schemes' ) as $listora_key ) {
		listora_auth_check( "config.auth.{$listora_key}", array_key_exists( $listora_key, $listora_auth_block ) );
	}
	listora_auth_check( 'config.auth.social-empty', array() === $listora_auth_block['social_providers'] );
	listora_auth_check(
		'config.auth.schemes-carry-app',
		in_array( App_Authorize_Access::app_scheme(), (array) $listora_auth_block['connect_schemes'], true ),
		'listoraapp missing from connect_schemes'
	);
}

// ── 2. Scheme seams ──────────────────────────────────────────────────────
listora_auth_check( 'schemes.own', in_array( 'listoraapp', App_Connect::schemes(), true ) );

$listora_sibling = function ( $schemes ) {
	$schemes[] = 'siblingapp';
	return $schemes;
};
add_filter( 'wb_listora_app_connect_schemes', $listora_sibling );
listora_auth_check( 'schemes.sibling-seam', in_array( 'siblingapp', App_Connect::schemes(), true ) );
remove_filter( 'wb_listora_app_connect_schemes', $listora_sibling );

// ── 3. One-door deference + override ─────────────────────────────────────
$listora_bridge = App_Connect::bridge_info();

if ( $listora_bn_active ) {
	listora_auth_check( 'bridge.owner-bn', 'buddynext' === $listora_bridge['owner'] );
	listora_auth_check( 'bridge.bn-url', '' !== (string) $listora_bridge['connect_url'] );
	listora_auth_check(
		'bridge.bn-allowlist-accepts-us',
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Reading BuddyNext's OWN allowlist is the point of this check; Listora only ever add_filter()s it in production code.
		in_array( 'listoraapp', (array) apply_filters( 'buddynext_app_connect_schemes', array() ), true ),
		'BN allowlist does not carry listoraapp — join filter not registered?'
	);
} else {
	listora_auth_check( 'bridge.owner-self', 'wb-listora' === $listora_bridge['owner'] );
	listora_auth_check( 'bridge.no-own-bridge', '' === (string) $listora_bridge['connect_url'], 'standalone Listora must NOT advertise a bridge' );
}

$listora_override = function () {
	return array(
		'owner'           => 'test',
		'connect_url'     => 'https://example.test/connect-app/',
		'connect_schemes' => array( 'listoraapp' ),
	);
};
add_filter( 'wb_listora_app_connect_bridge', $listora_override );
$listora_forced = App_Connect::bridge_info();
remove_filter( 'wb_listora_app_connect_bridge', $listora_override );
listora_auth_check( 'bridge.filter-override', 'https://example.test/connect-app/' === $listora_forced['connect_url'] );

// ── 4. kses repair is SCOPED to the authorize screen ─────────────────────
// esc_url() itself cannot be exercised both ways in-process: wp_allowed_protocols()
// freezes its static list long before this runs. On a REAL authorize request
// SCRIPT_FILENAME is the authorize screen from the first line, so the filter fires
// during boot and the scheme enters the list — the browser walk proves that
// end-to-end. Here we prove the filter's own scoping decision.
listora_auth_check( 'kses.stripped-in-content', '' === esc_url( 'listoraapp://auth?x=1' ), 'app scheme must NOT be linkable in ordinary content' );

$listora_prev_script        = isset( $_SERVER['SCRIPT_FILENAME'] )
	? sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_FILENAME'] ) )
	: '';
$_SERVER['SCRIPT_FILENAME'] = '/wp-admin/authorize-application.php';
$listora_on_authorize       = App_Authorize_Access::allow_app_scheme( array( 'http', 'https' ) );
$_SERVER['SCRIPT_FILENAME'] = '/index.php';
$listora_off_authorize      = App_Authorize_Access::allow_app_scheme( array( 'http', 'https' ) );
$_SERVER['SCRIPT_FILENAME'] = $listora_prev_script;

listora_auth_check( 'kses.filter-adds-on-authorize', in_array( 'listoraapp', $listora_on_authorize, true ) );
listora_auth_check( 'kses.filter-inert-elsewhere', ! in_array( 'listoraapp', $listora_off_authorize, true ) );

// ── 5. The credentials exchange, live over loopback ──────────────────────
$listora_login    = 'listora_appauth_contract_user';
$listora_password = wp_generate_password( 24 );
$listora_existing = get_user_by( 'login', $listora_login );

if ( $listora_existing ) {
	wp_delete_user( $listora_existing->ID );
}

$listora_user_id = wp_insert_user(
	array(
		'user_login' => $listora_login,
		'user_pass'  => $listora_password,
		'user_email' => $listora_login . '@example.test',
		'role'       => 'subscriber',
	)
);

if ( is_wp_error( $listora_user_id ) ) {
	listora_auth_check( 'exchange.fixture', false, $listora_user_id->get_error_message() );
} else {
	$listora_app_id = wp_generate_uuid4();

	list( $listora_status, $listora_body ) = listora_auth_request(
		'POST',
		'/auth/app-password',
		array(
			'username' => $listora_login,
			'password' => $listora_password,
			'app_name' => 'Listora contract',
			'app_id'   => $listora_app_id,
		)
	);
	$listora_minted                        = isset( $listora_body['password'] ) ? (string) $listora_body['password'] : '';

	listora_auth_check( 'exchange.mints', 200 === $listora_status && '' !== $listora_minted, "status {$listora_status}" );

	if ( '' !== $listora_minted ) {
		list( $listora_status ) = listora_auth_request( 'GET', '/settings/app-config', array(), $listora_login . ':' . $listora_minted );
		listora_auth_check( 'exchange.credential-authenticates', 200 === $listora_status, "status {$listora_status}" );
	}

	list( $listora_status, $listora_body ) = listora_auth_request(
		'POST',
		'/auth/app-password',
		array(
			'username' => $listora_login,
			'password' => 'definitely-wrong-password',
		)
	);
	listora_auth_check(
		'exchange.wrong-password-401',
		401 === $listora_status && 'wb_listora_login_failed' === ( isset( $listora_body['code'] ) ? $listora_body['code'] : '' ),
		"status {$listora_status} code " . ( isset( $listora_body['code'] ) ? $listora_body['code'] : '-' )
	);

	// ── 6. Reconnect replaces, on EVERY door ─────────────────────────────
	WP_Application_Passwords::create_new_application_password(
		$listora_user_id,
		array(
			'name'   => 'Listora',
			'app_id' => $listora_app_id,
		)
	);

	$listora_rows = array();
	foreach ( WP_Application_Passwords::get_user_application_passwords( $listora_user_id ) as $listora_row ) {
		if ( isset( $listora_row['app_id'] ) && $listora_app_id === $listora_row['app_id'] ) {
			$listora_rows[] = $listora_row;
		}
	}
	listora_auth_check( 'replace.one-row-per-install', 1 === count( $listora_rows ), count( $listora_rows ) . ' rows for one app_id' );

	WP_Application_Passwords::create_new_application_password( $listora_user_id, array( 'name' => 'Hand-made' ) );
	WP_Application_Passwords::create_new_application_password(
		$listora_user_id,
		array(
			'name'   => 'Listora',
			'app_id' => $listora_app_id,
		)
	);
	$listora_all = WP_Application_Passwords::get_user_application_passwords( $listora_user_id );
	listora_auth_check( 'replace.handmade-untouched', 2 === count( $listora_all ), count( $listora_all ) . ' rows total (want hand-made + one app row)' );

	wp_delete_user( $listora_user_id );
}

$listora_tally = listora_auth_check();

echo "\n{$listora_tally['pass']} passed, {$listora_tally['fail']} failed\n";

exit( $listora_tally['fail'] > 0 ? 1 : 0 );
