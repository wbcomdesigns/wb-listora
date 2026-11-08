<?php
/**
 * A fresh Setup Wizard run opens on defaults that work: a demo pack matching the
 * chosen listing types and a map that already has a tile source.
 *
 * @package WBListora\Tests
 */

use WBListora\Admin\Setup_Wizard;

/**
 * @group listora
 * @group settings
 */
class WizardFirstRunDefaultsTest extends WP_UnitTestCase {

	private function render( string $method, array $data ): string {
		$wizard = ( new ReflectionClass( Setup_Wizard::class ) )->newInstanceWithoutConstructor();
		ob_start();
		( new ReflectionMethod( Setup_Wizard::class, $method ) )->invoke( $wizard, $data );
		return (string) ob_get_clean();
	}

	public function test_demo_pack_defaults_to_the_first_chosen_type() {
		$html = $this->render( 'render_step_demo', array( 'selected_types' => array( 'business', 'restaurant' ) ) );

		$this->assertMatchesRegularExpression( '/value="restaurant"[^>]*checked/', $html );
	}

	public function test_job_type_maps_to_the_job_board_pack() {
		$html = $this->render( 'render_step_demo', array( 'selected_types' => array( 'job' ) ) );

		$this->assertMatchesRegularExpression( '/value="job-board"[^>]*checked/', $html );
	}

	public function test_demo_pack_falls_back_to_general_and_keeps_an_explicit_choice() {
		$this->assertMatchesRegularExpression(
			'/value="general"[^>]*checked/',
			$this->render( 'render_step_demo', array( 'selected_types' => array( 'business' ) ) )
		);
		$this->assertMatchesRegularExpression(
			'/value="hotel"[^>]*checked/',
			$this->render( 'render_step_demo', array( 'selected_types' => array( 'restaurant' ), 'demo_pack' => 'hotel' ) )
		);
	}

	public function test_map_step_prefills_a_tile_source_on_a_fresh_site() {
		update_option( 'wb_listora_settings', array( 'map_tile_url' => '' ) );

		$html = $this->render( 'render_step_maps', array() );

		$this->assertStringContainsString( 'value="https://tile.openstreetmap.org/{z}/{x}/{y}.png"', $html );
	}

	public function test_map_step_keeps_a_tile_source_the_owner_already_chose() {
		$html = $this->render( 'render_step_maps', array( 'map_tile_url' => 'https://tiles.example.com/{z}/{x}/{y}.png' ) );

		$this->assertStringContainsString( 'value="https://tiles.example.com/{z}/{x}/{y}.png"', $html );
		$this->assertStringNotContainsString( 'value="https://tile.openstreetmap.org', $html );
	}
}
