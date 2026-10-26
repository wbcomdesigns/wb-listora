<?php
/**
 * Inline owner-reply form for one review.
 *
 * Shared by My Dashboard > Reviews and the listing page's Reviews tab. The
 * wrapper that holds it supplies the Interactivity context (reviewId,
 * replyOpen, replyText, replySubmitting, replyError); the shared store's
 * openReplyForm / updateReplyText / submitReply / cancelReply drive it.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/reviews/reply-form.php
 *
 * @package WBListora
 *
 * @var int $review_id Review ID (makes the textarea id unique on the page).
 */

defined( 'ABSPATH' ) || exit;
?>
<form
	class="listora-dashboard__reply-form"
	data-wp-on--submit="actions.submitReply"
	data-wp-class--is-hidden="!context.replyOpen"
>
	<label class="listora-sr-only" for="listora-reply-<?php echo esc_attr( $review_id ); ?>"><?php esc_html_e( 'Reply text', 'wb-listora' ); ?></label>
	<textarea
		id="listora-reply-<?php echo esc_attr( $review_id ); ?>"
		class="listora-input listora-dashboard__reply-textarea"
		rows="3"
		required
		placeholder="<?php esc_attr_e( 'Write a public reply that will appear under this review…', 'wb-listora' ); ?>"
		data-wp-bind--value="context.replyText"
		data-wp-on--input="actions.updateReplyText"
	></textarea>
	<p
		class="listora-dashboard__reply-error"
		role="alert"
		data-wp-class--is-hidden="!context.replyError"
		data-wp-text="context.replyError"
	></p>
	<div class="listora-dashboard__reply-actions">
		<button
			type="submit"
			class="listora-btn listora-btn--primary"
			data-wp-bind--disabled="context.replySubmitting"
		>
			<span data-wp-class--is-hidden="context.replySubmitting"><?php esc_html_e( 'Submit reply', 'wb-listora' ); ?></span>
			<span data-wp-class--is-hidden="!context.replySubmitting"><?php esc_html_e( 'Submitting…', 'wb-listora' ); ?></span>
		</button>
		<button
			type="button"
			class="listora-btn listora-btn--text"
			data-wp-on--click="actions.cancelReply"
		>
			<?php esc_html_e( 'Cancel', 'wb-listora' ); ?>
		</button>
	</div>
</form>
