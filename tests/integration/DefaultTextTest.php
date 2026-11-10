<?php
/**
 * Built-in listing type text is shown in the visitor's language even though it
 * was stored in English at install; owner-edited text is left alone.
 *
 * @package WBListora\Tests
 */

use WBListora\Core\Default_Text;
use WBListora\Core\Field;
use WBListora\Core\Field_Group;

/**
 * @group listora
 * @group i18n
 */
class DefaultTextTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		// The test site has no Dutch language pack, and switch_to_locale() refuses locales that are not installed.
		add_filter(
			'get_available_languages',
			static function ( $languages ) {
				return array_merge( (array) $languages, array( 'nl_NL' ) );
			}
		);
		$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();
		// Stand-in for a Dutch catalogue: only answers when the locale is nl_NL.
		add_filter(
			'gettext',
			static function ( $translated, $text, $domain ) {
				if ( 'wb-listora' === $domain && 'nl_NL' === determine_locale() && 'Bathrooms' === $text ) {
					return 'Badkamers';
				}
				return $translated;
			},
			10,
			3
		);
	}

	public function tear_down() {
		remove_all_filters( 'gettext' );
		remove_all_filters( 'get_available_languages' );
		restore_current_locale();
		parent::tear_down();
	}

	public function test_a_stored_english_default_shows_in_the_site_language() {
		switch_to_locale( 'nl_NL' );

		$this->assertSame( 'Badkamers', Default_Text::translate( 'Bathrooms' ) );
		$this->assertSame( 'Badkamers', ( new Field( array( 'key' => 'b', 'label' => 'Bathrooms' ) ) )->get_label() );
		$this->assertSame( 'Badkamers', ( new Field( array( 'key' => 'b', 'label' => 'Bathrooms' ) ) )->to_array()['label'] );
	}

	public function test_text_the_owner_edited_is_shown_as_typed() {
		switch_to_locale( 'nl_NL' );

		$this->assertSame( 'Bathrooms (en-suite)', Default_Text::translate( 'Bathrooms (en-suite)' ) );
		$this->assertSame( 'My baths', ( new Field( array( 'key' => 'b', 'label' => 'My baths' ) ) )->get_label() );
	}

	public function test_english_sites_are_untouched() {
		$this->assertSame( 'Bathrooms', Default_Text::translate( 'Bathrooms' ) );
	}

	public function test_option_labels_and_group_labels_translate_too() {
		switch_to_locale( 'nl_NL' );

		$field = new Field( array( 'key' => 'b', 'label' => 'x', 'options' => array( array( 'value' => 'a', 'label' => 'Bathrooms' ) ) ) );
		$this->assertSame( 'Badkamers', $field->get( 'options' )[0]['label'] );
		$this->assertSame( 'a', $field->get( 'options' )[0]['value'] );

		$group = new Field_Group( array( 'key' => 'g', 'label' => 'Bathrooms' ) );
		$this->assertSame( 'Badkamers', $group->get_label() );
	}

	public function test_saving_what_an_editor_showed_stores_the_english_source() {
		switch_to_locale( 'nl_NL' );

		$saved = Default_Text::walk( array( 'label' => 'Badkamers', 'key' => 'Badkamers', 'options' => array( array( 'label' => 'Badkamers' ) ) ), false );

		$this->assertSame( 'Bathrooms', $saved['label'] );
		$this->assertSame( 'Bathrooms', $saved['options'][0]['label'] );
		$this->assertSame( 'Badkamers', $saved['key'], 'Only text keys are touched.' );
	}
}
