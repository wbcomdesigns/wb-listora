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
		// Menu hubs: related screens share one menu item and a tab row.
		Admin_Hubs::init();
		// Hide third-party admin notices on Listora admin pages to keep the
		// interface focused on Listora content. Fires very early so that every
		// plugin's notice hook gets removed before it runs.
		add_action( 'in_admin_header', array( $this, 'suppress_third_party_notices' ), 1 );

		// NOTE: the activation->setup-wizard redirect is owned solely by
		// Activation_Redirect (instantiated below). The former duplicate
		// maybe_redirect_to_wizard() here was a second admin_init handler on the
		// same `wb_listora_activation_redirect` transient — removed so there is
		// exactly one Free redirect path (card 10020037441 / both-active flow).
		add_action( 'admin_init', array( Settings_Page::class, 'register' ) );

		// Listing-fields meta box — surfaces every type-defined field group
		// in wp-admin so admins/editors can edit the 50+ content fields
		// (address, hours, cuisine, capacity, etc.) without going through
		// the frontend submission wizard.
		Listing_Fields_Metabox::register();

		// Listing-type selector. The type taxonomy is show_ui => false, so
		// core renders no metabox and a listing filed under the wrong type
		// had no correction path in the UI. Registers at save priority 20,
		// after the field save at 15, so on-screen fields persist against
		// the type they were rendered for.
		Listing_Type_Metabox::register();

		// Services meta box on the Edit Listing screen — owners manage
		// services from the frontend dashboard, this surfaces the same
		// CRUD for site admins / editors. Basecamp 9843428450.
		Services_Metabox::register();

		// Featured meta box — manual feature/unfeature toggle in wp-admin.
		// Wraps Free's canonical Featured service; Pro's credit-gated rotation
		// hooks the same `wb_listora_before_feature_listing` filter, so the
		// admin checkbox + Pro credit holds + cron expiration all stay
		// coherent without duplicate writers.
		Featured_Metabox::register();

		// Reports meta box — surfaces visitor flags from the report_listings
		// feature so admins can review + clear them on the edit screen.
		Report_Metabox::register();

		// Features tab — admin-post handler (separate from WP Settings API
		// because the wb_listora_features option is independent of wb_listora_settings).
		add_action( 'admin_post_wb_listora_save_features', array( Settings_Page::class, 'save_features' ) );
		add_action( 'admin_post_wb_listora_create_page', array( Settings_Page::class, 'create_page' ) );
		add_action( 'admin_notices', array( Settings_Page::class, 'created_page_notice' ) );
		Menu_Prompt::init();

		// Integrations page — one-click free install / activate companion plugins.
		// Action is plugin-prefixed (wb_listora_install_companion) to avoid
		// colliding with sibling plugins that ship an identical installer.
		add_action( 'admin_post_wb_listora_install_companion', array( $this, 'handle_install_companion' ) );

		// Plug-and-play: auto-redirect to the wizard the first admin pageload
		// after activation. Decoupled from the legacy redirect above so we can
		// remove the legacy code once all installs ship the new transient.
		( new Activation_Redirect() )->init();

		// Setup wizard processes its POST on `admin_init` priority 1 so the
		// final-step redirect runs before the admin header is emitted. Card
		// 9867159785 — round 2 fix for the "Go to Dashboard" blank page.
		Setup_Wizard::init();
		add_action( 'wp_dashboard_setup', array( $this, 'register_dashboard_widget' ) );
		add_action( 'admin_notices', array( $this, 'onboarding_notice' ) );
		add_action( 'admin_init', array( self::class, 'heal_setup_complete_flags' ) );
		add_action( 'admin_init', array( $this, 'handle_setup_notice_dismiss' ) );
		add_action( 'admin_init', array( $this, 'redirect_legacy_health_page' ) );
		add_action( 'admin_init', array( $this, 'handle_search_reindex' ) );
		add_action( 'admin_init', array( $this, 'handle_claim_actions' ) );
		add_action( 'admin_init', array( $this, 'handle_review_actions' ) );
		add_action( 'admin_post_wb_listora_delete_type', array( Type_Editor::class, 'handle_delete' ) );
		add_action( 'admin_post_wb_listora_email_log', array( Settings_Page::class, 'handle_email_log_action' ) );
		add_action( 'wp_ajax_listora_dismiss_onboarding', array( $this, 'ajax_dismiss_onboarding' ) );
		add_action( 'wp_ajax_listora_run_migration', array( $this, 'ajax_run_migration' ) );
		add_action( 'wp_ajax_listora_run_demo_import', array( Settings_Page::class, 'ajax_run_demo_import' ) );
		add_action( 'wp_ajax_listora_delete_demo', array( Settings_Page::class, 'ajax_delete_demo' ) );

		// Keep Listora menu open on taxonomy and CPT screens.
		add_filter( 'parent_file', array( $this, 'fix_parent_menu' ) );
		add_filter( 'submenu_file', array( $this, 'fix_submenu_highlight' ), 10, 2 );

		// Admin columns and filters for listings CPT.
		new Listing_Columns();

		// Extra list-table bulk actions (approve/reject/feature/unfeature/assign-category).
		new Listing_Bulk_Actions();

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
		// Delegate to the canonical helper so admin-header injection,
		// asset enqueue (class-assets.php), and Pro's enqueue
		// (wb-listora-pro/includes/class-assets.php) all share one
		// detection rule. Drift between these caused unstyled admin
		// pages (Basecamp incident 2026-05-13) — keep them aligned.
		return wb_listora_is_admin_screen();
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

		wp_enqueue_style(
			'listora-confirm',
			$plugin_url . 'assets/css/shared/confirm.css',
			array(),
			$version
		);

		wp_enqueue_style(
			'listora-pro-cta',
			$plugin_url . 'assets/css/shared/pro-cta.css',
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

		wp_enqueue_script(
			'listora-confirm',
			$plugin_url . 'assets/js/shared/confirm.js',
			array(),
			$version,
			true
		);

		wp_enqueue_script(
			'listora-submit-lock',
			$plugin_url . 'assets/js/shared/submit-lock.js',
			array(),
			$version,
			true
		);

		wp_enqueue_script(
			'listora-admin-delegation',
			$plugin_url . 'assets/js/admin/admin-delegation.js',
			array(),
			$version,
			true
		);

		// List-page assets carry the shared admin chrome for any Listora
		// list-table screen: .listora-filter-tabs filter pills,
		// .listora-filter-bar search row, .listora-empty-state, etc.
		// Pro pages that render the same chrome (Needs Moderation,
		// Transactions, Audit Log, Moderators…) load it via the
		// allowlist below so admin UX stays uniform across Free+Pro.
		// Filter `wb_listora_list_page_slugs` lets third-party plugins
		// extend the set without forking this file.
		$list_pages = (array) apply_filters(
			'wb_listora_list_page_slugs',
			array(
				'listora-reviews',
				'listora-claims',
				'listora-email-log',
				'listora-webhooks',
				'listora-listing-types',
				'listora-needs',
				'listora-transactions',
				'listora-audit-log',
				'listora-moderators',
				'listora-coupons',
				'listora-badges',
				'listora-analytics',
				'listora-reverse-listings',
				'listora-saved-searches',
			)
		);
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

		// Settings page: enqueue wp-api-fetch for REST-based import/export and migration CSS.
		if ( 'listora-settings' === $page ) {
			wp_enqueue_script( 'wp-api-fetch' );
			wp_enqueue_style(
				'listora-migration',
				$plugin_url . 'assets/css/admin/migration.css',
				array( 'listora-admin' ),
				$version
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
		// Main menu. Gated by the virtual `view_listora_dashboard` cap which
		// is granted at runtime to any user with either `manage_listora_settings`
		// or `edit_listora_listings` (see Capabilities::grant_view_dashboard_to_managers).
		// This makes the wizard's post-completion redirect viable for users who
		// have the wizard cap but not the listings-edit cap (card 9867159785).
		add_menu_page(
			__( 'Listora', 'wb-listora' ),
			__( 'Listora', 'wb-listora' ),
			\WBListora\Core\Capabilities::CAP_VIEW_DASHBOARD,
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
			\WBListora\Core\Capabilities::CAP_VIEW_DASHBOARD,
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

		// Service Categories — taxonomy is not attached to a post type, so WP
		// will not auto-add a menu. Without this the dashboard service form
		// has a permanently empty dropdown (BC 10217677159).
		add_submenu_page(
			'listora',
			__( 'Service Categories', 'wb-listora' ),
			__( 'Service Categories', 'wb-listora' ),
			'manage_listora_types',
			'edit-tags.php?taxonomy=listora_service_cat'
		);

		// Reviews.
		add_submenu_page(
			'listora',
			__( 'Reviews', 'wb-listora' ),
			__( 'Reviews', 'wb-listora' ),
			'moderate_listora_reviews',
			'listora-reviews',
			array( $this, 'render_reviews_page' )
		);

		// Claims.
		add_submenu_page(
			'listora',
			__( 'Claims', 'wb-listora' ),
			__( 'Claims', 'wb-listora' ),
			'manage_listora_claims',
			'listora-claims',
			array( $this, 'render_claims_page' )
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

		// Email Log — outbound notification activity (Rule 1: row-bearing
		// data lives in submenus, not Settings tabs).
		add_submenu_page(
			'listora',
			__( 'Email Log', 'wb-listora' ),
			__( 'Email Log', 'wb-listora' ),
			'manage_listora_settings',
			'listora-email-log',
			array( '\\WBListora\\Admin\\Settings_Page', 'render_email_log_page' )
		);

		// Integrations — companion plugin catalog (BuddyNext, Jetonomy, WB
		// Gamification): detect / one-click install. Each works standalone; this
		// screen only reflects status + triggers installs.
		// It is a Settings tab now (card 10337185716); the old screen stays
		// registered without a menu entry so bookmarks and install
		// redirect-backs land on the tab.
		$integrations_hook = add_submenu_page(
			'',
			__( 'Integrations', 'wb-listora' ),
			__( 'Integrations', 'wb-listora' ),
			'manage_listora_settings',
			'listora-integrations',
			'__return_null'
		);
		if ( $integrations_hook ) {
			add_action( 'load-' . $integrations_hook, array( __CLASS__, 'redirect_integrations_page' ) );
		}

		// Health Check (Tools).
		// Health Check folded into Settings → Advanced (per Rule 1: diagnostics
		// are part of the maintenance/debug surface, not a separate menu item).
		// Hidden submenu stub redirects the legacy URL to the new tab anchor —
		// WP's page-not-registered check fires before admin_init so a plain
		// admin_init redirect would die with "Sorry, you are not allowed".
		add_submenu_page(
			'',
			__( 'Health Check', 'wb-listora' ),
			__( 'Health Check', 'wb-listora' ),
			'manage_listora_settings',
			'listora-health',
			static function (): void {
				wp_safe_redirect(
					admin_url( 'admin.php?page=listora-settings&tab=advanced#advanced' )
				);
				exit;
			}
		);

		// Setup Wizard. Hidden from the sidebar once setup is complete (or
		// when the user has explicitly dismissed the wizard) — but the page
		// itself stays registered, so admins can revisit via the direct URL
		// `admin.php?page=listora-setup` to re-run any step.
		//
		// Parent must be '' (empty string), NOT null, when hiding. WP's
		// add_submenu_page() internally calls plugin_basename( $parent_slug )
		// → wp_normalize_path() → wp_is_stream() → strpos( $path, '://' ).
		// Passing null cascades into PHP 8 "Passing null to strpos()"
		// deprecation noise on every admin request (~4 lines per submenu).
		//
		// When parent is '' the page has no entry in $submenu, so WP's
		// get_admin_page_title() can't resolve a title — the global $title
		// stays null, and admin-header.php:41 triggers
		// "Deprecated: strip_tags(): Passing null". We compensate with the
		// load-{hook} listener below, which fires BEFORE admin-header.php
		// and seeds $title with the wizard's page-title. Basecamp #9927464901.
		$wizard_visible_in_sidebar = ! self::is_setup_complete();
		$wizard_hook               = add_submenu_page(
			$wizard_visible_in_sidebar ? 'listora' : '',
			__( 'Setup Wizard', 'wb-listora' ),
			__( 'Setup Wizard', 'wb-listora' ),
			'manage_listora_settings',
			'listora-setup',
			array( $this, 'render_setup_wizard' )
		);
		if ( $wizard_hook ) {
			add_action(
				'load-' . $wizard_hook,
				static function () {
					$GLOBALS['title'] = __( 'Setup Wizard', 'wb-listora' );
				}
			);
		}
	}

	/**
	 * Returns true once the site owner has finished the setup wizard, OR
	 * the site looks like a seeded/cloned install that no longer needs it.
	 *
	 * Two sources of truth, in order:
	 *   1. Top-level option `wb_listora_setup_complete` — the new contract.
	 *      Set by `Setup_Wizard::finalize_setup()`.
	 *   2. Legacy `wb_listora_settings.setup_complete` — for installs that
	 *      finished the wizard before the new option was introduced.
	 *
	 * @return bool
	 */
	public static function is_setup_complete() {
		// Delegate to the canonical global helper so the logic lives in exactly
		// one place (card 10020037441). Guarded for early-load safety.
		if ( function_exists( 'wb_listora_is_setup_complete' ) ) {
			return wb_listora_is_setup_complete();
		}

		// Fallback (helper not yet loaded): same canonical check inline.
		$option = get_option( 'wb_listora_setup_complete', null );
		if ( '1' === (string) $option || true === $option ) {
			return true;
		}

		return ! empty( wb_listora_get_setting( 'setup_complete' ) );
	}

	/**
	 * Record that setup is finished, writing BOTH flags.
	 *
	 * `is_setup_complete()` reads two sources, so anything that marks setup
	 * done has to write both or the contract goes dual-source: the checklist
	 * says configured while menu visibility, the activation redirect and the
	 * health check — which read only the top-level option — still say it is
	 * not (BC 10186092577).
	 *
	 * The seeded-site auto-flip used to set only the nested legacy key, which
	 * is how a site ended up with `wb_listora_settings.setup_complete = true`
	 * and `wb_listora_setup_complete = NULL` at the same time.
	 *
	 * Idempotent, so it is safe to call on every admin load.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public static function mark_setup_complete(): void {
		$settings = get_option( 'wb_listora_settings', array() );
		$settings = is_array( $settings ) ? $settings : array();

		if ( empty( $settings['setup_complete'] ) ) {
			$settings['setup_complete'] = true;
			update_option( 'wb_listora_settings', $settings );
		}

		$option = get_option( 'wb_listora_setup_complete', null );
		if ( '1' !== (string) $option && true !== $option ) {
			update_option( 'wb_listora_setup_complete', '1' );
		}
	}

	/**
	 * Heal installs that already flipped only the legacy nested flag.
	 *
	 * A site that auto-flipped before this fix carries the nested key alone and
	 * will never reconcile on its own — the auto-flip is skipped once
	 * `is_setup_complete()` returns true, which the nested key alone satisfies.
	 * So the repair cannot live inside that branch; it runs once per install
	 * here and then never touches the database again.
	 *
	 * @since 1.5.0
	 *
	 * @return void
	 */
	public static function heal_setup_complete_flags(): void {
		$nested = wb_listora_get_setting( 'setup_complete' );
		$option = get_option( 'wb_listora_setup_complete', null );

		if ( empty( $nested ) ) {
			return;
		}

		if ( '1' === (string) $option || true === $option ) {
			return;
		}

		update_option( 'wb_listora_setup_complete', '1' );
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
			'listora_service_cat',
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
			'listora_service_cat'      => 'edit-tags.php?taxonomy=listora_service_cat',
		);

		if ( isset( $taxonomy_map[ $screen->taxonomy ] ) ) {
			return $taxonomy_map[ $screen->taxonomy ];
		}

		return $submenu_file;
	}

	/**
	 * Show onboarding notice if setup not complete.
	 */
	public function onboarding_notice() {
		// Single canonical check so finishing the wizard (which writes the
		// top-level flag) reliably dismisses this notice (cards 10020076541 /
		// 10020037441).
		if ( self::is_setup_complete() ) {
			return;
		}

		// Auto-detect an already-configured site: at least one published
		// listing AND canonical submission/dashboard pages linked in
		// settings. Flip setup_complete on and skip the banner. This stops
		// the "Welcome to WB Listora" notice from nagging seeded installs
		// or staging clones where the wizard was never explicitly run.
		if ( self::looks_like_seeded_site() ) {
			self::mark_setup_complete();
			return;
		}

		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		// Respect a persistent per-user dismissal. Reuses the SAME meta the
		// activation redirect honours, so "Dismiss" means "stop guiding me to
		// setup" everywhere, once and for all — not WordPress's default
		// `is-dismissible` X, which only hides the notice client-side and lets
		// it reappear on the next page load (card 10023581495).
		$user_id = get_current_user_id();
		if ( $user_id && get_user_meta( $user_id, Activation_Redirect::USER_DISMISS, true ) ) {
			return;
		}

		// Surface the "complete setup" call-to-action ONLY on the natural
		// getting-started surfaces — the WP Dashboard, the Plugins screen, and
		// WB Listora's own top-level dashboard. Do NOT stamp it on every plugin
		// sub-page (Listings / Categories / Locations / Features list screens,
		// the setup wizard itself, Settings, etc.), where a repeated banner
		// reads as nagging rather than guidance. Card 10023581495 — QA saw the
		// notice on all four CPT/taxonomy screens; the previous substring guard
		// only kept it off the wizard page, not off the content screens.
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'plugins', 'toplevel_page_listora' ), true ) ) {
			return;
		}

		$wizard_url  = admin_url( 'admin.php?page=listora-setup' );
		$dismiss_url = wp_nonce_url(
			add_query_arg( 'wb_listora_dismiss_setup', '1' ),
			'wb_listora_dismiss_setup'
		);
		printf(
			'<div class="notice listora-notice notice-info"><p>%s <a href="%s" class="button button-primary listora-notice-cta">%s</a> <a href="%s" class="listora-notice-dismiss">%s</a></p></div>',
			esc_html__( 'Welcome to WB Listora! Complete the setup wizard to get started.', 'wb-listora' ),
			esc_url( $wizard_url ),
			esc_html__( 'Start Setup', 'wb-listora' ),
			esc_url( $dismiss_url ),
			esc_html__( 'Dismiss', 'wb-listora' )
		);
	}

	/**
	 * Redirect the legacy `?page=listora-health` URL to the Health Check card on
	 * the Settings Advanced tab.
	 *
	 * The Health UI moved from a standalone submenu into Settings. The hidden
	 * submenu stub's own callback tried to redirect, but it fires only once the
	 * admin page is being rendered — after headers are sent — so wp_safe_redirect
	 * failed and the URL rendered a blank admin shell (card 10132858198). Doing
	 * it on admin_init runs before any output, so the redirect actually fires;
	 * gated on the same capability the page requires.
	 *
	 * @return void
	 */
	public function redirect_legacy_health_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page routing.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'listora-health' !== $page ) {
			return;
		}
		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=listora-settings&tab=advanced#advanced' ) );
		exit;
	}

	/**
	 * Run the "Rebuild Search Index" button in Settings → Maintenance.
	 *
	 * The button has shipped since the Maintenance section existed, pointing at
	 * `?page=listora-settings&action=reindex` with a `listora_reindex` nonce —
	 * and nothing ever consumed either (BC 10203331648). Clicking it silently
	 * reloaded the settings page, so an owner told to "run this after a bulk
	 * edit" got a button that looked like it worked and did nothing.
	 *
	 * On `admin_init` rather than in the page's render callback, for the same
	 * reason as redirect_legacy_health_page() above: the render callback fires
	 * after admin chrome has started printing, so wp_safe_redirect() would emit
	 * a "headers already sent" warning and the notice would never appear.
	 *
	 * The work itself is already implemented and batched — this only schedules
	 * it, so a 100k-listing site does not rebuild inline on a page load.
	 *
	 * @return void
	 */
	public function handle_search_reindex(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';

		if ( 'reindex' !== $action ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified below.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'listora-settings' !== $page ) {
			return;
		}

		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			return;
		}

		check_admin_referer( 'listora_reindex' );

		\WBListora\Search\Search_Indexer::schedule_full_reindex();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'listora-settings',
					'listora_reindexed' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Persist a per-user dismissal of the "complete setup" onboarding notice.
	 *
	 * JS-free, mirroring the pages-review dismissal pattern: a nonced query arg
	 * sets the canonical `_wb_listora_wizard_dismissed` user meta and redirects
	 * to strip the arg so a refresh doesn't re-trigger it. Once dismissed, both
	 * this notice AND the activation redirect stay quiet (card 10023581495).
	 */
	public function handle_setup_notice_dismiss(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified immediately below before any write.
		if ( ! is_admin() || ! isset( $_GET['wb_listora_dismiss_setup'] ) ) {
			return;
		}

		check_admin_referer( 'wb_listora_dismiss_setup' );

		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_user_meta( $user_id, Activation_Redirect::USER_DISMISS, 1 );
		}

		wp_safe_redirect( remove_query_arg( array( 'wb_listora_dismiss_setup', '_wpnonce' ) ) );
		exit;
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

		echo '<div class="listora-glance-grid">';
		printf(
			'<div class="listora-glance-tile"><strong>%s</strong><span>%s</span></div>',
			esc_html( number_format_i18n( $published ) ),
			esc_html__( 'Listings', 'wb-listora' )
		);
		printf(
			'<div class="listora-glance-tile"><strong>%s</strong><span>%s</span></div>',
			esc_html( number_format_i18n( $review_total ) ),
			esc_html__( 'Reviews', 'wb-listora' )
		);
		printf(
			'<div class="listora-glance-tile"><strong>%s</strong><span>%s</span></div>',
			esc_html( number_format_i18n( $claims_pending ) ),
			esc_html__( 'Claims Pending', 'wb-listora' )
		);
		printf(
			'<div class="listora-glance-tile"><strong class="%s">%s</strong><span>%s</span></div>',
			$pending_total > 0 ? 'is-warn' : '',
			esc_html( number_format_i18n( $pending_total ) ),
			esc_html__( 'Pending Total', 'wb-listora' )
		);
		echo '</div>';

		echo '<p class="listora-glance-footer">';
		printf(
			'<a href="%s" class="listora-glance-link">%s &rarr;</a>',
			esc_url( admin_url( 'admin.php?page=listora' ) ),
			esc_html__( 'View Full Dashboard', 'wb-listora' )
		);
		echo '</p>';
	}

	/**
	 * Heuristic: does this install have enough configured content that the
	 * setup wizard is effectively redundant?
	 *
	 * Used to auto-flip setup_complete on seeded / staging / cloned sites
	 * that skip the wizard. Keep the check cheap — this fires on every
	 * admin page load.
	 */
	private static function looks_like_seeded_site(): bool {
		// One implementation, in Free's public helper — Pro's setup banner
		// needs the same judgement and now asks the same question
		// (BC 10208509984).
		return wb_listora_directory_is_operational();
	}

	/**
	 * AJAX handler to dismiss the onboarding checklist.
	 */
	public function ajax_dismiss_onboarding() {
		check_ajax_referer( 'listora_dismiss_onboarding', '_nonce' );

		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wb-listora' ) ), 403 );
		}

		update_option( 'wb_listora_onboarding_dismissed', true );
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

		/*
		 * "Map settings configured" must mean the map DRAWS.
		 *
		 * This tested only the default latitude, so a site with coordinates but
		 * no tile source got a green tick next to a map that renders nothing —
		 * the checklist asserting the opposite of what the owner sees on the
		 * Directory (BC 10213013326). A checklist that certifies a broken
		 * surface is worse than one that omits it.
		 */
		$map_default_lat_raw = wb_listora_get_setting( 'map_default_lat' );
		$map_has_center      = ! empty( $map_default_lat_raw ) && 0 !== (float) $map_default_lat_raw;
		$map_tiles           = function_exists( 'wb_listora_get_map_tiles' ) ? wb_listora_get_map_tiles() : array( 'url' => '' );
		$map_has_tiles       = '' !== trim( (string) ( $map_tiles['url'] ?? '' ) );
		$map_lat             = $map_has_center && $map_has_tiles;
		/*
		 * Notification emails are governed by the `notifications` array, one
		 * key per event, and Notifications::should_send() treats an unset key
		 * as ENABLED — so a fresh site is already sending them.
		 *
		 * This used to read `email_new_submission` / `email_new_review`. No
		 * code writes those two keys and nothing else reads them: they gated
		 * the checklist on settings that do not exist, so the item could never
		 * complete and the widget sat at 6/7 forever, telling owners their
		 * setup had failed when notifications were working the whole time
		 * (BC 10186092511).
		 *
		 * "Done" now means the owner has saved the Notifications tab at least
		 * once — a question that can actually be answered yes, and the one the
		 * item's own deep link leads to.
		 */
		$settings_all = get_option( 'wb_listora_settings', array() );
		$has_notif    = is_array( $settings_all ) && isset( $settings_all['notifications'] );

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

		$items = array(
			array(
				'label' => __( 'Plugin activated', 'wb-listora' ),
				'done'  => true,
				'icon'  => 'check-circle',
			),
			array(
				'label' => __( 'Setup wizard completed', 'wb-listora' ),
				'done'  => self::is_setup_complete(),
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
				'label' => $map_has_center && ! $map_has_tiles
					? __( 'Map tile source selected', 'wb-listora' )
					: __( 'Map settings configured', 'wb-listora' ),
				'done'  => $map_lat,
				'icon'  => 'map',
				'url'   => admin_url( 'admin.php?page=listora-settings&tab=maps' ),
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

		/**
		 * Filter the setup checklist on the Listora dashboard.
		 *
		 * The checklist is where a new owner actually looks, so it is where a
		 * feature makes itself discoverable. Monetization is the case that
		 * forced this open: packs live in a Settings tab, plans are a CPT,
		 * coupons are their own screen, and an owner had to already know the
		 * answer to find any of them — and the ORDER is load-bearing
		 * (packs -> gateway -> plan -> verify), which no menu can express
		 * (BC 10208510255).
		 *
		 * Each item: label, done (bool), icon, and an optional url that must
		 * deep-link to the exact screen that completes it. An item whose
		 * `done` can never become true is worse than no item — it tells an
		 * owner their setup failed forever (BC 10186092511).
		 *
		 * @since 1.6.0
		 *
		 * @param array[] $items Checklist items.
		 */
		$items = apply_filters( 'wb_listora_onboarding_checklist', $items );

		// Re-assert the shape: the renderer reads these keys unguarded, and a
		// third-party item missing one would fatal the dashboard.
		$clean = array();

		foreach ( (array) $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['label'] ) ) {
				continue;
			}

			$clean[] = array(
				'label' => (string) $item['label'],
				'done'  => ! empty( $item['done'] ),
				'icon'  => isset( $item['icon'] ) ? (string) $item['icon'] : 'circle',
				'url'   => isset( $item['url'] ) ? (string) $item['url'] : '',
			);
		}

		return $clean;
	}

	/**
	 * Render the onboarding checklist widget on the dashboard.
	 */
	private function render_onboarding_checklist() {
		// Do not show if dismissed. Pre-1.2.x stored this under the unprefixed
		// `listora_onboarding_dismissed`; migrate it on read so an existing
		// dismissal survives the rename to the `wb_listora_` namespace.
		if ( get_option( 'wb_listora_onboarding_dismissed' ) ) {
			return;
		}
		if ( get_option( 'listora_onboarding_dismissed' ) ) {
			update_option( 'wb_listora_onboarding_dismissed', true );
			delete_option( 'listora_onboarding_dismissed' );
			return;
		}

		$checklist     = $this->get_onboarding_checklist();
		$completed     = count( array_filter( $checklist, fn( $item ) => $item['done'] ) );
		$total         = count( $checklist );
		$all_done      = $completed === $total;
		$pct           = $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;
		$dismiss_nonce = wp_create_nonce( 'listora_dismiss_onboarding' );

		// Done: one line, not ten struck-through rows (card 10337178377).
		if ( $all_done ) {
			echo '<div class="listora-card listora-onboarding listora-onboarding--done" id="listora-onboarding-checklist">';
			echo '<i data-lucide="check-circle-2" aria-hidden="true"></i>';
			echo '<p>' . esc_html__( 'Setup complete: your directory is ready.', 'wb-listora' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=listora-settings&tab=advanced' ) ) . '">' . esc_html__( 'Re-run setup', 'wb-listora' ) . '</a></p>';
			echo '<button type="button" class="listora-btn wp-element-button listora-btn--sm listora-onboarding__dismiss" id="listora-dismiss-onboarding" data-nonce="' . esc_attr( $dismiss_nonce ) . '">' . esc_html__( 'Dismiss', 'wb-listora' ) . '</button>';
			echo '</div>';
			return;
		}

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
		echo '<button type="button" class="listora-btn wp-element-button listora-btn--sm listora-onboarding__dismiss" id="listora-dismiss-onboarding" data-nonce="' . esc_attr( $dismiss_nonce ) . '">';
		echo '<i data-lucide="x"></i> ' . esc_html__( 'Dismiss', 'wb-listora' ) . '</button>';
		echo '</div>';

		echo '<div class="listora-card__body">';

		// Progress bar.
		echo '<div class="listora-onboarding__progress">';
		echo '<div class="listora-onboarding__progress-bar">';
		echo '<div class="listora-onboarding__progress-fill" style="--listora-progress:' . esc_attr( $pct ) . '%;"></div>';
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

		echo '</div>'; // .listora-card__body
		echo '</div>'; // .listora-card

		// Behaviour lives in assets/js/admin/admin-pages.js (Rule 11).
	}

	// ─── Page Renderers (placeholders — full implementations in dedicated classes) ───

	/**
	 * Everything waiting on the owner, one row per queue (card 10337178377:
	 * "Pending Items" counted reviews and claims but not the pending listing
	 * owners most need to act on).
	 *
	 * @return array<string, array<string, mixed>> Each: count, label, url, icon.
	 */
	private static function attention_queues() {
		$listings = (int) ( wp_count_posts( 'listora_listing' )->pending ?? 0 );
		$reviews  = \WBListora\Core\Reviews_Model::status_counts();
		$claims   = \WBListora\Core\Claims_Model::status_counts();
		$reported = count( \WBListora\Core\Reviews_Model::reported_ids() );

		$queues = array(
			'listings' => array(
				'count' => $listings,
				/* translators: %s: number of listings. */
				'label' => _n( '%s listing waiting for approval', '%s listings waiting for approval', $listings, 'wb-listora' ),
				'url'   => admin_url( 'edit.php?post_type=listora_listing&post_status=pending' ),
				'icon'  => 'map-pin',
			),
			'reviews'  => array(
				'count' => (int) $reviews['pending'],
				/* translators: %s: number of reviews. */
				'label' => _n( '%s review to moderate', '%s reviews to moderate', (int) $reviews['pending'], 'wb-listora' ),
				'url'   => admin_url( 'admin.php?page=listora-reviews&status=pending' ),
				'icon'  => 'star',
			),
			'reported' => array(
				'count' => $reported,
				/* translators: %s: number of reviews. */
				'label' => _n( '%s review reported by visitors', '%s reviews reported by visitors', $reported, 'wb-listora' ),
				'url'   => admin_url( 'admin.php?page=listora-reviews&reported=1' ),
				'icon'  => 'flag',
			),
			'claims'   => array(
				'count' => (int) $claims['pending'],
				/* translators: %s: number of claims. */
				'label' => _n( '%s ownership claim to decide', '%s ownership claims to decide', (int) $claims['pending'], 'wb-listora' ),
				'url'   => admin_url( 'admin.php?page=listora-claims&status=pending' ),
				'icon'  => 'shield-check',
			),
		);

		/**
		 * Filter the dashboard's "Needs attention" queues.
		 *
		 * Each: count (int), label (with %s for the count), url (the filtered
		 * queue), icon (Lucide name). Queues with a count of 0 are not shown.
		 *
		 * @since 1.9.0
		 *
		 * @param array $queues Queues by key.
		 */
		$queues = (array) apply_filters( 'wb_listora_dashboard_attention', $queues );

		return array_filter(
			$queues,
			static function ( $queue ) {
				return is_array( $queue ) && ! empty( $queue['count'] ) && isset( $queue['label'], $queue['url'] );
			}
		);
	}

	/**
	 * The latest things that happened: listings added, reviews, claims,
	 * and whatever Pro adds (purchases). The old feed listed reviews only
	 * under the title "Recent Activity".
	 *
	 * @param int $limit Items.
	 * @return array<int, array{time:int, icon:string, html:string}>
	 */
	private static function recent_activity( $limit = 8 ) {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$items  = array();

		$listings = get_posts(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => array( 'publish', 'pending' ),
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
			)
		);
		cache_users( array_map( 'intval', wp_list_pluck( $listings, 'post_author' ) ) );
		foreach ( $listings as $post ) {
			$author  = get_userdata( (int) $post->post_author );
			$items[] = array(
				'time' => (int) get_post_time( 'U', true, $post ),
				'icon' => 'map-pin',
				'html' => sprintf(
					/* translators: 1: member name, 2: listing title. */
					'pending' === $post->post_status ? esc_html__( '%1$s submitted %2$s for approval', 'wb-listora' ) : esc_html__( '%1$s added %2$s', 'wb-listora' ),
					'<strong>' . esc_html( $author ? $author->display_name : __( 'A member', 'wb-listora' ) ) . '</strong>',
					'<a href="' . esc_url( (string) get_edit_post_link( $post->ID ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a>'
				),
			);
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- indexed on created_at, bounded by LIMIT.
		$reviews = (array) $wpdb->get_results( $wpdb->prepare( "SELECT r.id, r.user_id, r.listing_id, r.overall_rating, r.status, r.created_at, si.title FROM {$prefix}reviews r LEFT JOIN {$prefix}search_index si ON r.listing_id = si.listing_id ORDER BY r.created_at DESC LIMIT %d", $limit ), ARRAY_A );
		$claims  = (array) $wpdb->get_results( $wpdb->prepare( "SELECT c.id, c.user_id, c.listing_id, c.status, c.created_at, si.title FROM {$prefix}claims c LEFT JOIN {$prefix}search_index si ON c.listing_id = si.listing_id ORDER BY c.created_at DESC LIMIT %d", $limit ), ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		foreach ( $reviews as $review ) {
			$items[] = array(
				'time' => (int) strtotime( $review['created_at'] . ' UTC' ),
				'icon' => 'star',
				'html' => sprintf(
					/* translators: 1: reviewer, 2: listing title, 3: star rating. */
					esc_html__( '%1$s reviewed %2$s %3$s', 'wb-listora' ),
					'<strong>' . esc_html( wb_listora_review_author_name( (int) $review['user_id'] ) ) . '</strong>',
					'<a href="' . esc_url( admin_url( 'admin.php?page=listora-reviews&status=' . ( 'pending' === $review['status'] ? 'pending' : 'all' ) ) ) . '">' . esc_html( $review['title'] ? $review['title'] : '#' . $review['listing_id'] ) . '</a>',
					'<span class="listora-activity-item__stars" aria-label="' . esc_attr( sprintf( /* translators: %d: rating. */ __( '%d out of 5 stars', 'wb-listora' ), (int) $review['overall_rating'] ) ) . '">' . esc_html( str_repeat( "\u{2605}", max( 0, min( 5, (int) $review['overall_rating'] ) ) ) ) . '</span>'
				),
			);
		}
		foreach ( $claims as $claim ) {
			$user    = get_userdata( (int) $claim['user_id'] );
			$items[] = array(
				'time' => (int) strtotime( $claim['created_at'] . ' UTC' ),
				'icon' => 'shield-check',
				'html' => sprintf(
					/* translators: 1: member name, 2: listing title. */
					esc_html__( '%1$s claimed %2$s', 'wb-listora' ),
					'<strong>' . esc_html( $user ? $user->display_name : __( 'A member', 'wb-listora' ) ) . '</strong>',
					'<a href="' . esc_url( admin_url( 'admin.php?page=listora-claims&status=' . sanitize_key( (string) $claim['status'] ) ) ) . '">' . esc_html( $claim['title'] ? $claim['title'] : '#' . $claim['listing_id'] ) . '</a>'
				),
			);
		}

		/**
		 * Filter the dashboard activity feed before it is sorted and cut.
		 *
		 * Each: time (Unix, UTC), icon (Lucide), html (escaped).
		 *
		 * @since 1.9.0
		 *
		 * @param array $items Activity items.
		 * @param int   $limit Items shown.
		 */
		$items = (array) apply_filters( 'wb_listora_dashboard_activity', $items, $limit );

		usort(
			$items,
			static function ( $a, $b ) {
				return (int) $b['time'] <=> (int) $a['time'];
			}
		);
		return array_slice( $items, 0, $limit );
	}

	/**
	 * Render the dashboard (card 10337178377).
	 */
	public function render_dashboard_page() {
		global $wpdb;
		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$table  = wb_listora_service( 'admin_table' );
		$table  = $table instanceof Admin_Table ? $table : null;

		$counts    = wp_count_posts( 'listora_listing' );
		$published = (int) ( $counts->publish ?? 0 );
		$reviews   = \WBListora\Core\Reviews_Model::status_counts();
		$members   = count_users();
		$queues    = self::attention_queues();
		$waiting   = array_sum( wp_list_pluck( $queues, 'count' ) );
		$since     = gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- indexed counts.
		$savers      = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT user_id) FROM {$prefix}favorites" );
		$new_reviews = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$prefix}reviews WHERE created_at >= %s", $since ) );
		$new_members = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s", $since ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$new_listings = (int) ( new \WP_Query(
			array(
				'post_type'      => 'listora_listing',
				'post_status'    => array( 'publish', 'pending' ),
				'date_query'     => array( array( 'after' => '30 days ago' ) ),
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		) )->found_posts;

		echo '<div class="wrap wb-listora-admin">';

		// ── One-time welcome banner (arrives from setup wizard). ──
		$welcome_key = 'wb_listora_just_completed_setup_' . get_current_user_id();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only query flag, no state change.
		if ( isset( $_GET['listora-welcome'] ) && get_transient( $welcome_key ) ) {
			$current_user = wp_get_current_user();
			delete_transient( $welcome_key );
			echo '<div class="listora-welcome-banner">';
			echo '<div class="listora-welcome-banner__icon" aria-hidden="true"><i data-lucide="party-popper"></i></div>';
			echo '<div class="listora-welcome-banner__body">';
			echo '<h2 class="listora-welcome-banner__title">';
			printf(
				/* translators: %s: user display name */
				esc_html__( 'Welcome, %s — your directory is live.', 'wb-listora' ),
				esc_html( $current_user->display_name )
			);
			echo '</h2>';
			echo '<p class="listora-welcome-banner__desc">';
			esc_html_e( 'Next step: add your first listing or fine-tune your settings. The checklist below tracks your progress.', 'wb-listora' );
			echo '</p>';
			echo '</div>';
			echo '<div class="listora-welcome-banner__actions">';
			echo '<a href="' . esc_url( admin_url( 'post-new.php?post_type=listora_listing' ) ) . '" class="listora-btn wp-element-button listora-btn--primary">';
			echo '<i data-lucide="plus"></i> ' . esc_html__( 'Add first listing', 'wb-listora' ) . '</a>';
			echo '<a href="' . esc_url( wb_listora_get_directory_url() ) . '" class="listora-btn wp-element-button" target="_blank" rel="noopener">';
			echo '<i data-lucide="external-link"></i> ' . esc_html__( 'View directory', 'wb-listora' ) . '</a>';
			echo '</div>';
			echo '</div>';
		}

		// ── Page Header ──.
		echo '<div class="listora-page-header">';
		echo '<div class="listora-page-header__left">';
		echo '<h1 class="listora-page-header__title"><i data-lucide="layout-dashboard" class="listora-icon--sm"></i> ' . esc_html__( 'Dashboard', 'wb-listora' ) . '</h1>';
		echo '<p class="listora-page-header__desc">' . esc_html__( 'What needs you today, and how the directory is doing.', 'wb-listora' ) . '</p>';
		echo '</div>';
		echo '<div class="listora-page-header__actions">';
		echo '<a href="' . esc_url( admin_url( 'post-new.php?post_type=listora_listing' ) ) . '" class="listora-btn wp-element-button listora-btn--primary"><i data-lucide="plus"></i> ' . esc_html__( 'Add Listing', 'wb-listora' ) . '</a>';
		echo '<a href="' . esc_url( wb_listora_get_directory_url() ) . '" class="listora-btn wp-element-button" target="_blank" rel="noopener"><i data-lucide="external-link"></i> ' . esc_html__( 'View Directory', 'wb-listora' ) . '</a>';
		echo '</div>';
		echo '</div>';
		echo '<hr class="wp-header-end">';

		if ( $table ) {
			// Right now: each card opens the list it counts.
			$first = $queues ? reset( $queues ) : null;
			$table->stat_cards(
				array(
					array(
						'label'   => __( 'Needs attention', 'wb-listora' ),
						'value'   => $waiting,
						'icon'    => $waiting ? 'bell-ring' : 'check-circle-2',
						'variant' => $waiting ? 'warn' : 'success',
						'url'     => $first ? $first['url'] : '',
						'hint'    => $waiting ? __( 'Waiting for your decision', 'wb-listora' ) : __( 'Nothing waiting on you', 'wb-listora' ),
					),
					array(
						'label'   => __( 'Published listings', 'wb-listora' ),
						'value'   => $published,
						'icon'    => 'map-pin',
						'variant' => 'accent',
						'url'     => admin_url( 'edit.php?post_type=listora_listing&post_status=publish' ),
					),
					array(
						'label'   => __( 'Reviews', 'wb-listora' ),
						'value'   => (int) $reviews['approved'],
						'icon'    => 'star',
						'variant' => 'success',
						'url'     => admin_url( 'admin.php?page=listora-reviews' ),
						'hint'    => __( 'Approved and showing', 'wb-listora' ),
					),
					array(
						'label' => __( 'Members', 'wb-listora' ),
						'value' => (int) $members['total_users'],
						'icon'  => 'users',
						'url'   => admin_url( 'users.php' ),
						/* translators: %s: number of members. */
						'hint'  => sprintf( _n( '%s saved a listing', '%s saved a listing', $savers, 'wb-listora' ), number_format_i18n( $savers ) ),
					),
				),
				'listora-stats-grid--4'
			);

			/**
			 * Filter the dashboard's "Last 30 days" cards. Pro adds money in,
			 * listing views and leads. Same shape as Admin_Table::stat_cards().
			 *
			 * @since 1.9.0
			 *
			 * @param array $cards Cards.
			 */
			$period = (array) apply_filters(
				'wb_listora_dashboard_period_cards',
				array(
					array(
						'label' => __( 'New listings', 'wb-listora' ),
						'value' => $new_listings,
						'icon'  => 'file-plus',
						'url'   => admin_url( 'edit.php?post_type=listora_listing' ),
					),
					array(
						'label' => __( 'New reviews', 'wb-listora' ),
						'value' => $new_reviews,
						'icon'  => 'message-square',
						'url'   => admin_url( 'admin.php?page=listora-reviews&period=30' ),
					),
					array(
						'label' => __( 'New members', 'wb-listora' ),
						'value' => $new_members,
						'icon'  => 'user-plus',
						'url'   => admin_url( 'users.php?orderby=registered&order=desc' ),
					),
				)
			);
			echo '<h2 class="listora-dashboard__period">' . esc_html__( 'Last 30 days', 'wb-listora' ) . '</h2>';
			$table->stat_cards( $period, 'listora-stats-grid--3' );
		}

		// ── Onboarding Checklist ──.
		$this->render_onboarding_checklist();

		echo '<div class="listora-dashboard-columns">';

		// ── Needs attention ──.
		echo '<div class="listora-card">';
		echo '<div class="listora-card__head"><h2 class="listora-card__title"><i data-lucide="bell-ring" class="listora-icon--sm"></i> ' . esc_html__( 'Needs attention', 'wb-listora' ) . '</h2></div>';
		echo '<div class="listora-card__body">';
		if ( $queues ) {
			echo '<ul class="listora-activity-list">';
			foreach ( $queues as $queue ) {
				echo '<li class="listora-activity-item listora-activity-item--queue">';
				echo '<div class="listora-activity-item__icon listora-activity-item__icon--warn"><i data-lucide="' . esc_attr( (string) ( $queue['icon'] ?? 'circle-alert' ) ) . '"></i></div>';
				echo '<div class="listora-activity-item__text">' . esc_html( sprintf( (string) $queue['label'], number_format_i18n( (int) $queue['count'] ) ) ) . '</div>';
				echo '<a class="listora-btn wp-element-button listora-btn--sm" href="' . esc_url( (string) $queue['url'] ) . '">' . esc_html__( 'Open', 'wb-listora' ) . '</a>';
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<div class="listora-empty-state listora-empty-state--compact">';
			echo '<div class="listora-empty-state__icon"><i data-lucide="check-circle-2"></i></div>';
			echo '<p class="listora-empty-state__title">' . esc_html__( 'All caught up', 'wb-listora' ) . '</p>';
			echo '<p class="listora-empty-state__desc">' . esc_html__( 'New submissions, reviews and claims that need a decision show up here.', 'wb-listora' ) . '</p>';
			echo '</div>';
		}
		echo '</div></div>';

		// ── Recent activity ──.
		$activity = self::recent_activity();
		echo '<div class="listora-card">';
		echo '<div class="listora-card__head"><h2 class="listora-card__title"><i data-lucide="activity" class="listora-icon--sm"></i> ' . esc_html__( 'Recent activity', 'wb-listora' ) . '</h2></div>';
		echo '<div class="listora-card__body">';
		if ( $activity ) {
			echo '<ul class="listora-activity-list">';
			foreach ( $activity as $item ) {
				echo '<li class="listora-activity-item">';
				echo '<div class="listora-activity-item__icon"><i data-lucide="' . esc_attr( (string) $item['icon'] ) . '"></i></div>';
				echo '<div class="listora-activity-item__text">' . wp_kses_post( (string) $item['html'] ) . '</div>';
				/* translators: %s: time difference, e.g. "5 mins". */
				echo '<span class="listora-activity-item__time">' . esc_html( sprintf( __( '%s ago', 'wb-listora' ), human_time_diff( (int) $item['time'] ) ) ) . '</span>';
				echo '</li>';
			}
			echo '</ul>';
		} else {
			echo '<div class="listora-empty-state listora-empty-state--compact">';
			echo '<div class="listora-empty-state__icon"><i data-lucide="inbox"></i></div>';
			echo '<p class="listora-empty-state__title">' . esc_html__( 'No activity yet', 'wb-listora' ) . '</p>';
			echo '<p class="listora-empty-state__desc">' . esc_html__( 'New listings, reviews and claims appear here as they come in.', 'wb-listora' ) . '</p>';
			echo '</div>';
		}
		echo '</div></div>';

		echo '</div>'; // .listora-dashboard-columns.
		echo '</div>'; // .wrap.
	}

	/**
	 * Render Listing Types page — delegates to the Type Editor class.
	 */
	public function render_listing_types_page() {
		$editor = new Type_Editor();
		$editor->render();
	}

	/**
	 * Approve, reject or delete one review from the moderation screen.
	 *
	 * Dispatched through the review REST routes rather than written here, so
	 * wp-admin moderation gets the same capability check, before_/after_
	 * hooks, `wb_listora_review_status_changed`, cache busts and listing
	 * rating recompute as the API. The raw writes this replaced left the
	 * listing's rating and review count stale after every admin approval
	 * (found in the 2026-09-23 hooks audit).
	 *
	 * @param string $action    approve | reject | delete.
	 * @param int    $review_id Review ID.
	 * @return bool Whether the change was applied.
	 */
	private function moderate_review( $action, $review_id ) {
		$route = '/' . WB_LISTORA_REST_NAMESPACE . '/reviews/' . (int) $review_id;

		if ( 'delete' === $action ) {
			$request = new \WP_REST_Request( 'DELETE', $route );
		} elseif ( 'approve' === $action || 'reject' === $action ) {
			$request = new \WP_REST_Request( 'PUT', $route );
			$request->set_param( 'status', 'approve' === $action ? 'approved' : 'rejected' );
		} else {
			return false;
		}

		return ! rest_do_request( $request )->is_error();
	}

	/**
	 * Review actions (row and bulk), handled before output so the screen can
	 * redirect with a notice; a refresh never repeats one.
	 */
	public function handle_review_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; each branch verifies its nonce.
		if ( 'listora-reviews' !== ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' ) || ! current_user_can( 'moderate_listora_reviews' ) ) {
			return;
		}

		$ids    = array();
		$action = '';
		if ( isset( $_GET['review_action'], $_GET['review_id'] ) ) {
			check_admin_referer( 'listora_review_action' );
			$action = sanitize_key( wp_unslash( $_GET['review_action'] ) );
			$ids    = array( absint( $_GET['review_id'] ) );
		} elseif ( isset( $_POST['bulk_action'], $_POST['ids'] ) && '' !== $_POST['bulk_action'] ) {
			check_admin_referer( 'listora_review_bulk' );
			$action = sanitize_key( wp_unslash( $_POST['bulk_action'] ) );
			$ids    = array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) );
		}
		if ( ! $ids || ! in_array( $action, array( 'approve', 'reject', 'delete' ), true ) ) {
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews';
		$done  = 0;
		$same  = 0;
		foreach ( $ids as $id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$current = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $id ) );
			if ( null === $current ) {
				++$same;
				continue;
			}
			if ( ( 'approve' === $action && 'approved' === $current ) || ( 'reject' === $action && 'rejected' === $current ) ) {
				++$same;
				continue;
			}
			if ( $this->moderate_review( $action, $id ) ) {
				++$done;
			}
		}

		$back = remove_query_arg( array( 'review_action', 'review_id', '_wpnonce', 'listora_notice', 'n', 'same' ), wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=listora-reviews' ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'listora_notice' => $action,
					'n'              => $done,
					'same'           => $same,
				),
				$back
			)
		);
		exit;
	}

	/**
	 * Render the Reviews queue.
	 */
	public function render_reviews_page() {
		$table    = new Admin_Table();
		$base_url = admin_url( 'admin.php?page=listora-reviews' );
		$state    = $table->request( 'reviews', array( 'rating', 'listing_type', 'period', 'reply', 'reported' ), array( 'date', 'rating' ), 'date' );
		$filters  = $state['filters'];
		$counts   = \WBListora\Core\Reviews_Model::status_counts();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
		$view = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ( $counts['pending'] > 0 ? 'pending' : 'all' );
		$view = isset( $counts[ $view ] ) ? $view : 'all';

		$periods = array(
			'7'   => __( 'Last 7 days', 'wb-listora' ),
			'30'  => __( 'Last 30 days', 'wb-listora' ),
			'90'  => __( 'Last 90 days', 'wb-listora' ),
			'365' => __( 'Last 12 months', 'wb-listora' ),
		);

		$result  = \WBListora\Core\Reviews_Model::query(
			array(
				'status'       => 'all' === $view ? '' : $view,
				'rating'       => (int) $filters['rating'],
				'listing_type' => sanitize_key( $filters['listing_type'] ),
				'since_days'   => isset( $periods[ $filters['period'] ] ) ? (int) $filters['period'] : 0,
				'reply'        => in_array( $filters['reply'], array( 'yes', 'no' ), true ) ? $filters['reply'] : '',
				'reported'     => '1' === $filters['reported'],
				'search'       => $state['s'],
				'orderby'      => $state['orderby'],
				'order'        => $state['order'],
				'limit'        => $state['per_page'],
				'offset'       => $state['offset'],
			)
		);
		$reviews = $result['rows'];
		$total   = $result['total'];

		// Prime users, listings and terms for the page in batches, not per row.
		cache_users( array_filter( array_map( 'intval', wp_list_pluck( $reviews, 'user_id' ) ) ) );
		$listing_ids = array_filter( array_map( 'intval', wp_list_pluck( $reviews, 'listing_id' ) ) );
		if ( $listing_ids ) {
			_prime_post_caches( $listing_ids, false, false );
			update_object_term_cache( $listing_ids, 'listora_listing' );
		}
		$reported = array_flip( \WBListora\Core\Reviews_Model::reported_ids() );

		$type_options = array();
		$registry     = wb_listora_service( 'listing_types' );
		if ( $registry instanceof \WBListora\Contracts\Listing_Type_Registry_Interface ) {
			foreach ( $registry->get_all() as $listing_type ) {
				$type_options[ $listing_type->get_slug() ] = $listing_type->get_name();
			}
		}

		echo '<div class="wrap wb-listora-admin">';
		echo '<div class="listora-page-header"><div class="listora-page-header__left">';
		echo '<h1 class="listora-page-header__title"><i data-lucide="star"></i> ' . esc_html__( 'Reviews', 'wb-listora' ) . '</h1>';
		echo '<p class="listora-page-header__desc">' . esc_html__( 'Approve reviews before they appear on listings, and see which ones visitors reported.', 'wb-listora' ) . '</p>';
		echo '</div></div>';
		echo '<hr class="wp-header-end">';

		$this->render_review_notice();

		$rows = array();
		foreach ( $reviews as $rev ) {
			$rows[] = $this->review_row( $rev, isset( $reported[ (int) $rev['id'] ] ), $base_url );
		}

		$labels = array(
			'all'      => __( 'All', 'wb-listora' ),
			'pending'  => __( 'Pending', 'wb-listora' ),
			'approved' => __( 'Approved', 'wb-listora' ),
			'rejected' => __( 'Rejected', 'wb-listora' ),
		);
		$views  = array();
		foreach ( $labels as $key => $label ) {
			$views[ $key ] = array( $label, $counts[ $key ] );
		}

		$ratings = array();
		for ( $stars = 5; $stars >= 1; $stars-- ) {
			/* translators: %d: number of stars. */
			$ratings[ (string) $stars ] = sprintf( _n( '%d star', '%d stars', $stars, 'wb-listora' ), $stars );
		}

		$table->render(
			array(
				'id'         => 'reviews',
				'base_url'   => $base_url,
				'state'      => $state,
				'total'      => $total,
				/* translators: %s: number of reviews. */
				'count_text' => sprintf( _n( '%s review', '%s reviews', $total, 'wb-listora' ), number_format_i18n( $total ) ),
				'views'      => $views,
				'view'       => $view,
				'search'     => __( 'Search listing or review text', 'wb-listora' ),
				'filters'    => array(
					array(
						'name'    => 'rating',
						'label'   => __( 'Any rating', 'wb-listora' ),
						'options' => $ratings,
					),
					array(
						'name'    => 'listing_type',
						'label'   => __( 'Any listing type', 'wb-listora' ),
						'options' => $type_options,
					),
					array(
						'name'    => 'period',
						'label'   => __( 'Any time', 'wb-listora' ),
						'options' => $periods,
					),
					array(
						'name'    => 'reply',
						'label'   => __( 'Owner reply: any', 'wb-listora' ),
						'options' => array(
							'yes' => __( 'Has an owner reply', 'wb-listora' ),
							'no'  => __( 'No owner reply', 'wb-listora' ),
						),
					),
					array(
						'name'    => 'reported',
						'label'   => __( 'Reported or not', 'wb-listora' ),
						'options' => array( '1' => __( 'Reported by visitors', 'wb-listora' ) ),
					),
				),
				'columns'    => array(
					'review'  => array( 'label' => __( 'Review', 'wb-listora' ) ),
					'listing' => array( 'label' => __( 'Listing', 'wb-listora' ) ),
					'author'  => array(
						'label'    => __( 'Author', 'wb-listora' ),
						'priority' => 3,
					),
					'rating'  => array(
						'label'    => __( 'Rating', 'wb-listora' ),
						'sortable' => true,
					),
					'status'  => array( 'label' => __( 'Status', 'wb-listora' ) ),
					'date'    => array(
						'label'    => __( 'Date', 'wb-listora' ),
						'priority' => 3,
						'sortable' => true,
					),
				),
				'rows'       => $rows,
				'bulk'       => array(
					'approve' => __( 'Approve', 'wb-listora' ),
					'reject'  => __( 'Reject', 'wb-listora' ),
					'delete'  => __( 'Delete', 'wb-listora' ),
				),
				'bulk_nonce' => 'listora_review_bulk',
				'empty'      => array(
					'title' => 'pending' === $view ? __( 'No reviews waiting', 'wb-listora' ) : __( 'No reviews yet', 'wb-listora' ),
					'text'  => __( 'Reviews appear here as visitors rate your listings.', 'wb-listora' ),
					'icon'  => 'star',
				),
			)
		);

		echo '</div>';
	}

	/**
	 * The notice after a review action.
	 */
	private function render_review_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$action = isset( $_GET['listora_notice'] ) ? sanitize_key( wp_unslash( $_GET['listora_notice'] ) ) : '';
		$done   = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0;
		$same   = isset( $_GET['same'] ) ? absint( $_GET['same'] ) : 0;
		// phpcs:enable
		$texts = array(
			/* translators: %s: number of reviews. */
			'approve' => _n( '%s review approved. It now shows on the listing.', '%s reviews approved. They now show on their listings.', $done, 'wb-listora' ),
			/* translators: %s: number of reviews. */
			'reject'  => _n( '%s review rejected. It no longer shows on the listing.', '%s reviews rejected. They no longer show on their listings.', $done, 'wb-listora' ),
			/* translators: %s: number of reviews. */
			'delete'  => _n( '%s review deleted.', '%s reviews deleted.', $done, 'wb-listora' ),
		);
		if ( ! isset( $texts[ $action ] ) ) {
			return;
		}
		if ( $done ) {
			echo '<div class="notice notice-success listora-notice is-dismissible"><p>' . esc_html( sprintf( $texts[ $action ], number_format_i18n( $done ) ) ) . '</p></div>';
		}
		if ( $same ) {
			/* translators: %s: number of reviews. */
			echo '<div class="notice notice-info listora-notice is-dismissible"><p>' . esc_html( sprintf( _n( '%s review was already in that state or no longer exists (perhaps changed in another tab or by another moderator) and was left as it was.', '%s reviews were already in that state or no longer exist (perhaps changed in another tab or by another moderator) and were left as they were.', $same, 'wb-listora' ), number_format_i18n( $same ) ) ) . '</p></div>';
		}
	}

	/**
	 * One review as a table row.
	 *
	 * @param array<mixed>  $rev      Review row.
	 * @param bool   $reported Whether visitors reported it.
	 * @param string $base_url Screen URL.
	 * @return array<mixed> Admin_Table row.
	 */
	private function review_row( array $rev, $reported, $base_url ) {
		$id      = (int) $rev['id'];
		$title   = $rev['listing_title'] ? (string) $rev['listing_title'] : '#' . $rev['listing_id'];
		$user_id = (int) $rev['user_id'];
		$user    = $user_id ? get_userdata( $user_id ) : false;
		if ( $user ) {
			$author = '<span class="listora-row-title">' . esc_html( $user->display_name ) . '</span><br><span class="listora-muted">' . esc_html( $user->user_email ) . '</span>';
		} elseif ( $user_id ) {
			/* translators: %d: former user ID. */
			$author = '<span class="listora-muted">' . esc_html( sprintf( __( 'Deleted user (#%d)', 'wb-listora' ), $user_id ) ) . '</span>';
		} else {
			$author = '<span class="listora-muted">' . esc_html__( 'Guest', 'wb-listora' ) . '</span>';
		}

		$rating = max( 0, min( 5, (int) $rev['overall_rating'] ) );
		/* translators: %d: stars out of 5. */
		$stars = '<span class="listora-star-rating" role="img" aria-label="' . esc_attr( sprintf( __( '%d out of 5 stars', 'wb-listora' ), $rating ) ) . '">' . esc_html( str_repeat( "\xe2\x98\x85", $rating ) ) . '<span class="listora-star-rating__empty">' . esc_html( str_repeat( "\xe2\x98\x86", 5 - $rating ) ) . '</span></span>';

		$status_labels = array(
			'approved' => array( __( 'Approved', 'wb-listora' ), 'listora-badge--success' ),
			'pending'  => array( __( 'Pending', 'wb-listora' ), 'listora-badge--warn' ),
			'rejected' => array( __( 'Rejected', 'wb-listora' ), 'listora-badge--danger' ),
		);
		$status        = $status_labels[ $rev['status'] ] ?? array( ucfirst( (string) $rev['status'] ), 'listora-badge--muted' );

		$has_reply = '' !== trim( (string) $rev['owner_reply'] );
		$review    = '' !== trim( (string) $rev['title'] ) ? '<span class="listora-row-title">' . esc_html( (string) $rev['title'] ) . '</span><br>' : '';
		$review   .= '<span class="listora-clamp">' . esc_html( wp_trim_words( (string) $rev['content'], 18 ) ) . '</span>';
		$tags      = '';
		if ( $has_reply ) {
			$tags .= '<span class="listora-badge listora-badge--muted">' . esc_html__( 'Owner replied', 'wb-listora' ) . '</span> ';
		}
		if ( $reported ) {
			$tags .= '<span class="listora-badge listora-badge--danger">' . esc_html__( 'Reported', 'wb-listora' ) . '</span>';
		}
		$review .= '' !== $tags ? '<br>' . $tags : '';

		$url = static function ( $action ) use ( $id, $base_url ) {
			return wp_nonce_url(
				add_query_arg(
					array(
						'review_action' => $action,
						'review_id'     => $id,
					),
					$base_url
				),
				'listora_review_action'
			);
		};

		$actions = array();
		if ( 'approved' !== $rev['status'] ) {
			$actions[] = array(
				'label'   => __( 'Approve', 'wb-listora' ),
				'url'     => $url( 'approve' ),
				'primary' => true,
			);
		}
		if ( 'rejected' !== $rev['status'] ) {
			$actions[] = array(
				'label' => __( 'Reject', 'wb-listora' ),
				'url'   => $url( 'reject' ),
			);
		}
		$actions[] = array(
			'label'   => __( 'Delete', 'wb-listora' ),
			'url'     => $url( 'delete' ),
			'danger'  => true,
			'confirm' => __( 'The review and its owner reply are removed for good. Rejecting hides a review and can be undone.', 'wb-listora' ),
		);

		return array(
			'id'      => $id,
			/* translators: %s: listing title. */
			'label'   => sprintf( __( 'Review of %s', 'wb-listora' ), $title ),
			'cells'   => array(
				'review'  => $review,
				'listing' => '<a href="' . esc_url( (string) get_permalink( (int) $rev['listing_id'] ) ) . '">' . esc_html( $title ) . '</a>',
				'author'  => $author,
				'rating'  => $stars,
				'status'  => '<span class="listora-badge ' . esc_attr( $status[1] ) . '">' . esc_html( $status[0] ) . '</span>',
				'date'    => esc_html( (string) mysql2date( (string) get_option( 'date_format' ), get_date_from_gmt( (string) $rev['created_at'] ) ) ),
			),
			'actions' => $actions,
			'detail'  => $this->review_detail( $rev, $stars, $reported ),
		);
	}

	/**
	 * The review drawer: the whole review, its criteria, reports, and the
	 * owner reply form.
	 *
	 * @param array<mixed>  $rev      Review row.
	 * @param string $stars    Rendered stars.
	 * @param bool   $reported Whether visitors reported it.
	 * @return string Escaped HTML.
	 */
	private function review_detail( array $rev, $stars, $reported ) {
		$user  = (int) $rev['user_id'] ? get_userdata( (int) $rev['user_id'] ) : false;
		$html  = '<p>' . $stars . ' &middot; ';
		$html .= $user ? esc_html( $user->display_name ) . ' &middot; <a href="mailto:' . esc_attr( $user->user_email ) . '">' . esc_html( $user->user_email ) . '</a>' : esc_html( (int) $rev['user_id'] ? __( 'Deleted user', 'wb-listora' ) : __( 'Guest', 'wb-listora' ) );
		$html .= '</p>';
		if ( '' !== trim( (string) $rev['title'] ) ) {
			$html .= '<h3>' . esc_html( (string) $rev['title'] ) . '</h3>';
		}
		$html .= wp_kses_post( wpautop( esc_html( (string) $rev['content'] ) ) );

		ob_start();
		wb_listora_render_review_criteria( $rev );
		$criteria = (string) ob_get_clean();
		if ( '' !== trim( $criteria ) ) {
			$html .= '<h3>' . esc_html__( 'Ratings by criterion', 'wb-listora' ) . '</h3><div class="listora-review-excerpt__full">' . $criteria . '</div>';
		}

		if ( $reported ) {
			$html .= '<h3>' . esc_html__( 'Visitor reports', 'wb-listora' ) . '</h3><ul class="listora-claim-history">';
			foreach ( \WBListora\Core\Reviews_Model::reports( (int) $rev['id'] ) as $report ) {
				$reporter = get_userdata( (int) ( $report['user_id'] ?? 0 ) );
				$html    .= '<li>' . esc_html( ucfirst( str_replace( '_', ' ', (string) ( $report['reason'] ?? '' ) ) ) );
				$html    .= ! empty( $report['details'] ) ? ': ' . esc_html( (string) $report['details'] ) : '';
				$html    .= '<br><span class="listora-muted">' . esc_html( $reporter ? $reporter->display_name : __( 'Deleted user', 'wb-listora' ) ) . ' &middot; ' . esc_html( (string) mysql2date( (string) get_option( 'date_format' ), get_date_from_gmt( (string) ( $report['date'] ?? '' ) ) ) ) . '</span></li>';
			}
			$html .= '</ul>';
		}

		$html .= '<h3>' . esc_html__( 'Owner reply', 'wb-listora' ) . '</h3>';
		if ( 'approved' !== $rev['status'] ) {
			// A reply is public only once the review is (card 10328137367).
			$html .= '' !== trim( (string) $rev['owner_reply'] ) ? wp_kses_post( wpautop( esc_html( (string) $rev['owner_reply'] ) ) ) : '';
			$html .= '<p class="listora-muted">' . esc_html__( 'You can reply once the review is approved.', 'wb-listora' ) . '</p>';
		} elseif ( ! wb_listora_review_replies_enabled() ) {
			$html .= '' !== trim( (string) $rev['owner_reply'] ) ? wp_kses_post( wpautop( esc_html( (string) $rev['owner_reply'] ) ) ) : '';
			$html .= '<p class="listora-muted">' . esc_html__( 'Owner replies are turned off in Settings > Reviews.', 'wb-listora' ) . '</p>';
		} else {
			$html .= '<div class="listora-reply-form" data-review-id="' . esc_attr( (string) $rev['id'] ) . '">';
			$html .= '<label class="screen-reader-text" for="listora-reply-' . esc_attr( (string) $rev['id'] ) . '">' . esc_html__( 'Owner reply', 'wb-listora' ) . '</label>';
			$html .= '<textarea id="listora-reply-' . esc_attr( (string) $rev['id'] ) . '" class="listora-reply-textarea large-text" rows="4" placeholder="' . esc_attr__( 'Write a reply the listing page will show under this review', 'wb-listora' ) . '">' . esc_textarea( (string) $rev['owner_reply'] ) . '</textarea>';
			$html .= '<p><button type="button" class="listora-btn listora-btn--primary listora-btn--sm listora-reply-submit">' . esc_html( '' === trim( (string) $rev['owner_reply'] ) ? __( 'Post reply', 'wb-listora' ) : __( 'Update reply', 'wb-listora' ) ) . '</button></p>';
			$html .= '<div class="listora-reply-status" role="status"></div></div>';
		}
		return $html;
	}

	/**
	 * Fire the canonical claim-updated hook from an admin-side transition.
	 *
	 * The admin Claims page changed claim status without ever firing
	 * `wb_listora_after_update_claim` — only the REST `update_claim()` did. So
	 * every listener on that hook silently missed every admin approve/reject:
	 * Pro's Audit_Log (the audit trail simply had no row), Pro's
	 * Outgoing_Webhooks (no `claim_approved` delivery), and Free's own
	 * Suite_Notifications. The claim-approved email still went out only
	 * because it hangs off the separate `wb_listora_claim_approved` fired
	 * inside `apply_approval_side_effects()` — which is exactly what made the
	 * gap hard to spot: the visible half worked.
	 *
	 * Same signature as the REST fire, so a listener cannot tell the paths
	 * apart. The third argument is the REST request, which does not exist
	 * here; every current listener ignores it, and `null` is honest about
	 * there being no request rather than inventing one.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $claim_id   Claim ID.
	 * @param string $new_status New claim status (`approved` / `rejected`).
	 * @return void
	 */
	private static function fire_claim_updated( $claim_id, $new_status ) {
		/**
		 * Fires after a claim's status changes, on every path.
		 *
		 * @since 1.0.0
		 *
		 * @param int                   $claim_id   Claim ID.
		 * @param string                $new_status New status.
		 * @param \WP_REST_Request|null $request    Request, or null on the admin path.
		 */
		do_action( 'wb_listora_after_update_claim', (int) $claim_id, (string) $new_status, null );
	}

	/**
	 * Claim actions (row and bulk), handled before any output so the screen
	 * can redirect: a refresh never repeats an action, and the notice says
	 * what happened, including "already decided" when another moderator
	 * acted first.
	 */
	public function handle_claim_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only; each branch verifies its nonce.
		if ( 'listora-claims' !== ( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' ) || ! current_user_can( 'manage_listora_claims' ) ) {
			return;
		}

		$ids    = array();
		$action = '';
		if ( isset( $_GET['claim_action'], $_GET['claim_id'] ) ) {
			check_admin_referer( 'listora_claim_action' );
			$action = sanitize_key( wp_unslash( $_GET['claim_action'] ) );
			$ids    = array( absint( $_GET['claim_id'] ) );
		} elseif ( isset( $_POST['bulk_action'], $_POST['ids'] ) && '' !== $_POST['bulk_action'] ) {
			check_admin_referer( 'listora_claim_bulk' );
			$action = sanitize_key( wp_unslash( $_POST['bulk_action'] ) );
			$ids    = array_filter( array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) );
		}
		if ( ! $ids || ! in_array( $action, array( 'approve', 'reject', 'delete' ), true ) ) {
			return;
		}

		global $wpdb;
		$done = 0;
		$same = 0;
		foreach ( $ids as $id ) {
			if ( 'delete' === $action ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$done += (int) $wpdb->delete( $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'claims', array( 'id' => $id ) );
				continue;
			}
			$status = 'approve' === $action ? 'approved' : 'rejected';
			$result = \WBListora\REST\Claims_Controller::change_status( $id, $status, 'approve' === $action ? 'admin_claim' : 'admin_claim_reject' );
			if ( 'updated' === $result ) {
				++$done;
				self::fire_claim_updated( $id, $status );
			} elseif ( 'unchanged' === $result ) {
				++$same;
			}
		}

		$back = remove_query_arg( array( 'claim_action', 'claim_id', '_wpnonce', 'listora_notice', 'n', 'same' ), wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=listora-claims' ) );
		wp_safe_redirect(
			add_query_arg(
				array(
					'listora_notice' => $action,
					'n'              => $done,
					'same'           => $same,
				),
				$back
			)
		);
		exit;
	}

	/**
	 * Render the Claims queue.
	 */
	public function render_claims_page() {
		$table    = new Admin_Table();
		$base_url = admin_url( 'admin.php?page=listora-claims' );
		$state    = $table->request( 'claims', array(), array( 'date' ), 'date' );
		$counts   = \WBListora\Core\Claims_Model::status_counts();

		// Pending first: while anything waits for a decision, that is the
		// queue the owner opens to (card 10337181799).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view.
		$view = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ( $counts['pending'] > 0 ? 'pending' : 'all' );
		$view = isset( $counts[ $view ] ) ? $view : 'all';

		$query  = array(
			'status' => 'all' === $view ? '' : $view,
			'search' => $state['s'],
			'limit'  => $state['per_page'],
			'offset' => $state['offset'],
			'order'  => $state['order'],
		);
		$total  = \WBListora\Core\Claims_Model::get_list_count( $query );
		$claims = \WBListora\Core\Claims_Model::get_list( $query );

		// Claimant history for everyone on this page, in one query.
		$history = \WBListora\Core\Claims_Model::history_for_users( wp_list_pluck( $claims, 'user_id' ) );

		echo '<div class="wrap wb-listora-admin">';
		echo '<div class="listora-page-header"><div class="listora-page-header__left">';
		echo '<h1 class="listora-page-header__title"><i data-lucide="shield-check"></i> ' . esc_html__( 'Claims', 'wb-listora' ) . '</h1>';
		echo '<p class="listora-page-header__desc">' . esc_html__( 'Business owners asking to take over a listing. Approving gives them the listing.', 'wb-listora' ) . '</p>';
		echo '</div></div>';
		echo '<hr class="wp-header-end">';

		$this->render_claim_notice();

		$rows = array();
		foreach ( $claims as $claim ) {
			$rows[] = $this->claim_row( $claim, $history[ (int) $claim['user_id'] ] ?? array(), $base_url );
		}

		$labels = array(
			'all'      => __( 'All', 'wb-listora' ),
			'pending'  => __( 'Pending', 'wb-listora' ),
			'approved' => __( 'Approved', 'wb-listora' ),
			'rejected' => __( 'Rejected', 'wb-listora' ),
		);
		$views  = array();
		foreach ( $labels as $key => $label ) {
			$views[ $key ] = array( $label, $counts[ $key ] );
		}

		$table->render(
			array(
				'id'         => 'claims',
				'base_url'   => $base_url,
				'state'      => $state,
				'total'      => $total,
				/* translators: %s: number of claims. */
				'count_text' => sprintf( _n( '%s claim', '%s claims', $total, 'wb-listora' ), number_format_i18n( $total ) ),
				'views'      => $views,
				'view'       => $view,
				'search'     => __( 'Search by listing, name or email', 'wb-listora' ),
				'columns'    => array(
					'listing'  => array( 'label' => __( 'Listing', 'wb-listora' ) ),
					'claimant' => array( 'label' => __( 'Claimant', 'wb-listora' ) ),
					'proof'    => array(
						'label'    => __( 'Proof', 'wb-listora' ),
						'priority' => 2,
					),
					'status'   => array( 'label' => __( 'Status', 'wb-listora' ) ),
					'date'     => array(
						'label'    => __( 'Submitted', 'wb-listora' ),
						'priority' => 3,
						'sortable' => true,
					),
				),
				'rows'       => $rows,
				'bulk'       => array(
					'approve' => __( 'Approve', 'wb-listora' ),
					'reject'  => __( 'Reject', 'wb-listora' ),
					'delete'  => __( 'Delete', 'wb-listora' ),
				),
				'bulk_nonce' => 'listora_claim_bulk',
				'empty'      => array(
					'title' => 'pending' === $view ? __( 'No claims waiting', 'wb-listora' ) : __( 'No claims yet', 'wb-listora' ),
					'text'  => __( 'Business owners claim their listing from its page. New claims appear here.', 'wb-listora' ),
					'icon'  => 'shield-check',
				),
			)
		);

		echo '</div>';
	}

	/**
	 * The notice after a claim action.
	 */
	private function render_claim_notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$action = isset( $_GET['listora_notice'] ) ? sanitize_key( wp_unslash( $_GET['listora_notice'] ) ) : '';
		$done   = isset( $_GET['n'] ) ? absint( $_GET['n'] ) : 0;
		$same   = isset( $_GET['same'] ) ? absint( $_GET['same'] ) : 0;
		// phpcs:enable
		$texts = array(
			/* translators: %s: number of claims. */
			'approve' => _n( '%s claim approved. The listing now belongs to the claimant.', '%s claims approved. Each listing now belongs to its claimant.', $done, 'wb-listora' ),
			/* translators: %s: number of claims. */
			'reject'  => _n( '%s claim rejected. The claimant has been told.', '%s claims rejected. The claimants have been told.', $done, 'wb-listora' ),
			/* translators: %s: number of claims. */
			'delete'  => _n( '%s claim deleted.', '%s claims deleted.', $done, 'wb-listora' ),
		);
		if ( ! isset( $texts[ $action ] ) ) {
			return;
		}
		if ( $done ) {
			echo '<div class="notice notice-success listora-notice is-dismissible"><p>' . esc_html( sprintf( $texts[ $action ], number_format_i18n( $done ) ) ) . '</p></div>';
		}
		if ( $same ) {
			/* translators: %s: number of claims. */
			echo '<div class="notice notice-info listora-notice is-dismissible"><p>' . esc_html( sprintf( _n( '%s claim was already decided (perhaps in another tab or by another moderator) and was left as it was.', '%s claims were already decided (perhaps in another tab or by another moderator) and were left as they were.', $same, 'wb-listora' ), number_format_i18n( $same ) ) ) . '</p></div>';
		}
	}

	/**
	 * One claim as a table row.
	 *
	 * @param array<mixed>  $claim    Claim row (Claims_Model::get_list()).
	 * @param array<mixed>  $history  Every claim by the same member.
	 * @param string $base_url Screen URL.
	 * @return array<mixed> Admin_Table row.
	 */
	private function claim_row( array $claim, array $history, $base_url ) {
		$id      = (int) $claim['id'];
		$title   = $claim['listing_title'] ? (string) $claim['listing_title'] : '#' . $claim['listing_id'];
		$name    = $claim['user_name'] ? (string) $claim['user_name'] : __( 'Deleted user', 'wb-listora' );
		$others  = array_values(
			array_filter(
				$history,
				static function ( $row ) use ( $id ) {
					return (int) $row['id'] !== $id;
				}
			)
		);
		$flagged = count( $others ) >= 2 || in_array( 'rejected', wp_list_pluck( $others, 'status' ), true );

		$status_labels = array(
			'approved' => array( __( 'Approved', 'wb-listora' ), 'listora-badge--success' ),
			'pending'  => array( __( 'Pending', 'wb-listora' ), 'listora-badge--warn' ),
			'rejected' => array( __( 'Rejected', 'wb-listora' ), 'listora-badge--danger' ),
		);
		$status        = $status_labels[ $claim['status'] ] ?? array( ucfirst( (string) $claim['status'] ), 'listora-badge--muted' );

		$claimant = '<span class="listora-row-title">' . esc_html( $name ) . '</span>';
		if ( ! empty( $claim['user_email'] ) ) {
			$claimant .= '<br><span class="listora-muted">' . esc_html( (string) $claim['user_email'] ) . '</span>';
		}
		if ( $flagged ) {
			/* translators: %d: number of other claims by the same member. */
			$claimant .= '<br><span class="listora-badge listora-badge--warn">' . esc_html( sprintf( _n( '%d other claim', '%d other claims', count( $others ), 'wb-listora' ), count( $others ) ) ) . '</span>';
		}

		$proof = trim( (string) $claim['proof_text'] );
		$files = json_decode( (string) ( $claim['proof_files'] ?? '' ), true );
		$files = is_array( $files ) ? $files : array();

		$url = static function ( $action ) use ( $id, $base_url ) {
			return wp_nonce_url(
				add_query_arg(
					array(
						'claim_action' => $action,
						'claim_id'     => $id,
					),
					$base_url
				),
				'listora_claim_action'
			);
		};

		$actions = array();
		if ( 'approved' !== $claim['status'] ) {
			$actions[] = array(
				'label'   => __( 'Approve', 'wb-listora' ),
				'url'     => $url( 'approve' ),
				'primary' => true,
			);
		}
		if ( 'pending' === $claim['status'] ) {
			$actions[] = array(
				'label' => __( 'Reject', 'wb-listora' ),
				'url'   => $url( 'reject' ),
			);
		} elseif ( 'approved' === $claim['status'] ) {
			$owner     = get_userdata( \WBListora\REST\Claims_Controller::pre_claim_author( (int) $claim['listing_id'] ) );
			$actions[] = array(
				'label'   => __( 'Reverse approval', 'wb-listora' ),
				'url'     => $url( 'reject' ),
				'more'    => true,
				'confirm' => sprintf(
					/* translators: 1: listing title, 2: claimant name, 3: previous owner name. */
					__( '"%1$s" will be taken back from %2$s and returned to %3$s. %2$s is told the claim was rejected.', 'wb-listora' ),
					$title,
					$name,
					$owner ? $owner->display_name : __( 'you', 'wb-listora' )
				),
			);
		}
		$actions[] = array(
			'label'   => __( 'Delete', 'wb-listora' ),
			'url'     => $url( 'delete' ),
			'danger'  => true,
			'confirm' => __( 'The claim record is removed. Who owns the listing does not change.', 'wb-listora' ),
		);

		return array(
			'id'      => $id,
			'label'   => $title,
			'cells'   => array(
				'listing'  => '<a class="listora-row-title" href="' . esc_url( (string) get_permalink( (int) $claim['listing_id'] ) ) . '">' . esc_html( $title ) . '</a>',
				'claimant' => $claimant,
				'proof'    => '' !== $proof ? '<span class="listora-clamp">' . esc_html( wp_trim_words( $proof, 18 ) ) . '</span>' . ( $files ? '<br><span class="listora-muted">' . esc_html( sprintf( /* translators: %d: number of files. */ _n( '%d document', '%d documents', count( $files ), 'wb-listora' ), count( $files ) ) ) . '</span>' : '' ) : '<span class="listora-muted">' . esc_html__( 'None given', 'wb-listora' ) . '</span>',
				'status'   => '<span class="listora-badge ' . esc_attr( $status[1] ) . '">' . esc_html( $status[0] ) . '</span>',
				'date'     => esc_html( (string) mysql2date( (string) get_option( 'date_format' ), get_date_from_gmt( (string) $claim['created_at'] ) ) ),
			),
			'actions' => $actions,
			'detail'  => $this->claim_detail( $claim, $proof, $files, $others ),
		);
	}

	/**
	 * The claim drawer: the full proof, its documents and the member's other
	 * claims.
	 *
	 * @param array<mixed>  $claim  Claim row.
	 * @param string $proof  Proof text.
	 * @param array<mixed>  $files  Proof attachment IDs.
	 * @param array<mixed>  $others The member's other claims.
	 * @return string Escaped HTML.
	 */
	private function claim_detail( array $claim, $proof, array $files, array $others ) {
		$html  = '<h3>' . esc_html__( 'Claimant', 'wb-listora' ) . '</h3><p>' . esc_html( $claim['user_name'] ? (string) $claim['user_name'] : __( 'Deleted user', 'wb-listora' ) );
		$html .= ! empty( $claim['user_email'] ) ? ' &middot; <a href="mailto:' . esc_attr( (string) $claim['user_email'] ) . '">' . esc_html( (string) $claim['user_email'] ) . '</a>' : '';
		$html .= '</p>';

		$html .= '<h3>' . esc_html__( 'Proof of ownership', 'wb-listora' ) . '</h3>';
		$html .= '' !== $proof ? '<p>' . nl2br( esc_html( $proof ) ) . '</p>' : '<p class="listora-muted">' . esc_html__( 'No statement given.', 'wb-listora' ) . '</p>';

		if ( $files ) {
			$html .= '<h3>' . esc_html__( 'Documents', 'wb-listora' ) . '</h3><ul class="listora-proof-files">';
			foreach ( $files as $att_id ) {
				// Guarded endpoint, not the raw file URL: proof can be an ID scan.
				$att_url = \WBListora\Core\Claim_Proofs::url( (int) $att_id );
				if ( ! $att_url ) {
					continue;
				}
				$mime  = (string) get_post_mime_type( (int) $att_id );
				$html .= '<li><a href="' . esc_url( $att_url ) . '" target="_blank" rel="noopener">';
				$html .= 0 === strpos( $mime, 'image/' )
					? '<img class="listora-proof-file__thumb" src="' . esc_url( $att_url ) . '" alt="' . esc_attr__( 'Proof document', 'wb-listora' ) . '">'
					: esc_html( (string) get_the_title( (int) $att_id ) );
				$html .= '</a></li>';
			}
			$html .= '</ul>';
		}

		$html .= '<h3>' . esc_html__( 'Other claims by this member', 'wb-listora' ) . '</h3>';
		if ( ! $others ) {
			$html .= '<p class="listora-muted">' . esc_html__( 'None. This is their only claim.', 'wb-listora' ) . '</p>';
		} else {
			$html .= '<ul class="listora-claim-history">';
			foreach ( array_slice( $others, 0, 10 ) as $other ) {
				$html .= '<li>' . esc_html( $other['listing_title'] ? (string) $other['listing_title'] : '#' . $other['listing_id'] ) . ' &middot; ' . esc_html( ucfirst( (string) $other['status'] ) ) . ' &middot; ' . esc_html( (string) mysql2date( (string) get_option( 'date_format' ), get_date_from_gmt( (string) $other['created_at'] ) ) ) . '</li>';
			}
			$html .= '</ul>';
			if ( count( $others ) > 10 ) {
				/* translators: %d: number of further claims. */
				$html .= '<p class="listora-muted">' . esc_html( sprintf( _n( 'and %d more', 'and %d more', count( $others ) - 10, 'wb-listora' ), count( $others ) - 10 ) ) . '</p>';
			}
		}
		return $html;
	}

	/**
	 * Render Settings page.
	 */
	public function render_settings_page() {
		Settings_Page::render();
	}

	/**
	 * The Settings > Integrations URL, with optional query args.
	 *
	 * @param array<mixed> $args Extra query args (install status).
	 * @return string
	 */
	public static function integrations_url( array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => 'listora-settings',
					'tab'  => 'integrations',
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Send the retired Integrations screen to its Settings tab, keeping the
	 * install result flags.
	 */
	public static function redirect_integrations_page(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect of status flags.
		$args = array();
		foreach ( array( 'listora_install', 'listora_msg' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				$args[ $key ] = rawurlencode( sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) );
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( self::integrations_url( $args ) );
		exit;
	}

	/**
	 * admin-post handler for the Integrations page one-click install.
	 *
	 * Action: wb_listora_install_companion (plugin-prefixed to avoid collisions
	 * with sibling plugins that ship an identical installer pattern).
	 *
	 * @return void
	 */
	public function handle_install_companion(): void {
		$slug = isset( $_POST['companion'] ) ? sanitize_key( wp_unslash( $_POST['companion'] ) ) : '';
		$tier = isset( $_POST['tier'] ) ? sanitize_key( wp_unslash( $_POST['tier'] ) ) : 'free';

		if ( '' === $slug ) {
			wp_safe_redirect(
				self::integrations_url(
					array(
						'listora_install' => 'error',
						'listora_msg'     => rawurlencode( __( 'No integration specified.', 'wb-listora' ) ),
					)
				)
			);
			exit;
		}

		if ( ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ),
			'wb_listora_install_companion_' . $slug
		) ) {
			wp_die( esc_html__( 'Security check failed. Please try again.', 'wb-listora' ) );
		}

		if ( ! current_user_can( 'install_plugins' ) ) {
			wp_die( esc_html__( 'You do not have permission to install plugins.', 'wb-listora' ) );
		}

		$license = isset( $_POST['license'] ) ? sanitize_text_field( wp_unslash( $_POST['license'] ) ) : '';
		$result  = \WBListora\Integrations\Companion_Installer::install( $slug, $tier, $license );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect(
				self::integrations_url(
					array(
						'listora_install' => 'error',
						'listora_msg'     => rawurlencode( $result->get_error_message() ),
					)
				)
			);
			exit;
		}

		wp_safe_redirect(
			self::integrations_url( array( 'listora_install' => 'ok' ) )
		);
		exit;
	}

	/**
	 * Render Setup Wizard.
	 */
	public function render_setup_wizard() {
		// Delegate to the Setup_Wizard class.
		$wizard = new Setup_Wizard();
		$wizard->render();
	}

	/**
	 * AJAX handler for running a migration.
	 */
	public function ajax_run_migration() {
		check_ajax_referer( 'listora_migration', '_nonce' );

		if ( ! current_user_can( 'manage_listora_settings' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'wb-listora' ) ), 403 );
		}

		$source  = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$dry_run = isset( $_POST['dry_run'] ) && '1' === $_POST['dry_run'];

		if ( empty( $source ) ) {
			wp_send_json_error( array( 'message' => __( 'No migration source specified.', 'wb-listora' ) ) );
		}

		$migrators = \WBListora\ImportExport\Migration_Base::get_migrators();
		$target    = null;

		foreach ( $migrators as $migrator ) {
			if ( $migrator->get_source_slug() === $source ) {
				$target = $migrator;
				break;
			}
		}

		if ( ! $target ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: source slug */
						__( 'Unknown migration source: %s', 'wb-listora' ),
						$source
					),
				)
			);
		}

		if ( ! $target->detect() ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: source plugin name */
						__( '%s data not found on this site.', 'wb-listora' ),
						$target->get_source_name()
					),
				)
			);
		}

		// Increase time limit for large migrations.
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$stats = $target->migrate_all( $dry_run );

		wp_send_json_success(
			array(
				'imported' => $stats['imported'],
				'skipped'  => $stats['skipped'],
				'errors'   => $stats['errors'],
				'total'    => $stats['total'],
				'dry_run'  => $dry_run,
			)
		);
	}

	/**
	 * Remove third-party admin notices on Listora admin pages.
	 *
	 * Keeps our plugin pages focused on Listora content — users shouldn't see
	 * unrelated "Please review this plugin" or "Install these plugins" notices
	 * when configuring the directory.
	 *
	 * Preserves Listora's own notices (anything whose callback class/function
	 * contains 'listora' or 'wb_listora') plus WordPress core notices.
	 */
	public function suppress_third_party_notices() {
		if ( ! $this->is_listora_screen() ) {
			return;
		}

		global $wp_filter;

		$notice_hooks = array(
			'admin_notices',
			'all_admin_notices',
			'user_admin_notices',
			'network_admin_notices',
		);

		foreach ( $notice_hooks as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $id => $cb ) {
					if ( $this->is_listora_callback( $cb['function'] ) ) {
						continue;
					}
					unset( $wp_filter[ $hook ]->callbacks[ $priority ][ $id ] );
				}
			}
		}
	}

	/**
	 * Check whether a notice callback belongs to Listora (safe to keep).
	 *
	 * @param mixed $callback The hook callback (string, array, or Closure).
	 * @return bool
	 */
	private function is_listora_callback( $callback ) {
		if ( is_string( $callback ) ) {
			return false !== stripos( $callback, 'listora' );
		}
		if ( is_array( $callback ) && isset( $callback[0] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return false !== stripos( $class, 'listora' ) || false !== stripos( $class, 'wblistora' );
		}
		// Closures and other callables — allow by default to avoid killing
		// WordPress core notices (updates, errors).
		return true;
	}
}
