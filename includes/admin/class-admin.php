<?php
/**
 * Admin — registers menus, handles admin redirects, dashboard widget.
 *
 * @package WBListora\Admin
 */

namespace WBListora\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Main admin class.
 */
class Admin {

	/**
	 * Constructor — hooks admin actions.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );
		add_action( 'admin_init', array( Settings_Page::class, 'register' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_notices', array( $this, 'onboarding_notice' ) );

		// Admin columns and filters for listings CPT.
		new Listing_Columns();
	}

	/**
	 * Check if the current admin screen is a Listora page.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 *
	 * @return bool
	 */
	private function is_listora_screen( $hook_suffix = '' ) {
		// Check hook suffix first (faster, no get_current_screen dependency).
		if ( $hook_suffix && false !== strpos( $hook_suffix, 'listora' ) ) {
			return true;
		}

		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		// Match Listora menu pages and post type screens.
		if ( false !== strpos( $screen->id, 'listora' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Enqueue admin CSS and JS assets on Listora pages.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( ! $this->is_listora_screen( $hook_suffix ) ) {
			return;
		}

		$plugin_url = WB_LISTORA_PLUGIN_URL;
		$version    = WB_LISTORA_VERSION;

		// Styles.
		wp_enqueue_style(
			'listora-admin',
			$plugin_url . 'assets/css/admin.css',
			array(),
			$version
		);

		wp_enqueue_style(
			'listora-icons',
			$plugin_url . 'assets/css/admin/icons.css',
			array( 'listora-admin' ),
			$version
		);

		wp_enqueue_style(
			'listora-toast',
			$plugin_url . 'assets/css/shared/toast.css',
			array(),
			$version
		);

		// Scripts.
		wp_enqueue_script(
			'lucide',
			$plugin_url . 'assets/js/vendor/lucide.min.js',
			array(),
			'0.460.0',
			true
		);

		wp_enqueue_script(
			'listora-icons',
			$plugin_url . 'assets/js/admin/icons.js',
			array( 'lucide' ),
			$version,
			true
		);

		wp_enqueue_script(
			'listora-toast',
			$plugin_url . 'assets/js/shared/toast.js',
			array(),
			$version,
			true
		);

		// List page assets for Reviews, Claims, and Listing Types.
		$list_pages = array( 'listora-reviews', 'listora-claims', 'listora-listing-types' );
		$page       = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( in_array( $page, $list_pages, true ) ) {
			wp_enqueue_style(
				'listora-list-page',
				$plugin_url . 'assets/css/admin/list-page.css',
				array( 'listora-admin' ),
				$version
			);

			wp_enqueue_script(
				'listora-list-page',
				$plugin_url . 'assets/js/admin/list-page.js',
				array(),
				$version,
				true
			);
		}

		// Type Editor page assets.
		if ( 'listora-listing-types' === $page ) {
			wp_enqueue_style(
				'listora-type-editor',
				$plugin_url . 'assets/css/admin/type-editor.css',
				array( 'listora-admin' ),
				$version
			);

			wp_enqueue_script(
				'listora-type-editor',
				$plugin_url . 'assets/js/admin/type-editor.js',
				array( 'lucide', 'listora-toast' ),
				$version,
				true
			);

			$type_editor   = new Type_Editor();
			$localize_data = array(
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'fieldTypes' => \WBListora\Core\Field_Registry::instance()->get_all(),
				'categories' => $type_editor->get_all_categories(),
				'apiBase'    => rest_url( 'listora/v1/listing-types' ),
				'adminUrl'   => admin_url( 'admin.php?page=listora-listing-types' ),
				'isNew'      => isset( $_GET['action'] ) && 'new' === $_GET['action'], // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			);

			wp_localize_script( 'listora-type-editor', 'listoraTypeEditor', $localize_data );
		}
	}

	/**
	 * Register admin menu and submenu pages.
	 */
	public function register_menus() {
		// Main menu.
		add_menu_page(
			__( 'Listora', 'wb-listora' ),
			__( 'Listora', 'wb-listora' ),
			'edit_listora_listings',
			'listora',
			array( $this, 'render_dashboard_page' ),
			'dashicons-location-alt',
			25
		);

		// Dashboard (same as main).
		add_submenu_page(
			'listora',
			__( 'Dashboard', 'wb-listora' ),
			__( 'Dashboard', 'wb-listora' ),
			'edit_listora_listings',
			'listora',
			array( $this, 'render_dashboard_page' )
		);

		// Note: "All Listings" and "Add New" are auto-added by the CPT
		// via 'show_in_menu' => 'listora' in Post_Types class.

		// Categories.
		add_submenu_page(
			'listora',
			__( 'Categories', 'wb-listora' ),
			__( 'Categories', 'wb-listora' ),
			'manage_listora_types',
			'edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing'
		);

		// Listing Types.
		add_submenu_page(
			'listora',
			__( 'Listing Types', 'wb-listora' ),
			__( 'Listing Types', 'wb-listora' ),
			'manage_listora_types',
			'listora-listing-types',
			array( $this, 'render_listing_types_page' )
		);

		// Locations.
		add_submenu_page(
			'listora',
			__( 'Locations', 'wb-listora' ),
			__( 'Locations', 'wb-listora' ),
			'manage_listora_types',
			'edit-tags.php?taxonomy=listora_listing_location&post_type=listora_listing'
		);

		// Features.
		add_submenu_page(
			'listora',
			__( 'Features', 'wb-listora' ),
			__( 'Features', 'wb-listora' ),
			'manage_listora_types',
			'edit-tags.php?taxonomy=listora_listing_feature&post_type=listora_listing'
		);

		// Reviews.
		add_submenu_page(
			'listora',
			__( 'Reviews', 'wb-listora' ),
			__( 'Reviews', 'wb-listora' ),
			'manage_listora_types',
			'listora-reviews',
			array( $this, 'render_reviews_page' )
		);

		// Claims.
		add_submenu_page(
			'listora',
			__( 'Claims', 'wb-listora' ),
			__( 'Claims', 'wb-listora' ),
			'manage_listora_types',
			'listora-claims',
			array( $this, 'render_claims_page' )
		);

		// Import / Export.
		add_submenu_page(
			'listora',
			__( 'Import / Export', 'wb-listora' ),
			__( 'Import / Export', 'wb-listora' ),
			'manage_listora_settings',
			'listora-import-export',
			array( $this, 'render_import_export_page' )
		);

		// Settings.
		add_submenu_page(
			'listora',
			__( 'Settings', 'wb-listora' ),
			__( 'Settings', 'wb-listora' ),
			'manage_listora_settings',
			'listora-settings',
			array( $this, 'render_settings_page' )
		);

		// Setup Wizard (hidden from menu).
		add_submenu_page(
			null, // Hidden.
			__( 'Setup Wizard', 'wb-listora' ),
			__( 'Setup Wizard', 'wb-listora' ),
			'manage_listora_settings',
			'listora-setup',
			array( $this, 'render_setup_wizard' )
		);
	}

	/**
	 * Redirect to setup wizard on first activation.
	 */
	public function maybe_redirect_to_wizard() {
		if ( ! get_transient( 'wb_listora_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'wb_listora_activation_redirect' );

		// Don't redirect during bulk activation or AJAX.
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		// Don't redirect if already completed setup.
		if ( wb_listora_get_setting( 'setup_complete' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=listora-setup' ) );
		exit;
	}

	/**
	 * Show onboarding notice if setup not complete.
	 */
	public function onboarding_notice() {
		if ( wb_listora_get_setting( 'setup_complete' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && 'admin_page_listora-setup' === $screen->id ) {
			return;
		}

		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		$wizard_url = admin_url( 'admin.php?page=listora-setup' );
		printf(
			'<div class="notice notice-info is-dismissible"><p>%s <a href="%s" class="button button-primary" style="margin-inline-start:1rem;">%s</a></p></div>',
			esc_html__( 'Welcome to WB Listora! Complete the setup wizard to get started.', 'wb-listora' ),
			esc_url( $wizard_url ),
			esc_html__( 'Start Setup', 'wb-listora' )
		);
	}

	/**
	 * Dashboard widget.
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( 'edit_listora_listings' ) ) {
			return;
		}

		wp_add_dashboard_widget(
			'listora_dashboard_widget',
			__( 'WB Listora Overview', 'wb-listora' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render compact dashboard widget for the WP Dashboard.
	 *
	 * Shows 4 key numbers and a link to the full Listora dashboard.
	 */
	public function render_dashboard_widget() {
		$counts    = wp_count_posts( 'listora_listing' );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$review_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$review_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews WHERE status = 'pending'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$claims_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}claims WHERE status = 'pending'" );

		$pending_total = $review_pending + $claims_pending;

		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">';
		printf(
			'<div><strong style="font-size:20px;display:block;">%s</strong><span style="color:#757575;font-size:12px;">%s</span></div>',
			esc_html( number_format_i18n( $published ) ),
			esc_html__( 'Listings', 'wb-listora' )
		);
		printf(
			'<div><strong style="font-size:20px;display:block;">%s</strong><span style="color:#757575;font-size:12px;">%s</span></div>',
			esc_html( number_format_i18n( $review_total ) ),
			esc_html__( 'Reviews', 'wb-listora' )
		);
		printf(
			'<div><strong style="font-size:20px;display:block;">%s</strong><span style="color:#757575;font-size:12px;">%s</span></div>',
			esc_html( number_format_i18n( $claims_pending ) ),
			esc_html__( 'Claims Pending', 'wb-listora' )
		);
		printf(
			'<div><strong style="font-size:20px;display:block;%s">%s</strong><span style="color:#757575;font-size:12px;">%s</span></div>',
			$pending_total > 0 ? 'color:#d97706;' : '',
			esc_html( number_format_i18n( $pending_total ) ),
			esc_html__( 'Pending Total', 'wb-listora' )
		);
		echo '</div>';

		echo '<p style="margin-top:1rem;padding-top:0.75rem;border-top:1px solid #e2e8f0;">';
		printf(
			'<a href="%s" style="font-weight:500;">%s &rarr;</a>',
			esc_url( admin_url( 'admin.php?page=listora' ) ),
			esc_html__( 'View Full Dashboard', 'wb-listora' )
		);
		echo '</p>';
	}

	// ─── Page Renderers (placeholders — full implementations in dedicated classes) ───

	/**
	 * Render full dashboard page (Pattern C layout).
	 */
	public function render_dashboard_page() {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$counts    = wp_count_posts( 'listora_listing' );
		$published = isset( $counts->publish ) ? (int) $counts->publish : 0;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $prefix is a safe table prefix built from $wpdb->prefix.
		$review_total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews" );
		$review_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews WHERE status = 'pending'" );
		$claims_total   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}claims" );
		$claims_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}claims WHERE status = 'pending'" );
		$fav_users      = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$prefix}favorites" );
		$recent_reviews = $wpdb->get_results(
			"SELECT r.*, si.title AS listing_title
			 FROM {$prefix}reviews r
			 LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id
			 ORDER BY r.created_at DESC
			 LIMIT 5",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$pending_total = $review_pending + $claims_pending;

		echo '<div class="wrap wb-listora-admin">';

		// ── Page Header ──.
		echo '<div class="listora-page-header">';
		echo '<div class="listora-page-header__left">';
		echo '<h1 class="listora-page-header__title"><i data-lucide="layout-dashboard" class="listora-icon--sm"></i> ';
		echo esc_html__( 'Dashboard', 'wb-listora' ) . '</h1>';
		echo '<p class="listora-page-header__desc">';
		echo esc_html__( 'Overview of your directory at a glance.', 'wb-listora' ) . '</p>';
		echo '</div>';
		echo '<div class="listora-page-header__actions">';
		echo '<a href="' . esc_url( home_url( '/' ) ) . '" class="listora-btn" target="_blank">';
		echo '<i data-lucide="external-link"></i> ' . esc_html__( 'View Site', 'wb-listora' ) . '</a>';
		echo '</div>';
		echo '</div>';

		// ── Stat Cards ──.
		echo '<div class="listora-stats-grid">';

		$this->render_stat_card( 'map-pin', 'accent', $published, __( 'Published Listings', 'wb-listora' ) );
		$this->render_stat_card( 'star', 'success', $review_total, __( 'Total Reviews', 'wb-listora' ) );
		$this->render_stat_card( 'shield-check', '', $claims_total, __( 'Total Claims', 'wb-listora' ) );
		$this->render_stat_card( 'users', '', $fav_users, __( 'Unique Users', 'wb-listora' ) );
		$this->render_stat_card( 'alert-triangle', 'warn', $pending_total, __( 'Pending Items', 'wb-listora' ) );

		echo '</div>';

		// ── Quick Actions ──.
		echo '<div class="listora-quick-actions">';
		echo '<a href="' . esc_url( admin_url( 'post-new.php?post_type=listora_listing' ) ) . '" class="listora-btn listora-btn--primary">';
		echo '<i data-lucide="plus"></i> ' . esc_html__( 'Add Listing', 'wb-listora' ) . '</a>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-import-export' ) ) . '" class="listora-btn">';
		echo '<i data-lucide="upload"></i> ' . esc_html__( 'Import CSV', 'wb-listora' ) . '</a>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-settings' ) ) . '" class="listora-btn">';
		echo '<i data-lucide="settings"></i> ' . esc_html__( 'Settings', 'wb-listora' ) . '</a>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-setup' ) ) . '" class="listora-btn">';
		echo '<i data-lucide="wand-2"></i> ' . esc_html__( 'Run Wizard', 'wb-listora' ) . '</a>';
		echo '</div>';

