<?php
/**
 * Listing Submission — Step: Duplicate Review.
 *
 * Rendered when the server returns HTTP 409 listora_duplicate_detected on submit.
 * The actual list of duplicate cards, confirm checkbox, and explanation textarea
 * are injected client-side by `view.js` (showDuplicateReviewStep()) when the
 * 409 response arrives — the server does not know the duplicates at first
 * render. This template emits the empty panel scaffold so theme overrides and
 * progressive-enhancement flows can target a stable container.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-submission/step-duplicate-review.php
 *
 * @package WBListora
 *
 * @var array $view_data Full view data array.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="listora-submission__duplicate-review" data-step="duplicate-review" hidden>
	<h2 class="listora-submission__duplicate-review-heading">
		<?php esc_html_e( 'We found similar listings — is yours different?', 'wb-listora' ); ?>
	</h2>
	<p class="listora-submission__duplicate-review-intro">
		<?php esc_html_e( 'These existing listings look similar to what you entered. Please review them before submitting.', 'wb-listora' ); ?>
	</p>

	<ul class="listora-submission__duplicate-list" aria-live="polite">
		<?php // Cards are injected by view.js when the 409 response arrives. ?>
	</ul>

	<p class="listora-submission__duplicate-review-notice">
		<?php esc_html_e( 'If your business is different from all listings above, you can submit it. We\'ll keep both.', 'wb-listora' ); ?>
	</p>

	<div class="listora-submission__field listora-submission__field--checkbox">
		<label class="listora-submission__checkbox-label">
			<input type="checkbox" name="listora_dup_confirm" required />
			<span><?php esc_html_e( 'I confirm this is a different business, not a duplicate of the above', 'wb-listora' ); ?></span>
		</label>
	</div>

	<div class="listora-submission__field">
		<label class="listora-submission__label">
			<?php esc_html_e( 'Briefly explain how it\'s different', 'wb-listora' ); ?>
			<span class="listora-submission__field-hint">
				<?php esc_html_e( '(helps our review team — at least 20 characters)', 'wb-listora' ); ?>
			</span>
		</label>
		<textarea
			name="listora_dup_explanation"
			rows="4"
			minlength="20"
			class="listora-input"
			placeholder="<?php esc_attr_e( 'e.g. We are a different business with a separate location.', 'wb-listora' ); ?>"
			required
		></textarea>
	</div>

	<div class="listora-submission__duplicate-review-error" role="alert" hidden></div>

	<div class="listora-submission__duplicate-review-actions">
		<button type="button" class="listora-btn listora-btn--secondary listora-submission__duplicate-cancel">
			<?php esc_html_e( 'Cancel — change my listing', 'wb-listora' ); ?>
		</button>
		<button type="button" class="listora-btn listora-btn--primary listora-submission__duplicate-submit-anyway">
			<?php esc_html_e( 'Submit anyway', 'wb-listora' ); ?>
		</button>
	</div>
</div>
