<?php
/**
 * User Dashboard — My Listings tab content.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/user-dashboard/tab-listings.php
 *
 * @package WBListora
 *
 * @var int    $user_id       Current user ID.
 * @var string $default_tab   Default active tab slug.
 * @var array  $user_listings Array of WP_Post objects for user listings.
 * @var array  $status_map    Status label/class map.
 * @var array  $view_data     Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

$listora_renewal_enabled = (bool) wb_listora_feature_enabled( 'renewal' );
$listora_renewal_window  = (int) wb_listora_get_setting( 'renewal_window_days', 7 );

// ─── Inline ADD / EDIT mode detection ───
// /dashboard/?tab=listings&action=add        → render submission block inline (new)
// /dashboard/?tab=listings&action=edit&id=X  → render submission block inline (edit X)
// phpcs:disable WordPress.Security.NonceVerification.Recommended
$dash_action  = isset( $_GET['action'] ) ? sanitize_key( (string) $_GET['action'] ) : '';
$dash_edit_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
$inline_form_mode = '';
if ( 'add' === $dash_action ) {
	$inline_form_mode = 'add';
} elseif ( 'edit' === $dash_action && $dash_edit_id > 0 ) {
	$inline_form_mode = 'edit';
}

do_action( 'wb_listora_before_dashboard_listings', $view_data );
?>
<div role="tabpanel" id="dash-panel-listings" aria-labelledby="dash-tab-listings" class="listora-dashboard__panel"
	<?php echo 'listings' !== $default_tab ? 'hidden' : ''; ?>>

	<?php if ( $inline_form_mode ) : ?>
	<?php // ─── Inline Add / Edit form — render listora/listing-submission block inline ─── ?>
	<div class="listora-dashboard__inline-form">
		<div class="listora-dashboard__inline-form-head">
			<h3 class="listora-dashboard__section-title">
				<?php echo 'add' === $inline_form_mode
					? esc_html__( 'Add New Listing', 'wb-listora' )
					: esc_html__( 'Edit Listing', 'wb-listora' ); ?>
			</h3>
			<a href="<?php echo esc_url( wb_listora_get_dashboard_url( 'listings' ) ); ?>" class="listora-btn wp-element-button listora-btn--secondary listora-btn--sm">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
				<?php esc_html_e( 'Back to listings', 'wb-listora' ); ?>
			</a>
		</div>
		<?php
		// Render the submission block inline. layoutMode='single-form' is
		// auto-applied in edit mode by render.php; pass it explicitly here
		// for add mode too so the experience is consistent in-dashboard
		// (wizard kept ONLY on the standalone /submit-listing/ page for
		// external visitor / SEO landing journey).
		echo do_blocks(
			'<!-- wp:listora/listing-submission { "layoutMode":"single-form" } /-->'
		);
		?>
	</div>
	<?php else : ?>

	<?php if ( empty( $user_listings ) ) : ?>
	<div class="listora-dashboard__empty">
		<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg>
		<h3><?php esc_html_e( 'No listings yet', 'wb-listora' ); ?></h3>
		<p><?php esc_html_e( 'Create your first listing and start getting discovered.', 'wb-listora' ); ?></p>
		<a href="<?php echo esc_url( wb_listora_get_dashboard_add_url() ); ?>" class="listora-btn wp-element-button listora-btn--primary">
			<?php esc_html_e( 'Add Your First Listing', 'wb-listora' ); ?>
		</a>
	</div>
	<?php else : ?>
	<?php if ( $listora_renewal_enabled ) : ?>
	<div class="listora-dashboard__filters">
		<label for="listora-renewal-filter" class="listora-dashboard__filters-label">
			<?php esc_html_e( 'Filter:', 'wb-listora' ); ?>
		</label>
		<select id="listora-renewal-filter" class="listora-input listora-dashboard__filter-select" data-listora-listing-filter>
			<option value="all"><?php esc_html_e( 'All listings', 'wb-listora' ); ?></option>
			<option value="active"><?php esc_html_e( 'Active', 'wb-listora' ); ?></option>
			<option value="expiring"><?php esc_html_e( 'Expiring soon', 'wb-listora' ); ?></option>
			<option value="expired"><?php esc_html_e( 'Expired', 'wb-listora' ); ?></option>
		</select>
	</div>
	<?php endif; ?>
	<div class="listora-dashboard__listing-list">
		<?php
		foreach ( $user_listings as $row_index => $listing ) :
			$status_info = $status_map[ $listing->post_status ] ?? array(
				'label' => $listing->post_status,
				'class' => 'listora-dashboard__status--draft',
			);
			$thumb_url   = get_the_post_thumbnail_url( $listing->ID, 'thumbnail' );
			$type        = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $listing->ID );

			// Compute renewal eligibility for this row.
			$listora_exp_raw  = (string) get_post_meta( $listing->ID, '_listora_expiration_date', true );
			$listora_exp_ts   = $listora_exp_raw ? (int) strtotime( $listora_exp_raw ) : 0;
			$listora_now_ts   = (int) current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$listora_days_left = $listora_exp_ts > 0 ? (int) ceil( ( $listora_exp_ts - $listora_now_ts ) / DAY_IN_SECONDS ) : 0;
			$listora_is_expired = ( 'listora_expired' === $listing->post_status );
			$listora_is_expiring = ( ! $listora_is_expired && 'publish' === $listing->post_status && $listora_exp_ts > 0 && $listora_days_left <= $listora_renewal_window && $listora_days_left >= 0 );
			$listora_filter_state = $listora_is_expired ? 'expired' : ( $listora_is_expiring ? 'expiring' : 'active' );
			$listora_can_renew = $listora_renewal_enabled && ( $listora_is_expired || $listora_is_expiring );
			?>
		<div class="listora-dashboard__listing-row" data-listora-listing-id="<?php echo (int) $listing->ID; ?>" data-listora-state="<?php echo esc_attr( $listora_filter_state ); ?>" style="--row-index: <?php echo (int) $row_index; ?>">
			<div class="listora-dashboard__listing-thumb">
				<?php if ( $thumb_url ) : ?>
				<img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $listing->post_title ); ?>" loading="lazy" />
				<?php else : ?>
				<div class="listora-dashboard__listing-thumb-placeholder">
					<?php if ( $type ) : ?>
						<?php echo \WBListora\Core\Lucide_Icons::render( $type->get_icon(), 32 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<div class="listora-dashboard__listing-info">
				<h3 class="listora-dashboard__listing-title">
					<a href="<?php echo esc_url( get_permalink( $listing->ID ) ); ?>"><?php echo esc_html( $listing->post_title ); ?></a>
				</h3>
				<?php
				$listora_is_featured    = \WBListora\Core\Featured::is_featured( $listing->ID );
				$listora_featured_until = \WBListora\Core\Featured::get_featured_until( $listing->ID );
				$dash_svc_count         = \WBListora\Core\Services::get_service_count( $listing->ID );
				?>
				<div class="listora-dashboard__listing-meta">
					<?php // Status pill — always rendered. ?>
					<span class="listora-dashboard__status <?php echo esc_attr( $status_info['class'] ); ?>">
						<?php echo esc_html( $status_info['label'] ); ?>
					</span>

					<?php // Type as a neutral badge (was plain text — inconsistent with the
					// surrounding pills). The neutral variant pairs with the status pill
					// without competing visually. ?>
					<?php if ( $type ) : ?>
					<span class="listora-dashboard__type-tag">
						<?php echo esc_html( $type->get_name() ); ?>
					</span>
					<?php endif; ?>

					<?php // Expiration as muted text — secondary information, no pill needed
					// unless expiring soon (which gets its own warning pill below). ?>
					<?php if ( $listora_exp_ts > 0 && 'publish' === $listing->post_status && ! $listora_is_expiring ) : ?>
					<span class="listora-dashboard__listing-expires">
						<?php
						printf(
							/* translators: %s: expiration date */
							esc_html__( 'Expires %s', 'wb-listora' ),
							esc_html( wp_date( get_option( 'date_format' ), $listora_exp_ts ) )
						);
						?>
					</span>
					<?php endif; ?>

					<?php if ( $listora_is_expiring ) : ?>
					<span class="listora-dashboard__status listora-dashboard__status--expiring">
						<?php echo \WBListora\Core\Lucide_Icons::render( 'clock', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
						<?php
						if ( 0 === $listora_days_left ) {
							esc_html_e( 'Expires today', 'wb-listora' );
						} elseif ( 1 === $listora_days_left ) {
							esc_html_e( 'Expires tomorrow', 'wb-listora' );
						} else {
							printf(
								/* translators: %d: days remaining */
								esc_html( _n( 'Expires in %d day', 'Expires in %d days', $listora_days_left, 'wb-listora' ) ),
								(int) $listora_days_left
							);
						}
						?>
					</span>
					<?php endif; ?>

					<?php if ( $listora_is_featured ) : ?>
					<span class="listora-dashboard__featured-tag">
						<?php echo \WBListora\Core\Lucide_Icons::render( 'star', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
						<?php
						if ( 0 === $listora_featured_until ) {
							esc_html_e( 'Featured', 'wb-listora' );
						} else {
							printf(
								/* translators: %s: date listing stays featured until */
								esc_html__( 'Featured until %s', 'wb-listora' ),
								esc_html( wp_date( get_option( 'date_format' ), (int) $listora_featured_until ) )
							);
						}
						?>
					</span>
					<?php endif; ?>

					<?php // Services count as a subtle "N services" tag — was a heavyweight
					// filled button competing with the status pill. The actual "Manage
					// Services" action now lives in the row's actions cluster (right
					// edge) where every other row-level action sits. ?>
					<?php if ( $dash_svc_count > 0 ) : ?>
					<span class="listora-dashboard__services-count">
						<?php echo \WBListora\Core\Lucide_Icons::render( 'wrench', 12 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
						<?php
						/* translators: %d: number of services on this listing */
						printf( esc_html( _n( '%d service', '%d services', $dash_svc_count, 'wb-listora' ) ), (int) $dash_svc_count );
						?>
					</span>
					<?php endif; ?>
				</div>
				<?php if ( 'pending_verification' === $listing->post_status ) : ?>
				<div class="listora-dashboard__verify-note" data-listing-id="<?php echo (int) $listing->ID; ?>">
					<p class="listora-dashboard__verify-message">
						<?php esc_html_e( 'Click the link in your email to publish this listing.', 'wb-listora' ); ?>
					</p>
					<button type="button"
						class="listora-btn wp-element-button listora-btn--secondary listora-dashboard__verify-resend"
						data-listing-id="<?php echo (int) $listing->ID; ?>">
						<?php esc_html_e( 'Resend verification email', 'wb-listora' ); ?>
					</button>
					<span class="listora-dashboard__verify-status" hidden></span>
				</div>
				<?php endif; ?>
				<?php
				// ─── Awaiting-credits recovery row ───
				// Surfaces when Pro's plan activation could not deduct credits
				// (insufficient balance) — the listing is paused in
				// `listora_payment` until the vendor tops up. Credits are the
				// ONLY currency, so the CTA always points at Buy Credits and
				// the listing auto-resumes once the balance reaches the
				// required cost (wired via `wb_listora_pro_credits_added` in
				// Pricing_Plans::auto_resume_pending_listings).
				if ( 'listora_payment' === $listing->post_status ) :
					$pending_plan_id    = (int) get_post_meta( $listing->ID, '_listora_pending_plan_id', true );
					$pending_failure    = get_post_meta( $listing->ID, '_listora_pending_plan_failure', true );
					$pending_plan_name  = '';
					$pending_plan_cost  = 0;
					if ( $pending_plan_id > 0 ) {
						$pending_plan_post = get_post( $pending_plan_id );
						if ( $pending_plan_post && 'listora_plan' === $pending_plan_post->post_type ) {
							$pending_plan_name = $pending_plan_post->post_title;
							// Canonical plan-cost meta after the duplicate
							// _listora_plan_credit_cost was retired in Pro 1.5.0.
							$pending_plan_cost = (int) get_post_meta( $pending_plan_id, '_listora_plan_credits', true );
						}
					}
					$current_balance = isset( $credit_balance ) ? (int) $credit_balance : 0;
					$credits_short   = max( 0, $pending_plan_cost - $current_balance );
					?>
				<div class="listora-dashboard__paused-note" data-listing-id="<?php echo (int) $listing->ID; ?>">
					<div class="listora-dashboard__paused-head">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="10" y1="9" x2="10" y2="15"/><line x1="14" y1="9" x2="14" y2="15"/></svg>
						<strong class="listora-dashboard__paused-title">
							<?php esc_html_e( 'Paused — credits needed to activate', 'wb-listora' ); ?>
						</strong>
					</div>
					<?php if ( $pending_plan_name && $pending_plan_cost > 0 ) : ?>
					<p class="listora-dashboard__paused-message">
						<?php
						printf(
							/* translators: 1: plan name, 2: plan cost in credits */
							esc_html__( 'Plan: %1$s — costs %2$s credits to activate.', 'wb-listora' ),
							'<strong>' . esc_html( $pending_plan_name ) . '</strong>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							'<strong>' . esc_html( number_format_i18n( $pending_plan_cost ) ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						);
						?>
					</p>
					<?php endif; ?>
					<div class="listora-dashboard__paused-credits">
						<span class="listora-dashboard__paused-credits-row">
							<span class="listora-dashboard__paused-credits-label"><?php esc_html_e( 'Your balance:', 'wb-listora' ); ?></span>
							<span class="listora-dashboard__paused-credits-value"><?php echo esc_html( number_format_i18n( $current_balance ) ); ?></span>
						</span>
						<?php if ( $credits_short > 0 ) : ?>
						<span class="listora-dashboard__paused-credits-row listora-dashboard__paused-credits-row--short">
							<span class="listora-dashboard__paused-credits-label"><?php esc_html_e( 'Short by:', 'wb-listora' ); ?></span>
							<span class="listora-dashboard__paused-credits-value"><?php echo esc_html( number_format_i18n( $credits_short ) ); ?></span>
						</span>
						<?php endif; ?>
					</div>
					<p class="listora-dashboard__paused-explainer">
						<?php esc_html_e( 'Top up credits and this listing activates automatically — no further action needed. There is no separate payment for plans; credits are the only currency.', 'wb-listora' ); ?>
					</p>
					<?php if ( ! empty( $show_credits ) ) : ?>
					<a href="<?php echo esc_url( wb_listora_get_dashboard_url( 'credits' ) ); ?>" class="listora-btn wp-element-button listora-btn--secondary listora-btn--sm listora-dashboard__paused-cta">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
						<?php
						echo esc_html(
							$credits_short > 0
								/* translators: %s: credits needed */
								? sprintf( __( 'Buy %s credits to resume', 'wb-listora' ), number_format_i18n( $credits_short ) )
								: __( 'Buy credits', 'wb-listora' )
						);
						?>
					</a>
					<?php endif; ?>
					<?php if ( is_array( $pending_failure ) && ! empty( $pending_failure['message'] ) ) : ?>
					<details class="listora-dashboard__paused-details">
						<summary><?php esc_html_e( 'Why was this paused?', 'wb-listora' ); ?></summary>
						<p class="listora-dashboard__paused-details-body">
							<?php echo esc_html( (string) $pending_failure['message'] ); ?>
						</p>
					</details>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>
			<div class="listora-dashboard__listing-actions">
				<?php if ( $listora_can_renew ) : ?>
				<button type="button"
					class="listora-btn wp-element-button listora-btn--primary listora-btn--sm listora-dashboard__renew-btn"
					data-listora-renew-listing="<?php echo (int) $listing->ID; ?>"
					data-listing-title="<?php echo esc_attr( $listing->post_title ); ?>">
					<?php echo \WBListora\Core\Lucide_Icons::render( 'refresh-cw', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
					<?php esc_html_e( 'Renew Now', 'wb-listora' ); ?>
				</button>
				<?php endif; ?>
				<?php // Manage Services — opens the inline services CRUD panel for
				// this listing. Lives in the row actions cluster so it matches
				// the visual weight of Edit / View / More icons. The services
				// count itself surfaces in the meta cluster above. ?>
				<button type="button"
					class="listora-btn wp-element-button listora-btn--icon listora-dashboard__services-toggle"
					data-wp-on--click="actions.toggleDashServices"
					data-wp-context='<?php echo wp_json_encode( array( 'servicesListingId' => $listing->ID ) ); ?>'
					aria-label="<?php esc_attr_e( 'Manage services', 'wb-listora' ); ?>">
					<?php echo \WBListora\Core\Lucide_Icons::render( 'wrench', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
				</button>
				<a href="<?php echo esc_url( wb_listora_get_dashboard_edit_url( $listing->ID ) ); ?>" class="listora-btn wp-element-button listora-btn--icon" aria-label="<?php esc_attr_e( 'Edit', 'wb-listora' ); ?>">
					<?php echo \WBListora\Core\Lucide_Icons::render( 'pencil-line', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
				</a>
				<a href="<?php echo esc_url( get_permalink( $listing->ID ) ); ?>" class="listora-btn wp-element-button listora-btn--icon" aria-label="<?php esc_attr_e( 'View', 'wb-listora' ); ?>">
					<?php echo \WBListora\Core\Lucide_Icons::render( 'eye', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
				</a>
				<div class="listora-dashboard__menu-wrap" data-wp-interactive="listora/directory">
					<button type="button" class="listora-btn wp-element-button listora-btn--icon" data-wp-on--click="actions.toggleListingMenu" aria-label="<?php esc_attr_e( 'More actions', 'wb-listora' ); ?>">
						<?php echo \WBListora\Core\Lucide_Icons::render( 'more-vertical', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Lucide_Icons::render emits a controlled SVG literal. ?>
					</button>
					<div class="listora-dashboard__menu-dropdown" hidden>
						<?php if ( $listora_can_renew ) : ?>
						<button type="button" class="listora-dashboard__menu-item wp-element-button" data-listora-renew-listing="<?php echo (int) $listing->ID; ?>" data-listing-title="<?php echo esc_attr( $listing->post_title ); ?>">
							<?php esc_html_e( 'Renew', 'wb-listora' ); ?>
						</button>
						<?php endif; ?>
						<?php if ( 'listora_deactivated' === $listing->post_status ) : ?>
						<button class="listora-dashboard__menu-item wp-element-button"
							data-wp-on--click="actions.reactivateListing"
							data-wp-context='<?php echo wp_json_encode( array( 'listingId' => $listing->ID ) ); ?>'>
							<?php esc_html_e( 'Reactivate', 'wb-listora' ); ?>
						</button>
						<?php elseif ( 'publish' === $listing->post_status ) : ?>
						<button class="listora-dashboard__menu-item wp-element-button listora-dashboard__menu-item--danger"
							data-wp-on--click="actions.deactivateListing"
							data-wp-context='<?php echo wp_json_encode( array( 'listingId' => $listing->ID ) ); ?>'>
							<?php esc_html_e( 'Deactivate', 'wb-listora' ); ?>
						</button>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>

		<?php // Inline Services Management per listing. ?>
		<?php
		foreach ( $user_listings as $svc_listing ) :
			$svc_panel_id = 'services-panel-' . $svc_listing->ID;
			?>
		<div class="listora-dashboard__services-panel" id="<?php echo esc_attr( $svc_panel_id ); ?>" data-listing-id="<?php echo (int) $svc_listing->ID; ?>" hidden>
			<div class="listora-dashboard__services-header">
				<h4>
					<?php
					printf(
						/* translators: %s: listing title */
						esc_html__( 'Services for "%s"', 'wb-listora' ),
						esc_html( $svc_listing->post_title )
					);
					?>
				</h4>
				<button type="button" class="listora-btn wp-element-button listora-btn--secondary listora-btn--sm listora-dashboard__add-service-btn"
					data-wp-on--click="actions.toggleServiceForm"
					data-wp-context='<?php echo wp_json_encode( array( 'serviceListingId' => $svc_listing->ID ) ); ?>'>
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
					<?php esc_html_e( 'Add Service', 'wb-listora' ); ?>
				</button>
			</div>

			<?php // Add Service Form. ?>
			<div class="listora-dashboard__service-form" data-listing-id="<?php echo (int) $svc_listing->ID; ?>" hidden>
				<div class="listora-dashboard__service-form-grid">
					<div class="listora-submission__field">
						<label for="listora-service-title-<?php echo (int) $svc_listing->ID; ?>" class="listora-submission__label"><?php esc_html_e( 'Service Name', 'wb-listora' ); ?> <span class="required">*</span></label>
						<input type="text" id="listora-service-title-<?php echo (int) $svc_listing->ID; ?>" name="service_title" class="listora-input" required placeholder="<?php esc_attr_e( 'e.g., Teeth Cleaning', 'wb-listora' ); ?>" />
					</div>
					<div class="listora-submission__field listora-submission__field--full">
						<label for="listora-service-desc-<?php echo (int) $svc_listing->ID; ?>" class="listora-submission__label"><?php esc_html_e( 'Description', 'wb-listora' ); ?></label>
						<textarea id="listora-service-desc-<?php echo (int) $svc_listing->ID; ?>" name="service_description" class="listora-input listora-submission__textarea" rows="3" placeholder="<?php esc_attr_e( 'Describe this service...', 'wb-listora' ); ?>"></textarea>
					</div>
					<div class="listora-submission__field">
						<label for="listora-service-price-<?php echo (int) $svc_listing->ID; ?>" class="listora-submission__label"><?php esc_html_e( 'Price', 'wb-listora' ); ?></label>
						<input type="number" id="listora-service-price-<?php echo (int) $svc_listing->ID; ?>" name="service_price" class="listora-input" step="0.01" min="0" placeholder="0.00" />
					</div>
					<div class="listora-submission__field">
						<label for="listora-service-pricetype-<?php echo (int) $svc_listing->ID; ?>" class="listora-submission__label"><?php esc_html_e( 'Price Type', 'wb-listora' ); ?></label>
						<select id="listora-service-pricetype-<?php echo (int) $svc_listing->ID; ?>" name="service_price_type" class="listora-input">
							<option value="fixed"><?php esc_html_e( 'Fixed', 'wb-listora' ); ?></option>
							<option value="starting_from"><?php esc_html_e( 'Starting From', 'wb-listora' ); ?></option>
							<option value="hourly"><?php esc_html_e( 'Hourly', 'wb-listora' ); ?></option>
							<option value="free"><?php esc_html_e( 'Free', 'wb-listora' ); ?></option>
							<option value="contact"><?php esc_html_e( 'Contact for Price', 'wb-listora' ); ?></option>
						</select>
					</div>
					<div class="listora-submission__field">
						<label for="listora-service-duration-<?php echo (int) $svc_listing->ID; ?>" class="listora-submission__label"><?php esc_html_e( 'Duration (minutes)', 'wb-listora' ); ?></label>
						<input type="number" id="listora-service-duration-<?php echo (int) $svc_listing->ID; ?>" name="service_duration" class="listora-input" min="0" placeholder="30" />
					</div>
					<div class="listora-submission__field">
						<label for="listora-service-cat-<?php echo (int) $svc_listing->ID; ?>" class="listora-submission__label"><?php esc_html_e( 'Category', 'wb-listora' ); ?></label>
						<select id="listora-service-cat-<?php echo (int) $svc_listing->ID; ?>" name="service_category" class="listora-input">
							<option value=""><?php esc_html_e( 'Select a category', 'wb-listora' ); ?></option>
							<?php
							$svc_cats = get_terms(
								array(
									'taxonomy'   => 'listora_service_cat',
									'hide_empty' => false,
								)
							);
							if ( ! is_wp_error( $svc_cats ) ) :
								foreach ( $svc_cats as $svc_cat ) :
									?>
							<option value="<?php echo (int) $svc_cat->term_id; ?>"><?php echo esc_html( $svc_cat->name ); ?></option>
									<?php
								endforeach;
							endif;
							?>
						</select>
					</div>
				</div>
				<div class="listora-dashboard__service-form-actions">
					<button type="button" class="listora-btn wp-element-button listora-btn--primary listora-btn--sm" data-wp-on--click="actions.saveService">
						<?php esc_html_e( 'Save Service', 'wb-listora' ); ?>
					</button>
					<button type="button" class="listora-btn wp-element-button listora-btn--text listora-btn--sm" data-wp-on--click="actions.toggleServiceForm">
						<?php esc_html_e( 'Cancel', 'wb-listora' ); ?>
					</button>
				</div>
			</div>

			<?php
			$dash_services = \WBListora\Core\Services::get_services( $svc_listing->ID );
			if ( ! empty( $dash_services ) ) :
				?>
			<div class="listora-dashboard__services-list">
				<?php foreach ( $dash_services as $dash_svc ) : ?>
				<div class="listora-dashboard__service-row" data-service-id="<?php echo (int) $dash_svc['id']; ?>">
					<span class="listora-dashboard__service-drag" aria-hidden="true">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
					</span>
					<?php
					$dash_svc_img = '';
					if ( ! empty( $dash_svc['image_id'] ) ) {
						$dash_svc_img = wp_get_attachment_image_url( (int) $dash_svc['image_id'], 'thumbnail' );
					}
					?>
					<?php if ( $dash_svc_img ) : ?>
					<img src="<?php echo esc_url( $dash_svc_img ); ?>" alt="<?php echo esc_attr( $dash_svc['title'] ); ?>" class="listora-dashboard__service-thumb" width="40" height="40" loading="lazy" />
					<?php endif; ?>
					<span class="listora-dashboard__service-title"><?php echo esc_html( $dash_svc['title'] ); ?></span>
					<?php if ( null !== $dash_svc['price'] && '' !== $dash_svc['price'] ) : ?>
					<span class="listora-dashboard__service-price">$<?php echo esc_html( number_format( (float) $dash_svc['price'], 2 ) ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $dash_svc['duration_minutes'] ) ) : ?>
					<span class="listora-dashboard__service-duration">
						<?php
						$dh = floor( (int) $dash_svc['duration_minutes'] / 60 );
						$dm = (int) $dash_svc['duration_minutes'] % 60;
						if ( $dh > 0 && $dm > 0 ) {
							/* translators: 1: hours, 2: minutes */
							printf( esc_html__( '%1$dh %2$dm', 'wb-listora' ), (int) $dh, (int) $dm );
						} elseif ( $dh > 0 ) {
							/* translators: %d: hours */
							printf( esc_html__( '%dh', 'wb-listora' ), (int) $dh );
						} else {
							/* translators: %d: minutes */
							printf( esc_html__( '%dm', 'wb-listora' ), (int) $dm );
						}
						?>
					</span>
					<?php endif; ?>
					<div class="listora-dashboard__service-actions">
						<button type="button" class="listora-btn wp-element-button listora-btn--icon" data-wp-on--click="actions.editService"
							data-wp-context='<?php echo wp_json_encode( array( 'serviceId' => (int) $dash_svc['id'] ) ); ?>'
							aria-label="<?php esc_attr_e( 'Edit', 'wb-listora' ); ?>">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
						</button>
						<button type="button" class="listora-btn wp-element-button listora-btn--icon listora-dashboard__menu-item--danger" data-wp-on--click="actions.deleteService"
							data-wp-context='<?php echo wp_json_encode( array( 'serviceId' => (int) $dash_svc['id'] ) ); ?>'
							aria-label="<?php esc_attr_e( 'Delete', 'wb-listora' ); ?>">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
						</button>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php else : ?>
			<div class="listora-dashboard__services-empty">
				<p><?php esc_html_e( 'No services added yet. Click "Add Service" to get started.', 'wb-listora' ); ?></p>
			</div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>

	</div>
	<?php endif; ?>

	<?php endif; /* inline_form_mode */ ?>

	<?php // Renewal confirm modal (shared, hidden by default). ?>
	<div class="listora-dashboard__renew-modal" data-listora-renew-modal hidden role="dialog" aria-modal="true" aria-labelledby="listora-renew-modal-title">
		<div class="listora-dashboard__renew-modal-backdrop" data-listora-renew-close></div>
		<div class="listora-dashboard__renew-modal-panel">
			<button type="button" class="listora-dashboard__renew-modal-close wp-element-button" data-listora-renew-close aria-label="<?php esc_attr_e( 'Close', 'wb-listora' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
			<h3 id="listora-renew-modal-title" class="listora-dashboard__renew-modal-title">
				<?php esc_html_e( 'Renew listing', 'wb-listora' ); ?>
			</h3>
			<div class="listora-dashboard__renew-modal-body">
				<p class="listora-dashboard__renew-modal-listing"></p>
				<dl class="listora-dashboard__renew-modal-grid">
					<dt><?php esc_html_e( 'Plan', 'wb-listora' ); ?></dt>
					<dd data-listora-renew-plan>—</dd>
					<dt><?php esc_html_e( 'Cost', 'wb-listora' ); ?></dt>
					<dd data-listora-renew-cost>—</dd>
					<dt><?php esc_html_e( 'Duration', 'wb-listora' ); ?></dt>
					<dd data-listora-renew-duration>—</dd>
					<dt><?php esc_html_e( 'Your balance', 'wb-listora' ); ?></dt>
					<dd data-listora-renew-balance>—</dd>
				</dl>
				<p class="listora-dashboard__renew-modal-error" data-listora-renew-error hidden></p>
			</div>
			<div class="listora-dashboard__renew-modal-actions">
				<button type="button" class="listora-btn wp-element-button listora-btn--secondary" data-listora-renew-close>
					<?php esc_html_e( 'Cancel', 'wb-listora' ); ?>
				</button>
				<a href="#" class="listora-btn wp-element-button listora-btn--secondary" data-listora-renew-buy hidden>
					<?php esc_html_e( 'Buy more credits', 'wb-listora' ); ?>
				</a>
				<button type="button" class="listora-btn wp-element-button listora-btn--primary" data-listora-renew-confirm>
					<?php esc_html_e( 'Confirm renewal', 'wb-listora' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div class="listora-dashboard__toast-stack" data-listora-toast-stack aria-live="polite" aria-atomic="true"></div>
</div>
<?php
do_action( 'wb_listora_after_dashboard_listings', $view_data );
