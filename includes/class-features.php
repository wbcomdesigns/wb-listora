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
 * Every key here renders as a row in the Settings → Features tab.
 * Each toggle has a `category`, `label`, `description`, `default`,
 * and `icon`.
 *
 * @return array<string, array<string, mixed>>
 */
function wb_listora_features_registry() {
	$registry = array(
		// ─── Core ─────────────────────────────────────────────────────
		'submission'  => array(
			'category' => 'core',
			'label'    => __( 'Listing Submission', 'wb-listora' ),
			'desc'     => __( 'Allow visitors and members to submit new listings from the frontend submission form.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'file-plus',
		),
		'reviews'     => array(
			'category' => 'core',
			'label'    => __( 'Reviews & Ratings', 'wb-listora' ),
			'desc'     => __( 'Enable star ratings and review submissions on listing detail pages.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'message-circle',
		),
		'claims'      => array(
			'category' => 'core',
			'label'    => __( 'Business Claims', 'wb-listora' ),
			'desc'     => __( 'Allow business owners to claim ownership of unverified listings.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'badge-check',
		),
		'favorites'   => array(
			'category' => 'core',
			'label'    => __( 'Favorites', 'wb-listora' ),
			'desc'     => __( 'Let logged-in users save listings to a personal favorites list.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'heart',
		),
		'renewal'     => array(
			'category' => 'core',
			'label'    => __( 'Listing Renewal', 'wb-listora' ),
			'desc'     => __( 'Allow listing owners to renew expired listings from their dashboard.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'refresh-cw',
		),
		// ─── SEO & Meta ───────────────────────────────────────────────
		'schema'      => array(
			'category' => 'seo',
			'label'    => __( 'Schema.org JSON-LD', 'wb-listora' ),
			'desc'     => __( 'Output structured data on listing pages (LocalBusiness, AggregateRating, etc.).', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'code',
		),
		'opengraph'   => array(
			'category' => 'seo',
			'label'    => __( 'Open Graph + Twitter Cards', 'wb-listora' ),
			'desc'     => __( 'Generate social sharing meta tags so listings preview nicely on Facebook, Twitter/X, LinkedIn.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'share-2',
		),
		'breadcrumbs' => array(
			'category' => 'seo',
			'label'    => __( 'Breadcrumbs', 'wb-listora' ),
			'desc'     => __( 'Show breadcrumb navigation on listing detail pages with BreadcrumbList schema.', 'wb-listora' ),
			'default'  => true,
			'icon'     => 'chevrons-right',
		),
		'sitemap'     => array(
			'category' => 'seo',
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
