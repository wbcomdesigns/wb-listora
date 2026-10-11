<?php
/**
 * Plugin Name: WB Listora
 * Plugin URI:  https://wbcomdesigns.com/downloads/listora/
 * Description: The complete WordPress directory plugin. Create any type of listing directory — business, restaurant, hotel, real estate, jobs, events, and more.
 * Version:     1.9.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author:      Wbcom Designs
 * Author URI:  https://wbcomdesigns.com
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wb-listora
 * Domain Path: /languages
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'WB_LISTORA_VERSION', '1.9.0' );
define( 'WB_LISTORA_DB_VERSION', '1.9.1.5' );
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

	// Build subdirectory path. Each namespace segment is kebab-cased, so
	// `ImportExport` resolves to `import-export/`, not `importexport/`.
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

	// Almost every class lives under includes/. WBListora\Demo\* is the one
	// namespace root that ships at the top level (demo/), used by the Setup
	// Wizard, the Delete demo data setting and its AJAX remover, and the CLI.
	$base = WB_LISTORA_PLUGIN_DIR . 'includes/';
	if ( isset( $parts[0] ) && 'Demo' === $parts[0] ) {
		$base = WB_LISTORA_PLUGIN_DIR;
	}

	$file = $base . $subdir . $class_file;

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
 * The public extension surface for Pro and third-party code: the returned
 * object implements a {@see \WBListora\Contracts} interface. Pro consumes Free
 * only through this and the Contracts namespace, never concrete Core classes.
 * Always null-check the result: an older Free build may not register the service.
 *
 * Available services: 'listing_types', 'featured', 'meta', 'services',
 * 'search_indexer', 'search_engine', 'geo_query', 'block_css', 'cache',
 * 'admin_table'.
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
 * The explicit owner/theme credit-purchase override, resolved to a URL.
 *
 * Just the `wb_listora_credit_purchase_url` option (numeric page id → permalink),
 * with NO dashboard / auto fallback — returns '' when unset. This is the single
 * source of the override so BOTH Free's wb_listora_get_credits_purchase_url() and
 * Pro's Pricing_Plans::resolve_credits_url() honour the same owner-set destination
 * first; without it, an owner who set the documented override got it on Free CTAs
 * but not on Pro's insufficient-credits card / paused-listing email (two checkout
 * destinations in one flow).
 *
 * @since 1.3.0
 *
 * @return string Resolved override URL, or '' when unset.
 */
function wb_listora_get_credit_purchase_url_override() {
	$override = get_option( 'wb_listora_credit_purchase_url', '' );
	if ( empty( $override ) ) {
		return '';
	}
	if ( is_numeric( $override ) ) {
		return (string) get_permalink( (int) $override );
	}
	return (string) $override;
}

/**
 * The owner's own store for credits, when it is somewhere other than Listora.
 *
 * The wb_listora_credit_purchase_url option can hold a URL or a page ID, and
 * often points at Listora's own Buy Credits page. That page is not a store, so
 * it is not offered as one; a page ID is resolved to an absolute link.
 *
 * @since 1.9.0
 *
 * @return string Absolute URL, or '' when there is no separate store.
 */
function wb_listora_get_external_credit_store_url() {
	$url = wb_listora_get_credit_purchase_url_override();
	if ( '' === $url ) {
		return '';
	}
	$page_id = url_to_postid( $url );
	if ( $page_id > 0 && has_block( 'listora-pro/credit-purchase', $page_id ) ) {
		return '';
	}
	return $url;
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
	$override = wb_listora_get_credit_purchase_url_override();
	if ( '' !== $override ) {
		/** This filter is documented in wb-listora.php */
		return (string) apply_filters( 'wb_listora_credits_purchase_url', $override );
	}

	// Auto-resolve: the mapped dashboard page with ?tab=credits. Read from the
	// page registry, which Settings writes; the legacy settings key was only
	// set at activation, so the link kept pointing at the old page.
	$url = wb_listora_get_page_url( 'dashboard', array( 'tab' => 'credits' ) );
	if ( '' !== $url ) {
		$url .= '#listora-credit-packs';
	}

	/**
	 * Filter the "buy credits" URL used across the directory.
	 *
	 * @param string $url Fully-resolved purchase URL.
	 */
	return (string) apply_filters( 'wb_listora_credits_purchase_url', $url );
}

/**
 * Whether any payment gateway can actually accept a credits checkout.
 *
 * Probes the Wbcom Credits SDK Gateway_Registry for enabled + credentialed
 * gateways (Stripe, PayPal, custom). Used by member-facing UX gates so we
 * never show "Buy Credits" when checkout would be a dead end.
 *
 * @since 1.3.0
 *
 * @return bool
 */
