<?php
/**
 * Main Plugin orchestrator.
 *
 * @package WBListora
 */

namespace WBListora;

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
		$this->load_textdomain();
		$this->init_core();
		$this->init_hooks();

		/**
		 * Fires after WB Listora is fully loaded.
		 * Pro and extensions hook in here.
		 */
		do_action( 'wb_listora_loaded' );
	}

	/**
	 * Load plugin text domain for translations.
	 */
	private function load_textdomain() {
		load_plugin_textdomain(
			'wb-listora',
			false,
			dirname( WB_LISTORA_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Initialize core subsystems.
	 */
	private function init_core() {
		// Register CPT and Taxonomies early.
		add_action( 'init', array( new Core\Post_Types(), 'register' ), 5 );
		add_action( 'init', array( new Core\Taxonomies(), 'register' ), 5 );
		add_action( 'init', array( new Core\Capabilities(), 'register' ), 5 );

		// Listing type and field registries.
		add_action( 'init', array( Core\Listing_Type_Registry::instance(), 'init' ), 10 );
		add_action( 'init', array( Core\Field_Registry::instance(), 'init' ), 10 );
		add_action( 'init', array( new Core\Meta_Handler(), 'register_meta' ), 10 );

		// Dashboard cache-busting hooks.
		Core\Listing_Data::init();

		// Per-role listing limits + credits overflow enforcement.
		Core\Listing_Limits::init();

		// Featured lifecycle — duration, expiration cron, is_featured helper.
		Core\Featured::init();
	}

	/**
	 * Initialize hooks for all subsystems.
	 */
	private function init_hooks() {
		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Search indexer.
		add_action( 'init', array( $this, 'init_search' ), 15 );

		// Admin.
		if ( is_admin() ) {
			add_action( 'init', array( $this, 'init_admin' ), 20 );
		}

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

		// Expired listings — noindex header + content notice.
		add_action( 'template_redirect', array( $this, 'handle_expired_listing' ) );

		// Schema/SEO.
		add_action( 'wp_head', array( $this, 'output_schema' ), 5 );

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

		$block_dirs = glob( $blocks_dir . '*/block.json' );

		foreach ( $block_dirs as $block_json ) {
			register_block_type( dirname( $block_json ) );
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

		foreach ( $wp_styles->registered as $handle => $_obj ) {
			// Match all Listora-owned handles: listora-* and wb-listora-*.
			if ( 0 === strpos( $handle, 'listora-' ) || 0 === strpos( $handle, 'wb-listora-' ) ) {
				wp_style_add_data( $handle, 'rtl', 'replace' );
			}
		}
	}

	/**
	 * Initialize the workflow system (status manager, cron, notifications).
	 */
	public function init_workflow() {
		new Workflow\Status_Manager();
		new Workflow\Expiration_Cron();
		new Workflow\Notifications();
		new Workflow\Email_Verification();
	}

	/**
	 * Output Schema.org structured data.
	 */
	public function output_schema() {
		if ( ! wb_listora_get_setting( 'enable_schema' ) ) {
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
