<?php
/**
 * Plugin uninstall handler.
 *
 * Fired when the plugin is deleted via WordPress admin.
 * Only deletes data if the user opted in via Settings → Advanced → Delete data on uninstall.
 *
 * @package WBListora
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Check if user opted in to data deletion.
$settings = get_option( 'wb_listora_settings', array() );

if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$prefix = $wpdb->prefix . 'listora_';

// Drop all custom tables.
$tables = array(
	'geo',
	'search_index',
	'field_index',
	'reviews',
	'review_votes',
	'favorites',
	'claims',
	'hours',
	'analytics',
	'payments',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete all listora postmeta.
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete all listora options.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'wb_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete all listora term meta.
$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE '_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete all listora user meta.
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE '_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete transients.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_listora_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

// Delete CPT posts.
$listings = get_posts(
	array(
		'post_type'      => 'listora_listing',
		'post_status'    => 'any',
		'posts_per_page' => 500,
		'fields'         => 'ids',
	)
);

foreach ( $listings as $listing_id ) {
	wp_delete_post( $listing_id, true );
}

// Delete taxonomy terms.
$taxonomies = array(
	'listora_listing_type',
	'listora_listing_cat',
	'listora_listing_tag',
	'listora_listing_location',
	'listora_listing_feature',
);

foreach ( $taxonomies as $taxonomy ) {
	$terms = get_terms(
		array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( is_array( $terms ) ) {
		foreach ( $terms as $term_id ) {
			wp_delete_term( $term_id, $taxonomy );
		}
	}
}

// Remove capabilities from all roles.
$caps = array(
	'edit_listora_listing',
	'edit_listora_listings',
	'edit_others_listora_listings',
	'edit_published_listora_listings',
	'publish_listora_listings',
	'delete_listora_listing',
	'delete_listora_listings',
	'delete_others_listora_listings',
	'delete_published_listora_listings',
	'read_private_listora_listings',
	'manage_listora_settings',
	'moderate_listora_reviews',
	'manage_listora_claims',
	'manage_listora_types',
	'submit_listora_listing',
);

$roles = wp_roles();
foreach ( $roles->role_objects as $role ) {
	foreach ( $caps as $cap ) {
		$role->remove_cap( $cap );
	}
}

// Flush rewrite rules.
flush_rewrite_rules();
