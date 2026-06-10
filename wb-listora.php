<?php
/**
 * Plugin Name: WB Listora
 * Plugin URI:  https://wblistora.com
 * Description: The complete WordPress directory plugin. Create any type of listing directory — business, restaurant, hotel, real estate, jobs, events, and more.
 * Version:     1.2.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author:      WBCom
 * Author URI:  https://wblistora.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wb-listora
 * Domain Path: /languages
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'WB_LISTORA_VERSION', '1.2.0' );
define( 'WB_LISTORA_DB_VERSION', '1.4.0' );
define( 'WB_LISTORA_PLUGIN_FILE', __FILE__ );
define( 'WB_LISTORA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WB_LISTORA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WB_LISTORA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WB_LISTORA_TABLE_PREFIX', 'listora_' );
define( 'WB_LISTORA_META_PREFIX', '_listora_' );
define( 'WB_LISTORA_REST_NAMESPACE', 'listora/v1' );
define( 'WB_LISTORA_INTERACTIVITY_NS', 'listora/directory' );

// Minimum requirements.
define( 'WB_LISTORA_MIN_PHP', '7.4' );
define( 'WB_LISTORA_MIN_WP', '6.9' );

/*
 * Action Scheduler — bundled in Free so it is available to BOTH plugins.
 *
 * Under the upscale model Free is mandatory whenever Pro is active, so shared
 * infrastructure belongs here (same as the Wbcom Credits SDK below). Free's
 * Workflow\Cron_Scheduler routes every recurring job through Action Scheduler;
 * bundling it here means Free-only sites get AS too instead of silently
 * falling back to WP-Cron. Pro's loader is guarded by the same
 * function_exists() check, so it defers to this copy at runtime (Free loads
 * before Pro). Loaded as early as possible so AS's version registry picks the
 * highest available copy across all active plugins.
 */
if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
	$wb_listora_action_scheduler = WB_LISTORA_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
	if ( file_exists( $wb_listora_action_scheduler ) ) {
		require_once $wb_listora_action_scheduler;
		// Marker so Pro defers to this copy instead of registering its own
		// (Free loads before Pro), keeping a single authoritative AS source.
		define( 'WB_LISTORA_AS_FROM_FREE', true );
	}
	unset( $wb_listora_action_scheduler );
}

/**
 * Check environment requirements before loading.
 *
 * @return bool
 */
function wb_listora_check_requirements() {
	$errors = array();

	if ( version_compare( PHP_VERSION, WB_LISTORA_MIN_PHP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required PHP version, 2: Current PHP version */
			__( 'WB Listora requires PHP %1$s or higher. You are running PHP %2$s.', 'wb-listora' ),
			WB_LISTORA_MIN_PHP,
			PHP_VERSION
		);
	}

	if ( version_compare( get_bloginfo( 'version' ), WB_LISTORA_MIN_WP, '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Required WP version, 2: Current WP version */
			__( 'WB Listora requires WordPress %1$s or higher. You are running %2$s.', 'wb-listora' ),
			WB_LISTORA_MIN_WP,
			get_bloginfo( 'version' )
		);
	}

	if ( ! empty( $errors ) ) {
		add_action(
			'admin_notices',
			function () use ( $errors ) {
				foreach ( $errors as $error ) {
					printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $error ) );
				}
			}
		);
		return false;
	}

	return true;
}

/**
 * Load the Composer autoloader.
 *
 * @return bool
 */
function wb_listora_load_autoloader() {
	$autoloader = WB_LISTORA_PLUGIN_DIR . 'vendor/autoload.php';

	// Composer's autoloader is an OPTIONAL fast-path optimization, never a
	// hard requirement. The dist zip ships a pre-built vendor/autoload.php so
	// customers never run `composer install`; but if vendor/ is ever absent
	// (a stripped package, a fresh git clone with no `composer install`, or
	// the no-composer composer-free proof) the `file_exists` guard means its
	// absence can NEVER fatal the site. The kebab-case fallback registered
	// below resolves every WBListora\ class on its own, so the plugin boots
	// either way.
	if ( file_exists( $autoloader ) ) {
		require_once $autoloader;
	}

	// Always register the kebab-case fallback so a stale Composer classmap
	// (e.g. a new class added without re-running `composer dump-autoload`)
	// — or a missing vendor/ entirely — cannot fatal the site. Composer's
	// autoloader handles known classes on the fast path when present; this
	// acts as the authoritative composer-free resolver for the rest.
	spl_autoload_register( 'wb_listora_autoload' );

	return true;
}