		// ── Alert Cards (only if pending items exist) ──.
		if ( $review_pending > 0 || $claims_pending > 0 ) {
			echo '<div class="listora-alerts">';

			if ( $review_pending > 0 ) {
				echo '<div class="listora-alert listora-alert--warn">';
				echo '<i data-lucide="alert-triangle"></i>';
				echo '<span class="listora-alert__text"><strong>';
				echo esc_html( number_format_i18n( $review_pending ) ) . '</strong> ';
				echo esc_html__( 'pending reviews need attention', 'wb-listora' ) . '</span>';
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-reviews&status=pending' ) ) . '" class="listora-btn listora-btn--sm listora-alert__action">';
				echo esc_html__( 'Review', 'wb-listora' ) . ' &rarr;</a>';
				echo '</div>';
			}

			if ( $claims_pending > 0 ) {
				echo '<div class="listora-alert listora-alert--warn">';
				echo '<i data-lucide="shield-alert"></i>';
				echo '<span class="listora-alert__text"><strong>';
				echo esc_html( number_format_i18n( $claims_pending ) ) . '</strong> ';
				echo esc_html__( 'pending claims awaiting review', 'wb-listora' ) . '</span>';
				echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-claims&status=pending' ) ) . '" class="listora-btn listora-btn--sm listora-alert__action">';
				echo esc_html__( 'View', 'wb-listora' ) . ' &rarr;</a>';
				echo '</div>';
			}

			echo '</div>';
		}

