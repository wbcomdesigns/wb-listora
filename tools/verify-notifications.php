<?php
/**
 * Verification script for the WB Listora notification gating system.
 *
 * Run with:
 *   wp eval-file wp-content/plugins/wb-listora/tools/verify-notifications.php
 *
 * Validates three gating paths:
 *   1. Admin global toggle off  -> 'admin_disabled' skip reason fired.
 *   2. Per-user toggle off      -> 'user_disabled' skip reason fired.
 *   3. Both enabled             -> wp_mail() is invoked + log entry written.
 *
 * Restores the prior state at the end so this is safe to re-run on any site.
 *
 * @package WBListora\Tools
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tiny color helper for terminal output.
 */
function listora_verify_print( string $msg ): void {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
	echo $msg . "\n";
}

// Pick a real listing + author so the methods don't bail out early.
$listings = get_posts(
	array(
		'post_type'      => 'listora_listing',
		'post_status'    => 'any',
		'posts_per_page' => 1,
		'fields'         => 'ids',
	)
);

if ( empty( $listings ) ) {
	listora_verify_print( 'NO listings found — create one before running this script.' );
	return;
}

$listing_id = (int) $listings[0];
$user_id    = (int) get_post_field( 'post_author', $listing_id );

if ( $user_id <= 0 ) {
	listora_verify_print( "Listing #{$listing_id} has no valid author." );
	return;
}

listora_verify_print( "Using listing #{$listing_id}, author user #{$user_id}." );

// Snapshot existing state so we can restore at the end.
$prev_settings = get_option( 'wb_listora_settings', array() );
$prev_user_pref = get_user_meta( $user_id, '_listora_notify_listing_approved', true );

// ─────────────────────────────────────────────────────────────────
// 1. Admin global toggle off — should skip with reason 'admin_disabled'.
// ─────────────────────────────────────────────────────────────────
$settings = $prev_settings;
if ( ! is_array( $settings ) ) {
	$settings = array();
}
if ( ! isset( $settings['notifications'] ) || ! is_array( $settings['notifications'] ) ) {
	$settings['notifications'] = array();
}
$settings['notifications']['listing_approved'] = false;
update_option( 'wb_listora_settings', $settings );

// Make sure no per-user override interferes.
delete_user_meta( $user_id, '_listora_notify_listing_approved' );

$skipped_admin = false;
$cb_admin      = function ( $event, $reason ) use ( &$skipped_admin ) {
	if ( 'listing_approved' === $event && 'admin_disabled' === $reason ) {
		$skipped_admin = true;
	}
};
add_action( 'wb_listora_notification_skipped', $cb_admin, 10, 2 );

$notifications = new \WBListora\Workflow\Notifications();
// Pass a valid old_status so the early-return guard inside listing_approved() is satisfied.
$notifications->listing_approved( $listing_id, 'pending' );

remove_action( 'wb_listora_notification_skipped', $cb_admin, 10 );
listora_verify_print( '1) skipped on admin off:        ' . ( $skipped_admin ? 'YES ✓' : 'NO ✗' ) );

// ─────────────────────────────────────────────────────────────────
// 2. Re-enable globally; disable per-user — should skip 'user_disabled'.
// ─────────────────────────────────────────────────────────────────
$settings['notifications']['listing_approved'] = true;
update_option( 'wb_listora_settings', $settings );
update_user_meta( $user_id, '_listora_notify_listing_approved', '0' );

$skipped_user = false;
$cb_user      = function ( $event, $reason ) use ( &$skipped_user ) {
	if ( 'listing_approved' === $event && 'user_disabled' === $reason ) {
		$skipped_user = true;
	}
};
add_action( 'wb_listora_notification_skipped', $cb_user, 10, 2 );

$notifications->listing_approved( $listing_id, 'pending' );

remove_action( 'wb_listora_notification_skipped', $cb_user, 10 );
listora_verify_print( '2) skipped on user off:         ' . ( $skipped_user ? 'YES ✓' : 'NO ✗' ) );

// ─────────────────────────────────────────────────────────────────
// 3. Both enabled — wp_mail() should fire and a log entry should be written.
// ─────────────────────────────────────────────────────────────────
delete_user_meta( $user_id, '_listora_notify_listing_approved' );

// Snapshot log length so we can verify a NEW entry was added.
$pre_log_count = count( \WBListora\Workflow\Notifications::get_log() );

$mail_fired = false;
$cb_mail    = function ( $args ) use ( &$mail_fired ) {
	$mail_fired = true;
	// Short-circuit so wp_mail returns the args without actually sending.
	return $args;
};
add_filter( 'wp_mail', $cb_mail );

// Also intercept phpmailer to short-circuit actual delivery on dev hosts that
// might otherwise try (and fail) to use sendmail.
$pm_short = function ( $mailer ) {
	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	$mailer->Mailer = 'mail';
};
add_action( 'phpmailer_init', $pm_short );

$notifications->listing_approved( $listing_id, 'pending' );

remove_filter( 'wp_mail', $cb_mail );
remove_action( 'phpmailer_init', $pm_short );

listora_verify_print( '3) wp_mail fires when enabled:  ' . ( $mail_fired ? 'YES ✓' : 'NO ✗' ) );

$log = \WBListora\Workflow\Notifications::get_log();
listora_verify_print( '   log entries (was -> now):    ' . $pre_log_count . ' -> ' . count( $log ) );
listora_verify_print( '   latest log event_key:        ' . ( $log[0]['event_key'] ?? 'none' ) );
listora_verify_print( '   latest log subject:          ' . ( $log[0]['subject'] ?? 'none' ) );

// ─────────────────────────────────────────────────────────────────
// Restore prior state.
// ─────────────────────────────────────────────────────────────────
update_option( 'wb_listora_settings', $prev_settings );
if ( '' === $prev_user_pref ) {
	delete_user_meta( $user_id, '_listora_notify_listing_approved' );
} else {
	update_user_meta( $user_id, '_listora_notify_listing_approved', $prev_user_pref );
}

$all_pass = $skipped_admin && $skipped_user && $mail_fired && count( $log ) > $pre_log_count;
listora_verify_print( '' );
listora_verify_print( $all_pass ? 'ALL CHECKS PASSED ✓' : 'ONE OR MORE CHECKS FAILED ✗' );