/**
 * PSR-4 fallback autoloader for when Composer is not available.
 *
 * Maps WBListora\ namespace to includes/ directory.
 * Converts PascalCase class names to WordPress file naming: Class_Name → class-class-name.php
 *
 * @param string $class_name Fully qualified class name.
 */
function wb_listora_autoload( $class_name ) {
	$namespace = 'WBListora\\';

	if ( 0 !== strpos( $class_name, $namespace ) ) {
		return;
	}

	$relative_class = substr( $class_name, strlen( $namespace ) );
	$parts          = explode( '\\', $relative_class );
	$class_file     = array_pop( $parts );

	// Convert PascalCase to kebab-case: PostTypes → post-types.
	$class_file = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $class_file ) );
	$class_file = str_replace( '_', '-', $class_file );
	$class_file = 'class-' . $class_file . '.php';

	// Build subdirectory path. Each namespace segment is also kebab-cased
	// so a compound namespace like `ImportExport` resolves to the
	// `import-export/` directory, not `importexport/`. This matters for
	// classes like \WBListora\ImportExport\Migration_Base whose file lives
	// at includes/import-export/class-migration-base.php (card 9867184397).
	$subdir = '';
	if ( ! empty( $parts ) ) {
		$subdir_parts = array_map(
			static function ( $part ) {
				$part = preg_replace( '/([a-z])([A-Z])/', '$1-$2', $part );
				$part = str_replace( '_', '-', $part );
				return strtolower( $part );
			},
			$parts
		);
		$subdir       = implode( '/', $subdir_parts ) . '/';
	}

	// Irregular-filename aliases: a few legacy files keep a multi-letter
	// acronym un-split on disk, so the lower→Upper kebab rule above computes
	// the wrong path. Composer's classmap resolved these when vendor/ was
	// present; this explicit map keeps the no-composer path complete. Keep it
	// tiny — add an entry only when a class file genuinely cannot follow the
	// kebab convention.
	static $aliases = array(
		'WBListora\\ImportExport\\GeoJSON_Importer' => 'import-export/class-geojson-importer.php',
	);
	if ( isset( $aliases[ $class_name ] ) ) {
		$alias_file = WB_LISTORA_PLUGIN_DIR . 'includes/' . $aliases[ $class_name ];
		if ( file_exists( $alias_file ) ) {
			require_once $alias_file;
		}
		return;
	}

	$file = WB_LISTORA_PLUGIN_DIR . 'includes/' . $subdir . $class_file;

	if ( file_exists( $file ) ) {
		require_once $file;
	}
}

/**
 * Check if WB Listora Pro is active.
 *
 * @return bool
 */
function wb_listora_is_pro_active() {
	return defined( 'WB_LISTORA_PRO_VERSION' ) && did_action( 'wb_listora_pro_loaded' );
}

/**
 * Resolve a registered Listora service from the global service locator.
 *
 * This is the public extension surface for Pro and 3rd-party code. The
 * returned object implements one of the {@see \WBListora\Contracts}
 * interfaces.
 *
 * Pro must consume Free only via this function and the Contracts namespace —
 * never via concrete \WBListora\Core\* classes — so that internal renames in
 * Free do not break Pro.
 *
 * Always null-check the result. Pro may be installed against an older Free
 * build that has not yet registered the service:
 *
 *     $registry = wb_listora_service( 'listing_types' );
 *     if ( ! $registry ) {
 *         return; // Free is missing or too old — fail soft.
 *     }
 *     $type = $registry->get_for_post( $post_id );
 *
 * Available services: 'listing_types', 'featured', 'meta', 'services',
 * 'search_indexer', 'search_engine', 'geo_query', 'block_css'.
 *
 * @param string $name Service short name.
 * @return object|null
 */
function wb_listora_service( $name ) {
	if ( ! class_exists( '\\WBListora\\Service_Locator' ) ) {
		return null;
	}
	return \WBListora\Service_Locator::get( $name );
}

