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
		// Translations load on `init` priority 1: loading the text domain during
		// bootstrap triggers WP 6.7+ `_load_textdomain_just_in_time` notices.
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
		Service_Locator::register( 'admin_table', new Admin\Admin_Table() );
		// Automation. Registered before wb_listora_loaded fires so Pro can
		// resolve it at its own boot and declare its triggers into it.
		$triggers = new Automation\Trigger_Registry();
		Service_Locator::register( 'triggers', $triggers );

		/*
		 * Register the empty trigger registry now (so `wb_listora_service( 'triggers' )`
		 * resolves at `wb_listora_loaded`), but populate it on `init` priority 2.
		 * Trigger labels call __(), and doing that at bootstrap triggers WP 6.7+
		 * just-in-time notices and leaves labels in English on translated sites.
		 * Priority 2 is after load_textdomain (1) and before every consumer (5+).
		 * Never call __() at bootstrap.
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
	 * Create the essential pages activation could not create.
	 *
	 * Runs at most once per site: {@see Activator::ensure_essential_pages()}
	 * deletes the flag as soon as it gets a real attempt, and it adopts an
	 * existing page before creating one, so this never produces a duplicate and
	 * never resurrects a page the owner deleted.
	 *
	 * @since 1.8.0
	 *
	 * @return void
	 */
	public function maybe_ensure_pending_pages(): void {
		if ( ! get_option( Activator::PAGES_PENDING_OPTION ) ) {
			return;
		}

		Activator::ensure_essential_pages();
	}

	/**
	 * Flush rewrite rules once after activation, on the next init.
	 *
	 * Activation sets the `wb_listora_flush_rewrites_pending` transient instead
	 * of flushing, because registering CPTs before init triggers WP 6.7+
	 * just-in-time translation notices. At init priority 99 the post types and
	 * taxonomies (priority 5) are registered, so the flush sees every rule.
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
	 * Restrict the media-library query to the current user's own uploads for
	 * non-privileged members.
	 *
	 * The frontend submission form opens `wp.media()`, so without this a member
	 * could browse everyone's uploads. Users with `edit_others_posts` keep the
	 * full library; override with `wb_listora_restrict_media_to_own_uploads`.
	 * This runs on the `query-attachments` AJAX request itself, so tampering
	 * with the client-side picker cannot bypass it.
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

		// Deferred rewrite flush after activation; see maybe_flush_pending_rewrites().
		add_action( 'init', array( $this, 'maybe_flush_pending_rewrites' ), 99 );

		// Essential pages activation could not create (activation runs after
		// `init`, before the page registry fills at priority 5). Priority 6 so
		// the registry is loaded.
		add_action( 'init', array( $this, 'maybe_ensure_pending_pages' ), 6 );

		// Make Listora layout-owning blocks render the same way on every
		// theme by tagging the host page with a `wb-listora-fullwidth` body
		// class consumed by theme-isolation.css.
		( new Core\Theme_Defenses() )->register();
		( new Core\Term_Archives() )->register();

		// Members only see their own uploads in the media picker; this is the
		// server-side guard, the picker scoping in view.js is UX only.
		add_filter( 'ajax_query_attachments_args', array( $this, 'restrict_media_to_own_uploads' ) );

		// Listing type and field registries.
		add_action( 'init', array( Core\Listing_Type_Registry::instance(), 'init' ), 10 );
		add_action( 'init', array( Core\Field_Registry::instance(), 'init' ), 10 );
		add_action( 'init', array( new Core\Meta_Handler(), 'register_meta' ), 10 );
		// Cascade hard-deletes across every Free-owned listing-scoped table and
		// fire the Pro extension point. Index tables stay with Search_Indexer,
		// which also handles trash.
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

		// Turns a page whose feature is gone into a 404 rather than a blank 200.
		Core\Page_Availability::init();

		// Drop a page mapping when its page is permanently deleted, so the option
		// stops naming an ID that resolves to nothing.
		add_action( 'deleted_post', array( Core\Page_Registry::class, 'forget_deleted_page' ) );

		// Purge a listing's BuddyNext space-showcase links when the business is
		// permanently deleted, so no space keeps a row pointing at nothing. Trash
		// needs no cleanup: the showcase hydrates only published listings, so a
		// trashed one simply drops out and re-appears if restored.
		add_action(
			'before_delete_post',
			static function ( $post_id ) {
				if ( 'listora_listing' === get_post_type( (int) $post_id ) ) {
					Core\Space_Listings_Model::remove_all_for_listing( (int) $post_id );
				}
			}
		);

		// Capability-checked delivery for claim proof documents.
		Core\Claim_Proofs::init();

		// Every Listora Site Health check, registered from one place.
		Core\Site_Health::init();

		// Contact-form leads: retention cleanup and privacy export / erase.
		Core\Leads::init();

		// Capability + attachment-ownership guards on EVERY listing write route,
		// including WordPress's own /wp/v2/listings.
		Rest\Listing_Write_Guards::init();

		// Free contact-form on listing detail. Stands down when Pro's
		// Lead_Form feature toggle takes over (see Contact_Form::should_render()).
		Contact_Form::init();
		Core\Leads_Dashboard_Tab::init();

		// Per-criterion stars under each review. On the action rather than in
		// the templates, so theme overrides get it too.
		add_action( 'wb_listora_review_after_content', 'wb_listora_render_review_criteria', 5 );

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

		// One-shot repair of HTML-entity-encoded term names from older imports.
		// Guarded by the wb_listora_term_entity_repair_done option; admin_init so
		// the frontend never pays for it. The closure discards the method's int
		// return because action callbacks must not return a value.
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
		add_filter( 'rest_request_before_callbacks', array( \WBListora\Rate_Limiter::class, 'limit_public_reads' ), 10, 3 );
		add_filter( 'rest_post_dispatch', array( \WBListora\Rate_Limiter::class, 'retry_after_header' ) );

		// Development builds (version ending -dev): every Listora asset URL carries
		// its file's modification time, so a tester never runs yesterday's cached JS.
		if ( str_ends_with( WB_LISTORA_VERSION, '-dev' ) ) {
			foreach ( array( 'script_loader_src', 'style_loader_src', 'script_module_loader_src' ) as $hook ) {
				add_filter( $hook, 'wb_listora_dev_asset_version' );
			}
		}

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

		// One-shot duplicate-recurring-action sweep: catches the cross-request
		// race where two requests both pass as_next_scheduled_action before
		// either commits. Priority 16, after init_workflow has scheduled.
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
		// A feature toggle must gate the sitemap as well as the UI.
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
		// A member who never chose a public name gets no profile link: profile
		// URLs (BuddyPress, author archives) are built from the login.
		add_filter( 'wb_listora_member_profile_url', array( $this, 'hide_unnamed_member_profile_url' ), 20, 2 );
		// Listing embeds name the listing's public owner, never the account login.
		add_filter( 'oembed_response_data', array( $this, 'listing_oembed_author' ), 10, 2 );
		// Feeds and theme bylines on listings print the same public owner name.
		add_filter( 'the_author', array( $this, 'listing_public_author' ) );
	}

	/**
	 * No profile link for a member whose public name is empty.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url     Resolved profile URL.
	 * @param int    $user_id Member the URL points at.
	 * @return string
	 */
	public function hide_unnamed_member_profile_url( $url, $user_id ) {
		if ( '' === (string) $url ) {
			return $url;
		}
		$user = get_userdata( (int) $user_id );
		return $user && '' !== wb_listora_user_public_name( $user ) ? $url : '';
	}

	/**
	 * On the front end and in feeds, a listing's author is its public owner name.
	 *
	 * @since 2.0.0
	 *
	 * @param string|null $name Author display name.
	 * @return string|null
	 */
	public function listing_public_author( $name ) {
		$post = get_post();
		if ( is_admin() || ! $post || 'listora_listing' !== $post->post_type ) {
			return $name;
		}
		return wb_listora_get_listing_owner_name( $post->ID );
	}

	/**
	 * Listing oEmbed: author is the public owner name, with no author archive link.
	 *
	 * @since 2.0.0
	 *
	 * @param array<string, mixed> $data oEmbed response data.
	 * @param \WP_Post             $post Embedded post.
	 * @return array<string, mixed>
	 */
	public function listing_oembed_author( $data, $post ) {
		if ( ! $post instanceof \WP_Post || 'listora_listing' !== $post->post_type ) {
			return $data;
		}
		unset( $data['author_name'], $data['author_url'] );
		$name = wb_listora_get_listing_owner_name( $post->ID );
		if ( '' !== $name ) {
			$data['author_name'] = $name;
			$data['author_url']  = get_permalink( $post );
		}
		return $data;
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
				'exporter_friendly_name' => wb_listora_brand_name(),
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
				'eraser_friendly_name' => wb_listora_brand_name(),
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
			new REST\Leads_Controller(),
			// Self-serve account lifecycle: POST /me/deactivate, POST
			// /me/reactivate, DELETE /me. Apple App Store Guideline 5.1.1(v)
			// requires an in-app account-deletion path, so these ship in Free —
			// the requirement applies to every Listora-backed app, not only
			// Pro-licensed ones.
			new REST\Account_Controller(),
			// POST /auth/app-password — the mobile app's first credential.
			// Public by necessity; every guard lives in Auth\App_Credentials.
			new REST\Auth_Controller(),
			// Listing <-> BuddyNext space showcase: submit / approve / reject /
			// showcase / queue. Space authority is answered by BuddyNext via the
			// wb_listora_user_can_moderate_space / wb_listora_user_can_view_space filters.
			new REST\Space_Listings_Controller(),
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
		 * Script modules need their text domain wired by hand:
		 * `register_block_type()` only sets translations for classic scripts,
		 * not `viewScriptModule`. The JSON is keyed on md5() of the built path,
		 * so it must come from `bin/build-i18n-json.mjs`, not `wp i18n make-json`.
		 * `wp_set_script_module_translations()` is WP 7.0+; on 6.9 these strings
		 * stay English.
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

			// Each release serves its block CSS under a new URL.
			wb_listora_version_block_styles( $block_type, WB_LISTORA_VERSION );

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
		 * Core prints script modules once on `wp_footer` priority 10. A footer
		 * built by a page builder during `wp_footer` enqueues after that, and the
		 * block loses its view module, store and stylesheet. Priority 20 runs
		 * after core's late styles and before module translations (21). The
		 * print is idempotent (core skips modules already done). Covers Pro
		 * modules too; footers rendered after priority 20 cannot be rescued.
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
			// Marking EVERY matching handle unconditionally makes WordPress request a
			// `-rtl.css` for stylesheets that have none, and the browser takes a 404 on
			// every pageview. The three theme bridges (buddyx, buddyx-pro, reign) are
			// hand-written overrides with no generated twin, so they would 404 on every
			// RTL load.
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

		// Confirmed email changes for the member dashboard.
		Auth\Email_Change::init();
		new Workflow\Suite_Notifications();
	}

	/**
	 * Sweep duplicate pending recurring AS actions for the known Free hooks.
	 *
	 * Runs on `init` priority 16, after `init_workflow` (15) has scheduled.
	 * No-op in the steady state of one pending action per hook. See
	 * Cron_Scheduler::dedupe_pending().
	 *
	 * @return void
	 */
	public function dedupe_recurring_cron() {
		if ( ! Workflow\Cron_Scheduler::is_scheduling_request() || ! Workflow\Cron_Scheduler::has_action_scheduler() ) {
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
			$group                = is_string( $group ) && '' !== $group ? $group : Workflow\Cron_Scheduler::GROUP;
			$by_group[ $group ][] = $hook;
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
			// Drop every Listora post type by prefix, as the taxonomy filter
			// below does, so Pro's and future CPTs leave the sitemap too.
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
	 * Mark the member-dashboard page so the theme's page title can be hidden.
	 *
	 * The dashboard block renders its own heading, so the theme's
	 * `h1.entry-title` would be a second, wrong title. The dashboard is not
	 * wrapped in `.listora-page` (its sidebar layout conflicts with it), so a
	 * body class is how the existing hide rule reaches it.
	 *
	 * @param array<int,string> $classes Body classes.
	 * @return array<int,string>
	 */
	public function dashboard_body_class( $classes ) {
		if ( ! is_array( $classes ) || is_admin() ) {
			return $classes;
		}

		$dashboard_page = \WBListora\Core\Page_Registry::get_id( 'dashboard' );

		if ( $dashboard_page > 0 && is_page( $dashboard_page ) ) {
			$classes[] = 'listora-has-dashboard';
		}

		return $classes;
	}

	/**
	 * Title the member dashboard by its ACTIVE tab.
	 *
	 * Server-side because tabs navigate to `?tab=…`: a JS-only title is lost
	 * on direct links, bookmarks and reloads. The label comes from the same
	 * map the sidebar renders from.
	 *
	 * @param array<string,string> $parts Title parts.
	 * @return array<string,string>
	 */
	public function filter_dashboard_document_title( $parts ) {
		if ( ! is_array( $parts ) || is_admin() ) {
			return $parts;
		}

		$dashboard_page = \WBListora\Core\Page_Registry::get_id( 'dashboard' );

		if ( $dashboard_page <= 0 || ! is_page( $dashboard_page ) ) {
			return $parts;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation state.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : '';

		if ( '' === $tab ) {
			return $parts;
		}

		$labels = wb_listora_get_dashboard_tab_labels();

		// An unknown or switched-off tab keeps the page's own title; the
		// dashboard shows Overview for it.
		if ( ! isset( $labels[ $tab ] ) || ! wb_listora_dashboard_tab_available( $tab ) ) {
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
