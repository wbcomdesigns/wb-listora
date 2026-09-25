<?php
/**
 * Services can be switched off for a listing type.
 *
 * Card 10217625415: services rendered on every type, so a Job listing shipped
 * with a Services tab - and the demo content had invented a service for one.
 * The gate lives in Services so the detail page, the dashboard, the REST
 * payload and the write routes cannot disagree with each other.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 * @group   services
 */

namespace WBListora\Tests\Unit;

use WBListora\Core\Listing_Type_Registry;
use WBListora\Core\Services;
use WP_UnitTestCase;

/**
 * @group listora
 * @group services
 */
class ServicesPerTypeTest extends WP_UnitTestCase {

	/**
	 * A listing type this test owns.
	 *
	 * Deliberately not one of the shipped types: the suite's other tests create
	 * and delete those, so depending on them makes this pass alone and fail in
	 * the full run (which is exactly what happened).
	 */
	private function make_type( string $slug ): int {
		$existing = get_term_by( 'slug', $slug, 'listora_listing_type' );
		if ( $existing ) {
			return (int) $existing->term_id;
		}

		$term = wp_insert_term( ucfirst( $slug ), 'listora_listing_type', array( 'slug' => $slug ) );

		// The registry loads types once; without a reload the REST route 404s
		// on a type made here, unless an earlier test happened to reload it.
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();

		return (int) $term['term_id'];
	}

	private function listing_of_type( string $slug ): int {
		$this->make_type( $slug );
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish' ) );
		wp_set_object_terms( $listing, $slug, 'listora_listing_type' );

		return $listing;
	}

	private function set_services( string $slug, $value ): void {
		update_term_meta( $this->make_type( $slug ), '_listora_services_enabled', $value );
		// flush() empties the registry and get() does not lazily re-populate,
		// so the pair must be called together - which is what the REST
		// controller does after a type is saved.
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();
	}

	public function test_a_type_that_never_saved_the_flag_still_has_services(): void {
		$term_id = $this->make_type( 'qa-legacy-type' );
		delete_term_meta( $term_id, '_listora_services_enabled' );
		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();

		// Absent meta must read as ON. A bare (bool) cast of '' is false, which
		// would have switched services off for every existing type on upgrade.
		$this->assertTrue( Services::enabled_for_listing( $this->listing_of_type( 'qa-legacy-type' ) ) );
	}

	public function test_switching_it_off_hides_services_and_refuses_writes(): void {
		$listing = $this->listing_of_type( 'qa-job-type' );

		$this->set_services( 'qa-job-type', 1 );
		$created = Services::create_service( array( 'listing_id' => $listing, 'title' => 'Fast-track interview' ) );
		$this->assertNotWPError( $created );
		$this->assertCount( 1, Services::get_services( $listing ) );

		$this->set_services( 'qa-job-type', 0 );

		$this->assertFalse( Services::enabled_for_listing( $listing ) );
		$this->assertSame( array(), Services::get_services( $listing ), 'Reads report none.' );

		$refused = Services::create_service( array( 'listing_id' => $listing, 'title' => 'Another one' ) );
		$this->assertWPError( $refused );
		$this->assertSame( 'listora_services_disabled', $refused->get_error_code() );

		// Rows are hidden, not deleted: switching it back on restores them.
		$this->set_services( 'qa-job-type', 1 );
		$this->assertCount( 1, Services::get_services( $listing ) );
	}

	/**
	 * Saving false through the type route must read back as off. WordPress
	 * stores a bare false as '', which bool_meta() reads as ON (BC 10331936497).
	 */
	public function test_saving_off_through_the_type_route_sticks(): void {
		$listing = $this->listing_of_type( 'qa-route-type' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new \WP_REST_Request( 'PUT', '/listora/v1/listing-types/qa-route-type' );
		$request->set_param( 'services_enabled', false );
		rest_get_server()->dispatch( $request );

		Listing_Type_Registry::instance()->flush();
		Listing_Type_Registry::instance()->init();

		$this->assertFalse( Services::enabled_for_listing( $listing ) );
	}

	public function test_a_filter_can_override_the_type(): void {
		$listing = $this->listing_of_type( 'qa-job-type' );
		$this->set_services( 'qa-job-type', 0 );

		add_filter( 'wb_listora_services_enabled', '__return_true' );
		$this->assertTrue( Services::enabled_for_listing( $listing ) );
		remove_filter( 'wb_listora_services_enabled', '__return_true' );
	}
}
