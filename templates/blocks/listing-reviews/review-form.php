<?php
/**
 * Review form (stars, criteria, text, submit). Override: copy to yourtheme/wb-listora/blocks/listing-reviews/review-form.php.
 *
 * @package WBListora
 *
 * @var int    $post_id           Listing post ID.
 * @var string $listing_type_slug Listing type slug.
 * @var array  $review_criteria   Filtered review criteria for this listing type.
 * @var array  $view_data         Full view data array (all variables).
 * @var string $form_id           Optional. Wrapper id and field id prefix (default 'listora-review').
 * @var string $submit_action     Optional. Interactivity action for submit (default 'actions.submitReviewForm').
 * @var string $cancel_action     Optional. Interactivity action for Cancel (default 'actions.toggleReviewForm').
 * @var string $captcha_context   Optional. CAPTCHA context (default 'review').
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

// The listing page's Reviews tab and the Reviews block share this form; each passes its own ids and actions.
$form_id         = isset( $form_id ) ? (string) $form_id : 'listora-review';
$submit_action   = isset( $submit_action ) ? (string) $submit_action : 'actions.submitReviewForm';
$cancel_action   = isset( $cancel_action ) ? (string) $cancel_action : 'actions.toggleReviewForm';
$captcha_context = isset( $captcha_context ) ? (string) $captcha_context : 'review';

// Same minimum length the REST API enforces; 0 means rating-only reviews are allowed.
$listora_review_settings = function_exists( 'wb_listora_get_setting' ) ? wb_listora_get_setting( 'reviews', array() ) : array();
if ( ! is_array( $listora_review_settings ) ) {
	$listora_review_settings = array();
}
$listora_review_min_length = isset( $listora_review_settings['min_length'] ) ? absint( $listora_review_settings['min_length'] ) : 20;
$listora_review_required   = $listora_review_min_length > 0;
?>
<?php if ( ! is_user_logged_in() ) : ?>
<?php // A guest has no form to open, so the log-in link sits outside the hidden wrapper. ?>
<p class="listora-reviews__login-notice">
	<a href="<?php echo esc_url( wp_login_url( get_permalink() . '#reviews' ) ); ?>"><?php esc_html_e( 'Log in', 'wb-listora' ); ?></a>
	<?php esc_html_e( 'to write a review.', 'wb-listora' ); ?>
</p>
<?php else : ?>
<div class="listora-reviews__form-wrapper" id="<?php echo esc_attr( $form_id ); ?>-form" hidden>
	<form class="listora-reviews__form" data-wp-on--submit="<?php echo esc_attr( $submit_action ); ?>">
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
			<label for="<?php echo esc_attr( $form_id ); ?>-title" class="listora-submission__label"><?php esc_html_e( 'Review Title', 'wb-listora' ); ?> <span class="required">*</span></label>
			<input type="text" id="<?php echo esc_attr( $form_id ); ?>-title" name="title" class="listora-input" required
				placeholder="<?php esc_attr_e( 'Summarize your experience', 'wb-listora' ); ?>"
				data-wp-on--blur="actions.validateFieldOnBlur" />
		</div>

		<div class="listora-submission__field">
			<label for="<?php echo esc_attr( $form_id ); ?>-content" class="listora-submission__label">
				<?php esc_html_e( 'Your Review', 'wb-listora' ); ?>
				<?php if ( $listora_review_required ) : ?>
				<span class="required">*</span>
				<?php endif; ?>
			</label>
			<textarea id="<?php echo esc_attr( $form_id ); ?>-content" name="content" class="listora-input listora-submission__textarea" rows="5"
				<?php if ( $listora_review_required ) : ?>
				required minlength="<?php echo esc_attr( $listora_review_min_length ); ?>"
				<?php endif; ?>
				<?php // Placeholder must stay on one line: a wrapped attribute value leaks the surrounding template indentation as literal whitespace into the box. ?>
				placeholder="<?php echo esc_attr( $listora_review_required ? sprintf( /* translators: %d: minimum number of characters required for a review. */ _n( 'Share your experience (minimum %d character)', 'Share your experience (minimum %d characters)', $listora_review_min_length, 'wb-listora' ), $listora_review_min_length ) : __( 'Share your experience (optional)', 'wb-listora' ) ); ?>"
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
						<?php /* translators: %d: number of stars */ ?>
						<span class="listora-sr-only"><?php echo esc_html( $cs ); ?> <?php echo esc_html( _n( 'star', 'stars', $cs, 'wb-listora' ) ); ?></span>
					</label>
					<?php endfor; ?>
				</fieldset>
			</div>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>

		<?php // CAPTCHA widget for review form. ?>
		<?php \WBListora\Captcha::render_widget( $captcha_context ); ?>

		<div class="listora-reviews__form-actions">
			<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Review', 'wb-listora' ); ?></button>
			<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="<?php echo esc_attr( $cancel_action ); ?>"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
		</div>

		<div class="listora-reviews__form-message" hidden></div>
	</form>
</div>
<?php endif; ?>
