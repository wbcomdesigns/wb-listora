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

$unique_id      = $attributes['uniqueId'] ?? '';
$user_id        = get_current_user_id();
$user           = wp_get_current_user();
$default_tab    = $attributes['defaultTab'] ?? 'listings';
$show_listings  = $attributes['showListings'] ?? true;
$show_reviews   = $attributes['showReviews'] ?? true;
$show_favorites = $attributes['showFavorites'] ?? true;
$show_profile   = $attributes['showProfile'] ?? true;

global $wpdb;
$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

// ─── Stats (cached 60s) ───
$cache_key  = 'listora_dashboard_stats_' . $user_id;
$stats_data = get_transient( $cache_key );

if ( false === $stats_data ) {
	$listing_counts = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_status, COUNT(*) as cnt FROM {$wpdb->posts}
		WHERE post_type = 'listora_listing' AND post_author = %d
		GROUP BY post_status",
			$user_id
		),
		OBJECT_K
	);

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

	$stats_data = array(
		'published' => (int) ( $listing_counts['publish']->cnt ?? 0 ),
		'pending'   => (int) ( $listing_counts['pending']->cnt ?? 0 ),
		'expired'   => (int) ( $listing_counts['listora_expired']->cnt ?? 0 ),
		'draft'     => (int) ( $listing_counts['draft']->cnt ?? 0 ),
		'reviews'   => $review_count,
		'favorites' => $favorite_count,
	);

	set_transient( $cache_key, $stats_data, 60 );
}

$stat_published = $stats_data['published'];
$stat_pending   = $stats_data['pending'];
$stat_expired   = $stats_data['expired'];
$stat_draft     = $stats_data['draft'];
$stat_total     = $stat_published + $stat_pending + $stat_expired + $stat_draft;
$review_count   = $stats_data['reviews'];
$favorite_count = $stats_data['favorites'];

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

$visibility_classes = \WBListora\Block_CSS::visibility_classes( $attributes );
$block_classes      = 'listora-block' . ( $unique_id ? ' listora-block-' . $unique_id : '' ) . ( $visibility_classes ? ' ' . $visibility_classes : '' );

$wrapper_attrs = get_block_wrapper_attributes(
	array(
		'class'               => 'listora-dashboard ' . $block_classes,
		'data-wp-interactive' => 'listora/directory',
		'data-wp-context'     => $context,
	)
);

// Status labels with pill classes.
$status_map = array(
	'publish'             => array(
		'label' => __( 'Published', 'wb-listora' ),
		'class' => 'listora-dashboard__status--publish',
	),
	'pending'             => array(
		'label' => __( 'Pending Review', 'wb-listora' ),
		'class' => 'listora-dashboard__status--pending',
	),
	'draft'               => array(
		'label' => __( 'Draft', 'wb-listora' ),
		'class' => 'listora-dashboard__status--draft',
	),
	'listora_expired'     => array(
		'label' => __( 'Expired', 'wb-listora' ),
		'class' => 'listora-dashboard__status--expired',
	),
	'listora_rejected'    => array(
		'label' => __( 'Rejected', 'wb-listora' ),
		'class' => 'listora-dashboard__status--rejected',
	),
	'listora_deactivated' => array(
		'label' => __( 'Deactivated', 'wb-listora' ),
		'class' => 'listora-dashboard__status--deactivated',
	),
);
?>

<?php echo \WBListora\Block_CSS::render( $unique_id, $attributes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
<div <?php echo $wrapper_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php
	// ─── Sidebar Navigation (overridable template) ───
	$nav_view_data = array(
		'user'           => $user,
		'user_id'        => $user_id,
		'default_tab'    => $default_tab,
		'show_listings'  => $show_listings,
		'show_reviews'   => $show_reviews,
		'show_favorites' => $show_favorites,
		'show_profile'   => $show_profile,
		'stat_total'     => $stat_total,
		'review_count'   => $review_count,
		'favorite_count' => $favorite_count,
	);
	$nav_view_data['view_data'] = $nav_view_data;
	wb_listora_get_template( 'blocks/user-dashboard/nav.php', $nav_view_data );
	?>

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

		<?php
		// ─── My Listings Panel (overridable template) ───
		if ( $show_listings ) :
			$listings_view_data = array(
				'user_id'       => $user_id,
				'default_tab'   => $default_tab,
				'user_listings' => $user_listings,
				'status_map'    => $status_map,
			);
			$listings_view_data['view_data'] = $listings_view_data;
			wb_listora_get_template( 'blocks/user-dashboard/tab-listings.php', $listings_view_data );
		endif;
		?>

		<?php
		// ─── Reviews Panel (overridable template) ───
		if ( $show_reviews ) :
			$reviews_view_data = array(
				'user_id'          => $user_id,
				'user_reviews'     => $user_reviews,
				'reviews_received' => $reviews_received,
			);
			$reviews_view_data['view_data'] = $reviews_view_data;
			wb_listora_get_template( 'blocks/user-dashboard/tab-reviews.php', $reviews_view_data );
		endif;
		?>

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

		<?php
		// ─── Profile Panel (overridable template) ───
		if ( $show_profile ) :
			$profile_view_data = array(
				'user_id' => $user_id,
				'user'    => $user,
			);
			$profile_view_data['view_data'] = $profile_view_data;
			wb_listora_get_template( 'blocks/user-dashboard/tab-profile.php', $profile_view_data );
		endif;
		?>

	</div>
</div>
