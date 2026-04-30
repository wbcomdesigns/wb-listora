<?php
/**
 * User Dashboard — My Reviews tab content.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/user-dashboard/tab-reviews.php
 *
 * @package WBListora
 *
 * @var int   $user_id          Current user ID.
 * @var array $user_reviews     Reviews written by the user.
 * @var array $reviews_received Reviews received on user's listings.
 * @var array $view_data        Full view data array.
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();

do_action( 'wb_listora_before_dashboard_reviews', $view_data );
?>
<div role="tabpanel" id="dash-panel-reviews" aria-labelledby="dash-tab-reviews" class="listora-dashboard__panel" hidden>

	<?php if ( ! empty( $user_reviews ) ) : ?>
	<h3 class="listora-dashboard__section-title"><?php esc_html_e( 'Reviews I\'ve Written', 'wb-listora' ); ?></h3>
		<?php foreach ( $user_reviews as $review ) : ?>
	<div class="listora-dashboard__review-row">
		<div class="listora-dashboard__review-header">
			<span class="listora-rating">
				<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
				<svg class="listora-rating__star <?php echo esc_attr( $s > (int) $review['overall_rating'] ? 'listora-rating__star--empty' : '' ); ?>" viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<?php endfor; ?>
			</span>
			<span class="listora-dashboard__review-listing">
				<?php echo esc_html( $review['listing_title'] ?: __( 'Deleted listing', 'wb-listora' ) ); ?>
			</span>
			<span class="listora-dashboard__review-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $review['created_at'] ) ) ); ?></span>
		</div>
			<?php if ( $review['title'] ) : ?>
		<strong><?php echo esc_html( $review['title'] ); ?></strong>
		<?php endif; ?>
		<p class="listora-dashboard__review-content"><?php echo esc_html( wp_trim_words( $review['content'], 30 ) ); ?></p>
	</div>
	<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( ! empty( $reviews_received ) ) : ?>
	<h3 class="listora-dashboard__section-title" style="margin-block-start: var(--listora-gap-lg);"><?php esc_html_e( 'Reviews on My Listings', 'wb-listora' ); ?></h3>
		<?php foreach ( $reviews_received as $review ) : ?>
		<?php
		$review_id     = (int) ( $review['id'] ?? 0 );
		$reply_context = wp_json_encode(
			array(
				'reviewId'      => $review_id,
				'replyOpen'     => false,
				'replySubmitting' => false,
				'replyError'    => '',
				'replyText'     => isset( $review['owner_reply'] ) ? (string) $review['owner_reply'] : '',
			)
		);
		?>
	<div class="listora-dashboard__review-row" data-wp-context='<?php echo $reply_context; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-built via wp_json_encode. ?>'>
		<div class="listora-dashboard__review-header">
			<span class="listora-rating">
				<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
				<svg class="listora-rating__star <?php echo esc_attr( $s > (int) $review['overall_rating'] ? 'listora-rating__star--empty' : '' ); ?>" viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				<?php endfor; ?>
			</span>
			<span class="listora-dashboard__review-listing"><?php echo esc_html( $review['listing_title'] ); ?></span>
			<span class="listora-dashboard__review-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $review['created_at'] ) ) ); ?></span>
		</div>
		<p class="listora-dashboard__review-content"><?php echo esc_html( wp_trim_words( $review['content'], 30 ) ); ?></p>

		<?php // Existing owner reply (if any). ?>
		<?php if ( ! empty( $review['owner_reply'] ) ) : ?>
		<div class="listora-dashboard__owner-reply" data-wp-class--is-hidden="context.replyOpen">
			<strong><?php esc_html_e( 'Your reply:', 'wb-listora' ); ?></strong>
			<p><?php echo esc_html( $review['owner_reply'] ); ?></p>
		</div>
		<?php endif; ?>

		<?php // Reply trigger — visible when no inline form is open. ?>
		<button
			type="button"
			class="listora-btn listora-btn--text listora-dashboard__reply-trigger"
			style="font-size: var(--listora-text-sm);"
			data-wp-on--click="actions.openReplyForm"
			data-wp-class--is-hidden="context.replyOpen"
		>
			<?php echo empty( $review['owner_reply'] ) ? esc_html__( 'Reply', 'wb-listora' ) : esc_html__( 'Edit reply', 'wb-listora' ); ?>
		</button>

		<?php // Inline reply form — toggled open by Reply button. ?>
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
	</div>
	<?php endforeach; ?>
	<?php endif; ?>

	<?php if ( empty( $user_reviews ) && empty( $reviews_received ) ) : ?>
	<div class="listora-dashboard__empty">
		<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
		<h3><?php esc_html_e( 'No reviews yet', 'wb-listora' ); ?></h3>
		<p><?php esc_html_e( 'Reviews you write and receive will appear here.', 'wb-listora' ); ?></p>
		<a href="<?php echo esc_url( wb_listora_get_directory_url() ); ?>" class="listora-btn listora-btn--secondary">
			<?php esc_html_e( 'Find a listing to review', 'wb-listora' ); ?>
		</a>
	</div>
	<?php endif; ?>
</div>
<?php
do_action( 'wb_listora_after_dashboard_reviews', $view_data );
