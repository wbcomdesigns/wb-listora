<?php
/**
 * A wrong-shaped param on the submit routes is refused and changes nothing.
 *
 * RequestShapeFuzzTest only fails on a PHP error or a 5xx, so a 200 that emptied a
 * listing's tags passed it. This test asserts the answer (a 4xx) and the state (the
 * listing is byte-for-byte what it was), and that a failure part-way through an edit
 * rolls the whole edit back.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group request-shape
 */
class SubmissionShapeStateTest extends WP_UnitTestCase {

	private int $listing_id = 0;

	public function set_up() {
		parent::set_up();
		add_filter( 'wb_listora_rate_limit_bypass', '__return_true' );
		// Route code that opens its own transaction must not commit the test's.
		add_filter( 'query', array( $this, 'nest_transactions' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->listing_id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'draft',
				'post_title'  => 'Original title',
				'post_author' => get_current_user_id(),
			)
		);
		wp_set_object_terms( $this->listing_id, array( 'alpha', 'beta' ), 'listora_listing_tag' );
		do_action( 'rest_api_init' );
	}

	public function tear_down() {
		remove_filter( 'query', array( $this, 'nest_transactions' ) );
		parent::tear_down();
	}

	/**
	 * Turn a route's own transaction into a savepoint inside the test's.
	 *
	 * @param string $query SQL.
	 * @return string
	 */
	public function nest_transactions( $query ) {
		$map = array(
			'START TRANSACTION' => 'SAVEPOINT listora_shape',
			'COMMIT'            => 'RELEASE SAVEPOINT listora_shape',
			'ROLLBACK'          => 'ROLLBACK TO SAVEPOINT listora_shape',
		);

		return $map[ strtoupper( trim( $query ) ) ] ?? $query;
	}

	/**
	 * Everything an edit can change on the listing, as one comparable string.
	 */
	private function snapshot(): string {
		clean_post_cache( $this->listing_id );
		$post = get_post( $this->listing_id );

		return (string) wp_json_encode(
			array(
				$post->post_title,
				$post->post_status,
				wp_get_object_terms( $this->listing_id, 'listora_listing_tag', array( 'fields' => 'names' ) ),
				get_post_thumbnail_id( $this->listing_id ),
				get_post_meta( $this->listing_id ),
			)
		);
	}

	private function put( array $body ): WP_REST_Response {
		$request = new WP_REST_Request( 'PUT', '/listora/v1/submit/' . $this->listing_id );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $body ) );

		return rest_do_request( $request );
	}

	/**
	 * @return array<string, array{0: string, 1: array}>
	 */
	public function wrong_shapes(): array {
		return array(
			'tags'           => array( 'tags', array( 'x', 'y' ) ),
			'featured_image' => array( 'featured_image', array( 7 ) ),
			'status'         => array( 'status', array( 'draft' ) ),
			'plan_id'        => array( 'plan_id', array( 5 ) ),
			'category'       => array( 'category', array( 3 ) ),
			'video'          => array( 'video', array( 'x' ) ),
		);
	}

	/**
	 * @dataProvider wrong_shapes
	 *
	 * @param string $param Param name.
	 * @param array  $value A list for a single-value param.
	 */
	public function test_a_list_for_a_single_value_param_is_refused_and_changes_nothing( string $param, array $value ) {
		$before   = $this->snapshot();
		$response = $this->put( array( $param => $value ) + array( 'status' => 'draft' ) );

		$this->assertSame( 400, $response->get_status(), "{$param} as a list must be a 400, not a coerced write." );
		$this->assertSame( $before, $this->snapshot(), "{$param} as a list must leave the listing untouched." );
	}

	public function test_a_normal_edit_still_saves() {
		$response = $this->put(
			array(
				'title'  => 'Edited title',
				'tags'   => 'gamma, delta',
				'status' => 'draft',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Edited title', get_post( $this->listing_id )->post_title );
		$this->assertEqualsCanonicalizing( array( 'gamma', 'delta' ), wp_get_object_terms( $this->listing_id, 'listora_listing_tag', array( 'fields' => 'names' ) ) );
	}

	public function test_a_failure_part_way_through_an_edit_rolls_back_the_whole_edit() {
		// Throws once the title and terms are written, the way a deadlock or a filter would.
		add_filter(
			'wb_listora_field_sanitize_callbacks',
			static function ( $callbacks ) {
				$callbacks['text'] = static function () {
					throw new RuntimeException( 'boom' );
				};
				return $callbacks;
			}
		);

		$types = \WBListora\Core\Listing_Type_Registry::instance()->get_all();
		$field = null;
		foreach ( $types as $type ) {
			foreach ( $type->get_all_fields() as $candidate ) {
				if ( 'text' === $candidate->get_type() ) {
					wp_set_object_terms( $this->listing_id, $type->get_slug(), 'listora_listing_type' );
					$field = $candidate;
					break 2;
				}
			}
		}
		if ( ! $field ) {
			$this->markTestSkipped( 'No listing type with a text field is registered.' );
		}
		$before = $this->snapshot();

		$response = $this->put(
			array(
				'title'                     => 'Half saved title',
				'tags'                      => 'zeta',
				'status'                    => 'draft',
				'meta_' . $field->get_key() => 'x',
			)
		);

		$this->assertSame( 500, $response->get_status() );
		$this->assertSame( $before, $this->snapshot(), 'A failed edit must not leave part of itself saved.' );
	}
}
