<?php
/**
 * User Dashboard block — modern sidebar layout.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

wp_enqueue_style( 'listora-shared' );

// Login check.
if ( ! is_user_logged_in() ) {
	$wrapper_attrs = get_block_wrapper_attributes( array( 'class' => 'listora-dashboard listora-dashboard--logged-out' ) );
	?>
	<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<div class="listora-dashboard__login-prompt">
			<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
				<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
			</svg>
			<h2><?php esc_html_e( 'My Dashboard', 'wb-listora' ); ?></h2>
			<p><?php esc_html_e( 'Please log in to view your dashboard.', 'wb-listora' ); ?></p>
			<a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>" class="listora-btn listora-btn--primary">
				<?php esc_html_e( 'Log In', 'wb-listora' ); ?>
			</a>
		</div>
	</div>
	<?php
	return;
}

$user_id        = get_current_user_id();
$user           = wp_get_current_user();
$default_tab    = $attributes['defaultTab'] ?? 'listings';
$show_listings  = $attributes['showListings'] ?? true;
$show_reviews   = $attributes['showReviews'] ?? true;
$show_favorites = $attributes['showFavorites'] ?? true;
$show_profile   = $attributes['showProfile'] ?? true;

global $wpdb;
$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

// ─── Stats ───
$listing_counts = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT post_status, COUNT(*) as cnt FROM {$wpdb->posts}
	WHERE post_type = 'listora_listing' AND post_author = %d
	GROUP BY post_status",
		$user_id
	),
	OBJECT_K
);

$stat_published = (int) ( $listing_counts['publish']->cnt ?? 0 );
$stat_pending   = (int) ( $listing_counts['pending']->cnt ?? 0 );
$stat_expired   = (int) ( $listing_counts['listora_expired']->cnt ?? 0 );
$stat_draft     = (int) ( $listing_counts['draft']->cnt ?? 0 );
$stat_total     = $stat_published + $stat_pending + $stat_expired + $stat_draft;

$review_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$prefix}reviews WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$user_id
	)
);

$favorite_count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$prefix}favorites WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$user_id
	)
);

// ─── User Listings ───
$user_listings = get_posts(
	array(
		'post_type'      => 'listora_listing',
		'post_status'    => array( 'publish', 'pending', 'draft', 'listora_expired', 'listora_rejected', 'listora_deactivated' ),
		'author'         => $user_id,
		'posts_per_page' => 20,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

// ─── User Reviews ───
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
$user_reviews = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT r.*, si.title as listing_title
	FROM {$prefix}reviews r
	LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id
	WHERE r.user_id = %d
	ORDER BY r.created_at DESC LIMIT 20",
		$user_id
	),
	ARRAY_A
);

$reviews_received = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT r.*, si.title as listing_title
	FROM {$prefix}reviews r
	INNER JOIN {$wpdb->posts} p ON r.listing_id = p.ID
	LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id
	WHERE p.post_author = %d AND r.user_id != %d AND r.status = 'approved'
	ORDER BY r.created_at DESC LIMIT 20",
		$user_id,
		$user_id
	),
	ARRAY_A
);
// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// ─── User Favorites ───
$favorite_ids = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT listing_id FROM {$prefix}favorites WHERE user_id = %d ORDER BY created_at DESC LIMIT 20", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$user_id
	)
);

$context = wp_json_encode( array( 'activeTab' => $default_tab ) );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-dashboard',
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
	)
);

// Status labels with pill classes.
$status_map = array(
	'publish'             => array( 'label' => __( 'Published', 'wb-listora' ), 'class' => 'listora-dashboard__status--publish' ),
	'pending'             => array( 'label' => __( 'Pending Review', 'wb-listora' ), 'class' => 'listora-dashboard__status--pending' ),
	'draft'               => array( 'label' => __( 'Draft', 'wb-listora' ), 'class' => 'listora-dashboard__status--draft' ),
	'listora_expired'     => array( 'label' => __( 'Expired', 'wb-listora' ), 'class' => 'listora-dashboard__status--expired' ),
	'listora_rejected'    => array( 'label' => __( 'Rejected', 'wb-listora' ), 'class' => 'listora-dashboard__status--rejected' ),
	'listora_deactivated' => array( 'label' => __( 'Deactivated', 'wb-listora' ), 'class' => 'listora-dashboard__status--deactivated' ),
);
?>

<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php // ─── Sidebar Navigation ─── ?>
	<nav class="listora-dashboard__sidebar" aria-label="<?php esc_attr_e( 'Dashboard navigation', 'wb-listora' ); ?>">
		<div class="listora-dashboard__sidebar-header">
			<p class="listora-dashboard__user-name"><?php echo esc_html( $user->display_name ); ?></p>
			<span class="listora-dashboard__user-email"><?php echo esc_html( $user->user_email ); ?></span>
		</div>

		<?php if ( $show_listings ) : ?>
		<button class="listora-dashboard__nav-item <?php echo 'listings' === $default_tab ? 'is-active' : ''; ?>"
			data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"listings"}'
			id="dash-tab-listings" role="tab" aria-selected="<?php echo 'listings' === $default_tab ? 'true' : 'false'; ?>" aria-controls="dash-panel-listings">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
			<?php esc_html_e( 'My Listings', 'wb-listora' ); ?>
			<span class="listora-dashboard__nav-count"><?php echo esc_html( $stat_total ); ?></span>
		</button>
		<?php endif; ?>

		<?php if ( $show_reviews ) : ?>
		<button class="listora-dashboard__nav-item" data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"reviews"}'
			id="dash-tab-reviews" role="tab" aria-selected="false" aria-controls="dash-panel-reviews">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
			<?php esc_html_e( 'Reviews', 'wb-listora' ); ?>
			<span class="listora-dashboard__nav-count"><?php echo esc_html( $review_count ); ?></span>
		</button>
		<?php endif; ?>

		<?php if ( $show_favorites ) : ?>
		<button class="listora-dashboard__nav-item" data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"favorites"}'
			id="dash-tab-favorites" role="tab" aria-selected="false" aria-controls="dash-panel-favorites">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
			<?php esc_html_e( 'Favorites', 'wb-listora' ); ?>
			<span class="listora-dashboard__nav-count"><?php echo esc_html( $favorite_count ); ?></span>
		</button>
		<?php endif; ?>

		<?php if ( $show_profile ) : ?>
		<button class="listora-dashboard__nav-item" data-wp-on--click="actions.switchDashTab" data-wp-context='{"tabId":"profile"}'
			id="dash-tab-profile" role="tab" aria-selected="false" aria-controls="dash-panel-profile">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
			<?php esc_html_e( 'Profile', 'wb-listora' ); ?>
		</button>
		<?php endif; ?>

		<?php
		/**
		 * Fires inside the dashboard sidebar nav, before the closing nav tag.
		 *
		 * Pro hooks in here to add nav buttons for Saved Searches and Analytics panels.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id Current user ID.
		 */
		do_action( 'wb_listora_dashboard_nav_items', $user_id );
		?>
	</nav>

	<?php // ─── Main Content ─── ?>
	<div class="listora-dashboard__main">

		<?php // ─── Header ─── ?>
		<div class="listora-dashboard__header">
			<h1 class="listora-dashboard__title">
				<?php
				printf(
					/* translators: %s: user display name */
					esc_html__( 'Hello, %s!', 'wb-listora' ),
					esc_html( $user->display_name )
				);
				?>
			</h1>
			<a href="<?php echo esc_url( home_url( '/add-listing/' ) ); ?>" class="listora-btn listora-btn--primary">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
				<?php esc_html_e( 'Add Listing', 'wb-listora' ); ?>
			</a>
		</div>

		<?php // ─── Stats Cards ─── ?>
		<div class="listora-dashboard__stats">
			<div class="listora-dashboard__stat">
				<span class="listora-dashboard__stat-icon listora-dashboard__stat-icon--active">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
				</span>
				<span class="listora-dashboard__stat-content">
					<span class="listora-dashboard__stat-value"><?php echo esc_html( $stat_published ); ?></span>
					<span class="listora-dashboard__stat-label"><?php esc_html_e( 'Active', 'wb-listora' ); ?></span>
				</span>
			</div>
			<div class="listora-dashboard__stat">
				<span class="listora-dashboard__stat-icon listora-dashboard__stat-icon--pending">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
				</span>
				<span class="listora-dashboard__stat-content">
					<span class="listora-dashboard__stat-value"><?php echo esc_html( $stat_pending ); ?></span>
					<span class="listora-dashboard__stat-label"><?php esc_html_e( 'Pending', 'wb-listora' ); ?></span>
				</span>
			</div>
			<div class="listora-dashboard__stat">
				<span class="listora-dashboard__stat-icon listora-dashboard__stat-icon--reviews">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
				</span>
				<span class="listora-dashboard__stat-content">
					<span class="listora-dashboard__stat-value"><?php echo esc_html( $review_count ); ?></span>
					<span class="listora-dashboard__stat-label"><?php esc_html_e( 'Reviews', 'wb-listora' ); ?></span>
				</span>
			</div>
			<div class="listora-dashboard__stat">
				<span class="listora-dashboard__stat-icon listora-dashboard__stat-icon--saved">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
				</span>
				<span class="listora-dashboard__stat-content">
					<span class="listora-dashboard__stat-value"><?php echo esc_html( $favorite_count ); ?></span>
					<span class="listora-dashboard__stat-label"><?php esc_html_e( 'Saved', 'wb-listora' ); ?></span>
				</span>
			</div>
		</div>

		<?php // ─── My Listings Panel ─── ?>
		<?php if ( $show_listings ) : ?>
		<div role="tabpanel" id="dash-panel-listings" aria-labelledby="dash-tab-listings" class="listora-dashboard__panel"
			<?php echo 'listings' !== $default_tab ? 'hidden' : ''; ?>>

			<?php if ( empty( $user_listings ) ) : ?>
			<div class="listora-dashboard__empty">
				<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M12 8v8M8 12h8"/></svg>
				<h3><?php esc_html_e( 'No listings yet', 'wb-listora' ); ?></h3>
				<p><?php esc_html_e( 'Create your first listing and start getting discovered.', 'wb-listora' ); ?></p>
				<a href="<?php echo esc_url( home_url( '/add-listing/' ) ); ?>" class="listora-btn listora-btn--primary">
					<?php esc_html_e( 'Add Your First Listing', 'wb-listora' ); ?>
				</a>
			</div>
			<?php else : ?>
			<div class="listora-dashboard__listing-list">
				<?php
				foreach ( $user_listings as $row_index => $listing ) :
					$status_info = $status_map[ $listing->post_status ] ?? array(
						'label' => $listing->post_status,
						'class' => 'listora-dashboard__status--draft',
					);
					$thumb_url = get_the_post_thumbnail_url( $listing->ID, 'thumbnail' );
					$type      = \WBListora\Core\Listing_Type_Registry::instance()->get_for_post( $listing->ID );
					?>
				<div class="listora-dashboard__listing-row" style="--row-index: <?php echo (int) $row_index; ?>">
					<div class="listora-dashboard__listing-thumb">
						<?php if ( $thumb_url ) : ?>
						<img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy" />
						<?php else : ?>
						<div class="listora-dashboard__listing-thumb-placeholder">
							<?php if ( $type ) : ?>
							<span class="dashicons <?php echo esc_attr( $type->get_icon() ); ?>" aria-hidden="true"></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
					<div class="listora-dashboard__listing-info">
						<h3 class="listora-dashboard__listing-title">
							<a href="<?php echo esc_url( get_permalink( $listing->ID ) ); ?>"><?php echo esc_html( $listing->post_title ); ?></a>
						</h3>
						<div class="listora-dashboard__listing-meta">
							<span class="listora-dashboard__status <?php echo esc_attr( $status_info['class'] ); ?>">
								<?php echo esc_html( $status_info['label'] ); ?>
							</span>
							<?php if ( $type ) : ?>
							<span><?php echo esc_html( $type->get_name() ); ?></span>
							<?php endif; ?>
							<?php
							$exp = get_post_meta( $listing->ID, '_listora_expiration_date', true );
							if ( $exp && 'publish' === $listing->post_status ) :
								?>
							<span>
								<?php
								printf(
									/* translators: %s: expiration date */
									esc_html__( 'Expires: %s', 'wb-listora' ),
									esc_html( wp_date( get_option( 'date_format' ), strtotime( $exp ) ) )
								);
								?>
							</span>
							<?php endif; ?>
						</div>
					</div>
					<div class="listora-dashboard__listing-actions">
						<a href="<?php echo esc_url( home_url( '/add-listing/?edit=' . $listing->ID ) ); ?>" class="listora-btn listora-btn--icon" title="<?php esc_attr_e( 'Edit', 'wb-listora' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 3a2.85 2.85 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
						</a>
						<a href="<?php echo esc_url( get_permalink( $listing->ID ) ); ?>" class="listora-btn listora-btn--icon" title="<?php esc_attr_e( 'View', 'wb-listora' ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
						</a>
						<div class="listora-dashboard__menu-wrap" data-wp-interactive="listora/directory">
							<button type="button" class="listora-btn listora-btn--icon" data-wp-on--click="actions.toggleListingMenu" title="<?php esc_attr_e( 'More actions', 'wb-listora' ); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
							</button>
							<div class="listora-dashboard__menu-dropdown" hidden>
								<?php if ( 'listora_expired' === $listing->post_status ) : ?>
								<button class="listora-dashboard__menu-item"><?php esc_html_e( 'Renew', 'wb-listora' ); ?></button>
								<?php endif; ?>
								<button class="listora-dashboard__menu-item listora-dashboard__menu-item--danger"
									data-wp-on--click="actions.deactivateListing"
									data-wp-context='<?php echo wp_json_encode( array( 'listingId' => $listing->ID ) ); ?>'>
									<?php esc_html_e( 'Deactivate', 'wb-listora' ); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php // ─── Reviews Panel ─── ?>
		<?php if ( $show_reviews ) : ?>
		<div role="tabpanel" id="dash-panel-reviews" aria-labelledby="dash-tab-reviews" class="listora-dashboard__panel" hidden>

			<?php if ( ! empty( $user_reviews ) ) : ?>
			<h3 class="listora-dashboard__section-title"><?php esc_html_e( 'Reviews I\'ve Written', 'wb-listora' ); ?></h3>
				<?php foreach ( $user_reviews as $review ) : ?>
			<div class="listora-dashboard__review-row">
				<div class="listora-dashboard__review-header">
					<span class="listora-rating">
						<?php for ( $s = 1; $s <= 5; $s++ ) : ?>
						<svg class="listora-rating__star <?php echo $s > (int) $review['overall_rating'] ? 'listora-rating__star--empty' : ''; ?>" viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
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
						<svg class="listora-rating__star <?php echo $s > (int) $review['overall_rating'] ? 'listora-rating__star--empty' : ''; ?>" viewBox="0 0 24 24" width="14" height="14"><path fill="currentColor" d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
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
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php // ─── Favorites Panel ─── ?>
		<?php if ( $show_favorites ) : ?>
		<div role="tabpanel" id="dash-panel-favorites" aria-labelledby="dash-tab-favorites" class="listora-dashboard__panel" hidden>

			<?php if ( empty( $favorite_ids ) ) : ?>
			<div class="listora-dashboard__empty">
				<svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
				<h3><?php esc_html_e( 'No saved listings', 'wb-listora' ); ?></h3>
				<p><?php esc_html_e( 'Save listings you like by clicking the heart icon.', 'wb-listora' ); ?></p>
			</div>
			<?php else : ?>
			<div class="listora-dashboard__favorites-grid listora-grid" style="--listora-grid-columns: 2;">
				<?php
				foreach ( $favorite_ids as $fav_index => $fav_id ) :
					$fav_data = wb_listora_prepare_card_data( (int) $fav_id );
					if ( ! $fav_data ) {
						continue;
					}
					$attributes = array(
						'listingId'     => (int) $fav_id,
						'layout'        => 'standard',
						'showRating'    => true,
						'showFavorite'  => true,
						'showType'      => true,
						'showFeatures'  => false,
						'maxMetaFields' => 3,
						'_listing_data' => $fav_data,
						'_card_index'   => $fav_index,
					);
					include WB_LISTORA_PLUGIN_DIR . 'blocks/listing-card/render.php';
				endforeach;
				?>
			</div>
			<?php endif; ?>
		</div>
		<?php endif; ?>

		<?php
		/**
		 * Fires after the standard dashboard panels (Listings, Reviews, Favorites).
		 *
		 * Pro hooks in here to render additional panels such as Saved Searches and Analytics.
		 * Each hooked renderer is responsible for outputting both a sidebar nav button
		 * (ideally via a separate `wb_listora_dashboard_nav_items` action) and the panel
		 * `<div role="tabpanel">` markup that follows the same conventions as core panels.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id Current user ID.
		 */
		do_action( 'wb_listora_dashboard_sections', $user_id );
		?>

		<?php // ─── Profile Panel ─── ?>
		<?php if ( $show_profile ) : ?>
		<div role="tabpanel" id="dash-panel-profile" aria-labelledby="dash-tab-profile" class="listora-dashboard__panel" hidden>
			<form class="listora-dashboard__profile-form" method="post" action="">
				<?php wp_nonce_field( 'listora_update_profile', 'listora_profile_nonce' ); ?>

				<div class="listora-dashboard__profile-grid">
					<div class="listora-submission__field">
						<label for="listora-display-name" class="listora-submission__label"><?php esc_html_e( 'Display Name', 'wb-listora' ); ?></label>
						<input type="text" id="listora-display-name" name="display_name" class="listora-input"
							value="<?php echo esc_attr( $user->display_name ); ?>" />
					</div>

					<div class="listora-submission__field">
						<label for="listora-email" class="listora-submission__label"><?php esc_html_e( 'Email', 'wb-listora' ); ?></label>
						<input type="email" id="listora-email" name="email" class="listora-input"
							value="<?php echo esc_attr( $user->user_email ); ?>" />
					</div>

					<div class="listora-submission__field listora-submission__field--full">
						<label for="listora-bio" class="listora-submission__label"><?php esc_html_e( 'Bio', 'wb-listora' ); ?></label>
						<textarea id="listora-bio" name="description" class="listora-input listora-submission__textarea" rows="3"><?php echo esc_textarea( $user->description ); ?></textarea>
					</div>
				</div>

				<div class="listora-dashboard__profile-section">
					<h3 class="listora-dashboard__profile-section-title"><?php esc_html_e( 'Email Notifications', 'wb-listora' ); ?></h3>

					<?php
					$prefs         = get_user_meta( $user_id, '_listora_notification_prefs', true ) ?: array();
					$notifications = array(
						'review_received'    => __( 'New review on my listing', 'wb-listora' ),
						'listing_status'     => __( 'Listing status changes', 'wb-listora' ),
						'listing_expiration' => __( 'Listing expiration reminders', 'wb-listora' ),
					);
					foreach ( $notifications as $pref_key => $pref_label ) :
						$checked = ! isset( $prefs[ $pref_key ] ) || $prefs[ $pref_key ];
						?>
					<div class="listora-dashboard__notification-toggle">
						<span class="listora-dashboard__notification-label"><?php echo esc_html( $pref_label ); ?></span>
						<label class="listora-toggle">
							<input type="checkbox" name="notification_prefs[<?php echo esc_attr( $pref_key ); ?>]" value="1"
								class="listora-toggle__input" <?php checked( $checked ); ?> />
							<span class="listora-toggle__track"></span>
						</label>
					</div>
					<?php endforeach; ?>
				</div>

				<div style="margin-block-start: var(--listora-gap-lg);">
					<button type="submit" name="listora_update_profile" class="listora-btn listora-btn--primary">
						<?php esc_html_e( 'Save Changes', 'wb-listora' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php endif; ?>

	</div>
</div>
