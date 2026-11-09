<?php
/**
 * OpenStreetMap: the default provider. Needs no key; the tile source is
 * chosen in Settings > Maps.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * OpenStreetMap / tile-URL provider.
 */
class Osm_Map_Provider extends Map_Provider {

	/**
	 * @return string
	 */
	public function slug(): string {
		return 'osm';
	}

	/**
	 * @return string
	 */
	public function label(): string {
		return __( 'OpenStreetMap', 'wb-listora' );
	}
}
