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
	<div class="listora-dashboard__review-row">
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
			<?php if ( empty( $review['owner_reply'] ) ) : ?>
		<button class="listora-btn listora-btn--text" style="font-size: var(--listora-text-sm);"><?php esc_html_e( 'Reply', 'wb-listora' ); ?></button>
		<?php endif; ?>
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
