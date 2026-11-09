<?php
/**
 * The map provider registry: one rule for which provider is live.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Map_Provider;
use WBListora\Core\Map_Provider_Registry;

/**
 * @group listora
 * @group maps
 */
class MapProviderRegistryTest extends WP_UnitTestCase {

	private function fake( bool $ready ): Map_Provider {
		return new class( $ready ) extends Map_Provider {
			public function __construct( private bool $ready ) {}
			public function slug(): string {
				return 'fake';
			}
			public function label(): string {
				return 'Fake';
			}
			public function is_ready(): bool {
				return $this->ready;
			}
		};
	}

	private function register( Map_Provider $p ): void {
		add_filter(
			'wb_listora_map_providers',
			static function ( $all ) use ( $p ) {
				$all[ $p->slug() ] = $p;
				return $all;
			}
		);
	}

	public function tear_down() {
		remove_all_filters( 'wb_listora_map_providers' );
		remove_all_filters( 'wb_listora_map_provider' );
		delete_option( 'wb_listora_settings' );
		wb_listora_get_setting( null, null, true );
		parent::tear_down();
	}

	public function test_osm_is_always_registered_first() {
		$this->register( $this->fake( true ) );

		$this->assertSame( array( 'osm', 'fake' ), array_keys( Map_Provider_Registry::all() ) );
	}

	public function test_a_provider_without_a_key_falls_back_to_osm() {
		$this->register( $this->fake( false ) );

		$this->assertSame( 'osm', Map_Provider_Registry::resolve( 'fake' ) );
	}

	public function test_a_ready_provider_is_kept() {
		$this->register( $this->fake( true ) );

		$this->assertSame( 'fake', Map_Provider_Registry::resolve( 'fake' ) );
	}

	public function test_an_unregistered_slug_from_the_old_filter_passes_through() {
		add_filter( 'wb_listora_map_provider', static fn() => 'legacy' );
		wb_listora_get_setting( null, null, true );

		$this->assertSame( 'legacy', wb_listora_get_setting( 'map_provider' ) );
	}

	public function test_the_setting_getter_applies_the_fallback() {
		$this->register( $this->fake( false ) );
		update_option( 'wb_listora_settings', array( 'map_provider' => 'fake' ) );
		wb_listora_get_setting( null, null, true );

		$this->assertSame( 'osm', wb_listora_get_setting( 'map_provider' ) );
	}

	public function test_coordinates_are_unchanged_by_default() {
		$this->assertSame( array( 12.5, 77.5 ), ( new Map_Provider_Registry() )::get( 'osm' )->to_display_coords( 12.5, 77.5 ) );
	}
}
