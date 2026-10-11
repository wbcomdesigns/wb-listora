<?php
/**
 * Throwaway fixtures for 2026-09-30 RFT5. Restore via restore-rft5.php.
 */
defined( 'ABSPATH' ) || exit;

$snap = array();

$classified = get_term_by( 'slug', 'classified', 'listora_listing_type' );
$snap['classified_term']    = $classified ? (int) $classified->term_id : 0;
$snap['classified_allowed'] = $classified ? get_term_meta( $classified->term_id, '_listora_allowed_categories', true ) : array();

$parent = term_exists( 'qa-rft5-motorcycle', 'listora_listing_cat' );
if ( ! $parent ) {
	$parent = wp_insert_term( 'QA-RFT5-Motorcycle', 'listora_listing_cat', array( 'slug' => 'qa-rft5-motorcycle' ) );
}
$parent_id = is_wp_error( $parent ) ? 0 : (int) ( $parent['term_id'] ?? $parent );

$cruiser = term_exists( 'qa-rft5-cruiser', 'listora_listing_cat' );
if ( ! $cruiser && $parent_id ) {
	$cruiser = wp_insert_term( 'QA-RFT5-Cruiser', 'listora_listing_cat', array( 'slug' => 'qa-rft5-cruiser', 'parent' => $parent_id ) );
}
$sport = term_exists( 'qa-rft5-sport', 'listora_listing_cat' );
if ( ! $sport && $parent_id ) {
	$sport = wp_insert_term( 'QA-RFT5-Sport', 'listora_listing_cat', array( 'slug' => 'qa-rft5-sport', 'parent' => $parent_id ) );
}
$cruiser_id = is_wp_error( $cruiser ) ? 0 : (int) ( $cruiser['term_id'] ?? $cruiser );
$sport_id   = is_wp_error( $sport ) ? 0 : (int) ( $sport['term_id'] ?? $sport );

$snap['parent_id']  = $parent_id;
$snap['cruiser_id'] = $cruiser_id;
$snap['sport_id']   = $sport_id;
$snap['vehicles_id'] = 102;

if ( $classified && $parent_id ) {
	update_term_meta( $classified->term_id, '_listora_allowed_categories', array( $parent_id, 102 ) );
	wp_cache_delete( 'wb_listora_type_registry', 'listora' );
}

$plan_id = wp_insert_post(
	array(
		'post_type'   => 'listora_plan',
		'post_status' => 'publish',
		'post_title'  => 'QA-RFT5-Moto-Plan',
		'post_author' => 1,
	)
);
if ( ! is_wp_error( $plan_id ) && $plan_id && $parent_id ) {
	update_post_meta( $plan_id, '_listora_plan_categories', array( $parent_id ) );
	update_post_meta( $plan_id, '_listora_plan_credits', 0 );
	update_post_meta( $plan_id, '_listora_plan_duration_days', 30 );
	update_post_meta( $plan_id, '_listora_plan_display_price', 'QA $0' );
}
$snap['plan_id'] = is_wp_error( $plan_id ) ? 0 : (int) $plan_id;

$need_author = get_user_by( 'login', 'journey_owner' );
$need_id     = 0;
if ( $need_author && post_type_exists( 'listora_need' ) ) {
	$need_id = wp_insert_post(
		array(
			'post_type'    => 'listora_need',
			'post_status'  => 'publish',
			'post_title'   => 'QA-RFT5-stale-need',
			'post_author'  => $need_author->ID,
			'post_content' => 'Throwaway need for stale-count gate retest.',
			'meta_input'   => array(
				'_listora_need_status' => 'open',
			),
		),
		true
	);
	if ( is_wp_error( $need_id ) ) {
		$snap['need_error'] = $need_id->get_error_message();
		$need_id            = 0;
	}
}
if ( $need_id ) {
	update_post_meta( $need_id, '_listora_need_response_count', 2 );
	update_post_meta( $need_id, '_listora_need_status', 'open' );
	update_post_meta( $need_id, '_listora_need_urgency', 'normal' );
}
$snap['need_id'] = (int) $need_id;

file_put_contents( __DIR__ . '/rft5-snap.json', wp_json_encode( $snap, JSON_PRETTY_PRINT ) );

$allows_child = $allows_unrelated = $count = null;
if ( $snap['plan_id'] && $cruiser_id && class_exists( '\WBListoraPro\Features\Pricing_Plans' ) ) {
	$allows_child     = \WBListoraPro\Features\Pricing_Plans::plan_allows_category( $snap['plan_id'], $cruiser_id );
	$allows_parent    = \WBListoraPro\Features\Pricing_Plans::plan_allows_category( $snap['plan_id'], $parent_id );
	$allows_unrelated = \WBListoraPro\Features\Pricing_Plans::plan_allows_category( $snap['plan_id'], 102 );
	$allows_empty     = \WBListoraPro\Features\Pricing_Plans::plan_allows_category( $snap['plan_id'], array() );
}
if ( $need_id && class_exists( '\WBListoraPro\Needs\Need_Response_Manager' ) ) {
	$count = \WBListoraPro\Needs\Need_Response_Manager::get_count_for_need( $need_id );
}

$expanded = function_exists( 'wb_listora_expand_category_ids_with_descendants' )
	? wb_listora_expand_category_ids_with_descendants( array( $parent_id ) )
	: array();

echo wp_json_encode(
	array(
		'snap'             => $snap,
		'expanded'         => $expanded,
		'allows_child'     => $allows_child,
		'allows_parent'    => $allows_parent ?? null,
		'allows_unrelated' => $allows_unrelated,
		'allows_empty'     => $allows_empty ?? null,
		'need_live_count'  => $count,
		'need_meta'        => $need_id ? (int) get_post_meta( $need_id, '_listora_need_response_count', true ) : null,
		'type_exists'      => post_type_exists( 'listora_need' ),
	),
	JSON_UNESCAPED_SLASHES
) . "\n";
