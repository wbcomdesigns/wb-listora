<?php
/**
 * Asset management.
 *
 * @package WBListora
 */

namespace WBListora;

defined( 'ABSPATH' ) || exit;

/**
 * Handles script and style registration/enqueueing.
 */
class Assets {

	/**
	 * Enqueue frontend assets.
	 * Block-specific assets are loaded via block.json — this handles shared assets only.
	 */
	public function enqueue_frontend() {
		// Shared CSS variables and base styles.
		wp_register_style(
			'listora-shared',
			WB_LISTORA_PLUGIN_URL . 'assets/css/shared.css',
			array(),
			WB_LISTORA_VERSION
		);

		// Provide initial state for the Interactivity API store.
		$this->provide_interactivity_state();

		// Ensure wp-api-fetch global is available for script modules.
		wp_enqueue_script( 'wp-api-fetch' );

		// i18n strings for JS — delivered via a lightweight classic script shim
		// because wp_localize_script does not work with script module handles.
		wp_register_script( 'listora-i18n', false, array(), WB_LISTORA_VERSION, true );
		wp_enqueue_script( 'listora-i18n' );
		wp_localize_script(
			'listora-i18n',
			'listoraI18n',
			array(
				'noResults'       => __( 'No listings found', 'wb-listora' ),
				'result'          => __( 'result', 'wb-listora' ),
				'results'         => __( 'results', 'wb-listora' ),
				'searchError'     => __( 'Search failed. Please try again.', 'wb-listora' ),
				'geoNotSupported' => __( 'Geolocation is not supported by your browser.', 'wb-listora' ),
				'geoDenied'       => __( 'Location access denied. Use the location search instead.', 'wb-listora' ),
				'saveFavorite'    => __( 'Save to favorites', 'wb-listora' ),
				'removeFavorite'  => __( 'Remove from favorites', 'wb-listora' ),
				'share'           => __( 'Share', 'wb-listora' ),
				'claim'           => __( 'Claim this listing', 'wb-listora' ),
				'loginRequired'   => __( 'Please log in to continue.', 'wb-listora' ),
				'openNow'         => __( 'Open Now', 'wb-listora' ),
				'closed'          => __( 'Closed', 'wb-listora' ),
				'featured'        => __( 'Featured', 'wb-listora' ),
				'verified'        => __( 'Verified', 'wb-listora' ),
				'nearMe'          => __( 'Near Me', 'wb-listora' ),
				'clearAll'        => __( 'Clear all', 'wb-listora' ),
				'showResults'     => __( 'Show results', 'wb-listora' ),
				'moreFilters'     => __( 'More Filters', 'wb-listora' ),
				'prev'            => __( 'Previous', 'wb-listora' ),
				'next'            => __( 'Next', 'wb-listora' ),
			)
		);
	}

	/**
	 * Provide server-side state to the Interactivity API store.
	 */
	private function provide_interactivity_state() {
		$user_id   = get_current_user_id();
		$favorites = array();

		// Load user favorites.
		if ( $user_id > 0 ) {
			global $wpdb;
			$prefix    = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
			$favorites = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT listing_id FROM {$prefix}favorites WHERE user_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$user_id
				)
			);
			$favorites = array_map( 'intval', $favorites );
		}

		wp_interactivity_state(
			'listora/directory',
			array(
				'isLoggedIn' => is_user_logged_in(),
				'userId'     => $user_id,
				'favorites'  => $favorites,
				'perPage'    => (int) wb_listora_get_setting( 'per_page', 20 ),
				'radiusUnit' => wb_listora_get_setting( 'distance_unit', 'km' ),
			)
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_admin( $hook_suffix ) {
		// Only load on Listora admin pages.
		if ( ! $this->is_listora_admin_page( $hook_suffix ) ) {
			return;
		}

		wp_enqueue_style(
			'listora-admin',
			WB_LISTORA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WB_LISTORA_VERSION
		);

		// jQuery UI Sortable for field ordering in listing type editor.
		if ( $this->is_type_editor_page( $hook_suffix ) ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}

		wp_enqueue_script(
			'listora-admin-js',
			WB_LISTORA_PLUGIN_URL . 'src/admin/admin.js',
			array( 'jquery' ),
			WB_LISTORA_VERSION,
			true
		);

		wp_localize_script(
			'listora-admin-js',
			'listoraAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'restUrl'   => rest_url( WB_LISTORA_REST_NAMESPACE ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => WB_LISTORA_PLUGIN_URL,
			)
		);

		// Dashboard page: stat cards, quick actions, activity feed.
		if ( $this->is_dashboard_page( $hook_suffix ) ) {
			wp_enqueue_style(
				'listora-dashboard',
				WB_LISTORA_PLUGIN_URL . 'assets/css/admin/dashboard.css',
				array( 'listora-admin' ),
				WB_LISTORA_VERSION
			);
		}

		// Settings page: sidebar layout + hash nav + Lucide icons.
		if ( $this->is_settings_page( $hook_suffix ) ) {
			wp_enqueue_style(
				'listora-settings',
				WB_LISTORA_PLUGIN_URL . 'assets/css/admin/settings.css',
				array( 'listora-admin' ),
				WB_LISTORA_VERSION
			);

			wp_enqueue_script(
				'lucide',
				WB_LISTORA_PLUGIN_URL . 'assets/js/vendor/lucide.min.js',
				array(),
				'0.460.0',
				true
			);

			wp_enqueue_script(
				'listora-settings-nav',
				WB_LISTORA_PLUGIN_URL . 'assets/js/admin/settings-nav.js',
				array( 'lucide' ),
				WB_LISTORA_VERSION,
				true
			);

			// Needed for Reset to Defaults, Import, and Export REST calls.
			wp_enqueue_script( 'wp-api-fetch' );
		}
	}

	/**
	 * Check if the current admin page is a Listora page.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return bool
	 */
	private function is_listora_admin_page( $hook_suffix ) {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return false;
		}

		// Listora CPT screens.
		if ( 'listora_listing' === $screen->post_type ) {
			return true;
		}

		// Listora admin pages.
		$listora_pages = array(
			'toplevel_page_listora',
			'listora_page_listora-settings',
			'listora_page_listora-listing-types',
			'listora_page_listora-reviews',
			'listora_page_listora-claims',
			'listora_page_listora-import-export',
			'listora_page_listora-setup',
		);

		return in_array( $hook_suffix, $listora_pages, true );
	}

	/**
	 * Check if we're on the listing type editor page.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return bool
	 */
	private function is_type_editor_page( $hook_suffix ) {
		return 'listora_page_listora-listing-types' === $hook_suffix;
	}

	/**
	 * Check if we're on the Listora dashboard page.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return bool
	 */
	private function is_dashboard_page( $hook_suffix ) {
		return 'toplevel_page_listora' === $hook_suffix;
	}

	/**
	 * Check if we're on the Listora settings page.
	 *
	 * @param string $hook_suffix Admin page hook suffix.
	 * @return bool
	 */
	private function is_settings_page( $hook_suffix ) {
		return 'listora_page_listora-settings' === $hook_suffix;
	}
}