function wb_listora_has_configured_payment_gateway() {
	$available = false;

	if ( class_exists( '\\Wbcom\\Credits\\Gateways\\Gateway_Registry' ) ) {
		try {
			$registry = \Wbcom\Credits\Gateways\Gateway_Registry::for_slug( 'wb-listora' );
			$registry->boot();
			$available = ! empty( $registry->get_available() );
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- fail closed.
			$available = false;
		}
	}

	/**
	 * Filter whether a payment gateway is available for credit purchases.
	 *
	 * @since 1.2.0
	 *
	 * @param bool $available True when ≥1 SDK gateway is enabled + configured.
	 * @param int  $user_id   Current user (0 when called outside a user context).
	 */
	return (bool) apply_filters( 'wb_listora_has_payment_gateway', $available, get_current_user_id() );
}

/**
 * Every live way a member could pay for credits on this site.
 *
 * The single answer to "can a member buy credits here?"; every credit surface
 * reads this. Facts come from the Credits SDK (gateways, adapters, the
 * `{slug}_credit_mappings` option). Free never reads Pro's packs (INV-12); Pro
 * adds them through the `wb_listora_credit_purchase_paths` filter.
 *
 * @since 1.7.0
 *
 * @return array<string, bool> Keyed by route: external_url, gateway, mapping.
 *                             Pro adds pack_url. True means that route is live.
 */
function wb_listora_credit_purchase_paths() {
	/*
	 * The Credits SDK owns this question - Credits::purchase_paths() reads the
	 * gateway registry, the adapter registry and the {slug}_credit_mappings
	 * option it already maintains. Asking it means an adapter the SDK gains
	 * later is counted here with no change in this file, which is the whole
	 * reason the composite lives there rather than in each consumer.
	 *
	 * There is deliberately NO local reimplementation to fall back to. The SDK
	 * is bundled and git-committed under libs/, so its version is fixed at
	 * build time rather than resolved at runtime - a fallback would guard a
	 * case that cannot occur in a shipped install, while duplicating logic the
	 * SDK owns. If the bundle is ever missing entirely, no purchase route is
	 * live and no credit surface renders, which is the correct answer anyway.
	 */
	if ( ! class_exists( '\\Wbcom\\Credits\\Credits' )
		|| ! method_exists( '\\Wbcom\\Credits\\Credits', 'purchase_paths' ) ) {
		return array();
	}

	/**
	 * Filter the live credit purchase routes.
	 *
	 * Pro contributes `pack_url` here, because credit packs are Pro-owned and
	 * Free must not read that option (INV-12). Add a key rather than replacing
	 * the array, so no route is lost.
	 *
	 * @since 1.7.0
	 *
	 * @param array<string, bool> $paths Route => whether it is live.
	 */
	$paths = (array) apply_filters(
		'wb_listora_credit_purchase_paths',
		\Wbcom\Credits\Credits::purchase_paths( 'wb-listora' )
	);

	return array_map( 'boolval', $paths );
}

/**
 * Whether members have a real way to buy credits (not just an empty Credits UI).
 *
 * True only when at least one of:
 * - Explicit external purchase URL / page (`wb_listora_credit_purchase_url`)
 * - Pro credit packs with a direct checkout path (configured gateway, or pack `url`)
 * - Adapter mappings (WooCommerce / PMPro / MemberPress) that resolve a buy URL
 *
 * Auto-resolving the dashboard Credits tab does NOT count — that would create a
 * circular "purchase path" that still confuses members with empty packs.
 *
 * @since 1.3.0
 *
 * @return bool
 */
function wb_listora_has_credit_purchase_path() {
	$has_path = in_array( true, wb_listora_credit_purchase_paths(), true );

	/**
	 * Filter whether members have a real credit purchase path.
	 *
	 * Retained for back-compat. Prefer wb_listora_credit_purchase_paths(),
	 * which says WHICH route is live rather than only that one is.
	 *
	 * @since 1.3.0
	 *
	 * @param bool $has_path True when buy UX would not be a dead end.
	 */
	return (bool) apply_filters( 'wb_listora_has_credit_purchase_path', $has_path );
}

/**
 * Whether member-facing Credits / Buy Credits UI should render.
 *
 * Plug-and-play rule: never show credit economy chrome to members when there
 * is nothing they can buy (Free-only, monetization off, or no gateway/packs/
 * mappings). Admins still configure credits in wp-admin Settings.
 *
 * Prefer this (or the `wb_listora_show_credits` filter) over raw
 * `class_exists( Credits )` checks in front-end templates.
 *
 * @since 1.3.0
 *
 * @return bool
 */
function wb_listora_should_show_member_credits() {
	$show = class_exists( '\\Wbcom\\Credits\\Credits' )
		&& function_exists( 'wb_listora_is_pro_active' )
		&& wb_listora_is_pro_active()
		&& wb_listora_has_credit_purchase_path();

	/**
	 * Filter whether customer-facing credit surfaces render.
	 *
	 * Documented in blocks/user-dashboard/render.php. Pro forces false when
	 * monetization is off; other plugins may force on/off for custom stores.
	 *
	 * @param bool $show Whether credit surfaces should render.
	 */
	return (bool) apply_filters( 'wb_listora_show_credits', $show );
}

/**
 * Whether listing owners may reply to reviews (Settings > Reviews > Enable replies).
 *
 * The one reading of reviews.allow_reply: the reply endpoint, the dashboard
 * Reviews tab and the listing page's review cards all ask here. Default on
 * when never saved, like the checkbox.
 *
 * @since 1.9.0
 *
 * @return bool
 */
