<?php
/**
 * Follow-up: open credit purchase path + top up balances.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$credits_id = (int) get_option( 'wb_listora_pro_credits_page' );
if ( ! $credits_id ) {
	$credits_id = (int) get_option( 'wb_listora_pro_credits_page_id' );
}

update_option( 'wb_listora_credit_purchase_url', $credits_id );

// Do NOT point pack URLs at the Buy Credits page — that creates a Buy Now
// self-loop. Purchase-path visibility comes from wb_listora_credit_purchase_url
// (and/or a real gateway / external product URL). Clear any prior self-links.
$packs   = (array) get_option( 'wb_listora_pro_credit_packs', array() );
$buy_url = $credits_id ? untrailingslashit( (string) get_permalink( $credits_id ) ) : '';
foreach ( $packs as &$pack ) {
	$pack_url = ! empty( $pack['url'] ) ? untrailingslashit( (string) $pack['url'] ) : '';
	if ( $pack_url && $buy_url && $pack_url === $buy_url ) {
		$pack['url'] = '';
	}
}
unset( $pack );

// Wire WooCommerce add-to-cart URLs so Pro's purchase-path gate opens member
// Credits surfaces (Pro ignores same-site override + empty pack URLs).
if ( function_exists( 'wc_get_product' ) && function_exists( 'wb_listora_get_credit_mappings' ) ) {
	$mappings = wb_listora_get_credit_mappings();
	foreach ( $packs as $i => &$pack ) {
		if ( ! empty( $pack['url'] ) || ! isset( $mappings[ $i ] ) ) {
			continue;
		}
		$map = $mappings[ $i ];
		if ( empty( $map['item_id'] ) || 'woocommerce' !== ( $map['adapter'] ?? '' ) ) {
			continue;
		}
		$product = wc_get_product( (int) $map['item_id'] );
		if ( $product ) {
			$pack['url'] = $product->add_to_cart_url();
		}
	}
	unset( $pack );
}

update_option( 'wb_listora_pro_credit_packs', $packs );

$slug  = 'wb-listora';
$users = array( 1 );
foreach ( get_users( array( 'number' => 20, 'orderby' => 'ID' ) ) as $user ) {
	if ( false !== strpos( $user->user_login, 'listora' ) || false !== strpos( $user->user_login, 'qa' ) ) {
		$users[] = (int) $user->ID;
	}
}
$users  = array_values( array_unique( array_map( 'intval', $users ) ) );
$topups = array();

if ( class_exists( '\Wbcom\Credits\Credits' ) ) {
	foreach ( $users as $uid ) {
		$before = \Wbcom\Credits\Credits::balance_money( $slug, $uid );
		$ok     = \Wbcom\Credits\Credits::award( $slug, $uid, 500, 'QA site-owner seed' );
		$after  = \Wbcom\Credits\Credits::balance_money( $slug, $uid );
		$topups[] = array(
			'uid'    => $uid,
			'ok'     => (bool) $ok,
			'before' => $before,
			'after'  => $after,
		);
	}
}

$out = array(
	'credits_page'   => $credits_id,
	'credits_url'    => $credits_id ? get_permalink( $credits_id ) : '',
	'purchase_path'  => function_exists( 'wb_listora_has_credit_purchase_path' ) ? wb_listora_has_credit_purchase_path() : null,
	'show_credits'   => function_exists( 'wb_listora_should_show_member_credits' ) ? wb_listora_should_show_member_credits() : null,
	'override'       => get_option( 'wb_listora_credit_purchase_url' ),
	'topups'         => $topups,
	'plans'          => count( get_posts( array( 'post_type' => 'listora_plan', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids' ) ) ),
	'coupons'        => count( get_posts( array( 'post_type' => 'listora_coupon', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids' ) ) ),
	'needs'          => count( get_posts( array( 'post_type' => 'listora_need', 'posts_per_page' => -1, 'post_status' => 'publish', 'fields' => 'ids' ) ) ),
	'webhooks'       => count( get_posts( array( 'post_type' => 'listora_webhook', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) ),
);

echo wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
