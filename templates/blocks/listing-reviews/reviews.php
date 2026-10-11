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
 * @var bool   $user_review_pending Whether the current user's review here awaits moderation.
 * @var bool   $user_reviewed   Whether the current user has used up their review here (one per listing is on and they reviewed).
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
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() escapes its output. ?>>

	<?php // ─── Rating Summary ─── ?>
	<?php if ( $show_summary ) : ?>
	<div class="listora-reviews__summary">
		<div class="listora-reviews__summary-score">
			<span class="listora-reviews__avg"><?php echo esc_html( $avg ?: '—' ); ?></span>
			<div class="listora-reviews__avg-stars">
				<?php wb_listora_render_stars( (float) $avg, 18 ); ?>
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

	<?php if ( $show_form && ! empty( $user_review_pending ) ) : ?>
	<p class="listora-reviews__pending-notice" role="status">
		<?php esc_html_e( 'You have already submitted a review for this listing. It is waiting for moderation and will appear once approved.', 'wb-listora' ); ?>
	</p>
	<?php endif; ?>

	<?php // ─── Review Form ─── ?>
	<?php if ( $show_form && ! $user_reviewed && ! $is_owner ) : ?>
		<?php wb_listora_get_template( 'blocks/listing-reviews/review-form.php', $view_data ); ?>
	<?php endif; ?>

	<?php // ─── Review List ─── ?>
	<div class="listora-reviews__list">
		<?php if ( empty( $reviews ) ) : ?>
			<?php
			wb_listora_render_empty_state(
				array(
					'icon'        => 'message-square',
					'title'       => __( 'No reviews yet', 'wb-listora' ),
					'description' => __( 'Be the first to share your experience with this listing.', 'wb-listora' ),
					'class'       => 'listora-reviews__empty',
					'attrs'       => 'role="status"',
				)
			);
			?>
		<?php else : ?>
			<?php
			foreach ( $reviews as $review ) :
				$reviewer = get_user_by( 'id', $review['user_id'] );
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
		<?php wb_listora_get_template( 'blocks/reviews/report-modal.php', $view_data ); ?>
	<?php endif; ?>
</div>
<?php
do_action( 'wb_listora_after_reviews', $view_data );
