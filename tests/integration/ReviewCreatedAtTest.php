<?php
/**
 * A moderator can date a review created through REST; nobody else can, and never into the future.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class ReviewCreatedAtTest extends WP_UnitTestCase {

	private int $listing = 0;

	public function set_up() {
		parent::set_up();
		add_filter( 'wb_listora_rate_limit_bypass', '__return_true' );
		$this->listing = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_author' => self::factory()->user->create( array( 'role' => 'editor' ) ),
			)
		);
		do_action( 'rest_api_init' );
	}

	private function review( string $role, string $created_at ): array {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		$req = new WP_REST_Request( 'POST', '/listora/v1/listings/' . $this->listing . '/reviews' );
		$req->set_header( 'content-type', 'application/json' );
		$req->set_body( (string) wp_json_encode( array(
			'overall_rating' => 4,
			'title'          => 'Great place',
			'content'        => 'Really good food and friendly staff, would come back again and again.',
			'created_at'     => $created_at,
		) ) );
		$res = rest_do_request( $req );
		$this->assertSame( 201, $res->get_status(), wp_json_encode( $res->get_data() ) );
		global $wpdb;
		return (array) $wpdb->get_row( $wpdb->prepare( "SELECT created_at FROM {$wpdb->prefix}listora_reviews WHERE id = %d", (int) ( $res->get_data()['id'] ?? 0 ) ), ARRAY_A );
	}

	public function test_an_administrator_can_date_a_review() {
		$when = gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS );

		$this->assertSame( $when, $this->review( 'administrator', $when )['created_at'] );
	}

	public function test_a_member_cannot_and_a_future_date_is_ignored() {
		$past = gmdate( 'Y-m-d H:i:s', time() - 40 * DAY_IN_SECONDS );
		$this->assertGreaterThan( time() - 120, strtotime( $this->review( 'subscriber', $past )['created_at'] . ' UTC' ) );

		$future = gmdate( 'Y-m-d H:i:s', time() + 40 * DAY_IN_SECONDS );
		$this->assertLessThanOrEqual( time() + 5, strtotime( $this->review( 'administrator', $future )['created_at'] . ' UTC' ) );
	}
}
