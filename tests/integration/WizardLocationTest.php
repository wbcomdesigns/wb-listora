<?php
/**
 * The setup wizard keeps the country and city it asks for.
 *
 * @package WBListora\Tests
 */

use WBListora\Admin\Setup_Wizard;

/**
 * @group listora
 * @group settings
 */
class WizardLocationTest extends WP_UnitTestCase {

	private function finalize( array $data ): array {
		$wizard = ( new ReflectionClass( Setup_Wizard::class ) )->newInstanceWithoutConstructor();
		$method = new ReflectionMethod( Setup_Wizard::class, 'finalize_setup' );
		$method->setAccessible( true );
		$method->invoke( $wizard, $data );
		return (array) get_option( 'wb_listora_settings', array() );
	}

	public function test_country_and_city_are_saved_without_moving_the_map() {
		update_option( 'wb_listora_settings', array( 'map_default_lat' => 12.5, 'map_default_lng' => 7.5 ) );

		$s = $this->finalize( array( 'country' => 'Canada', 'city' => 'Toronto', 'is_global' => true ) );

		$this->assertSame( 'Canada', $s['default_country'] );
		$this->assertSame( 'Toronto', $s['default_city'] );
		$this->assertSame( 12.5, $s['map_default_lat'], 'A global directory keeps its map center.' );
		$this->assertSame( 'Canada', rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/settings/app-config' ) )->get_data()['default_country'] );
	}

	public function test_a_run_that_skipped_the_location_step_keeps_the_saved_values() {
		update_option( 'wb_listora_settings', array( 'default_country' => 'Kenya', 'default_city' => 'Nairobi' ) );

		$s = $this->finalize( array( 'map_provider' => 'osm' ) );

		$this->assertSame( 'Kenya', $s['default_country'] );
		$this->assertSame( 'Nairobi', $s['default_city'] );
	}
}
