<?php
/**
 * CSV export writes through a temporary file that is never web-readable.
 *
 * @package WBListora\Tests
 */

use WBListora\ImportExport\CSV_Exporter;
use WBListora\Tests\Factories\Factories;

/**
 * @group listora
 */
class CsvExportTest extends WP_UnitTestCase {

	public function test_export_file_is_outside_uploads_and_complete() {
		$first  = Factories::listing()->create( array( 'title' => 'Export One', 'type_slug' => 'restaurant' ) );
		$second = Factories::listing()->create( array( 'title' => '=Export Two', 'type_slug' => 'restaurant' ) );

		$path = CSV_Exporter::export( array( 'type' => 'restaurant' ) );

		$this->assertIsString( $path );
		$this->assertStringStartsNotWith( wp_upload_dir()['basedir'], $path, 'Nothing may be written under the public uploads folder.' );

		$lines = file( $path, FILE_IGNORE_NEW_LINES );
		wp_delete_file( $path );

		$this->assertStringStartsWith( 'ID,Title', $lines[0] );
		$this->assertCount( 3, array_filter( $lines ), 'Header plus the two listings.' );
		$this->assertStringContainsString( "'=Export Two", implode( "\n", $lines ), 'Formula cells stay neutralised.' );
		$this->assertContains( $first, CSV_Exporter::listing_ids( array( 'type' => 'restaurant' ) ) );
		$this->assertContains( $second, CSV_Exporter::listing_ids( array( 'type' => 'restaurant' ) ) );
	}

	public function test_every_header_is_unique() {
		// Classified and Education label their address field "Location", the same as the core column.
		\WBListora\Core\Listing_Type_Registry::instance()->save_type(
			'qa-csv-loc',
			array(
				'props'        => array( 'name' => 'QA CSV' ),
				'field_groups' => array( array( 'key' => 'where', 'label' => 'Where', 'fields' => array( array( 'key' => 'address', 'label' => 'Location', 'type' => 'text' ) ) ) ),
			)
		);
		Factories::listing()->create( array( 'title' => 'Header Check', 'type_slug' => 'qa-csv-loc' ) );

		$path    = CSV_Exporter::export( array( 'type' => 'qa-csv-loc' ) );
		$handle  = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- reading the test's own temp file.
		$headers = fgetcsv( $handle, null, ',', '"', '\\' );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- same file.
		wp_delete_file( $path );

		$this->assertSame( array_unique( $headers ), $headers, 'Two "Location" columns sent the address into the location on re-import.' );
		$this->assertContains( 'Location (address)', $headers );
	}

	public function test_nothing_to_export_is_an_error_not_an_empty_file() {
		$result = CSV_Exporter::export( array( 'type' => 'no-such-type' ) );

		$this->assertWPError( $result );
	}
}
