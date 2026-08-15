<?php
/**
 * Unit tests for the automation schema loader.
 *
 * Schemas live on disk as JSON rather than in PHP arrays so that CI can diff
 * them without booting WordPress, review can read the diff, and the discovery
 * endpoint can serve them byte for byte.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;
use WBListora\Automation\Schema_Loader;

/**
 * @group listora
 * @group automation
 */
class AutomationSchemaLoaderTest extends WP_UnitTestCase {

	/**
	 * Temporary schema file created during tests.
	 *
	 * @var string|null
	 */
	private $temp_schema_file;

	public function test_loads_a_real_schema_file() {
		$schema = Schema_Loader::load( 'listing_approved.v1.json' );

		$this->assertIsArray( $schema );
		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'properties', $schema );
	}

	public function test_missing_schema_returns_null_rather_than_fataling() {
		$this->assertNull( Schema_Loader::load( 'does_not_exist.v1.json' ) );
	}

	/**
	 * The filename comes from a registry entry, which Pro can populate. A
	 * traversal must not escape the schema directory. Uses path() to test the
	 * guard itself, not just the side effect of a file being absent. Tests
	 * against real files so the test fails if the guard is removed.
	 */
	public function test_refuses_path_traversal() {
		// Non-existent targets (harmless but insufficient).
		$this->assertSame( '', Schema_Loader::path( '../../../wp-config.php' ) );
		$this->assertSame( '', Schema_Loader::path( 'sub/dir/thing.json' ) );

		// Real file one level up — would be found if basename check were removed.
		$this->assertSame( '', Schema_Loader::path( '../class-schema-loader.php' ) );

		// Schema-shaped file outside the schemas dir.
		$this->temp_schema_file = dirname( Schema_Loader::dir() ) . 'external_schema.v1.json';
		$this->create_temp_schema_file( $this->temp_schema_file );
		$this->assertSame( '', Schema_Loader::path( '../external_schema.v1.json' ) );
	}

	/**
	 * Clean up temporary files after each test, even if assertions fail.
	 */
	public function tear_down() {
		if ( $this->temp_schema_file && file_exists( $this->temp_schema_file ) ) {
			unlink( $this->temp_schema_file );
		}
		parent::tear_down();
	}

	/**
	 * Create a temporary schema file for testing.
	 *
	 * @param string $filepath Absolute path to file.
	 */
	private function create_temp_schema_file( $filepath ) {
		file_put_contents(
			$filepath,
			wp_json_encode( array( 'type' => 'object', 'properties' => array() ) )
		);
	}

	public function test_every_shipped_schema_is_valid_json_and_an_object_schema() {
		$files = glob( Schema_Loader::dir() . '*.json' );

		$this->assertNotEmpty( $files, 'No schema files shipped' );

		foreach ( $files as $file ) {
			$decoded = json_decode( file_get_contents( $file ), true );
			$this->assertIsArray( $decoded, basename( $file ) . ' is not valid JSON' );
			$this->assertSame( 'object', $decoded['type'] ?? null, basename( $file ) . ' must be an object schema' );
			$this->assertArrayHasKey( 'properties', $decoded, basename( $file ) . ' has no properties' );
		}
	}
}
