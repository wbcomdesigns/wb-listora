<?php
/**
 * One map provider (OpenStreetMap, Google, Kakao...).
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for a map provider. Register one through the
 * `wb_listora_map_providers` filter; no core edit is needed to add a provider.
 */
abstract class Map_Provider {

	/** Leaflet draws the map from a tile URL. */
	const KIND_TILES = 'tiles';

	/** The provider ships its own JS map (Google, Kakao...). */
	const KIND_SDK = 'sdk';

	/**
	 * Stable slug stored in the `map_provider` setting.
	 *
	 * @return string
	 */
	abstract public function slug(): string;

	/**
	 * Name shown in Settings > Maps and the setup wizard.
	 *
	 * @return string
	 */
	abstract public function label(): string;

	/**
	 * One of the KIND_* constants.
	 *
	 * @return string
	 */
	public function kind(): string {
		return self::KIND_TILES;
	}

	/**
	 * Whether the provider can serve a map right now (its key is saved).
	 * A provider that is not ready falls back to OpenStreetMap.
	 *
	 * @return bool
	 */
	public function is_ready(): bool {
		return true;
	}

	/**
	 * Setting that holds the browser key, or '' when the provider needs none.
	 *
	 * @return string
	 */
	public function key_setting(): string {
		return '';
	}

	/**
	 * Convert stored WGS-84 coordinates to the system the provider draws in.
	 * Identity except for providers such as AMap (GCJ-02).
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array{0: float, 1: float}
	 */
	public function to_display_coords( float $lat, float $lng ): array {
		return array( $lat, $lng );
	}
}
