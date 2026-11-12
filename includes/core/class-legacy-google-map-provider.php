<?php
/**
 * Google Maps as drawn by a Pro older than 2.1.1.
 *
 * Pro registers its own Google provider from 2.1.1. Pro 2.1.0 draws Google
 * maps but predates the provider registry, so without this entry Free saw a
 * Google site as having no map background and blanked the map block. A site
 * whose Pro licence lapsed can stay on that pair indefinitely. Registered only
 * while such a Pro is active.
 *
 * @package WBListora\Core
 * @since 2.1.1
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Google Maps entry for a Pro that predates the registry.
 */
class Legacy_Google_Map_Provider extends Map_Provider {

	/**
	 * Provider slug.
	 */
	public function slug(): string {
		return 'google';
	}

	/**
	 * Display name.
	 */
	public function label(): string {
		return __( 'Google Maps', 'wb-listora' );
	}

	/**
	 * Google draws its own map, so no raster tiles are needed.
	 */
	public function kind(): string {
		return self::KIND_SDK;
	}

	/**
	 * Setting that holds the key.
	 */
	public function key_setting(): string {
		return 'google_maps_key';
	}

	/**
	 * Ready once a key is saved, the same rule Pro 2.1.0 applied.
	 */
	public function is_ready(): bool {
		return '' !== (string) wb_listora_get_setting( 'google_maps_key', '' );
	}
}
