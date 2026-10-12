<?php
/**
 * Regression checks for the 2026-10-12 fix batch: Health Check tables, page
 * clamping, display name, file fields, card layouts.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class BatchFixesTest extends WP_UnitTestCase {

	public function test_health_check_lists_every_table_the_plugin_creates() {
		$required = ( new ReflectionClassConstant( \WBListora\Admin\Health_Check::class, 'REQUIRED_TABLES' ) )->getValue();
		$source   = file_get_contents( WB_LISTORA_PLUGIN_DIR . 'includes/class-activator.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reading plugin source in a test.
		preg_match_all( '/CREATE TABLE \{\$prefix\}([a-z_]+)/', $source, $m );
		$this->assertGreaterThan( 10, count( $m[1] ) );
		foreach ( array_merge( $m[1], array( 'leads' ) ) as $table ) { // Leads creates its own table.
			$this->assertContains( $table, $required, "Health Check misses {$table}." );
		}
	}

	public function test_a_page_past_the_end_moves_to_the_last_page() {
		$state = array( 'paged' => 99, 'per_page' => 20, 'offset' => 1960 );
		$this->assertTrue( wb_listora_clamp_paged( $state, 45 ) );
		$this->assertSame( 3, $state['paged'] );
		$this->assertSame( 40, $state['offset'] );

		$state = array( 'paged' => 2, 'per_page' => 20, 'offset' => 20 );
		$this->assertFalse( wb_listora_clamp_paged( $state, 45 ), 'A real page is left alone.' );
		$this->assertFalse( wb_listora_clamp_paged( $state, 0 ), 'An empty list has no last page to move to.' );
	}

	public function test_profile_refuses_an_empty_display_name() {
		$user = self::factory()->user->create( array( 'display_name' => 'Ada Owner' ) );
		wp_set_current_user( $user );
		$request = new WP_REST_Request( 'PUT', '/listora/v1/dashboard/profile' );
		$request->set_body_params( array( 'display_name' => '  ' ) );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'Ada Owner', get_userdata( $user )->display_name );
	}

	public function test_a_file_field_shows_its_file_name() {
		$file  = self::factory()->attachment->create( array( 'post_mime_type' => 'image/png', 'file' => 'company-logo.png' ) );
		$field = new \WBListora\Core\Field( array( 'key' => 'company_logo', 'label' => 'Company Logo', 'type' => 'file' ) );

		$this->assertSame( 'company-logo.png', wb_listora_format_card_value( $field, $file, true ) );
		$this->assertSame( '', wb_listora_format_card_value( $field, 999999, true ), 'A missing file shows nothing, not a number.' );
	}
}