/**
 * Get the URL where users buy credits.
 *
 * Always resolves to the dashboard Credits tab when the dashboard page is
 * configured. Falls back to the legacy `wb_listora_credit_purchase_url`
 * option if set (for themes/devs that want to override with a custom page).
 *
 * The option has no UI anymore — the dashboard Credits tab is the single
 * source of truth — but the option + filter remain for extension points.
 *
 * @return string
 */
function wb_listora_get_credits_purchase_url() {
	// Legacy override: if an admin/theme has explicitly set a custom URL or
	// page ID, respect it.
	$override = get_option( 'wb_listora_credit_purchase_url', '' );
	if ( ! empty( $override ) ) {
		if ( is_numeric( $override ) ) {
			$override = (string) get_permalink( (int) $override );
		} else {
			$override = (string) $override;
		}
		/** This filter is documented in wb-listora.php */
		return (string) apply_filters( 'wb_listora_credits_purchase_url', $override );
	}

	// Auto-resolve: dashboard page with ?tab=credits fragment.
	$dashboard_page_id = (int) wb_listora_get_setting( 'dashboard_page', 0 );
	$url               = '';

	if ( $dashboard_page_id > 0 ) {
		$permalink = get_permalink( $dashboard_page_id );
		if ( $permalink ) {
			$url = add_query_arg( 'tab', 'credits', $permalink ) . '#listora-credit-packs';
		}
	}

	if ( '' === $url ) {
		// Last-ditch: try to find a page at /dashboard/.
		$page = get_page_by_path( 'dashboard' );
		if ( $page instanceof \WP_Post ) {
			$permalink = get_permalink( $page );
			if ( $permalink ) {
				$url = add_query_arg( 'tab', 'credits', $permalink ) . '#listora-credit-packs';
			}
		}
	}

	/**
	 * Filter the "buy credits" URL used across the directory.
	 *
	 * @param string $url Fully-resolved purchase URL.
	 */
	return (string) apply_filters( 'wb_listora_credits_purchase_url', $url );
}

/**
 * Recompute the rating aggregates (avg_rating / review_count) for one listing.
 *
 * Canonical public entry point (f7a contract) delegating to
 * \WBListora\Core\Listing_Data::recompute_rating_aggregate(). Lives HERE in
 * the always-loaded main file - not in the autoloaded class file - so
 * function_exists() guards (e.g. the privacy eraser at
 * includes/privacy/class-privacy-eraser.php) see it without first triggering
 * the autoloader. Wave-7 verifier finding: the eraser silently skipped the
 * recompute because this function was documented but never defined.
 *
 * @param int $listing_id Listing post ID.
 * @return void
 */
function wb_listora_recompute_listing_rating( $listing_id ) {
	\WBListora\Core\Listing_Data::recompute_rating_aggregate( (int) $listing_id );
}

/**
 * Get a plugin setting value.
 *
 * Reads settings from the `wb_listora_settings` option once per request
 * and caches the array in a function-static. The cache is invalidated by
 * \WBListora\Core\Cache::on_settings_updated() (hooked to
 * `update_option_wb_listora_settings`) which calls this function with
 * `$force_reload = true` to re-prime in the same request.
 *
 * @param string|null $key          Setting key. Pass `null` together with
 *                                  `$force_reload = true` to refresh the
 *                                  static cache without reading any value.
 * @param mixed       $default_value      Default value if setting not found.
 * @param bool        $force_reload When true, re-read the option and
 *                                  refresh the static cache before
 *                                  resolving the requested key.
 * @return mixed
 */
