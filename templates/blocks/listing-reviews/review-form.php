<?php
/**
 * Listing Reviews — Review submission form (star picker, criteria, textarea, submit).
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-reviews/review-form.php
 *
 * @package WBListora
 *
 * @var int    $post_id           Listing post ID.
 * @var string $listing_type_slug Listing type slug.
 * @var array  $review_criteria   Filtered review criteria for this listing type.
 * @var array  $view_data         Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();
?>
<div class="listora-reviews__form-wrapper" id="listora-review-form" hidden>
	<?php if ( ! is_user_logged_in() ) : ?>
	<p class="listora-reviews__login-notice">
		<a href="<?php echo esc_url( wp_login_url( get_permalink() . '#reviews' ) ); ?>"><?php esc_html_e( 'Log in', 'wb-listora' ); ?></a>
		<?php esc_html_e( 'to write a review.', 'wb-listora' ); ?>
	</p>
	<?php else : ?>
	<form class="listora-reviews__form" data-wp-on--submit="actions.submitReviewForm">
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
			<label for="listora-review-title" class="listora-submission__label"><?php esc_html_e( 'Review Title', 'wb-listora' ); ?> <span class="required">*</span></label>
			<input type="text" id="listora-review-title" name="title" class="listora-input" required
				placeholder="<?php esc_attr_e( 'Summarize your experience', 'wb-listora' ); ?>"
				data-wp-on--blur="actions.validateFieldOnBlur" />
		</div>

		<div class="listora-submission__field">
			<label for="listora-review-content" class="listora-submission__label"><?php esc_html_e( 'Your Review', 'wb-listora' ); ?> <span class="required">*</span></label>
			<textarea id="listora-review-content" name="content" class="listora-input listora-submission__textarea" rows="5" required minlength="20"
				placeholder="<?php esc_attr_e( 'Share your experience (minimum 20 characters)', 'wb-listora' ); ?>"
				data-wp-on--blur="actions.validateFieldOnBlur"></textarea>
		</div>

		<?php
		/**
		 * Fires after the review form textarea, before criteria fields.
		 *
		 * Pro uses this to inject the photo upload UI.
		 *
		 * @param int $post_id Current listing post ID.
		 */
		do_action( 'wb_listora_review_form_after_content', $post_id );
		?>

		<?php if ( ! empty( $review_criteria ) ) : ?>
		<div class="listora-reviews__criteria">
			<label class="listora-submission__label"><?php esc_html_e( 'Rate each aspect', 'wb-listora' ); ?></label>
			<?php foreach ( $review_criteria as $criterion ) : ?>
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
					</label>
					<?php endfor; ?>
				</fieldset>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php // CAPTCHA widget for review form. ?>
		<?php \WBListora\Captcha::render_widget( 'review' ); ?>

		<div class="listora-reviews__form-actions">
			<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Review', 'wb-listora' ); ?></button>
			<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="actions.toggleReviewForm"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
		</div>

		<div class="listora-reviews__form-message" hidden></div>
	</form>
	<?php endif; ?>
</div>
