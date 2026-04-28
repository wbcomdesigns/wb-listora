<?php
/**
 * Geo query service — instance proxy over \WBListora\Search\Geo_Query.
 *
 * Resolved via wb_listora_service( 'geo_query' ). Implements
 * {@see \WBListora\Contracts\Geo_Query_Interface}.
 *
 * @package WBListora\Services
 */

namespace WBListora\Services;

use WBListora\Contracts\Geo_Query_Interface;
use WBListora\Search\Geo_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Instance proxy over the static Geo_Query helpers.
 */
class Geo_Query_Service implements Geo_Query_Interface {

	/**
	 * {@inheritdoc}
	 */
	public function encode_geohash( $lat, $lng, $precision = 8 ) {
		return Geo_Query::encode_geohash( $lat, $lng, $precision );
	}

	/**
	 * {@inheritdoc}
	 */
	public function haversine_distance( $lat1, $lng1, $lat2, $lng2, $unit = 'km' ) {
		return Geo_Query::haversine_distance( $lat1, $lng1, $lat2, $lng2, $unit );
	}

	/**
	 * {@inheritdoc}
	 */
	public function bounding_box( $lat, $lng, $radius, $unit = 'km' ) {
		return Geo_Query::bounding_box( $lat, $lng, $radius, $unit );
	}
}