function wb_listora_get_setting( $key = null, $default_value = null, $force_reload = false ) {
	static $settings = null;

	if ( null === $settings || $force_reload ) {
		$settings = get_option( 'wb_listora_settings', array() );
	}

	// `wb_listora_get_setting( null, null, true )` is the documented way
	// to invalidate the static cache after settings change. No key to
	// resolve in that case — just return early.
	if ( null === $key ) {
		return null;
	}

	$default_values = wb_listora_get_default_settings();

	if ( null === $default_value && isset( $default_values[ $key ] ) ) {
		$default_value = $default_values[ $key ];
	}

	$value = isset( $settings[ $key ] ) ? $settings[ $key ] : $default_value;

	// Per-key extension hooks. A small set of canonical settings expose a
	// named filter so Pro (and site owners) can override the resolved value
	// at runtime without having to write to the option. `wb_listora_map_provider`
	// is the documented Pro-Google-Maps extension point referenced in
	// plans/free/11-maps.md and plans/free/41-free-vs-pro-definitive.md.
	// See plan/2026-04-30-cross-ref-orphans.md (O3).
	if ( 'map_provider' === $key ) {
		/**
		 * Filter the resolved map provider.
		 *
		 * Pro's Google_Maps feature only attaches a listener after it has
		 * verified the option already reads `google` AND the API key is set
		 * — so this filter is purely a confirmation/override surface for
		 * future listeners (e.g. a site owner forcing OSM in a sub-region).
		 *
		 * @param string $value Resolved provider key (e.g. 'osm', 'google').
		 */
		$value = apply_filters( 'wb_listora_map_provider', $value );
	}

	return $value;
}

/**
 * Get default plugin settings.
 *
 * @return array
 */
/**
 * Log a debug message to PHP's error log when Listora's debug-logging
 * setting is on.
 *
 * Site owners flip this from Settings → General → "Enable debug logging"
 * without having to toggle WP_DEBUG globally. WP_DEBUG remains a force-on
 * fallback so devs running with debug already enabled keep seeing logs.
 *
 * @param string $message Debug message (will be prefixed with [wb-listora]).
 * @return void
 */
