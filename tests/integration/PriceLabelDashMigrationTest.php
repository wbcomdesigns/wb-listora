<?php
/**
 * Migration 1.9.1.21 rewrites the stored "$$ — Moderate" labels to a hyphen.
 *
 * @package WBListora\Tests
 */

namespace WBListora\Tests\Integration;

use WBListora\DB\Migrator;
use WP_UnitTestCase;

/**
 * @group listora
 * @group migration
 */
class PriceLabelDashMigrationTest extends WP_UnitTestCase {

	private function type_with( array $options ): int {
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => 'listora_listing_type' ) );
		update_term_meta(
			$term->term_id,
			'_listora_field_groups',
			array( array( 'key' => 'g', 'fields' => array( array( 'key' => 'price_range', 'label' => 'Price — range', 'options' => $options ) ) ) )
		);
		return (int) $term->term_id;
	}

	private function options_of( int $term_id ): array {
		return get_term_meta( $term_id, '_listora_field_groups', true )[0]['fields'][0]['options'];
	}

	public function test_default_dash_labels_become_hyphens_and_owner_text_is_untouched() {
		$id = $this->type_with(
			array(
				array( 'value' => '$', 'label' => "$ \u{2014} Budget" ),
				array( 'value' => '$$$$', 'label' => "$$$$ \u{2014} Fine Dining" ),
				array( 'value' => 'x', 'label' => "Our \u{2014} custom tier" ),
			)
		);

		Migrator::migrate_1_9_1_21();

		$labels = array_column( $this->options_of( $id ), 'label' );
		$this->assertSame( array( '$ - Budget', '$$$$ - Fine Dining', "Our \u{2014} custom tier" ), $labels );
		$this->assertSame( 'Price — range', get_term_meta( $id, '_listora_field_groups', true )[0]['fields'][0]['label'], 'Only price options are rewritten.' );
	}

	public function test_running_twice_changes_nothing_more() {
		$id = $this->type_with( array( array( 'value' => '$$', 'label' => "$$ \u{2014} Moderate" ) ) );

		Migrator::migrate_1_9_1_21();
		Migrator::migrate_1_9_1_21();

		$this->assertSame( '$$ - Moderate', $this->options_of( $id )[0]['label'] );
	}
}
