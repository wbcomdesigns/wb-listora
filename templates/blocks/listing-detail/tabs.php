<?php
/**
 * Listing Detail — Tab navigation + panels container.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-detail/tabs.php
 *
 * @package WBListora
 *
 * @var int    $post_id              Listing post ID.
 * @var object $post                 WP_Post object.
 * @var object $type                 Listing type object or null.
 * @var array  $meta                 All listing meta values.
 * @var array  $field_groups         Field group objects.
 * @var array  $features             Feature term objects.
 * @var array  $business_hours       Business hours data.
 * @var array  $detail_services      Services array.
 * @var int    $detail_service_count Service count.
 * @var bool   $show_reviews         Whether to show reviews tab.
 * @var bool   $show_map             Whether to show map tab.
 * @var float  $avg_rating           Average rating.
 * @var int    $review_count         Review count.
 * @var float  $lat                  Latitude.
 * @var float  $lng                  Longitude.
 * @var array  $detail_reviews       Pre-assembled review rows (newest first, limit 20) as ARRAY_A.
 * @var array  $detail_review_summary Keys: avg (float), total (int), dist (array<int,int> stars 1-5).
 * @var bool   $detail_user_reviewed Whether the current user has reviewed this listing.
 * @var bool   $detail_is_owner      Whether the current user authored this listing.
 * @var array  $view_data            Full view data array.
 *
 * Pre-assembled in blocks/listing-detail/render.php — do NOT add $wpdb queries
 * here. Add data by hooking `wb_listora_detail_tabs_view_data`.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

do_action( 'wb_listora_before_detail_tabs', $view_data );
?>
<div class="listora-detail__main">

	<?php // Tab Navigation. ?>
	<div class="listora-detail__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Listing details', 'wb-listora' ); ?>">
		<button role="tab" class="listora-detail__tab is-active" id="tab-overview" aria-selected="true" aria-controls="panel-overview"
			data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"overview"}'>
			<?php esc_html_e( 'Overview', 'wb-listora' ); ?>
		</button>
		<?php foreach ( $field_groups as $group ) : ?>
		<button role="tab" class="listora-detail__tab" id="tab-<?php echo esc_attr( $group->get_key() ); ?>" aria-selected="false"
			aria-controls="panel-<?php echo esc_attr( $group->get_key() ); ?>"
			data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"<?php echo esc_attr( $group->get_key() ); ?>"}'>
			<?php echo esc_html( $group->get_label() ); ?>
		</button>
		<?php endforeach; ?>
		<?php if ( $detail_service_count > 0 ) : ?>
		<button role="tab" class="listora-detail__tab" id="tab-services" aria-selected="false" aria-controls="panel-services"
			data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"services"}'>
			<?php esc_html_e( 'Services', 'wb-listora' ); ?>
			<span class="listora-detail__tab-count"><?php echo esc_html( $detail_service_count ); ?></span>
		</button>
		<?php endif; ?>
		<?php if ( $show_reviews ) : ?>
		<button role="tab" class="listora-detail__tab" id="tab-reviews" aria-selected="false" aria-controls="panel-reviews"
			data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"reviews"}'>
			<?php esc_html_e( 'Reviews', 'wb-listora' ); ?>
			<?php if ( $review_count > 0 ) : ?>
			<span class="listora-detail__tab-count"><?php echo esc_html( $review_count ); ?></span>
			<?php endif; ?>
		</button>
		<?php endif; ?>
		<?php if ( $show_map && $lat ) : ?>
		<button role="tab" class="listora-detail__tab" id="tab-map" aria-selected="false" aria-controls="panel-map"
			data-wp-on--click="actions.switchTab" data-wp-context='{"tabId":"map"}'>
			<?php esc_html_e( 'Map', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<?php // Overview Tab. ?>
	<div role="tabpanel" id="panel-overview" aria-labelledby="tab-overview" class="listora-detail__panel">
		<?php if ( $post->post_content ) : ?>
		<div class="listora-detail__description" itemprop="description">
			<?php echo wp_kses_post( apply_filters( 'the_content', $post->post_content ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core WP filter. ?>
		</div>
		<?php endif; ?>

		<?php // Quick info fields. ?>
		<?php if ( $type ) : ?>
		<div class="listora-detail__quick-info">
			<?php
			foreach ( $type->get_card_fields() as $field ) :
				$key     = $field->get_key();
				$value   = $meta[ $key ] ?? '';
				$display = wb_listora_format_card_value( $field, $value );
				if ( '' === $display || 'map_location' === $field->get_type() || 'gallery' === $field->get_type() || 'social_links' === $field->get_type() || 'business_hours' === $field->get_type() ) {
					continue;
				}
				?>
			<div class="listora-detail__info-item">
				<dt><?php echo esc_html( $field->get_label() ); ?></dt>
				<dd><?php echo esc_html( $display ); ?></dd>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php // Features. ?>
		<?php if ( ! empty( $features ) ) : ?>
		<div class="listora-detail__features">
			<h3><?php esc_html_e( 'Features & Amenities', 'wb-listora' ); ?></h3>
			<div class="listora-detail__features-list">
				<?php foreach ( $features as $feature ) : ?>
				<span class="listora-feature-badge">
					<?php $icon = get_term_meta( $feature->term_id, '_listora_icon', true ); ?>
					<?php if ( $icon ) : ?>
						<?php echo \WBListora\Core\Lucide_Icons::render( $icon, 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php endif; ?>
					<?php echo esc_html( $feature->name ); ?>
				</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>
	</div>

	<?php // Field Group Tabs. ?>
	<?php foreach ( $field_groups as $group ) : ?>
	<div role="tabpanel" id="panel-<?php echo esc_attr( $group->get_key() ); ?>" aria-labelledby="tab-<?php echo esc_attr( $group->get_key() ); ?>" class="listora-detail__panel" hidden>
		<dl class="listora-detail__field-list">
			<?php
			foreach ( $group->get_fields() as $field ) :
				// Skip fields whose conditional logic is not met.
				if ( ! $field->check_conditional( $meta ) ) {
					continue;
				}

				$key     = $field->get_key();
				$value   = $meta[ $key ] ?? '';
				$display = wb_listora_format_card_value( $field, $value );
				if ( '' === $display ) {
					continue;
				}

				// Skip complex types that render separately.
				if ( in_array( $field->get_type(), array( 'gallery', 'social_links' ), true ) ) {
					continue;
				}

				// Business hours: render as schedule.
				if ( 'business_hours' === $field->get_type() && ! empty( $business_hours ) ) :
					?>
				<div class="listora-detail__field-item listora-detail__field-item--hours">
					<dt><?php echo esc_html( $field->get_label() ); ?></dt>
					<dd>
						<?php echo wp_kses_post( wb_listora_render_hours( $business_hours ) ); ?>
					</dd>
				</div>
					<?php
					continue;
endif;
				?>
			<div class="listora-detail__field-item">
				<dt><?php echo esc_html( $field->get_label() ); ?></dt>
				<dd>
					<?php if ( 'url' === $field->get_type() ) : ?>
					<a href="<?php echo esc_url( $value ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $value, PHP_URL_HOST ) ?: $value ); ?></a>
					<?php elseif ( 'email' === $field->get_type() ) : ?>
					<a href="mailto:<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $value ); ?></a>
					<?php elseif ( 'phone' === $field->get_type() ) : ?>
					<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $value ) ); ?>"><?php echo esc_html( $value ); ?></a>
					<?php else : ?>
						<?php echo esc_html( $display ); ?>
					<?php endif; ?>
				</dd>
			</div>
			<?php endforeach; ?>
		</dl>
	</div>
	<?php endforeach; ?>

	<?php // Services Tab. ?>
	<?php if ( $detail_service_count > 0 ) : ?>
	<div role="tabpanel" id="panel-services" aria-labelledby="tab-services" class="listora-detail__panel" hidden>
		<div class="listora-detail__services-grid">
			<?php foreach ( $detail_services as $svc ) : ?>
			<div class="listora-detail__service-card">
				<?php
				$svc_image_url = '';
				if ( ! empty( $svc['image_id'] ) ) {
					$svc_image_url = wp_get_attachment_image_url( (int) $svc['image_id'], 'medium' );
				}
				?>
				<?php if ( $svc_image_url ) : ?>
				<div class="listora-detail__service-image">
					<img src="<?php echo esc_url( $svc_image_url ); ?>" alt="<?php echo esc_attr( $svc['title'] ); ?>" loading="lazy" />
				</div>
				<?php endif; ?>
				<div class="listora-detail__service-body">
					<h4 class="listora-detail__service-title"><?php echo esc_html( $svc['title'] ); ?></h4>
					<?php
					// Price display.
					$svc_price_display = '';
					if ( 'free' === $svc['price_type'] ) {
						$svc_price_display = __( 'Free', 'wb-listora' );
					} elseif ( 'contact' === $svc['price_type'] ) {
						$svc_price_display = __( 'Contact for price', 'wb-listora' );
					} elseif ( null !== $svc['price'] ) {
						$svc_formatted_price = number_format( (float) $svc['price'], 2 );
						if ( 'starting_from' === $svc['price_type'] ) {
							/* translators: %s: price amount */
							$svc_price_display = sprintf( __( 'From $%s', 'wb-listora' ), $svc_formatted_price );
						} elseif ( 'hourly' === $svc['price_type'] ) {
							/* translators: %s: price amount */
							$svc_price_display = sprintf( __( '$%s/hr', 'wb-listora' ), $svc_formatted_price );
						} else {
							$svc_price_display = '$' . $svc_formatted_price;
						}
					}
					?>
					<div class="listora-detail__service-meta">
						<?php if ( $svc_price_display ) : ?>
						<span class="listora-detail__service-price"><?php echo esc_html( $svc_price_display ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $svc['duration_minutes'] ) ) : ?>
						<span class="listora-detail__service-duration">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
							<?php
							$svc_hours = floor( (int) $svc['duration_minutes'] / 60 );
							$svc_mins  = (int) $svc['duration_minutes'] % 60;
							if ( $svc_hours > 0 && $svc_mins > 0 ) {
								/* translators: 1: hours, 2: minutes */
								printf( esc_html__( '%1$dh %2$dmin', 'wb-listora' ), (int) $svc_hours, (int) $svc_mins );
							} elseif ( $svc_hours > 0 ) {
								/* translators: %d: hours */
								printf( esc_html( _n( '%d hour', '%d hours', (int) $svc_hours, 'wb-listora' ) ), (int) $svc_hours );
							} else {
								/* translators: %d: minutes */
								printf( esc_html__( '%d min', 'wb-listora' ), (int) $svc_mins );
							}
							?>
						</span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $svc['description'] ) ) : ?>
					<div class="listora-detail__service-desc-wrap">
						<p class="listora-detail__service-desc listora-detail__service-desc--collapsed"><?php echo esc_html( $svc['description'] ); ?></p>
						<button type="button" class="listora-btn listora-btn--text listora-detail__service-toggle"
							data-wp-on--click="actions.toggleServiceDesc">
							<?php esc_html_e( 'Details', 'wb-listora' ); ?>
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
						</button>
					</div>
					<?php endif; ?>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php // Reviews Tab. ?>
	<?php if ( $show_reviews ) : ?>
	<div role="tabpanel" id="panel-reviews" aria-labelledby="tab-reviews" class="listora-detail__panel" hidden>
		<?php
		if ( ! empty( $detail_reviews ) ) :
			$avg = (float) $detail_review_summary['avg'];
			$cnt = (int) $detail_review_summary['total'];
			?>
			<div class="listora-detail__reviews-summary">
				<div class="listora-detail__reviews-score">
					<span class="listora-detail__reviews-avg"><?php echo esc_html( number_format( $avg, 1 ) ); ?></span>
					<div class="listora-detail__reviews-stars">
						<?php for ( $star = 1; $star <= 5; $star++ ) : ?>
						<svg class="listora-rating__star<?php echo esc_attr( $star > round( $avg ) ? ' listora-rating__star--empty' : '' ); ?>" viewBox="0 0 24 24" aria-hidden="true" width="20" height="20"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
						<?php endfor; ?>
					</div>
					<span class="listora-detail__reviews-total">
						<?php
						/* translators: %d: number of reviews */
						printf( esc_html( _n( '%d review', '%d reviews', $cnt, 'wb-listora' ) ), $cnt ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- integer used with %d format specifier; format string is esc_html-wrapped.
						?>
					</span>
				</div>
				<div class="listora-detail__reviews-bars">
					<?php
					for ( $bar = 5; $bar >= 1; $bar-- ) :
						$bar_count = (int) ( $detail_review_summary['dist'][ $bar ] ?? 0 );
						$bar_pct   = $cnt > 0 ? round( $bar_count / $cnt * 100 ) : 0;
						?>
					<div class="listora-detail__reviews-bar-row">
						<span><?php echo esc_html( $bar ); ?></span>
						<div class="listora-detail__reviews-bar"><div style="width:<?php echo esc_attr( $bar_pct ); ?>%"></div></div>
						<span><?php echo esc_html( $bar_count ); ?></span>
					</div>
					<?php endfor; ?>
				</div>
			</div>

			<div class="listora-detail__reviews-list">
				<?php
				foreach ( $detail_reviews as $rev ) :
					$reviewer   = get_user_by( 'id', $rev['user_id'] );
					$rev_name   = $reviewer ? $reviewer->display_name : __( 'Anonymous', 'wb-listora' );
					$rev_avatar = $reviewer ? get_avatar_url( $rev['user_id'], array( 'size' => 48 ) ) : '';
					?>
				<div class="listora-detail__review">
					<div class="listora-detail__review-header">
						<?php if ( $rev_avatar ) : ?>
						<img src="<?php echo esc_url( $rev_avatar ); ?>" alt="<?php echo esc_attr( $rev_name ); ?>" class="listora-detail__review-avatar" width="40" height="40" loading="lazy" />
						<?php endif; ?>
						<div>
							<strong class="listora-detail__review-author"><?php echo esc_html( $rev_name ); ?></strong>
							<div class="listora-detail__review-meta">
								<span class="listora-rating">
									<?php for ( $rs = 1; $rs <= 5; $rs++ ) : ?>
									<svg class="listora-rating__star<?php echo esc_attr( $rs > (int) $rev['overall_rating'] ? ' listora-rating__star--empty' : '' ); ?>" viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
									<?php endfor; ?>
								</span>
								<time datetime="<?php echo esc_attr( $rev['created_at'] ); ?>"><?php echo esc_html( human_time_diff( strtotime( $rev['created_at'] ), current_time( 'timestamp' ) ) . ' ' . __( 'ago', 'wb-listora' ) ); ?></time>
							</div>
						</div>
					</div>
					<?php if ( ! empty( $rev['title'] ) ) : ?>
					<h4 class="listora-detail__review-title"><?php echo esc_html( $rev['title'] ); ?></h4>
					<?php endif; ?>
					<p class="listora-detail__review-content"><?php echo esc_html( $rev['content'] ); ?></p>
					<?php if ( ! empty( $rev['owner_reply'] ) ) : ?>
					<div class="listora-detail__review-reply">
						<strong><?php esc_html_e( 'Owner Response:', 'wb-listora' ); ?></strong>
						<p><?php echo esc_html( $rev['owner_reply'] ); ?></p>
					</div>
					<?php endif; ?>
					<?php if ( (int) $rev['helpful_count'] > 0 ) : ?>
					<span class="listora-detail__review-helpful">
						<?php
						/* translators: %d: number of people */
						printf( esc_html( _n( '%d person found this helpful', '%d people found this helpful', (int) $rev['helpful_count'], 'wb-listora' ) ), (int) $rev['helpful_count'] );
						?>
					</span>
					<?php endif; ?>
				</div>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<div class="listora-detail__reviews-empty">
				<p><?php esc_html_e( 'No reviews yet. Be the first to share your experience!', 'wb-listora' ); ?></p>
			</div>
		<?php endif; ?>

		<?php // Review Form. ?>
		<?php if ( ! $detail_user_reviewed && ! $detail_is_owner && is_user_logged_in() ) : ?>
		<button type="button" class="listora-btn listora-btn--primary listora-reviews__write-btn" data-wp-on--click="actions.toggleDetailReviewForm">
			<?php esc_html_e( 'Write a Review', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>

		<?php if ( ! $detail_user_reviewed && ! $detail_is_owner ) : ?>
		<div class="listora-reviews__form-wrapper" id="listora-detail-review-form" hidden>
			<?php if ( ! is_user_logged_in() ) : ?>
			<p class="listora-reviews__login-notice">
				<a href="<?php echo esc_url( wp_login_url( get_permalink() . '#reviews' ) ); ?>"><?php esc_html_e( 'Log in', 'wb-listora' ); ?></a>
				<?php esc_html_e( 'to write a review.', 'wb-listora' ); ?>
			</p>
			<?php else : ?>
			<form class="listora-reviews__form" data-wp-on--submit="actions.submitDetailReviewForm">
				<h3><?php esc_html_e( 'Write a Review', 'wb-listora' ); ?></h3>

				<div class="listora-submission__field">
					<label class="listora-submission__label"><?php esc_html_e( 'Your Rating', 'wb-listora' ); ?> <span class="required">*</span></label>
					<fieldset class="listora-reviews__star-input" role="radiogroup" aria-label="<?php esc_attr_e( 'Rating', 'wb-listora' ); ?>">
						<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
						<label class="listora-reviews__star-label">
							<input type="radio" name="overall_rating" value="<?php echo esc_attr( $s ); ?>" required />
							<svg viewBox="0 0 24 24" width="28" height="28" class="listora-reviews__star-svg">
								<path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
							</svg>
							<?php /* translators: %d: number of stars */ ?>
							<span class="listora-sr-only"><?php echo esc_html( $s ); ?> <?php echo esc_html( _n( 'star', 'stars', $s, 'wb-listora' ) ); ?></span>
						</label>
						<?php endfor; ?>
					</fieldset>
				</div>

				<div class="listora-submission__field">
					<label for="listora-detail-review-title" class="listora-submission__label"><?php esc_html_e( 'Review Title', 'wb-listora' ); ?> <span class="required">*</span></label>
					<input type="text" id="listora-detail-review-title" name="title" class="listora-input" required
						placeholder="<?php esc_attr_e( 'Summarize your experience', 'wb-listora' ); ?>" />
				</div>

				<div class="listora-submission__field">
					<label for="listora-detail-review-content" class="listora-submission__label"><?php esc_html_e( 'Your Review', 'wb-listora' ); ?> <span class="required">*</span></label>
					<textarea id="listora-detail-review-content" name="content" class="listora-input listora-submission__textarea" rows="5" required minlength="20"
						placeholder="<?php esc_attr_e( 'Share your experience (minimum 20 characters)', 'wb-listora' ); ?>"></textarea>
				</div>

				<?php
				/** This action is documented in blocks/listing-reviews/render.php */
				do_action( 'wb_listora_review_form_after_content', $post_id );
				?>

				<?php
				/** This filter is documented in blocks/listing-reviews/render.php */
				$detail_listing_type_slug = '';
				$detail_listing_type_obj  = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $post_id );
				if ( $detail_listing_type_obj ) {
					$detail_listing_type_slug = $detail_listing_type_obj->get_slug();
				}
				$detail_review_criteria = apply_filters( 'wb_listora_review_criteria', array(), $detail_listing_type_slug );

				if ( ! empty( $detail_review_criteria ) ) :
					?>
				<div class="listora-reviews__criteria">
					<label class="listora-submission__label"><?php esc_html_e( 'Rate each aspect', 'wb-listora' ); ?></label>
					<?php foreach ( $detail_review_criteria as $criterion ) : ?>
					<div class="listora-reviews__criterion">
						<span class="listora-reviews__criterion-label"><?php echo esc_html( $criterion['label'] ); ?></span>
						<fieldset class="listora-reviews__star-input listora-reviews__star-input--small" role="radiogroup"
							aria-label="<?php echo esc_attr( $criterion['label'] ); ?>">
							<?php for ( $cs = 1; $cs <= 5; $cs++ ) : ?>
							<label class="listora-reviews__star-label">
								<input type="radio" name="criteria_ratings[<?php echo esc_attr( $criterion['key'] ); ?>]" value="<?php echo esc_attr( $cs ); ?>" />
								<svg viewBox="0 0 24 24" width="20" height="20" class="listora-reviews__star-svg">
									<path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
								</svg>
								<?php /* translators: %d: number of stars */ ?>
								<span class="listora-sr-only"><?php echo esc_html( $cs ); ?> <?php echo esc_html( _n( 'star', 'stars', $cs, 'wb-listora' ) ); ?></span>
							</label>
							<?php endfor; ?>
						</fieldset>
					</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>

				<div class="listora-reviews__form-actions">
					<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Review', 'wb-listora' ); ?></button>
					<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="actions.toggleDetailReviewForm"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
				</div>

				<div class="listora-reviews__form-message" hidden></div>
			</form>
			<?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php // Map Tab. ?>
	<?php if ( $show_map && $lat ) : ?>
	<div role="tabpanel" id="panel-map" aria-labelledby="tab-map" class="listora-detail__panel" hidden>
		<div class="listora-detail__map-embed" style="height: 300px;" id="listora-detail-map"
			data-lat="<?php echo esc_attr( $lat ); ?>" data-lng="<?php echo esc_attr( $lng ); ?>">
		</div>
		<a class="listora-btn listora-btn--secondary" href="https://www.google.com/maps/dir/?api=1&destination=<?php echo esc_attr( $lat . ',' . $lng ); ?>" target="_blank" rel="noopener" style="margin-block-start: 0.75rem;">
			<?php esc_html_e( 'Get Directions', 'wb-listora' ); ?>
		</a>
	</div>
	<?php endif; ?>
</div>
<?php
do_action( 'wb_listora_after_detail_tabs', $view_data );