function wb_listora_log( $message ) {
	$enabled = (bool) wb_listora_get_setting( 'debug_logging', false );
	if ( ! $enabled && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
		return;
	}
	error_log( '[wb-listora] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

function wb_listora_get_default_settings() {
	return array(
		'per_page'                       => 20,
		'default_type'                   => 'business',
		'default_sort'                   => 'featured',
		'default_country'                => '',
		'listing_slug'                   => 'listing',
		// Pagination mode for listing-grid block. Free renders 'pagination'
		// (numbered) by default; Pro's Infinite_Scroll feature adds 'load_more'
		// and 'infinite_scroll'. The key must be declared here — Settings_Page::sanitize()
		// only persists keys present in $default_values, so a Pro write of a key
		// missing from this map is silently dropped.
		'pagination_type'                => 'pagination',
		'category_slug'                  => 'listing-category',
		'location_slug'                  => 'listing-location',
		'feature_slug'                   => 'listing-feature',
		'tag_slug'                       => 'listing-tag',
		'currency'                       => 'USD',
		'distance_unit'                  => 'km',
		// Directory listings are LIFETIME by default — the realistic
		// model for most directories (Yelp / TripAdvisor / Yellow Pages
		// style). Time-bound listings are a niche (classifieds, job
		// posts, event rentals) and must be opted into per plan by
		// setting `_listora_plan_duration_days > 0` on a `listora_plan`.
		// When `enable_expiration` is false the expiration cron is a
		// no-op (class-expiration-cron.php:39); when `default_expiration`
		// is 0 the status-manager skips setting an expiry meta
		// (class-status-manager.php:142 — `if ( $days > 0 )`).
		'enable_expiration'              => false,
		'default_expiration'             => 0,
		'renewal_window_days'            => 7,
		'default_renewal_duration_days'  => 0,
		'default_renewal_credit_cost'    => 0,
		'map_provider'                   => 'osm',
		'map_default_lat'                => 40.7128,
		'map_default_lng'                => -74.0060,
		'map_default_zoom'               => 12,
		'map_clustering'                 => true,
		'map_search_on_drag'             => true,
		'map_max_markers'                => 500,
		'google_maps_key'                => '',
		'moderation'                     => 'manual',
		// Default presentation of the listing-submission block when its
		// layoutMode attribute is 'default': 'wizard' (step-by-step with
		// progress bar) or 'single_form' (every field on one page). A block
		// whose author explicitly set wizard/single-form keeps that choice.
		'submission_form_style'          => 'wizard',
		'max_upload_size'                => 5,
		'max_gallery_images'             => 20,
		'submission_page'                => 0,
		'dashboard_page'                 => 0,
		'delete_on_uninstall'            => false,
		'search_cache_ttl'               => 15,
		'facet_cache_ttl'                => 30,
		'debug_logging'                  => false,
		'setup_complete'                 => false,
		'captcha_provider'               => 'none',
		'captcha_site_key'               => '',
		'captcha_secret_key'             => '',
		'enable_guest_submission'        => false,
		'guest_email_verification'       => true,
		'verification_link_expiry_hours' => 24,
		'unverified_listings_action'     => 'trash',
		'unverified_listings_max_days'   => 7,
		'featured_credit_cost'           => 0,
		'featured_duration_days'         => 30,
		'default_listing_credit_cost'    => 0,
		'listing_limits_period'          => 'lifetime',
		'listing_beyond_limit_behavior'  => 'block',
		// Per-role listing limits — empty map means "no role-specific
		// override; everyone gets `listing_limits_default`". Site owners
		// populate this via Settings → Submissions → Listing Limits.
		'listing_limits_per_role'        => array(),
		'listing_limits_default'         => -1,
		// Reviews — nested settings group. Defaults match the most common
		// directory-site posture: anyone (logged-in or guest with email)
		// can review, one review per user per listing, owners can reply,
		// minimum 20-character reviews to filter spam, NOT auto-approved
		// (admin reviews everything in Listora → Reviews).
		'reviews'                        => array(
			'require_login'   => true,
			'one_per_listing' => true,
			'allow_reply'     => true,
			'min_length'      => 20,
			'auto_approve'    => false,
		),
		// Per-event email notification toggles — keys are event IDs from
		// Notifications class (e.g. 'listing_submitted', 'review_received').
		// Empty default means "fire every event"; site owners can opt out
		// per-event from Settings → Notifications.
		'notifications'                  => array(),
	);
}

// Load autoloader immediately (needed for activation hooks).
wb_listora_load_autoloader();

// ─── Wbcom Credits SDK ───
// Register with the shared credit engine BEFORE including the SDK.
// Pattern: same as WP Career Board, EDD license library.
add_action(
	'wbcom_credits_sdk_registry',
	static function ( \Wbcom\Credits\Registry $registry ): void {
		$registry->register(
			array(
				'slug'      => 'wb-listora',
				// SDK uses this to namespace its ledger table: {wp_prefix}{prefix}_credit_ledger.
				// Use 'listora' (no trailing underscore — SDK adds its own separator).
				'prefix'    => 'listora',
				'version'   => WB_LISTORA_VERSION,
				'file'      => WB_LISTORA_PLUGIN_FILE,
				'user_type' => 'listing_owner',
				'consumers' => array(
					array(
						'id'        => 'listing_submission',
						'label'     => __( 'Listing Submission', 'wb-listora' ),
						'cost'      => static function ( int $item_id ): int {
							// When a Pro pricing plan is in play — successful
							// activation OR a paused one waiting for credits —
							// Pro's Pricing_Plans owns the hold → commit
							// lifecycle. Free's consumer must return 0 or the
							// vendor gets double-charged on success AND ends
							// up with a stuck hold on the paused path
							// (Free's consumer settle hook
							// `wb_listora_after_approve_listing` never fires
							// for a listing that's in listora_payment status).
							//
							// Hook order in submit_listing(): Pro's plan
							// handler fires on `wb_listora_listing_submitted`
							// BEFORE Free's SDK consumer fires on
							// `wb_listora_after_create_listing`. By the time
							// this callback runs Pro has already set either
							// _listora_plan_id (success) or
							// _listora_pending_plan_id (paused). Checking
							// both meta keys covers every Pro outcome.
							//
							// Forensic record: ledger trace on 2026-05-13
							// caught two regressions this guard prevents:
							//   - listing #1311 double-charged 100cr against
							//     a 50cr Featured plan.
							//   - listing #1335 (paused on insufficient
							//     credits) accumulated a stuck 5cr hold the
							//     vendor couldn't see released because the
							//     SDK consumer's refund hook never fires for
							//     listings that go to listora_payment.
							$plan_id = (int) get_post_meta( $item_id, '_listora_plan_id', true );
							if ( $plan_id > 0 ) {
								return 0;
							}
							$pending = (int) get_post_meta( $item_id, '_listora_pending_plan_id', true );
							if ( $pending > 0 ) {
								return 0;
							}
							if ( 'listora_payment' === get_post_status( $item_id ) ) {
								return 0;
							}

							// Listing-limit overflow: when the site owner has
							// configured `listing_beyond_limit_behavior=credits`
							// (the "1 free, then charge" flexibility model),
							// listings that push the vendor past their per-role
							// cap cost `overflow_credit_cost` per listing
							// instead of the default. Listing_Limits::enforce_on_create
							// has already permitted the submission by this
							// point — it gates but doesn't charge. The charge
							// happens here so a single hold/deduct cycle
							// covers the right amount.
							//
							// Counting model: at after_create_listing time the
							// new post is already counted, so listings up to
							// the cap (count == cap) are in-tier, count > cap
							// is overflow.
							$author_id = (int) get_post_field( 'post_author', $item_id );
							if ( $author_id > 0 && class_exists( '\\WBListora\\Core\\Listing_Limits' ) ) {
								$behavior = \WBListora\Core\Listing_Limits::get_beyond_limit_behavior();
								if ( 'credits' === $behavior ) {
									$cap   = \WBListora\Core\Listing_Limits::get_user_limit( $author_id );
									$count = \WBListora\Core\Listing_Limits::get_user_count( $author_id );
									if ( $cap >= 0 && $count > $cap ) {
										$overflow = (int) get_option( \WBListora\Core\Listing_Limits::OVERFLOW_COST_OPTION, 0 );
										if ( $overflow > 0 ) {
											return $overflow;
										}
									}
								}
							}

							// In-tier listing (within cap or no cap) — use the
							// site-wide default per-listing credit cost. Set
							// to 0 for "list free, everyone gets X free
							// listings before overflow kicks in" directories.
							return (int) wb_listora_get_setting( 'default_listing_credit_cost', 0 );
						},
						// SDK's on_hold expects (int $post_id). Hook fires after the listing is created
						// with $post_id as first arg. Hold is placed when listing enters pending state.
						'hold_on'   => 'wb_listora_after_create_listing',
						// Settle hold when admin approves (post status → publish).
						'deduct_on' => 'wb_listora_after_approve_listing',
						// Release hold when admin rejects or user deletes.
						'refund_on' => 'wb_listora_after_reject_listing',
					),
					// The `featured_upgrade` SDK consumer was retired in
					// 1.0.5 — it never actually charged credits. The hooks
					// `wb_listora_before_feature_listing` (apply_filters
					// with $check, $post_id, $context) and
					// `wb_listora_after_feature_listing` (do_action with
					// $post_id, $context) don't match the SDK Consumer's
					// `add_action($hook, ..., 10, 1)` contract — the
					// callback received the bool $check instead of
					// $post_id, couldn't determine the item, silently
					// no-op'd. Vendors got Featured for free. The flow
					// now runs explicit Hold→Commit inside the REST
					// endpoint `POST /listings/{id}/feature` (see
					// Listings_Controller::feature_listing).
				),
				'settings'  => array(
					'low_threshold'       => (int) get_option( 'wb_listora_low_credit_threshold', 5 ),
					'purchase_url'        => wb_listora_get_credits_purchase_url(),
					'admin_settings_hook' => 'wb_listora_settings_tab_content',
				),
			)
		);
	}
);

// Include the bundled SDK (multi-version safe — latest wins across all plugins).
//
// The SDK is bundled composer-free in libs/wbcom-credits-sdk/ (git-committed,
// no submodule, no `composer install`) so BOTH plugins consume one canonical
// copy at runtime — same upscale pattern as Action Scheduler + the EDD SL SDK.
// Its loader (wbcom-credits-sdk.php) registers a self-contained PSR-4
// autoloader plus an explicit class→file map, then registers `after_setup_theme`
// callbacks that reference `\Wbcom\Credits\Versions`.
//
// Defensive guard: if a partial package ever ships the loader stub without the
// `src/` directory, those callbacks would fatal the whole site with
// "Class Wbcom\Credits\Versions not found". Only require the loader when its
// class source is actually present so a partial package degrades gracefully
// (credits features simply stay inactive) instead of taking the site down.
$wb_listora_credits_sdk_loader = WB_LISTORA_PLUGIN_DIR . 'libs/wbcom-credits-sdk/wbcom-credits-sdk.php';
$wb_listora_credits_sdk_class  = WB_LISTORA_PLUGIN_DIR . 'libs/wbcom-credits-sdk/src/Versions.php';

if ( file_exists( $wb_listora_credits_sdk_loader ) && file_exists( $wb_listora_credits_sdk_class ) ) {
	require_once $wb_listora_credits_sdk_loader;
} elseif ( file_exists( $wb_listora_credits_sdk_loader ) ) {
	// Loader present but submodule source missing — surface an admin notice
	// for site owners / support instead of failing silently or fatally.
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p>';
			echo esc_html__( 'WB Listora: the bundled Credits SDK could not be loaded (libs/wbcom-credits-sdk/src is missing). Credit-based features are disabled. Reinstall the plugin from a complete package to restore them.', 'wb-listora' );
			echo '</p></div>';
		}
	);
}

