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
	 * traversal must not escape the schema directory.
	 */
	public function test_refuses_path_traversal() {
		$this->assertNull( Schema_Loader::load( '../../../wp-config.php' ) );
		$this->assertNull( Schema_Loader::load( 'sub/dir/thing.json' ) );
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
