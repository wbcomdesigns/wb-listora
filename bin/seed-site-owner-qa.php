<?php
/**
 * Site-owner readiness seed for directory.local (1.3.1 QA).
 *
 * Enables Pro surfaces site owners expect, creates showcase pages,
 * credit packs + Buy Credits page, needs marketplace sample data,
 * and assigns a manual custom badge for discovery QA.
 *
 * Usage: wp eval-file bin/seed-site-owner-qa.php
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

$report = array();

// ── 1. Pro feature toggles (site-owner "everything on for QA") ───────
$pro = (array) get_option( 'wb_listora_pro_features', array() );
$enable = array(
	'comparison',
	'quick_view',
	'lead_form',
	'verification',
	'badges',
	'audit_log',
	'google_maps', // still OSM until API key — toggle stays on for settings UX
	'multi_criteria_reviews',
	'photo_reviews',
	'advanced_search',
	'seo_pages',
	'infinite_scroll',
	'outgoing_webhooks',
	'analytics',
	'buddy_press_integration',
	'reverse_listings',
	'white_label',
	'coming_soon',
	'notification_digest',
	'monetization',
);
foreach ( $enable as $key ) {
	$pro[ $key ] = true;
}
update_option( 'wb_listora_pro_features', $pro );
$report['pro_toggles_on'] = count( $enable );

// Free flags stay on.
$free = (array) get_option( 'wb_listora_features', array() );
foreach ( array( 'submission', 'reviews', 'claims', 'favorites', 'renewal', 'report_listings', 'schema', 'opengraph', 'breadcrumbs', 'sitemap' ) as $k ) {
	$free[ $k ] = true;
}
update_option( 'wb_listora_features', $free );

// ── 2. Credit packs + rate ───────────────────────────────────────────
$packs = array(
	array( 'label' => 'Starter', 'credits' => 50, 'price' => 10.0 ),
	array( 'label' => 'Pro', 'credits' => 200, 'price' => 35.0 ),
	array( 'label' => 'Business', 'credits' => 500, 'price' => 75.0 ),
);
update_option( 'wb_listora_pro_credit_packs', $packs );
update_option( 'wb_listora_pro_credit_rate', '1' );
$report['credit_packs'] = count( $packs );

// ── 3. Buy Credits page ──────────────────────────────────────────────
if ( class_exists( '\WBListoraPro\Features\Pricing_Plans' ) ) {
	$credits_id = \WBListoraPro\Features\Pricing_Plans::ensure_credits_page();
	// Keep both option keys in sync (legacy _id vs current).
	update_option( 'wb_listora_pro_credits_page', (int) $credits_id );
	update_option( 'wb_listora_pro_credits_page_id', (int) $credits_id );
	// Free override opens member credit surfaces without a live payment gateway
	// (QA / offline path). Points at the Buy Credits page we just ensured.
	if ( $credits_id ) {
		update_option( 'wb_listora_credit_purchase_url', (int) $credits_id );
		foreach ( $packs as &$pack_row ) {
			if ( empty( $pack_row['url'] ) ) {
				$pack_row['url'] = (string) get_permalink( $credits_id );
			}
		}
		unset( $pack_row );
		update_option( 'wb_listora_pro_credit_packs', $packs );
	}
	$report['credits_page_id'] = (int) $credits_id;
	$report['credits_page_url'] = $credits_id ? get_permalink( $credits_id ) : '';
}

// ── 4. Re-run Pro QA pack (idempotent) after toggles ─────────────────
if ( defined( 'WB_LISTORA_PRO_DIR' ) ) {
	require_once WB_LISTORA_PRO_DIR . 'demo/pro-pack.php';
	$counts = \WBListoraPro\Demo\seed_pro_pack( array( 'skip_images' => true ) );
	$report['pro_seed'] = $counts;
}

// ── 5. Showcase pages (blocks site owners expect on a real directory) ─
function listora_qa_ensure_page( $slug, $title, $content ) {
	$existing = get_page_by_path( $slug );
	if ( $existing ) {
		// Refresh content if block missing.
		if ( ! has_block( 'listora/', $existing ) && ! has_block( 'listora-pro/', $existing ) ) {
			wp_update_post(
				array(
					'ID'           => $existing->ID,
					'post_content' => $content,
					'post_status'  => 'publish',
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

$pages = array(
	'browse-categories' => array(
		'Browse Categories',
		"<!-- wp:heading {\"level\":1} --><h1>Browse Categories</h1><!-- /wp:heading -->\n\n<!-- wp:listora/listing-categories /-->",
	),
	'featured-listings' => array(
		'Featured Listings',
		"<!-- wp:heading {\"level\":1} --><h1>Featured Listings</h1><!-- /wp:heading -->\n\n<!-- wp:listora/listing-featured /-->",
	),
	'events-calendar'   => array(
		'Events Calendar',
		"<!-- wp:heading {\"level\":1} --><h1>Events Calendar</h1><!-- /wp:heading -->\n\n<!-- wp:listora/listing-calendar /-->",
	),
	'browse-needs'      => array(
		'Browse Needs',
		"<!-- wp:heading {\"level\":1} --><h1>Browse Needs</h1><!-- /wp:heading -->\n\n<!-- wp:listora-pro/needs-grid /-->",
	),
	'post-a-need'       => array(
		'Post a Need',
		"<!-- wp:heading {\"level\":1} --><h1>Post a Need</h1><!-- /wp:heading -->\n\n<!-- wp:listora-pro/post-need /-->",
	),
);

$report['showcase_pages'] = array();
foreach ( $pages as $slug => $pair ) {
	$id = listora_qa_ensure_page( $slug, $pair[0], $pair[1] );
	$report['showcase_pages'][ $slug ] = array(
		'id'  => $id,
		'url' => $id ? get_permalink( $id ) : '',
	);
}

// ── 6. Seed reverse-listing needs + responses ────────────────────────
$need_specs = array(
	array(
		'title'   => 'Looking for a weekend wedding photographer in Brooklyn',
		'content' => 'Need 6–8 hours coverage for an outdoor ceremony in Prospect Park. Prefer someone with event portfolio.',
		'type'    => 'place',
		'budget'  => array( 800, 1500 ),
		'urgency' => 'normal',
	),
	array(
		'title'   => 'Seeking Italian restaurant catering for 40 guests',
		'content' => 'Office dinner next month. Family-style pasta + salad. Delivery to Midtown.',
		'type'    => 'restaurant',
		'budget'  => array( 600, 1200 ),
		'urgency' => 'urgent',
	),
	array(
		'title'   => 'Need a short-term hotel block for a conference (12 rooms)',
		'content' => 'Three nights near JFK or Downtown Brooklyn. Corporate rate preferred.',
		'type'    => 'hotel',
		'budget'  => array( 2000, 4500 ),
		'urgency' => 'normal',
	),
	array(
		'title'   => 'Hiring a part-time barista with latte-art skills',
		'content' => 'Weekend shifts only. Must have NYC food-handler card.',
		'type'    => 'job',
		'budget'  => array( 18, 25 ),
		'urgency' => 'normal',
	),
	array(
		'title'   => 'Looking for a family physician accepting new patients',
		'content' => 'Prefer Upper West Side. Telehealth follow-ups OK.',
		'type'    => 'healthcare',
		'budget'  => array( 0, 0 ),
		'urgency' => 'normal',
	),
	array(
		'title'   => 'Need a 2BR apartment near a subway (under $3500)',
		'content' => 'Move-in within 45 days. Pets OK preferred.',
		'type'    => 'real-estate',
		'budget'  => array( 2500, 3500 ),
		'urgency' => 'urgent',
	),
);

$users = get_users( array( 'number' => 5, 'orderby' => 'ID', 'order' => 'ASC' ) );
$user_ids = wp_list_pluck( $users, 'ID' );
if ( empty( $user_ids ) ) {
	$user_ids = array( 1 );
}

$needs_created = 0;
$need_ids      = array();
foreach ( $need_specs as $i => $spec ) {
	$existing = get_posts(
		array(
			'post_type'      => 'listora_need',
			'title'          => $spec['title'],
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		)
	);
	if ( ! empty( $existing ) ) {
		$need_ids[] = (int) $existing[0];
		continue;
	}
	$author = (int) $user_ids[ $i % count( $user_ids ) ];
	$nid    = wp_insert_post(
		array(
			'post_type'    => 'listora_need',
			'post_title'   => $spec['title'],
			'post_content' => $spec['content'],
			'post_status'  => 'publish',
			'post_author'  => $author,
		),
		true
	);
	if ( ! $nid || is_wp_error( $nid ) ) {
		continue;
	}
	update_post_meta( $nid, '_listora_need_status', 'open' );
	update_post_meta( $nid, '_listora_need_listing_type', $spec['type'] );
	update_post_meta( $nid, '_listora_need_budget_min', $spec['budget'][0] );
	update_post_meta( $nid, '_listora_need_budget_max', $spec['budget'][1] );
	update_post_meta( $nid, '_listora_need_currency', 'USD' );
	update_post_meta( $nid, '_listora_need_urgency', $spec['urgency'] );
	update_post_meta( $nid, '_listora_demo_content', '1' );
	$need_ids[] = (int) $nid;
	++$needs_created;
}
$report['needs_created'] = $needs_created;
$report['needs_total']   = count( $need_ids );

// Attach a few vendor responses if helper exists.
$responses = 0;
if ( class_exists( '\WBListoraPro\Demo\Pro_Demo_Seeder' ) && count( $need_ids ) >= 3 ) {
	$vendors = array_values( array_filter( $user_ids, function ( $id ) {
		return (int) $id !== 1;
	} ) );
	if ( empty( $vendors ) ) {
		$vendors = $user_ids;
	}
	$specs = array(
		array( 'Happy to help — portfolio attached on request.', 950, 'pending' ),
		array( 'We can do family-style for 40; quote includes delivery.', 1100, 'pending' ),
		array( 'Corporate rate available for your dates.', 3200, 'accepted' ),
	);
	foreach ( $specs as $i => $s ) {
		if ( ! isset( $need_ids[ $i ] ) ) {
			break;
		}
		$vid = (int) $vendors[ $i % count( $vendors ) ];
		if ( \WBListoraPro\Demo\Pro_Demo_Seeder::seed_need_response( $need_ids[ $i ], $vid, $s[0], (float) $s[1], $s[2] ) ) {
			++$responses;
		}
	}
}
$report['need_responses_seeded'] = $responses;

// ── 7. Manual custom badge for discovery bug repro ───────────────────
$badge_id = 0;
$badge_q  = get_posts(
	array(
		'post_type'      => 'listora_badge',
		'title'          => "Editor's Pick",
		'posts_per_page' => 1,
		'post_status'    => 'any',
		'fields'         => 'ids',
	)
);
if ( ! empty( $badge_q ) ) {
	$badge_id = (int) $badge_q[0];
} else {
	$badge_id = wp_insert_post(
		array(
			'post_type'   => 'listora_badge',
			'post_title'  => "Editor's Pick",
			'post_status' => 'publish',
			'post_author' => 1,
		),
		true
	);
	if ( $badge_id && ! is_wp_error( $badge_id ) ) {
		update_post_meta( $badge_id, '_listora_badge_label', "Editor's Pick" );
		update_post_meta( $badge_id, '_listora_badge_color', '#7C3AED' );
		update_post_meta( $badge_id, '_listora_badge_text_color', '#FFFFFF' );
		update_post_meta( $badge_id, '_listora_badge_icon', 'award' );
		update_post_meta( $badge_id, '_listora_badge_assignment', 'manual' );
		update_post_meta( $badge_id, '_listora_demo_content', '1' );
	} else {
		$badge_id = 0;
	}
}

$listing = get_posts(
	array(
		'post_type'      => 'listora_listing',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'name'           => 'central-park-the-mall-bethesda-terrace',
		'fields'         => 'ids',
	)
);
if ( empty( $listing ) ) {
	$listing = get_posts(
		array(
			'post_type'      => 'listora_listing',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
}
$listing_id = ! empty( $listing ) ? (int) $listing[0] : 0;
if ( $badge_id && $listing_id ) {
	update_post_meta( $listing_id, '_listora_manual_badges', wp_json_encode( array( $badge_id ) ) );
	// Also mark verified for verification surface.
	update_post_meta( $listing_id, '_listora_is_verified', '1' );
}
$report['editors_pick_badge_id'] = $badge_id;
$report['badge_assigned_listing'] = $listing_id;
$report['badge_listing_url'] = $listing_id ? get_permalink( $listing_id ) : '';

// ── 8. Top up admin + QA users so monetization CTAs are usable ───────
$report['admin_credits_added'] = array();
if ( class_exists( '\Wbcom\Credits\Credits' ) ) {
	$credit_users = array( 1 );
	foreach ( get_users( array( 'number' => 20, 'orderby' => 'ID' ) ) as $u ) {
		if ( false !== strpos( $u->user_login, 'listora' ) || false !== strpos( $u->user_login, 'qa' ) ) {
			$credit_users[] = (int) $u->ID;
		}
	}
	$credit_users = array_values( array_unique( array_map( 'intval', $credit_users ) ) );
	foreach ( $credit_users as $uid ) {
		$ok = \Wbcom\Credits\Credits::award( 'wb-listora', $uid, 500, 'QA site-owner seed' );
		$report['admin_credits_added'][] = array(
			'uid'     => $uid,
			'ok'      => (bool) $ok,
			'balance' => \Wbcom\Credits\Credits::balance_money( 'wb-listora', $uid ),
		);
	}
} else {
	$report['admin_credits_added'] = 'Credits SDK unavailable';
}

// ── 9. Flush rewrites (needs CPT, SEO pages) ─────────────────────────
flush_rewrite_rules( false );
$report['rewrites_flushed'] = true;

// ── 10. Add showcase links to primary menu if present ────────────────
$locations = get_nav_menu_locations();
$menu_id   = $locations['menu-1'] ?? ( $locations['primary'] ?? 0 );
if ( ! $menu_id ) {
	$menus = wp_get_nav_menus();
	if ( ! empty( $menus ) ) {
		$menu_id = (int) $menus[0]->term_id;
	}
}
$report['menu_id'] = $menu_id;
if ( $menu_id ) {
	$wanted = array(
		'featured-listings' => 'Featured',
		'browse-categories' => 'Categories',
		'events-calendar'   => 'Calendar',
		'buy-credits'       => 'Buy Credits',
		'browse-needs'      => 'Needs',
		'compare-listings'  => 'Compare',
	);
	$existing_urls = array();
	foreach ( wp_get_nav_menu_items( $menu_id ) ?: array() as $item ) {
		$existing_urls[] = untrailingslashit( (string) $item->url );
	}
	$added = 0;
	foreach ( $wanted as $slug => $label ) {
		$page = get_page_by_path( $slug );
		if ( ! $page ) {
			continue;
		}
		$url = untrailingslashit( get_permalink( $page ) );
		if ( in_array( $url, $existing_urls, true ) ) {
			continue;
		}
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $label,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page->ID,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		++$added;
	}
	$report['menu_items_added'] = $added;
}

$report['purchase_path'] = (bool) apply_filters( 'wb_listora_has_credit_purchase_path', false );
$report['show_credits']  = function_exists( 'wb_listora_should_show_member_credits' )
	? (bool) wb_listora_should_show_member_credits()
	: null;

echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
