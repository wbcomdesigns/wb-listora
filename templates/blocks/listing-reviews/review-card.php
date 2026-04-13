<?php
/**
 * Listing Reviews — Individual review card.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-reviews/review-card.php
 *
 * @package WBListora
 *
 * @var array  $review        Review row from the database.
 * @var string $reviewer_name Reviewer display name.
 * @var string $avatar_url    Reviewer avatar URL (empty string if no avatar).
 * @var bool   $is_owner      Whether the current user is the listing author.
 * @var int    $post_id       Listing post ID.
 * @var array  $view_data     Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;

$view_data = $view_data ?? get_defined_vars();
?>
<div class="listora-reviews__review" id="review-<?php echo esc_attr( $review['id'] ); ?>">
	<div class="listora-reviews__review-header">
		<?php if ( $avatar_url ) : ?>
		<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $reviewer_name ); ?>" class="listora-reviews__avatar" width="40" height="40" loading="lazy" />
		<?php endif; ?>
		<div class="listora-reviews__review-meta">
			<span class="listora-reviews__reviewer"><?php echo esc_html( $reviewer_name ); ?></span>
			<div class="listora-reviews__review-rating">
				<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
				<svg class="listora-rating__star <?php echo $s > (int) $review['overall_rating'] ? 'listora-rating__star--empty' : ''; ?>" viewBox="0 0 24 24" width="14" height="14">
					<path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
				</svg>
				<?php endfor; ?>
				<span class="listora-reviews__review-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $review['created_at'] ) ) ); ?></span>
			</div>
		</div>
	</div>

	<?php if ( $review['title'] ) : ?>
	<h4 class="listora-reviews__review-title"><?php echo esc_html( $review['title'] ); ?></h4>
	<?php endif; ?>

	<p class="listora-reviews__review-content"><?php echo esc_html( $review['content'] ); ?></p>

	<?php
	/**
	 * Fires after the review content text, inside the review list item.
	 *
	 * Pro uses this to render review photo thumbnails.
	 *
	 * @param array $review Current review row from the database.
	 */
	do_action( 'wb_listora_review_after_content', $review );
	?>

	<div class="listora-reviews__review-actions">
		<button class="listora-reviews__helpful-btn" data-wp-on--click="actions.voteReviewHelpful"
			data-wp-context='<?php echo wp_json_encode( array( 'reviewId' => (int) $review['id'] ) ); ?>'
			aria-label="<?php esc_attr_e( 'Mark as helpful', 'wb-listora' ); ?>">
			<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
				<path d="M7 10v12"/><path d="M15 5.88L14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2h0a3.13 3.13 0 0 1 3 3.88Z"/>
			</svg>
			<?php esc_html_e( 'Helpful', 'wb-listora' ); ?>
			<?php if ( (int) $review['helpful_count'] > 0 ) : ?>
			<span class="listora-reviews__helpful-count">(<?php echo esc_html( $review['helpful_count'] ); ?>)</span>
			<?php endif; ?>
		</button>

		<button class="listora-reviews__report-btn" data-wp-on--click="actions.showReportModal"
			data-wp-context='<?php echo wp_json_encode( array( 'reviewId' => (int) $review['id'] ) ); ?>'>
			<?php esc_html_e( 'Report', 'wb-listora' ); ?>
		</button>
	</div>

	<?php // Owner Reply ?>
	<?php if ( ! empty( $review['owner_reply'] ) ) : ?>
	<div class="listora-reviews__owner-reply">
		<strong class="listora-reviews__reply-label"><?php esc_html_e( 'Owner Response', 'wb-listora' ); ?></strong>
		<p><?php echo esc_html( $review['owner_reply'] ); ?></p>
		<?php if ( $review['owner_reply_at'] ) : ?>
		<span class="listora-reviews__reply-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $review['owner_reply_at'] ) ) ); ?></span>
		<?php endif; ?>
	</div>
	<?php elseif ( $is_owner ) : ?>
	<button class="listora-btn listora-btn--text listora-reviews__reply-btn"
		data-wp-on--click="actions.showReplyForm"
		data-wp-context='<?php echo wp_json_encode( array( 'reviewId' => (int) $review['id'] ) ); ?>'>
		<?php esc_html_e( 'Reply to this review', 'wb-listora' ); ?>
	</button>
	<?php endif; ?>
</div>
