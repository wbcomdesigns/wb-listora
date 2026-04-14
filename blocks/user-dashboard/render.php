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

// ─── Listing limit (per-role cap + credits overflow) ───
$limit_value        = \WBListora\Core\Listing_Limits::get_user_limit( $user_id );
$limit_count        = \WBListora\Core\Listing_Limits::get_user_count( $user_id );
$limit_remaining    = \WBListora\Core\Listing_Limits::get_remaining( $user_id );
$limit_unlimited    = ( -1 === $limit_value );
$limit_overflow     = \WBListora\Core\Listing_Limits::get_overflow_cost();
$limit_behavior     = \WBListora\Core\Listing_Limits::get_beyond_limit_behavior();
$limit_can_overflow = ( 'credits' === $limit_behavior ) && $limit_overflow > 0;
$limit_purchase_url = \WBListora\Core\Listing_Limits::get_purchase_url();
$limit_period       = \WBListora\Core\Listing_Limits::get_period();
$limit_period_label = \WBListora\Core\Listing_Limits::get_period_label();

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

// ─── Credits ───
$show_credits    = class_exists( '\\Wbcom\\Credits\\Credits' );
$credit_balance  = 0;
$credit_threshold = 0;
$credit_packs    = array();
$credit_ledger   = array();
$credit_purchase_url = '';

