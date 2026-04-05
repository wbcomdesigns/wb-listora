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
		add_action( 'wp_ajax_listora_dismiss_onboarding', array( $this, 'ajax_dismiss_onboarding' ) );

		// Keep Listora menu open on taxonomy and CPT screens.
		add_filter( 'parent_file', array( $this, 'fix_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'fix_submenu_highlight' ), 10, 2 );

		// Admin columns and filters for listings CPT.
		new Listing_Columns();

		// Custom fields on taxonomy term forms.
		new Taxonomy_Fields();

		// Import/Export and Review Reply are now served via REST endpoints:
		// GET  /listora/v1/export/csv     — class-import-export-controller.php
		// POST /listora/v1/import/csv     — class-import-export-controller.php
		// POST /listora/v1/reviews/{id}/reply — class-reviews-controller.php
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

		// Reviews page: enqueue wp-api-fetch for inline REST reply.
		if ( 'listora-reviews' === $page ) {
			wp_enqueue_script( 'wp-api-fetch' );
		}

		// Import/Export page: enqueue wp-api-fetch for REST-based import/export.
		if ( 'listora-import-export' === $page ) {
			wp_enqueue_script( 'wp-api-fetch' );
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
	 * Keep the Listora top-level menu open on taxonomy and CPT edit screens.
	 *
	 * @param string $parent_file Current parent file.
	 * @return string
	 */
	public function fix_parent_menu( $parent_file ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $parent_file;
		}

		// Taxonomy screens for Listora taxonomies.
		$listora_taxonomies = array(
			'listora_listing_cat',
			'listora_listing_type',
			'listora_listing_location',
			'listora_listing_feature',
			'listora_listing_tag',
		);

		if ( in_array( $screen->taxonomy, $listora_taxonomies, true ) ) {
			return 'listora';
		}

		// CPT edit screens.
		if ( 'listora_listing' === $screen->post_type && in_array( $screen->base, array( 'edit', 'post' ), true ) ) {
			return 'listora';
		}

		return $parent_file;
	}

	/**
	 * Highlight the correct submenu item on taxonomy screens.
	 *
	 * @param string|null $submenu_file Current submenu file.
	 * @param string      $parent_file  Parent file.
	 * @return string|null
	 */
	public function fix_submenu_highlight( $submenu_file, $parent_file ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $submenu_file;
		}

		$taxonomy_map = array(
			'listora_listing_cat'      => 'edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing',
			'listora_listing_location' => 'edit-tags.php?taxonomy=listora_listing_location&post_type=listora_listing',
			'listora_listing_feature'  => 'edit-tags.php?taxonomy=listora_listing_feature&post_type=listora_listing',
		);

		if ( isset( $taxonomy_map[ $screen->taxonomy ] ) ) {
			return $taxonomy_map[ $screen->taxonomy ];
		}

		return $submenu_file;
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

	/**
	 * AJAX handler to dismiss the onboarding checklist.
	 */
	public function ajax_dismiss_onboarding() {
		check_ajax_referer( 'listora_dismiss_onboarding', '_nonce' );

		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wb-listora' ) ), 403 );
		}

		update_option( 'listora_onboarding_dismissed', true );
		wp_send_json_success();
	}

	/**
	 * Get onboarding checklist items with their completion status.
	 *
	 * @return array[] Checklist items with 'label', 'done', 'icon', and optional 'url'.
	 */
	private function get_onboarding_checklist() {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;

		$listing_count = (int) wp_count_posts( 'listora_listing' )->publish;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$review_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}reviews" );

		$settings  = get_option( 'wb_listora_settings', array() );
		$map_lat   = ! empty( $settings['map_default_lat'] ) && 0 !== (float) $settings['map_default_lat'];
		$has_notif = ! empty( $settings['email_new_submission'] ) || ! empty( $settings['email_new_review'] );

		// Check if any page uses a Listora block.
		$has_directory_page = false;
		$pages              = get_pages( array( 'number' => 50 ) );
		if ( $pages ) {
			foreach ( $pages as $page ) {
				if ( has_block( 'listora/listing-grid', $page ) || has_block( 'listora/listing-search', $page ) || has_block( 'listora/listing-map', $page ) ) {
					$has_directory_page = true;
					break;
				}
			}
		}

		return array(
			array(
				'label' => __( 'Plugin activated', 'wb-listora' ),
				'done'  => true,
				'icon'  => 'check-circle',
			),
			array(
				'label' => __( 'Setup wizard completed', 'wb-listora' ),
				'done'  => (bool) wb_listora_get_setting( 'setup_complete' ),
				'icon'  => 'wand-2',
				'url'   => admin_url( 'admin.php?page=listora-setup' ),
			),
			array(
				'label' => __( 'First listing created', 'wb-listora' ),
				'done'  => $listing_count > 0,
				'icon'  => 'map-pin',
				'url'   => admin_url( 'post-new.php?post_type=listora_listing' ),
			),
			array(
				'label' => __( 'Directory page configured', 'wb-listora' ),
				'done'  => $has_directory_page,
				'icon'  => 'layout',
				'url'   => admin_url( 'post-new.php?post_type=page' ),
			),
			array(
				'label' => __( 'Map settings configured', 'wb-listora' ),
				'done'  => $map_lat,
				'icon'  => 'map',
				'url'   => admin_url( 'admin.php?page=listora-settings&tab=map' ),
			),
			array(
				'label' => __( 'Email notifications configured', 'wb-listora' ),
				'done'  => $has_notif,
				'icon'  => 'bell',
				'url'   => admin_url( 'admin.php?page=listora-settings&tab=notifications' ),
			),
			array(
				'label' => __( 'First review received', 'wb-listora' ),
				'done'  => $review_count > 0,
				'icon'  => 'star',
				'url'   => admin_url( 'admin.php?page=listora-reviews' ),
			),
		);
	}

	/**
	 * Render the onboarding checklist widget on the dashboard.
	 */
	private function render_onboarding_checklist() {
		// Do not show if dismissed.
		if ( get_option( 'listora_onboarding_dismissed' ) ) {
			return;
		}

		$checklist     = $this->get_onboarding_checklist();
		$completed     = count( array_filter( $checklist, fn( $item ) => $item['done'] ) );
		$total         = count( $checklist );
		$all_done      = $completed === $total;
		$pct           = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;
		$dismiss_nonce = wp_create_nonce( 'listora_dismiss_onboarding' );

		echo '<div class="listora-card listora-onboarding" id="listora-onboarding-checklist">';
		echo '<div class="listora-card__head">';
		echo '<div>';
		echo '<h2 class="listora-card__title"><i data-lucide="clipboard-check" class="listora-icon--sm"></i> ';
		echo esc_html__( 'Getting Started', 'wb-listora' ) . '</h2>';
		echo '<p class="listora-card__desc">';
		printf(
			/* translators: 1: completed count, 2: total count */
			esc_html__( '%1$d of %2$d steps completed', 'wb-listora' ),
			$completed, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- integer used with %d format specifier.
			$total // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- integer used with %d format specifier.
		);
		echo '</p>';
		echo '</div>';
		echo '<button type="button" class="listora-btn listora-btn--sm listora-onboarding__dismiss" id="listora-dismiss-onboarding" data-nonce="' . esc_attr( $dismiss_nonce ) . '">';
		echo '<i data-lucide="x"></i> ' . esc_html__( 'Dismiss', 'wb-listora' ) . '</button>';
		echo '</div>';

		echo '<div class="listora-card__body">';

		// Progress bar.
		echo '<div class="listora-onboarding__progress">';
		echo '<div class="listora-onboarding__progress-bar">';
		echo '<div class="listora-onboarding__progress-fill" style="width:' . esc_attr( $pct ) . '%;"></div>';
		echo '</div>';
		echo '<span class="listora-onboarding__progress-pct">' . esc_html( $pct ) . '%</span>';
		echo '</div>';

		// Checklist items.
		echo '<ul class="listora-onboarding__list">';
		foreach ( $checklist as $item ) {
			$done_class = $item['done'] ? 'listora-onboarding__item--done' : '';
			echo '<li class="listora-onboarding__item ' . esc_attr( $done_class ) . '">';

			echo '<span class="listora-onboarding__check">';
			if ( $item['done'] ) {
				echo '<i data-lucide="check-circle-2"></i>';
			} else {
				echo '<i data-lucide="circle"></i>';
			}
			echo '</span>';

			echo '<span class="listora-onboarding__item-icon"><i data-lucide="' . esc_attr( $item['icon'] ) . '"></i></span>';

			if ( ! $item['done'] && ! empty( $item['url'] ) ) {
				echo '<a href="' . esc_url( $item['url'] ) . '" class="listora-onboarding__item-label">' . esc_html( $item['label'] ) . '</a>';
			} else {
				echo '<span class="listora-onboarding__item-label">' . esc_html( $item['label'] ) . '</span>';
			}

			echo '</li>';
		}
		echo '</ul>';

		if ( $all_done ) {
			echo '<div class="listora-onboarding__complete">';
			echo '<i data-lucide="party-popper"></i>';
			echo '<p>' . esc_html__( 'All set! Your directory is ready to go.', 'wb-listora' ) . '</p>';
			echo '</div>';
		}

		echo '</div>'; // .listora-card__body
		echo '</div>'; // .listora-card

		// Inline JS for dismiss.
		?>
		<script>
		document.addEventListener( 'DOMContentLoaded', function() {
			var btn = document.getElementById( 'listora-dismiss-onboarding' );
			if ( ! btn ) return;
			btn.addEventListener( 'click', function() {
				var card = document.getElementById( 'listora-onboarding-checklist' );
				if ( card ) card.style.opacity = '0.5';
				var formData = new FormData();
				formData.append( 'action', 'listora_dismiss_onboarding' );
				formData.append( '_nonce', btn.dataset.nonce );
				fetch( ajaxurl, { method: 'POST', body: formData } )
					.then( function() {
						if ( card ) {
							card.style.transition = 'opacity 0.3s, max-height 0.4s';
							card.style.opacity = '0';
							card.style.maxHeight = '0';
							card.style.overflow = 'hidden';
							card.style.marginBottom = '0';
							card.style.padding = '0';
							setTimeout( function() { card.remove(); }, 500 );
						}
					} );
			} );
		} );
		</script>
		<?php
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

		// ── Onboarding Checklist ──.
		$this->render_onboarding_checklist();

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

		// Reply feedback is now handled inline via REST + JS (no page reload).

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
		echo '<label for="listora-reviews-search" class="screen-reader-text">' . esc_html__( 'Search reviews', 'wb-listora' ) . '</label>';
		echo '<input type="search" id="listora-reviews-search" name="s" class="listora-search-input" placeholder="' . esc_attr__( 'Search reviews...', 'wb-listora' ) . '" value="' . esc_attr( $search_term ) . '">';
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
			echo '<th class="listora-table__check"><input type="checkbox" class="listora-table__select-all" aria-label="' . esc_attr__( 'Select all reviews', 'wb-listora' ) . '"></th>';
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
				echo '<td class="listora-table__check"><input type="checkbox" name="ids[]" value="' . esc_attr( $rev['id'] ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: listing title */ __( 'Select review for %s', 'wb-listora' ), $rev['listing_title'] ? $rev['listing_title'] : '#' . $rev['listing_id'] ) ) . '"></td>';
				echo '<td><a href="' . esc_url( get_permalink( $rev['listing_id'] ) ) . '" class="listora-row-title">' . esc_html( $rev['listing_title'] ? $rev['listing_title'] : '#' . $rev['listing_id'] ) . '</a></td>';
				echo '<td>' . esc_html( $name ) . '</td>';
				echo '<td><span class="listora-star-rating">' . esc_html( $stars_filled ) . '<span class="listora-star-rating__empty">' . esc_html( $stars_empty ) . '</span></span></td>';
				echo '<td>';
					echo '<div class="listora-review-excerpt__title">' . esc_html( $rev['title'] ) . '</div>';
					echo '<div class="listora-review-excerpt__text">' . esc_html( wp_trim_words( $rev['content'], 15 ) ) . '</div>';
				if ( ! empty( $rev['owner_reply'] ) ) {
					echo '<div class="listora-review-excerpt__reply" style="margin-top:0.5rem;padding:0.5rem 0.75rem;background:#f0f6fc;border-left:3px solid #0073aa;border-radius:3px;font-size:12px;">';
					echo '<strong>' . esc_html__( 'Owner Reply:', 'wb-listora' ) . '</strong> ';
					echo esc_html( wp_trim_words( $rev['owner_reply'], 15 ) );
					if ( ! empty( $rev['owner_reply_at'] ) ) {
						echo ' <span style="color:#888;">(' . esc_html( human_time_diff( strtotime( $rev['owner_reply_at'] ), current_time( 'timestamp' ) ) ) . ' ' . esc_html__( 'ago', 'wb-listora' ) . ')</span>';
					}
					echo '</div>';
				}
					echo '</td>';
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
					$reply_label = empty( $rev['owner_reply'] ) ? __( 'Reply', 'wb-listora' ) : __( 'Edit Reply', 'wb-listora' );
					echo '<a href="#" class="listora-action-link listora-review-reply-toggle" data-review-id="' . esc_attr( $rev['id'] ) . '">' . esc_html( $reply_label ) . '</a>';
					echo '</div></td>';

					echo '</tr>';

					// Inline reply form row (hidden by default) — uses REST endpoint.
					echo '<tr class="listora-review-reply-row" id="listora-reply-row-' . esc_attr( $rev['id'] ) . '" style="display:none;">';
					echo '<td colspan="8" style="padding:0.75rem 1rem;background:#f9f9f9;">';
					echo '<div class="listora-reply-form" data-review-id="' . esc_attr( $rev['id'] ) . '">';
					echo '<div style="display:flex;gap:0.5rem;align-items:flex-start;">';
					echo '<textarea class="listora-reply-textarea" rows="2" style="flex:1;min-width:0;" placeholder="' . esc_attr__( 'Write your reply...', 'wb-listora' ) . '" aria-label="' . esc_attr__( 'Reply to review', 'wb-listora' ) . '">' . esc_textarea( $rev['owner_reply'] ?? '' ) . '</textarea>';
					echo '<button type="button" class="listora-btn listora-btn--sm listora-btn--primary listora-reply-submit">' . esc_html__( 'Send Reply', 'wb-listora' ) . '</button>';
					echo '</div>';
					echo '<div class="listora-reply-status" style="margin-top:0.25rem;font-size:12px;"></div>';
					echo '</div>';
					echo '</td>';
					echo '</tr>';
			}

			echo '</tbody></table>';
			echo '</div>';

			// Table footer with bulk actions.
			echo '<div class="listora-table-footer">';
			echo '<div class="listora-bulk-actions">';
			echo '<label for="listora-reviews-bulk-action" class="screen-reader-text">' . esc_html__( 'Bulk actions for reviews', 'wb-listora' ) . '</label>';
			echo '<select id="listora-reviews-bulk-action" name="bulk_action" class="listora-filter-select" required>';
			echo '<option value="">' . esc_html__( 'Bulk Actions', 'wb-listora' ) . '</option>';
			echo '<option value="approve">' . esc_html__( 'Approve', 'wb-listora' ) . '</option>';
			echo '<option value="reject">' . esc_html__( 'Reject', 'wb-listora' ) . '</option>';
			echo '<option value="delete">' . esc_html__( 'Delete', 'wb-listora' ) . '</option>';
			echo '</select>';
			echo '<button type="submit" class="listora-btn listora-btn--sm" onclick="if(this.form.checkValidity()){this.disabled=true;this.textContent=\'' . esc_js( __( 'Processing...', 'wb-listora' ) ) . '\';this.form.submit();}">' . esc_html__( 'Apply', 'wb-listora' ) . '</button>';
			echo '</div>';
			echo '</div>';

			echo '</form>';
		}

		// Inline JS for reply toggle.
		?>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Toggle reply row visibility.
			document.querySelectorAll('.listora-review-reply-toggle').forEach(function(link) {
				link.addEventListener('click', function(e) {
					e.preventDefault();
					var reviewId = this.getAttribute('data-review-id');
					var row = document.getElementById('listora-reply-row-' + reviewId);
					if (row) {
						row.style.display = row.style.display === 'none' ? '' : 'none';
						if (row.style.display !== 'none') {
							var textarea = row.querySelector('textarea');
							if (textarea) textarea.focus();
						}
					}
				});
			});

			// Submit reply via REST endpoint.
			document.querySelectorAll('.listora-reply-submit').forEach(function(btn) {
				btn.addEventListener('click', function() {
					var form     = this.closest('.listora-reply-form');
					var reviewId = form.getAttribute('data-review-id');
					var textarea = form.querySelector('.listora-reply-textarea');
					var status   = form.querySelector('.listora-reply-status');
					var content  = textarea.value.trim();

					if (!content) {
						status.textContent = '<?php echo esc_js( __( 'Please enter a reply.', 'wb-listora' ) ); ?>';
						status.style.color = '#d63638';
						return;
					}

					btn.disabled = true;
					btn.textContent = '<?php echo esc_js( __( 'Sending...', 'wb-listora' ) ); ?>';
					status.textContent = '';

					wp.apiFetch({
						path: '/listora/v1/reviews/' + reviewId + '/reply',
						method: 'POST',
						data: { content: content }
					}).then(function() {
						status.textContent = '<?php echo esc_js( __( 'Reply saved.', 'wb-listora' ) ); ?>';
						status.style.color = '#00a32a';
						btn.textContent = '<?php echo esc_js( __( 'Send Reply', 'wb-listora' ) ); ?>';
						btn.disabled = false;
					}).catch(function(err) {
						status.textContent = err.message || '<?php echo esc_js( __( 'Failed to save reply.', 'wb-listora' ) ); ?>';
						status.style.color = '#d63638';
						btn.textContent = '<?php echo esc_js( __( 'Send Reply', 'wb-listora' ) ); ?>';
						btn.disabled = false;
					});
				});
			});
		});
		</script>
		<?php

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
		echo '<label for="listora-claims-search" class="screen-reader-text">' . esc_html__( 'Search claims', 'wb-listora' ) . '</label>';
		echo '<input type="search" id="listora-claims-search" name="s" class="listora-search-input" placeholder="' . esc_attr__( 'Search claims...', 'wb-listora' ) . '" value="' . esc_attr( $search_term ) . '">';
		echo '<button type="submit" class="listora-btn listora-btn--sm" onclick="this.disabled=true;this.textContent=\'' . esc_js( __( 'Filtering...', 'wb-listora' ) ) . '\';this.form.submit();">' . esc_html__( 'Filter', 'wb-listora' ) . '</button>';
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
			echo '<th class="listora-table__check"><input type="checkbox" class="listora-table__select-all" aria-label="' . esc_attr__( 'Select all claims', 'wb-listora' ) . '"></th>';
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
				echo '<td class="listora-table__check"><input type="checkbox" name="ids[]" value="' . esc_attr( $claim['id'] ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: listing title */ __( 'Select claim for %s', 'wb-listora' ), $claim['listing_title'] ? $claim['listing_title'] : '#' . $claim['listing_id'] ) ) . '"></td>';
				echo '<td><a href="' . esc_url( get_permalink( $claim['listing_id'] ) ) . '" class="listora-row-title">' . esc_html( $claim['listing_title'] ? $claim['listing_title'] : '#' . $claim['listing_id'] ) . '</a></td>';
				echo '<td>' . esc_html( $claim['user_name'] ? $claim['user_name'] : __( 'Unknown', 'wb-listora' ) ) . '</td>';
				echo '<td>' . esc_html( isset( $claim['user_email'] ) ? $claim['user_email'] : '' ) . '</td>';
				echo '<td>';
				echo esc_html( wp_trim_words( $claim['proof_text'], 20 ) );
				if ( ! empty( $claim['proof_files'] ) ) {
					$proof_file_ids = json_decode( $claim['proof_files'], true );
					if ( is_array( $proof_file_ids ) ) {
						foreach ( $proof_file_ids as $att_id ) {
							$att_url  = wp_get_attachment_url( (int) $att_id );
							$att_mime = get_post_mime_type( (int) $att_id );
							if ( $att_url ) {
								echo '<div class="listora-proof-file" style="margin-top:6px;">';
								if ( $att_mime && str_starts_with( $att_mime, 'image/' ) ) {
									echo '<a href="' . esc_url( $att_url ) . '" target="_blank" rel="noopener" title="' . esc_attr__( 'View proof document', 'wb-listora' ) . '">';
									echo '<img src="' . esc_url( $att_url ) . '" alt="' . esc_attr__( 'Proof document', 'wb-listora' ) . '" style="max-width:80px;max-height:60px;border-radius:4px;border:1px solid #ddd;" />';
									echo '</a>';
								} else {
									echo '<a href="' . esc_url( $att_url ) . '" target="_blank" rel="noopener" class="listora-action-link">';
									echo '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:3px;" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
									echo esc_html__( 'View proof document', 'wb-listora' );
									echo '</a>';
								}
								echo '</div>';
							}
						}
					}
				}
				echo '</td>';
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
			echo '<label for="listora-claims-bulk-action" class="screen-reader-text">' . esc_html__( 'Bulk actions for claims', 'wb-listora' ) . '</label>';
			echo '<select id="listora-claims-bulk-action" name="bulk_action" class="listora-filter-select" required>';
			echo '<option value="">' . esc_html__( 'Bulk Actions', 'wb-listora' ) . '</option>';
			echo '<option value="approve">' . esc_html__( 'Approve', 'wb-listora' ) . '</option>';
			echo '<option value="reject">' . esc_html__( 'Reject', 'wb-listora' ) . '</option>';
			echo '<option value="delete">' . esc_html__( 'Delete', 'wb-listora' ) . '</option>';
			echo '</select>';
			echo '<button type="submit" class="listora-btn listora-btn--sm" onclick="if(this.form.checkValidity()){this.disabled=true;this.textContent=\'' . esc_js( __( 'Processing...', 'wb-listora' ) ) . '\';this.form.submit();}">' . esc_html__( 'Apply', 'wb-listora' ) . '</button>';
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
		// Get listing types for the dropdowns.
		$type_terms = get_terms(
			array(
				'taxonomy'   => 'listora_listing_type',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $type_terms ) ) {
			$type_terms = array();
		}

		echo '<div class="wrap wb-listora-admin">';

		// Page header.
		echo '<div class="listora-page-header">';
		echo '<div class="listora-page-header__left">';
		echo '<h1 class="listora-page-header__title"><i data-lucide="arrow-left-right" class="listora-icon--sm"></i> ';
		echo esc_html__( 'Import / Export', 'wb-listora' ) . '</h1>';
		echo '<p class="listora-page-header__desc">';
		echo esc_html__( 'Import listings from CSV or export your directory data.', 'wb-listora' ) . '</p>';
		echo '</div>';
		echo '</div>';

		echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem;margin-top:1rem;">';

		// ── Export Card ──.
		echo '<div class="listora-card" style="padding:1.5rem;">';
		echo '<h2><i data-lucide="download" class="listora-icon--sm"></i> ' . esc_html__( 'Export Listings', 'wb-listora' ) . '</h2>';
		echo '<p>' . esc_html__( 'Download all listings as a CSV file for backup or migration.', 'wb-listora' ) . '</p>';

		echo '<div style="margin:1rem 0;">';
		echo '<label for="listora-export-type" style="display:block;margin-bottom:0.25rem;font-weight:500;">' . esc_html__( 'Listing Type (optional)', 'wb-listora' ) . '</label>';
		echo '<select id="listora-export-type" class="listora-filter-select" style="width:100%;">';
		echo '<option value="">' . esc_html__( 'All Types', 'wb-listora' ) . '</option>';
		foreach ( $type_terms as $term ) {
			echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<button type="button" id="listora-export-btn" class="listora-btn listora-btn--primary">';
		echo '<i data-lucide="download"></i> ' . esc_html__( 'Export CSV', 'wb-listora' ) . '</button>';
		echo '<div id="listora-export-status" style="margin-top:0.5rem;font-size:12px;"></div>';

		echo '<p style="margin-top:1rem;color:#757575;font-size:12px;"><strong>' . esc_html__( 'WP-CLI:', 'wb-listora' ) . '</strong> <code>wp listora export --type=restaurant --output=file.csv</code></p>';
		echo '</div>';

		// ── Import Card ──.
		echo '<div class="listora-card" style="padding:1.5rem;">';
		echo '<h2><i data-lucide="upload" class="listora-icon--sm"></i> ' . esc_html__( 'Import Listings', 'wb-listora' ) . '</h2>';
		echo '<p>' . esc_html__( 'Import listings from a CSV file. The first row must be column headers.', 'wb-listora' ) . '</p>';

		echo '<div style="margin:1rem 0;">';
		echo '<label for="listora-import-type" style="display:block;margin-bottom:0.25rem;font-weight:500;">' . esc_html__( 'Listing Type', 'wb-listora' ) . ' <span style="color:#d63638;">*</span></label>';
		echo '<select id="listora-import-type" class="listora-filter-select" style="width:100%;" required>';
		echo '<option value="">' . esc_html__( 'Select a type...', 'wb-listora' ) . '</option>';
		foreach ( $type_terms as $term ) {
			echo '<option value="' . esc_attr( $term->slug ) . '">' . esc_html( $term->name ) . '</option>';
		}
		echo '</select>';
		echo '</div>';

		echo '<div style="margin:1rem 0;">';
		echo '<label for="listora-import-file" style="display:block;margin-bottom:0.25rem;font-weight:500;">' . esc_html__( 'CSV File', 'wb-listora' ) . ' <span style="color:#d63638;">*</span></label>';
		echo '<input type="file" id="listora-import-file" accept=".csv,text/csv">';
		echo '</div>';

		echo '<div style="margin:1rem 0;">';
		echo '<label style="display:flex;align-items:center;gap:0.5rem;">';
		echo '<input type="checkbox" id="listora-import-dryrun">';
		echo esc_html__( 'Dry run (validate only, no listings created)', 'wb-listora' );
		echo '</label>';
		echo '</div>';

		echo '<button type="button" id="listora-import-btn" class="listora-btn listora-btn--primary">';
		echo '<i data-lucide="upload"></i> ' . esc_html__( 'Import CSV', 'wb-listora' ) . '</button>';
		echo '<div id="listora-import-status" style="margin-top:0.5rem;font-size:12px;"></div>';

		echo '<p style="margin-top:1rem;color:#757575;font-size:12px;"><strong>' . esc_html__( 'WP-CLI:', 'wb-listora' ) . '</strong> <code>wp listora import &lt;file.csv&gt; --type=restaurant</code></p>';
		echo '</div>';

		echo '</div>'; // grid
		?>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// ── Export ──
			var exportBtn = document.getElementById('listora-export-btn');
			if (exportBtn) {
				exportBtn.addEventListener('click', function() {
					var type = document.getElementById('listora-export-type').value;
					var status = document.getElementById('listora-export-status');
					var params = new URLSearchParams({ include_meta: '1' });
					if (type) params.set('type', type);

					status.textContent = '<?php echo esc_js( __( 'Generating export...', 'wb-listora' ) ); ?>';
					status.style.color = '#2271b1';
					exportBtn.disabled = true;

					// Use a direct link to the REST endpoint for download.
					var url = '<?php echo esc_js( rest_url( 'listora/v1/export/csv' ) ); ?>' + '?' + params.toString();

					// Append the nonce.
					url += '&_wpnonce=' + '<?php echo esc_js( wp_create_nonce( 'wp_rest' ) ); ?>';

					// Trigger download via hidden link.
					var a = document.createElement('a');
					a.href = url;
					a.download = '';
					document.body.appendChild(a);
					a.click();
					document.body.removeChild(a);

					status.textContent = '<?php echo esc_js( __( 'Download started.', 'wb-listora' ) ); ?>';
					status.style.color = '#00a32a';
					exportBtn.disabled = false;
				});
			}

			// ── Import ──
			var importBtn = document.getElementById('listora-import-btn');
			if (importBtn) {
				importBtn.addEventListener('click', function() {
					var typeSlug  = document.getElementById('listora-import-type').value;
					var fileInput = document.getElementById('listora-import-file');
					var dryRun    = document.getElementById('listora-import-dryrun').checked;
					var status    = document.getElementById('listora-import-status');

					if (!typeSlug) {
						status.textContent = '<?php echo esc_js( __( 'Please select a listing type.', 'wb-listora' ) ); ?>';
						status.style.color = '#d63638';
						return;
					}
					if (!fileInput.files.length) {
						status.textContent = '<?php echo esc_js( __( 'Please select a CSV file.', 'wb-listora' ) ); ?>';
						status.style.color = '#d63638';
						return;
					}

					importBtn.disabled = true;
					importBtn.textContent = '<?php echo esc_js( __( 'Importing...', 'wb-listora' ) ); ?>';
					status.textContent = '';

					// Build auto-mapping: column index maps to column header name lowercase.
					// For basic use: 0=title, 1=description. Users can customize via WP-CLI for complex mapping.
					// Here we send a simple sequential mapping.
					var formData = new FormData();
					formData.append('file', fileInput.files[0]);
					formData.append('type_slug', typeSlug);
					formData.append('dry_run', dryRun ? '1' : '0');
					// Default mapping: first col = title, second = description.
					formData.append('mapping', JSON.stringify({"0": "title", "1": "description", "2": "category", "3": "tags"}));

					wp.apiFetch({
						path: '/listora/v1/import/csv',
						method: 'POST',
						body: formData,
						parse: true,
					}).then(function(res) {
						var msg = '<?php echo esc_js( __( 'Imported:', 'wb-listora' ) ); ?> ' + res.imported;
						if (res.skipped) msg += ', <?php echo esc_js( __( 'Skipped:', 'wb-listora' ) ); ?> ' + res.skipped;
						if (res.errors)  msg += ', <?php echo esc_js( __( 'Errors:', 'wb-listora' ) ); ?> ' + res.errors;
						if (res.dry_run) msg += ' (<?php echo esc_js( __( 'dry run', 'wb-listora' ) ); ?>)';
						status.textContent = msg;
						status.style.color = res.errors ? '#d63638' : '#00a32a';
						importBtn.textContent = '<?php echo esc_js( __( 'Import CSV', 'wb-listora' ) ); ?>';
						importBtn.disabled = false;
					}).catch(function(err) {
						status.textContent = err.message || '<?php echo esc_js( __( 'Import failed.', 'wb-listora' ) ); ?>';
						status.style.color = '#d63638';
						importBtn.textContent = '<?php echo esc_js( __( 'Import CSV', 'wb-listora' ) ); ?>';
						importBtn.disabled = false;
					});
				});
			}
		});
		</script>
		<?php

		echo '</div>'; // .wrap
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
