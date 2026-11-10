<?php
/**
 * Plus Codes: encode, decode and search by code.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Plus_Code;

/**
 * @group listora
 * @group search
 */
class PlusCodeTest extends WP_UnitTestCase {

	public function test_known_places_encode_to_their_published_codes() {
		$this->assertSame( '849VCWC8+R9', Plus_Code::encode( 37.4220, -122.0841 ) );
		$this->assertSame( '8FVC9G8F+6W', Plus_Code::encode( 47.3655625, 8.5248125 ) );
	}

	public function test_decode_returns_the_cell_centre() {
		$p = Plus_Code::decode( '8fvc9g8f+6w' );

		$this->assertEqualsWithDelta( 47.3655625, $p['lat'], 0.00001 );
		$this->assertEqualsWithDelta( 8.5248125, $p['lng'], 0.00001 );
	}

	public function test_round_trip_stays_in_the_same_cell() {
		foreach ( array( array( 0.0, 0.0 ), array( -33.8688, 151.2093 ), array( 89.9999, 179.9999 ), array( -89.99, -179.99 ) ) as $point ) {
			$code = Plus_Code::encode( $point[0], $point[1] );
			$back = Plus_Code::decode( $code );

			$this->assertSame( $code, Plus_Code::encode( $back['lat'], $back['lng'] ), "round trip for $code" );
		}
	}

	public function test_text_that_is_not_a_full_code_is_not_a_place() {
		foreach ( array( 'pizza', '9G8F+6W', '9G8F+6W Zurich', '8FVC9G8F6W', '8FVC9G8F+6', 'ZZZZZZZZ+ZZ' ) as $text ) {
			$this->assertNull( Plus_Code::decode( $text ), $text );
		}
	}

	public function test_a_plus_code_keyword_becomes_a_nearby_search() {
		$engine = wb_listora_service( 'search_engine' );
		$parse  = new ReflectionMethod( $engine, 'parse_args' );

		$args = $parse->invoke( $engine, array( 'keyword' => '8FVC9G8F+6W' ) );

		$this->assertSame( '', $args['keyword'] );
		$this->assertEqualsWithDelta( 47.3655625, $args['lat'], 0.00001 );
		$this->assertGreaterThan( 0, $args['radius'] );
	}

	public function test_an_explicit_centre_wins_over_the_code() {
		$engine = wb_listora_service( 'search_engine' );
		$parse  = new ReflectionMethod( $engine, 'parse_args' );

		$args = $parse->invoke( $engine, array( 'keyword' => '8FVC9G8F+6W', 'lat' => 10.0, 'lng' => 20.0 ) );

		$this->assertSame( 10.0, $args['lat'] );
		$this->assertSame( '8FVC9G8F+6W', $args['keyword'] );
	}
}