function wb_listora_review_replies_enabled() {
	$settings = wb_listora_get_setting( 'reviews', array() );
	return ! is_array( $settings ) || ! isset( $settings['allow_reply'] ) || ! empty( $settings['allow_reply'] );
}

/**
 * Whether a member may leave only one review per listing (Settings > Reviews).
 *
 * Default on when never saved, like the checkbox. The review endpoint and
 * the review forms both read it here.
 *
 * @since 1.9.0
 *
 * @return bool
 */
function wb_listora_one_review_per_listing() {
	$settings = wb_listora_get_setting( 'reviews', array() );
	return ! is_array( $settings ) || ! isset( $settings['one_per_listing'] ) || ! empty( $settings['one_per_listing'] );
}

/**
 * Whether a member's own credit record (balance + history) is shown.
 *
 * Wider than wb_listora_should_show_member_credits(), which asks "can the
 * member buy?". A member who holds credits, owes them, or has any history
 * keeps seeing that record when nothing is on sale: it is a record of their
 * money. The buy parts stay hidden.
 *
 * @since 1.9.0
 *
 * @param int $user_id Member. 0 for the current user.
 * @return bool
 */
function wb_listora_should_show_member_credit_record( $user_id = 0 ) {
	if ( wb_listora_should_show_member_credits() ) {
		return true;
	}

	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	$show    = $user_id > 0
		&& wb_listora_credits_ready()
		&& function_exists( 'wb_listora_is_pro_active' )
		&& wb_listora_is_pro_active()
		&& ( 0.0 !== (float) \Wbcom\Credits\Credits::balance_money( 'wb-listora', $user_id )
			|| \Wbcom\Credits\Credits::count_ledger( 'wb-listora', $user_id ) > 0 );

	/**
	 * Filter whether a member's credit balance and history are shown when
	 * nothing is on sale.
	 *
	 * On by default even with Monetization off: a member who holds credits,
	 * owes them or has history always sees that record; only the buy parts
	 * hide.
	 *
	 * @since 1.9.0
	 *
	 * @param bool $show    Default: the member has a balance or any history.
	 * @param int  $user_id Member.
	 */
	return (bool) apply_filters( 'wb_listora_show_credit_record', $show, $user_id );
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
 * Reads `wb_listora_settings` once per request into a function-static.
 * Cache::on_settings_updated() re-primes it with `$force_reload = true`.
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
 * Resolve the listing type new submissions default to.
 *
 * Stored as ONE site setting, not a per-type flag, so "only one default" holds
 * by construction. Returns '' when unset, when the slug no longer resolves to
 * a registered type, or when that type does not accept frontend submissions.
 * Unrelated to the `is_default` type prop, which means "shipped with the plugin".
 *
 * @since 1.5.0
 *
 * @return string Listing type slug, or '' when there is no usable default.
 */
function wb_listora_get_default_listing_type() {
	$slug = (string) wb_listora_get_setting( 'default_listing_type', '' );

	if ( '' !== $slug ) {
		$type = \WBListora\Core\Listing_Type_Registry::instance()->get( $slug );
		if ( ! $type || ! $type->get_prop( 'submission_enabled' ) ) {
			$slug = '';
		}
	}

	/**
	 * Filters the listing type pre-selected on the submission form.
	 *
	 * Return '' to restore the pre-1.5.0 behaviour of never pre-selecting a
	 * type, or a slug to override the stored setting per-context.
	 *
	 * @since 1.5.0
	 *
	 * @param string $slug Resolved default type slug, '' when none.
	 */
	return (string) apply_filters( 'wb_listora_default_listing_type', $slug );
}

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
	error_log( '[wb-listora] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- opt-in debug logging, off unless enabled or WP_DEBUG.
}

/**
 * Radius choices a visitor gets after tapping "Near me", in the site's distance unit.
 *
 * Always contains the owner's default from Settings > General.
 *
 * @since 1.9.1
 *
 * @return int[] Sorted, unique, positive radii.
 */
function wb_listora_near_me_radius_options() {
	/**
	 * Filter: the radius choices shown next to the search bar once "Near me" is active.
	 *
	 * @since 1.9.1
	 *
	 * @param int[] $options Radii in the site's distance unit.
	 */
	$options   = (array) apply_filters( 'wb_listora_near_me_radius_options', array( 1, 5, 10, 25, 50 ) );
	$options[] = (int) wb_listora_get_setting( 'near_me_radius' );
	$options   = array_unique( array_filter( array_map( 'absint', $options ) ) );
	sort( $options );
	return $options;
}

function wb_listora_get_default_settings() {
	return array(
		'per_page'                       => 20,
		'default_type'                   => 'business', // @deprecated 1.9.1 - never read; the type comes from the block or the URL.
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
		'near_me_radius'                 => 5,
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
		/*
		 * Empty by design. Listora ships no default tile server: OpenStreetMap's
		 * public tiles are not licensed for product-scale use. An owner supplies
		 * their own in Settings -> Map; blank renders markers with no background
		 * layer, which the mobile app already handles.
		 */
		'map_tile_url'                   => '',
		'map_tile_attribution'           => '',
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
		'max_upload_size'                => 5, // @deprecated 1.9.1 - the server limit applies; no longer read.
		'max_gallery_images'             => 20,
		'submission_page'                => 0,
		'dashboard_page'                 => 0,
		// Directory (listings archive) page. Declared here so Settings can store
		// + expose the selected Directory page and Page_Registry's heal path can
		// read it, mirroring submission_page / dashboard_page. The ID must live in
		// Settings, not only in the wb_listora_directory_page_id option, or the
		// two desync and the registry reports "orphan".
		'directory_page'                 => 0,
		'delete_on_uninstall'            => false,
		'search_cache_ttl'               => 15,
		'facet_cache_ttl'                => 30, // @deprecated 1.9.1 - only the deprecated Facets class read it.
		'debug_logging'                  => false,
		'setup_complete'                 => false,
		'captcha_provider'               => 'none',
		'captcha_site_key'               => '',
		'captcha_secret_key'             => '',
		'enable_guest_submission'        => false, // @deprecated 1.9.1 - never read; submission needs an account.
		'guest_email_verification'       => true, // @deprecated 1.9.1 - never read.
		'verification_link_expiry_hours' => 24,
		'unverified_listings_action'     => 'trash',
		'unverified_listings_max_days'   => 7,
		'featured_credit_cost'           => 0,
		'featured_duration_days'         => 30,
		'default_listing_credit_cost'    => 0,
		// Legal / App Store surface. Exposed in GET /settings/app-config's
		// `legal` block. Apple requires reachable privacy + terms links inside
		// the app; `privacy_policy_url` there falls back to WP core's privacy
		// page, but these three have no core equivalent and must be authored
		// by the site owner (Settings → General → Legal & App Store), else the
		// native app ships an empty legal block and is rejected at review.
		'legal_terms_url'                => '',
		'legal_community_guidelines_url' => '',
		'legal_abuse_contact_email'      => '',
		'listing_limits_period'          => 'lifetime',
		'listing_beyond_limit_behavior'  => 'block',
		// Per-role listing limits — empty map means "no role-specific
		// override; everyone gets `listing_limits_default`". Site owners
		// populate this via Settings → Submissions → Listing Limits.
		'listing_limits_per_role'        => array(),
		'listing_limits_default'         => -1,
		// Reviews — nested settings group. Defaults match the most common
		// directory-site posture: logged-in members review (always required),
		// one review per user per listing, owners can reply,
		// minimum 20-character reviews to filter spam, NOT auto-approved
		// (admin reviews everything in Listora → Reviews).
		'reviews'                        => array(
			'require_login'   => true, // @deprecated 1.9.1 - reviews always require login; never read.
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
		/*
		 * Credit quantities the owner actually sells as direct packs. Read once
		 * here because the SDK stores `min_credits`/`max_credits` as scalars.
		 */
		$listora_pack_credits = array();
		if ( function_exists( 'wb_listora_get_credit_mappings' ) ) {
			foreach ( wb_listora_get_credit_mappings() as $listora_map ) {
				if ( is_array( $listora_map ) && 'direct' === ( $listora_map['adapter'] ?? '' ) ) {
					$listora_credits = (int) ( $listora_map['credits'] ?? 0 );
					if ( $listora_credits > 0 ) {
						$listora_pack_credits[] = $listora_credits;
					}
				}
			}
		}

		$registry->register(
			array(
				'slug'       => 'wb-listora',
				// SDK uses this to namespace its ledger table: {wp_prefix}{prefix}_credit_ledger.
				// Use 'listora' (no trailing underscore — SDK adds its own separator).
				'prefix'     => 'listora',
				'version'    => WB_LISTORA_VERSION,
				'file'       => WB_LISTORA_PLUGIN_FILE,
				'user_type'  => 'listing_owner',
				// Where every direct Stripe/PayPal checkout returns when the caller
				// passed no return_url: the dashboard Credits tab, which claims the
				// session. Without it the SDK falls back to the home page, where
				// nothing claims, and credits never land on a site without a webhook.
				'return_url' => static function (): string {
					return function_exists( 'wb_listora_get_public_page_url' )
						? (string) wb_listora_get_public_page_url( 'dashboard', array( 'tab' => 'credits' ) )
						: '';
				},
				// A Listora credit IS a unit of the store currency (credit_rate
				// defaults to 1.0; balances are money). Declaring money mode makes
				// the SDK store the ledger in integer MINOR units, so fractional
				// payments no longer lose cents and zero/three-decimal currencies
				// work. The credit path uses Credits::*_money() (see Credit_System).
				'money'      => array(
					'currency' => static function (): string {
						return strtoupper( (string) wb_listora_get_setting( 'currency', 'USD' ) );
					},
				),
				/*
				 * Server-authoritative pricing, required by the Credits SDK from
				 * 1.3.0: without it `POST /checkout/{gateway}` returns
				 * `503 pricing_not_configured`. The browser sends a CREDIT COUNT,
				 * never a price; the amount resolves from the owner's own packs.
				 */
				'pricing'    => array(
					/*
					 * A STRING, not a closure — unlike `money.currency` above.
					 * Pricing::resolve() does `(string) $pricing['currency']`,
					 * so a closure here is a fatal on the checkout call:
					 * "Object of class Closure could not be converted to string".
					 * Resolved at registration, which runs after settings load.
					 */
					'currency'               => strtoupper( (string) wb_listora_get_setting( 'currency', 'USD' ) ),
					'credits_to_price_cents' => static function ( int $credits ): int {
						if ( $credits < 1 || ! function_exists( 'wb_listora_get_credit_mappings' ) ) {
							return 0;
						}

						foreach ( wb_listora_get_credit_mappings() as $map ) {
							if ( ! is_array( $map ) || 'direct' !== ( $map['adapter'] ?? '' ) ) {
								continue;
							}

							if ( (int) ( $map['credits'] ?? 0 ) === $credits ) {
								return (int) ( $map['price_cents'] ?? 0 );
							}
						}

						/*
						 * 0 means "no pack sells this quantity", and the SDK
						 * refuses the checkout. Deliberately NOT falling back to
						 * credits x credit_rate: that would let a member buy any
						 * quantity at a rate the owner never offered as a pack.
						 */
						return 0;
					},
					/*
					 * Plain ints: the SDK casts these; only `credits_to_price_cents`
					 * is invoked as a callable, so a closure here is a fatal.
					 * Bounds come from the owner's real packs. A non-pack quantity
					 * inside the bounds gets a 500 from the SDK and the purchase is
					 * refused; no fallback price on a money path.
					 */
					'min_credits'            => $listora_pack_credits ? min( $listora_pack_credits ) : 1,
					'max_credits'            => $listora_pack_credits ? max( $listora_pack_credits ) : 1,
				),
				'consumers'  => array(
					array(
						'id'        => 'listing_submission',
						'label'     => __( 'Listing Submission', 'wb-listora' ),
						// One definition shared with the submission route's
						// balance check (wb_listora_listing_submission_cost()).
						'cost'      => 'wb_listora_listing_submission_cost',
						// Fired by the submission route when a listing goes to
						// review or live, not on create, so a saved draft cannot
						// skip the charge. The route checks the balance under the
						// credit lock first.
						'hold_on'   => 'wb_listora_listing_submission_charge',
						// Settle on approval, release on rejection - fired below
						// only for listings a plan does not pay for.
						'deduct_on' => 'wb_listora_listing_submission_settle',
						'refund_on' => 'wb_listora_listing_submission_release',
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
				'settings'   => array(
					'low_threshold'       => (int) get_option( 'wb_listora_low_credit_threshold', 5 ),
					'purchase_url'        => wb_listora_get_credits_purchase_url(),
					'admin_settings_hook' => 'wb_listora_settings_tab_content',
				),
			)
		);
	}
);

/**
 * Require a bundled library loader only when its class source is present.
 *
 * A package that ships a loader stub without its src/ classes would fatal the
 * site on a later class reference; this degrades that to an admin notice and
 * disables the dependent features instead.
 *
 * @param string $loader     Absolute path to the library's loader file.
 * @param string $probe_file Absolute path to a class file that must exist for
 *                           the library to function (proves src/ shipped).
 * @param string $label      Human-readable library name for the notice.
 * @return bool True when the loader was required, false when a notice was shown.
 */
function wb_listora_require_bundled_lib( string $loader, string $probe_file, string $label ): bool {
	if ( file_exists( $loader ) && file_exists( $probe_file ) ) {
		require_once $loader;
		return true;
	}

	add_action(
		'admin_notices',
		static function () use ( $label ): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: bundled library/SDK name. */
				esc_html__( 'WB Listora: the bundled %s could not be loaded from a complete package, so the features it powers are disabled. Reinstall WB Listora from a complete zip to restore them.', 'wb-listora' ),
				esc_html( $label )
			);
			echo '</p></div>';
		}
	);

	return false;
}


/**
 * Whether the LOADED Credits SDK carries the money API this plugin calls.
 *
 * class_exists() is not enough: each Wbcom plugin bundles the SDK and the copy
 * loaded first owns `\Wbcom\Credits\Credits` for the request, so an older
 * plugin can supply a class without balance_money(), hold_money() and the rest
 * of the money API (SDK 1.5.0/1.6.0). Checking sentinel methods keeps such a
 * site on the no-credits path instead of a fatal.
 *
 * @since 1.8.0
 *
 * @return bool True when the loaded SDK can service credit operations.
 */
function wb_listora_credits_ready() {
	static $ready = null;

	if ( null !== $ready ) {
		return $ready;
	}

	if ( ! class_exists( '\Wbcom\Credits\Credits' ) || ! class_exists( '\Wbcom\Credits\Money' ) ) {
		$ready = false;
		return $ready;
	}

	foreach ( array( 'is_money', 'balance_money', 'hold_money', 'deduct_money', 'purchase_paths' ) as $method ) {
		if ( ! method_exists( '\Wbcom\Credits\Credits', $method ) ) {
			$ready = false;
			return $ready;
		}
	}

	$ready = true;
	return $ready;
}

/**
 * Restore `balance_units` / `balance_money` / `currency` on the bundled
 * Credits SDK's own `GET /wbcom-credits/v1/wb-listora/balance` route.
 *
 * Upstream SDK 1.6.0 does not return these fields, so a client cannot tell
 * minor units from whole credits. The vendored copy is never hand-patched
 * (Credits SDK Standard), so they are added through the REST response filter
 * until the SDK ships them.
 *
 * @since 1.9.0
 */
add_filter(
	'rest_request_after_callbacks',
	static function ( $response, $handler, $request ) {
		if ( ! $response instanceof WP_REST_Response || '/wbcom-credits/v1/wb-listora/balance' !== $request->get_route() ) {
			return $response;
		}

		$data = $response->get_data();
		if ( ! is_array( $data ) || isset( $data['balance_units'] ) || ! wb_listora_credits_ready() ) {
			return $response;
		}

		$is_money              = \Wbcom\Credits\Credits::is_money( 'wb-listora' );
		$data['balance_units'] = $is_money ? 'minor' : 'credits';
		if ( $is_money ) {
			$data['balance_money'] = \Wbcom\Credits\Credits::balance_money( 'wb-listora', (int) ( $data['user_id'] ?? 0 ) );
			$data['currency']      = \Wbcom\Credits\Credits::resolve_money_currency( 'wb-listora' );
		}
		$response->set_data( $data );

		return $response;
	},
	10,
	3
);

/**
 * The per-listing credit cost for a member, in credits.
 *
 * The overflow cost once they are past their listing cap on a "charge beyond
 * the limit" site, otherwise Settings > Credit Costs > Listing submission cost.
 *
 * @since 1.9.0
 *
 * @param int $user_id Member.
 * @param int $extra   Listings not created yet that will count (1 before a new one is inserted).
 * @return int
 */
function wb_listora_member_listing_cost( $user_id, $extra = 0 ) {
	$user_id = (int) $user_id;
	if ( $user_id > 0 && 'credits' === \WBListora\Core\Listing_Limits::get_beyond_limit_behavior() ) {
		$cap   = \WBListora\Core\Listing_Limits::get_user_limit( $user_id );
		$count = \WBListora\Core\Listing_Limits::get_user_count( $user_id ) + (int) $extra;
		if ( $cap >= 0 && $count > $cap ) {
			$overflow = (int) get_option( \WBListora\Core\Listing_Limits::OVERFLOW_COST_OPTION, 0 );
			if ( $overflow > 0 ) {
				return $overflow;
			}
		}
	}

	return (int) wb_listora_get_setting( 'default_listing_credit_cost', 0 );
}

/**
 * Serve a block's stylesheets under the plugin's version.
 *
 * block.json carries its own `version` ("1.0.0" on every block), and core uses
 * that for the style URL. It never changes between releases, so browsers kept
 * the cached block CSS after an update and style fixes did not reach returning
 * visitors. Stamping the registered handles makes each release a new URL.
 *
 * @since 1.9.0
 *
 * @param mixed  $block_type Result of register_block_type().
 * @param string $version    Plugin version to serve the styles under.
 * @return void
 */
function wb_listora_version_block_styles( $block_type, $version ) {
	if ( ! $block_type instanceof \WP_Block_Type ) {
		return;
	}

	$handles = array_merge(
		(array) $block_type->style_handles,
		(array) $block_type->editor_style_handles,
		(array) $block_type->view_style_handles
	);

	foreach ( $handles as $handle ) {
		if ( isset( wp_styles()->registered[ $handle ] ) ) {
			wp_styles()->registered[ $handle ]->ver = (string) $version;
		}
	}
}

/**
 * Whether a Pro pricing plan pays for this listing instead of the submission cost.
 *
 * True once a plan is activated or chosen and waiting for credits.
 *
 * @since 1.9.0
 *
 * @param int $post_id Listing ID.
 * @return bool
 */
function wb_listora_listing_plan_pays( $post_id ) {
	$post_id = (int) $post_id;
	return (int) get_post_meta( $post_id, '_listora_plan_id', true ) > 0
		|| (int) get_post_meta( $post_id, '_listora_pending_plan_id', true ) > 0
		|| 'listora_payment' === get_post_status( $post_id );
}

/**
 * What submitting this listing costs, in credits (0 when a plan pays instead).
 *
 * A Pro pricing plan - activated, or chosen and waiting for credits - owns the
 * charge, so this is 0 for those; otherwise the member's listing cost.
 * The SDK's listing_submission consumer and the submission route's balance
 * check both use it.
 *
 * @since 1.9.0
 *
 * @param int $post_id Listing ID.
 * @return int
 */
function wb_listora_listing_submission_cost( $post_id ) {
	$post_id = (int) $post_id;
	if ( wb_listora_listing_plan_pays( $post_id ) ) {
		return 0;
	}

	return wb_listora_member_listing_cost( (int) get_post_field( 'post_author', $post_id ) );
}

/**
 * How long a listing runs before it expires, in days (0 = lifetime).
 *
 * Plan duration, else listing type, else Settings > Default expiration.
 *
 * @since 1.9.0
 *
 * @param int $post_id Listing ID.
 * @return int
 */
function wb_listora_listing_duration_days( $post_id ) {
	return \WBListora\Workflow\Status_Manager::standard_duration_days( (int) $post_id );
}

/**
 * Run a credit spend while no other spend for the same user can run.
 *
 * Every path that checks a balance and then holds credits (plan activation,
 * Featured upgrades, renewals, need responses) must wrap that check, the hold
 * and the commit in this call, or two simultaneous requests can both pass the
 * check and overdraw the member. Nested calls are safe.
 *
 * @since 1.9.0
 *
 * @param int      $user_id  User whose credits are being spent.
 * @param callable $callback Work to run; its return value is passed through.
 * @return mixed|\WP_Error The callback's result, or `listora_credits_busy` (409)
 *                         when another spend by this user did not finish in time.
 */
function wb_listora_with_credits_lock( $user_id, callable $callback ) {
	return \WBListora\DB\Credit_Lock::run( (int) $user_id, $callback );
}

/**
 * Tell the site owner which plugin is loading the older credits library.
 *
 * Hooked unconditionally and self-gated: a healthy site pays one cached
 * readiness check and renders nothing. Registering the hook from inside the
 * readiness check instead would miss any request whose first credits gate runs
 * after `admin_notices` has already fired.
 *
 * @since 1.8.0
 *
 * @return void
 */
function wb_listora_render_credits_sdk_outdated_notice() {
	if ( wb_listora_credits_ready() || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	// This notice is about an OLDER copy loaded by another plugin. When no copy
	// is loaded at all, wb_listora_require_bundled_lib() has already reported
	// the package as incomplete.
	if ( ! class_exists( '\Wbcom\Credits\Credits' ) ) {
		return;
	}

	$owner = '';

	try {
		$file = ( new ReflectionClass( '\Wbcom\Credits\Credits' ) )->getFileName();
		if ( $file && preg_match( '#/plugins/([^/]+)/#', wp_normalize_path( $file ), $matches ) ) {
			$owner = $matches[1];
		}
	} catch ( ReflectionException $e ) {
		$owner = '';
	}

	$message = $owner
		? sprintf(
			/* translators: %s: plugin folder name that bundles the older library. */
			__( 'WB Listora: credits are disabled because the plugin in %s is loading an older copy of the Wbcom Credits library. Update that plugin to the latest version to restore credit balances, holds and purchases.', 'wb-listora' ),
			'<code>' . esc_html( $owner ) . '</code>'
		)
		: __( 'WB Listora: credits are disabled because another plugin on this site is loading an older copy of the Wbcom Credits library. Update your Wbcom plugins to the latest versions to restore credit balances, holds and purchases.', 'wb-listora' );

	echo '<div class="notice notice-error"><p>' . wp_kses_post( $message ) . '</p></div>';
}
add_action( 'admin_notices', 'wb_listora_render_credits_sdk_outdated_notice' );

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
// licensed auto-updates. The loader registers a LAZY PSR-4 autoloader, so a
// missing src/ does not WSOD at boot — but the first \EasyDigitalDownloads\Updater\*
// reference (e.g. on the update check) would fatal. Route it through the same
// guard so a partial package disables auto-updates with a soft notice instead.
wb_listora_require_bundled_lib(
	WB_LISTORA_PLUGIN_DIR . 'libs/edd-sl-sdk/edd-sl-sdk.php',
	WB_LISTORA_PLUGIN_DIR . 'libs/edd-sl-sdk/src/Registry.php',
	'license and updates SDK'
);

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

		$preset_key = 'wbcomfree8a5d1c7e3f2b9a4c6e0d1b7f9c2a6e55';
		$option     = 'wb-listora_license_key'; // SDK reads `{$slug}_license_key`.
		$activated  = 'wb_listora_preset_activated';
		$lock       = 'wb_listora_preset_activate_lock';

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

// A textarea holds a form value, not prose: wptexturize() would turn `<!--`
// into `<!&#8211;` and change quotes and dashes, altering the value on save.
add_filter(
	'no_texturize_tags',
	static function ( $tags ) {
		$tags[] = 'textarea';
		return $tags;
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

// The submission-cost consumer settles or releases on these, never on a
// listing a Pro plan pays for: the SDK consumer acts on every open hold on the
// item, so on approval it consumed Pro's plan hold, Pro's own settle then
// failed and the plan was charged again on the next top-up.
// SDK-WORKAROUND: Consumer::settle_holds()/release_holds() are not scoped to
// the consumer's own hold id - remove when the bundled SDK settles by hold id.
// ponytail: a listing given a plan after its submission hold was placed keeps
// that hold open; settle by hold id upstream closes that too.
add_action(
	'wb_listora_after_approve_listing',
	static function ( $post_id ): void {
		if ( ! wb_listora_listing_plan_pays( (int) $post_id ) ) {
			/** Settles the listing's submission-cost hold (credits SDK consumer). @since 1.9.0 */
			do_action( 'wb_listora_listing_submission_settle', (int) $post_id );
		}
	}
);
add_action(
	'wb_listora_after_reject_listing',
	static function ( $post_id ): void {
		if ( ! wb_listora_listing_plan_pays( (int) $post_id ) ) {
			/** Releases the listing's submission-cost hold (credits SDK consumer). @since 1.9.0 */
			do_action( 'wb_listora_listing_submission_release', (int) $post_id );
		}
	}
);

// Bridge: allow themes/plugins to get Listora credit balance via filter.
//
// Returns MAJOR units (what a site owner calls "credits"), not the raw ledger
// integer. Listora registers the SDK in money mode, so get_balance() returns
// integer MINOR units — a 7.40 balance reads as 740. Every consumer of this
// filter compares it against a credit cost, so the raw value made a member look
// ~100x richer than they were and let the overflow gate through.
add_filter(
	'wb_listora_user_credit_balance',
	static function ( float $balance, int $user_id ): float {
		if ( wb_listora_credits_ready() ) {
			return \Wbcom\Credits\Credits::balance_money( 'wb-listora', $user_id );
		}
		return $balance;
	},
	10,
	2
);

// State the units on the SDK's own balance and history routes.
//
// Listora runs the SDK in money mode, so the balance route answers in MINOR
// units (1000 means 10.00). The route stays live while Monetization is off,
// so this belongs here, where Free declares money mode, not in Pro's credit
// feature. Additive and read-only.
add_filter(
	'rest_request_after_callbacks',
	static function ( $response, $handler, $request ) {
		unset( $handler );
		if ( ! $response instanceof \WP_REST_Response || ! wb_listora_credits_ready() ) {
			return $response;
		}
		$route = untrailingslashit( strtolower( $request->get_route() ) );
		if ( ! in_array( $route, array( '/wbcom-credits/v1/wb-listora/balance', '/wbcom-credits/v1/wb-listora/history' ), true ) ) {
			return $response;
		}
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! isset( $data['user_id'] ) ) {
			return $response;
		}
		$is_money = \Wbcom\Credits\Credits::is_money( 'wb-listora' );
		$extra    = array( 'balance_units' => $is_money ? 'minor' : 'credits' );
		if ( $is_money ) {
			$extra['balance_money'] = \Wbcom\Credits\Credits::balance_money( 'wb-listora', (int) $data['user_id'] );
			$extra['currency']      = \Wbcom\Credits\Credits::resolve_money_currency( 'wb-listora', '' );
		}
		$response->set_data( $data + $extra );
		return $response;
	},
	10,
	3
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
require_once WB_LISTORA_PLUGIN_DIR . 'includes/workflow/renewal-helpers.php';
require_once WB_LISTORA_PLUGIN_DIR . 'includes/media-helpers.php';
require_once WB_LISTORA_PLUGIN_DIR . 'includes/import-export/migration-helpers.php';
// General-purpose helpers (wb_listora_is_bot_request, ...). Eager-required
// because call sites use the BARE FUNCTION (Analytics_Lite::is_bot,
// Anti_Spam::check) — a bare function call never triggers the class
// autoloader, so relying on Bot_Detection's own require_once would fatal
// on the first analytics view where the class hasn't loaded yet.
require_once WB_LISTORA_PLUGIN_DIR . 'includes/helpers.php';
// URL search-filter parsing shared by every block that renders a filtered set
// (grid, map). Procedural helpers, so eager-required rather than autoloaded.
require_once WB_LISTORA_PLUGIN_DIR . 'includes/search/search-url-helpers.php';

// Include the bundled SDK (multi-version safe - latest wins across all plugins).
// After the helpers: when a plugin is activated after plugins_loaded (fresh
// install, wp-admin activation) the SDK starts at once and calls this plugin's
// consumer config, which resolves page URLs through the helpers above.
// Required only when its src/ is present, so a partial package leaves credits
// inactive instead of fataling. The label stays untranslated: this runs before
// `init`, where __() triggers the "translation loading too early" notice.
wb_listora_require_bundled_lib(
	WB_LISTORA_PLUGIN_DIR . 'libs/wbcom-credits-sdk/wbcom-credits-sdk.php',
	WB_LISTORA_PLUGIN_DIR . 'libs/wbcom-credits-sdk/src/Versions.php',
	'Credits SDK'
);
// Privacy / GDPR policy helpers (wb_listora_get_erasure_map,
// wb_listora_is_account_deactivated). Eager-required for the same reason as
// helpers.php above — call sites use the BARE FUNCTION, which never triggers
// the class autoloader. Pro filters `wb_listora_erasure_map` to declare its own
// tables, so the map must exist before Pro boots.
require_once WB_LISTORA_PLUGIN_DIR . 'includes/privacy/privacy-helpers.php';

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
