<?php
/**
 * One click on Import CSV sends one request, and the column mapper offers the chosen type's fields.
 *
 * Two scripts used to bind the same button: a click queued a background run AND ran the old
 * synchronous import, so every row landed twice. The mapper also only ever offered the shared
 * fields, so a Listora export lost its custom fields on the way back in.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 */
class CsvImportOneMapperTest extends WP_UnitTestCase {

	public function test_only_the_queued_importer_binds_the_import_button() {
		$settings = (string) file_get_contents( WB_LISTORA_PLUGIN_DIR . 'assets/js/admin/settings-page.js' );
		$pages    = (string) file_get_contents( WB_LISTORA_PLUGIN_DIR . 'assets/js/admin/admin-pages.js' );

		$this->assertStringNotContainsString( 'listora-csv-import-btn', $settings, 'settings-page.js must not also handle the import button' );
		$this->assertStringContainsString( '/listora/v1/import/queue/csv', $pages );
	}

	public function test_the_mapper_offers_the_types_custom_fields() {
		\WBListora\Core\Listing_Type_Registry::instance()->register(
			'csv_map_t',
			new \WBListora\Core\Listing_Type(
				'csv_map_t',
				array( 'name' => 'T' ),
				array( array( 'key' => 'main', 'label' => 'Main', 'fields' => array( array( 'key' => 'phone', 'type' => 'text', 'label' => 'Phone' ) ) ) )
			)
		);

		$this->assertArrayNotHasKey( 'meta_phone', \WBListora\ImportExport\CSV_Importer::get_mappable_fields( '' ) );
		$this->assertArrayHasKey( 'meta_phone', \WBListora\ImportExport\CSV_Importer::get_mappable_fields( 'csv_map_t' ) );
	}
}
