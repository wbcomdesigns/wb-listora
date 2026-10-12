<?php
/**
 * Per-type Moderation and Reviews: 'global' follows Settings, a choice wins.
 *
 * @package WBListora\Tests\Integration
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Core\Listing_Type_Registry;

/**
 * Both per-type settings were saved but never read (cards 10355736648, 10355511224).
 *
 * @group listora
 */
class PerTypeSettingsTest extends WP_UnitTestCase {

	/**
	 * Save a type with the given props and return it fresh from the registry.
	 *
	 * @param string $slug  Type slug.
	 * @param array  $props Props.
	 * @return \WBListora\Core\Listing_Type
	 */
	private function type( $slug, array $props ) {
		$registry = Listing_Type_Registry::instance();
		$registry->save_type( $slug, array( 'props' => array( 'name' => ucfirst( $slug ) ) + $props ) );
		$registry->flush();
		$registry->init();
		return $registry->get( $slug );
	}

	public function test_moderation_follows_the_site_until_the_type_chooses() {
		$settings               = get_option( 'wb_listora_settings', array() );
		$settings['moderation'] = 'auto_approve';
		update_option( 'wb_listora_settings', $settings );

		$this->assertSame( 'auto', $this->type( 'qa-global', array( 'moderation' => 'global' ) )->get_moderation() );
		$this->assertSame( 'manual', $this->type( 'qa-manual', array( 'moderation' => 'manual' ) )->get_moderation() );
		$this->assertSame( 'auto', $this->type( 'qa-none', array( 'moderation' => 'none' ) )->get_moderation(), "Legacy 'none' means auto-approve." );
	}

	public function test_reviews_choice_and_legacy_boolean() {
		$this->assertTrue( $this->type( 'qa-rev-global', array( 'reviews' => 'global' ) )->is_review_enabled() );
		$this->assertFalse( $this->type( 'qa-rev-off', array( 'reviews' => 'off' ) )->is_review_enabled() );
		$this->assertFalse( $this->type( 'qa-rev-legacy', array( 'review_enabled' => false ) )->is_review_enabled(), 'An older client sending review_enabled=false still turns them off.' );
	}

	public function test_editing_a_type_never_changes_whether_it_is_built_in() {
		$registry = Listing_Type_Registry::instance();
		$registry->save_type( 'qa-custom', array( 'props' => array( 'name' => 'QA Custom' ) ) );
		$term = get_term_by( 'slug', 'qa-custom', 'listora_listing_type' );
		update_term_meta( $term->term_id, '_listora_is_default', true ); // As if the plugin shipped it.

		$registry->save_type( 'qa-custom', array( 'props' => array( 'name' => 'QA Custom renamed' ) ) );
		$this->assertTrue( (bool) get_term_meta( $term->term_id, '_listora_is_default', true ), 'An edit keeps the built-in flag.' );

		$registry->save_type( 'qa-brand-new', array( 'props' => array( 'name' => 'Brand new' ) ) );
		$new = get_term_by( 'slug', 'qa-brand-new', 'listora_listing_type' );
		$this->assertFalse( (bool) get_term_meta( $new->term_id, '_listora_is_default', true ), 'A site-created type is not built in.' );
	}
}
