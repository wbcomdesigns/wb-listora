<?php
/**
 * Snapshot + throwaway fixtures for adversarial re-walk. Restore via restore-adversarial.php.
 */
$snap = array();

$term = get_term_by( 'slug', 'restaurant', 'listora_listing_type' );
$snap['criteria_term'] = $term ? (int) $term->term_id : 0;
$snap['criteria']      = $term ? get_term_meta( $term->term_id, '_listora_review_criteria', true ) : array();
if ( $term ) {
	update_term_meta(
		$term->term_id,
		'_listora_review_criteria',
		array(
			array(
				'key'   => 'qa_bounce_crit',
				'label' => 'QA Bounce Criterion',
			),
		)
	);
}

$s = get_option( 'wb_listora_settings', array() );
$snap['social_platforms'] = $s['social_platforms'] ?? null;
$s['social_platforms']    = array();
update_option( 'wb_listora_settings', $s );

$owned = get_post( 7264 );
$snap['owned_status'] = $owned ? $owned->post_status : '';

$cat = wp_insert_term( 'QA-RFT-svc-cat-' . time(), 'listora_service_cat' );
$snap['svc_cat'] = is_wp_error( $cat ) ? 0 : (int) $cat['term_id'];

$need_author = get_user_by( 'login', 'journey_owner' );
$need_id     = 0;
if ( $need_author && post_type_exists( 'listora_need' ) ) {
	$need_id = wp_insert_post(
		array(
			'post_type'   => 'listora_need',
			'post_status' => 'publish',
			'post_title'  => 'QA-ADV-need-20260930',
			'post_author' => $need_author->ID,
			'post_content'=> 'Throwaway need for My Needs button typography.',
		)
	);
}
$snap['need_id'] = is_wp_error( $need_id ) ? 0 : (int) $need_id;

file_put_contents( __DIR__ . '/adversarial-snap.json', wp_json_encode( $snap, JSON_PRETTY_PRINT ) );
echo wp_json_encode(
	array(
		'snap'     => $snap,
		'platforms'=> \WBListora\Core\Field::social_link_platforms(),
		'criteria' => $term ? get_term_meta( $term->term_id, '_listora_review_criteria', true ) : null,
	),
	JSON_UNESCAPED_SLASHES
) . "\n";
