# Automation Triggers Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Free a canonical trigger registry with versioned JSON-Schema payloads, and make Pro's webhook delivery read it instead of its own private event list — so no event can be dispatched that nobody can subscribe to, and no payload can change without a deliberate version bump.

**Architecture:** A `Trigger_Registry` in Free, behind a contract interface, resolved through the existing `wb_listora_service()` locator at boot. Each trigger declares the hook it hangs off and points at a JSON Schema file on disk. Pro deletes its `EVENTS` constant and its four private payload builders, reading the Free registry and Free's canonical serializers instead. Three new guardrails make the drift that caused this impossible to re-introduce.

**Tech Stack:** PHP 7.4+, WordPress 6.9, PHPUnit (`WP_UnitTestCase`), PHPCS (WordPress standard), PHPStan level 7, bash for guardrails, Action Scheduler for delivery (already in place).

**Spec:** [`plan/automation-integration-surface.md`](automation-integration-surface.md)

## Global Constraints

- **PHP 7.4+.** No typed properties, no union types, no `match`, no constructor promotion, no enums.
- **Namespace:** `WBListora\Automation` in Free, `WBListoraPro\Features` in Pro (existing).
- **File naming:** `class-{kebab-name}.php`, matching the `wb_listora_autoload` kebab rule.
- **INV-3 (non-negotiable):** Pro must never reference `\WBListora\Core\*` or `\WBListora\Automation\*` directly. It resolves through `wb_listora_service()` or documented `wb_listora_*` globals only. `bin/architecture-checks.sh` enforces this and the pre-push hook runs it.
- **Production rule 2:** never rename a public identifier without an alias. Event names and the four existing envelope keys (`event`, `timestamp`, `site_url`, `data`) are frozen.
- **Production rule 8:** minor releases are additive. Nothing in this plan removes a public symbol.
- **Text domains:** `wb-listora` in Free, `wb-listora-pro` in Pro. Never mix them.
- **No em-dashes in customer-facing strings.** Regular hyphen only.
- **Every guardrail must be mutation-tested on authorship:** revert the fix, confirm the check FAILS, restore. Three of the six detectors written during the 1.6.0 wave passed on a deliberate regression the first time. A detector that cannot fail is worse than none, because it reports green.
- **Gate before every commit:** `composer ci:no-journeys` green in the repo you touched. Both repos before the final commit.
- **Do not bump plugin versions.** 1.6.0 is in flight and unreleased; this work is not scheduled against a release.

---

### Task 1: Trigger registry contract and class

**Files:**
- Create: `includes/contracts/class-trigger-registry-interface.php`
- Create: `includes/automation/class-trigger-registry.php`
- Modify: `includes/class-plugin.php` (in `register_services()`, after the `cache` registration ~line 76)
- Test: `tests/unit/TriggerRegistryTest.php`

**Interfaces:**
- Consumes: `WBListora\Service_Locator::register( string $name, object $instance )`, existing.
- Produces:
  - `WBListora\Contracts\Trigger_Registry_Interface` with `register( array $trigger ): bool`, `all(): array`, `get( string $name ): ?array`, `has( string $name ): bool`, `names(): array`
  - Service key `'triggers'`, resolved as `wb_listora_service( 'triggers' )`
  - A trigger array shape: `name`, `label`, `group`, `hook`, `capability`, `version`, `schema`

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * Unit tests for the trigger registry.
 *
 * The registry is the single source of truth for what an automation can
 * subscribe to. Pro's Outgoing_Webhooks reads it instead of its own EVENTS
 * constant, which is what stops a second list drifting from the first —
 * `coupon_redeemed` and `need_posted` were dispatched for releases while
 * being absent from EVENTS, so nobody could subscribe and every dispatch
 * was discarded.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 */

namespace WBListora\Tests\Unit;

use WP_UnitTestCase;
use WBListora\Automation\Trigger_Registry;

/**
 * @group listora
 * @group automation
 */
class TriggerRegistryTest extends WP_UnitTestCase {

	/**
	 * A fresh registry per test — these are boot-time singletons in
	 * production, so a shared instance would leak state across cases.
	 *
	 * @var Trigger_Registry
	 */
	private $registry;

	public function set_up() {
		parent::set_up();
		$this->registry = new Trigger_Registry();
	}

	private function valid_trigger( $overrides = array() ) {
		return array_merge(
			array(
				'name'       => 'listing_approved',
				'label'      => 'Listing Approved',
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_status_changed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_approved.v1.json',
			),
			$overrides
		);
	}

	public function test_registers_and_returns_a_valid_trigger() {
		$this->assertTrue( $this->registry->register( $this->valid_trigger() ) );
		$this->assertTrue( $this->registry->has( 'listing_approved' ) );
		$this->assertSame( 'Listing Approved', $this->registry->get( 'listing_approved' )['label'] );
		$this->assertSame( array( 'listing_approved' ), $this->registry->names() );
	}

	public function test_rejects_a_trigger_missing_a_required_key() {
		foreach ( array( 'name', 'label', 'group', 'hook', 'capability', 'version', 'schema' ) as $key ) {
			$trigger = $this->valid_trigger();
			unset( $trigger[ $key ] );
			$this->assertFalse(
				$this->registry->register( $trigger ),
				"Registering without '{$key}' must be refused"
			);
		}
		$this->assertSame( array(), $this->registry->names() );
	}

	/**
	 * Re-registering is how Pro would accidentally clobber a Free trigger.
	 * First declaration wins, so a Pro typo cannot silently redefine a Free
	 * event's payload.
	 */
	public function test_first_registration_wins() {
		$this->registry->register( $this->valid_trigger() );
		$this->assertFalse( $this->registry->register( $this->valid_trigger( array( 'label' => 'Hijacked' ) ) ) );
		$this->assertSame( 'Listing Approved', $this->registry->get( 'listing_approved' )['label'] );
	}

	public function test_unknown_trigger_reads_as_absent_not_fatal() {
		$this->assertFalse( $this->registry->has( 'nope' ) );
		$this->assertNull( $this->registry->get( 'nope' ) );
	}

