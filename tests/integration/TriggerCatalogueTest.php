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
use WBListora\Automation\Trigger_Registry;
use WBListora\Automation\Trigger_Definitions;

/**
 * @group listora
 * @group automation
 */
class TriggerCatalogueTest extends WP_UnitTestCase {

	/**
	 * Every trigger name this task declares. Kept as an explicit list (not
	 * derived from the registry under test) so a whole group silently
	 * dropped, or a name silently lost to a duplicate-name collision inside
	 * Trigger_Registry::register()'s "first declaration wins" rule, shows up
	 * as a set difference instead of passing because the registry merely
	 * "is not empty".
	 *
	 * @var string[]
	 */
	private const EXPECTED_TRIGGER_NAMES = array(
		'listing_submitted',
		'listing_approved',
		'listing_rejected',
		'listing_deactivated',
		'listing_reactivated',
		'listing_expired',
		'listing_claimed',
		'listing_renewed',
		'listing_reported',
		'listing_pending_review',
		'review_submitted',
		'review_reply_posted',
		'claim_submitted',
		'claim_approved',
		'claim_rejected',
		'member_suspended',
		'member_unsuspended',
		'account_deactivated',
		'account_reactivated',
		'account_deleted',
		'favorite_added',
		'favorite_removed',
		'service_created',
		'service_updated',
		'service_deleted',
	);

	/**
	 * Hook name prefixes/exact-names Ruling A forbids a trigger from using —
	 * template/render extension points that fire on every page render with
	 * view data, not on a business event. Mirrors the literal list in the
	 * task brief plus the render-hook families confirmed in the manifest.
	 *
	 * @var string[]
	 */
	private const RENDER_HOOK_DENYLIST = array(
		'wb_listora_after_card',
		'wb_listora_before_card',
		'wb_listora_grid_after_card',
		'wb_listora_after_listing_card',
		'wb_listora_before_listing_card',
		'wb_listora_after_dashboard_',
		'wb_listora_before_dashboard_',
		'wb_listora_after_detail_',
		'wb_listora_before_detail_',
		'wb_listora_after_map',
		'wb_listora_before_map',
		'wb_listora_after_calendar',
		'wb_listora_before_calendar',
		'wb_listora_after_template',
		'wb_listora_before_template',
		'wb_listora_after_reviews',
		'wb_listora_before_reviews',
		'wb_listora_review_after_content',
		'wb_listora_review_form_after_content',
		'wb_listora_after_search_results',
		'wb_listora_search_before_form',
		'wb_listora_search_after_form',
		'wb_listora_after_categories_grid',
		'wb_listora_before_categories_grid',
		'wb_listora_after_featured_listings',
		'wb_listora_before_featured_listings',
		'wb_listora_after_related_listings',
		'wb_listora_before_related_listings',
		'wb_listora_after_listing_grid',
		'wb_listora_before_listing_grid',
		'wb_listora_after_listing_fields',
		'wb_listora_after_service_detail',
		'wb_listora_listing_title_badges',
		'wb_listora_detail_actions',
		'wb_listora_detail_owner_bar_actions',
		'wb_listora_card_actions',
		'wb_listora_appointment_button',
	);

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
	 *
	 * Known limitation this test cannot catch on its own (why the manifest
	 * check is necessary but not sufficient): a hook can be recorded as
	 * "fired" in the manifest yet be dead in practice because every real
	 * call site is gated on a condition that can never be true — exactly
	 * what happened to `wb_listora_listing_pending_admin`, which is recorded
	 * as fired but every call site is unreachable because
	 * `pending_verification` status can never be produced. That class of bug
	 * needs source-level reasoning, not a string match against a generated
	 * inventory; it is not something a catalogue test can mechanically
	 * detect without re-deriving reachability analysis. See
	 * `test_listing_pending_review_condition_actually_fires_on_pending_transition()`
	 * below for the direct proof that the SPECIFIC hook this catalogue now
	 * uses for that trigger really does fire.
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

