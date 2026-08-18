<?php
/**
 * Listing Reviews — Main wrapper: summary + toolbar + form + review list.
 *
 * This template can be overridden by copying it to:
 *   yourtheme/wb-listora/blocks/listing-reviews/reviews.php
 *
 * @package WBListora
 *
 * @var int    $post_id         Listing post ID.
 * @var bool   $show_summary    Whether to display the rating summary section.
 * @var bool   $show_form       Whether to display the review form.
 * @var int    $per_page        Number of reviews per page.
 * @var float  $avg             Average rating (0-5).
 * @var int    $total           Total number of reviews.
 * @var array  $dist            Rating distribution array keyed 1-5.
 * @var string $review_sort     Current review sort order.
 * @var array  $reviews         Array of review rows from the database.
 * @var bool   $user_reviewed   Whether the current user has already reviewed.
 * @var bool   $is_owner        Whether the current user is the listing author.
 * @var string $wrapper_attrs   Block wrapper attributes string.
 * @var string $unique_id       Block unique ID.
 * @var array  $attributes      Block attributes array.
 * @var string $listing_type_slug Listing type slug.
 * @var array  $review_criteria   Filtered review criteria for this listing type.
 * @var array  $view_data       Full view data array (all variables).
 */

defined( 'ABSPATH' ) || exit;

