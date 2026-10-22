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

// Owner-reply UI respects Settings ▸ Reviews ▸ Enable replies. When off, the Reply trigger
// and inline form are suppressed; any already-published owner reply still
// displays (it is existing public content, not a new write affordance).
$listora_allow_reply = wb_listora_review_replies_enabled();

// A member sees their own pending and rejected reviews too, so say which are
// not live. Approved rows carry no label.
$listora_review_status_labels = array(
	'pending'  => array(
		'label' => __( 'Awaiting approval', 'wb-listora' ),
		'class' => 'listora-dashboard__status--pending',
	),
	'rejected' => array(
		'label' => __( 'Not published', 'wb-listora' ),
		'class' => 'listora-dashboard__status--rejected',
	),
);

do_action( 'wb_listora_before_dashboard_reviews', $view_data );
?>
<div role="tabpanel" id="dash-panel-reviews" aria-labelledby="dash-tab-reviews" class="listora-dashboard__panel" hidden>

	<?php if ( ! empty( $user_reviews ) ) : ?>
	<h3 class="listora-dashboard__section-title"><?php esc_html_e( 'Reviews I\'ve Written', 'wb-listora' ); ?></h3>
	<div class="listora-dashboard__review-list">
		<?php foreach ( $user_reviews as $review ) : ?>
	<div class="listora-dashboard__review-row">
		<div class="listora-dashboard__review-header">
			<?php wb_listora_render_stars( (int) $review['overall_rating'] ); ?>
			<?php
			// Link the listing title to the single listing so the reviewer
			// can re-check it. The plugin's anchor-defence rule keeps the
			// text-coloured-no-underline look (underline returns on hover).
			$listing_id  = isset( $review['listing_id'] ) ? (int) $review['listing_id'] : 0;
			$listing_url = $listing_id ? get_permalink( $listing_id ) : '';
			?>
			<span class="listora-dashboard__review-listing">
				<?php if ( $listing_url ) : ?>
					<a class="listora-dashboard__review-listing-link" href="<?php echo esc_url( $listing_url ); ?>">
						<?php echo esc_html( $review['listing_title'] ?: __( 'Deleted listing', 'wb-listora' ) ); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html( $review['listing_title'] ?: __( 'Deleted listing', 'wb-listora' ) ); ?>
				<?php endif; ?>
			</span>
			<span class="listora-dashboard__review-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $review['created_at'] ) ) ); ?></span>
			<?php if ( isset( $review['status'], $listora_review_status_labels[ $review['status'] ] ) ) : ?>
			<span class="listora-dashboard__status <?php echo esc_attr( $listora_review_status_labels[ $review['status'] ]['class'] ); ?>"><?php echo esc_html( $listora_review_status_labels[ $review['status'] ]['label'] ); ?></span>
			<?php endif; ?>
		</div>
			<?php if ( $review['title'] ) : ?>
		<strong><?php echo esc_html( $review['title'] ); ?></strong>
		<?php endif; ?>
		<p class="listora-dashboard__review-content"><?php echo esc_html( wp_trim_words( $review['content'], 30 ) ); ?></p>
	</div>
	<?php endforeach; ?>
	</div>
		<?php
		// Two lists on one tab, so two pagers with separate query args — paging the
		// reviews you wrote must not reset the reviews you received (LST-F-06).
		if ( function_exists( 'wb_listora_render_pagination' ) ) {
			wb_listora_render_pagination(
				array(
					'tab'         => 'reviews',
					'page_arg'    => 'reviews_page',
					'page'        => isset( $reviews_written_page ) ? (int) $reviews_written_page : 1,
					'total_pages' => isset( $reviews_written_pages ) ? (int) $reviews_written_pages : 0,
					'label'       => __( 'Reviews written pagination', 'wb-listora' ),
				)
			);
		}
		?>
	<?php endif; ?>

	<?php if ( ! empty( $reviews_received ) ) : ?>
	<h3 class="listora-dashboard__section-title listora-dashboard__section-title--spaced"><?php esc_html_e( 'Reviews on My Listings', 'wb-listora' ); ?></h3>
	<div class="listora-dashboard__review-list">
		<?php foreach ( $reviews_received as $review ) : ?>
			<?php
			$review_id     = (int) ( $review['id'] ?? 0 );
			$reply_context = wp_interactivity_data_wp_context(
				array(
					'reviewId'        => $review_id,
					'replyOpen'       => false,
					'replySubmitting' => false,
					'replyError'      => '',
					'replyText'       => isset( $review['owner_reply'] ) ? (string) $review['owner_reply'] : '',
				)
			);
			?>
	<div class="listora-dashboard__review-row" <?php echo $reply_context; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by core, attribute already escaped. ?>>
		<div class="listora-dashboard__review-header">
			<div class="listora-dashboard__review-meta">
			<?php wb_listora_render_stars( (int) $review['overall_rating'] ); ?>
			<?php
			$listing_id  = isset( $review['listing_id'] ) ? (int) $review['listing_id'] : 0;
			$listing_url = $listing_id ? get_permalink( $listing_id ) : '';
			?>
			<span class="listora-dashboard__review-listing">
				<?php if ( $listing_url ) : ?>
					<a class="listora-dashboard__review-listing-link" href="<?php echo esc_url( $listing_url ); ?>">
						<?php echo esc_html( $review['listing_title'] ); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html( $review['listing_title'] ); ?>
				<?php endif; ?>
			</span>
			<span class="listora-dashboard__review-date"><?php echo esc_html( wp_date( get_option( 'date_format' ), strtotime( $review['created_at'] ) ) ); ?></span>
			</div>

				<?php if ( $listora_allow_reply ) : ?>
					<?php
					/*
					 * Right-aligned in the header, not floating below the reply
					 * (card: "very bad UX" — a short review + reply left the whole
					 * row's width empty with the trigger stranded under it). Every
					 * sibling dashboard row (My Listings, Favorites) puts its
					 * primary action top-right against the row's meta line; this
					 * now matches that instead of inventing a different shape.
					 */
					?>
			<button
				type="button"
				class="listora-btn listora-btn--text listora-btn--sm listora-dashboard__reply-trigger"
				data-wp-on--click="actions.openReplyForm"
				data-wp-class--is-hidden="context.replyOpen"
			>
					<?php echo empty( $review['owner_reply'] ) ? esc_html__( 'Reply', 'wb-listora' ) : esc_html__( 'Edit reply', 'wb-listora' ); ?>
			</button>
			<?php endif; ?>
		</div>
		<p class="listora-dashboard__review-content"><?php echo esc_html( wp_trim_words( $review['content'], 30 ) ); ?></p>

			<?php // Existing owner reply (if any) — a real quoted block. ?>
			<?php if ( ! empty( $review['owner_reply'] ) ) : ?>
		<blockquote class="listora-dashboard__owner-reply" data-wp-class--is-hidden="context.replyOpen">
			<strong><?php esc_html_e( 'Your reply:', 'wb-listora' ); ?></strong>
			<p><?php echo esc_html( $review['owner_reply'] ); ?></p>
		</blockquote>
		<?php endif; ?>

			<?php if ( $listora_allow_reply ) : ?>
				<?php // Inline reply form, toggled open by the Reply button. ?>
				<?php wb_listora_get_template( 'blocks/reviews/reply-form.php', array( 'review_id' => $review_id ) ); ?>
		<?php endif; ?>
	</div>
	<?php endforeach; ?>
	</div>
		<?php
		if ( function_exists( 'wb_listora_render_pagination' ) ) {
			wb_listora_render_pagination(
				array(
					'tab'         => 'reviews',
					'page_arg'    => 'received_page',
					'page'        => isset( $reviews_received_page ) ? (int) $reviews_received_page : 1,
					'total_pages' => isset( $reviews_received_pages ) ? (int) $reviews_received_pages : 0,
					'label'       => __( 'Reviews received pagination', 'wb-listora' ),
				)
			);
		}
		?>
	<?php endif; ?>

	<?php if ( empty( $user_reviews ) && empty( $reviews_received ) ) : ?>
		<?php
		wb_listora_render_empty_state(
			array(
				'icon'          => 'star',
				'title'         => __( 'No reviews yet', 'wb-listora' ),
				'description'   => __( 'Reviews you write and receive will appear here.', 'wb-listora' ),
				'secondary_cta' => array(
					'label' => __( 'Find a listing to review', 'wb-listora' ),
					'url'   => wb_listora_get_directory_url(),
				),
			)
		);
		?>
	<?php endif; ?>
</div>
<?php
do_action( 'wb_listora_after_dashboard_reviews', $view_data );