// ─── EDD Software Licensing SDK ───
// Bundled in Free at libs/edd-sl-sdk/ so BOTH plugins consume one canonical
// copy at runtime (same upscale pattern as Action Scheduler + Credits SDK).
// Free registers with a preset key that auto-activates on first admin load so
// auto-updates work out of the box; Pro registers with the user-entered key.
add_action(
	'edd_sl_sdk_registry',
	static function ( $registry ): void {
		$registry->register(
			array(
				'id'      => 'wb-listora',
				'url'     => 'https://wbcomdesigns.com',
				'item_id' => 1662779,
				'version' => WB_LISTORA_VERSION,
				'file'    => WB_LISTORA_PLUGIN_FILE,
				'license' => 'wbcomfree8a5d1c7e3f2b9a4c6e0d1b7f9c2a6e55',
			)
		);
	}
);

// EDD SL SDK — bundled composer-free in libs/edd-sl-sdk/ and MANDATORY for
// licensed auto-updates. Its loader registers a self-contained PSR-4 autoloader
// (classes ship in src/, no vendor/ dependency), so a plain require always works
// on a minimal upload-zip-and-activate install — it can never WSOD.
$wb_listora_edd_sdk_loader = WB_LISTORA_PLUGIN_DIR . 'libs/edd-sl-sdk/edd-sl-sdk.php';
if ( file_exists( $wb_listora_edd_sdk_loader ) ) {
	require_once $wb_listora_edd_sdk_loader;
}