		// ── Recent Activity ──.
		echo '<div class="listora-card">';
		echo '<div class="listora-card__head">';
		echo '<h2 class="listora-card__title"><i data-lucide="activity" class="listora-icon--sm"></i> ';
		echo esc_html__( 'Recent Activity', 'wb-listora' ) . '</h2>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=listora-reviews' ) ) . '" class="listora-btn listora-btn--sm">';
		echo esc_html__( 'View All', 'wb-listora' ) . '</a>';
		echo '</div>';
		echo '<div class="listora-card__body">';

		if ( ! empty( $recent_reviews ) ) {
			echo '<ul class="listora-activity-list">';
			foreach ( $recent_reviews as $review ) {
				$user          = get_user_by( 'id', $review['user_id'] );
				$author_name   = $user ? $user->display_name : __( 'Anonymous', 'wb-listora' );
				$listing_title = ! empty( $review['listing_title'] ) ? $review['listing_title'] : '#' . $review['listing_id'];
				$time_ago      = human_time_diff( strtotime( $review['created_at'] ), current_time( 'timestamp' ) ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested

				echo '<li class="listora-activity-item">';
				echo '<div class="listora-activity-item__icon"><i data-lucide="message-square"></i></div>';
				echo '<div class="listora-activity-item__text">';
				printf(
					/* translators: 1: author name, 2: listing title */
					esc_html__( 'New review by %1$s on %2$s', 'wb-listora' ),
					'<strong>' . esc_html( $author_name ) . '</strong>',
					'<strong>' . esc_html( $listing_title ) . '</strong>'
				);
				if ( ! empty( $review['overall_rating'] ) ) {
					$stars = str_repeat( "\xe2\x98\x85", (int) $review['overall_rating'] );
					echo ' &mdash; ' . esc_html( $stars );
				}
				echo '</div>';
				echo '<span class="listora-activity-item__time">';
				echo esc_html( $time_ago ) . ' ' . esc_html__( 'ago', 'wb-listora' ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<div class="listora-empty-state">';
			echo '<div class="listora-empty-state__icon"><i data-lucide="inbox"></i></div>';
			echo '<p class="listora-empty-state__title">';
			echo esc_html__( 'No recent activity', 'wb-listora' ) . '</p>';
			echo '<p class="listora-empty-state__desc">';
			echo esc_html__( 'Reviews and activity will appear here once your directory starts receiving engagement.', 'wb-listora' ) . '</p>';
			echo '</div>';
		}

		echo '</div>'; // .listora-card__body.
		echo '</div>'; // .listora-card.
		echo '</div>'; // .wrap.
	}

	/**
	 * Render a single stat card.
	 *
	 * @param string $icon    Lucide icon name.
	 * @param string $variant Color variant: accent, success, warn, danger, or empty for default.
	 * @param int    $number  The stat number.
	 * @param string $label   The stat label.
	 */
	private function render_stat_card( $icon, $variant, $number, $label ) {
		$icon_class = 'listora-stat-card__icon';
		if ( $variant ) {
			$icon_class .= ' listora-stat-card__icon--' . $variant;
		}

		echo '<div class="listora-stat-card">';
		echo '<div class="' . esc_attr( $icon_class ) . '"><i data-lucide="' . esc_attr( $icon ) . '"></i></div>';
		echo '<div class="listora-stat-card__body">';
		echo '<div class="listora-stat-card__number">' . esc_html( number_format_i18n( $number ) ) . '</div>';
		echo '<div class="listora-stat-card__label">' . esc_html( $label ) . '</div>';
		echo '</div></div>';
	}

	/**
	 * Render Listing Types page — delegates to the Type Editor class.
	 */
	public function render_listing_types_page() {
		$editor = new Type_Editor();
		$editor->render();
	}

	/**
	 * Render Reviews moderation page (Pattern B).
	 */
	public function render_reviews_page() {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// Handle approve/reject/delete actions.
		if ( isset( $_GET['action'], $_GET['review_id'], $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action    = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$review_id = absint( $_GET['review_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'listora_review_action' ) ) {
				if ( 'approve' === $action ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->update( "{$prefix}reviews", array( 'status' => 'approved' ), array( 'id' => $review_id ) );
				} elseif ( 'reject' === $action ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->update( "{$prefix}reviews", array( 'status' => 'rejected' ), array( 'id' => $review_id ) );
				} elseif ( 'delete' === $action ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->delete( "{$prefix}reviews", array( 'id' => $review_id ) );
				}
				echo '<div class="notice notice-success listora-notice"><p>' . esc_html__( 'Review updated.', 'wb-listora' ) . '</p></div>';
			}
		}

		// Handle bulk actions.
		if ( isset( $_POST['bulk_action'], $_POST['ids'], $_POST['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'listora_review_bulk' ) ) {
				$bulk_action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) );
				$ids         = array_map( 'absint', (array) $_POST['ids'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$ids         = array_filter( $ids );

				foreach ( $ids as $id ) {
					if ( 'approve' === $bulk_action ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->update( "{$prefix}reviews", array( 'status' => 'approved' ), array( 'id' => $id ) );
					} elseif ( 'reject' === $bulk_action ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->update( "{$prefix}reviews", array( 'status' => 'rejected' ), array( 'id' => $id ) );
					} elseif ( 'delete' === $bulk_action ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->delete( "{$prefix}reviews", array( 'id' => $id ) );
					}
				}

				if ( ! empty( $ids ) ) {
					echo '<div class="notice notice-success listora-notice"><p>' . esc_html__( 'Bulk action applied.', 'wb-listora' ) . '</p></div>';
				}
			}
		}

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_term   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where         = '1=1';

		if ( $status_filter ) {
			$where .= $wpdb->prepare( ' AND r.status = %s', $status_filter );
		}

		if ( $search_term ) {
			$like   = '%' . $wpdb->esc_like( $search_term ) . '%';
			$where .= $wpdb->prepare( ' AND (si.title LIKE %s OR r.title LIKE %s OR r.content LIKE %s)', $like, $like, $like );
		}

		// Status counts.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_all      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews" );
		$count_pending  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews WHERE status = 'pending'" );
		$count_approved = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews WHERE status = 'approved'" );
		$count_rejected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews WHERE status = 'rejected'" );

		$reviews = $wpdb->get_results(
			"SELECT r.*, si.title as listing_title FROM {$prefix}reviews r LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id WHERE {$where} ORDER BY r.created_at DESC LIMIT 50",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$base_url = admin_url( 'admin.php?page=listora-reviews' );

		echo '<div class="wrap wb-listora-admin">';

		// Page header.
		echo '<div class="listora-page-header">';
		echo '<div class="listora-page-header__left">';
		echo '<h1 class="listora-page-header__title"><i data-lucide="star"></i> ' . esc_html__( 'Reviews', 'wb-listora' ) . '</h1>';
		echo '<p class="listora-page-header__desc">' . esc_html__( 'Manage listing reviews and ratings.', 'wb-listora' ) . '</p>';
		echo '</div>';
		echo '</div>';

		// Filter tabs.
		$tabs = array(
			''         => array( __( 'All', 'wb-listora' ), $count_all ),
			'pending'  => array( __( 'Pending', 'wb-listora' ), $count_pending ),
			'approved' => array( __( 'Approved', 'wb-listora' ), $count_approved ),
			'rejected' => array( __( 'Rejected', 'wb-listora' ), $count_rejected ),
		);

		echo '<div class="listora-filter-tabs">';
		foreach ( $tabs as $status => $tab_data ) {
			$tab_url   = $status ? add_query_arg( 'status', $status, $base_url ) : $base_url;
			$is_active = $status_filter === $status ? ' is-active' : '';
			echo '<a href="' . esc_url( $tab_url ) . '" class="listora-filter-tab' . esc_attr( $is_active ) . '">';
			echo esc_html( $tab_data[0] );
			echo '<span class="listora-filter-tab__count">' . esc_html( $tab_data[1] ) . '</span>';
			echo '</a>';
		}
		echo '</div>';

		// Search bar.
		echo '<form method="get" class="listora-filter-bar">';
		echo '<input type="hidden" name="page" value="listora-reviews">';
		if ( $status_filter ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $status_filter ) . '">';
		}
		echo '<input type="search" name="s" class="listora-search-input" placeholder="' . esc_attr__( 'Search reviews...', 'wb-listora' ) . '" value="' . esc_attr( $search_term ) . '">';
		echo '<button type="submit" class="listora-btn listora-btn--sm">' . esc_html__( 'Filter', 'wb-listora' ) . '</button>';
		echo '</form>';

		if ( empty( $reviews ) ) {
			// Empty state.
			echo '<div class="listora-empty-state">';
			echo '<div class="listora-empty-state__icon"><i data-lucide="star"></i></div>';
			echo '<p class="listora-empty-state__title">' . esc_html__( 'No reviews yet', 'wb-listora' ) . '</p>';
			echo '<p class="listora-empty-state__desc">' . esc_html__( 'Reviews will appear here once visitors start rating your listings.', 'wb-listora' ) . '</p>';
			echo '</div>';
		} else {
			// Table.
			echo '<form method="post">';
			wp_nonce_field( 'listora_review_bulk' );

			echo '<div class="listora-table-wrap">';
			echo '<table class="listora-table">';
			echo '<thead><tr>';
			echo '<th class="listora-table__check"><input type="checkbox" class="listora-table__select-all"></th>';
			echo '<th>' . esc_html__( 'Listing', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Author', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Rating', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Review', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Date', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Actions', 'wb-listora' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $reviews as $rev ) {
				$user = get_user_by( 'id', $rev['user_id'] );
				$name = $user ? $user->display_name : __( 'Anonymous', 'wb-listora' );

				// Rating stars.
				$rating       = (int) $rev['overall_rating'];
				$stars_filled = str_repeat( "\xe2\x98\x85", $rating );
				$stars_empty  = str_repeat( "\xe2\x98\x86", 5 - $rating );

				// Status badge.
				$badge_map   = array(
					'approved' => 'listora-badge--success',
					'pending'  => 'listora-badge--warn',
					'rejected' => 'listora-badge--danger',
				);
				$badge_class = isset( $badge_map[ $rev['status'] ] ) ? $badge_map[ $rev['status'] ] : 'listora-badge--muted';

				echo '<tr>';
				echo '<td class="listora-table__check"><input type="checkbox" name="ids[]" value="' . esc_attr( $rev['id'] ) . '"></td>';
				echo '<td><a href="' . esc_url( get_permalink( $rev['listing_id'] ) ) . '" class="listora-row-title">' . esc_html( $rev['listing_title'] ? $rev['listing_title'] : '#' . $rev['listing_id'] ) . '</a></td>';
				echo '<td>' . esc_html( $name ) . '</td>';
				echo '<td><span class="listora-star-rating">' . esc_html( $stars_filled ) . '<span class="listora-star-rating__empty">' . esc_html( $stars_empty ) . '</span></span></td>';
				echo '<td><div class="listora-review-excerpt__title">' . esc_html( $rev['title'] ) . '</div><div class="listora-review-excerpt__text">' . esc_html( wp_trim_words( $rev['content'], 15 ) ) . '</div></td>';
				echo '<td><span class="listora-badge ' . esc_attr( $badge_class ) . '">' . esc_html( ucfirst( $rev['status'] ) ) . '</span></td>';
				echo '<td>' . esc_html( human_time_diff( strtotime( $rev['created_at'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'wb-listora' ) . '</td>';

				// Actions.
				echo '<td><div class="listora-row-actions">';
				if ( 'pending' === $rev['status'] || 'rejected' === $rev['status'] ) {
					echo '<a href="' . esc_url(
						wp_nonce_url(
							add_query_arg(
								array(
									'action'    => 'approve',
									'review_id' => $rev['id'],
								),
								$base_url
							),
							'listora_review_action'
						)
					) . '" class="listora-action-link">' . esc_html__( 'Approve', 'wb-listora' ) . '</a>';
				}
				if ( 'pending' === $rev['status'] || 'approved' === $rev['status'] ) {
					echo '<a href="' . esc_url(
						wp_nonce_url(
							add_query_arg(
								array(
									'action'    => 'reject',
									'review_id' => $rev['id'],
								),
								$base_url
							),
							'listora_review_action'
						)
					) . '" class="listora-action-link">' . esc_html__( 'Reject', 'wb-listora' ) . '</a>';
				}
				echo '<a href="' . esc_url(
					wp_nonce_url(
						add_query_arg(
							array(
								'action'    => 'delete',
								'review_id' => $rev['id'],
							),
							$base_url
						),
						'listora_review_action'
					)
				) . '" class="listora-action-link listora-action-link--danger">' . esc_html__( 'Delete', 'wb-listora' ) . '</a>';
				echo '</div></td>';

				echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';

			// Table footer with bulk actions.
			echo '<div class="listora-table-footer">';
			echo '<div class="listora-bulk-actions">';
			echo '<select name="bulk_action" class="listora-filter-select">';
			echo '<option value="">' . esc_html__( 'Bulk Actions', 'wb-listora' ) . '</option>';
			echo '<option value="approve">' . esc_html__( 'Approve', 'wb-listora' ) . '</option>';
			echo '<option value="reject">' . esc_html__( 'Reject', 'wb-listora' ) . '</option>';
			echo '<option value="delete">' . esc_html__( 'Delete', 'wb-listora' ) . '</option>';
			echo '</select>';
			echo '<button type="submit" class="listora-btn listora-btn--sm">' . esc_html__( 'Apply', 'wb-listora' ) . '</button>';
			echo '</div>';
			echo '</div>';

			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Render Claims management page (Pattern B).
	 */
	public function render_claims_page() {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// Handle approve/reject/delete actions.
		if ( isset( $_GET['action'], $_GET['claim_id'], $_GET['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action   = sanitize_text_field( wp_unslash( $_GET['action'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$claim_id = absint( $_GET['claim_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'listora_claim_action' ) ) {
				if ( 'approve_claim' === $action ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->update( "{$prefix}claims", array( 'status' => 'approved' ), array( 'id' => $claim_id ) );
				} elseif ( 'reject_claim' === $action ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->update( "{$prefix}claims", array( 'status' => 'rejected' ), array( 'id' => $claim_id ) );
				} elseif ( 'delete_claim' === $action ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$wpdb->delete( "{$prefix}claims", array( 'id' => $claim_id ) );
				}
				echo '<div class="notice notice-success listora-notice"><p>' . esc_html__( 'Claim updated.', 'wb-listora' ) . '</p></div>';
			}
		}

		// Handle bulk actions.
		if ( isset( $_POST['bulk_action'], $_POST['ids'], $_POST['_wpnonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'listora_claim_bulk' ) ) {
				$bulk_action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ) );
				$ids         = array_map( 'absint', (array) $_POST['ids'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$ids         = array_filter( $ids );

				foreach ( $ids as $id ) {
					if ( 'approve' === $bulk_action ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->update( "{$prefix}claims", array( 'status' => 'approved' ), array( 'id' => $id ) );
					} elseif ( 'reject' === $bulk_action ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->update( "{$prefix}claims", array( 'status' => 'rejected' ), array( 'id' => $id ) );
					} elseif ( 'delete' === $bulk_action ) {
						// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$wpdb->delete( "{$prefix}claims", array( 'id' => $id ) );
					}
				}

				if ( ! empty( $ids ) ) {
					echo '<div class="notice notice-success listora-notice"><p>' . esc_html__( 'Bulk action applied.', 'wb-listora' ) . '</p></div>';
				}
			}
		}

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_term   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where         = '1=1';

		if ( $status_filter ) {
			$where .= $wpdb->prepare( ' AND c.status = %s', $status_filter );
		}

		if ( $search_term ) {
			$like   = '%' . $wpdb->esc_like( $search_term ) . '%';
			$where .= $wpdb->prepare( ' AND (p.post_title LIKE %s OR u.display_name LIKE %s OR u.user_email LIKE %s)', $like, $like, $like );
		}

		// Status counts.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_all      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}claims" );
		$count_pending  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}claims WHERE status = 'pending'" );
		$count_approved = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}claims WHERE status = 'approved'" );
		$count_rejected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}claims WHERE status = 'rejected'" );

		$claims = $wpdb->get_results(
			"SELECT c.*, p.post_title as listing_title, u.display_name as user_name, u.user_email FROM {$prefix}claims c LEFT JOIN {$wpdb->posts} p ON c.listing_id = p.ID LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID WHERE {$where} ORDER BY c.created_at DESC LIMIT 50",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$base_url = admin_url( 'admin.php?page=listora-claims' );

		echo '<div class="wrap wb-listora-admin">';

		// Page header.
		echo '<div class="listora-page-header">';
		echo '<div class="listora-page-header__left">';
		echo '<h1 class="listora-page-header__title"><i data-lucide="shield-check"></i> ' . esc_html__( 'Claims', 'wb-listora' ) . '</h1>';
		echo '<p class="listora-page-header__desc">' . esc_html__( 'Manage listing ownership claims.', 'wb-listora' ) . '</p>';
		echo '</div>';
		echo '</div>';

		// Filter tabs.
		$tabs = array(
			''         => array( __( 'All', 'wb-listora' ), $count_all ),
			'pending'  => array( __( 'Pending', 'wb-listora' ), $count_pending ),
			'approved' => array( __( 'Approved', 'wb-listora' ), $count_approved ),
			'rejected' => array( __( 'Rejected', 'wb-listora' ), $count_rejected ),
		);

		echo '<div class="listora-filter-tabs">';
		foreach ( $tabs as $status => $tab_data ) {
			$tab_url   = $status ? add_query_arg( 'status', $status, $base_url ) : $base_url;
			$is_active = $status_filter === $status ? ' is-active' : '';
			echo '<a href="' . esc_url( $tab_url ) . '" class="listora-filter-tab' . esc_attr( $is_active ) . '">';
			echo esc_html( $tab_data[0] );
			echo '<span class="listora-filter-tab__count">' . esc_html( $tab_data[1] ) . '</span>';
			echo '</a>';
		}
		echo '</div>';

		// Search bar.
		echo '<form method="get" class="listora-filter-bar">';
		echo '<input type="hidden" name="page" value="listora-claims">';
		if ( $status_filter ) {
			echo '<input type="hidden" name="status" value="' . esc_attr( $status_filter ) . '">';
		}
		echo '<input type="search" name="s" class="listora-search-input" placeholder="' . esc_attr__( 'Search claims...', 'wb-listora' ) . '" value="' . esc_attr( $search_term ) . '">';
		echo '<button type="submit" class="listora-btn listora-btn--sm">' . esc_html__( 'Filter', 'wb-listora' ) . '</button>';
		echo '</form>';

		if ( empty( $claims ) ) {
			// Empty state.
			echo '<div class="listora-empty-state">';
			echo '<div class="listora-empty-state__icon"><i data-lucide="shield-check"></i></div>';
			echo '<p class="listora-empty-state__title">' . esc_html__( 'No claims yet', 'wb-listora' ) . '</p>';
			echo '<p class="listora-empty-state__desc">' . esc_html__( 'Business owners can claim their listings from the frontend.', 'wb-listora' ) . '</p>';
			echo '</div>';
		} else {
			// Table.
			echo '<form method="post">';
			wp_nonce_field( 'listora_claim_bulk' );

			echo '<div class="listora-table-wrap">';
			echo '<table class="listora-table">';
			echo '<thead><tr>';
			echo '<th class="listora-table__check"><input type="checkbox" class="listora-table__select-all"></th>';
			echo '<th>' . esc_html__( 'Listing', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Claimant', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Email', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Proof', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Date', 'wb-listora' ) . '</th>';
			echo '<th>' . esc_html__( 'Actions', 'wb-listora' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $claims as $claim ) {
				// Status badge.
				$badge_map   = array(
					'approved' => 'listora-badge--success',
					'pending'  => 'listora-badge--warn',
					'rejected' => 'listora-badge--danger',
				);
				$badge_class = isset( $badge_map[ $claim['status'] ] ) ? $badge_map[ $claim['status'] ] : 'listora-badge--muted';

				echo '<tr>';
				echo '<td class="listora-table__check"><input type="checkbox" name="ids[]" value="' . esc_attr( $claim['id'] ) . '"></td>';
				echo '<td><a href="' . esc_url( get_permalink( $claim['listing_id'] ) ) . '" class="listora-row-title">' . esc_html( $claim['listing_title'] ? $claim['listing_title'] : '#' . $claim['listing_id'] ) . '</a></td>';
				echo '<td>' . esc_html( $claim['user_name'] ? $claim['user_name'] : __( 'Unknown', 'wb-listora' ) ) . '</td>';
				echo '<td>' . esc_html( isset( $claim['user_email'] ) ? $claim['user_email'] : '' ) . '</td>';
				echo '<td>' . esc_html( wp_trim_words( $claim['proof_text'], 20 ) ) . '</td>';
				echo '<td><span class="listora-badge ' . esc_attr( $badge_class ) . '">' . esc_html( ucfirst( $claim['status'] ) ) . '</span></td>';
				echo '<td>' . esc_html( human_time_diff( strtotime( $claim['created_at'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'wb-listora' ) . '</td>';

				// Actions.
				echo '<td><div class="listora-row-actions">';
				if ( 'pending' === $claim['status'] || 'rejected' === $claim['status'] ) {
					$approve_url = wp_nonce_url(
						add_query_arg(
							array(
								'action'   => 'approve_claim',
								'claim_id' => $claim['id'],
							),
							$base_url
						),
						'listora_claim_action'
					);
					echo '<a href="' . esc_url( $approve_url ) . '" class="listora-action-link">' . esc_html__( 'Approve', 'wb-listora' ) . '</a>';
				}
				if ( 'pending' === $claim['status'] || 'approved' === $claim['status'] ) {
					$reject_url = wp_nonce_url(
						add_query_arg(
							array(
								'action'   => 'reject_claim',
								'claim_id' => $claim['id'],
							),
							$base_url
						),
						'listora_claim_action'
					);
					echo '<a href="' . esc_url( $reject_url ) . '" class="listora-action-link">' . esc_html__( 'Reject', 'wb-listora' ) . '</a>';
				}
				$delete_url = wp_nonce_url(
					add_query_arg(
						array(
							'action'   => 'delete_claim',
							'claim_id' => $claim['id'],
						),
						$base_url
					),
					'listora_claim_action'
				);
				echo '<a href="' . esc_url( $delete_url ) . '" class="listora-action-link listora-action-link--danger">' . esc_html__( 'Delete', 'wb-listora' ) . '</a>';
				echo '</div></td>';

				echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';

			// Table footer with bulk actions.
			echo '<div class="listora-table-footer">';
			echo '<div class="listora-bulk-actions">';
			echo '<select name="bulk_action" class="listora-filter-select">';
			echo '<option value="">' . esc_html__( 'Bulk Actions', 'wb-listora' ) . '</option>';
			echo '<option value="approve">' . esc_html__( 'Approve', 'wb-listora' ) . '</option>';
			echo '<option value="reject">' . esc_html__( 'Reject', 'wb-listora' ) . '</option>';
			echo '<option value="delete">' . esc_html__( 'Delete', 'wb-listora' ) . '</option>';
			echo '</select>';
			echo '<button type="submit" class="listora-btn listora-btn--sm">' . esc_html__( 'Apply', 'wb-listora' ) . '</button>';
			echo '</div>';
			echo '</div>';

			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * Render Import/Export page.
	 */
	public function render_import_export_page() {
		echo '<div class="wrap wb-listora-admin"><h1>' . esc_html__( 'Import / Export', 'wb-listora' ) . '</h1>';
		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-top:1rem;">';

		// Import card.
		echo '<div class="card" style="padding:1.5rem;">';
		echo '<h2>' . esc_html__( 'Import Listings', 'wb-listora' ) . '</h2>';
		echo '<p>' . esc_html__( 'Import listings from a CSV file. Use column mapping to match your data.', 'wb-listora' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'WP-CLI:', 'wb-listora' ) . '</strong> <code>wp listora import &lt;file.csv&gt; --type=restaurant</code></p>';
		echo '</div>';

		// Export card.
		echo '<div class="card" style="padding:1.5rem;">';
		echo '<h2>' . esc_html__( 'Export Listings', 'wb-listora' ) . '</h2>';
		echo '<p>' . esc_html__( 'Export all listings to CSV for backup or migration.', 'wb-listora' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'WP-CLI:', 'wb-listora' ) . '</strong> <code>wp listora export --type=restaurant --output=file.csv</code></p>';
		echo '</div>';

		echo '</div></div>';
	}

	/**
	 * Render Settings page.
	 */
	public function render_settings_page() {
		Settings_Page::render();
	}

	/**
	 * Render Setup Wizard.
	 */
	public function render_setup_wizard() {
		// Delegate to the Setup_Wizard class.
		$wizard = new Setup_Wizard();
		$wizard->render();
	}
}
