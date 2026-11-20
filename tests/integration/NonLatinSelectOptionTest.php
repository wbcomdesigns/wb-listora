<?php
/**
 * A select option written in a non-Latin script keeps its value from the type editor to the saved listing.
 *
 * sanitize_title() percent-encodes such text and sanitize_text_field() strips the octets, so a
 * Korean option saved as empty: nothing on the single page, and the edit form reset.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class NonLatinSelectOptionTest extends WP_UnitTestCase {

	private function type_with_options( string $slug, array $options ): \WBListora\Core\Listing_Type {
		$type = new \WBListora\Core\Listing_Type(
			$slug,
			array( 'name' => 'Food' ),
			array(
				array(
					'key'    => 'main',
					'label'  => 'Main',
					'fields' => array( array( 'key' => 'cuisine', 'type' => 'select', 'label' => 'Cuisine', 'options' => $options ) ),
				),
			)
		);
		\WBListora\Core\Listing_Type_Registry::instance()->register( $slug, $type );

		return $type;
	}

	public function test_a_label_only_korean_option_gets_a_readable_value() {
		$type    = $this->type_with_options( 'ko_a', array( '한식', '양식' ) );
		$options = $type->get_all_fields()[0]->get( 'options' );

		$this->assertSame( array( '한식', '양식' ), wp_list_pluck( $options, 'value' ) );
	}

	public function test_an_option_saved_percent_encoded_by_an_older_version_is_decoded() {
		$type    = $this->type_with_options( 'ko_b', array( array( 'value' => '%ed%95%9c%ec%8b%9d', 'label' => '한식' ) ) );
		$options = $type->get_all_fields()[0]->get( 'options' );

		$this->assertSame( '한식', $options[0]['value'] );
	}

	public function test_the_value_survives_sanitising_and_matches_its_option() {
		$type    = $this->type_with_options( 'ko_c', array( '한식' ) );
		$options = $type->get_all_fields()[0]->get( 'options' );
		$saved   = sanitize_text_field( $options[0]['value'] );

		$this->assertSame( '한식', $saved );
		$this->assertContains( $saved, wp_list_pluck( $options, 'value' ) );
	}

	public function test_latin_options_keep_their_slugs() {
		$type    = $this->type_with_options( 'ko_d', array( 'Fast Food' ) );
		$options = $type->get_all_fields()[0]->get( 'options' );

		$this->assertSame( 'fast-food', $options[0]['value'] );
	}
}