// Auto-activate the preset license key on first admin load so downloads work
// without the customer ever needing to enter a key.
//
// Two guards protect customer admin from a slow / down license server:
//   1. `wb_listora_preset_activated` option — set after a `valid` EDD response.
//      Permanent short-circuit once activation succeeds.
//   2. `wb_listora_preset_activate_lock` 1-day transient — set on EVERY attempt
//      regardless of outcome. If the EDD store is unreachable, the next admin
//      page load (and the next ~24h of them) skip the remote call entirely
//      instead of stalling on the 8s timeout on every request.
//
// We also bail when `admin_init` fires outside a real admin page render
// (AJAX, REST, cron) — there's no user there to benefit from auto-activation
// and the network call would just block background requests.
add_action(
	'admin_init',
	static function (): void {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		$preset_key   = 'wbcomfree8a5d1c7e3f2b9a4c6e0d1b7f9c2a6e55';
		$option       = 'wb-listora_license_key'; // SDK reads `{$slug}_license_key`.
		$activated    = 'wb_listora_preset_activated';
		$lock         = 'wb_listora_preset_activate_lock';

		if ( get_option( $activated ) ) {
			return;
		}

		if ( get_site_transient( $lock ) ) {
			return;
		}

		// Re-attempt at most once per day until activation succeeds.
		set_site_transient( $lock, 1, DAY_IN_SECONDS );

		update_option( $option, $preset_key, false );

		$response = wp_remote_post(
			'https://wbcomdesigns.com',
			array(
				'timeout' => 8,
				'body'    => array(
					'edd_action' => 'activate_license',
					'license'    => $preset_key,
					'item_id'    => 1662779,
					'url'        => home_url(),
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 'valid' === ( $body['license'] ?? '' ) ) {
				update_option( $activated, 1, false );
				delete_site_transient( $lock );
				update_option(
					$option . '_allow_tracking',
					array(
						'allowed'   => true,
						'timestamp' => time(),
					),
					false
				);
			}
		}
	}
);

// Fire approve/reject lifecycle actions based on post status transitions.
// The SDK listens to these hooks to settle/refund credit holds.
add_action(
	'transition_post_status',
	static function ( $new_status, $old_status, $post ): void {
		if ( ! $post instanceof \WP_Post || 'listora_listing' !== $post->post_type ) {
			return;
		}
		// Only fire once per transition (not on initial create).
		if ( $new_status === $old_status || 'new' === $old_status || 'auto-draft' === $old_status ) {
			return;
		}
		// Approval: any status → publish.
		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			do_action( 'wb_listora_after_approve_listing', $post->ID );
		}
		// Rejection/trash: → rejected, deactivated, or trash.
		if ( in_array( $new_status, array( 'listora_rejected', 'listora_deactivated', 'trash' ), true ) ) {
			do_action( 'wb_listora_after_reject_listing', $post->ID );
		}
	},
	10,
	3
);

