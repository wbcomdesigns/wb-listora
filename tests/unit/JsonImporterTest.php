<?php
/**
 * Unit tests for WBListora\ImportExport\JSON_Importer.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;
use WBListora\ImportExport\JSON_Importer;
use WBListora\ImportExport\GeoJSON_Importer;

/**
 * @group listora
 * @group importer
 */
class JsonImporterTest extends WP_UnitTestCase {

	/**
	 * Temp file paths to clean up.
	 *
	 * @var string[]
	 */
	private $temp_files = array();

	/**
	 * Write a temp JSON file and track it for cleanup.
	 *
	 * @param mixed $data Data to encode.
	 * @return string File path.
	 */
	private function write_temp_json( $data ) {
		$path = tempnam( sys_get_temp_dir(), 'listora_test_' ) . '.json';
		file_put_contents( $path, wp_json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->temp_files[] = $path;
		return $path;
	}

	/**
	 * Clean up temp files after each test.
	 */
	public function tear_down() {
		foreach ( $this->temp_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			}
		}
		$this->temp_files = array();
		parent::tear_down();
	}

	/**
	 * parse_preview should return the correct field count and total.
	 */
	public function test_parse_preview_returns_correct_field_count() {
		$listings = array(
			array(
				'title'       => 'Coffee Shop',
				'description' => 'Great coffee.',
				'category'    => 'Cafe',
				'lat'         => 40.7128,
				'lng'         => -74.006,
			),
			array(
				'title'       => 'Bookstore',
				'description' => 'Rare books.',
				'tags'        => 'books, reading',
			),
		);

		$path   = $this->write_temp_json( $listings );
		$result = JSON_Importer::parse_preview( $path );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'fields', $result );
		$this->assertArrayHasKey( 'total', $result );
		$this->assertArrayHasKey( 'preview', $result );

		$this->assertSame( 2, $result['total'] );

		// Union of fields across both items: title, description, category, lat, lng, tags = 6.
		$this->assertCount( 6, $result['fields'] );
		$this->assertContains( 'title', $result['fields'] );
		$this->assertContains( 'lat', $result['fields'] );
		$this->assertContains( 'tags', $result['fields'] );
	}

	/**
	 * import() should create listing posts with correct titles.
	 */
	public function test_import_creates_listing_posts() {
		// Register the post type so wp_insert_post works.
		if ( ! post_type_exists( 'listora_listing' ) ) {
			register_post_type( 'listora_listing', array( 'public' => true ) );
		}

		// Register taxonomy for the type assignment.
		if ( ! taxonomy_exists( 'listora_listing_type' ) ) {
			register_taxonomy( 'listora_listing_type', 'listora_listing' );
		}

		$listings = array(
			array( 'title' => 'Test Listing Alpha' ),
			array( 'title' => 'Test Listing Beta' ),
			array( 'description' => 'No title here' ), // Will be skipped.
		);

		$path   = $this->write_temp_json( $listings );
		$result = JSON_Importer::import( $path, 'business' );

		$this->assertIsArray( $result );
		$this->assertSame( 2, $result['imported'], 'Two listings with titles should be imported.' );
		$this->assertSame( 1, $result['skipped'], 'One listing without title should be skipped.' );

		// Verify the posts actually exist.
		$posts = get_posts(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'numberposts' => 10,
			)
		);

		$titles = wp_list_pluck( $posts, 'post_title' );
		$this->assertContains( 'Test Listing Alpha', $titles );
		$this->assertContains( 'Test Listing Beta', $titles );
	}

	/**
	 * GeoJSON FeatureCollection parsing should extract coordinates from Point geometry.
	 */
	public function test_geojson_feature_collection_extracts_coordinates() {
		$geojson = array(
			'type'     => 'FeatureCollection',
			'features' => array(
				array(
					'type'       => 'Feature',
					'properties' => array(
						'name'        => 'Central Park',
						'description' => 'A large public park.',
					),
					'geometry'   => array(
						'type'        => 'Point',
						'coordinates' => array( -73.9654, 40.7829 ), // [lng, lat] per GeoJSON spec.
					),
				),
				array(
					'type'       => 'Feature',
					'properties' => array(
						'title' => 'Times Square',
					),
					'geometry'   => array(
						'type'        => 'Point',
						'coordinates' => array( -73.9855, 40.7580 ),
					),
				),
			),
		);

		$path   = $this->write_temp_json( $geojson );
		$result = GeoJSON_Importer::parse_preview( $path );

		$this->assertIsArray( $result );
		$this->assertSame( 2, $result['total'] );
		$this->assertContains( 'Point', $result['geometry_types'] );

		// Check that preview includes geometry with coordinates.
		$this->assertNotEmpty( $result['preview'] );
		$first_feature = $result['preview'][0];
		$this->assertArrayHasKey( 'geometry', $first_feature );
		$this->assertSame( 'Point', $first_feature['geometry']['type'] );
		$this->assertSame( -73.9654, $first_feature['geometry']['coordinates'][0] );
		$this->assertSame( 40.7829, $first_feature['geometry']['coordinates'][1] );
	}
}
