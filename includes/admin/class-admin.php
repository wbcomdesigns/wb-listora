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
	 * Render dashboard widget.
	 */
	public function render_dashboard_widget() {
		$counts = wp_count_posts( 'listora_listing' );

		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$review_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$review_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews WHERE status = 'pending'" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$claims_pending = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}claims WHERE status = 'pending'" );

		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;">';
		printf( '<div><strong>%d</strong> %s</div>', (int) $counts->publish, esc_html__( 'Published', 'wb-listora' ) );
		printf( '<div><strong>%d</strong> %s</div>', (int) ( $counts->pending ?? 0 ), esc_html__( 'Pending', 'wb-listora' ) );
		printf( '<div><strong>%d</strong> %s</div>', (int) $review_total, esc_html__( 'Reviews', 'wb-listora' ) );
		printf( '<div><strong>%d</strong> %s</div>', (int) $claims_pending, esc_html__( 'Claims pending', 'wb-listora' ) );
		echo '</div>';

		if ( $review_pending > 0 ) {
			printf(
				'<p style="margin-top:0.5rem;color:#d63638;">%s</p>',
				sprintf(
					/* translators: %d: pending review count */
					esc_html__( '%d reviews pending moderation', 'wb-listora' ),
					(int) $review_pending
				)
			);
		}

		echo '<p style="margin-top:0.75rem;">';
		printf( '<a href="%s">%s</a> | ', esc_url( admin_url( 'edit.php?post_type=listora_listing' ) ), esc_html__( 'View All', 'wb-listora' ) );
		printf( '<a href="%s">%s</a> | ', esc_url( admin_url( 'post-new.php?post_type=listora_listing' ) ), esc_html__( 'Add New', 'wb-listora' ) );
		printf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=listora-settings' ) ), esc_html__( 'Settings', 'wb-listora' ) );
		echo '</p>';
	}

	// ─── Page Renderers (placeholders — full implementations in dedicated classes) ───

	public function render_dashboard_page() {
		echo '<div class="wrap wb-listora-admin"><h1>' . esc_html__( 'Listora Dashboard', 'wb-listora' ) . '</h1>';
		$this->render_dashboard_widget();
		echo '</div>';
	}

	public function render_listing_types_page() {
		echo '<div class="wrap wb-listora-admin"><h1>' . esc_html__( 'Listing Types', 'wb-listora' ) . '</h1>';
		$types = \WBListora\Core\Listing_Type_Registry::instance()->get_all();
		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Name', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Slug', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Fields', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Schema', 'wb-listora' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $types as $type ) {
			echo '<tr>';
			echo '<td><strong>' . esc_html( $type->get_name() ) . '</strong></td>';
			echo '<td>' . esc_html( $type->get_slug() ) . '</td>';
			echo '<td>' . esc_html( count( $type->get_all_fields() ) ) . '</td>';
			echo '<td>' . esc_html( $type->get_schema_type() ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Render Reviews moderation page.
	 */
	public function render_reviews_page() {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		// Handle approve/reject actions.
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
				echo '<div class="notice notice-success"><p>' . esc_html__( 'Review updated.', 'wb-listora' ) . '</p></div>';
			}
		}

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where         = '1=1';
		if ( $status_filter ) {
			$where .= $wpdb->prepare( ' AND r.status = %s', $status_filter );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $prefix is safe table prefix, $where is built with $wpdb->prepare().
		$reviews = $wpdb->get_results(
			"SELECT r.*, si.title as listing_title FROM {$prefix}reviews r LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id WHERE {$where} ORDER BY r.created_at DESC LIMIT 50",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		echo '<div class="wrap wb-listora-admin"><h1>' . esc_html__( 'Reviews', 'wb-listora' ) . '</h1>';

		// Status filter links.
		$base_url = admin_url( 'admin.php?page=listora-reviews' );
		echo '<ul class="subsubsub">';
		echo '<li><a href="' . esc_url( $base_url ) . '"' . ( ! $status_filter ? ' class="current"' : '' ) . '>' . esc_html__( 'All', 'wb-listora' ) . '</a> | </li>';
		echo '<li><a href="' . esc_url( add_query_arg( 'status', 'pending', $base_url ) ) . '"' . ( 'pending' === $status_filter ? ' class="current"' : '' ) . '>' . esc_html__( 'Pending', 'wb-listora' ) . '</a> | </li>';
		echo '<li><a href="' . esc_url( add_query_arg( 'status', 'approved', $base_url ) ) . '"' . ( 'approved' === $status_filter ? ' class="current"' : '' ) . '>' . esc_html__( 'Approved', 'wb-listora' ) . '</a> | </li>';
		echo '<li><a href="' . esc_url( add_query_arg( 'status', 'rejected', $base_url ) ) . '"' . ( 'rejected' === $status_filter ? ' class="current"' : '' ) . '>' . esc_html__( 'Rejected', 'wb-listora' ) . '</a></li>';
		echo '</ul><br class="clear">';

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Listing', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Author', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Rating', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Review', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wb-listora' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $reviews ) ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No reviews found.', 'wb-listora' ) . '</td></tr>';
		}

		foreach ( $reviews as $rev ) {
			$user = get_user_by( 'id', $rev['user_id'] );
			$name = $user ? $user->display_name : __( 'Anonymous', 'wb-listora' );

			echo '<tr>';
			echo '<td><a href="' . esc_url( get_permalink( $rev['listing_id'] ) ) . '">' . esc_html( $rev['listing_title'] ?: '#' . $rev['listing_id'] ) . '</a></td>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( str_repeat( '★', (int) $rev['overall_rating'] ) . str_repeat( '☆', 5 - (int) $rev['overall_rating'] ) ) . '</td>';
			echo '<td><strong>' . esc_html( $rev['title'] ) . '</strong><br>' . esc_html( wp_trim_words( $rev['content'], 15 ) ) . '</td>';
			echo '<td><span style="color:' . ( 'approved' === $rev['status'] ? '#16a34a' : ( 'pending' === $rev['status'] ? '#d97706' : '#dc2626' ) ) . ';font-weight:600;">' . esc_html( ucfirst( $rev['status'] ) ) . '</span></td>';
			echo '<td>' . esc_html( human_time_diff( strtotime( $rev['created_at'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'wb-listora' ) . '</td>';
			echo '<td>';
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
				) . '" class="button button-small">' . esc_html__( 'Approve', 'wb-listora' ) . '</a> ';
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
				) . '" class="button button-small">' . esc_html__( 'Reject', 'wb-listora' ) . '</a> ';
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
			) . '" class="button button-small" style="color:#dc2626;" onclick="return confirm(\'' . esc_js( __( 'Delete this review?', 'wb-listora' ) ) . '\');">' . esc_html__( 'Delete', 'wb-listora' ) . '</a>';
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Render Claims management page.
	 */
	public function render_claims_page() {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where         = '1=1';
		if ( $status_filter ) {
			$where .= $wpdb->prepare( ' AND c.status = %s', $status_filter );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $prefix is safe table prefix, $where is built with $wpdb->prepare().
		$claims = $wpdb->get_results(
			"SELECT c.*, p.post_title as listing_title, u.display_name as user_name, u.user_email FROM {$prefix}claims c LEFT JOIN {$wpdb->posts} p ON c.listing_id = p.ID LEFT JOIN {$wpdb->users} u ON c.user_id = u.ID WHERE {$where} ORDER BY c.created_at DESC LIMIT 50",
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		echo '<div class="wrap wb-listora-admin"><h1>' . esc_html__( 'Claims', 'wb-listora' ) . '</h1>';

		$base_url = admin_url( 'admin.php?page=listora-claims' );
		echo '<ul class="subsubsub">';
		echo '<li><a href="' . esc_url( $base_url ) . '"' . ( ! $status_filter ? ' class="current"' : '' ) . '>' . esc_html__( 'All', 'wb-listora' ) . '</a> | </li>';
		echo '<li><a href="' . esc_url( add_query_arg( 'status', 'pending', $base_url ) ) . '"' . ( 'pending' === $status_filter ? ' class="current"' : '' ) . '>' . esc_html__( 'Pending', 'wb-listora' ) . '</a> | </li>';
		echo '<li><a href="' . esc_url( add_query_arg( 'status', 'approved', $base_url ) ) . '"' . ( 'approved' === $status_filter ? ' class="current"' : '' ) . '>' . esc_html__( 'Approved', 'wb-listora' ) . '</a> | </li>';
		echo '<li><a href="' . esc_url( add_query_arg( 'status', 'rejected', $base_url ) ) . '"' . ( 'rejected' === $status_filter ? ' class="current"' : '' ) . '>' . esc_html__( 'Rejected', 'wb-listora' ) . '</a></li>';
		echo '</ul><br class="clear">';

		echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Listing', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Claimant', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Proof', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'wb-listora' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wb-listora' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $claims ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No claims found.', 'wb-listora' ) . '</td></tr>';
		}

		foreach ( $claims as $claim ) {
			echo '<tr>';
			echo '<td><a href="' . esc_url( get_permalink( $claim['listing_id'] ) ) . '">' . esc_html( $claim['listing_title'] ?: '#' . $claim['listing_id'] ) . '</a></td>';
			echo '<td>' . esc_html( $claim['user_name'] ?: __( 'Unknown', 'wb-listora' ) ) . '<br><small>' . esc_html( $claim['user_email'] ?? '' ) . '</small></td>';
			echo '<td>' . esc_html( wp_trim_words( $claim['proof_text'], 20 ) ) . '</td>';
			echo '<td><span style="color:' . ( 'approved' === $claim['status'] ? '#16a34a' : ( 'pending' === $claim['status'] ? '#d97706' : '#dc2626' ) ) . ';font-weight:600;">' . esc_html( ucfirst( $claim['status'] ) ) . '</span></td>';
			echo '<td>' . esc_html( human_time_diff( strtotime( $claim['created_at'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'wb-listora' ) . '</td>';
			echo '<td>';
			if ( 'pending' === $claim['status'] ) {
				$approve_url = wp_nonce_url( admin_url( 'admin.php?page=listora-claims&action=approve_claim&claim_id=' . $claim['id'] ), 'listora_claim_action' );
				$reject_url  = wp_nonce_url( admin_url( 'admin.php?page=listora-claims&action=reject_claim&claim_id=' . $claim['id'] ), 'listora_claim_action' );
				echo '<a href="' . esc_url( $approve_url ) . '" class="button button-small button-primary">' . esc_html__( 'Approve', 'wb-listora' ) . '</a> ';
				echo '<a href="' . esc_url( $reject_url ) . '" class="button button-small">' . esc_html__( 'Reject', 'wb-listora' ) . '</a>';
			} else {
				echo '<em>' . esc_html( ucfirst( $claim['status'] ) ) . '</em>';
			}
			echo '</td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
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
