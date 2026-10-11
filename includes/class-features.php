<?php
/**
 * Features — central feature toggle system for WB Listora Free.
 *
 * Single source of truth for whether a feature is enabled. The
 * `wb_listora_features` option is an array of feature_key => bool.
 * Read it via {@see wb_listora_feature_enabled()}; write it via
 * {@see wb_listora_set_feature()} or the Settings → Features tab.
 *
 * @package WBListora
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default feature toggles.
 *
 * Each key renders as a row in Settings → Features. A row carries
 * `category`, `label`, `description`, `default`, `icon` and `store` (the
 * option it saves to; omitted means `'free'`, i.e. `wb_listora_features`).
 * Add rows via the `wb_listora_features_registry` filter, name new categories
 * via `wb_listora_features_category_labels`, and persist your own option on
 * `wb_listora_save_features_extra`.
 *
 * @return array<string, array<string, mixed>>
 */
function wb_listora_features_registry() {
	$registry = array(
		// ─── Core ─────────────────────────────────────────────────────
		'submission'  => array(
			'category' => 'core',
			'store'    => 'free',
			'label'    => __( 'Listing Submission', 'wb-listora' ),
			'desc'     => __( 'Allow visitors and members to submit new listings from the frontend submission form.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'file-plus',
		),
		'reviews'     => array(
			'category' => 'core',
			'store'    => 'free',
			'label'    => __( 'Reviews & Ratings', 'wb-listora' ),
			'desc'     => __( 'Enable star ratings and review submissions on listing detail pages.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'message-circle',
		),
		'claims'      => array(
			'category' => 'core',
			'store'    => 'free',
			'label'    => __( 'Business Claims', 'wb-listora' ),
			'desc'     => __( 'Allow business owners to claim ownership of unverified listings.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'badge-check',
		),
		'favorites'   => array(
			'category' => 'core',
			'store'    => 'free',
			'label'    => __( 'Favorites', 'wb-listora' ),
			'desc'     => __( 'Let logged-in users save listings to a personal favorites list.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'heart',
		),
		'contact_form' => array(
			'category' => 'core',
			'store'    => 'free',
			'label'    => __( 'Contact Owner Form', 'wb-listora' ),
			'desc'     => __( 'Show a form on listing pages that emails the listing owner. With Pro\'s Lead Capture Form on, that form is shown instead. Off: no contact form at all, on the site or in the app.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'mail',
		),
		'renewal'     => array(
			'category' => 'core',
			'store'    => 'free',
			'label'    => __( 'Listing Renewal', 'wb-listora' ),
			'desc'     => __( 'Allow listing owners to renew expired listings from their dashboard.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'refresh-cw',
		),
		'owner_name'  => array(
			'category' => 'core',
			'store'    => 'free',
			'label'    => __( 'Show Who Listed It', 'wb-listora' ),
			'desc'     => __( 'Show a "Listed by" name on listing pages, the way Google Maps, Yelp and TripAdvisor do. Uses the listing\'s public contact name, or the account display name when there is none. Off: listings show no name anywhere, site or app.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'user',
		),
		'report_listings' => array(
			'category' => 'core',
			'store'    => 'free',
			'label'    => __( 'Report Listings', 'wb-listora' ),
			'desc'     => __( 'Let visitors flag inaccurate, spam, closed, or inappropriate listings for admin review.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'flag',
		),
		// ─── SEO & Meta ───────────────────────────────────────────────
		'schema'      => array(
			'category' => 'seo',
			'store'    => 'free',
			'label'    => __( 'Schema.org JSON-LD', 'wb-listora' ),
			'desc'     => __( 'Output structured data on listing pages (LocalBusiness, AggregateRating, etc.).', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'code',
		),
		'opengraph'   => array(
			'category' => 'seo',
			'store'    => 'free',
			'label'    => __( 'Open Graph + Twitter Cards', 'wb-listora' ),
			'desc'     => __( 'Generate social sharing meta tags so listings preview nicely on Facebook, Twitter/X, LinkedIn.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'share-2',
		),
		'breadcrumbs' => array(
			'category' => 'seo',
			'store'    => 'free',
			'label'    => __( 'Breadcrumbs', 'wb-listora' ),
			'desc'     => __( 'Show breadcrumb navigation on listing detail pages with BreadcrumbList schema.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'chevrons-right',
		),
		'sitemap'     => array(
			'category' => 'seo',
			'store'    => 'free',
			'label'    => __( 'Sitemap (XML)', 'wb-listora' ),
			'desc'     => __( 'Add listings to the XML sitemap so search engines can discover them.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'map',
		),
	);

	/**
	 * Filter the Free feature registry. Pro/3rd parties can register additional Free-side features here.
	 *
	 * @since 1.1.0
	 * @param array<string, array<string, mixed>> $registry Feature registry.
	 */
	return apply_filters( 'wb_listora_features_registry', $registry );
}

/**
 * Labels for the feature categories on Settings → Features.
 *
 * Every `category` used in {@see wb_listora_features_registry()} needs a label
 * here (add new ones via `wb_listora_features_category_labels`), otherwise the
 * raw slug is shown. Categories render in this array's order.
 *
 * @return array<string, string> Map of category slug => display label.
 */
function wb_listora_features_category_labels() {
	$labels = array(
		'core' => __( 'Core Features', 'wb-listora' ),
		'seo'  => __( 'SEO & Meta', 'wb-listora' ),
	);

	/**
	 * Filter the feature category label map.
	 *
	 * Pro / 3rd-party plugins name their own categories here so the single
	 * Features screen can render a clear heading for each group.
	 *
	 * @since 1.1.0
	 * @param array<string, string> $labels Map of category slug => label.
	 */
	return apply_filters( 'wb_listora_features_category_labels', $labels );
}

/**
 * Default feature flags (key => bool).
 *
 * @return array<string, bool>
 */
function wb_listora_default_features() {
	$registry = wb_listora_features_registry();
	$defaults = array();
	foreach ( $registry as $key => $config ) {
		$defaults[ $key ] = ! empty( $config['default'] );
	}

	/**
	 * Filter the Free default feature flags.
	 *
	 * @since 1.1.0
	 * @param array<string, bool> $defaults Map of feature key => default enabled.
	 */
	return apply_filters( 'wb_listora_default_features', $defaults );
}

/**
 * Get the resolved feature flags array.
 *
 * Reads from the `wb_listora_features` option and back-fills any
 * missing keys with their registry defaults so newly-registered
 * features turn on automatically.
 *
 * @return array<string, bool>
 */
function wb_listora_get_features() {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$defaults = wb_listora_default_features();
	$stored   = get_option( 'wb_listora_features', null );

	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	// Back-fill any registry keys missing from storage with their defaults
	// so a newly-shipped feature defaults to its registry value rather than off.
	$resolved = array();
	foreach ( $defaults as $key => $default_enabled ) {
		$resolved[ $key ] = array_key_exists( $key, $stored )
			? (bool) $stored[ $key ]
			: (bool) $default_enabled;
	}

	$cache = $resolved;
	return $cache;
}

/**
 * Check if a feature is enabled.
 *
 * @param string $key Feature key.
 * @return bool
 */
function wb_listora_feature_enabled( $key ) {
	$features = wb_listora_get_features();
	$enabled  = isset( $features[ $key ] ) ? (bool) $features[ $key ] : true;

	/**
	 * Filter whether a specific Free feature is enabled.
	 *
	 * @since 1.1.0
	 * @param bool   $enabled Whether the feature is enabled.
	 * @param string $key     Feature key.
	 */
	return (bool) apply_filters( "wb_listora_feature_{$key}_enabled", $enabled, $key );
}

/**
 * Update a feature's enabled state.
 *
 * @param string $key     Feature key.
 * @param bool   $enabled New state.
 * @return void
 */
function wb_listora_set_feature( $key, $enabled ) {
	$features         = wb_listora_get_features();
	$features[ $key ] = (bool) $enabled;
	update_option( 'wb_listora_features', $features );

	// Bust static cache.
	$GLOBALS['wb_listora_features_cache_bust'] = microtime( true );
}

/**
 * Resolve whether a listing is verified.
 *
 * Use this instead of reading `_listora_is_verified` directly: the meta can
 * outlive Pro's verification feature, and Pro hooks `wb_listora_is_verified`
 * to return false when that feature is off.
 *
 * @since 1.1.0
 *
 * @param int $post_id Listing post ID.
 * @return bool Whether the listing should be treated as verified.
 */
function wb_listora_is_verified( $post_id ) {
	$post_id  = (int) $post_id;
	$verified = $post_id > 0 && (bool) get_post_meta( $post_id, '_listora_is_verified', true );

	/**
	 * Filter whether a listing is verified.
	 *
	 * Pro returns false here when its verification feature is disabled so the
	 * verified badge/flag never renders without the feature behind it.
	 *
	 * @since 1.1.0
	 * @param bool $verified Whether the stored meta marks the listing verified.
	 * @param int  $post_id  Listing post ID.
	 */
	return (bool) apply_filters( 'wb_listora_is_verified', $verified, $post_id );
}

/**
 * Whether a popular third-party SEO plugin is active.
 *
 * When Yoast SEO, Rank Math, AIOSEO or SEOPress is active it owns the title,
 * meta description, canonical, Open Graph and JSON-LD, so every Listora head
 * emitter (Free and Pro) checks this one helper and stays out to avoid
 * duplicate tags. Detection uses version constants or bootstrap classes, so
 * load order does not matter.
 *
 * @since 1.1.0
 *
 * @return bool True when a known SEO plugin owns the page meta.
 */
function wb_listora_seo_plugin_active() {
	$active = (
		// Yoast SEO.
		defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' )
		// Rank Math.
		|| defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' )
		// All in One SEO (AIOSEO).
		|| function_exists( 'aioseo' ) || defined( 'AIOSEO_VERSION' )
		// SEOPress.
		|| defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_get_service' )
	);

	/**
	 * Filter whether a third-party SEO plugin owns the page meta.
	 *
	 * Lets a site force-declare an SEO plugin we don't auto-detect (return true
	 * to suppress Listora's head injection) or force Listora to keep injecting
	 * (return false) when the site routes meta through a custom layer.
	 *
	 * @since 1.1.0
	 * @param bool $active Whether a known SEO plugin is active.
	 */
	return (bool) apply_filters( 'wb_listora_seo_plugin_active', $active );
}

// Bootstrap: ensure the option exists on first request.
add_action(
	'init',
	static function () {
		if ( null === get_option( 'wb_listora_features', null ) ) {
			update_option( 'wb_listora_features', wb_listora_default_features() );
		}
	},
	1
);
