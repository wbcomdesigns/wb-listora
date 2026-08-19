<?php
/**
 * Unit tests for the map tile URL sanitizer.
 *
 * A tile template is not an ordinary URL. Leaflet substitutes {z}/{x}/{y} at
 * request time, so the curly braces have to survive sanitization. esc_url_raw()
 * strips them, which shipped a value that looked saved but 404'd every tile
 * (BC 10217195006). These tests pin both halves of the contract: placeholders
 * survive, hostile schemes do not.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;
use WBListora\Admin\Settings_Page;

/**
 * @group listora
 * @group maps
 */
class MapTileUrlSanitizeTest extends WP_UnitTestCase {

	/**
	 * The placeholders Leaflet needs must survive sanitization.
	 *
	 * @dataProvider placeholder_templates
	 *
	 * @param string $template Tile template to sanitize.
	 * @return void
	 */
	public function test_placeholders_survive( $template ) {
		$this->assertSame( $template, wb_listora_sanitize_tile_url( $template ) );
	}

	/**
	 * Tile templates that must round-trip unchanged.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function placeholder_templates() {
		return array(
			'plain xyz'          => array( 'https://tiles.example.com/{z}/{x}/{y}.png' ),
			'subdomain + retina' => array( 'https://{s}.tile.example.com/{z}/{x}/{y}{r}.png' ),
			'api key query'      => array( 'https://api.maptiler.com/maps/streets/{z}/{x}/{y}.png?key=ABC123' ),
			'http scheme'        => array( 'http://tiles.example.com/{z}/{x}/{y}.png' ),
		);
	}

	/**
	 * Anything that is not an http(s) URL is rejected outright.
	 *
	 * @dataProvider rejected_values
	 *
	 * @param string $value Value to sanitize.
	 * @return void
	 */
	public function test_non_http_values_are_rejected( $value ) {
		$this->assertSame( '', wb_listora_sanitize_tile_url( $value ) );
	}

	/**
	 * Values that must never be stored.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function rejected_values() {
		return array(
			'javascript scheme' => array( 'javascript:alert(1)' ),
			'ftp scheme'        => array( 'ftp://tiles.example.com/{z}/{x}/{y}.png' ),
			'empty'             => array( '' ),
			'whitespace only'   => array( '   ' ),
		);
	}

	/**
	 * Surrounding whitespace is trimmed rather than percent-encoded into the URL.
	 *
	 * @return void
	 */
	public function test_surrounding_whitespace_is_trimmed() {
		$this->assertSame(
			'https://tiles.example.com/{z}/{x}/{y}.png',
			wb_listora_sanitize_tile_url( '  https://tiles.example.com/{z}/{x}/{y}.png  ' )
		);
	}

	/**
	 * The settings screen save path keeps the placeholders.
	 *
	 * This is the path the bug actually shipped on: the sanitizer can be right
	 * while the settings screen still calls esc_url_raw().
	 *
	 * @return void
	 */
	public function test_settings_save_path_keeps_placeholders() {
		$clean = Settings_Page::sanitize(
			array( 'map_tile_url' => 'https://tiles.example.com/{z}/{x}/{y}.png' )
		);

		$this->assertSame( 'https://tiles.example.com/{z}/{x}/{y}.png', $clean['map_tile_url'] );
	}

	/**
	 * The settings screen save path still refuses a hostile scheme.
	 *
	 * @return void
	 */
	public function test_settings_save_path_rejects_hostile_scheme() {
		$clean = Settings_Page::sanitize( array( 'map_tile_url' => 'javascript:alert(1)' ) );

		$this->assertSame( '', $clean['map_tile_url'] );
	}
}
