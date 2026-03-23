<?php
/**
 * Listing Reviews block — rating summary + review list + form.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

$post_id      = get_the_ID();
$show_summary = $attributes['showSummary'] ?? true;
$show_form    = $attributes['showForm'] ?? true;
$per_page     = $attributes['perPage'] ?? 10;
$default_sort = $attributes['defaultSort'] ?? 'newest';

if ( ! $post_id || 'listora_listing' !== get_post_type( $post_id ) ) {
	return;
}

global $wpdb;
$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

// Rating summary.
$summary = $wpdb->get_row(
	$wpdb->prepare(
		"SELECT
		AVG(overall_rating) as avg_rating,
		COUNT(*) as total,
		SUM(CASE WHEN overall_rating = 5 THEN 1 ELSE 0 END) as s5,
		SUM(CASE WHEN overall_rating = 4 THEN 1 ELSE 0 END) as s4,
		SUM(CASE WHEN overall_rating = 3 THEN 1 ELSE 0 END) as s3,
		SUM(CASE WHEN overall_rating = 2 THEN 1 ELSE 0 END) as s2,
		SUM(CASE WHEN overall_rating = 1 THEN 1 ELSE 0 END) as s1
	FROM {$prefix}reviews WHERE listing_id = %d AND status = 'approved'",
		$post_id
	),
	ARRAY_A
);

$avg   = $summary ? round( (float) $summary['avg_rating'], 1 ) : 0;
$total = $summary ? (int) $summary['total'] : 0;
$dist  = array(
	5 => (int) ( $summary['s5'] ?? 0 ),
	4 => (int) ( $summary['s4'] ?? 0 ),
	3 => (int) ( $summary['s3'] ?? 0 ),
	2 => (int) ( $summary['s2'] ?? 0 ),
	1 => (int) ( $summary['s1'] ?? 0 ),
);

// Get reviews.
$reviews = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$prefix}reviews
	WHERE listing_id = %d AND status = 'approved'
	ORDER BY created_at DESC LIMIT %d",
		$post_id,
		$per_page
	),
	ARRAY_A
);

// Check if current user already reviewed.
$user_reviewed = false;
if ( is_user_logged_in() ) {
	$user_reviewed = (bool) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id FROM {$prefix}reviews WHERE listing_id = %d AND user_id = %d",
			$post_id,
			get_current_user_id()
		)
	);
}

// Check if current user is listing author.
$is_owner = is_user_logged_in() && (int) get_post_field( 'post_author', $post_id ) === get_current_user_id();

$context = wp_json_encode(
	array(
		'listingId'  => $post_id,
		'reviewSort' => $default_sort,
	)
);

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-reviews',
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
	)
);
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Rating Summary ─── ?>
	<?php if ( $show_summary ) : ?>
	<div class="listora-reviews__summary">
		<div class="listora-reviews__summary-score">
			<span class="listora-reviews__avg"><?php echo esc_html( $avg ?: '—' ); ?></span>
			<div class="listora-reviews__avg-stars">
				<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
				<svg class="listora-rating__star <?php echo $s > round( $avg ) ? 'listora-rating__star--empty' : ''; ?>" viewBox="0 0 24 24" width="18" height="18">
					<path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
				</svg>
				<?php endfor; ?>
			</div>
			<span class="listora-reviews__total">
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
		<select class="listora-input listora-select listora-reviews__sort" aria-label="<?php esc_attr_e( 'Sort reviews', 'wb-listora' ); ?>">
			<option value="newest"><?php esc_html_e( 'Most Recent', 'wb-listora' ); ?></option>
			<option value="highest"><?php esc_html_e( 'Highest Rated', 'wb-listora' ); ?></option>
			<option value="lowest"><?php esc_html_e( 'Lowest Rated', 'wb-listora' ); ?></option>
			<option value="helpful"><?php esc_html_e( 'Most Helpful', 'wb-listora' ); ?></option>
		</select>

		<?php if ( $show_form && ! $user_reviewed && ! $is_owner && is_user_logged_in() ) : ?>
		<button type="button" class="listora-btn listora-btn--primary listora-reviews__write-btn" data-wp-on--click="actions.toggleReviewForm">
			<?php esc_html_e( 'Write a Review', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>
	</div>

	<?php // ─── Review Form ─── ?>
	<?php if ( $show_form && ! $user_reviewed && ! $is_owner ) : ?>
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
						<span class="listora-sr-only"><?php echo esc_html( $s ); ?> <?php echo esc_html( _n( 'star', 'stars', $s, 'wb-listora' ) ); ?></span>
					</label>
					<?php endfor; ?>
				</fieldset>
			</div>

			<div class="listora-submission__field">
				<label for="listora-review-title" class="listora-submission__label"><?php esc_html_e( 'Review Title', 'wb-listora' ); ?> <span class="required">*</span></label>
				<input type="text" id="listora-review-title" name="title" class="listora-input" required
					placeholder="<?php esc_attr_e( 'Summarize your experience', 'wb-listora' ); ?>" />
			</div>

			<div class="listora-submission__field">
				<label for="listora-review-content" class="listora-submission__label"><?php esc_html_e( 'Your Review', 'wb-listora' ); ?> <span class="required">*</span></label>
				<textarea id="listora-review-content" name="content" class="listora-input listora-submission__textarea" rows="5" required minlength="20"
					placeholder="<?php esc_attr_e( 'Share your experience (minimum 20 characters)', 'wb-listora' ); ?>"></textarea>
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

			<?php
			/**
			 * Filter review criteria fields for the current listing type.
			 *
			 * Pro uses this to inject multi-criteria rating inputs (food, service, etc.).
			 *
			 * @param array  $criteria  Default criteria (empty array).
			 * @param string $type_slug Listing type slug.
			 */
			$listing_type_slug = '';
			$listing_type_obj  = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $post_id );
			if ( $listing_type_obj ) {
				$listing_type_slug = $listing_type_obj->get_slug();
			}
			$review_criteria = apply_filters( 'wb_listora_review_criteria', array(), $listing_type_slug );

			if ( ! empty( $review_criteria ) ) :
			?>
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

			<div class="listora-reviews__form-actions">
				<button type="submit" class="listora-btn listora-btn--primary"><?php esc_html_e( 'Submit Review', 'wb-listora' ); ?></button>
				<button type="button" class="listora-btn listora-btn--text" data-wp-on--click="actions.toggleReviewForm"><?php esc_html_e( 'Cancel', 'wb-listora' ); ?></button>
			</div>

			<div class="listora-reviews__form-message" hidden></div>
		</form>
		<?php endif; ?>
	</div>
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
				?>
			<div class="listora-reviews__review" id="review-<?php echo esc_attr( $review['id'] ); ?>">
				<div class="listora-reviews__review-header">
					<?php if ( $avatar_url ) : ?>
					<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" class="listora-reviews__avatar" width="40" height="40" loading="lazy" />
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
			<?php endforeach; ?>

			<?php if ( $total > $per_page ) : ?>
			<button class="listora-btn listora-btn--secondary listora-reviews__load-more" data-wp-on--click="actions.loadMoreReviews">
				<?php esc_html_e( 'Load More Reviews', 'wb-listora' ); ?>
			</button>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