if ( $show_credits ) {
	$credit_balance      = (int) \Wbcom\Credits\Credits::get_balance( 'wb-listora', $user_id );
	$credit_threshold    = (int) get_option( 'wb_listora_low_credit_threshold', 5 );
	$credit_ledger       = \Wbcom\Credits\Credits::get_ledger( 'wb-listora', $user_id, 20, 0 );
	$credit_purchase_url = function_exists( 'wb_listora_get_credits_purchase_url' )
		? wb_listora_get_credits_purchase_url()
		: (string) get_option( 'wb_listora_credit_purchase_url', '' );

	// Build display-ready pack data from credit mappings.
	$credit_mappings = get_option( 'wb-listora_credit_mappings', array() );
	if ( is_array( $credit_mappings ) ) {
		foreach ( $credit_mappings as $map ) {
			if ( ! is_array( $map ) || empty( $map['adapter'] ) || empty( $map['item_id'] ) ) {
				continue;
			}

			$pack = array(
				'adapter'       => (string) $map['adapter'],
				'adapter_label' => isset( $map['adapter_label'] ) ? (string) $map['adapter_label'] : '',
				'item_id'       => (int) $map['item_id'],
				'item_label'    => isset( $map['item_label'] ) ? (string) $map['item_label'] : '',
				'credits'       => isset( $map['credits'] ) ? (int) $map['credits'] : 0,
				'price_html'    => '',
				'buy_url'       => '',
				'buy_label'     => __( 'Buy Now', 'wb-listora' ),
			);

			switch ( $pack['adapter'] ) {
				case 'woocommerce':
					if ( function_exists( 'wc_get_product' ) ) {
						$product = wc_get_product( $pack['item_id'] );
						if ( $product ) {
							$pack['price_html'] = $product->get_price_html();
							$pack['buy_url']    = $product->add_to_cart_url();
							if ( ! $pack['item_label'] ) {
								$pack['item_label'] = $product->get_name();
							}
						}
					}
					break;

				case 'woo_subscriptions':
					if ( function_exists( 'wc_get_product' ) ) {
						$product = wc_get_product( $pack['item_id'] );
						if ( $product ) {
							$pack['price_html'] = $product->get_price_html();
							$pack['buy_url']    = $product->add_to_cart_url();
							if ( ! $pack['item_label'] ) {
								$pack['item_label'] = $product->get_name();
							}
						}
					}
					$pack['buy_label'] = __( 'Subscribe', 'wb-listora' );
					break;

				case 'pmpro':
					if ( function_exists( 'pmpro_url' ) ) {
						$pack['buy_url'] = pmpro_url( 'checkout', '?level=' . $pack['item_id'] );
					}
					$pack['buy_label'] = __( 'Subscribe', 'wb-listora' );
					break;

				case 'memberpress':
					$permalink = get_permalink( $pack['item_id'] );
					if ( $permalink ) {
						$pack['buy_url'] = $permalink;
					}
					$pack['buy_label'] = __( 'Subscribe', 'wb-listora' );
					break;
			}

			$credit_packs[] = $pack;
		}
	}
}

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
	$nav_view_data              = array(
		'user'           => $user,
		'user_id'        => $user_id,
		'default_tab'    => $default_tab,
		'show_listings'  => $show_listings,
		'show_reviews'   => $show_reviews,
		'show_favorites' => $show_favorites,
		'show_profile'   => $show_profile,
		'show_credits'   => $show_credits,
		'credit_balance' => $credit_balance,
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

		<?php // ─── Listing Limit Card ─── ?>
		<?php
		$limit_classes = 'listora-dashboard__limit';
		if ( $limit_unlimited ) {
			$limit_classes .= ' listora-dashboard__limit--unlimited';
		} elseif ( 0 === $limit_remaining ) {
			$limit_classes .= ' listora-dashboard__limit--exhausted';
		} elseif ( $limit_remaining > 0 && $limit_remaining <= 2 ) {
			$limit_classes .= ' listora-dashboard__limit--low';
		}
		?>
		<div class="<?php echo esc_attr( $limit_classes ); ?>" role="region" aria-labelledby="listora-limit-heading">
			<div class="listora-dashboard__limit-main">
				<span class="listora-dashboard__limit-icon" aria-hidden="true">
					<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path d="M12 2v4"/><path d="M12 18v4"/><path d="M4.93 4.93l2.83 2.83"/><path d="M16.24 16.24l2.83 2.83"/><path d="M2 12h4"/><path d="M18 12h4"/><path d="M4.93 19.07l2.83-2.83"/><path d="M16.24 7.76l2.83-2.83"/>
					</svg>
				</span>
				<div class="listora-dashboard__limit-stats">
					<h3 id="listora-limit-heading" class="listora-dashboard__limit-title">
						<?php
						if ( 'calendar_month' === $limit_period ) {
							esc_html_e( 'Your listings this month', 'wb-listora' );
						} elseif ( 'rolling_30d' === $limit_period ) {
							esc_html_e( 'Your listings (last 30 days)', 'wb-listora' );
						} else {
							esc_html_e( 'Your Listings', 'wb-listora' );
						}
						?>
					</h3>
					<div class="listora-dashboard__limit-grid">
						<div class="listora-dashboard__limit-metric">
							<span class="listora-dashboard__limit-value"><?php echo esc_html( $limit_count ); ?></span>
							<span class="listora-dashboard__limit-label">
								<?php
								if ( 'lifetime' === $limit_period ) {
									esc_html_e( 'Active + Pending', 'wb-listora' );
								} else {
									/* translators: period label such as "this month" or "in last 30 days". */
									printf( esc_html__( 'Used %s', 'wb-listora' ), esc_html( $limit_period_label ) );
								}
								?>
							</span>
						</div>
						<div class="listora-dashboard__limit-metric">
							<span class="listora-dashboard__limit-value">
								<?php echo $limit_unlimited ? esc_html__( '∞', 'wb-listora' ) : esc_html( $limit_value ); ?>
							</span>
							<span class="listora-dashboard__limit-label"><?php esc_html_e( 'Limit', 'wb-listora' ); ?></span>
						</div>
						<div class="listora-dashboard__limit-metric">
							<span class="listora-dashboard__limit-value">
								<?php echo $limit_unlimited ? esc_html__( 'Unlimited', 'wb-listora' ) : esc_html( $limit_remaining ); ?>
							</span>
							<span class="listora-dashboard__limit-label"><?php esc_html_e( 'Remaining', 'wb-listora' ); ?></span>
						</div>
					</div>
				</div>
			</div>

			<?php if ( ! $limit_unlimited && 0 === $limit_remaining && $limit_can_overflow ) : ?>
				<div class="listora-dashboard__limit-cta">
					<p class="listora-dashboard__limit-message">
						<?php
						if ( 'lifetime' === $limit_period ) {
							printf(
								/* translators: %d: credits cost. */
								esc_html__( 'You have reached your limit. Submit another listing for %d credits.', 'wb-listora' ),
								(int) $limit_overflow
							);
						} else {
							printf(
								/* translators: 1: period label, 2: credits cost. */
								esc_html__( 'You have reached your limit %1$s. Submit another listing for %2$d credits.', 'wb-listora' ),
								esc_html( $limit_period_label ),
								(int) $limit_overflow
							);
						}
						?>
					</p>
					<?php if ( $limit_purchase_url ) : ?>
						<a href="<?php echo esc_url( $limit_purchase_url ); ?>" class="listora-btn listora-btn--secondary listora-btn--sm">
							<?php esc_html_e( 'Buy Credits', 'wb-listora' ); ?>
						</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( home_url( '/add-listing/' ) ); ?>" class="listora-btn listora-btn--primary listora-btn--sm">
						<?php
						printf(
							/* translators: %d: credits cost. */
							esc_html__( 'Submit for %d credits', 'wb-listora' ),
							(int) $limit_overflow
						);
						?>
					</a>
				</div>
			<?php elseif ( ! $limit_unlimited && 0 === $limit_remaining ) : ?>
				<div class="listora-dashboard__limit-cta">
					<p class="listora-dashboard__limit-message">
						<?php
						if ( 'lifetime' === $limit_period ) {
							esc_html_e( 'You have reached your listing limit. Contact an administrator to request more.', 'wb-listora' );
						} elseif ( 'calendar_month' === $limit_period ) {
							esc_html_e( 'You have reached your listing limit for this month. It will reset on the 1st.', 'wb-listora' );
						} else {
							esc_html_e( 'You have reached your listing limit for the last 30 days. Older listings will roll off the window soon.', 'wb-listora' );
						}
						?>
					</p>
				</div>
			<?php endif; ?>
		</div>

		<?php
		// ─── My Listings Panel (overridable template) ───
		if ( $show_listings ) :
			$listings_view_data              = array(
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
			$reviews_view_data              = array(
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
		// ─── Credits Panel (overridable template) ───
		if ( $show_credits ) :
			$credits_view_data              = array(
				'user_id'             => $user_id,
				'default_tab'         => $default_tab,
				'credit_balance'      => $credit_balance,
				'credit_threshold'    => $credit_threshold,
				'credit_packs'        => $credit_packs,
				'credit_ledger'       => $credit_ledger,
				'credit_purchase_url' => $credit_purchase_url,
			);
			$credits_view_data['view_data'] = $credits_view_data;
			wb_listora_get_template( 'blocks/user-dashboard/tab-credits.php', $credits_view_data );
		endif;
		?>

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
			$profile_view_data              = array(
				'user_id' => $user_id,
				'user'    => $user,
			);
			$profile_view_data['view_data'] = $profile_view_data;
			wb_listora_get_template( 'blocks/user-dashboard/tab-profile.php', $profile_view_data );
		endif;
		?>

	</div>
</div>
