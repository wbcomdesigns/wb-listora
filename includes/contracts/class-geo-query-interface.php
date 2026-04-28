<?php
/**
 * Geo Query contract.
 *
 * Public surface for Pro / extensions to run geospatial computations such as
 * Haversine distance, bounding boxes, and geohash encoding.
 *
 * Resolve via:
 *   $geo = wb_listora_service( 'geo_query' );
 *
 * The underlying \WBListora\Search\Geo_Query class uses static methods. The
 * service-locator instance is a thin proxy so consumers don't import the
 * concrete class directly.
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Geo Query contract.
 */
interface Geo_Query_Interface {

	/**
	 * Encode a coordinate pair as a geohash.
	 *
	 * @param float $lat       Latitude.
	 * @param float $lng       Longitude.
	 * @param int   $precision Geohash precision (default 8).
	 * @return string
	 */
	public function encode_geohash( $lat, $lng, $precision = 8 );

	/**
	 * Calculate Haversine distance between two points.
	 *
	 * @param float  $lat1 Latitude 1.
	 * @param float  $lng1 Longitude 1.
	 * @param float  $lat2 Latitude 2.
	 * @param float  $lng2 Longitude 2.
	 * @param string $unit 'km' or 'mi'.
	 * @return float
	 */
	public function haversine_distance( $lat1, $lng1, $lat2, $lng2, $unit = 'km' );

	/**
	 * Bounding box around a centre point.
	 *
	 * @param float  $lat    Center latitude.
	 * @param float  $lng    Center longitude.
	 * @param float  $radius Radius.
	 * @param string $unit   'km' or 'mi'.
	 * @return array { min_lat, max_lat, min_lng, max_lng }
	 */
	public function bounding_box( $lat, $lng, $radius, $unit = 'km' );
}
