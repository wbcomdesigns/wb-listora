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

do_action( 'wb_listora_before_dashboard_nav', $view_data );
?>
<nav class="listora-dashboard__sidebar" aria-label="<?php esc_attr_e( 'Dashboard navigation', 'wb-listora' ); ?>">
	<div class="listora-dashboard__sidebar-header">
		<p class="listora-dashboard__user-name"><?php echo esc_html( $user->display_name ); ?></p>
		<span class="listora-dashboard__user-email"><?php echo esc_html( $user->user_email ); ?></span>
	</div>

	<?php if ( $show_listings ) : ?>
	<button class="listora-dashboard__nav-item <?php echo esc_attr( 'listings' === $default_tab ? 'is-active' : '' ); ?>"
		data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"listings"}'
		id="dash-tab-listings" role="tab" aria-selected="<?php echo esc_attr( 'listings' === $default_tab ? 'true' : 'false' ); ?>" aria-controls="dash-panel-listings">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
		<?php esc_html_e( 'My Listings', 'wb-listora' ); ?>
		<span class="listora-dashboard__nav-count"><?php echo esc_html( $stat_total ); ?></span>
	</button>
	<?php endif; ?>

	<?php if ( $show_reviews ) : ?>
	<button class="listora-dashboard__nav-item" data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"reviews"}'
		id="dash-tab-reviews" role="tab" aria-selected="false" aria-controls="dash-panel-reviews">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
		<?php esc_html_e( 'Reviews', 'wb-listora' ); ?>
		<span class="listora-dashboard__nav-count"><?php echo esc_html( $review_count ); ?></span>
	</button>
	<?php endif; ?>

	<?php if ( $show_favorites ) : ?>
	<button class="listora-dashboard__nav-item" data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"favorites"}'
		id="dash-tab-favorites" role="tab" aria-selected="false" aria-controls="dash-panel-favorites">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
		<?php esc_html_e( 'Favorites', 'wb-listora' ); ?>
		<span class="listora-dashboard__nav-count"><?php echo esc_html( $favorite_count ); ?></span>
	</button>
	<?php endif; ?>

	<?php if ( ! empty( $show_claims ) ) : ?>
	<button class="listora-dashboard__nav-item <?php echo esc_attr( 'claims' === $default_tab ? 'is-active' : '' ); ?>"
		data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"claims"}'
		id="dash-tab-claims" role="tab"
		aria-selected="<?php echo esc_attr( 'claims' === $default_tab ? 'true' : 'false' ); ?>"
		aria-controls="dash-panel-claims">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 12l2 2 4-4"/><path d="M21 12c0 4.97-4.03 9-9 9s-9-4.03-9-9 4.03-9 9-9c1.86 0 3.59.56 5.03 1.53"/></svg>
		<?php esc_html_e( 'My Claims', 'wb-listora' ); ?>
		<?php if ( ! empty( $pending_claim_count ) ) : ?>
		<span class="listora-dashboard__nav-count listora-dashboard__nav-count--accent"><?php echo esc_html( $pending_claim_count ); ?></span>
		<?php endif; ?>
	</button>
	<?php endif; ?>

	<?php if ( ! empty( $show_credits ) ) : ?>
	<button class="listora-dashboard__nav-item <?php echo esc_attr( 'credits' === $default_tab ? 'is-active' : '' ); ?>"
		data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"credits"}'
		id="dash-tab-credits" role="tab" aria-selected="<?php echo esc_attr( 'credits' === $default_tab ? 'true' : 'false' ); ?>" aria-controls="dash-panel-credits">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>
		<?php esc_html_e( 'Credits', 'wb-listora' ); ?>
		<?php if ( isset( $credit_balance ) ) : ?>
		<span class="listora-dashboard__nav-count"><?php echo esc_html( $credit_balance ); ?></span>
		<?php endif; ?>
	</button>
	<?php endif; ?>

	<?php if ( $show_profile ) : ?>
	<button class="listora-dashboard__nav-item" data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"profile"}'
		id="dash-tab-profile" role="tab" aria-selected="false" aria-controls="dash-panel-profile">
		<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
		<?php esc_html_e( 'Profile', 'wb-listora' ); ?>
	</button>
	<?php endif; ?>

	<?php
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
	?>
</nav>
<?php
do_action( 'wb_listora_after_dashboard_nav', $view_data );
