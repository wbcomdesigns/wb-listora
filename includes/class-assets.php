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
		// v2 token layer — loads BEFORE listora-base so any selector
		// in shared.css or block CSS can reference the new --listora-{space,
		// text-size,fg,bg,border,radius,shadow}-* tokens.
		//
		// During the v2 refactor, this file lives alongside the legacy
		// :root block in shared.css. Phase 4 deletes the legacy block
		// from shared.css once all blocks have migrated to v2 token names.
		wp_register_style(
			'listora-variables',
			WB_LISTORA_PLUGIN_URL . 'assets/css/listora-variables.css',
			array(),
			WB_LISTORA_VERSION
		);

		// v2 primitive layer — canonical UI vocabulary (.listora-form-field,
		// .listora-btn, .listora-modal, .listora-tabs, .listora-tooltip,
		// .listora-table). Loads BETWEEN tokens and shared so the cascade
		// is tokens → primitives → shared → block-specific.
		//
		// Phase 1 ships 6 net-new primitives that don't collide with any
		// pre-existing classes. Phase 4 will move .listora-card, .listora-empty,
		// .listora-page, .listora-badge, .listora-stepper from shared.css
		// into this layer.
		wp_register_style(
			'listora-components',
			WB_LISTORA_PLUGIN_URL . 'assets/css/listora-components.css',
			array( 'listora-variables' ),
			WB_LISTORA_VERSION
		);

		// Shared CSS variables and base styles.
		// Depends on primitives (which depends on tokens) so the full v2
		// vocabulary is available to any selector that migrates inline.
		wp_register_style(
			'listora-base',
			WB_LISTORA_PLUGIN_URL . 'assets/css/listora-base.css',
			array( 'listora-components' ),
			WB_LISTORA_VERSION
		);

		// Per-listing-type color classes. The type color is admin-configurable
		// per type (finite set), so instead of an inline `style` attribute on
		// every type badge/card we generate one `.listora-type--{slug}` class
		// per type and attach it to the base stylesheet via wp_add_inline_style
		// (the sanctioned dynamic-CSS path — no inline style attributes).
		$type_color_css = $this->build_type_color_css();
		if ( '' !== $type_color_css ) {
			wp_add_inline_style( 'listora-base', $type_color_css );
		}

		// Theme integration bridge — when the active theme ships its own
		// design tokens, this re-binds Listora's semantic tokens
		// (--listora-primary, --listora-bg-*, --listora-fg-*, --listora-border-*,
		// --listora-button-*) to the theme's tokens. The directory then
		// inherits the customer's theme palette (light + dark, customizer
		// overrides) instead of Listora's defaults.
		//
		// Each supported theme has a bridge at assets/css/themes/{slug}.css.
		// To add a new theme: drop a file there and add the slug to the
		// $bridges map below. Geometry tokens (spacing, radius, shadow)
		// intentionally stay on Listora's values so the directory keeps
		// its own visual rhythm.
		$active_theme = get_template();
		$child_theme  = get_stylesheet();
		$bridges      = array(
			// slug => mapped-from-slug ('' means use $active_theme as the file slug)
			'buddyx'      => 'buddyx',     // BuddyX free bridge.
			'buddyx-pro'  => 'buddyx-pro', // Dedicated BuddyX Pro 5.1.0 bridge.
			'reign-theme' => 'reign',      // Reign bridge (light + dark-scheme).
		);

		/**
		 * Filters the theme-bridge slug map.
		 *
		 * Lets sites point a custom child theme at an existing bridge
		 * file, or register an entirely new bridge for a forked theme.
		 *
		 * @param array<string, string> $bridges Map of theme slug → bridge CSS file slug.
		 */
		$bridges = (array) apply_filters( 'wb_listora_theme_bridges', $bridges );

		$bridge_slug = $bridges[ $child_theme ] ?? ( $bridges[ $active_theme ] ?? '' );
		if ( '' !== $bridge_slug ) {
			$bridge_path = WB_LISTORA_PLUGIN_DIR . 'assets/css/themes/' . $bridge_slug . '.css';
			if ( file_exists( $bridge_path ) ) {
				// Bridge loads AFTER tokens so the token overrides win the
				// cascade. Enqueued eagerly because every Listora surface
				// (admin + frontend) reads these tokens.
				wp_register_style(
					'listora-theme-bridge',
					WB_LISTORA_PLUGIN_URL . 'assets/css/themes/' . $bridge_slug . '.css',
					array( 'listora-variables' ),
					WB_LISTORA_VERSION
				);
				wp_enqueue_style( 'listora-theme-bridge' );
			}
		}

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
		// Registered (not enqueued): no public-frontend block currently emits the
		// `data-listora-submit-lock` attribute, so loading it on every page was
		// 1-2 KB of dead weight on every request. Frontend blocks/templates that
		// add the attribute later should declare `listora-submit-lock` as a
		// dependency (e.g. via `wp_enqueue_block_view_script` or block.json
		// `viewScript`) and the handle will load only on those pages. Admin still
		// enqueues directly in class-admin.php where it IS used.
		wp_register_script(
			'listora-submit-lock',
			WB_LISTORA_PLUGIN_URL . 'assets/js/shared/submit-lock.js',
			array(),
			WB_LISTORA_VERSION,
			true
		);

		// Image-fallback delegated handler — replaces inline onerror= strings on
		// listing-card images (and any future surface that opts in via
		// data-listora-fallback-src). Inline `onerror=` violates the WP
		// Interactivity API hydration contract ("Component's onerror property
		// should be a function, but got string instead"). Enqueued eagerly on
		// every frontend page because listing cards appear inside multiple
		// blocks AND shortcode/template contexts. Basecamp #9927470152.
		wp_enqueue_script(
			'listora-image-fallback',
			WB_LISTORA_PLUGIN_URL . 'assets/js/listora-image-fallback.js',
			array(),
			WB_LISTORA_VERSION,
			true
		);

		// Pro upgrade CTA — loaded as a dependency of shared.css so any block that
		// renders the user dashboard / submission pages gets it automatically.
		wp_register_style(
			'listora-pro-cta',
			WB_LISTORA_PLUGIN_URL . 'assets/css/shared/pro-cta.css',
			array( 'listora-base' ),
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
				'noResults'               => __( 'No listings found', 'wb-listora' ),
				'result'                  => __( 'result', 'wb-listora' ),
				'results'                 => __( 'results', 'wb-listora' ),
				'searchError'             => __( 'Search failed. Please try again.', 'wb-listora' ),
				'geoNotSupported'         => __( 'Geolocation is not supported by your browser.', 'wb-listora' ),
				'geoDenied'               => __( 'Location access denied. Use the location search instead.', 'wb-listora' ),
				'saveFavorite'            => __( 'Save to favorites', 'wb-listora' ),
				'removeFavorite'          => __( 'Remove from favorites', 'wb-listora' ),
				'share'                   => __( 'Share', 'wb-listora' ),
				'claim'                   => __( 'Claim this listing', 'wb-listora' ),
				'featureSuccess'          => __( 'Listing featured.', 'wb-listora' ),
				'featureFailed'           => __( 'Unable to feature this listing.', 'wb-listora' ),
				'leadSent'                => __( 'Message sent successfully.', 'wb-listora' ),
				'leadFailed'              => __( 'Failed to send message. Please try again.', 'wb-listora' ),
				'leadRequired'            => __( 'Please fill in all required fields.', 'wb-listora' ),
				'leadSending'             => __( 'Sending…', 'wb-listora' ),
				'leadSend'                => __( 'Send Message', 'wb-listora' ),
				'loginRequired'           => __( 'Please log in to continue.', 'wb-listora' ),
				'openNow'                 => __( 'Open Now', 'wb-listora' ),
				'closed'                  => __( 'Closed', 'wb-listora' ),
				// Hours-builder live state chip + step-5 preview table both
				// read this (view.js initBusinessHoursToggles +
				// appendBusinessHoursPreview — previously fallback-only).
				'open24h'                 => __( 'Open 24 Hours', 'wb-listora' ),
				'featured'                => __( 'Featured', 'wb-listora' ),
				'verified'                => __( 'Verified', 'wb-listora' ),
				'nearMe'                  => __( 'Near Me', 'wb-listora' ),
				'clearAll'                => __( 'Clear all', 'wb-listora' ),
				'showResults'             => __( 'Show results', 'wb-listora' ),
				'moreFilters'             => __( 'More Filters', 'wb-listora' ),
				'prev'                    => __( 'Previous', 'wb-listora' ),
				'next'                    => __( 'Next', 'wb-listora' ),
				'requiredFieldError'      => __( 'This field is required.', 'wb-listora' ),
				// Per-field validation prompts for custom-required submission
				// fields (data-listora-required contexts). Generic "required"
				// copy loses casual submitters exactly where they bail — the
				// Media step — so name the action, not the rule.
				'requiredFieldMessages'   => apply_filters(
					'wb_listora_required_field_messages',
					array(
						'featured_image' => __( 'Add a featured photo to continue.', 'wb-listora' ),
						'gallery'        => __( 'Add at least one photo to continue.', 'wb-listora' ),
						'agree_terms'    => __( 'Please accept the Terms of Service to continue.', 'wb-listora' ),
					)
				),
				// Dashboard services panel. The CRUD handlers were wired to the
				// Services_Controller in 1.6.0 and their user-facing strings
				// went in as `t()` fallbacks only \u2014 a fallback is a literal in
				// the bundle, invisible to `wp i18n make-pot`, so the catalogue
				// reported complete while these five stayed English forever.
				'serviceSaveFailed'       => __( 'Could not save the service. Please try again.', 'wb-listora' ),
				'serviceLoadFailed'       => __( 'Could not load that service.', 'wb-listora' ),
				'serviceDeleteFailed'     => __( 'Could not delete the service.', 'wb-listora' ),
				'confirmDeleteService'    => __( 'Delete this service? This cannot be undone.', 'wb-listora' ),
				'confirmUnavailable'      => __( 'Could not open the confirmation dialog. Please reload the page and try again.', 'wb-listora' ),
				'submitting'              => __( 'Submitting\u2026', 'wb-listora' ),
				'submitClaim'             => __( 'Submit Claim', 'wb-listora' ),
				'claimSubmitted'          => __( 'Claim submitted — we\'ll email you when it\'s reviewed.', 'wb-listora' ),
				'claimFailed'             => __( 'Failed to submit claim. Please try again.', 'wb-listora' ),
				'viewMyClaims'            => __( 'View my claims', 'wb-listora' ),
				'submitReport'            => __( 'Submit Report', 'wb-listora' ),
				'reportFailed'            => __( 'Failed to submit report. Please try again.', 'wb-listora' ),
				'dashboardUrl'            => function_exists( 'wb_listora_get_dashboard_url' ) ? wb_listora_get_dashboard_url() : '',
				'linkCopied'              => __( 'Link copied!', 'wb-listora' ),
				'reportSubmitted'         => __( 'Report submitted. Thank you.', 'wb-listora' ),
				// Blocking is symmetric and hides content in both directions, so
				// the confirmation says what actually happened rather than a
				// bare "Done".
				'memberBlocked'           => __( 'Member blocked. You will no longer see their reviews or messages.', 'wb-listora' ),
				'memberBlockFailed'       => __( 'Could not block this member. Please try again.', 'wb-listora' ),
				'memberUnblocked'         => __( 'Member unblocked. Their reviews will be visible again.', 'wb-listora' ),
				'memberUnblockFailed'     => __( 'Could not unblock this member. Please try again.', 'wb-listora' ),
				/*
				 * Strings written into the DOM by view scripts.
				 *
				 * These were literals inside the JS bundles, which `wp i18n
				 * make-pot` cannot see — so they never reached a .po file and no
				 * translation pass could fix them. A German site showed "Save
				 * Draft" and "Submitting..." in English while the catalogue
				 * reported 100% complete.
				 *
				 * The ones carrying placeholders were previously built by
				 * concatenation, which is untranslatable: languages order their
				 * clauses differently, so the translator needs the whole
				 * sentence with numbered placeholders in it.
				 */
				'jsSubmitting'            => __( 'Submitting...', 'wb-listora' ),
				'jsSubmitReview'          => __( 'Submit Review', 'wb-listora' ),
				'jsReviewSubmitted'       => __( 'Review submitted!', 'wb-listora' ),
				'jsReviewPending'         => __( 'Awaiting approval', 'wb-listora' ),
				'jsReplyPlaceholder'      => __( 'Write your reply...', 'wb-listora' ),
				'jsReply'                 => __( 'Reply', 'wb-listora' ),
				'jsSending'               => __( 'Sending...', 'wb-listora' ),
				'jsCancel'                => __( 'Cancel', 'wb-listora' ),
				'jsSent'                  => __( 'Sent', 'wb-listora' ),
				'jsRenewing'              => __( 'Renewing...', 'wb-listora' ),
				'jsPublished'             => __( 'Published', 'wb-listora' ),
				'jsRedirecting'           => __( 'Redirecting...', 'wb-listora' ),
				'jsSaving'                => __( 'Saving...', 'wb-listora' ),
				'jsSaveDraft'             => __( 'Save Draft', 'wb-listora' ),
				'jsDraftSaved'            => __( 'Draft saved', 'wb-listora' ),
				'jsView'                  => __( 'View', 'wb-listora' ),
				'jsDuplicateTitle'        => __( 'We found similar listings — is yours different?', 'wb-listora' ),
				'jsDuplicateCancel'       => __( 'Cancel — change my listing', 'wb-listora' ),
				'jsSubmitAnyway'          => __( 'Submit anyway', 'wb-listora' ),
				'jsExplainDifferent'      => __( 'Briefly explain how it\'s different', 'wb-listora' ),
				'jsNoBlockedMembers'      => __( 'You have not blocked anyone. You can block a member from any review they have written.', 'wb-listora' ),
				'jsVerifyTitle'           => __( 'Almost there — verify your email', 'wb-listora' ),
				'jsVerifyNote'            => __( 'Didn\'t get the email? Check your spam folder or click below to resend.', 'wb-listora' ),
				'jsResendEmail'           => __( 'Resend email', 'wb-listora' ),
				'jsWrongEmail'            => __( 'Wrong email? Edit submission', 'wb-listora' ),
				'jsResendSent'            => __( 'A fresh verification email is on its way.', 'wb-listora' ),
				'jsResendFailed'          => __( 'Could not send the email. Please try again later.', 'wb-listora' ),
				/* translators: %s: email address the verification link was sent to. */
				'jsVerifySentTo'          => __( 'We sent a verification link to %s. Click the link in the email to publish your listing.', 'wb-listora' ),
				/* translators: 1: credits required, 2: credits the member currently holds. */
				'jsNeedCredits'           => __( 'You need %1$s credits to renew (you have %2$s).', 'wb-listora' ),
				/* translators: %d: seconds the member must wait before requesting another email. */
				'jsResendWait'            => __( 'Please wait %d seconds before requesting another email.', 'wb-listora' ),
				// LISTING report reasons. Reviews are NOT included here: they have
				// their own enum in wb_listora_get_review_report_reasons(),
				// because a review cannot be "permanently closed" or a
				// "duplicate listing" (BC 10154926676). The review modal reads
				// that helper directly, so this key is listings only.
				'reportReasons'           => \WBListora\Admin\Report_Metabox::reasons(),
				// Owner: Deactivate listing modal (T1 — store.js deactivateListing).
				'confirmDeactivate'       => __( 'Deactivate this listing? It will be hidden from the public directory until you reactivate it.', 'wb-listora' ),
				'confirmDeactivateTitle'  => __( 'Deactivate listing?', 'wb-listora' ),
				'deactivate'              => __( 'Deactivate', 'wb-listora' ),
				'deactivateSuccess'       => __( 'Listing deactivated.', 'wb-listora' ),
				'deactivateFailed'        => __( 'Unable to deactivate listing.', 'wb-listora' ),
				// Owner: Reactivate listing modal (Card 8 — store.js reactivateListing).
				'confirmReactivate'       => __( 'Reactivate this listing? It will reappear in the public directory.', 'wb-listora' ),
				'confirmReactivateTitle'  => __( 'Reactivate listing?', 'wb-listora' ),
				'reactivate'              => __( 'Reactivate', 'wb-listora' ),
				'reactivateSuccess'       => __( 'Listing reactivated.', 'wb-listora' ),
				'reactivateFailed'        => __( 'Unable to reactivate listing.', 'wb-listora' ),
				// Submission media uploader caps. PHP's upload_max_filesize is the
				// hard ceiling; this is the user-friendly cap exposed to the
				// listing-submission widget so a 50 MB photo gets rejected before
				// the user uploads it. JS-side check; server-side enforcement
				// still relies on PHP's setting.
				'maxUploadSizeMb'         => max( 1, (int) wb_listora_get_setting( 'max_upload_size', 5 ) ),
				'fileTooLarge'            => __( 'This file exceeds the %d MB upload limit. Please choose a smaller image.', 'wb-listora' ),
				// Submission gallery cap — enforced client-side in addition to
				// the template-rendered label, so users can't sneak past the
				// limit by picking more than N images from the media library.
				// BC 9901104724.
				'maxGalleryImages'        => max( 1, (int) wb_listora_get_setting( 'max_gallery_images', 20 ) ),
				'galleryLimitReached'     => __( 'You can upload a maximum of %d gallery images.', 'wb-listora' ),
				'galleryLimitWouldExceed' => __( 'You can add %1$d more image(s). You selected %2$d.', 'wb-listora' ),
				'removeGalleryImage'      => __( 'Remove gallery image', 'wb-listora' ),
				'uploadPrompt'            => __( 'Click to upload or drag & drop', 'wb-listora' ),
				'uploadHint'              => __( 'Max 5MB, JPG/PNG/WebP', 'wb-listora' ),
				// Helpful-vote outcome messages. Distinguishing these from a
				// generic "error" lets the UI show honest status (already
				// voted, own review, login required) instead of the same
				// scary `is-error` state for every non-success path.
				'alreadyVoted'            => __( 'You have already marked this review as helpful.', 'wb-listora' ),
				'ownReview'               => __( 'You can\'t mark your own review as helpful.', 'wb-listora' ),
				// Surfaced when wp.media is missing on the submission page —
				// the submission render now always enqueues it, so this only
				// fires on a script-load race or a third-party plugin that
				// dequeues media. Without a visible message the upload zone
				// looks broken (silent click).
				'mediaUnavailable'        => __( 'The media uploader could not load. Please refresh the page and try again.', 'wb-listora' ),
				// Frontend media-picker scoping (card 9996105562). Non-privileged
				// members only ever see their OWN uploads in the listing-submission
				// picker — never other members' or the admin's Media Library. The
				// authoritative enforcement is the server-side
				// `ajax_query_attachments_args` filter in class-plugin.php; these
				// two keys mirror that decision so the modal opens pre-scoped
				// (better UX) instead of fetching then hiding. Editors/admins
				// (edit_others_posts) keep the full library.
				'mediaAuthorId'           => get_current_user_id(),
				'mediaRestrictToOwn'      => (bool) apply_filters(
					'wb_listora_restrict_media_to_own_uploads',
					! current_user_can( 'edit_others_posts' )
				),
			)
		);

		// Toast utility — lightweight, no dependencies. Same API as assets/js/shared/toast.js (admin).
		wp_add_inline_script(
			'listora-i18n',
			'if(!window.listoraToast){(function(){var c;function i(){if(c)return;c=document.createElement("div");c.className="listora-toast-container";document.body.appendChild(c)}window.listoraToast=function(m,o){i();var t="info",d=4000;if(typeof o==="string")t=o;else if(o&&typeof o==="object"){t=o.type||"info";d=o.duration||4000}var e=document.createElement("div");e.className="listora-toast listora-toast--"+t;e.setAttribute("role","status");e.setAttribute("aria-live","polite");e.textContent=m;c.appendChild(e);setTimeout(function(){e.classList.add("is-visible")},10);setTimeout(function(){e.classList.remove("is-visible");setTimeout(function(){if(e.parentNode)e.parentNode.removeChild(e)},300)},d)}})()}'
		);
	}

	/**
	 * Build per-listing-type color CSS — one `.listora-type--{slug}` class per
	 * registered type, each setting the `--listora-type-color` custom property.
	 *
	 * Lets type badges/cards reference a class instead of an inline `style`
	 * attribute. Colors are admin-configurable (term meta) so this is generated
	 * dynamically; sanitize_hex_color() guards against CSS injection.
	 *
	 * @return string CSS rules, or empty string when no types/colors resolve.
	 */
	private function build_type_color_css() {
		if ( ! class_exists( '\WBListora\Core\Listing_Type_Registry' ) ) {
			return '';
		}

		$types = \WBListora\Core\Listing_Type_Registry::instance()->get_all();
		if ( empty( $types ) ) {
			return '';
		}

		$css = '';
		foreach ( $types as $type ) {
			$slug  = sanitize_html_class( $type->get_slug() );
			$color = sanitize_hex_color( $type->get_color() );
			if ( '' === $slug || ! $color ) {
				continue;
			}
			$css .= sprintf( '.listora-type--%s{--listora-type-color:%s}', $slug, $color );
		}

		return $css;
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

		// Register the v2 token + primitive layer on admin too. enqueue_frontend()
		// only fires on wp_enqueue_scripts; admin needed its own registration so
		// `listora-admin` can depend on the foundation layer. Without this every
		// CSS variable in admin.css / settings.css resolves to nothing and the
		// admin UI renders with zero chrome.
		if ( ! wp_style_is( 'listora-variables', 'registered' ) ) {
			wp_register_style(
				'listora-variables',
				WB_LISTORA_PLUGIN_URL . 'assets/css/listora-variables.css',
				array(),
				WB_LISTORA_VERSION
			);
		}
		if ( ! wp_style_is( 'listora-components', 'registered' ) ) {
			wp_register_style(
				'listora-components',
				WB_LISTORA_PLUGIN_URL . 'assets/css/listora-components.css',
				array( 'listora-variables' ),
				WB_LISTORA_VERSION
			);
		}

		wp_enqueue_style(
			'listora-admin',
			WB_LISTORA_PLUGIN_URL . 'assets/css/admin.css',
			array( 'listora-components' ),
			WB_LISTORA_VERSION
		);

		// jQuery UI Sortable for field ordering in listing type editor.
		if ( $this->is_type_editor_page( $hook_suffix ) ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}

		// Built admin entry. The src/ directory is stripped from dist builds,
		// so enqueue the compiled file under build/ to avoid a 404. Use the
		// generated asset manifest for dependencies + version when present.
		$admin_asset_file = WB_LISTORA_PLUGIN_DIR . 'build/admin/admin.asset.php';
		$admin_asset      = file_exists( $admin_asset_file ) ? require $admin_asset_file : array(
			'dependencies' => array(),
			'version'      => WB_LISTORA_VERSION,
		);

		wp_enqueue_script(
			'listora-admin-js',
			WB_LISTORA_PLUGIN_URL . 'build/admin/admin.js',
			array_merge( array( 'jquery' ), $admin_asset['dependencies'] ),
			$admin_asset['version'],
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
					'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
					'restNonce'      => wp_create_nonce( 'wp_rest' ),
					'migrationNonce' => wp_create_nonce( 'listora_migration' ),
					'exportCsvUrl'   => rest_url( 'listora/v1/export/csv' ),
					// Core (type-independent) mappable fields for the CSV import
					// column-mapping dropdowns. Mirrors
					// CSV_Importer::get_mappable_fields() minus the per-type meta
					// fields (those depend on the chosen listing type and are not
					// needed for the title/category/tags/location round-trip).
					'csvFields'      => \WBListora\ImportExport\CSV_Importer::get_mappable_fields( '' ),
					'i18n'           => array(
						'generatingExport'     => __( 'Generating export...', 'wb-listora' ),
						'downloadStarted'      => __( 'Download started.', 'wb-listora' ),
						'selectListingType'    => __( 'Please select a listing type.', 'wb-listora' ),
						'selectCsvFile'        => __( 'Please select a CSV file.', 'wb-listora' ),
						'importing'            => __( 'Importing...', 'wb-listora' ),
						'imported'             => __( 'Imported:', 'wb-listora' ),
						'skipped'              => __( 'Skipped:', 'wb-listora' ),
						'errors'               => __( 'Errors:', 'wb-listora' ),
						'dryRun'               => __( 'dry run', 'wb-listora' ),
						'importCsv'            => __( 'Import CSV', 'wb-listora' ),
						'importFailed'         => __( 'Import failed.', 'wb-listora' ),
						'mapColumns'           => __( 'Map columns', 'wb-listora' ),
						'mapColumnsHint'       => __( 'Match each CSV column to a listing field. Unmatched columns are skipped.', 'wb-listora' ),
						'apiFetchUnavailable'  => __( 'WordPress API helper is not loaded.', 'wb-listora' ),
						'copied'               => __( 'Copied!', 'wb-listora' ),
						'sending'              => __( 'Sending…', 'wb-listora' ),
						'sent'                 => __( 'Sent', 'wb-listora' ),
						'failed'               => __( 'Failed:', 'wb-listora' ),
						'errored'              => __( 'Error:', 'wb-listora' ),
						'logSentAt'            => __( 'Sent At (UTC)', 'wb-listora' ),
						'logEvent'             => __( 'Event', 'wb-listora' ),
						'logRecipient'         => __( 'Recipient', 'wb-listora' ),
						'logSubject'           => __( 'Subject', 'wb-listora' ),
						'logResult'            => __( 'Result', 'wb-listora' ),
						'logEmpty'             => __( 'No activity yet. Use the Send Test panel in Settings → Notifications to record an entry.', 'wb-listora' ),
						'logFailed'            => __( 'Failed to load log:', 'wb-listora' ),
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
						'migStarting'          => __( 'Starting...', 'wb-listora' ),
						'migMigrating'         => __( 'Migrating...', 'wb-listora' ),
						'migImported'          => __( 'Imported:', 'wb-listora' ),
						'migSkipped'           => __( 'Skipped:', 'wb-listora' ),
						'migErrors'            => __( 'Errors:', 'wb-listora' ),
						'migErrored'           => __( 'Migration completed with errors. Check the logs for details.', 'wb-listora' ),
						'migDryDone'           => __( 'Dry run complete. No data was imported. Run again without dry run to import.', 'wb-listora' ),
						'migDone'              => __( 'Migration completed successfully.', 'wb-listora' ),
						'migFailed'            => __( 'Migration failed.', 'wb-listora' ),
						'migComplete'          => __( 'Complete', 'wb-listora' ),
						'migStart'             => __( 'Start Migration', 'wb-listora' ),
						'migRequestFailed'     => __( 'Request failed.', 'wb-listora' ),
						'migNetwork'           => __( 'Network error. Please try again.', 'wb-listora' ),
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
			array( 'wp-api-fetch', 'listora-confirm' ),
			WB_LISTORA_VERSION,
			true
		);

		wp_localize_script(
			'listora-admin-pages',
			'listoraAdminPages',
			array(
				'endpoints' => array(
					'exportCsv'          => rest_url( 'listora/v1/export/csv' ),
					'restNonce'          => wp_create_nonce( 'wp_rest' ),
					'migrationNonce'     => wp_create_nonce( 'listora_migration' ),
					'demoImportNonce'    => wp_create_nonce( 'listora_demo_import' ),
					'demoImportUrl'      => admin_url( 'admin-ajax.php' ),
					'demoDeleteNonce'    => wp_create_nonce( 'listora_demo_delete' ),
					'demoDeleteUrl'      => admin_url( 'admin-ajax.php' ),
					'viewListingsUrl'    => admin_url( 'edit.php?post_type=listora_listing' ),
					'actionSchedulerUrl' => admin_url( 'tools.php?page=action-scheduler' ),
				),
				'i18n'      => array(
					'replyEmpty'             => __( 'Please enter a reply.', 'wb-listora' ),
					'replySending'           => __( 'Sending...', 'wb-listora' ),
					'replySend'              => __( 'Send Reply', 'wb-listora' ),
					'replySaved'             => __( 'Reply saved.', 'wb-listora' ),
					'replyFailed'            => __( 'Failed to save reply.', 'wb-listora' ),
					'exportGenerating'       => __( 'Generating export...', 'wb-listora' ),
					'exportStarted'          => __( 'Download started.', 'wb-listora' ),
					'importNoType'           => __( 'Please select a listing type.', 'wb-listora' ),
					'importNoFile'           => __( 'Please select a CSV file.', 'wb-listora' ),
					'importImporting'        => __( 'Importing...', 'wb-listora' ),
					'importBtn'              => __( 'Import CSV', 'wb-listora' ),
					'importImported'         => __( 'Imported:', 'wb-listora' ),
					'importSkipped'          => __( 'Skipped:', 'wb-listora' ),
					'importErrors'           => __( 'Errors:', 'wb-listora' ),
					'importDryRun'           => __( 'dry run', 'wb-listora' ),
					'importFailed'           => __( 'Import failed.', 'wb-listora' ),
					'importViewListings'     => __( 'View Listings', 'wb-listora' ),
					'importViewScheduler'    => __( 'View Action Scheduler', 'wb-listora' ),
					'importRetrying'         => __( 'Import failed — retrying…', 'wb-listora' ),
					'importStillRunning'     => __( 'Import is still running in the background.', 'wb-listora' ),
					'importProgressLost'     => __( 'Lost track of the import. Refresh to check its status.', 'wb-listora' ),
					'demoImportRunning'      => __( 'Demo import queued. Importing in background…', 'wb-listora' ),
					'demoImportBtn'          => __( 'Re-run Demo Import', 'wb-listora' ),
					'demoImportBtnRunning'   => __( 'Queueing…', 'wb-listora' ),
					'demoImportConfirm'      => __( 'Demo listings already exist. Re-running will add duplicate demo content. Continue?', 'wb-listora' ),
					'demoImportFailed'       => __( 'Failed to queue demo import.', 'wb-listora' ),
					'demoDeleteConfirm'      => __( 'Permanently delete ALL demo listings and their demo images? This cannot be undone. Your own real listings are not affected.', 'wb-listora' ),
					'demoDeleteConfirmTitle' => __( 'Delete demo data?', 'wb-listora' ),
					'demoDeleteBtn'          => __( 'Delete Demo Data', 'wb-listora' ),
					'demoDeleteBtnRunning'   => __( 'Deleting…', 'wb-listora' ),
					'demoDeleteNone'         => __( 'No demo data found to delete.', 'wb-listora' ),
					'demoDeleteDone'         => __( 'Demo data deleted.', 'wb-listora' ),
					'demoDeleteFailed'       => __( 'Failed to delete demo data.', 'wb-listora' ),
					'migrationStarting'      => __( 'Starting...', 'wb-listora' ),
					'migrationMigrating'     => __( 'Migrating...', 'wb-listora' ),
					'migrationImported'      => __( 'Imported:', 'wb-listora' ),
					'migrationSkipped'       => __( 'Skipped:', 'wb-listora' ),
					'migrationErrors'        => __( 'Errors:', 'wb-listora' ),
					'migrationErroredMsg'    => __( 'Migration completed with errors. Check the logs for details.', 'wb-listora' ),
					'migrationDryrunMsg'     => __( 'Dry run complete. No data was imported. Run again without dry run to import.', 'wb-listora' ),
					'migrationDoneMsg'       => __( 'Migration completed successfully.', 'wb-listora' ),
					'migrationFailed'        => __( 'Migration failed.', 'wb-listora' ),
					'migrationComplete'      => __( 'Complete', 'wb-listora' ),
					'migrationStart'         => __( 'Start Migration', 'wb-listora' ),
					'migrationRequestFailed' => __( 'Request failed.', 'wb-listora' ),
					'migrationNetworkErr'    => __( 'Network error. Please try again.', 'wb-listora' ),
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
		// Single source of truth — see class-template-helpers.php for the
		// detection rules. Same helper is consumed by class-admin.php's
		// asset/header injector AND by Pro's class-assets.php so the
		// three enqueue checks stay aligned by construction. If they
		// drift, Pro's CSS (which depends on Free's `listora-admin`
		// handle) silently drops the dependency and pages render
		// unstyled (Basecamp incident 2026-05-13).
		return wb_listora_is_admin_screen();
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
