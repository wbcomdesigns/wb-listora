<?php
/**
 * User Dashboard — Sidebar navigation tabs.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/user-dashboard/nav.php
 *
 * @package WBListora
 *
 * @var object  $user           WP_User object.
 * @var int     $user_id        Current user ID.
 * @var string  $default_tab    Default active tab slug.
 * @var bool    $show_listings  Whether to show listings tab.
 * @var bool    $show_reviews   Whether to show reviews tab.
 * @var bool    $show_favorites Whether to show favorites tab.
 * @var bool    $show_profile   Whether to show profile tab.
 * @var bool    $show_credits   Whether to show credits tab.
 * @var int     $credit_balance Current credit balance (if credits enabled).
 * @var int     $stat_total     Total listings count.
 * @var int     $review_count   Reviews count.
 * @var int     $favorite_count Favorites count.
 * @var array   $view_data      Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

// Same label source the document title uses, so the sidebar and the browser
// tab can never disagree about what a tab is called (BC 10208510032).
$listora_tab_labels = function_exists( 'wb_listora_get_dashboard_tab_labels' )
	? wb_listora_get_dashboard_tab_labels()
	: array();

/**
 * One nav item: a real link to `?tab=`, so it opens in a new tab, can be
 * shared and works before hydration; the click handler switches in place
 * (card 10337190578).
 *
 * @param string $id     Tab id.
 * @param string $label  Fallback label.
 * @param string $icon   Inline SVG.
 * @param string $count  Badge text, '' for none.
 * @param bool   $accent Accent the badge (something needs attention).
 */
$listora_nav_item = static function ( $id, $label, $icon, $count = '', $accent = false ) use ( $default_tab, $listora_tab_labels ) {
	$active = $id === $default_tab;
	$label  = $listora_tab_labels[ $id ] ?? $label;
	?>
	<a class="listora-dashboard__nav-item<?php echo $active ? ' is-active' : ''; ?>"
		href="<?php echo esc_url( add_query_arg( 'tab', $id, wb_listora_get_dashboard_url() ) ); ?>"
		data-wp-on--click="actions.switchDashTab" data-wp-context='<?php echo wp_json_encode( array( 'tabId' => $id ) ); ?>'
		id="dash-tab-<?php echo esc_attr( $id ); ?>" data-listora-tab-label="<?php echo esc_attr( $label ); ?>"
		role="tab" aria-selected="<?php echo $active ? 'true' : 'false'; ?>" aria-controls="dash-panel-<?php echo esc_attr( $id ); ?>">
		<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Inline SVG literal from this template. ?>
		<?php echo esc_html( $label ); ?>
		<?php if ( '' !== (string) $count ) : ?>
		<span class="listora-dashboard__nav-count<?php echo $accent ? ' listora-dashboard__nav-count--accent' : ''; ?>"><?php echo esc_html( (string) $count ); ?></span>
		<?php endif; ?>
	</a>
	<?php
};

/**
 * A titled group. Buffered, so a group nothing lands in (Marketplace on a
 * Free site with favourites off) renders no empty heading.
 *
 * @param string   $group Group id: listings | marketplace | account.
 * @param string   $title Group title.
 * @param callable $items Renders the group's items.
 */
$listora_nav_group = static function ( $group, $title, $items ) use ( $user_id ) {
	ob_start();
	$items();
	/**
	 * Fires inside a dashboard nav group, after Free's own items.
	 *
	 * Pro adds Analytics to `listings` and My Needs, My Responses and Saved
	 * Searches to `marketplace`. Anything hooked to the older
	 * `wb_listora_dashboard_nav_items` action lands in `marketplace`.
	 *
	 * @since 1.9.0
	 *
	 * @param string $group   Group id: 'listings', 'marketplace' or 'account'.
	 * @param int    $user_id Current user ID.
	 */
	do_action( 'wb_listora_dashboard_nav_group', $group, $user_id );
	if ( 'marketplace' === $group ) {
		/**
		 * Fires inside the dashboard sidebar nav, before the closing nav tag.
		 *
		 * Pro hooks in here to add nav buttons for Saved Searches and Analytics panels.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id Current user ID.
		 */
		do_action( 'wb_listora_dashboard_nav_items', $user_id );
	}
	$markup = trim( (string) ob_get_clean() );
	if ( '' === $markup ) {
		return;
	}
	?>
	<div class="listora-dashboard__nav-group" role="group" aria-labelledby="listora-nav-group-<?php echo esc_attr( $group ); ?>">
		<p class="listora-dashboard__nav-group-title" id="listora-nav-group-<?php echo esc_attr( $group ); ?>"><?php echo esc_html( $title ); ?></p>
		<?php echo $markup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Buffered markup already escaped at each of its own output points. ?>
	</div>
	<?php
};

do_action( 'wb_listora_before_dashboard_nav', $view_data );
?>
<nav class="listora-dashboard__sidebar" aria-label="<?php esc_attr_e( 'Dashboard navigation', 'wb-listora' ); ?>" role="tablist" aria-orientation="vertical">
	<div class="listora-dashboard__sidebar-header">
		<p class="listora-dashboard__user-name"><?php echo esc_html( $user->display_name ); ?></p>
		<span class="listora-dashboard__user-email"><?php echo esc_html( $user->user_email ); ?></span>
	</div>

	<?php
	$listora_nav_group(
		'listings',
		__( 'Listings', 'wb-listora' ),
		static function () use ( $listora_nav_item, $show_listings, $show_reviews, $show_claims, $stat_total, $review_count, $pending_claim_count ) {
			$listora_nav_item(
				'overview',
				__( 'Overview', 'wb-listora' ),
				'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>'
			);
			if ( $show_listings ) {
				$listora_nav_item(
					'listings',
					__( 'My Listings', 'wb-listora' ),
					'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
					(string) $stat_total
				);
			}
			if ( $show_reviews ) {
				$listora_nav_item(
					'reviews',
					__( 'Reviews', 'wb-listora' ),
					'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>',
					(string) $review_count
				);
			}
			if ( ! empty( $show_claims ) ) {
				$listora_nav_item(
					'claims',
					__( 'My Claims', 'wb-listora' ),
					'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c1.86 0 3.59.56 5.03 1.53"/></svg>',
					! empty( $pending_claim_count ) ? (string) $pending_claim_count : '',
					true
				);
			}
		}
	);

	$listora_nav_group(
		'marketplace',
		__( 'Marketplace', 'wb-listora' ),
		static function () use ( $listora_nav_item, $show_favorites, $favorite_count ) {
			if ( $show_favorites ) {
				$listora_nav_item(
					'favorites',
					__( 'Favorites', 'wb-listora' ),
					'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
					(string) $favorite_count
				);
			}
		}
	);

	$listora_nav_group(
		'account',
		__( 'Account', 'wb-listora' ),
		static function () use ( $listora_nav_item, $show_credits, $show_profile, $credit_balance ) {
			if ( ! empty( $show_credits ) ) {
				$listora_nav_item(
					'credits',
					__( 'Credits', 'wb-listora' ),
					'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>',
					isset( $credit_balance ) ? wb_listora_format_credits( $credit_balance ) : ''
				);
			}
			if ( $show_profile ) {
				$listora_nav_item(
					'profile',
					__( 'Profile', 'wb-listora' ),
					'<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>'
				);
			}
		}
	);
	?>
</nav>
<?php
do_action( 'wb_listora_after_dashboard_nav', $view_data );
