<?php
/**
 * Activation must leave the site with its three essential pages.
 *
 * Card 10317818112: activation runs after `init` has already fired, so
 * `ensure_essential_pages()` deferred itself to an `init` that never came
 * again and nothing re-hooked it. A fresh install ended up with no Directory,
 * Add Listing or Dashboard page, and `wb_listora_get_public_page_url(
 * 'dashboard' )` returned an empty string, until someone ran the setup wizard.
 *
 * @package WBListora\Tests\Unit
 * @group   listora
 * @group   activation
 */

namespace WBListora\Tests\Unit;

use WBListora\Activator;
use WBListora\Core\Page_Registry;
use WP_UnitTestCase;

/**
 * @group listora
 * @group activation
 */
class EssentialPagesOnActivationTest extends WP_UnitTestCase {

	/**
	 * Forget every page the site has ever auto-created, so `ensure()` treats
	 * this as a site that has never had them (it deliberately refuses to
	 * resurrect a page the owner deleted).
	 */
	private function forget_created_pages(): void {
		foreach ( array( 'directory', 'submission', 'dashboard' ) as $key ) {
			$id = Page_Registry::get_id( $key );
			if ( $id > 0 ) {
				wp_delete_post( $id, true );
			}
		}

		delete_option( Page_Registry::OPTION_CREATED );
		delete_option( Activator::PAGES_PENDING_OPTION );
		delete_option( 'wb_listora_directory_page_id' );
		delete_option( 'wb_listora_submission_page_id' );
		delete_option( 'wb_listora_dashboard_page_id' );

		$settings = (array) get_option( 'wb_listora_settings', array() );
		unset( $settings['directory_page'], $settings['submission_page'], $settings['dashboard_page'] );
		update_option( 'wb_listora_settings', $settings );
	}

	public function tear_down() {
		delete_option( Activator::PAGES_PENDING_OPTION );
		parent::tear_down();
	}

	public function test_essential_pages_resolve_after_ensure(): void {
		$this->forget_created_pages();

		Activator::ensure_essential_pages();

		foreach ( array( 'directory', 'submission', 'dashboard' ) as $key ) {
			$this->assertGreaterThan( 0, Page_Registry::get_id( $key ), "Page '{$key}' should exist." );
		}

		$this->assertNotSame( '', wb_listora_get_public_page_url( 'dashboard' ), 'The dashboard URL must not be an empty string.' );
	}

	public function test_a_pending_flag_is_consumed_on_a_later_request(): void {
		$this->forget_created_pages();

		// The state activation leaves behind when it cannot finish: the flag is
		// set and no page exists yet.
		update_option( Activator::PAGES_PENDING_OPTION, '1', false );
		$this->assertSame( 0, Page_Registry::get_id( 'dashboard' ) );

		// What the next request does with it.
		\WBListora\Plugin::instance()->maybe_ensure_pending_pages();

		$this->assertGreaterThan( 0, Page_Registry::get_id( 'dashboard' ) );
		$this->assertFalse( get_option( Activator::PAGES_PENDING_OPTION ), 'The flag must be cleared once the pages are resolved.' );
	}

	public function test_running_twice_creates_no_duplicates(): void {
		$this->forget_created_pages();

		Activator::ensure_essential_pages();
		$first = Page_Registry::get_id( 'dashboard' );

		Activator::ensure_essential_pages();

		$this->assertSame( $first, Page_Registry::get_id( 'dashboard' ), 'A second run must adopt the same page, not create another.' );
	}
}
