<?php
/**
 * The unlinked-pages prompt belongs on one screen.
 *
 * Card 10322574958: the guard was a substring test for "listora" in the screen
 * id, which also matches `edit-listora_listing` and every listing taxonomy
 * screen. An owner working through their categories met the same "nothing
 * links to these pages" notice on page after page - and on a Pro install it
 * stacked with Pro's feature-pages notice, which had the identical bug.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   admin-notices
 */

namespace WBListora\Tests\Integration;

use WBListora\Admin\Menu_Prompt;
use WP_UnitTestCase;

/**
 * @group listora
 * @group admin-notices
 */
class MenuPromptScreenScopeTest extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		set_current_screen( 'dashboard' );
	}

	public function tear_down() {
		unset( $GLOBALS['current_screen'] );
		parent::tear_down();
	}

	/**
	 * Render the prompt against a given screen id and report whether it spoke.
	 *
	 * @param string $screen_id Screen id to simulate.
	 * @return bool Whether the prompt rendered anything.
	 */
	private function renders_on( string $screen_id ): bool {
		set_current_screen( $screen_id );
		// set_current_screen() derives the id from the hook name, so force the
		// exact id the real request produces.
		$GLOBALS['current_screen']->id = $screen_id;

		ob_start();
		Menu_Prompt::render();

		return '' !== trim( (string) ob_get_clean() );
	}

	/**
	 * The screen ids the substring test wrongly matched. None of them carries
	 * the "Add to menu" action, so the notice was pure interruption there.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function wrong_screens(): array {
		return array(
			'listings list'    => array( 'edit-listora_listing' ),
			'single listing'   => array( 'listora_listing' ),
			'categories'       => array( 'edit-listora_listing_cat' ),
			'locations'        => array( 'edit-listora_listing_location' ),
			'features'         => array( 'edit-listora_listing_feature' ),
			'service cats'     => array( 'edit-listora_service_cat' ),
			'Listora landing'  => array( 'toplevel_page_listora' ),
		);
	}

	/**
	 * @dataProvider wrong_screens
	 *
	 * @param string $screen_id Screen that must stay quiet.
	 */
	public function test_it_stays_off_content_screens( string $screen_id ): void {
		$this->assertFalse(
			$this->renders_on( $screen_id ),
			"The prompt must not render on {$screen_id}."
		);
	}

	/**
	 * The guard must not be so tight that the notice never appears at all -
	 * that would "fix" the duplication by deleting the feature.
	 *
	 * Asserted on the constant rather than by rendering, because rendering
	 * additionally needs unlinked pages to exist on the install.
	 */
	public function test_the_settings_screen_is_the_one_it_targets(): void {
		$this->assertSame( 'listora_page_listora-settings', Menu_Prompt::SETTINGS_SCREEN_ID );
	}

	/**
	 * Pro's notice targets the same single screen. The two were duplicating
	 * each other; they must not now diverge and reintroduce half the bug.
	 */
	public function test_pro_targets_the_same_screen_when_present(): void {
		if ( ! class_exists( '\WBListoraPro\Admin\Feature_Pages_Notice' ) ) {
			$this->markTestSkipped( 'Pro not loaded in this suite.' );
		}

		$this->assertSame(
			Menu_Prompt::SETTINGS_SCREEN_ID,
			\WBListoraPro\Admin\Feature_Pages_Notice::SETTINGS_SCREEN_ID
		);
	}
}