do_action( 'wb_listora_before_reviews', $view_data );
?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Rating Summary ─── ?>
	<?php if ( $show_summary ) : ?>
	<div class="listora-reviews__summary">
		<div class="listora-reviews__summary-score">
			<span class="listora-reviews__avg"><?php echo esc_html( $avg ?: '—' ); ?></span>
			<div class="listora-reviews__avg-stars">
				<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
				<svg class="listora-rating__star <?php echo esc_attr( $s > round( $avg ) ? 'listora-rating__star--empty' : '' ); ?>" viewBox="0 0 24 24" width="18" height="18">
					<path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
				</svg>
				<?php endfor; ?>
			</div>
			<span class="listora-reviews__total">
				<?php /* translators: %s: number of reviews */ ?>
				<?php echo esc_html( sprintf( _n( '%s review', '%s reviews', $total, 'wb-listora' ), number_format_i18n( $total ) ) ); ?>
			</span>
		</div>

		<div class="listora-reviews__distribution">
			<?php
			for ( $star = 5; $star >= 1; $star-- ) :
				$count = $dist[ $star ];
				$pct   = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;
				?>
			<div class="listora-reviews__bar-row">
				<span class="listora-reviews__bar-label"><?php echo esc_html( $star ); ?> ★</span>
				<div class="listora-reviews__bar">
					<div class="listora-reviews__bar-fill" style="width: <?php echo esc_attr( $pct ); ?>%"></div>
				</div>
				<span class="listora-reviews__bar-count"><?php echo esc_html( $count ); ?></span>
			</div>
			<?php endfor; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php // ─── Sort + Write Review ─── ?>
	<div class="listora-reviews__toolbar">
		<select class="listora-input listora-select listora-reviews__sort" aria-label="<?php esc_attr_e( 'Sort reviews', 'wb-listora' ); ?>" data-wp-on--change="actions.sortReviews">
			<option value="newest" <?php selected( $review_sort, 'newest' ); ?>><?php esc_html_e( 'Most Recent', 'wb-listora' ); ?></option>
			<option value="highest" <?php selected( $review_sort, 'highest' ); ?>><?php esc_html_e( 'Highest Rated', 'wb-listora' ); ?></option>
			<option value="lowest" <?php selected( $review_sort, 'lowest' ); ?>><?php esc_html_e( 'Lowest Rated', 'wb-listora' ); ?></option>
			<option value="helpful" <?php selected( $review_sort, 'helpful' ); ?>><?php esc_html_e( 'Most Helpful', 'wb-listora' ); ?></option>
		</select>

		<?php if ( $show_form && ! $user_reviewed && ! $is_owner && is_user_logged_in() ) : ?>
		<button type="button" class="listora-btn listora-btn--primary listora-reviews__write-btn" data-wp-on--click="actions.toggleReviewForm">
			<?php esc_html_e( 'Write a Review', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<?php // ─── Review Form ─── ?>
	<?php if ( $show_form && ! $user_reviewed && ! $is_owner ) : ?>
		<?php wb_listora_get_template( 'blocks/listing-reviews/review-form.php', $view_data ); ?>
	<?php endif; ?>

	<?php // ─── Review List ─── ?>
	<div class="listora-reviews__list">
		<?php if ( empty( $reviews ) ) : ?>
		<div class="listora-reviews__empty listora-card listora-card--empty" role="status">
			<div class="listora-empty">
				<span class="listora-empty__icon" aria-hidden="true">
					<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
						<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
					</svg>
				</span>
				<h3 class="listora-empty__title"><?php esc_html_e( 'No reviews yet', 'wb-listora' ); ?></h3>
				<p class="listora-empty__desc"><?php esc_html_e( 'Be the first to share your experience with this listing.', 'wb-listora' ); ?></p>
			</div>
		</div>
		<?php else : ?>
			<?php
			foreach ( $reviews as $review ) :
				$reviewer      = get_user_by( 'id', $review['user_id'] );
				// Shared with the REST list so both cannot drift, and so a
				// deleted account reads "Former member" rather than the
				// "Anonymous" that belongs to eraser-anonymised rows.
				$reviewer_name = wb_listora_review_author_name( (int) $review['user_id'] );
				$avatar_url    = $reviewer ? get_avatar_url( $review['user_id'], array( 'size' => 48 ) ) : '';
				$reviewer_id   = $reviewer ? (int) $reviewer->ID : 0;
				$reviewer_url  = $reviewer_id ? (string) apply_filters( 'wb_listora_member_profile_url', '', $reviewer_id, 'review_user' ) : '';

				$card_data              = array_merge(
					$view_data,
					array(
						'review'        => $review,
						'reviewer_name' => $reviewer_name,
						'reviewer_id'   => $reviewer_id,
						'reviewer_url'  => $reviewer_url,
						'avatar_url'    => $avatar_url,
					)
				);
				$card_data['view_data'] = $card_data;

				wb_listora_get_template( 'blocks/listing-reviews/review-card.php', $card_data );
			endforeach;
			?>

			<?php if ( $total > $per_page ) : ?>
			<button class="listora-btn listora-btn--secondary listora-reviews__load-more" data-wp-on--click="actions.loadMoreReviews">
				<?php esc_html_e( 'Load More Reviews', 'wb-listora' ); ?>
			</button>
			<?php endif; ?>
		<?php endif; ?>
	</div>

	<?php // ─── Report Review Modal ─── ?>
	<?php // Single page-level dialog reused by every review card. The clicked ?>
	<?php // card's reviewId is captured into state.reportReviewId by ?>
	<?php // actions.showReportModal (src/interactivity/store.js). Reuses the ?>
	<?php // .listora-detail__modal family already shared by the claim/login ?>
	<?php // modals — no new component. Replaces the inaccessible native ?>
	<?php // prompt() that was CSP-blocked. ?>
	<?php if ( ! empty( $reviews ) ) : ?>
	<div class="listora-detail__modal" id="listora-report-review-modal" data-wp-class--is-open="state.isReportReviewModalOpen">
		<div class="listora-detail__modal-backdrop" data-wp-on--click="actions.closeReportReviewModal"></div>
		<div class="listora-detail__modal-content" id="listora-report-review-dialog" role="dialog" aria-modal="true" aria-labelledby="listora-report-review-title" tabindex="-1" data-wp-on--keydown="actions.handleReportReviewKeydown">
			<button type="button" class="listora-detail__modal-close" data-wp-on--click="actions.closeReportReviewModal" aria-label="<?php esc_attr_e( 'Close', 'wb-listora' ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<line x1="18" y1="6" x2="6" y2="18"></line>
					<line x1="6" y1="6" x2="18" y2="18"></line>
				</svg>
			</button>
			<h3 id="listora-report-review-title"><?php esc_html_e( 'Report This Review', 'wb-listora' ); ?></h3>
			<p class="listora-detail__modal-desc"><?php esc_html_e( 'Tell us what is wrong so our team can review it.', 'wb-listora' ); ?></p>
			<form class="listora-detail__report-form" data-wp-on--submit="actions.submitReviewReport">
				<div class="listora-detail__report-body">
					<div class="listora-submission__field">
						<label for="listora-report-review-reason" class="listora-submission__label"><?php esc_html_e( 'Reason', 'wb-listora' ); ?> *</label>
						<select id="listora-report-review-reason" name="reason" class="listora-input" required data-wp-on--change="actions.setReportReviewReason">
							<option value=""><?php esc_html_e( 'Select a reason…', 'wb-listora' ); ?></option>
							<?php // Review reasons, NOT the listing enum — the listing keys are rejected by /reviews/{id}/report (BC 10154926676). ?>
							<?php foreach ( wb_listora_get_review_report_reasons() as $listora_reason_key => $listora_reason_label ) : ?>
							<option value="<?php echo esc_attr( $listora_reason_key ); ?>"><?php echo esc_html( $listora_reason_label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="listora-detail__report-actions">
						<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Report', 'wb-listora' ); ?></button>
						<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="actions.closeReportReviewModal"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
					</div>
				</div>
			</form>
		</div>
	</div>
	<?php endif; ?>
</div>
<?php
do_action( 'wb_listora_after_reviews', $view_data );
