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

		// Workflow — deferred to init.
		add_action( 'init', array( $this, 'init_workflow' ), 15 );

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

		$block_dirs = glob( $blocks_dir . '*/block.json' );

		foreach ( $block_dirs as $block_json ) {
			register_block_type( dirname( $block_json ) );
		}
	}

	/**
	 * Initialize the workflow system (status manager, cron, notifications).
	 */
	public function init_workflow() {
		new Workflow\Status_Manager();
		new Workflow\Expiration_Cron();
		new Workflow\Notifications();
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
	 * Use plugin's full-width template for single listings.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function listing_single_template( $template ) {
		if ( 'listora_listing' === get_post_type() ) {
			$plugin_template = WB_LISTORA_PLUGIN_DIR . 'templates/single-listora_listing.php';
			if ( file_exists( $plugin_template ) ) {
				return $plugin_template;
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
	 * @param string $template Template path.
	 * @return string
	 */
	public function load_page_template( $template ) {
		if ( is_page() ) {
			$page_template = get_page_template_slug();
			if ( 'template-listora-full-width.php' === $page_template ) {
				$plugin_template = WB_LISTORA_PLUGIN_DIR . 'templates/template-listora-full-width.php';
				if ( file_exists( $plugin_template ) ) {
					return $plugin_template;
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
