<?php
/**
 * Main Plugin orchestrator.
 *
 * @package WBListora
 */

namespace WBListora;

use WBListora\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin singleton class.
 */
final class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor — hooks everything up.
	 */
	private function __construct() {
		// Translations register on `init` (priority 1) — calling
		// `load_plugin_textdomain` during plugin bootstrap triggers WP 6.7+
		// `_load_textdomain_just_in_time` notices and the cascading null
		// deprecation warnings against wp-includes/functions.php
		// (Basecamp 9842833276). Hook everything else here at construct time.
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		$this->init_core();
		$this->register_services();
		$this->init_hooks();

		/**
		 * Fires after WB Listora is fully loaded.
		 * Pro and extensions hook in here.
		 */
		do_action( 'wb_listora_loaded' );
	}

	/**
	 * Register the public services that Pro / extensions consume via
	 * wb_listora_service(). Must run before do_action( 'wb_listora_loaded' ).
	 *
	 * Each registered instance implements one of the
	 * \WBListora\Contracts\* interfaces — that interface is the documented
	 * extension surface, NOT the concrete class returned here.
	 *
	 * @return void
	 */
	private function register_services() {
		Service_Locator::register( 'listing_types', Core\Listing_Type_Registry::instance() );
		Service_Locator::register( 'featured', new Services\Featured_Service() );
		Service_Locator::register( 'meta', new Services\Meta_Service() );
		Service_Locator::register( 'services', new Services\Services_Service() );
		Service_Locator::register( 'search_indexer', new Search\Search_Indexer() );
		Service_Locator::register( 'search_engine', new Search\Search_Engine() );
		Service_Locator::register( 'geo_query', new Services\Geo_Query_Service() );
		Service_Locator::register( 'block_css', new Services\Block_CSS_Service() );
		Service_Locator::register( 'cache', new Services\Cache_Service() );
		// Automation. Registered before wb_listora_loaded fires so Pro can
		// resolve it at its own boot and declare its triggers into it.
		$triggers = new Automation\Trigger_Registry();
		Service_Locator::register( 'triggers', $triggers );

		/*
		 * Declare Free's own trigger catalogue, then fire
		 * `wb_listora_register_triggers` so Pro/add-ons can declare theirs
		 * into the same registry. Registers against the concrete instance
		 * directly (not a Service_Locator::get() round-trip) so the type stays
		 * Trigger_Registry_Interface instead of Service_Locator's generic
		 * object|null.
		 *
		 * POPULATING the registry is deferred to `init` priority 2; only the
		 * empty instance is registered during bootstrap. Every trigger carries
		 * a translated `label`, so building the catalogue calls __() — and
		 * doing that at bootstrap is precisely what the constructor comment
		 * above forbids. It reintroduced BC 9842833276 from the other end:
		 * ~6 `_load_textdomain_just_in_time` notices per request against BOTH
		 * the wb-listora and wb-listora-pro domains, on every page load.
		 *
		 * The notices were the visible half. The real damage was silent: WP
		 * 6.7+ refuses the too-early load, so on a non-English site every
		 * trigger label fell back to English permanently — in the webhook
		 * subscriber UI and in the published trigger catalogue.
		 *
		 * Priority 2 is after load_textdomain at init@1 and before every
		 * consumer (REST, admin UI, dispatch — all init@5 or later). The
		 * instance is still registered at bootstrap, so anything resolving
		 * `wb_listora_service( 'triggers' )` at `wb_listora_loaded` still gets
		 * the object; and Pro's listener is added at plugin-file load, so it
		 * runs whenever this fires. Guard: never call __() at bootstrap.
		 */
		add_action(
			'init',
			static function () use ( $triggers ) {
				Automation\Trigger_Definitions::register_all( $triggers );
			},
			2
		);
	}

	/**
	 * Load plugin text domain for translations. Hooked to `init@1` from the
	 * constructor — never called directly during bootstrap.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wb-listora',
			false,
			dirname( WB_LISTORA_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Flush rewrite rules once after activation, on the next init.
	 *
	 * `Activator::activate()` sets the `wb_listora_flush_rewrites_pending`
	 * transient (60s TTL) instead of calling `flush_rewrite_rules()` directly,
	 * because the activation hook fires before init and any CPT/taxonomy
	 * registration there triggers `_load_textdomain_just_in_time` notices on
	 * WP 6.7+. By the time this callback fires (init priority 99) the
	 * Post_Types + Taxonomies registrations at init priority 5 have already
	 * landed, so the flush picks up every fresh permalink rule. Card 9842833276.
	 *
	 * @return void
	 */
	public function maybe_flush_pending_rewrites() {
		if ( ! get_transient( 'wb_listora_flush_rewrites_pending' ) ) {
			return;
		}
		delete_transient( 'wb_listora_flush_rewrites_pending' );
		flush_rewrite_rules();
	}

	/**
	 * Restrict the WordPress media-library query to the current user's own
	 * uploads for non-privileged members.
	 *
	 * The frontend listing-submission form enqueues `wp.media()`; without this
	 * a non-admin member could browse other members' and the admin's Media
	 * Library through the picker (card 9996105562 — a privacy leak). Users who
	 * can manage others' content (editors/admins via `edit_others_posts`) keep
	 * the full library so site-wide media curation is unaffected. Site owners
	 * can override per-site with the `wb_listora_restrict_media_to_own_uploads`
	 * filter (e.g. to share a curated media pool with members).
	 *
	 * This is the authoritative guard: it runs on the `query-attachments`
	 * AJAX request itself (which requires the `upload_files` cap), so it can't
	 * be bypassed by tampering with the client-side picker config.
	 *
	 * @param array<string, mixed> $args WP_Query args for the attachment query.
	 * @return array<string, mixed>
	 */
	public function restrict_media_to_own_uploads( $args ) {
		$restrict = (bool) apply_filters(
			'wb_listora_restrict_media_to_own_uploads',
			! current_user_can( 'edit_others_posts' )
		);

		if ( $restrict ) {
			$args['author'] = get_current_user_id();
		}

		return $args;
	}

	/**
	 * Initialize core subsystems.
	 */
	private function init_core() {
		// Register CPT and Taxonomies early.
		add_action( 'init', array( new Core\Post_Types(), 'register' ), 5 );
		add_action( 'init', array( new Core\Taxonomies(), 'register' ), 5 );
		add_action( 'init', array( new Core\Capabilities(), 'register' ), 5 );

		// Deferred rewrite-rules flush after activation. The activation hook
		// sets the `wb_listora_flush_rewrites_pending` transient (see
		// `Activator::activate()`); this callback consumes it after the CPT
		// + taxonomies have already registered at init priority 5, so the
		// flush picks up the latest permalink rules without us having to call
		// any translation function during activation. Card 9842833276.
		add_action( 'init', array( $this, 'maybe_flush_pending_rewrites' ), 99 );

		// Make Listora layout-owning blocks render the same way on every
		// theme by tagging the host page with a `wb-listora-fullwidth` body
		// class consumed by theme-isolation.css.
		( new Core\Theme_Defenses() )->register();

		// Privacy: non-privileged members only ever see their OWN uploads in
		// the WordPress media picker, so the frontend listing-submission form
		// can never expose other members' or the admin's Media Library
		// (card 9996105562). Editors/admins (edit_others_posts) keep the full
		// library for site-wide media curation. Authoritative server-side
		// guard — the JS picker scoping in view.js is just UX on top of this.
		add_filter( 'ajax_query_attachments_args', array( $this, 'restrict_media_to_own_uploads' ) );

		// Listing type and field registries.
		add_action( 'init', array( Core\Listing_Type_Registry::instance(), 'init' ), 10 );
		add_action( 'init', array( Core\Field_Registry::instance(), 'init' ), 10 );
		add_action( 'init', array( new Core\Meta_Handler(), 'register_meta' ), 10 );
		// Cascade hard-deletes across every Free-owned listing-scoped data
		// table (reviews/votes/favorites/claims/services/analytics) and fire
		// the Pro extension point. Index tables stay with Search_Indexer,
		// which also handles trash. BC 10156782139.
		( new Core\Listing_Data_Eraser() )->init();

		// Owner-initiated member suspension, and enforcement of the
		// self-deactivation that shipped in 1.2.3 without any. Registers one
		// REST gate and one capability filter rather than editing ~45
		// permission callbacks, so an endpoint added later cannot escape it.
		( new Core\Member_Suspension() )->init();

		// Member-to-member blocking. Separate from suspension on purpose: this
		// is one member choosing not to see another, not the owner silencing
		// someone. App Store Guideline 1.2 requires it alongside reporting.
		( new Core\Member_Blocks() )->init();

		// WP-core cache invalidation — the wp_cache_set_last_changed
		// incrementor pattern. Wires write hooks to group bumps. Must run
		// before Listing_Data::init() so the group bumps fire first when a
		// write hook resolves multiple listeners.
		Core\Cache::init();

		// Dashboard cache-busting hooks.
		Core\Listing_Data::init();

		// Per-role listing limits + credits overflow enforcement.
		Core\Listing_Limits::init();

		// Featured lifecycle — duration, expiration cron, is_featured helper.
		Core\Featured::init();

		// Free contact-form on listing detail. Stands down when Pro's
		// Lead_Form feature toggle takes over (see Contact_Form::should_render()).
		Contact_Form::init();

		// Mobile-app credential acquisition (Wbcom App Auth standard).
		// App_Authorize_Access keeps core's authorize screen usable — the app's
		// deep-link scheme survives esc_url() there, and a WooCommerce-style
		// wp-admin block is exempted for that one screen. App_Connect wires the
		// one-door-per-site seams (BuddyNext bridge join, reconnect-replaces
		// pruner). Both are harmless no-ops when nothing uses them, and both
		// register unconditionally so plugin activation ORDER cannot matter.
		Auth\App_Authorize_Access::init();
		Auth\App_Connect::init();

		// Free analytics-lite — records bot-filtered, rate-limited view events
		// on the existing `listora_analytics` table and exposes per-listing view
		// counts to the dashboard / admin / REST surfaces. Stands down from
		// recording when Pro's analytics feature owns the table (it returns true
		// from the `wb_listora_pro_owns_analytics` filter), so views are never
		// double-counted. Reads keep working either way.
		Features\Analytics_Lite::init();

		// One-shot data repair — decodes HTML-entity-encoded term names left
		// over from CSV imports / type-registry seeds before the upstream
		// term-creation paths started normalizing input. Idempotent: guarded
		// by the wb_listora_term_entity_repair_done option. Runs at admin_init
		// instead of init so frontend requests don't pay the cost; the option
		// flip means subsequent admin pageloads are an early option_exists
		// branch. Wrapped in a void closure because the underlying method
		// returns int (count of repaired terms) for test introspection and
		// action callbacks must not return a value. Basecamp #9927392446.
		add_action(
			'admin_init',
			static function (): void {
				ImportExport\Term_Helper::repair_entity_encoded_term_names();
			}
		);
	}

	/**
	 * Initialize hooks for all subsystems.
	 */
	private function init_hooks() {
		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Search indexer.
		add_action( 'init', array( $this, 'init_search' ), 15 );

		// Email-template overrides: the read-back filters MUST exist on EVERY
		// request - notification emails fire from frontend REST + cron, where
		// is_admin() is false and Settings_Page (the other init caller) never
		// loads. Double-verify finding: admin-edited subject/body were
		// silently ignored on all real sends; only the admin test-send (an
		// admin request) applied them. init() is static-guarded so the admin
		// path calling it again is a no-op.
		Admin\Email_Templates_Page::init();

		// Admin.
		if ( is_admin() ) {
			add_action( 'init', array( $this, 'init_admin' ), 20 );
		}

		// Pro promotion / upsell surfaces — registers admin AND frontend hooks
		// (dashboard CTA, map OSM hint). The class self-bails when Pro is
		// active, so this is a no-op on Pro installs.
		add_action( 'init', array( $this, 'init_pro_promotion' ), 20 );

		// Frontend assets.
		add_action( 'init', array( $this, 'register_blocks' ), 20 );
		add_action( 'wp_enqueue_scripts', array( new Assets(), 'enqueue_frontend' ) );
		add_action( 'admin_enqueue_scripts', array( new Assets(), 'enqueue_admin' ) );

		// Mark every Listora-prefixed style handle as RTL-aware so WordPress
		// auto-loads the matching `*-rtl.css` sibling whenever is_rtl() is true.
		// Runs late (priority 100) after all our enqueue callbacks have registered
		// their handles. Safe to run on every request — wp_style_add_data is idempotent
		// and the actual `-rtl.css` swap only happens at print time when is_rtl().
		add_action( 'wp_enqueue_scripts', array( $this, 'mark_styles_rtl' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'mark_styles_rtl' ), 100 );

		// Workflow — deferred to init.
		add_action( 'init', array( $this, 'init_workflow' ), 15 );

		// One-shot duplicate-recurring-action sweep (BC 9910208588). The
		// in-request guard in Cron_Scheduler prevents same-request duplicates;
		// this catches the cross-request activation race where two simultaneous
		// requests both pass the as_next_scheduled_action check before either
		// commits. Runs once per request after init_workflow has scheduled
		// (priority 16) and only does work if duplicates actually exist.
		add_action( 'init', array( $this, 'dedupe_recurring_cron' ), 16 );

		// Expired listings — noindex header + content notice.
		add_action( 'template_redirect', array( $this, 'handle_expired_listing' ) );

		// Schema/SEO.
		add_action( 'wp_head', array( $this, 'output_schema' ), 5 );

		// The dashboard's tabs navigate (?tab=…), so the document title is
		// server-rendered on every real page load.
		add_filter( 'document_title_parts', array( $this, 'filter_dashboard_document_title' ) );
		add_filter( 'body_class', array( $this, 'dashboard_body_class' ) );

		// Sitemap (XML) feature gate — wb_listora_features_registry() exposes
		// a 'sitemap' toggle but had ZERO consumers, so disabling it did
		// nothing. WordPress core auto-includes 'public' CPTs in the sitemap,
		// so respecting the toggle requires filtering wp_sitemaps_post_types.
		// Surfaced during journey #29 feature-toggle parity sweep 2026-05-18.
		add_filter( 'wp_sitemaps_post_types', array( $this, 'filter_sitemap_post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( $this, 'filter_sitemap_taxonomies' ) );

		// OG tags, breadcrumbs, canonical URLs.
		Schema\Schema_Generator::init_seo();

		// Single listing — use our full-width template + inject listing-detail block.
		add_filter( 'single_template', array( $this, 'listing_single_template' ) );
		add_filter( 'the_content', array( $this, 'inject_listing_detail' ), 5 );

		// Register "Listora Full Width" page template for directory pages.
		add_filter( 'theme_page_templates', array( $this, 'register_page_templates' ) );
		add_filter( 'template_include', array( $this, 'load_page_template' ) );

		// Add body class for Listora pages (enables theme overrides in shared.css).
		add_filter( 'body_class', array( $this, 'add_listora_body_class' ) );

		// WP privacy-tools (GDPR) integration — register the Listora personal-data
		// exporter (reviews + claims + favorites) and eraser (anonymize reviews,
		// delete favorites + claims) with WordPress core. Neither core filter is
		// registered anywhere else in the plugin, so there is no duplicate-
		// registration risk. This is the single registration site for both; the
		// actual business logic lives in \WBListora\Privacy\Privacy_Exporter (f7b) and
		// \WBListora\Privacy\Privacy_Eraser (f7c) — we only hand core their callbacks here.
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_privacy_exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_privacy_erasers' ) );

		// A deactivated member's profile must stop being linkable. Free fires
		// `wb_listora_member_profile_url` at every site that renders a member
		// link (review author on the detail tab, the reviews block, and the
		// reviews REST payload), so answering '' here hides the profile
		// everywhere at once — including for Pro's BuddyPress integration,
		// which answers the same filter at the default priority 10. Priority 20
		// runs AFTER it so the suppression wins.
		add_filter( 'wb_listora_member_profile_url', array( $this, 'hide_deactivated_member_profile_url' ), 20, 2 );
	}

	/**
	 * Suppress the member-profile link for deactivated accounts.
	 *
	 * Deactivation must hide the member without destroying anything, so this is
	 * a read-time suppression: the moment the account is reactivated the link
	 * comes back on its own, with no data to migrate back.
	 *
	 * @param string $url     Resolved profile URL.
	 * @param int    $user_id Member the URL points at.
	 * @return string Empty string when the member is deactivated.
	 */
	public function hide_deactivated_member_profile_url( $url, $user_id ) {
		if ( function_exists( 'wb_listora_is_account_deactivated' ) && wb_listora_is_account_deactivated( (int) $user_id ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Register the Listora personal-data exporter with WordPress core.
	 *
	 * Thin wiring only — instantiates \WBListora\Privacy\Privacy_Exporter and hands core
	 * its paginated `export` callback under the stable `wb-listora` key. The
	 * `class_exists` guard keeps this null-safe if the exporter class is ever
	 * absent (stripped build, partial deploy) so the privacy tools never fatal.
	 *
	 * @param array<string, array<string, mixed>> $exporters Registered exporters keyed by slug.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_privacy_exporters( $exporters ) {
		if ( ! is_array( $exporters ) ) {
			$exporters = array();
		}

		if ( class_exists( '\WBListora\Privacy\Privacy_Exporter' ) ) {
			$exporters['wb-listora'] = array(
				'exporter_friendly_name' => __( 'WB Listora', 'wb-listora' ),
				'callback'               => array( new Privacy\Privacy_Exporter(), 'export' ),
			);
		}

		return $exporters;
	}

	/**
	 * Register the Listora personal-data eraser with WordPress core.
	 *
	 * Thin wiring only — instantiates \WBListora\Privacy\Privacy_Eraser and hands core
	 * its paginated `erase` callback under the stable `wb-listora` key. The
	 * `class_exists` guard keeps this null-safe if the eraser class is ever
	 * absent (stripped build, partial deploy) so the privacy tools never fatal.
	 *
	 * @param array<string, array<string, mixed>> $erasers Registered erasers keyed by slug.
	 * @return array<string, array<string, mixed>>
	 */
	public function register_privacy_erasers( $erasers ) {
		if ( ! is_array( $erasers ) ) {
			$erasers = array();
		}

		if ( class_exists( '\WBListora\Privacy\Privacy_Eraser' ) ) {
			$erasers['wb-listora'] = array(
				'eraser_friendly_name' => __( 'WB Listora', 'wb-listora' ),
				'callback'             => array( new Privacy\Privacy_Eraser(), 'erase' ),
			);
		}

		return $erasers;
	}

	/**
	 * Add 'listora-page' body class on pages that contain Listora blocks.
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function add_listora_body_class( $classes ) {
		$is_listora = false;

		if ( is_singular( 'listora_listing' ) ) {
			$classes[]  = 'listora-page';
			$classes[]  = 'listora-single';
			$is_listora = true;
		} elseif ( is_post_type_archive( 'listora_listing' ) ) {
			$classes[]  = 'listora-page';
			$classes[]  = 'listora-archive';
			$is_listora = true;
		} elseif ( is_page() ) {
			$post = get_post();
			if ( $post && ( has_block( 'listora/', $post ) || has_block( 'wb-listora/', $post ) ) ) {
				$classes[]  = 'listora-page';
				$is_listora = true;
			}
		}

		// Force full-width layout by removing theme sidebar classes.
		// This is the proper approach — works with any theme that uses
		// body classes to control sidebar visibility (BuddyX, Astra, GeneratePress, etc.)
		if ( $is_listora ) {
			$classes   = array_diff(
				$classes,
				array(
					'has-sidebar-right',
					'has-sidebar-left',
					'has-sidebar',
					'sidebar-right',
					'sidebar-left',
					'sticky-sidebar-enable',
					'layout-boxed',
				)
			);
			$classes[] = 'layout-wide';
			$classes[] = 'no-sidebar';
			$classes[] = 'full-width-content';
		}

		return $classes;
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes() {
		$controllers = array(
			new REST\Listings_Controller(),
			new REST\Search_Controller(),
			new REST\Listing_Types_Controller(),
			new REST\Reviews_Controller(),
			new REST\Favorites_Controller(),
			new REST\Claims_Controller(),
			new REST\Submission_Controller(),
			new REST\Dashboard_Controller(),
			new REST\Settings_Controller(),
			new REST\Import_Export_Controller(),
			new REST\Services_Controller(),
			// Self-serve account lifecycle: POST /me/deactivate, POST
			// /me/reactivate, DELETE /me. Apple App Store Guideline 5.1.1(v)
			// requires an in-app account-deletion path, so these ship in Free —
			// the requirement applies to every Listora-backed app, not only
			// Pro-licensed ones.
			new REST\Account_Controller(),
			// POST /auth/app-password — the mobile app's first credential.
			// Public by necessity; every guard lives in Auth\App_Credentials.
			new REST\Auth_Controller(),
		);

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}

		/**
		 * Fires after Listora REST routes are registered.
		 * Pro and extensions hook in here to add additional endpoints.
		 */
		do_action( 'wb_listora_rest_api_init' );
	}

	/**
	 * Initialize the search system.
	 */
	public function init_search() {
		$indexer = new Search\Search_Indexer();
		$indexer->register_hooks();
	}

	/**
	 * Initialize admin pages.
	 */
	public function init_admin() {
		new Admin\Admin();

		// Suspend / reinstate controls on the WordPress Users screens — where
		// an owner already goes to find a member, rather than a Listora page
		// they would have to learn while dealing with a problem.
		( new Admin\User_Moderation() )->init();
	}

	/**
	 * Initialize Pro promotion / upsell surfaces.
	 *
	 * Registered on init in BOTH admin and frontend contexts so the frontend
	 * CTAs (dashboard reviews, map OSM hint) and backend surfaces (upgrade
	 * page, settings banner, modal, dashboard widget) all share a single
	 * instance. The Pro_Promotion constructor self-bails when Pro is active.
	 */
	public function init_pro_promotion() {
		new Admin\Pro_Promotion();
	}

	/**
	 * Register all blocks.
	 */
	public function register_blocks() {
		$blocks_dir = WB_LISTORA_PLUGIN_DIR . 'blocks/';

		if ( ! is_dir( $blocks_dir ) ) {
			return;
		}

		// Register the shared Interactivity API store as a script module.
		$store_asset_path = WB_LISTORA_PLUGIN_DIR . 'build/interactivity/store.asset.php';
		$store_asset      = file_exists( $store_asset_path ) ? require $store_asset_path : array(
			'dependencies' => array(),
			'version'      => WB_LISTORA_VERSION,
		);

		wp_register_script_module(
			'listora-interactivity-store',
			WB_LISTORA_PLUGIN_URL . 'build/interactivity/store.js',
			array( '@wordpress/interactivity' ),
			$store_asset['version']
		);

		/*
		 * Script modules need their text domain wired by hand.
		 *
		 * `register_block_type()` calls `wp_set_script_translations()` for classic
		 * script handles only (see wp-includes/blocks.php) — it does NOT do the
		 * equivalent for `viewScriptModule`. Every Listora frontend bundle is a
		 * module, so without these two calls the store's toasts/errors and each
		 * block's view strings render in English no matter which locale is active.
		 *
		 * The JSON they resolve to is keyed on md5() of the built script's path
		 * relative to the plugin root, so it must be produced by
		 * `bin/build-i18n-json.mjs` — `wp i18n make-json` keys on the src/ path
		 * WordPress never asks for.
		 *
		 * `wp_set_script_module_translations()` landed in WordPress 7.0 and we
		 * still support 6.9, so every call is guarded. On 6.9 there is no API to
		 * translate a script module at all; those strings stay English until the
		 * site updates, which is the graceful outcome — not a fatal.
		 */
		$can_translate_modules = function_exists( 'wp_set_script_module_translations' );

		if ( $can_translate_modules ) {
			wp_set_script_module_translations(
				'listora-interactivity-store',
				'wb-listora',
				WB_LISTORA_PLUGIN_DIR . 'languages'
			);
		}

		$block_dirs = glob( $blocks_dir . '*/block.json' );

		foreach ( $block_dirs as $block_json ) {
			$block_type = register_block_type( dirname( $block_json ) );

			// Read the module IDs back off the registered type rather than
			// rebuilding them, so blocks added later are covered automatically.
			if ( $can_translate_modules && $block_type instanceof \WP_Block_Type ) {
				foreach ( (array) $block_type->view_script_module_ids as $module_id ) {
					wp_set_script_module_translations(
						$module_id,
						'wb-listora',
						WB_LISTORA_PLUGIN_DIR . 'languages'
					);
				}
			}
		}

		// Enqueue the shared store module when any Listora block renders.
		add_filter(
			'render_block',
			function ( $block_content, $block ) {
				if ( ! empty( $block['blockName'] ) && strpos( $block['blockName'], 'listora/' ) === 0 ) {
					wp_enqueue_script_module( 'listora-interactivity-store' );
				}
				return $block_content;
			},
			10,
			2
		);

		/*
		 * Second print pass for blocks that render late.
		 *
		 * WordPress prints script modules on `wp_footer` at priority 10 and
		 * walks the queue exactly once. The enqueue above happens during
		 * `render_block`, which is correct while the block is part of the post
		 * content — but a site owner can build their footer with anything, and
		 * a builder that renders it during `wp_footer` enqueues into a queue
		 * that has already been printed. The block then paints its markup and
		 * silently loses its view module, the shared store and its stylesheet:
		 * a search box that never searches, a dashboard that never hydrates.
		 *
		 * Priority 20 is deliberate on both sides. Core registers
		 * `wp_print_footer_scripts` (which runs `print_late_styles()`) at 20
		 * during bootstrap, so it is already registered when this plugin loads
		 * and runs first at the same priority — meaning late block CSS is
		 * printed before we get here. Module translations print at 21, so
		 * anything we print now still receives them.
		 *
		 * `WP_Script_Modules::print_script_module()` guards on its private
		 * `$done` list, so re-running the print is idempotent: modules already
		 * emitted are skipped and only the late arrivals are written. The
		 * import map is emitted separately and already carries the
		 * `@wordpress/interactivity` specifier, so a module printed here still
		 * resolves its dependency.
		 *
		 * This cannot rescue a footer that renders after priority 20 — nothing
		 * can, once the response has streamed past it. It covers the realistic
		 * builder cases and costs nothing on a page with no late blocks.
		 *
		 * One pass covers Pro too: Pro's block view modules land in the same
		 * queue, and Pro cannot run without Free.
		 */
		add_action(
			'wp_footer',
			static function () {
				/**
				 * Filters whether to run the late script-module print pass.
				 *
				 * @param bool $enabled Whether to run the second pass.
				 */
				if ( ! apply_filters( 'wb_listora_late_print_script_modules', true ) ) {
					return;
				}

				wp_script_modules()->print_enqueued_script_modules();

				/*
				 * Same story for stylesheets. Core's `print_late_styles()` runs
				 * inside `wp_print_footer_scripts`, also on `wp_footer` at 20 —
				 * and because core registered it during bootstrap it fires
				 * before we do. A block rendering at exactly priority 20 has
				 * therefore already missed it, so its `block.json` style never
				 * printed and the markup lands unstyled.
				 *
				 * `WP_Styles::do_footer_items()` tracks what it has emitted in
				 * its own `$done` list, so this second call is idempotent in
				 * the same way the module pass above is.
				 */
				print_late_styles();
			},
			20
		);
	}

	/**
	 * Mark all Listora style handles as RTL-aware.
	 *
	 * WordPress auto-loads the `<handle>-rtl.css` sibling on RTL sites only when
	 * the style has `rtl=replace` (or `rtl=true`) data set. We commit `*-rtl.css`
	 * files alongside every hand-authored stylesheet, so this loop turns the swap
	 * on for every Listora-owned handle in one place — no per-enqueue changes.
	 *
	 * Block stylesheets registered through block.json are handled by WP core
	 * automatically (see wp-includes/blocks.php) and don't need this.
	 *
	 * @return void
	 */
	public function mark_styles_rtl(): void {
		global $wp_styles;
		if ( ! ( $wp_styles instanceof \WP_Styles ) ) {
			return;
		}

		foreach ( $wp_styles->registered as $handle => $style_obj ) {
			// Match all Listora-owned handles: listora-* and wb-listora-*.
			if ( 0 !== strpos( $handle, 'listora-' ) && 0 !== strpos( $handle, 'wb-listora-' ) ) {
				continue;
			}

			// Only claim an RTL twin that actually exists.
			//
			// This used to mark EVERY matching handle unconditionally, so on an
			// RTL locale WordPress requested a `-rtl.css` for stylesheets that
			// have none and the browser took a 404 on every pageview. The three
			// theme bridges (buddyx, buddyx-pro, reign) are hand-written
			// overrides with no generated twin, so they 404'd on every RTL load.
			//
			// Checking existence rather than maintaining an exclusion list means
			// a stylesheet added later cannot reintroduce this: it opts into RTL
			// by having a twin, not by being remembered here.
			if ( ! self::has_rtl_twin( $style_obj ) ) {
				continue;
			}

			wp_style_add_data( $handle, 'rtl', 'replace' );
		}
	}

	/**
	 * Whether a registered stylesheet has an `-rtl.css` sibling on disk.
	 *
	 * Returns TRUE when the source cannot be resolved to a local file (a CDN or
	 * filtered URL, say). That is deliberate: the only safe reason to SKIP is
	 * positive proof the twin is missing. Guessing "no" for an unresolvable URL
	 * would silently drop RTL styling, which is a worse failure than a 404.
	 *
	 * @since 1.5.0
	 *
	 * @param object $style_obj Registered `_WP_Dependency`.
	 * @return bool
	 */
	private static function has_rtl_twin( $style_obj ): bool {
		$src = isset( $style_obj->src ) ? (string) $style_obj->src : '';

		if ( '' === $src ) {
			return false;
		}

		// Strip any query string before touching the filesystem.
		$src = (string) strtok( $src, '?' );

		$content_url = content_url();

		if ( '' === $content_url || 0 !== strpos( $src, $content_url ) ) {
			// Not resolvable to a local path — keep the previous behaviour.
			return true;
		}

		$path = WP_CONTENT_DIR . substr( $src, strlen( $content_url ) );

		if ( '.css' !== substr( $path, -4 ) ) {
			return true;
		}

		return file_exists( substr( $path, 0, -4 ) . '-rtl.css' );
	}

	/**
	 * Initialize the workflow system (status manager, cron, notifications).
	 */
	public function init_workflow() {
		new Workflow\Status_Manager();
		new Workflow\Expiration_Cron();
		new Workflow\Notifications();
		new Workflow\Email_Verification();

		// Carries the submission return URL across a WooCommerce checkout so a
		// member who leaves to buy credits can get back to their draft. No-op
		// without WooCommerce.
		Workflow\Return_To_Listing::init();
		new Workflow\Suite_Notifications();
	}

	/**
	 * Sweep duplicate pending recurring AS actions for the known Free hooks.
	 *
	 * Fires on `init` priority 16 (one tick after `init_workflow` at 15 has
	 * had a chance to schedule), so by the time we sweep, any newly-scheduled
	 * hook is already committed. The sweep is a no-op when AS reports a
	 * single pending action per hook (which is the steady state); it cancels
	 * extras only when the cross-request activation race created them.
	 *
	 * See BC 9910208588 + Cron_Scheduler::dedupe_pending() for the rationale.
	 *
	 * @return void
	 */
	public function dedupe_recurring_cron() {
		if ( ! Workflow\Cron_Scheduler::has_action_scheduler() ) {
			return;
		}
		$known_hooks = array(
			'wb_listora_check_expirations',
			'wb_listora_draft_reminder_cron',
			'wb_listora_daily_cleanup',
			'wb_listora_expire_featured',
			'wb_listora_cleanup_unverified_listings',
			// Was 'wb_listora_email_log_prune' — a transposed name that matched
			// nothing, so this job was never actually swept. The real hook is
			// Notifications::PRUNE_HOOK.
			Workflow\Notifications::PRUNE_HOOK,
			'wb_listora_review_reminder_cron',
		);

		// Keyed hook => Action Scheduler group. The group matters: a sweep that
		// looks in the wrong group finds nothing and reports success, which is
		// exactly how Pro's duplicates survived — Free's group is 'wb-listora',
		// Pro schedules into 'wb-listora-pro'.
		$known = array_fill_keys( $known_hooks, Workflow\Cron_Scheduler::GROUP );

		/**
		 * Recurring hooks the duplicate-pending sweep should collapse.
		 *
		 * Filterable because this list may not name a hook it does not own.
		 * Pro schedules its own recurring jobs and Free must not hardcode
		 * `wb_listora_pro_*` strings — INV-1 forbids a runtime dependency on
		 * Pro and the architecture gate enforces it. Pro registers its jobs
		 * here instead.
		 *
		 * A hook absent from this map is never deduplicated, and that failure
		 * is silent rather than loud: the sweep looks like it works because the
		 * hooks it DOES know about stay clean.
		 *
		 * @since 1.4.0
		 *
		 * @param array<string, string> $known Hook name => Action Scheduler group.
		 */
		$known = (array) apply_filters( 'wb_listora_dedupe_recurring_hooks', $known );

		// One call per group, since dedupe_pending_batch() takes a single group
		// for the whole batch.
		$by_group = array();
		foreach ( $known as $hook => $group ) {
			if ( ! is_string( $hook ) || '' === $hook ) {
				continue;
			}
			$group                 = is_string( $group ) && '' !== $group ? $group : Workflow\Cron_Scheduler::GROUP;
			$by_group[ $group ][]  = $hook;
		}

		foreach ( $by_group as $group => $hooks ) {
			Workflow\Cron_Scheduler::dedupe_pending_batch( array_values( array_unique( $hooks ) ), $group );
		}
	}

	/**
	 * Filter WP core sitemap to honor the 'sitemap' feature toggle.
	 *
	 * The Listora CPT registers with public=true so WordPress core
	 * auto-includes it in /wp-sitemap.xml. When the admin disables the
	 * 'sitemap' feature, drop listora_listing from the sitemap providers.
	 *
	 * @param array<string, \WP_Post_Type> $post_types Post types in sitemap.
	 * @return array<string, \WP_Post_Type>
	 */
	public function filter_sitemap_post_types( $post_types ) {
		if ( ! is_array( $post_types ) ) {
			return $post_types;
		}
		if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'sitemap' ) ) {
			// Drop every Listora post type by prefix, exactly as the taxonomy
			// filter below does. Naming `listora_listing` alone made the toggle
			// a half-measure: Pro's `listora_need` kept shipping in
			// /wp-sitemap.xml with the feature switched off (BC 10190575611),
			// and any future Listora CPT would have inherited the same leak.
			foreach ( array_keys( $post_types ) as $type ) {
				if ( 0 === strpos( (string) $type, 'listora_' ) ) {
					unset( $post_types[ $type ] );
				}
			}
		}
		return $post_types;
	}

	/**
	 * Filter WP core sitemap taxonomies to honor the 'sitemap' feature toggle.
	 *
	 * Listora registers public taxonomies (categories, tags, features, etc.),
	 * so core includes them in /wp-sitemap.xml even when the post-type filter
	 * drops listora_listing. When sitemap is off, drop every Listora taxonomy
	 * so the toggle is not a half-measure.
	 *
	 * @param array<string, \WP_Taxonomy> $taxonomies Taxonomies in sitemap.
	 * @return array<string, \WP_Taxonomy>
	 */
	public function filter_sitemap_taxonomies( $taxonomies ) {
		if ( ! is_array( $taxonomies ) ) {
			return $taxonomies;
		}
		if ( function_exists( 'wb_listora_feature_enabled' ) && ! wb_listora_feature_enabled( 'sitemap' ) ) {
			foreach ( array_keys( $taxonomies ) as $tax ) {
				if ( 0 === strpos( (string) $tax, 'listora_' ) ) {
					unset( $taxonomies[ $tax ] );
				}
			}
		}
		return $taxonomies;
	}

	/**
	 * Output Schema.org structured data.
	 */
	/**
	 * Mark the member-dashboard page so the theme's page title can be hidden.
	 *
	 * The dashboard block renders its own heading, so the theme's
	 * `h1.entry-title` is a second, competing title — and it says whatever the
	 * PAGE is called, usually "My Listings", on every tab including Credits
	 * and Claims (BC 10208510032).
	 *
	 * `.listora-page .entry-title { display: none }` already exists for
	 * exactly this, but the dashboard is deliberately NOT wrapped in
	 * `.listora-page` (its flex sidebar layout conflicts with that shell — see
	 * the note in blocks/user-dashboard/render.php). A body class is the way
	 * to reach it without touching the theme.
	 *
	 * @param array<int,string> $classes Body classes.
	 * @return array<int,string>
	 */
	public function dashboard_body_class( $classes ) {
		if ( ! is_array( $classes ) || is_admin() ) {
			return $classes;
		}

		$dashboard_page = (int) wb_listora_get_setting( 'dashboard_page', 0 );

		if ( $dashboard_page > 0 && is_page( $dashboard_page ) ) {
			$classes[] = 'listora-has-dashboard';
		}

		return $classes;
	}

	/**
	 * Title the member dashboard by its ACTIVE tab.
	 *
	 * The dashboard lives on one page, and that page is usually called "My
	 * Listings" — so Credits, Profile, Reviews and Claims all announced
	 * themselves as My Listings in the browser tab, the history entry, the
	 * bookmark and to a screen reader (BC 10208510032).
	 *
	 * This has to be server-side. The tabs are not a client-side toggle: they
	 * navigate to `?tab=…`, so a title set only in JS is overwritten by the
	 * next page load, and never applies at all to a link opened directly, a
	 * bookmark, or a restored session. The JS update stays for the in-page
	 * hash switches; this covers every other way a tab is reached.
	 *
	 * The label comes from the same map the sidebar renders from, so the two
	 * cannot drift apart.
	 *
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public function filter_dashboard_document_title( $parts ) {
		if ( ! is_array( $parts ) || is_admin() ) {
			return $parts;
		}

		$dashboard_page = (int) wb_listora_get_setting( 'dashboard_page', 0 );

		if ( $dashboard_page <= 0 || ! is_page( $dashboard_page ) ) {
			return $parts;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';

		if ( '' === $tab ) {
			return $parts;
		}

		$labels = wb_listora_get_dashboard_tab_labels();

		// An unknown tab keeps the page's own title rather than inventing one.
		if ( ! isset( $labels[ $tab ] ) ) {
			return $parts;
		}

		$parts['title'] = $labels[ $tab ];

		return $parts;
	}

	public function output_schema() {
		if ( ! wb_listora_feature_enabled( 'schema' ) ) {
			return;
		}

		// Defer to any active SEO plugin (Yoast / Rank Math / AIOSEO / SEOPress)
		// — it owns the page JSON-LD. Single canonical detector in Free; mirrors
		// Schema_Generator::output_og_tags / output_canonical.
		if ( function_exists( 'wb_listora_seo_plugin_active' ) && wb_listora_seo_plugin_active() ) {
			return;
		}

		if ( is_singular( 'listora_listing' ) ) {
			$schema = Schema\Schema_Generator::for_listing( get_the_ID() );
			if ( $schema ) {
				echo '<script type="application/ld+json">' . wp_json_encode( $schema->get_data(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
			}
		}
	}

	/**
	 * Handle expired listing display.
	 *
	 * For single listora_listing posts with status listora_expired:
	 * - Sets X-Robots-Tag: noindex header to prevent indexing.
	 * - Does NOT 404 — keeps the page accessible.
	 * - Prepends an "This listing has expired" notice to the_content.
	 */
	public function handle_expired_listing() {
		if ( ! is_singular( 'listora_listing' ) ) {
			return;
		}

		$post = get_queried_object();
		if ( ! $post || 'listora_expired' !== $post->post_status ) {
			return;
		}

		// Prevent search engines from indexing expired listings.
		header( 'X-Robots-Tag: noindex', true );

		// Prepend an expiration notice to the content.
		add_filter( 'the_content', array( $this, 'prepend_expired_notice' ), 1 );
	}

	/**
	 * Prepend an "expired listing" notice to the content.
	 *
	 * Only fires on expired listings (added via handle_expired_listing).
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function prepend_expired_notice( $content ) {
		// Only run once in the main loop.
		if ( ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		// Prevent duplicate notices on subsequent calls.
		static $notice_shown = false;
		if ( $notice_shown ) {
			return $content;
		}
		$notice_shown = true;

		$message = __( 'This listing has expired and may no longer be accurate. Please contact the listing owner for current information.', 'wb-listora' );

		/**
		 * Filter the expired listing notice message.
		 *
		 * @param string $message Notice text.
		 * @param int    $post_id Listing ID.
		 */
		$message = apply_filters( 'wb_listora_expired_listing_notice', $message, get_the_ID() );

		$notice = '<div class="listora-notice listora-notice--warning" role="alert">'
			. '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">'
			. '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>'
			. '<path d="M12 9v4"/><path d="M12 17h.01"/></svg>'
			. '<p>' . esc_html( $message ) . '</p>'
			. '</div>';

		return $notice . $content;
	}

	/**
	 * Use plugin's full-width template for single listings.
	 *
	 * Themes can override by placing single-listora_listing.php in {theme}/wb-listora/.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function listing_single_template( $template ) {
		if ( 'listora_listing' === get_post_type() ) {
			$located = wb_listora_locate_template( 'single-listora_listing.php' );
			if ( $located && file_exists( $located ) ) {
				return $located;
			}
		}
		return $template;
	}

	/**
	 * Replace single listing content with the listing-detail block.
	 *
	 * Runs at priority 5 (before do_blocks at 9) so the block markup
	 * goes through the full WP content pipeline including Interactivity API.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function inject_listing_detail( $content ) {
		if ( ! is_singular( 'listora_listing' ) || ! is_main_query() || ! in_the_loop() ) {
			return $content;
		}

		// Prevent infinite recursion — render.php calls apply_filters('the_content')
		// on the post description, which would re-trigger this filter.
		static $rendering = false;
		if ( $rendering ) {
			return $content;
		}
		$rendering = true;

		// Return block markup — let do_blocks() (priority 9) handle rendering.
		// After do_blocks processes it, $rendering stays true to prevent re-entry.
		add_action(
			'loop_end',
			function () use ( &$rendering ) {
				$rendering = false;
			}
		);

		return '<!-- wp:listora/listing-detail /-->';
	}

	/**
	 * Register the "Listora Full Width" page template.
	 *
	 * @param array $templates Existing page templates.
	 * @return array
	 */
	public function register_page_templates( $templates ) {
		$templates['template-listora-full-width.php'] = __( 'Listora Full Width', 'wb-listora' );
		return $templates;
	}

	/**
	 * Load the plugin page template when selected.
	 *
	 * Themes can override by placing template-listora-full-width.php in {theme}/wb-listora/.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function load_page_template( $template ) {
		if ( is_page() ) {
			$page_template = get_page_template_slug();
			if ( 'template-listora-full-width.php' === $page_template ) {
				$located = wb_listora_locate_template( 'template-listora-full-width.php' );
				if ( $located && file_exists( $located ) ) {
					return $located;
				}
			}
		}
		return $template;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}
