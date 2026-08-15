<?php
/**
 * The declared catalogue must be internally coherent: every trigger has a
 * schema that exists, a hook that is really fired, and a unique name.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Automation\Schema_Loader;

/**
 * @group listora
 * @group automation
 */
class TriggerCatalogueTest extends WP_UnitTestCase {

	private function registry() {
		return wb_listora_service( 'triggers' );
	}

	public function test_catalogue_is_not_empty() {
		$this->assertNotEmpty( $this->registry()->names() );
	}

	public function test_every_trigger_has_a_schema_file_that_loads() {
		foreach ( $this->registry()->all() as $name => $trigger ) {
			$this->assertNotNull(
				Schema_Loader::load( $trigger['schema'] ),
				"Trigger '{$name}' points at a schema that does not load: {$trigger['schema']}"
			);
		}
	}

	public function test_schema_filename_matches_event_name_and_version() {
		foreach ( $this->registry()->all() as $name => $trigger ) {
			$this->assertSame(
				sprintf( '%s.v%d.json', $name, $trigger['version'] ),
				$trigger['schema'],
				"Trigger '{$name}' schema filename must be <name>.v<version>.json"
			);
		}
	}

	/**
	 * Every declared hook must be a hook the CODE actually fires — checked
	 * against whichever plugin's manifest recorded firing it.
	 *
	 * The brief's original version of this test only ever read Free's own
	 * manifest. That is correct today (only Free registers triggers), but a
	 * later task registers Pro triggers into this SAME registry, and Pro's
	 * hooks are recorded as fired in Pro's own manifest
	 * (`../wb-listora-pro/audit/manifest.json`), not Free's. Checking only
	 * Free's manifest would then fail on correct Pro code the moment Pro
	 * starts contributing entries via `wb_listora_register_triggers` — a
	 * false failure this test must not produce.
	 *
	 * So: a declared hook passes when EITHER manifest records it as fired.
	 * Pro's manifest is only consulted when the Pro tree is checked out
	 * alongside Free (the normal combo-dev layout); on a Free-only checkout
	 * this degrades to exactly the brief's original Free-only check.
	 */
	public function test_every_declared_hook_exists_in_the_manifest_as_fired() {
		$fired = $this->fired_hooks_from_manifest( WB_LISTORA_PLUGIN_DIR . 'audit/manifest.json' );

		$pro_manifest_path = WB_LISTORA_PLUGIN_DIR . '../wb-listora-pro/audit/manifest.json';
		if ( is_readable( $pro_manifest_path ) ) {
			$fired = array_merge( $fired, $this->fired_hooks_from_manifest( $pro_manifest_path ) );
		}

		foreach ( $this->registry()->all() as $name => $trigger ) {
			$this->assertContains(
				$trigger['hook'],
				$fired,
				"Trigger '{$name}' hangs off '{$trigger['hook']}', which no available manifest records as fired"
			);
		}
	}

	/**
	 * Read a manifest file's `hooks_fired[].name` list.
	 *
	 * @param string $path Absolute path to a manifest.json.
	 * @return string[] Fired hook names. Empty when the file is missing or unreadable.
	 */
	private function fired_hooks_from_manifest( $path ) {
		if ( ! is_readable( $path ) ) {
			return array();
		}

		$manifest = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local test fixture read, not a remote fetch.

		if ( empty( $manifest['hooks_fired'] ) || ! is_array( $manifest['hooks_fired'] ) ) {
			return array();
		}

		return wp_list_pluck( $manifest['hooks_fired'], 'name' );
	}

	public function test_every_trigger_declares_a_subscriber_capability() {
		foreach ( $this->registry()->all() as $name => $trigger ) {
			$this->assertNotEmpty(
				$trigger['capability'],
				"Trigger '{$name}' must declare a capability required to subscribe to it"
			);
		}
	}
}
