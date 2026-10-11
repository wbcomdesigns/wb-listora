<?php
/**
 * The API shows what the listing page shows; Near me reads its centre from the URL.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Listing_Type_Registry;
use WBListora\Core\Meta_Handler;

/**
 * @group listora
 */
class VisibleMetaAndNearMeTest extends WP_UnitTestCase {

	public function tear_down() {
		unset( $_GET['listora_lat'], $_GET['listora_lng'], $_GET['listora_radius'] );
		parent::tear_down();
	}

	public function test_hidden_fields_leave_page_and_api_but_not_the_owners_api() {
		$registry = Listing_Type_Registry::instance();
		$registry->save_type(
			'qa-visible',
			array(
				'props'        => array( 'name' => 'QA visible' ),
				'field_groups' => array(
					array(
						'key'    => 'contact',
						'label'  => 'Contact',
						'fields' => array(
							array( 'key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'show_in_rest' => false ),
							array( 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'show_in_detail' => false ),
							array( 'key' => 'website', 'label' => 'Website', 'type' => 'url' ),
						),
					),
				),
			)
		);
		$registry->flush();
		$registry->init();

		$owner = self::factory()->user->create( array( 'role' => 'author' ) );
		$id    = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_author' => $owner, 'post_status' => 'publish' ) );
		wp_set_object_terms( $id, 'qa-visible', 'listora_listing_type' );
		foreach ( array( 'phone' => '555', 'email' => 'a@b.co', 'website' => 'https://x.co' ) as $key => $value ) {
			Meta_Handler::set_value( $id, $key, $value );
		}

		wp_set_current_user( 0 );
		$this->assertSame( array( 'phone', 'website' ), array_values( array_intersect( array( 'phone', 'email', 'website' ), array_keys( Meta_Handler::get_visible_values( $id ) ) ) ), 'Page hides only fields off for the page.' );
		$this->assertSame( array( 'website' ), array_values( array_intersect( array( 'phone', 'email', 'website' ), array_keys( Meta_Handler::get_visible_values( $id, 'rest' ) ) ) ), 'A visitor gets no more through the API than the page shows, minus API-off fields.' );

		wp_set_current_user( $owner );
		$this->assertArrayHasKey( 'email', Meta_Handler::get_visible_values( $id, 'rest' ), 'The owner can still read and edit every value.' );
	}

	public function test_near_me_centre_and_radius_from_url() {
		$this->assertSame( array(), wb_listora_search_geo_from_url() );

		$_GET['listora_lat'] = '40.749';
		$_GET['listora_lng'] = '-73.986';
		$this->assertSame( (int) wb_listora_get_setting( 'near_me_radius' ), wb_listora_search_geo_from_url()['radius'], 'No radius: the owner default.' );

		$_GET['listora_radius'] = '9999';
		$this->assertSame( 500, wb_listora_search_geo_from_url()['radius'] );

		$_GET['listora_lat'] = '91';
		$this->assertSame( array(), wb_listora_search_geo_from_url(), 'Out-of-range latitude is ignored.' );
	}
}
