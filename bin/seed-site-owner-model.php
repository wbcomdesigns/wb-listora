<?php
/**
 * Model site-owner configuration for directory.local (playground).
 *
 * Turns the QA dump into a directory a site owner would recognise:
 * OSM maps with a real tile URL, no unused Google toggle, no white-label
 * rename, linked pages, English Woo storefront, receipt identity, legal
 * URLs, and listing limits that match a paid directory.
 *
 * Usage (after demo + site-owner QA seed):
 *   wp eval-file wp-content/plugins/wb-listora/bin/seed-site-owner-model.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$report = array();

/**
 * Ensure a published page exists and return its ID.
 *
 * @param string $slug    Page slug.
 * @param string $title   Title if created.
 * @param string $content Post content.
 * @return int
 */
function listora_model_ensure_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		if ( 'publish' !== $existing->post_status ) {
			wp_update_post(
				array(
					'ID'          => $existing->ID,
					'post_status' => 'publish',
				)
			);
		}
		return (int) $existing->ID;
	}
	$id = wp_insert_post(
		array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => $content,
			'post_author'  => 1,
		),
		true
	);
	return is_wp_error( $id ) ? 0 : (int) $id;
}

// ── 1. Site identity ────────────────────────────────────────────────
update_option( 'blogname', 'Listora Directory (QA)' );
update_option( 'blogdescription', 'A model NYC directory — how a site owner should configure Listora.' );
update_option( 'WPLANG', '' );
update_option( 'timezone_string', 'America/New_York' );
update_option( 'date_format', 'F j, Y' );
update_option( 'time_format', 'g:i a' );
update_option( 'start_of_week', '0' );
update_option( 'permalink_structure', '/%postname%/' );
$report['identity'] = true;

// ── 2. Pro toggles: keep the product on, drop the confusing ones ────
$pro = (array) get_option( 'wb_listora_pro_features', array() );
// OSM is live; leaving Google Maps ON shows a persistent “feature does not
// switch tiles” notice on Settings even though Maps → Provider is OSM.
$pro['google_maps'] = false;
// White label hides “Listora” in admin. A playground should look like Listora.
$pro['white_label'] = false;
update_option( 'wb_listora_pro_features', $pro );
update_option( 'wb_listora_pro_visibility', 'public' );
$report['pro'] = array(
	'google_maps' => false,
	'white_label' => false,
	'visibility'  => 'public',
);

// ── 3. Maps + directory defaults (OSM, NYC, miles) ──────────────────
$s = (array) get_option( 'wb_listora_settings', array() );
$s['map_provider']          = 'osm';
$s['map_tile_url']          = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
$s['map_tile_attribution']  = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>';
$s['google_maps_key']       = '';
$s['map_default_lat']       = 40.7128;
$s['map_default_lng']       = -74.0060;
$s['map_default_zoom']      = 12;
$s['map_clustering']        = true;
$s['map_search_on_drag']    = true;
$s['currency']              = 'USD';
$s['distance_unit']         = 'mi';
$s['enable_expiration']     = true;
$s['moderation']            = 'manual';
$s['enable_guest_submission'] = false;
$s['debug_logging']         = false;
$s['delete_on_uninstall']   = false;

// Free to submit, paid to feature — the usual owner story.
$s['default_listing_credit_cost'] = 0;
$s['featured_credit_cost']        = 25;
$s['featured_duration_days']      = 30;

// Members: 5 listings / calendar month; staff unlimited; overflow via credits.
$s['listing_limits_period']            = 'calendar_month';
$s['listing_beyond_limit_behavior']    = 'credits';
$s['listing_limits_default']           = 5;
$s['listing_limits_per_role']          = array(
	'administrator'     => -1,
	'editor'            => -1,
	'listora_moderator' => -1,
	'shop_manager'      => -1,
	'author'            => 20,
	'contributor'       => 10,
	'subscriber'        => 5,
	'customer'          => 5,
);

// ── 4. Linked pages (settings + registry option keys) ───────────────
$dir_page    = get_page_by_path( 'listings' );
$submit_p    = get_page_by_path( 'add-listing' );
$dash_p      = get_page_by_path( 'my-listings' );
$compare_p   = get_page_by_path( 'compare-listings' );
$browse_p    = get_page_by_path( 'browse-needs' );
$post_need_p = get_page_by_path( 'post-a-need' );
if ( ! $post_need_p ) {
	$post_need_p = get_page_by_path( 'post-need' );
}
$credits_p  = get_page_by_path( 'buy-credits' );
$home_p     = get_page_by_path( 'home' );
$blog_p     = get_page_by_path( 'blog' );

$dir_id     = $dir_page ? (int) $dir_page->ID : 0;
$submit_id  = $submit_p ? (int) $submit_p->ID : 0;
$dash_id    = $dash_p ? (int) $dash_p->ID : 0;
$compare_id = $compare_p ? (int) $compare_p->ID : 0;
$browse_id  = $browse_p ? (int) $browse_p->ID : 0;
$post_need  = $post_need_p ? (int) $post_need_p->ID : 0;
$credits_id = $credits_p ? (int) $credits_p->ID : 0;
$home_id    = $home_p ? (int) $home_p->ID : 0;
$blog_id    = $blog_p ? (int) $blog_p->ID : 0;

$s['directory_page']  = $dir_id;
$s['submission_page'] = $submit_id;
$s['dashboard_page']  = $dash_id;

