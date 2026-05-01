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

do_action( 'wb_listora_before_dashboard_listings', $view_data );
?>
<div role="tabpanel" id="dash-panel-listings" aria-labelledby="dash-tab-listings" class="listora-dashboard__panel"
	<?php echo 'listings' !== $default_tab ? 'hidden' : ''; ?>>

	<?php if ( empty( $user_listings ) ) : ?>
	<div class="listora-dashboard__empty">
		<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg>
		<h3><?php esc_html_e( 'No listings yet', 'wb-listora' ); ?></h3>
		<p><?php esc_html_e( 'Create your first listing and start getting discovered.', 'wb-listora' ); ?></p>
		<a href="<?php echo esc_url( wb_listora_get_submit_url() ); ?>" class="listora-btn listora-btn--primary">
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
				<div class="listora-dashboard__listing-meta">
					<span class="listora-dashboard__status <?php echo esc_attr( $status_info['class'] ); ?>">
						<?php echo esc_html( $status_info['label'] ); ?>
					</span>
					<?php if ( $type ) : ?>
					<span><?php echo esc_html( $type->get_name() ); ?></span>
					<?php endif; ?>
					<?php if ( $listora_exp_ts > 0 && 'publish' === $listing->post_status && ! $listora_is_expiring ) : ?>
					<span>
						<?php
						printf(
							/* translators: %s: expiration date */
							esc_html__( 'Expires: %s', 'wb-listora' ),
							esc_html( wp_date( get_option( 'date_format' ), $listora_exp_ts ) )
						);
						?>
					</span>
					<?php endif; ?>
					<?php if ( $listora_is_expiring ) : ?>
					<span class="listora-dashboard__status listora-dashboard__status--expiring">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
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
					<?php
					$listora_is_featured    = \WBListora\Core\Featured::is_featured( $listing->ID );
					$listora_featured_until = \WBListora\Core\Featured::get_featured_until( $listing->ID );
					if ( $listora_is_featured ) :
						?>
					<span class="listora-dashboard__featured-tag">
						<svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						<?php
						if ( 0 === $listora_featured_until ) {
							esc_html_e( 'Featured (permanent)', 'wb-listora' );
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
					<?php $dash_svc_count = \WBListora\Core\Services::get_service_count( $listing->ID ); ?>
					<button type="button" class="listora-dashboard__services-link" data-wp-on--click="actions.toggleDashServices"
						data-wp-context='<?php echo wp_json_encode( array( 'servicesListingId' => $listing->ID ) ); ?>'>
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
						<?php
						printf(
							/* translators: %d: number of services */
							esc_html( _n( 'Manage Services (%d)', 'Manage Services (%d)', $dash_svc_count, 'wb-listora' ) ),
							(int) $dash_svc_count
						);
						?>
					</button>
				</div>
				<?php if ( 'pending_verification' === $listing->post_status ) : ?>
				<div class="listora-dashboard__verify-note" data-listing-id="<?php echo (int) $listing->ID; ?>">
					<p class="listora-dashboard__verify-message">
						<?php esc_html_e( 'Click the link in your email to publish this listing.', 'wb-listora' ); ?>
					</p>
					<button type="button"
						class="listora-btn listora-btn--secondary listora-dashboard__verify-resend"
						data-listing-id="<?php echo (int) $listing->ID; ?>">
						<?php esc_html_e( 'Resend verification email', 'wb-listora' ); ?>
					</button>
					<span class="listora-dashboard__verify-status" hidden></span>
				</div>
				<?php endif; ?>
			</div>
			<div class="listora-dashboard__listing-actions">
				<?php if ( $listora_can_renew ) : ?>
				<button type="button"
					class="listora-btn listora-btn--primary listora-btn--sm listora-dashboard__renew-btn"
					data-listora-renew-listing="<?php echo (int) $listing->ID; ?>"
					data-listing-title="<?php echo esc_attr( $listing->post_title ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 12a9 9 0 0 1 15.6-6.4L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.6 6.4L3 16"/><path d="M3 21v-5h5"/></svg>
					<?php esc_html_e( 'Renew Now', 'wb-listora' ); ?>
				</button>
				<?php endif; ?>
				<a href="<?php echo esc_url( add_query_arg( 'edit', $listing->ID, wb_listora_get_submit_url() ) ); ?>" class="listora-btn listora-btn--icon" aria-label="<?php esc_attr_e( 'Edit', 'wb-listora' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
				</a>
				<a href="<?php echo esc_url( get_permalink( $listing->ID ) ); ?>" class="listora-btn listora-btn--icon" aria-label="<?php esc_attr_e( 'View', 'wb-listora' ); ?>">
					<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
				</a>
				<div class="listora-dashboard__menu-wrap" data-wp-interactive="listora/directory">
					<button type="button" class="listora-btn listora-btn--icon" data-wp-on--click="actions.toggleListingMenu" aria-label="<?php esc_attr_e( 'More actions', 'wb-listora' ); ?>">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
					</button>
					<div class="listora-dashboard__menu-dropdown" hidden>
						<?php if ( $listora_can_renew ) : ?>
						<button type="button" class="listora-dashboard__menu-item" data-listora-renew-listing="<?php echo (int) $listing->ID; ?>" data-listing-title="<?php echo esc_attr( $listing->post_title ); ?>">
							<?php esc_html_e( 'Renew', 'wb-listora' ); ?>
						</button>
						<?php endif; ?>
						<?php if ( 'listora_deactivated' === $listing->post_status ) : ?>
						<button class="listora-dashboard__menu-item"
							data-wp-on--click="actions.reactivateListing"
							data-wp-context='<?php echo wp_json_encode( array( 'listingId' => $listing->ID ) ); ?>'>
							<?php esc_html_e( 'Reactivate', 'wb-listora' ); ?>
						</button>
						<?php elseif ( 'publish' === $listing->post_status ) : ?>
						<button class="listora-dashboard__menu-item listora-dashboard__menu-item--danger"
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
				<button type="button" class="listora-btn listora-btn--secondary listora-btn--sm listora-dashboard__add-service-btn"
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
					<button type="button" class="listora-btn listora-btn--primary listora-btn--sm" data-wp-on--click="actions.saveService">
						<?php esc_html_e( 'Save Service', 'wb-listora' ); ?>
					</button>
					<button type="button" class="listora-btn listora-btn--text listora-btn--sm" data-wp-on--click="actions.toggleServiceForm">
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
						<button type="button" class="listora-btn listora-btn--icon" data-wp-on--click="actions.editService"
							data-wp-context='<?php echo wp_json_encode( array( 'serviceId' => (int) $dash_svc['id'] ) ); ?>'
							aria-label="<?php esc_attr_e( 'Edit', 'wb-listora' ); ?>">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
						</button>
						<button type="button" class="listora-btn listora-btn--icon listora-dashboard__menu-item--danger" data-wp-on--click="actions.deleteService"
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

	<?php // Renewal confirm modal (shared, hidden by default). ?>
	<div class="listora-dashboard__renew-modal" data-listora-renew-modal hidden role="dialog" aria-modal="true" aria-labelledby="listora-renew-modal-title">
		<div class="listora-dashboard__renew-modal-backdrop" data-listora-renew-close></div>
		<div class="listora-dashboard__renew-modal-panel">
			<button type="button" class="listora-dashboard__renew-modal-close" data-listora-renew-close aria-label="<?php esc_attr_e( 'Close', 'wb-listora' ); ?>">
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
				<button type="button" class="listora-btn listora-btn--secondary" data-listora-renew-close>
					<?php esc_html_e( 'Cancel', 'wb-listora' ); ?>
				</button>
				<a href="#" class="listora-btn listora-btn--secondary" data-listora-renew-buy hidden>
					<?php esc_html_e( 'Buy more credits', 'wb-listora' ); ?>
				</a>
				<button type="button" class="listora-btn listora-btn--primary" data-listora-renew-confirm>
					<?php esc_html_e( 'Confirm renewal', 'wb-listora' ); ?>
				</button>
			</div>
		</div>
	</div>

	<div class="listora-dashboard__toast-stack" data-listora-toast-stack aria-live="polite" aria-atomic="true"></div>
</div>
<?php
do_action( 'wb_listora_after_dashboard_listings', $view_data );
