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
		<div class="listora-reviews__empty">
			<p><?php esc_html_e( 'No reviews yet. Be the first to leave a review!', 'wb-listora' ); ?></p>
		</div>
		<?php else : ?>
			<?php
			foreach ( $reviews as $review ) :
				$reviewer      = get_user_by( 'id', $review['user_id'] );
				$reviewer_name = $reviewer ? $reviewer->display_name : __( 'Anonymous', 'wb-listora' );
				$avatar_url    = $reviewer ? get_avatar_url( $review['user_id'], array( 'size' => 48 ) ) : '';

				$card_data = array_merge(
					$view_data,
					array(
						'review'        => $review,
						'reviewer_name' => $reviewer_name,
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
</div>
<?php
do_action( 'wb_listora_after_reviews', $view_data );
