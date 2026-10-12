<?php
/**
 * User Dashboard - Leads tab: messages the member's listings received.
 *
 * Override: yourtheme/wb-listora/blocks/user-dashboard/tab-leads.php
 *
 * @package WBListora
 *
 * @var string    $tab_id      Tab id ('leads').
 * @var array[]   $leads       Leads on this page (Core\Leads::query() rows).
 * @var int       $total       Leads matching the filter.
 * @var int       $page        Current page; $total_pages pages.
 * @var int       $listing_id  Listing filter, 0 for all.
 * @var WP_Post[] $listings    Listings that received leads, for the filter.
 */

defined( 'ABSPATH' ) || exit;
?>
<div role="tabpanel" id="dash-panel-<?php echo esc_attr( $tab_id ); ?>" aria-labelledby="dash-tab-<?php echo esc_attr( $tab_id ); ?>" class="listora-dashboard__panel listora-dashboard__leads" hidden>

	<?php if ( count( $listings ) > 1 ) : ?>
	<form class="listora-dashboard__filters" method="get" action="<?php echo esc_url( wb_listora_get_dashboard_url() ); ?>" role="search">
		<input type="hidden" name="tab" value="<?php echo esc_attr( $tab_id ); ?>" />
		<label for="listora-lead-listing" class="listora-sr-only"><?php esc_html_e( 'Listing', 'wb-listora' ); ?></label>
		<select id="listora-lead-listing" name="lead_listing" class="listora-input listora-dashboard__filter-select">
			<option value="0"><?php esc_html_e( 'All listings', 'wb-listora' ); ?></option>
			<?php foreach ( $listings as $listora_lead_listing ) : ?>
			<option value="<?php echo esc_attr( (string) $listora_lead_listing->ID ); ?>" <?php selected( $listing_id, $listora_lead_listing->ID ); ?>><?php echo esc_html( get_the_title( $listora_lead_listing ) ); ?></option>
			<?php endforeach; ?>
		</select>
		<button type="submit" class="listora-btn listora-btn--secondary listora-btn--sm"><?php esc_html_e( 'Apply', 'wb-listora' ); ?></button>
		<?php if ( $listing_id ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_id, wb_listora_get_dashboard_url() ) ); ?>" class="listora-btn listora-btn--text listora-btn--sm"><?php esc_html_e( 'Clear', 'wb-listora' ); ?></a>
		<?php endif; ?>
	</form>
	<?php endif; ?>

	<?php if ( empty( $leads ) ) : ?>
		<?php
		wb_listora_render_empty_state(
			array(
				'icon'        => 'message-square',
				'title'       => $listing_id ? __( 'No messages for this listing yet', 'wb-listora' ) : __( 'No messages yet', 'wb-listora' ),
				'description' => __( 'When someone uses the contact form on your listing, their message lands here and in your inbox.', 'wb-listora' ),
			)
		);
		?>
	<?php else : ?>
	<ul class="listora-dashboard__leads-list">
		<?php foreach ( $leads as $lead ) : ?>
		<li class="listora-dashboard__lead-row">
			<div class="listora-dashboard__lead-main">
				<h3 class="listora-dashboard__lead-title"><?php echo esc_html( $lead['name'] ); ?></h3>
				<div class="listora-dashboard__lead-meta">
					<a href="<?php echo esc_url( (string) get_permalink( $lead['listing_id'] ) ); ?>"><?php echo esc_html( $lead['listing_title'] ); ?></a>
					<span class="listora-dashboard__lead-date"><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $lead['created_local'] ) ); ?></span>
				</div>
				<p class="listora-dashboard__lead-message"><?php echo esc_html( $lead['message'] ); ?></p>
			</div>
			<div class="listora-dashboard__lead-actions">
				<a class="listora-btn listora-btn--primary listora-btn--sm" href="<?php echo esc_url( 'mailto:' . $lead['email'] . '?subject=' . rawurlencode( sprintf( /* translators: %s: listing title */ __( 'Re: %s', 'wb-listora' ), $lead['listing_title'] ) ) ); ?>"><?php esc_html_e( 'Reply by email', 'wb-listora' ); ?></a>
				<?php if ( '' !== $lead['phone'] ) : ?>
				<a class="listora-btn listora-btn--secondary listora-btn--sm" href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $lead['phone'] ) ); ?>"><?php echo esc_html( $lead['phone'] ); ?></a>
				<?php endif; ?>
			</div>
		</li>
		<?php endforeach; ?>
	</ul>
		<?php
		wb_listora_render_pagination(
			array(
				'tab'         => $tab_id,
				'page_arg'    => 'leads_page',
				'page'        => $page,
				'total_pages' => $total_pages,
				'total'       => $total,
				'label'       => __( 'Leads pages', 'wb-listora' ),
				'args'        => $listing_id ? array( 'lead_listing' => (string) $listing_id ) : array(),
			)
		);
		?>
	<?php endif; ?>
</div>
