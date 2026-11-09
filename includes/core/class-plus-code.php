<?php
/**
 * Plus Codes (Open Location Code): a short address made of letters and digits,
 * for places without a street address. Ten digits name a cell about 14 m wide.
 *
 * Pure arithmetic - no provider, no network.
 *
 * @package WBListora\Core
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Plus Code encode and decode.
 */
class Plus_Code {

	const ALPHABET = '23456789CFGHJMPQRVWX';

	/**
	 * Coordinates to a ten-digit code, e.g. "8FVC9G8F+6W".
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return string
	 */
	public static function encode( float $lat, float $lng ): string {
		$lat = min( 90.0, max( -90.0, $lat ) );
		$lng = fmod( fmod( $lng + 180.0, 360.0 ) + 360.0, 360.0 ) - 180.0;

		// 1/8000 degree units; the cell below the top edge keeps latitude 90 valid.
		$lat_val = min( 180 * 8000 - 1, (int) floor( round( ( $lat + 90.0 ) * 2.5e7 ) / 3125 ) );
		$lng_val = (int) floor( round( ( $lng + 180.0 ) * 8.192e6 ) / 1024 );

		$pairs = '';
		for ( $i = 0; $i < 5; $i++ ) {
			$pairs   = self::ALPHABET[ $lat_val % 20 ] . self::ALPHABET[ $lng_val % 20 ] . $pairs;
			$lat_val = intdiv( $lat_val, 20 );
			$lng_val = intdiv( $lng_val, 20 );
		}

		return substr( $pairs, 0, 8 ) . '+' . substr( $pairs, 8 );
	}

	/**
	 * Centre of the cell a full code names, or null when the text is not a
	 * full code. Short codes ("9G8F+6W Zurich") need a reference place and are
	 * not accepted.
	 *
	 * @param string $code Candidate text.
	 * @return array{lat: float, lng: float}|null
	 */
	public static function decode( string $code ): ?array {
		$code = strtoupper( trim( $code ) );
		if ( ! preg_match( '/^[' . self::ALPHABET . ']{8}\+[' . self::ALPHABET . ']{2,7}$/', $code ) ) {
			return null;
		}

		$digits = str_replace( '+', '', $code );
		$lat    = 0;
		$lng    = 0;
		for ( $i = 0; $i < 10; $i += 2 ) {
			$lat = $lat * 20 + strpos( self::ALPHABET, $digits[ $i ] );
			$lng = $lng * 20 + strpos( self::ALPHABET, $digits[ $i + 1 ] );
		}

		// 20 steps of 20 degrees cover 400, so a first digit past the real range is not a place.
		if ( $lat >= 180 * 8000 || $lng >= 360 * 8000 ) {
			return null;
		}

		return array(
			'lat' => round( ( $lat + 0.5 ) / 8000 - 90, 7 ),
			'lng' => round( ( $lng + 0.5 ) / 8000 - 180, 7 ),
		);
	}
}
