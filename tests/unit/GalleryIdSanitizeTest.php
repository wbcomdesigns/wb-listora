<?php
/**
 * Unit tests for gallery attachment-ID sanitization.
 *
 * A gallery travels as a comma-separated list of attachment IDs — that is what
 * the submission template's hidden input emits and what /submit explodes on.
 * sanitize_id_array() only understood an array or a JSON string, so the
 * wp-admin meta box posted "12,34" and got back an empty array: the editor's
 * gallery selection looked right and silently vanished on save
 * (BC 10272654379). The single-ID case failed for a second reason —
 * json_decode( '12' ) succeeds and returns an int, not an array.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;
use WBListora\Core\Field;

/**
 * @group listora
 * @group fields
 */
class GalleryIdSanitizeTest extends WP_UnitTestCase {

	/**
	 * Field under test.
	 *
	 * @var Field
	 */
	private $field;

	/**
	 * Attachment IDs owned by the acting user.
	 *
	 * @var int[]
	 */
	private $attachments = array();

	public function set_up() {
		parent::set_up();

		// sanitize_id_array() filters to attachments the current user may
		// attach, so these tests need a real user and real attachments —
		// without them every case returns an empty array for the wrong reason.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		for ( $i = 0; $i < 2; $i++ ) {
			$this->attachments[] = self::factory()->attachment->create(
				array(
					'post_mime_type' => 'image/jpeg',
					'post_author'    => $user_id,
				)
			);
		}

		$this->field = new Field(
			array(
				'key'   => 'gallery',
				'type'  => 'gallery',
				'label' => 'Photo Gallery',
			)
		);
	}

	public function test_comma_separated_ids_are_accepted() {
		$csv = implode( ',', $this->attachments );

		$this->assertSame( $this->attachments, $this->field->sanitize_id_array( $csv ) );
	}

	public function test_a_single_id_is_not_dropped() {
		// json_decode( '12' ) returns int 12, which is valid JSON but not an
		// array — the reason a one-image gallery came back empty.
		$single = (string) $this->attachments[0];

		$this->assertSame( array( $this->attachments[0] ), $this->field->sanitize_id_array( $single ) );
	}

	public function test_surrounding_whitespace_is_tolerated() {
		$padded = ' ' . $this->attachments[0] . ' , ' . $this->attachments[1] . ' ';

		$this->assertSame( $this->attachments, $this->field->sanitize_id_array( $padded ) );
	}

	public function test_json_input_still_works() {
		// The pre-existing contract. CSV support must not cost it.
		$json = (string) wp_json_encode( $this->attachments );

		$this->assertSame( $this->attachments, $this->field->sanitize_id_array( $json ) );
	}

	public function test_array_input_still_works() {
		$this->assertSame( $this->attachments, $this->field->sanitize_id_array( $this->attachments ) );
	}

	public function test_an_empty_value_clears_the_gallery() {
		// Removing every image must store an empty array, not array( 0 ).
		$this->assertSame( array(), $this->field->sanitize_id_array( '' ) );
	}

	public function test_non_numeric_input_yields_nothing() {
		$this->assertSame( array(), $this->field->sanitize_id_array( 'abc,def' ) );
	}
}