	/**
	 * Version must be a positive integer — G15 compares it across commits,
	 * and a string "1" would compare unequal to 1 and fail the build for the
	 * wrong reason.
	 */
	public function test_rejects_non_integer_version() {
		$this->assertFalse( $this->registry->register( $this->valid_trigger( array( 'version' => '1' ) ) ) );
		$this->assertFalse( $this->registry->register( $this->valid_trigger( array( 'version' => 0 ) ) ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter TriggerRegistryTest`
Expected: FAIL — `Class "WBListora\Automation\Trigger_Registry" not found`

- [ ] **Step 3: Write the contract interface**

Create `includes/contracts/class-trigger-registry-interface.php`:

```php
<?php
/**
 * Trigger registry contract.
 *
 * The canonical answer to "what can an automation subscribe to, and what does
 * each event send?". Resolve via:
 *
 *   $triggers = wb_listora_service( 'triggers' );
 *
 * Pro's webhook delivery reads this instead of keeping its own event list.
 * That is the whole point: two lists drift, and they did — `coupon_redeemed`
 * and `need_posted` were dispatched by real handlers while being absent from
 * Pro's EVENTS constant, so the admin UI never offered them, no subscriber
 * could exist, and every dispatch built a payload and threw it away.
 *
 * This registry declares. It never delivers — delivery is Pro's.
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Trigger registry contract.
 */
interface Trigger_Registry_Interface {

	/**
	 * Register a trigger.
	 *
	 * Required keys, all of them:
	 *
	 *   name       string  Stable event key, snake_case. Never renamed.
	 *   label      string  Human-readable, translated, shown in the UI.
	 *   group      string  UI grouping only.
	 *   hook       string  The WordPress hook this event hangs off.
	 *   capability string  Capability required to SUBSCRIBE, not to fire.
	 *   version    int     Current schema version, positive.
	 *   schema     string  Schema filename, relative to the schema directory.
	 *
	 * @param array $trigger Trigger definition.
	 * @return bool True when registered; false when invalid or already taken.
	 */
	public function register( array $trigger );

	/**
	 * Every registered trigger, keyed by name.
	 *
	 * @return array<string, array>
	 */
	public function all();

	/**
	 * One trigger by name.
	 *
	 * @param string $name Trigger name.
	 * @return array|null Null when not registered.
	 */
	public function get( $name );

	/**
	 * Whether a trigger is registered.
	 *
	 * @param string $name Trigger name.
	 * @return bool
	 */
	public function has( $name );

	/**
	 * Every registered trigger name.
	 *
	 * @return string[]
	 */
	public function names();
}
```

- [ ] **Step 4: Write the registry**

Create `includes/automation/class-trigger-registry.php`:

```php
<?php
/**
 * Trigger registry.
 *
 * @package WBListora\Automation
 */

namespace WBListora\Automation;

use WBListora\Contracts\Trigger_Registry_Interface;

defined( 'ABSPATH' ) || exit;

/**
 * Declares what automations can subscribe to. Delivers nothing.
 */
class Trigger_Registry implements Trigger_Registry_Interface {

	/**
	 * Keys every trigger must carry.
	 *
	 * @var string[]
	 */
	const REQUIRED_KEYS = array( 'name', 'label', 'group', 'hook', 'capability', 'version', 'schema' );

	/**
	 * Registered triggers, keyed by name.
	 *
	 * @var array<string, array>
	 */
	private $triggers = array();

	/**
	 * Register a trigger.
	 *
	 * @param array $trigger Trigger definition.
	 * @return bool
	 */
	public function register( array $trigger ) {
		foreach ( self::REQUIRED_KEYS as $key ) {
			if ( ! isset( $trigger[ $key ] ) || '' === $trigger[ $key ] ) {
				return false;
			}
		}

		// Positive integer, strictly. G15 compares versions across commits and
		// a string "1" would compare unequal to 1, failing the build for a
		// reason that has nothing to do with the payload.
		if ( ! is_int( $trigger['version'] ) || $trigger['version'] < 1 ) {
			return false;
		}

		$name = (string) $trigger['name'];

		// First declaration wins. Pro registers into this registry, and a Pro
		// typo must not silently redefine a Free event's payload.
		if ( isset( $this->triggers[ $name ] ) ) {
			return false;
		}

		$this->triggers[ $name ] = $trigger;

		return true;
	}

	/**
	 * Every registered trigger, keyed by name.
	 *
	 * @return array<string, array>
	 */
	public function all() {
		return $this->triggers;
	}

	/**
	 * One trigger by name.
	 *
	 * @param string $name Trigger name.
	 * @return array|null
	 */
	public function get( $name ) {
		$name = (string) $name;

		return isset( $this->triggers[ $name ] ) ? $this->triggers[ $name ] : null;
	}

	/**
	 * Whether a trigger is registered.
	 *
	 * @param string $name Trigger name.
	 * @return bool
	 */
	public function has( $name ) {
		return isset( $this->triggers[ (string) $name ] );
	}

	/**
	 * Every registered trigger name.
	 *
	 * @return string[]
	 */
	public function names() {
		return array_keys( $this->triggers );
	}
}
```

- [ ] **Step 5: Register the service**

In `includes/class-plugin.php`, inside `register_services()`, immediately after the `'cache'` line:

```php
		Service_Locator::register( 'cache', new Services\Cache_Service() );
		// Automation. Registered before wb_listora_loaded fires so Pro can
		// resolve it at its own boot and declare its triggers into it.
		Service_Locator::register( 'triggers', new Automation\Trigger_Registry() );
```

Add the import at the top of the file alongside the existing namespace imports:

```php
use WBListora\Automation;
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter TriggerRegistryTest`
Expected: PASS, 5 tests

- [ ] **Step 7: Verify the service resolves and the gate is green**

Run:
```bash
wp eval 'var_dump( wb_listora_service( "triggers" ) instanceof WBListora\Contracts\Trigger_Registry_Interface );'
composer ci:no-journeys
```
Expected: `bool(true)`, then `local-CI green`

- [ ] **Step 8: Commit**

```bash
git add includes/contracts/class-trigger-registry-interface.php includes/automation/class-trigger-registry.php includes/class-plugin.php tests/unit/TriggerRegistryTest.php
git commit -m "Automation: a registry for what can be subscribed to

Pro keeps its own EVENTS list today, and it drifted: coupon_redeemed and
need_posted are dispatched by real handlers but absent from EVENTS, so the
admin UI never offers them, no subscriber can exist, and every dispatch
builds a payload and discards it.

Adding two lines to EVENTS fixes today and re-opens on the next event
anyone adds. This is the second list, so that the first one can be deleted."
```

---

### Task 2: Schema loader and the first schema files

**Files:**
- Create: `includes/automation/class-schema-loader.php`
- Create: `includes/automation/schemas/listing_approved.v1.json`
- Create: `includes/automation/schemas/coupon_redeemed.v1.json`
- Test: `tests/unit/AutomationSchemaLoaderTest.php`

**Interfaces:**
- Consumes: `Trigger_Registry::get()` from Task 1.
- Produces: `WBListora\Automation\Schema_Loader` with `path( string $file ): string`, `load( string $file ): ?array`, `dir(): string`. Schemas are JSON files on disk, not PHP arrays — diffable in review, servable byte-for-byte by discovery, and diffable by CI without booting WordPress.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter AutomationSchemaLoaderTest`
Expected: FAIL — `Class "WBListora\Automation\Schema_Loader" not found`

- [ ] **Step 3: Write the loader**

Create `includes/automation/class-schema-loader.php`:

```php
<?php
/**
 * Automation schema loader.
 *
 * @package WBListora\Automation
 */

namespace WBListora\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Reads trigger payload schemas from disk.
 */
class Schema_Loader {

	/**
	 * Absolute path to Free's schema directory, with a trailing slash.
	 *
	 * @return string
	 */
	public static function dir() {
		return WB_LISTORA_PLUGIN_DIR . 'includes/automation/schemas/';
	}

	/**
	 * Every directory a schema may live in, in search order.
	 *
	 * Pro declares triggers into Free's registry and ships their schemas in
	 * its own tree — it cannot write into Free's plugin directory, and a Pro
	 * schema landing in Free would be deleted by the next Free update. Pro
	 * appends its own directory here.
	 *
	 * Free's own directory is always first and cannot be displaced, so an
	 * add-on cannot shadow a Free event's schema with its own.
	 *
	 * @since 1.7.0
	 *
	 * @return string[] Absolute paths, each with a trailing slash.
	 */
	public static function dirs() {
		/**
		 * Filters the directories searched for trigger payload schemas.
		 *
		 * @since 1.7.0
		 *
		 * @param string[] $dirs Absolute paths, trailing slash. Free's is first.
		 */
		$dirs = apply_filters( 'wb_listora_automation_schema_dirs', array( self::dir() ) );

		$resolved = array( self::dir() );

		foreach ( (array) $dirs as $dir ) {
			$dir = trailingslashit( (string) $dir );
			if ( '' !== $dir && ! in_array( $dir, $resolved, true ) && is_dir( $dir ) ) {
				$resolved[] = $dir;
			}
		}

		return $resolved;
	}

	/**
	 * Resolve a schema filename to an absolute path inside the schema dir.
	 *
	 * Returns an empty string for anything that is not a bare filename. The
	 * name arrives from a registry entry and Pro can populate the registry,
	 * so a traversal is reachable from another plugin's code — cheap to
	 * refuse, expensive to discover the hard way.
	 *
	 * @param string $file Schema filename.
	 * @return string Absolute path, or '' when the name is not acceptable.
	 */
	public static function path( $file ) {
		$file = (string) $file;

		if ( '' === $file || basename( $file ) !== $file ) {
			return '';
		}

		if ( ! preg_match( '/^[a-z0-9_]+\.v[0-9]+\.json$/', $file ) ) {
			return '';
		}

		foreach ( self::dirs() as $dir ) {
			if ( is_readable( $dir . $file ) ) {
				return $dir . $file;
			}
		}

		return '';
	}

	/**
	 * Load and decode a schema.
	 *
	 * @param string $file Schema filename.
	 * @return array|null Decoded schema, or null when missing or unreadable.
	 */
	public static function load( $file ) {
		$path = self::path( $file );

		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}

		$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file, not a remote fetch.

		return is_array( $decoded ) ? $decoded : null;
	}
}
```

- [ ] **Step 4: Write the two schema files**

Create `includes/automation/schemas/listing_approved.v1.json`:

```json
{
	"$schema": "https://json-schema.org/draft-07/schema#",
	"title": "listing_approved",
	"description": "A listing moved into the publish status from a pending or rejected one.",
	"type": "object",
	"required": ["listing"],
	"properties": {
		"listing": {
			"type": "object",
			"description": "Canonical listing card, identical to what GET /listings returns.",
			"required": ["id", "title", "link"],
			"properties": {
				"id": { "type": "integer" },
				"title": { "type": "string" },
				"link": { "type": "string", "format": "uri" },
				"excerpt": { "type": "string" },
				"featured_image": { "type": ["object", "null"] },
				"rating": { "type": "object" }
			}
		},
		"previous_status": {
			"type": "string",
			"description": "The status the listing held before approval."
		}
	}
}
```

Create `includes/automation/schemas/coupon_redeemed.v1.json`:

```json
{
	"$schema": "https://json-schema.org/draft-07/schema#",
	"title": "coupon_redeemed",
	"description": "A coupon was redeemed. Dispatched since it shipped; subscribable from this release.",
	"type": "object",
	"required": ["coupon_id"],
	"properties": {
		"coupon_id": { "type": "integer" },
		"code": { "type": "string" },
		"user": { "type": ["object", "null"] },
		"context": { "type": "object" }
	}
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter AutomationSchemaLoaderTest`
Expected: PASS, 4 tests

- [ ] **Step 6: Confirm the build does not strip the schemas**

The dist build must ship `includes/`. Confirm the schema files survive packaging:

Run: `grep -nE "exclude|rsync" bin/build-release.sh | grep -i "include\|schema" || echo "no includes/ exclusion — schemas ship"`
Expected: no rule excluding `includes/`. If one exists, add `includes/automation/schemas/` to the keep list before continuing.

- [ ] **Step 7: Commit**

```bash
git add includes/automation/class-schema-loader.php includes/automation/schemas/ tests/unit/AutomationSchemaLoaderTest.php
git commit -m "Automation: payload schemas as JSON files on disk

JSON rather than PHP arrays so CI can diff a payload contract without
booting WordPress, review can read the diff, and discovery can serve them
byte for byte. The filename is refused unless it is a bare
<event>.v<n>.json — Pro populates the registry, so the path is reachable
from another plugin's code."
```

---

### Task 3: Canonical listing serializer for trigger payloads

**Files:**
- Create: `includes/automation/class-payload.php`
- Test: `tests/integration/AutomationPayloadTest.php`

**Interfaces:**
- Consumes: `wb_listora_get_listing_cards( array $listing_ids ): array` — the existing shared card serializer, keyed by listing ID, built in 1.5.0 to unify `/search`, `/favorites` and `/listings/{id}/related`.
- Produces: `WBListora\Automation\Payload::listing( int $listing_id ): ?array`

**Why this task exists:** Pro's `Outgoing_Webhooks` carries a private `build_listing_payload()` that serializes a listing separately from every REST controller, with nothing keeping the two in step. That is the divergence that produced BC 10194450677, where `featured_image` meant a 300px image on one endpoint and a 768px one on another — except an integrator hits it quieter, because their automation does not error, it just stops mapping a field. Free already solved this once with `wb_listora_get_listing_cards()`. Triggers use the same function, so the shapes cannot drift.

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * The trigger payload for a listing must be the SAME shape the REST API
 * serves. Not similar — the same, from the same function.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 */

namespace WBListora\Tests\Integration;

use WP_UnitTestCase;
use WBListora\Automation\Payload;

/**
 * @group listora
 * @group automation
 */
class AutomationPayloadTest extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $listing_id;

	public function set_up() {
		parent::set_up();

		$this->listing_id = $this->factory->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => 'Payload Fixture',
			)
		);
	}

	public function test_listing_payload_is_identical_to_the_shared_card_serializer() {
		$cards = wb_listora_get_listing_cards( array( $this->listing_id ) );

		$this->assertSame(
			$cards[ $this->listing_id ],
			Payload::listing( $this->listing_id ),
			'A webhook listing must be byte-identical to the API listing'
		);
	}

	public function test_missing_listing_returns_null_rather_than_a_half_payload() {
		$this->assertNull( Payload::listing( 99999999 ) );
	}

	/**
	 * A trashed listing still serializes — the trigger that fires on deletion
	 * needs to say WHAT was deleted, and returning null there would send a
	 * payload with no subject.
	 */
	public function test_trashed_listing_still_serializes() {
		wp_trash_post( $this->listing_id );
		$this->assertIsArray( Payload::listing( $this->listing_id ) );
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter AutomationPayloadTest`
Expected: FAIL — `Class "WBListora\Automation\Payload" not found`

- [ ] **Step 3: Write the payload builder**

Create `includes/automation/class-payload.php`:

```php
<?php
/**
 * Canonical trigger payload builders.
 *
 * @package WBListora\Automation
 */

namespace WBListora\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Serializes entities for trigger payloads.
 *
 * Every method here delegates to the serializer the REST API already uses.
 * That is the entire point: Pro previously carried private
 * build_listing_payload() / build_review_payload() / build_claim_payload() /
 * build_user_data() methods, so a listing in a webhook and a listing in the
 * API were two shapes maintained by two people. When they drift, the person
 * who finds out is an integrator whose automation quietly stopped mapping a
 * field.
 */
class Payload {

	/**
	 * Canonical listing payload.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array|null Null when the listing does not exist.
	 */
	public static function listing( $listing_id ) {
		$listing_id = (int) $listing_id;

		if ( $listing_id <= 0 || ! get_post( $listing_id ) ) {
			return null;
		}

		$cards = wb_listora_get_listing_cards( array( $listing_id ) );

		return isset( $cards[ $listing_id ] ) ? $cards[ $listing_id ] : null;
	}
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter AutomationPayloadTest`
Expected: PASS, 3 tests

- [ ] **Step 5: Commit**

```bash
git add includes/automation/class-payload.php tests/integration/AutomationPayloadTest.php
git commit -m "Automation: one listing shape for the API and for webhooks

Pro serializes a listing for webhooks with its own private builder, beside
the REST controllers that serialize the same entity differently. That is
the featured_image divergence (BC 10194450677) with a slower feedback
loop: an integrator's automation does not error when a key moves, it just
stops mapping the field.

wb_listora_get_listing_cards() already exists to unify /search, /favorites
and /related. Triggers use it too."
```

---

### Task 4: Review, claim and user payloads

**Files:**
- Modify: `includes/automation/class-payload.php`
- Modify: `tests/integration/AutomationPayloadTest.php`

**Interfaces:**
- Produces: `Payload::review( int $review_id ): ?array`, `Payload::claim( int $claim_id ): ?array`, `Payload::user( int $user_id ): ?array`

**Before writing code:** read Pro's four private builders at `../wb-listora-pro/includes/features/class-outgoing-webhooks.php:520-625` and the matching REST serializers in `includes/rest/class-reviews-controller.php` and `includes/rest/class-claims-controller.php`. Where a REST controller already has a `prepare_*` method for the entity, delegate to it exactly as Task 3 delegates to the card helper. Where it does not, the canonical shape is the REST response's, and the extraction goes into Free — never a fifth private copy.

- [ ] **Step 1: Write the failing tests**

Append to `tests/integration/AutomationPayloadTest.php`:

```php
	public function test_review_payload_carries_the_api_keys() {
		$review_id = $this->make_review();
		$payload   = Payload::review( $review_id );

		$this->assertIsArray( $payload );
		foreach ( array( 'id', 'listing_id', 'rating', 'content', 'user_name' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, "review payload missing '{$key}'" );
		}
	}

	public function test_user_payload_never_carries_credentials() {
		$user_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$payload = Payload::user( $user_id );

		$this->assertIsArray( $payload );
		foreach ( array( 'user_pass', 'user_activation_key', 'session_tokens' ) as $forbidden ) {
			$this->assertArrayNotHasKey( $forbidden, $payload, "user payload leaks '{$forbidden}'" );
		}
	}

	public function test_missing_entities_return_null() {
		$this->assertNull( Payload::review( 99999999 ) );
		$this->assertNull( Payload::claim( 99999999 ) );
		$this->assertNull( Payload::user( 99999999 ) );
	}
```

Add this helper to the same class:

```php
	/**
	 * Create a review row against the fixture listing.
	 *
	 * @return int Review ID.
	 */
	private function make_review() {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'reviews',
			array(
				'listing_id' => $this->listing_id,
				'user_id'    => $this->factory->user->create(),
				'rating'     => 5,
				'content'    => 'Fixture review',
				'status'     => 'approved',
				'created_at' => current_time( 'mysql', true ),
			)
		);

		return (int) $wpdb->insert_id;
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter AutomationPayloadTest`
Expected: FAIL — `Call to undefined method ...Payload::review()`

- [ ] **Step 3: Implement the three builders**

Add to `includes/automation/class-payload.php`, following the delegation rule above. The `user()` builder is allow-list only — never a `get_userdata()` object spread, which carries `user_pass` and `user_activation_key`:

```php
	/**
	 * Canonical user payload.
	 *
	 * Allow-list, never a spread of the WP_User object. A webhook payload
	 * leaves the site and lands in a third-party log; `user_pass` and
	 * `user_activation_key` are one careless array_merge away from being in
	 * it, and neither is recoverable once sent.
	 *
	 * @param int $user_id User ID.
	 * @return array|null Null when the user does not exist.
	 */
	public static function user( $user_id ) {
		$user_id = (int) $user_id;
		$user    = $user_id > 0 ? get_userdata( $user_id ) : false;

		if ( ! $user ) {
			return null;
		}

		return array(
			'id'           => (int) $user->ID,
			'display_name' => (string) $user->display_name,
			'email'        => (string) $user->user_email,
			'registered'   => (string) $user->user_registered,
		);
	}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter AutomationPayloadTest`
Expected: PASS, 6 tests

- [ ] **Step 5: Add the global wrappers Pro consumes**

INV-3 forbids Pro naming `\WBListora\Automation\Payload` directly, so the
class is reachable only through documented `wb_listora_*` globals. **Task 8
deletes Pro's private builders in favour of these four functions — without
them that task has nothing to call.**

Append to `includes/class-template-helpers.php`, following the
`if ( ! function_exists() )` pattern every helper in that file uses:

```php
if ( ! function_exists( 'wb_listora_automation_payload_listing' ) ) {

	/**
	 * Canonical listing payload for an automation trigger.
	 *
	 * The documented surface for Pro and add-ons. INV-3 forbids naming
	 * \WBListora\Automation\Payload directly, and Pro's own private builder
	 * is exactly what this replaces.
	 *
	 * @since 1.7.0
	 *
	 * @param int $listing_id Listing post ID.
	 * @return array|null Null when the listing does not exist.
	 */
	function wb_listora_automation_payload_listing( $listing_id ) {
		return \WBListora\Automation\Payload::listing( $listing_id );
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_review' ) ) {

	/**
	 * Canonical review payload for an automation trigger.
	 *
	 * @since 1.7.0
	 *
	 * @param int $review_id Review ID.
	 * @return array|null Null when the review does not exist.
	 */
	function wb_listora_automation_payload_review( $review_id ) {
		return \WBListora\Automation\Payload::review( $review_id );
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_claim' ) ) {

	/**
	 * Canonical claim payload for an automation trigger.
	 *
	 * @since 1.7.0
	 *
	 * @param int $claim_id Claim ID.
	 * @return array|null Null when the claim does not exist.
	 */
	function wb_listora_automation_payload_claim( $claim_id ) {
		return \WBListora\Automation\Payload::claim( $claim_id );
	}
}

if ( ! function_exists( 'wb_listora_automation_payload_user' ) ) {

	/**
	 * Canonical user payload for an automation trigger.
	 *
	 * Allow-listed fields only — see Payload::user().
	 *
	 * @since 1.7.0
	 *
	 * @param int $user_id User ID.
	 * @return array|null Null when the user does not exist.
	 */
	function wb_listora_automation_payload_user( $user_id ) {
		return \WBListora\Automation\Payload::user( $user_id );
	}
}
```

- [ ] **Step 6: Prove the wrappers work from outside the namespace**

Run:
```bash
wp eval '$p = wb_listora_automation_payload_user( 1 ); var_dump( is_array( $p ), isset( $p["user_pass"] ) );'
```
Expected: `bool(true)` then `bool(false)` — it resolves, and it does not leak credentials.

- [ ] **Step 7: Run the gate**

Run: `composer ci:no-journeys`
Expected: `local-CI green`

- [ ] **Step 8: Commit**

```bash
git add includes/automation/class-payload.php includes/class-template-helpers.php tests/integration/AutomationPayloadTest.php
git commit -m "Automation: review, claim and user payloads from the API's own shapes

The user payload is an allow-list, not a WP_User spread. A webhook leaves
the site and lands in someone else's log, and user_pass is one careless
array_merge from being in it."
```

---

### Task 5: Declare the real trigger catalogue

**Files:**
- Create: `includes/automation/class-trigger-definitions.php`
- Create: one `includes/automation/schemas/<event>.v1.json` per declared trigger
- Modify: `includes/class-plugin.php` (call the definitions loader after service registration)
- Test: `tests/integration/TriggerCatalogueTest.php`

**Interfaces:**
- Consumes: `wb_listora_service( 'triggers' )`, `Payload::*` from Tasks 3-4.
- Produces: `WBListora\Automation\Trigger_Definitions::register_all( Trigger_Registry_Interface $registry ): void`, and the `wb_listora_register_triggers` action, fired after Free's own declarations so Pro can add its own.

**Scope:** declare every Free-owned event in the spec's groups — listing lifecycle, reviews, claims, members, favourites, services. Money events are Pro-owned and come in Task 7. Apply the spec's three tests to each candidate: it is a business fact, it fires from *every* path that produces the fact, and its payload resolves without request context. **A candidate that fails test 2 does not get declared — it gets a note in the plan's follow-ups instead.** Declaring a trigger that fires on one of two paths is worse than not declaring it, because the automation looks like it works.

- [ ] **Step 1: Write the failing test**

```php
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

	public function test_every_declared_hook_exists_in_the_manifest_as_fired() {
		$manifest = json_decode(
			file_get_contents( WB_LISTORA_PLUGIN_DIR . 'audit/manifest.json' ),
			true
		);
		$fired = wp_list_pluck( $manifest['hooks_fired'], 'name' );

		foreach ( $this->registry()->all() as $name => $trigger ) {
			$this->assertContains(
				$trigger['hook'],
				$fired,
				"Trigger '{$name}' hangs off '{$trigger['hook']}', which the manifest does not record as fired"
			);
		}
	}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter TriggerCatalogueTest`
Expected: FAIL — `test_catalogue_is_not_empty` fails, registry is empty

- [ ] **Step 3: Write the definitions class**

Create `includes/automation/class-trigger-definitions.php`. One entry per Free-owned event, each in this shape:

```php
		$registry->register(
			array(
				'name'       => 'listing_approved',
				'label'      => __( 'Listing Approved', 'wb-listora' ),
				'group'      => 'listing',
				'hook'       => 'wb_listora_listing_status_changed',
				'capability' => 'manage_listora_settings',
				'version'    => 1,
				'schema'     => 'listing_approved.v1.json',
			)
		);
```

End `register_all()` with the extension point Pro consumes:

```php
		/**
		 * Fires after Free declares its triggers.
		 *
		 * Pro and add-ons register their own here. Free's declarations land
		 * first, so a Pro entry can never shadow a Free event — the registry
		 * refuses a duplicate name.
		 *
		 * @since 1.7.0
		 *
		 * @param \WBListora\Contracts\Trigger_Registry_Interface $registry Registry.
		 */
		do_action( 'wb_listora_register_triggers', $registry );
```

- [ ] **Step 4: Write one schema file per declared trigger**

Follow the two files from Task 2 exactly. Every schema is `"type": "object"` with a `properties` block and a `required` array. Entities nest under their own key (`listing`, `review`, `claim`, `user`) and carry the note that they match the API shape.

- [ ] **Step 5: Call the definitions at boot**

In `includes/class-plugin.php`, after `register_services()` returns:

```php
		Automation\Trigger_Definitions::register_all( Service_Locator::get( 'triggers' ) );
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter TriggerCatalogueTest`
Expected: PASS, 4 tests

- [ ] **Step 7: Record the follow-ups**

Any candidate rejected for failing test 2 (fires on one path but not its sibling) goes in `plan/automation-integration-surface.md` under a new "Deferred triggers" heading, naming the event and the missing path. Do not declare it.

- [ ] **Step 8: Commit**

```bash
git add includes/automation/class-trigger-definitions.php includes/automation/schemas/ includes/class-plugin.php tests/integration/TriggerCatalogueTest.php
git commit -m "Automation: declare the Free trigger catalogue

Each entry is a business fact that fires from every path producing it. An
event that fires from REST but not from wp-admin is not declared — that is
the claim audit trail bug (BC 10199419982), and a trigger with one of two
paths wired is worse than none because the automation looks like it works."
```

---

### Task 6: Envelope gains `version` and `id`

**Files:**
- Modify: `../wb-listora-pro/includes/features/class-outgoing-webhooks.php` (`dispatch_event()`, ~line 626)
- Test: `../wb-listora-pro/tests/integration/WebhookEnvelopeTest.php`

**Interfaces:**
- Consumes: `wb_listora_service( 'triggers' )` for the version.
- Produces: envelope `{ event, timestamp, site_url, version, id, data }`.

**Constraint:** the four existing keys keep their names and meanings exactly. Production rule 2 forbids renaming a public identifier without an alias, and every live subscriber parses them. This is additive only.

- [ ] **Step 1: Write the failing test**

```php
	public function test_envelope_keeps_every_legacy_key() {
		$payload = json_decode( $this->capture_dispatch( 'listing_approved' ), true );

		foreach ( array( 'event', 'timestamp', 'site_url', 'data' ) as $key ) {
			$this->assertArrayHasKey( $key, $payload, "legacy envelope key '{$key}' was removed" );
		}
	}

	public function test_envelope_adds_version_and_id() {
		$payload = json_decode( $this->capture_dispatch( 'listing_approved' ), true );

		$this->assertIsInt( $payload['version'] );
		$this->assertMatchesRegularExpression( '/^[0-9a-f-]{36}$/', $payload['id'] );
	}

	public function test_id_is_stable_across_retries_of_one_delivery() {
		$first  = $this->capture_delivery_attempt( 1 );
		$second = $this->capture_delivery_attempt( 2 );

		$this->assertSame(
			json_decode( $first, true )['id'],
			json_decode( $second, true )['id'],
			'A retry must carry the same id so a receiver can dedupe'
		);
	}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ../wb-listora-pro && vendor/bin/phpunit --filter WebhookEnvelopeTest`
Expected: FAIL — `version` key absent

- [ ] **Step 3: Add the two keys**

In `dispatch_event()`, replace the payload assembly:

```php
		$triggers = function_exists( 'wb_listora_service' ) ? wb_listora_service( 'triggers' ) : null;
		$trigger  = $triggers ? $triggers->get( $event ) : null;

		$payload = wp_json_encode(
			array(
				'event'     => $event,
				'timestamp' => gmdate( 'c' ),
				'site_url'  => home_url(),
				// Schema version of THIS event, so a receiver can branch on a
				// payload change without caring about events it does not
				// consume. Falls back to 1 when Free is older than the
				// registry — the envelope must not break on a version skew.
				'version'   => $trigger ? (int) $trigger['version'] : 1,
				// Stable across every retry of this delivery, so a receiver
				// can dedupe. Generated once here, not per attempt.
				'id'        => wp_generate_uuid4(),
				'data'      => $data,
			)
		);
```

The `id` must be generated here, before the per-subscriber loop creates log entries, so each subscriber gets its own id and every retry of that delivery reuses the stored payload.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd ../wb-listora-pro && vendor/bin/phpunit --filter WebhookEnvelopeTest`
Expected: PASS, 3 tests

- [ ] **Step 5: Commit**

```bash
cd ../wb-listora-pro
git add includes/features/class-outgoing-webhooks.php tests/integration/WebhookEnvelopeTest.php
git commit -m "Webhooks: add version and id to the envelope

Additive only. The four existing keys keep their names and meanings —
every live subscriber parses them and production rule 2 forbids renaming a
public identifier without an alias.

id is stable across retries so a receiver can dedupe; version is per-event
so an integration need not care about payload changes to events it does
not consume."
```

---

### Task 7: Pro reads the registry; `EVENTS` is deleted

**Files:**
- Modify: `../wb-listora-pro/includes/features/class-outgoing-webhooks.php` (delete `EVENTS` ~line 71; update the admin UI and `get_active_webhooks_for_event()`)
- Create: `../wb-listora-pro/includes/automation/class-pro-trigger-definitions.php`
- Test: `../wb-listora-pro/tests/integration/WebhookEventSourceTest.php`

**Interfaces:**
- Consumes: `wb_listora_service( 'triggers' )`, the `wb_listora_register_triggers` action from Task 5.
- Produces: Pro's money-group triggers registered into Free's registry.

**This is the task the whole plan exists for.** Adding `coupon_redeemed` and `need_posted` to `EVENTS` would take thirty seconds and fix today. Deleting `EVENTS` means there is no second list to drift, ever again.

- [ ] **Step 1: Write the failing test**

```php
	/**
	 * The bug, encoded. Both are dispatched by real handlers and neither
	 * appeared in EVENTS, so no subscriber could exist and every dispatch was
	 * discarded.
	 */
	public function test_previously_orphaned_events_are_subscribable() {
		$registry = wb_listora_service( 'triggers' );

		$this->assertTrue( $registry->has( 'coupon_redeemed' ) );
		$this->assertTrue( $registry->has( 'need_posted' ) );
	}

	public function test_every_dispatched_event_is_registered() {
		$source     = file_get_contents( WB_LISTORA_PRO_DIR . 'includes/features/class-outgoing-webhooks.php' );
		$dispatched = array();
		preg_match_all( "/dispatch_event\(\s*'([a-z_]+)'/", $source, $dispatched );

		$registry = wb_listora_service( 'triggers' );

		foreach ( array_unique( $dispatched[1] ) as $event ) {
			$this->assertTrue(
				$registry->has( $event ),
				"'{$event}' is dispatched but not registered — nobody can subscribe to it"
			);
		}
	}

	public function test_events_constant_is_gone() {
		$this->assertFalse(
			defined( '\WBListoraPro\Features\Outgoing_Webhooks::EVENTS' ),
			'EVENTS is the second list this change exists to delete'
		);
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd ../wb-listora-pro && vendor/bin/phpunit --filter WebhookEventSourceTest`
Expected: FAIL — `coupon_redeemed` not registered

- [ ] **Step 3: Register Pro's triggers**

Create `../wb-listora-pro/includes/automation/class-pro-trigger-definitions.php`, hooked to `wb_listora_register_triggers`, declaring the money group plus the two orphans. Same entry shape as Task 5, text domain `wb-listora-pro`. Schema files go in Pro at `includes/automation/schemas/`. Pro makes them findable by appending its directory to the `wb_listora_automation_schema_dirs` filter that `Schema_Loader::dirs()` fires (Task 2):

```php
add_filter(
	'wb_listora_automation_schema_dirs',
	static function ( $dirs ) {
		$dirs[] = WB_LISTORA_PRO_DIR . 'includes/automation/schemas/';

		return $dirs;
	}
);
```

Pro cannot write into Free's plugin directory, and a Pro schema placed there would be deleted by the next Free update — hence the filter rather than a shared folder.

- [ ] **Step 4: Delete `EVENTS` and read the registry**

Remove the `EVENTS` constant. Replace every read of it. In the admin UI that renders the subscriber checkbox list:

```php
		$triggers = wb_listora_service( 'triggers' );
		$events   = array();

		if ( $triggers ) {
			foreach ( $triggers->all() as $name => $trigger ) {
				if ( current_user_can( $trigger['capability'] ) ) {
					$events[ $name ] = $trigger['label'];
				}
			}
		}
```

The capability check is why a trigger carries one: `payment_received` and `credits_added` expose money and `claim_submitted` exposes a member's contact details, so who may subscribe is not the same question as who may fire.

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd ../wb-listora-pro && vendor/bin/phpunit --filter WebhookEventSourceTest`
Expected: PASS, 3 tests

- [ ] **Step 6: Verify the architecture invariants still hold**

Run: `cd ../wb-listora-pro && composer arch-checks`
Expected: all 14 pass, INV-3 in particular — Pro resolves via `wb_listora_service()` and must not name `\WBListora\Automation\*`.

- [ ] **Step 7: Commit**

```bash
cd ../wb-listora-pro
git add includes/features/class-outgoing-webhooks.php includes/automation/ tests/integration/WebhookEventSourceTest.php
git commit -m "Webhooks: delete EVENTS, read Free's trigger registry

coupon_redeemed and need_posted have been dispatched into the void since
they shipped — real handlers building payloads that no subscriber could
ever receive, because neither name was in EVENTS and the admin UI builds
its checkbox list from EVENTS.

Adding two lines would fix today and re-open on the next event anyone
adds. This deletes the second list instead."
```

---

### Task 8: Delete Pro's private payload builders

**Files:**
- Modify: `../wb-listora-pro/includes/features/class-outgoing-webhooks.php:520-625`
- Test: `../wb-listora-pro/tests/integration/WebhookPayloadParityTest.php`

- [ ] **Step 1: Write the failing test**

```php
	public function test_webhook_listing_matches_the_api_listing() {
		$listing_id = $this->factory->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
			)
		);

		$payload = json_decode( $this->capture_dispatch_for_listing( $listing_id ), true );
		$cards   = wb_listora_get_listing_cards( array( $listing_id ) );

		$this->assertSame(
			$cards[ $listing_id ],
			$payload['data']['listing'],
			'A webhook listing must be the same shape the API serves'
		);
	}

	public function test_private_builders_are_gone() {
		$source = file_get_contents( WB_LISTORA_PRO_DIR . 'includes/features/class-outgoing-webhooks.php' );

		foreach ( array( 'build_listing_payload', 'build_review_payload', 'build_claim_payload', 'build_user_data' ) as $method ) {
			$this->assertStringNotContainsString(
				"function {$method}",
				$source,
				"{$method}() is a second shape for an entity the API already serializes"
			);
		}
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd ../wb-listora-pro && vendor/bin/phpunit --filter WebhookPayloadParityTest`
Expected: FAIL — builders still present

- [ ] **Step 3: Replace every call site**

Swap each `$this->build_*()` call for the Free global added in Task 4, then delete the four private methods:

| Pro private method | Free replacement |
|---|---|
| `$this->build_listing_payload( $post_id )` | `wb_listora_automation_payload_listing( $post_id )` |
| `$this->build_review_payload( $review_id, $listing_id, $user_id )` | `wb_listora_automation_payload_review( $review_id )` |
| `$this->build_claim_payload( $claim_id, $listing_id )` | `wb_listora_automation_payload_claim( $claim_id )` |
| `$this->build_user_data( $user_id )` | `wb_listora_automation_payload_user( $user_id )` |

Guard each call with `function_exists()` — Pro's minimum Free version floor (`WB_LISTORA_PRO_MIN_FREE_VERSION`) must also be raised to the Free release carrying these, or an older Free leaves the feature silently inactive. That floor sat at 1.1.0 while Pro reached 1.4.1 once already (LST-P-08).

Note the signature narrowing: the review and claim builders took redundant arguments the payload can resolve from the primary key itself.

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd ../wb-listora-pro && vendor/bin/phpunit --filter WebhookPayloadParityTest`
Expected: PASS, 2 tests

- [ ] **Step 5: Run both gates**

Run: `composer ci:no-journeys` in Pro, then in Free.
Expected: `local-CI green` in both

- [ ] **Step 6: Commit**

```bash
cd ../wb-listora-pro
git add includes/features/class-outgoing-webhooks.php tests/integration/WebhookPayloadParityTest.php
git commit -m "Webhooks: one entity shape, from Free's canonical serializers

Four private builders deleted. A webhook's listing is now the same shape
GET /listings serves, from the same function, so the two cannot drift."
```

---

### Task 9: G12 — every dispatched event is registered

**Files:**
- Modify: `bin/audit-guardrails.sh` (append after G11)

**Interfaces:**
- Consumes: `$FREE_DIR`, `$PRO_DIR`, `violation()`, `ok()` — all existing in the script.

- [ ] **Step 1: Write the check**

Append to `bin/audit-guardrails.sh`:

```bash
# ── G12: every dispatched webhook event is a registered trigger ─────────────
# coupon_redeemed and need_posted were dispatched by real handlers for
# releases while being absent from Pro's EVENTS constant, so the admin UI
# never offered them, no subscriber could exist, and every dispatch built a
# payload and discarded it. EVENTS is gone; this is what keeps it gone.
echo "G12 — dispatched events are registered triggers"
G12_FAIL=""
if [ -n "$PRO_DIR" ] && [ -f "$PRO_DIR/includes/features/class-outgoing-webhooks.php" ]; then
  DISPATCHED="$(grep -oE "dispatch_event\(\s*'[a-z_]+'" \
    "$PRO_DIR/includes/features/class-outgoing-webhooks.php" \
    | sed -E "s/.*'([a-z_]+)'/\1/" | sort -u)"
  DECLARED="$(grep -horE "'name'\s*=>\s*'[a-z_]+'" \
    "$FREE_DIR/includes/automation/" "$PRO_DIR/includes/automation/" 2>/dev/null \
    | sed -E "s/.*'([a-z_]+)'/\1/" | sort -u)"
  for ev in $DISPATCHED; do
    echo "$DECLARED" | grep -qx "$ev" || G12_FAIL="$G12_FAIL $ev"
  done
fi
if [ -n "$G12_FAIL" ]; then
  violation "dispatched but not registered (nobody can subscribe):$G12_FAIL"
else
  ok "every dispatched event is a registered trigger"
fi
```

- [ ] **Step 2: Mutation-test it**

```bash
# Break it deliberately: comment out one registration.
sed -i.bak "s/'name'       => 'coupon_redeemed'/'name'       => 'coupon_redeemed_TYPO'/" \
  ../wb-listora-pro/includes/automation/class-pro-trigger-definitions.php
bash bin/audit-guardrails.sh; echo "exit=$?"
```
Expected: **FAIL**, naming `coupon_redeemed`. If it passes, the check is broken — fix it before restoring.

```bash
mv ../wb-listora-pro/includes/automation/class-pro-trigger-definitions.php.bak \
   ../wb-listora-pro/includes/automation/class-pro-trigger-definitions.php
bash bin/audit-guardrails.sh; echo "exit=$?"
```
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add bin/audit-guardrails.sh
git commit -m "G12: fail the build when an event is dispatched but not registered

Mutation-tested: renaming a registration makes this fail, restoring it
makes it pass."
```

---

### Task 10: G14 and G15 — schema exists, and cannot change without a version bump

**Files:**
- Modify: `bin/audit-guardrails.sh`

- [ ] **Step 1: Write G14**

```bash
# ── G14: every registered trigger has a schema file ─────────────────────────
echo "G14 — every trigger has a schema on disk"
G14_FAIL=""
for dir in "$FREE_DIR" "$PRO_DIR"; do
  [ -z "$dir" ] && continue
  [ -d "$dir/includes/automation" ] || continue
  while IFS= read -r schema; do
    [ -z "$schema" ] && continue
    found=""
    for sdir in "$FREE_DIR/includes/automation/schemas" "$PRO_DIR/includes/automation/schemas"; do
      [ -f "$sdir/$schema" ] && found="yes"
    done
    [ -n "$found" ] || G14_FAIL="$G14_FAIL $schema"
  done <<< "$(grep -horE "'schema'\s*=>\s*'[a-z0-9_.]+'" "$dir/includes/automation/" 2>/dev/null | sed -E "s/.*'([a-z0-9_.]+)'/\1/" | sort -u)"
done
if [ -n "$G14_FAIL" ]; then
  violation "trigger declares a schema file that does not exist:$G14_FAIL"
else
  ok "every declared schema file exists"
fi
```

- [ ] **Step 2: Write G15**

```bash
# ── G15: a changed schema needs a bumped version ────────────────────────────
# The one check that protects live integrations. A subscriber whose payload
# shape changes under them does not get an error — their automation quietly
# stops mapping a field, and they find out days later from missing data.
# Compares against origin/main, so it only fires on real changes.
echo "G15 — changed schemas carry a version bump"
G15_FAIL=""
if git -C "$FREE_DIR" rev-parse --verify origin/main >/dev/null 2>&1; then
  CHANGED="$(git -C "$FREE_DIR" diff --name-only origin/main...HEAD -- 'includes/automation/schemas/*.json' 2>/dev/null)"
  for f in $CHANGED; do
    # A NEW schema file is fine — it is a new event or a deliberate new
    # version. Only an EDITED one is the problem.
    if git -C "$FREE_DIR" cat-file -e "origin/main:$f" 2>/dev/null; then
      base="$(basename "$f")"
      event="${base%%.v*}"
      ver="$(echo "$base" | sed -E 's/.*\.v([0-9]+)\.json/\1/')"
      next="$((ver + 1))"
      if [ ! -f "$FREE_DIR/includes/automation/schemas/${event}.v${next}.json" ]; then
        G15_FAIL="$G15_FAIL ${base}"
      fi
    fi
  done
fi
if [ -n "$G15_FAIL" ]; then
  violation "schema edited in place without a new version file:$G15_FAIL (add ${event}.v${next}.json and bump the registry entry)"
else
  ok "no schema changed without a version bump"
fi
```

- [ ] **Step 3: Mutation-test both**

```bash
# G14: point a trigger at a schema that does not exist.
sed -i.bak "s/'listing_approved.v1.json'/'listing_approved.v9.json'/" includes/automation/class-trigger-definitions.php
bash bin/audit-guardrails.sh; echo "exit=$?"   # expect FAIL naming listing_approved.v9.json
mv includes/automation/class-trigger-definitions.php.bak includes/automation/class-trigger-definitions.php

# G15: edit a schema in place without adding a v2.
echo '{"type":"object","properties":{"broken":{"type":"string"}}}' > includes/automation/schemas/listing_approved.v1.json
bash bin/audit-guardrails.sh; echo "exit=$?"   # expect FAIL naming listing_approved.v1.json
git checkout includes/automation/schemas/listing_approved.v1.json
bash bin/audit-guardrails.sh; echo "exit=$?"   # expect PASS
```

Each must FAIL when broken and PASS when restored. A check that stays green through its own mutation is worse than no check — it reports safety that is not there.

- [ ] **Step 4: Commit**

```bash
git add bin/audit-guardrails.sh
git commit -m "G14, G15: schemas must exist, and cannot change without a version

G15 is the one that protects integrations in the field. Both
mutation-tested."
```

---

### Task 11: QA — receiver fixture, journeys, runbook, manifests

**Files:**
- Create: `docs/qa/fixtures/webhook-receiver.php`
- Create: `docs/qa/journeys/regression/webhook-trigger-registry.md`
- Create: `../wb-listora-pro/docs/qa/journeys/regression/webhook-delivery-and-retry.md`
- Modify: `docs/qa/AGENT_SMOKE_RUNBOOK.md`
- Modify: `audit/manifest.json` and `../wb-listora-pro/audit/manifest.json`

- [ ] **Step 1: Write the receiver fixture**

A single-file endpoint that records what it receives — method, headers, body — to a JSON file, and can be told to return 500 on the first N requests so the retry ladder is exercisable. Journeys read the recording to assert delivery rather than assuming it.

- [ ] **Step 2: Write the Free journey**

`webhook-trigger-registry.md` asserts: every event in the subscriber UI comes from the registry; `coupon_redeemed` and `need_posted` are now offered and deliver; an event with a capability the current user lacks is not offered; the payload's `listing` matches `GET /listings/{id}` field for field.

- [ ] **Step 3: Write the Pro journey**

`webhook-delivery-and-retry.md` asserts: HMAC signature verifies against the shared secret; a receiver returning 500 is retried on the 60 / 300 / 1800 ladder and gives up at `MAX_ATTEMPTS` 4; every retry carries the same `id` so a receiver can dedupe; the delivery log records each attempt.

- [ ] **Step 4: Add the runbook rows**

One `D.` row per journey in `docs/qa/AGENT_SMOKE_RUNBOOK.md`, following the existing format: card reference, what broke, and the assertions. A webhook that silently stops delivering is invisible to every check that exists today, which is why these rows matter more than most.

- [ ] **Step 5: Update both manifests**

Targeted delta, agent-enumerated — never the deterministic generator output. Add: the `wb_listora_register_triggers` action, the `triggers` service key, the new `wb_listora_automation_payload_*` helpers, and a note recording that Pro's `EVENTS` constant and four private builders were removed.

- [ ] **Step 6: Run everything**

```bash
composer ci:no-journeys           # Free
cd ../wb-listora-pro && composer ci:no-journeys
cd ../wb-listora && bash bin/audit-guardrails.sh
```
Expected: green, green, and G1-G15 all passing.

- [ ] **Step 7: Commit**

```bash
git add docs/qa/ audit/manifest.json
git commit -m "QA: webhook receiver fixture, journeys and runbook rows

A webhook that silently stops delivering is invisible to every check that
existed before this. The fixture lets a journey assert real delivery,
signature and retry rather than intent."
```

---

## Follow-ups (not in this plan)

- **Plan 2 — Actions and discovery:** `Action_Registry`, the catalogue over the 136 existing routes, Application Password auth, `GET /automation/catalogue`, and G13.
- Any trigger candidate rejected in Task 5 for firing on one path but not its sibling. Each needs the missing path wired before it can be declared.