// Bridge: allow themes/plugins to get Listora credit balance via filter.
add_filter(
	'wb_listora_user_credit_balance',
	static function ( int $balance, int $user_id ): int {
		if ( class_exists( '\Wbcom\Credits\Credits' ) ) {
			return \Wbcom\Credits\Credits::get_balance( 'wb-listora', $user_id );
		}
		return $balance;
	},
	10,
	2
);

// Load template helper functions (used by block render.php files).
require_once WB_LISTORA_PLUGIN_DIR . 'includes/class-template-helpers.php';

// Page Registry — central resolver for Listora-managed pages. Registers Free's
// 3 canonical pages on init@5 so any code in init@10+ can resolve URLs by key.
require_once WB_LISTORA_PLUGIN_DIR . 'includes/page-registry-helpers.php';

// v2 primitive render helpers — wb_listora_render_empty_state() +
// wb_listora_render_tabs(). Blocks call these instead of inlining empty-state
// or tabs markup so the canonical primitives are consistently used.
require_once WB_LISTORA_PLUGIN_DIR . 'includes/class-render-helpers.php';

// Public extension functions Pro consumes (instead of direct refs to
// Free's internal helper classes). The classes themselves are autoloaded.
require_once WB_LISTORA_PLUGIN_DIR . 'includes/import-export/import-helpers.php';
require_once WB_LISTORA_PLUGIN_DIR . 'includes/workflow/email-helpers.php';
require_once WB_LISTORA_PLUGIN_DIR . 'includes/import-export/migration-helpers.php';
// General-purpose helpers (wb_listora_is_bot_request, ...). Eager-required
// because call sites use the BARE FUNCTION (Analytics_Lite::is_bot,
// Anti_Spam::check) — a bare function call never triggers the class
// autoloader, so relying on Bot_Detection's own require_once would fatal
// on the first analytics view where the class hasn't loaded yet.
require_once WB_LISTORA_PLUGIN_DIR . 'includes/helpers.php';

// Load central feature toggle system (wb_listora_feature_enabled, wb_listora_get_features, etc.).
require_once WB_LISTORA_PLUGIN_DIR . 'includes/class-features.php';

// Listing-submission field renderer. Loaded unconditionally so the
// step-details template can call it whether render.php defines
// $listing_type up front or lets view.js toggle between type blocks.
require_once WB_LISTORA_PLUGIN_DIR . 'includes/submission-field-renderer.php';

// Load WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WB_LISTORA_PLUGIN_DIR . 'includes/class-cli-commands.php';
}

// Activation and deactivation hooks (must be registered at file load time).
register_activation_hook( __FILE__, array( 'WBListora\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WBListora\\Deactivator', 'deactivate' ) );

// Boot the plugin on plugins_loaded.
add_action( 'plugins_loaded', 'wb_listora_init', 10 );

// Run pending DB migrations eagerly right after boot (priority 11 so the
// autoloader from wb_listora_init is in place). Without this, sites that
// update the plugin files never run Migrator::maybe_migrate() until something
// invokes it manually, leaving wb_listora_db_version stale against
// WB_LISTORA_DB_VERSION (smoke finding F2). Mirrors Pro's eager migrator.
// Cheap no-op on every request once versions match (one option read +
// version_compare).
add_action( 'plugins_loaded', array( 'WBListora\\DB\\Migrator', 'maybe_migrate' ), 11 );

/**
 * Initialize the plugin.
 */
function wb_listora_init() {
	if ( ! wb_listora_check_requirements() ) {
		return;
	}

	// Initialize the main plugin instance.
	\WBListora\Plugin::instance();
}