	/**
	 * The catalogue is exactly the 25 names this task declares — no group
	 * silently dropped, no name silently lost to a duplicate-name
	 * collision.
	 *
	 * Would fail today if, e.g., a copy-paste left two entries with the same
	 * `name`: `Trigger_Registry::register()` returns `false` for the second
	 * one and stores nothing, so the registry would report only 24 names
	 * and this assertion would catch the gap where nothing else in this
	 * file would.
	 */
	public function test_catalogue_is_exactly_the_expected_25_names() {
		$actual = $this->registry()->names();
		sort( $actual );

		$expected = self::EXPECTED_TRIGGER_NAMES;
		sort( $expected );

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Every declared trigger actually registered successfully — checked by
	 * re-running `Trigger_Definitions::register_all()` against a FRESH
	 * registry and confirming every expected name landed, rather than
	 * asserting against the shared boot-time registry (which pools together
	 * whatever Free's boot AND any other test's registrations produced).
	 *
	 * `Trigger_Registry::register()` returns a bool, but
	 * `Trigger_Definitions::register_all()` is (deliberately, per the task
	 * brief) `void` — it does not collect or return per-entry results. The
	 * only externally observable proof that every individual `register()`
	 * call returned `true` is that every expected name is present on the
	 * registry it populated: `register()` returning `false` (malformed
	 * shape, or a duplicate `name`) is the ONLY way an expected name could
	 * be absent here, since every name in EXPECTED_TRIGGER_NAMES is used
	 * exactly once in `class-trigger-definitions.php`.
	 */
	public function test_every_expected_trigger_registered_successfully() {
		$fresh = new Trigger_Registry();
		Trigger_Definitions::register_all( $fresh );

		foreach ( self::EXPECTED_TRIGGER_NAMES as $name ) {
			$this->assertTrue(
				$fresh->has( $name ),
				"register() did not succeed for '{$name}' — likely a malformed entry or a duplicate name"
			);
		}
	}

	/**
	 * No declared trigger hangs off a template/render extension point.
	 *
	 * Mutation-tested by hand: temporarily adding
	 * `'hook' => 'wb_listora_after_map'` to a throwaway entry in
	 * `class-trigger-definitions.php` and re-running this test made it fail
	 * with the expected message, confirming the denylist is live rather
	 * than vacuously true.
	 */
	public function test_no_declared_trigger_hangs_off_a_render_hook() {
		foreach ( $this->registry()->all() as $name => $trigger ) {
			foreach ( self::RENDER_HOOK_DENYLIST as $denied ) {
				$this->assertFalse(
					0 === strncmp( $trigger['hook'], $denied, strlen( $denied ) ),
					"Trigger '{$name}' hangs off '{$trigger['hook']}', which matches the Ruling A render-hook denylist prefix '{$denied}'"
				);
			}
		}
	}

	/**
	 * Two triggers sharing a hook must be distinguishable by `condition`,
	 * and no two triggers on the same hook may declare overlapping
	 * conditions — an overlap means one occurrence fires both, the exact
	 * double-delivery Ruling B forbids, just one layer later (dispatcher
	 * instead of registry).
	 *
	 * Would have caught C2 (four/six triggers sharing two hooks with no
	 * `condition` field at all) had it existed before that review round.
	 */
	public function test_triggers_sharing_a_hook_have_mutually_exclusive_conditions() {
		$by_hook = array();
		foreach ( $this->registry()->all() as $name => $trigger ) {
			$by_hook[ $trigger['hook'] ][ $name ] = $trigger;
		}

		foreach ( $by_hook as $hook => $triggers ) {
			if ( count( $triggers ) < 2 ) {
				continue;
			}

			foreach ( $triggers as $name => $trigger ) {
				$this->assertArrayHasKey(
					'condition',
					$trigger,
					"Trigger '{$name}' shares hook '{$hook}' with " . ( count( $triggers ) - 1 ) . ' other trigger(s) but declares no condition to distinguish them'
				);
			}

			$names = array_keys( $triggers );
			for ( $i = 0; $i < count( $names ); $i++ ) {
				for ( $j = $i + 1; $j < count( $names ); $j++ ) {
					$a = $triggers[ $names[ $i ] ]['condition'];
					$b = $triggers[ $names[ $j ] ]['condition'];
					$this->assertFalse(
						$this->conditions_overlap( $a, $b ),
						"Triggers '{$names[ $i ]}' and '{$names[ $j ]}' share hook '{$hook}' with overlapping conditions — one occurrence would fire both"
					);
				}
			}
		}
	}

	/**
	 * Whether two trigger `condition` arrays could both match the same
	 * fired-hook occurrence.
	 *
	 * @param array<string, mixed> $a First condition.
	 * @param array<string, mixed> $b Second condition.
	 * @return bool
	 */
	private function conditions_overlap( array $a, array $b ) {
		$a_new = $a['new_status'] ?? null;
		$b_new = $b['new_status'] ?? null;

		if ( $a_new !== $b_new ) {
			return false;
		}

		$a_prev = isset( $a['previous_status'] ) ? (array) $a['previous_status'] : null;
		$b_prev = isset( $b['previous_status'] ) ? (array) $b['previous_status'] : null;

		if ( null === $a_prev || null === $b_prev ) {
			// Same new_status and at least one side does not further
			// constrain previous_status — the unconstrained side matches
			// everything the constrained side matches.
			return true;
		}

		return (bool) array_intersect( $a_prev, $b_prev );
	}

	/**
	 * C1 proof: `listing_pending_review`'s condition
	 * (`new_status === 'pending'`) is reached by a real status transition on
	 * `wb_listora_listing_status_changed`, the hook it is now declared on.
	 *
	 * This is deliberately NOT a manifest check (the old, wrong hook was
	 * ALSO recorded as "fired" in the manifest — that is exactly how C1
	 * shipped green). It creates a real `listora_listing` post, transitions
	 * it from `draft` to `pending` via `wp_update_post()` (the same
	 * mechanism every real path — REST, wp-admin, WP-CLI — uses), captures
	 * the hook's actual fired arguments, and asserts they satisfy the
	 * declared condition.
	 */
	public function test_listing_pending_review_condition_actually_fires_on_pending_transition() {
		$trigger = $this->registry()->get( 'listing_pending_review' );
		$this->assertNotNull( $trigger, 'listing_pending_review must be declared' );
		$this->assertSame( 'wb_listora_listing_status_changed', $trigger['hook'] );
		$this->assertSame( 'pending', $trigger['condition']['new_status'] );

		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'draft',
				'post_title'  => 'C1 pending-transition fixture',
			)
		);

		$captured = array();
		$listener = function ( $id, $new_status, $old_status ) use ( &$captured ) {
			$captured[] = array(
				'post_id'         => $id,
				'new_status'      => $new_status,
				'previous_status' => $old_status,
			);
		};
		add_action( 'wb_listora_listing_status_changed', $listener, 20, 3 );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'pending',
			)
		);

		remove_action( 'wb_listora_listing_status_changed', $listener, 20 );

		$this->assertNotEmpty(
			$captured,
			'wb_listora_listing_status_changed did not fire at all for a draft -> pending transition'
		);

		$matched = false;
		foreach ( $captured as $fire ) {
			if ( (int) $fire['post_id'] === $post_id && 'pending' === $fire['new_status'] ) {
				$matched = true;
				break;
			}
		}

		$this->assertTrue(
			$matched,
			'None of the captured wb_listora_listing_status_changed fires satisfied listing_pending_review\'s condition (new_status === pending). Captured: ' . wp_json_encode( $captured )
		);
	}
}
