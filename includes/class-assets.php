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

		// Confirm modal — registered, enqueued by blocks that need it (listing-detail, user-dashboard).
		wp_register_style(
			'listora-confirm',
			WB_LISTORA_PLUGIN_URL . 'assets/css/shared/confirm.css',
			array(),
			WB_LISTORA_VERSION
		);
		wp_register_script(
			'listora-confirm',
			WB_LISTORA_PLUGIN_URL . 'assets/js/shared/confirm.js',
			array(),
			WB_LISTORA_VERSION,
			true
		);

		// Submit-lock delegation — replaces inline onclick disable-on-submit patterns.
		wp_enqueue_script(
			'listora-submit-lock',
			WB_LISTORA_PLUGIN_URL . 'assets/js/shared/submit-lock.js',
			array(),
			WB_LISTORA_VERSION,
			true
		);

		// Pro upgrade CTA — loaded as a dependency of shared.css so any block that
		// renders the user dashboard / submission pages gets it automatically.
		wp_register_style(
			'listora-pro-cta',
			WB_LISTORA_PLUGIN_URL . 'assets/css/shared/pro-cta.css',
			array( 'listora-shared' ),
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
				'featureSuccess'  => __( 'Listing featured.', 'wb-listora' ),
				'featureFailed'   => __( 'Unable to feature this listing.', 'wb-listora' ),
				'leadSent'        => __( 'Message sent successfully.', 'wb-listora' ),
				'leadFailed'      => __( 'Failed to send message. Please try again.', 'wb-listora' ),
				'leadRequired'    => __( 'Please fill in all required fields.', 'wb-listora' ),
				'leadSending'     => __( 'Sending…', 'wb-listora' ),
				'leadSend'        => __( 'Send Message', 'wb-listora' ),
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
				'submitting'      => __( 'Submitting\u2026', 'wb-listora' ),
				'submitClaim'     => __( 'Submit Claim', 'wb-listora' ),
				'claimSubmitted'  => __( 'Claim submitted — we\'ll email you when it\'s reviewed.', 'wb-listora' ),
				'claimFailed'     => __( 'Failed to submit claim. Please try again.', 'wb-listora' ),
				'viewMyClaims'    => __( 'View my claims', 'wb-listora' ),
				'dashboardUrl'    => function_exists( 'wb_listora_get_dashboard_url' ) ? wb_listora_get_dashboard_url() : '',
				'linkCopied'      => __( 'Link copied!', 'wb-listora' ),
				'reportSubmitted' => __( 'Report submitted. Thank you.', 'wb-listora' ),
				// Owner: Deactivate listing modal (T1 — store.js deactivateListing).
				'confirmDeactivate'      => __( 'Deactivate this listing? It will be hidden from the public directory until you reactivate it.', 'wb-listora' ),
				'confirmDeactivateTitle' => __( 'Deactivate listing?', 'wb-listora' ),
				'deactivate'             => __( 'Deactivate', 'wb-listora' ),
				'deactivateSuccess'      => __( 'Listing deactivated.', 'wb-listora' ),
				'deactivateFailed'       => __( 'Unable to deactivate listing.', 'wb-listora' ),
				// Owner: Reactivate listing modal (Card 8 — store.js reactivateListing).
				'confirmReactivate'      => __( 'Reactivate this listing? It will reappear in the public directory.', 'wb-listora' ),
				'confirmReactivateTitle' => __( 'Reactivate listing?', 'wb-listora' ),
				'reactivate'             => __( 'Reactivate', 'wb-listora' ),
				'reactivateSuccess'      => __( 'Listing reactivated.', 'wb-listora' ),
				'reactivateFailed'       => __( 'Unable to reactivate listing.', 'wb-listora' ),
				// Submission media uploader caps. PHP's upload_max_filesize is the
				// hard ceiling; this is the user-friendly cap exposed to the
				// listing-submission widget so a 50 MB photo gets rejected before
				// the user uploads it. JS-side check; server-side enforcement
				// still relies on PHP's setting.
				'maxUploadSizeMb'        => max( 1, (int) wb_listora_get_setting( 'max_upload_size', 5 ) ),
				'fileTooLarge'           => __( 'This file exceeds the %d MB upload limit. Please choose a smaller image.', 'wb-listora' ),
				// Helpful-vote outcome messages. Distinguishing these from a
				// generic "error" lets the UI show honest status (already
				// voted, own review, login required) instead of the same
				// scary `is-error` state for every non-success path.
				'alreadyVoted'           => __( 'You have already marked this review as helpful.', 'wb-listora' ),
				'ownReview'              => __( 'You can\'t mark your own review as helpful.', 'wb-listora' ),
				// Surfaced when wp.media is missing on the submission page —
				// the submission render now always enqueues it, so this only
				// fires on a script-load race or a third-party plugin that
				// dequeues media. Without a visible message the upload zone
				// looks broken (silent click).
				'mediaUnavailable'       => __( 'The media uploader could not load. Please refresh the page and try again.', 'wb-listora' ),
			)
		);

		// Toast utility — lightweight, no dependencies. Same API as assets/js/shared/toast.js (admin).
		wp_add_inline_script(
			'listora-i18n',
			'if(!window.listoraToast){(function(){var c;function i(){if(c)return;c=document.createElement("div");c.className="listora-toast-container";document.body.appendChild(c)}window.listoraToast=function(m,o){i();var t="info",d=4000;if(typeof o==="string")t=o;else if(o&&typeof o==="object"){t=o.type||"info";d=o.duration||4000}var e=document.createElement("div");e.className="listora-toast listora-toast--"+t;e.setAttribute("role","status");e.setAttribute("aria-live","polite");e.textContent=m;c.appendChild(e);setTimeout(function(){e.classList.add("is-visible")},10);setTimeout(function(){e.classList.remove("is-visible");setTimeout(function(){if(e.parentNode)e.parentNode.removeChild(e)},300)},d)}})()}'
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

			// Settings page behaviors — replaces inline <script> blocks
			// previously emitted from class-settings-page.php (no inline JS rule).
			wp_enqueue_script(
				'listora-settings-page',
				WB_LISTORA_PLUGIN_URL . 'assets/js/admin/settings-page.js',
				array( 'wp-api-fetch', 'listora-settings-nav' ),
				WB_LISTORA_VERSION,
				true
			);

			wp_localize_script(
				'listora-settings-page',
				'wbListoraSettings',
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'restNonce'       => wp_create_nonce( 'wp_rest' ),
					'migrationNonce'  => wp_create_nonce( 'listora_migration' ),
					'exportCsvUrl'    => rest_url( 'listora/v1/export/csv' ),
					'i18n'            => array(
						'generatingExport'    => __( 'Generating export...', 'wb-listora' ),
						'downloadStarted'     => __( 'Download started.', 'wb-listora' ),
						'selectListingType'   => __( 'Please select a listing type.', 'wb-listora' ),
						'selectCsvFile'       => __( 'Please select a CSV file.', 'wb-listora' ),
						'importing'           => __( 'Importing...', 'wb-listora' ),
						'imported'            => __( 'Imported:', 'wb-listora' ),
						'skipped'             => __( 'Skipped:', 'wb-listora' ),
						'errors'              => __( 'Errors:', 'wb-listora' ),
						'dryRun'              => __( 'dry run', 'wb-listora' ),
						'importCsv'           => __( 'Import CSV', 'wb-listora' ),
						'importFailed'        => __( 'Import failed.', 'wb-listora' ),
						'apiFetchUnavailable' => __( 'WordPress API helper is not loaded.', 'wb-listora' ),
						'copied'              => __( 'Copied!', 'wb-listora' ),
						'sending'             => __( 'Sending…', 'wb-listora' ),
						'sent'                => __( 'Sent', 'wb-listora' ),
						'failed'              => __( 'Failed:', 'wb-listora' ),
						'errored'             => __( 'Error:', 'wb-listora' ),
						'logSentAt'           => __( 'Sent At (UTC)', 'wb-listora' ),
						'logEvent'            => __( 'Event', 'wb-listora' ),
						'logRecipient'        => __( 'Recipient', 'wb-listora' ),
						'logSubject'          => __( 'Subject', 'wb-listora' ),
						'logResult'           => __( 'Result', 'wb-listora' ),
						'logEmpty'            => __( 'No activity yet. Use the Send Test panel in Settings → Notifications to record an entry.', 'wb-listora' ),
						'logFailed'           => __( 'Failed to load log:', 'wb-listora' ),
						'resetTitle'           => __( 'Reset all settings?', 'wb-listora' ),
						'resetMessage'         => __( 'Every tab will be restored to its default value. This cannot be undone.', 'wb-listora' ),
						'resetConfirm'         => __( 'Reset settings', 'wb-listora' ),
						'resetFailed'          => __( 'Reset failed:', 'wb-listora' ),
						'exportFailed'         => __( 'Export failed:', 'wb-listora' ),
						'importingSettings'    => __( 'Importing...', 'wb-listora' ),
						'importedSettings'     => __( 'Imported successfully!', 'wb-listora' ),
						'importSettingsFailed' => __( 'Import failed:', 'wb-listora' ),
						'selectJsonFile'       => __( 'Please select a JSON file first.', 'wb-listora' ),
						'invalidJson'          => __( 'Invalid JSON file.', 'wb-listora' ),
						'replaceTitle'         => __( 'Replace current settings?', 'wb-listora' ),
						'replaceMessage'       => __( 'Your current settings will be overwritten with values from the imported file.', 'wb-listora' ),
						'replaceConfirm'       => __( 'Replace settings', 'wb-listora' ),
						'migStarting'         => __( 'Starting...', 'wb-listora' ),
						'migMigrating'        => __( 'Migrating...', 'wb-listora' ),
						'migImported'         => __( 'Imported:', 'wb-listora' ),
						'migSkipped'          => __( 'Skipped:', 'wb-listora' ),
						'migErrors'           => __( 'Errors:', 'wb-listora' ),
						'migErrored'          => __( 'Migration completed with errors. Check the logs for details.', 'wb-listora' ),
						'migDryDone'          => __( 'Dry run complete. No data was imported. Run again without dry run to import.', 'wb-listora' ),
						'migDone'             => __( 'Migration completed successfully.', 'wb-listora' ),
						'migFailed'           => __( 'Migration failed.', 'wb-listora' ),
						'migComplete'         => __( 'Complete', 'wb-listora' ),
						'migStart'            => __( 'Start Migration', 'wb-listora' ),
						'migRequestFailed'    => __( 'Request failed.', 'wb-listora' ),
						'migNetwork'          => __( 'Network error. Please try again.', 'wb-listora' ),
					),
				)
			);
		}

		// Admin pages with shared scripts (onboarding dismiss, review reply,
		// import/export, migration). Replaces 4 inline <script> blocks
		// previously emitted from class-admin.php.
		wp_enqueue_script(
			'listora-admin-pages',
			WB_LISTORA_PLUGIN_URL . 'assets/js/admin/admin-pages.js',
			array( 'wp-api-fetch' ),
			WB_LISTORA_VERSION,
			true
		);

		wp_localize_script(
			'listora-admin-pages',
			'listoraAdminPages',
			array(
				'endpoints' => array(
					'exportCsv'       => rest_url( 'listora/v1/export/csv' ),
					'restNonce'       => wp_create_nonce( 'wp_rest' ),
					'migrationNonce'  => wp_create_nonce( 'listora_migration' ),
				),
				'i18n' => array(
					'replyEmpty'           => __( 'Please enter a reply.', 'wb-listora' ),
					'replySending'         => __( 'Sending...', 'wb-listora' ),
					'replySend'            => __( 'Send Reply', 'wb-listora' ),
					'replySaved'           => __( 'Reply saved.', 'wb-listora' ),
					'replyFailed'          => __( 'Failed to save reply.', 'wb-listora' ),
					'exportGenerating'     => __( 'Generating export...', 'wb-listora' ),
					'exportStarted'        => __( 'Download started.', 'wb-listora' ),
					'importNoType'         => __( 'Please select a listing type.', 'wb-listora' ),
					'importNoFile'         => __( 'Please select a CSV file.', 'wb-listora' ),
					'importImporting'      => __( 'Importing...', 'wb-listora' ),
					'importBtn'            => __( 'Import CSV', 'wb-listora' ),
					'importImported'       => __( 'Imported:', 'wb-listora' ),
					'importSkipped'        => __( 'Skipped:', 'wb-listora' ),
					'importErrors'         => __( 'Errors:', 'wb-listora' ),
					'importDryRun'         => __( 'dry run', 'wb-listora' ),
					'importFailed'         => __( 'Import failed.', 'wb-listora' ),
					'migrationStarting'    => __( 'Starting...', 'wb-listora' ),
					'migrationMigrating'   => __( 'Migrating...', 'wb-listora' ),
					'migrationImported'    => __( 'Imported:', 'wb-listora' ),
					'migrationSkipped'     => __( 'Skipped:', 'wb-listora' ),
					'migrationErrors'      => __( 'Errors:', 'wb-listora' ),
					'migrationErroredMsg'  => __( 'Migration completed with errors. Check the logs for details.', 'wb-listora' ),
					'migrationDryrunMsg'   => __( 'Dry run complete. No data was imported. Run again without dry run to import.', 'wb-listora' ),
					'migrationDoneMsg'     => __( 'Migration completed successfully.', 'wb-listora' ),
					'migrationFailed'      => __( 'Migration failed.', 'wb-listora' ),
					'migrationComplete'    => __( 'Complete', 'wb-listora' ),
					'migrationStart'       => __( 'Start Migration', 'wb-listora' ),
					'migrationRequestFailed' => __( 'Request failed.', 'wb-listora' ),
					'migrationNetworkErr'  => __( 'Network error. Please try again.', 'wb-listora' ),
				),
			)
		);
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

		// Listora taxonomy edit-tags screens (Categories, Locations,
		// Features) — needed so admin.css (with .is-hidden utility +
		// taxonomy-fields preview rules) loads on the term add/edit pages.
		$listora_taxonomies = array(
			'listora_listing_cat',
			'listora_listing_location',
			'listora_listing_feature',
			'listora_service_cat',
			'listora_listing_type',
		);
		if ( ! empty( $screen->taxonomy ) && in_array( $screen->taxonomy, $listora_taxonomies, true ) ) {
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
			'listora_page_listora-email-log',
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
		return in_array(
			$hook_suffix,
			array(
				'listora_page_listora-settings',
				// Email Log re-uses the settings stylesheet (.listora-notification-log)
				// and the settings-page JS (notification log fetcher).
				'listora_page_listora-email-log',
			),
			true
		);
	}
}