if ( $dir_id ) {
	update_option( 'wb_listora_directory_page_id', $dir_id );
}
if ( $submit_id ) {
	update_option( 'wb_listora_submission_page_id', $submit_id );
}
if ( $dash_id ) {
	update_option( 'wb_listora_dashboard_page_id', $dash_id );
}
if ( $compare_id ) {
	update_option( 'wb_listora_pro_compare_page_id', $compare_id );
}
if ( $browse_id ) {
	update_option( 'wb_listora_pro_browse_needs_page_id', $browse_id );
}
if ( $post_need ) {
	update_option( 'wb_listora_pro_post_need_page_id', $post_need );
}
if ( $credits_id ) {
	update_option( 'wb_listora_pro_credits_page', $credits_id );
	update_option( 'wb_listora_pro_credits_page_id', $credits_id );
	update_option( 'wb_listora_credit_purchase_url', $credits_id );
}

$report['pages'] = compact( 'dir_id', 'submit_id', 'dash_id', 'compare_id', 'browse_id', 'post_need', 'credits_id' );

// ── 5. Legal pages (app-store + Settings → General) ─────────────────
$privacy_id = listora_model_ensure_page(
	'privacy-policy',
	'Privacy Policy',
	"<!-- wp:paragraph --><p>This playground directory stores listing, review, and account data so site-owner flows can be tested. Do not put real customer data here.</p><!-- /wp:paragraph -->"
);
$terms_id = listora_model_ensure_page(
	'terms-of-service',
	'Terms of Service',
	"<!-- wp:paragraph --><p>QA playground terms. Listings, reviews, and credit purchases on this site are for testing WB Listora only.</p><!-- /wp:paragraph -->"
);
$guidelines_id = listora_model_ensure_page(
	'community-guidelines',
	'Community Guidelines',
	"<!-- wp:paragraph --><p>Be accurate. No spam listings, fake reviews, or harassment. Report problems from the listing or review actions.</p><!-- /wp:paragraph -->"
);
if ( $privacy_id ) {
	update_option( 'wp_page_for_privacy_policy', $privacy_id );
	$s['privacy_policy_url'] = (string) get_permalink( $privacy_id );
}
if ( $terms_id ) {
	$s['legal_terms_url'] = (string) get_permalink( $terms_id );
}
if ( $guidelines_id ) {
	$s['legal_community_guidelines_url'] = (string) get_permalink( $guidelines_id );
}
$s['legal_abuse_contact_email'] = 'abuse@listora.test';
update_option( 'wb_listora_settings', $s );
$report['legal'] = compact( 'privacy_id', 'terms_id', 'guidelines_id' );

// Front page = Home, posts = Journal (if present).
if ( $home_id ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $home_id );
}
if ( $blog_id ) {
	update_option( 'page_for_posts', $blog_id );
}

// ── 6. Receipt business details (clears Credits nag) ────────────────
update_option(
	'wb_listora_pro_business_details',
	array(
		'name'    => 'Listora QA Directory',
		'address' => "247 West Broadway\nNew York, NY 10013\nUnited States",
		'email'   => 'billing@listora.test',
		'phone'   => '+1 212 555 0100',
		'tax_id'  => 'EIN 12-3456789',
	),
	false
);
$report['receipts'] = true;

// ── 7. English WooCommerce storefront ───────────────────────────────
$woo_renames = array(
	'kasse'                     => array( 'Checkout', 'checkout' ),
	'warenkorb'                 => array( 'Cart', 'cart' ),
	'mein-konto'                => array( 'My Account', 'my-account' ),
	'erstattungen_rueckgaben'   => array( 'Refunds and Returns', 'refunds-returns' ),
	'shop'                      => array( 'Shop', 'shop' ),
);
foreach ( $woo_renames as $slug => $pair ) {
	$page = get_page_by_path( $slug );
	if ( ! $page ) {
		continue;
	}
	wp_update_post(
		array(
			'ID'           => $page->ID,
			'post_title'   => $pair[0],
			'post_name'    => $pair[1],
			'post_status'  => 'publish',
		)
	);
}
if ( function_exists( 'WC' ) ) {
	update_option( 'woocommerce_store_address', '247 West Broadway' );
	update_option( 'woocommerce_store_city', 'New York' );
	update_option( 'woocommerce_default_country', 'US:NY' );
	update_option( 'woocommerce_store_postcode', '10013' );
	update_option( 'woocommerce_currency', 'USD' );
	update_option( 'woocommerce_price_thousand_sep', ',' );
	update_option( 'woocommerce_price_decimal_sep', '.' );
	update_option( 'woocommerce_default_customer_address', 'base' );
}
$report['woocommerce'] = 'en_US storefront';

// ── 8. Keep credit pack buy URLs on Woo add-to-cart ─────────────────
if ( function_exists( 'wc_get_product' ) && function_exists( 'wb_listora_get_credit_mappings' ) ) {
	$packs    = (array) get_option( 'wb_listora_pro_credit_packs', array() );
	$mappings = wb_listora_get_credit_mappings();
	foreach ( $packs as $i => &$pack ) {
		if ( empty( $mappings[ $i ]['item_id'] ) ) {
			continue;
		}
		$product = wc_get_product( (int) $mappings[ $i ]['item_id'] );
		if ( $product ) {
			$pack['url'] = $product->add_to_cart_url();
		}
	}
	unset( $pack );
	update_option( 'wb_listora_pro_credit_packs', $packs );
}

// ── 9. Fix leftover need type (photographer was stored as place) ────
$needs = get_posts(
	array(
		'post_type'      => 'listora_need',
		's'              => 'wedding photographer',
		'posts_per_page' => 5,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);
foreach ( $needs as $nid ) {
	update_post_meta( (int) $nid, '_listora_need_listing_type', 'business' );
}
$report['needs_retyped'] = count( $needs );

flush_rewrite_rules( false );
$report['ok'] = true;

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
