<?php
/**
 * Geo Query — spatial calculations and queries.
 *
 * @package WBListora\Search
 */

namespace WBListora\Search;

defined( 'ABSPATH' ) || exit;

/**
 * Provides geospatial query utilities.
 */
class Geo_Query {

	/**
	 * Earth's radius in kilometers.
	 */
	const EARTH_RADIUS_KM = 6371;

	/**
	 * Earth's radius in miles.
	 */
	const EARTH_RADIUS_MI = 3959;

	/**
	 * Calculate Haversine distance between two points.
	 *
	 * @param float  $lat1 Latitude of point 1.
	 * @param float  $lng1 Longitude of point 1.
	 * @param float  $lat2 Latitude of point 2.
	 * @param float  $lng2 Longitude of point 2.
	 * @param string $unit 'km' or 'mi'.
	 * @return float Distance in specified unit.
	 */
	public static function haversine_distance( $lat1, $lng1, $lat2, $lng2, $unit = 'km' ) {
		$radius = ( 'mi' === $unit ) ? self::EARTH_RADIUS_MI : self::EARTH_RADIUS_KM;

		$d_lat = deg2rad( $lat2 - $lat1 );
		$d_lng = deg2rad( $lng2 - $lng1 );

		$a = sin( $d_lat / 2 ) * sin( $d_lat / 2 )
			+ cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) )
			* sin( $d_lng / 2 ) * sin( $d_lng / 2 );

		$c = 2 * asin( sqrt( $a ) );

		return $radius * $c;
	}

	/**
	 * Calculate a bounding box around a center point.
	 *
	 * @param float  $lat    Center latitude.
	 * @param float  $lng    Center longitude.
	 * @param float  $radius Radius.
	 * @param string $unit   'km' or 'mi'.
	 * @return array { min_lat, max_lat, min_lng, max_lng }
	 */
	public static function bounding_box( $lat, $lng, $radius, $unit = 'km' ) {
		$earth_radius = ( 'mi' === $unit ) ? self::EARTH_RADIUS_MI : self::EARTH_RADIUS_KM;

		$lat_rad = deg2rad( $lat );
		$lng_rad = deg2rad( $lng );
		$ang_rad = $radius / $earth_radius;

		$min_lat = rad2deg( $lat_rad - $ang_rad );
		$max_lat = rad2deg( $lat_rad + $ang_rad );

		$delta_lng = asin( sin( $ang_rad ) / cos( $lat_rad ) );
		$min_lng   = rad2deg( $lng_rad - $delta_lng );
		$max_lng   = rad2deg( $lng_rad + $delta_lng );

		return array(
			'min_lat' => $min_lat,
			'max_lat' => $max_lat,
			'min_lng' => $min_lng,
			'max_lng' => $max_lng,
		);
	}

	/**
	 * Encode a geohash from lat/lng.
	 *
	 * @param float $lat       Latitude.
	 * @param float $lng       Longitude.
	 * @param int   $precision Geohash precision (default 8).
	 * @return string Geohash string.
	 */
	public static function encode_geohash( $lat, $lng, $precision = 8 ) {
		$chars  = '0123456789bcdefghjkmnpqrstuvwxyz';
		$bits   = array( 16, 8, 4, 2, 1 );
		$hash   = '';
		$is_lng = true;

		$lat_range = array( -90.0, 90.0 );
		$lng_range = array( -180.0, 180.0 );

		$bit  = 0;
		$char = 0;

		while ( strlen( $hash ) < $precision ) {
			if ( $is_lng ) {
				$mid = ( $lng_range[0] + $lng_range[1] ) / 2;
				if ( $lng > $mid ) {
					$char        |= $bits[ $bit ];
					$lng_range[0] = $mid;
				} else {
					$lng_range[1] = $mid;
				}
			} else {
				$mid = ( $lat_range[0] + $lat_range[1] ) / 2;
				if ( $lat > $mid ) {
					$char        |= $bits[ $bit ];
					$lat_range[0] = $mid;
				} else {
					$lat_range[1] = $mid;
				}
			}

			$is_lng = ! $is_lng;
			++$bit;

			if ( 5 === $bit ) {
				$hash .= $chars[ $char ];
				$bit   = 0;
				$char  = 0;
			}
		}

		return $hash;
	}

	/**
	 * Find listings within a radius of a point.
	 *
	 * @param float  $lat    Center latitude.
	 * @param float  $lng    Center longitude.
	 * @param float  $radius Radius.
	 * @param string $unit   'km' or 'mi'.
	 * @param int    $limit  Max results.
	 * @return array Array of { listing_id, distance }.
	 */
	public static function find_nearby( $lat, $lng, $radius, $unit = 'km', $limit = 100 ) {
		global $wpdb;

		$prefix = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX;
		$bbox   = self::bounding_box( $lat, $lng, $radius, $unit );

		// Phase 1: Bounding box pre-filter.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT listing_id, lat, lng FROM {$prefix}geo
			WHERE lat BETWEEN %f AND %f
			AND lng BETWEEN %f AND %f",
				$bbox['min_lat'],
				$bbox['max_lat'],
				$bbox['min_lng'],
				$bbox['max_lng']
			),
			ARRAY_A
		);

		// Phase 2: Exact Haversine distance.
		$results = array();
		foreach ( $rows as $row ) {
			$dist = self::haversine_distance(
				$lat,
				$lng,
				(float) $row['lat'],
				(float) $row['lng'],
				$unit
			);

			if ( $dist <= $radius ) {
				$results[] = array(
					'listing_id' => (int) $row['listing_id'],
					'distance'   => round( $dist, 2 ),
				);
			}
		}

		// Sort by distance.
		usort(
			$results,
			function ( $a, $b ) {
				return $a['distance'] <=> $b['distance'];
			}
		);

		return array_slice( $results, 0, $limit );
	}
}
