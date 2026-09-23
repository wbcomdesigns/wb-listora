<?php
/**
 * A listing says who is behind it.
 *
 * Card 10222089571: a visitor had no way to see who listed a business. Only
 * the owner themselves saw an owner bar, and every major directory - Google
 * Maps, Yelp, TripAdvisor - shows a name. Anonymous listings read as
 * untrustworthy.
 *
 * @package WBListora\Tests\Integration
 * @group   listora
 * @group   owner-name
 */

namespace WBListora\Tests\Integration;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * @group listora
 * @group owner-name
 */
class ListingOwnerNameTest extends WP_UnitTestCase {

	/**
	 * Listing under test.
	 *
	 * @var int
	 */
	private $listing_id = 0;

	/**
	 * Its author.
	 *
	 * @var int
	 */
	private $author_id = 0;

	public function set_up() {
		parent::set_up();

		$this->author_id = self::factory()->user->create(
			array(
				'role'         => 'subscriber',
				'user_login'   => 'agency-account-7',
				'display_name' => 'Agency Account',
			)
		);

		$this->listing_id = self::factory()->post->create(
			array(
				'post_type'   => 'listora_listing',
				'post_status' => 'publish',
				'post_title'  => 'The Golden Fork',
				'post_author' => $this->author_id,
			)
		);

		rest_get_server();
	}

	/**
	 * Flip a feature for this test.
	 *
	 * Through the per-feature filter, not the option: `wb_listora_get_features()`
	 * memoizes its map in a function static that a test cannot clear, so writing
	 * the option mid-request changes nothing.
	 *
	 * @param string $slug Feature key.
	 * @param bool   $on   Desired state.
	 */
	private function set_feature( string $slug, bool $on ): void {
		add_filter(
			"wb_listora_feature_{$slug}_enabled",
			static function () use ( $on ) {
				return $on;
			}
		);
	}

	public function test_the_account_display_name_is_the_fallback(): void {
		$this->assertSame( 'Agency Account', wb_listora_get_listing_owner_name( $this->listing_id ) );
		$this->assertStringNotContainsString( 'agency-account-7', wb_listora_get_listing_owner_name( $this->listing_id ) );
	}

	/**
	 * The listing's own contact name wins. The business is what the listing is
	 * about, and the account behind it may be an agency, a staff member, or
	 * "admin".
	 */
	public function test_the_listing_contact_name_wins_over_the_account(): void {
		update_post_meta( $this->listing_id, '_listora_contact_name', 'Priya Raman' );

		$this->assertSame( 'Priya Raman', wb_listora_get_listing_owner_name( $this->listing_id ) );
	}

	public function test_a_blank_contact_name_falls_back_rather_than_showing_nothing(): void {
		update_post_meta( $this->listing_id, '_listora_contact_name', '   ' );

		$this->assertSame( 'Agency Account', wb_listora_get_listing_owner_name( $this->listing_id ) );
	}

	public function test_the_name_is_filterable(): void {
		add_filter( 'wb_listora_listing_owner_name', static fn() => 'Filtered Name' );

		$this->assertSame( 'Filtered Name', wb_listora_get_listing_owner_name( $this->listing_id ) );
	}

	/**
	 * A community site points the name at a member profile instead of
	 * /author/<slug>/, and '' renders it as plain text.
	 */
	public function test_the_url_is_filterable_and_can_be_emptied(): void {
		$this->assertStringContainsString( 'author', wb_listora_get_listing_owner_url( $this->listing_id ) );

		add_filter( 'wb_listora_listing_owner_url', static fn() => '' );
		$this->assertSame( '', wb_listora_get_listing_owner_url( $this->listing_id ) );
	}

	/**
	 * The REST detail route carries the same name the page renders, so the app
	 * and the website cannot name two different people.
	 */
	public function test_the_detail_route_carries_the_same_name(): void {
		update_post_meta( $this->listing_id, '_listora_contact_name', 'Priya Raman' );

		$request = new WP_REST_Request( 'GET', '/listora/v1/listings/' . $this->listing_id . '/detail' );
		$data    = (array) rest_do_request( $request )->get_data();

		$this->assertArrayHasKey( 'owner', $data );
		$this->assertSame( 'Priya Raman', $data['owner']['name'] );
		$this->assertSame( wb_listora_get_listing_owner_name( $this->listing_id ), $data['owner']['name'] );
	}

	/**
	 * Off means off everywhere. A toggle that hides the sidebar card and leaves
	 * the REST field is the half-applied toggle this plugin has been bitten by
	 * before (reviews, card 9895809632).
	 */
	public function test_the_toggle_darkens_the_page_and_the_route_together(): void {
		$this->set_feature( 'owner_name', false );

		$this->assertSame( '', wb_listora_get_listing_owner_name( $this->listing_id ) );

		$request = new WP_REST_Request( 'GET', '/listora/v1/listings/' . $this->listing_id . '/detail' );
		$data    = (array) rest_do_request( $request )->get_data();

		$this->assertArrayNotHasKey( 'owner', $data );
	}

	/**
	 * Every shipped type that collects contact details collects a contact name,
	 * or the owner has no way to set the name the page shows.
	 */
	public function test_shipped_contact_groups_collect_a_contact_name(): void {
		$defaults = \WBListora\Core\Listing_Type_Defaults::get_all();
		$checked  = 0;

		foreach ( $defaults as $slug => $definition ) {
			foreach ( (array) ( $definition['field_groups'] ?? array() ) as $group ) {
				if ( 'contact' !== ( $group['key'] ?? '' ) ) {
					continue;
				}

				$keys = wp_list_pluck( (array) ( $group['fields'] ?? array() ), 'key' );
				$this->assertContains( 'contact_name', $keys, "Type '{$slug}' collects contact details but not a contact name." );
				++$checked;
			}
		}

		$this->assertGreaterThan( 0, $checked, 'No shipped type has a contact group - the assertion above never ran.' );
	}
}
