<?php
/**
 * A claim that was not saved is not reported as submitted.
 *
 * @package WBListora\Tests
 */

use WBListora\REST\Claims_Controller;

/**
 * @group listora
 * @group claims
 */
class ClaimInsertFailureTest extends WP_UnitTestCase {

	private function submit( int $listing ) {
		$request = new WP_REST_Request( 'POST', '/listora/v1/claims' );
		$request->set_param( 'listing_id', $listing );
		$request->set_param( 'proof_text', 'I own this place and can prove it.' );

		return ( new Claims_Controller() )->submit_claim( $request );
	}

	public function test_failed_insert_is_an_error_and_fires_no_hook() {
		update_option( 'wb_listora_settings', array_merge( (array) get_option( 'wb_listora_settings', array() ), array( 'enable_claims' => true ) ) );
		wp_set_current_user( self::factory()->user->create() );
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_status' => 'publish', 'post_author' => self::factory()->user->create() ) );

		$fired = 0;
		add_action( 'wb_listora_claim_submitted', static function () use ( &$fired ) { ++$fired; } );

		$break = static fn ( $sql ) => ( 0 === strpos( ltrim( $sql ), 'INSERT' ) && false !== strpos( $sql, 'listora_claims' ) ) ? 'SELECT * FROM listora_no_such_table_xyz' : $sql;
		add_filter( 'query', $break );
		$suppress = $GLOBALS['wpdb']->suppress_errors( true );
		$result   = $this->submit( $listing );
		$GLOBALS['wpdb']->suppress_errors( $suppress );
		remove_filter( 'query', $break );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'listora_claim_not_saved', $result->get_error_code() );
		$this->assertSame( 0, $fired, 'No submitted hook for a claim that does not exist.' );
	}
}
