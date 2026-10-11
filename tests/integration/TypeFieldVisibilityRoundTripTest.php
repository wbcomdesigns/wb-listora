<?php
/**
 * A type save keeps every field setting, and "Show in API" off hides the field.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;
use WBListora\Core\Listing_Type_Registry;
use WBListora\Core\Meta_Handler;

/**
 * The type editor sent a short whitelist of field props, so "Show in API"
 * never persisted (card 10369254010).
 *
 * @group listora
 * @group rest-api
 */
class TypeFieldVisibilityRoundTripTest extends WP_UnitTestCase {

	private function field_groups( array $email ) {
		return array(
			array(
				'key'    => 'contact',
				'label'  => 'Contact',
				'fields' => array(
					array_merge(
						array(
							'key'   => 'email',
							'label' => 'Email',
							'type'  => 'email',
						),
						$email
					),
				),
			),
		);
	}

	public function test_saved_field_keeps_show_in_rest_and_unedited_props() {
		$server = rest_get_server();
		do_action( 'rest_api_init', $server );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$registry = Listing_Type_Registry::instance();
		$registry->save_type( 'qa-vis', array( 'props' => array( 'name' => 'QA Vis' ), 'field_groups' => $this->field_groups( array() ) ) );
		$registry->flush();
		$registry->init();

		$request = new WP_REST_Request( 'PUT', '/listora/v1/listing-types/qa-vis' );
		$request->set_body_params(
			array(
				'field_groups' => $this->field_groups(
					array(
						'show_in_rest' => false,
						'placeholder'  => 'you@example.com',
						'width'        => '50',
					)
				),
			)
		);
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );

		$registry->flush();
		$registry->init();
		$field = $registry->get( 'qa-vis' )->get_field( 'email' );
		$this->assertFalse( (bool) $field->get( 'show_in_rest' ) );
		$this->assertSame( '50', $field->get( 'width' ), 'Settings the editor has no control for survive the save.' );

		$post_id = self::factory()->post->create( array( 'post_type' => 'listora_listing' ) );
		wp_set_object_terms( $post_id, 'qa-vis', 'listora_listing_type' );
		update_post_meta( $post_id, '_listora_email', 'owner@example.com' );

		wp_set_current_user( 0 );
		$this->assertArrayNotHasKey( 'email', Meta_Handler::get_visible_values( $post_id, 'rest' ) );
	}
}
