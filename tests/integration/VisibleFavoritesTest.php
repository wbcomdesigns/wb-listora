<?php
/**
 * A member's saved-listings number, tab rows and /favorites total are one rule: published listings only.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Dashboard_Data;

/**
 * @group listora
 */
class VisibleFavoritesTest extends WP_UnitTestCase {

	private int $member  = 0;
	private int $visible = 0;

	public function set_up() {
		parent::set_up();
		global $wpdb;
		$this->member  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->visible = $this->listing( 'publish' );
		$gone          = array( $this->visible, $this->listing( 'trash' ), $this->listing( 'draft' ), $this->listing( 'listora_expired' ) );
		foreach ( $gone as $listing_id ) {
			$wpdb->insert(
				$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'favorites',
				array(
					'user_id'    => $this->member,
					'listing_id' => $listing_id,
					'collection' => 'default',
					'created_at' => current_time( 'mysql', true ),
				)
			);
		}
	}

	private function listing( string $status ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => $status,
				'post_title'  => 'Saved ' . $status,
			)
		);
	}

	public function test_the_count_and_the_tab_list_only_see_published_listings() {
		$this->assertSame( 1, Dashboard_Data::count_favorites( $this->member ) );
		$this->assertSame( array( $this->visible ), Dashboard_Data::list_favorite_ids( $this->member, 20 ) );
	}

	public function test_the_rest_total_equals_the_rows_it_returns() {
		wp_set_current_user( $this->member );
		$response = rest_do_request( new WP_REST_Request( 'GET', '/listora/v1/favorites' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 1, (int) $response->get_headers()['X-WP-Total'] );
		$this->assertCount( 1, $data['favorites'] );
	}
}
