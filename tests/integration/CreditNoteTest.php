<?php
/**
 * Credit history lines: the SDK's English notes become translated lines;
 * notes Listora or an administrator wrote are kept.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group credits
 */
class CreditNoteTest extends WP_UnitTestCase {

	public function test_sdk_notes_become_reason_lines() {
		$listing = self::factory()->post->create(
			array(
				'post_type'  => 'listora_listing',
				'post_title' => 'Golden Fork',
			)
		);

		$this->assertSame( 'Hold released', wb_listora_credit_note( array( 'reason' => 'hold_release', 'note' => 'Hold released on approval' ) ) );
		$this->assertSame( 'Spent on Golden Fork', wb_listora_credit_note( array( 'reason' => 'spend', 'note' => 'Credits spent', 'item_id' => $listing ) ) );
		$this->assertSame( 'Unused credits expired', wb_listora_credit_note( array( 'reason' => 'expiry', 'note' => 'Credits expired (lot #3)' ) ) );
		$this->assertSame( 'Adjusted by an administrator', wb_listora_credit_note( array( 'reason' => 'admin_adjust', 'note' => 'Admin adjustment' ) ) );
		$this->assertSame( 'Refunded', wb_listora_credit_note( array( 'reason' => 'refund', 'note' => 'Credits refunded' ) ) );
	}

	public function test_sdk_purchase_refund_and_default_notes_become_reason_lines() {
		$this->assertSame( 'Credits purchased', wb_listora_credit_note( array( 'reason' => 'purchase', 'note' => 'gateway:stripe:cs_test_ABC' ) ) );
		$this->assertSame( 'Credits purchased', wb_listora_credit_note( array( 'reason' => 'purchase', 'note' => 'Credits from WooCommerce order #1' ) ) );
		$this->assertSame( 'Refunded', wb_listora_credit_note( array( 'reason' => 'gateway_refund', 'note' => 'gateway:stripe:refund:cs_test_ABC' ) ) );
		$this->assertSame( 'Credits held', wb_listora_credit_note( array( 'reason' => 'hold', 'note' => 'Credits held' ) ) );
	}

	public function test_rows_from_before_reasons_were_stored_get_a_translated_line() {
		$filter = static function ( $translation, $text, $domain ) {
			return 'wb-listora' === $domain ? 'FR:' . $translation : $translation;
		};
		add_filter( 'gettext', $filter, 10, 3 );
		$this->assertSame( 'FR:Hold released', wb_listora_credit_note( array( 'reason' => '', 'note' => 'Hold released' ) ) );
		$this->assertSame( 'FR:Credits held', wb_listora_credit_note( array( 'reason' => '', 'note' => 'Credits held' ) ) );
		remove_filter( 'gettext', $filter, 10 );
	}

	public function test_listora_and_admin_notes_are_kept() {
		$this->assertSame( 'Plan: Basic Listing for a removed listing', wb_listora_credit_note( array( 'reason' => 'spend', 'note' => 'Plan: Basic Listing for listing #8572' ) ) );
		$this->assertSame( 'Referral bonus', wb_listora_credit_note( array( 'reason' => 'admin_adjust', 'note' => 'Referral bonus' ) ) );
	}

	public function test_rows_without_a_known_reason_keep_their_note() {
		$this->assertSame( 'Legacy row', wb_listora_credit_note( array( 'reason' => '', 'note' => 'Legacy row' ) ) );
		$this->assertSame( 'Something new', wb_listora_credit_note( array( 'reason' => 'future_reason', 'note' => 'Something new' ) ) );
	}

	public function test_lines_are_translated() {
		$filter = static function ( $translation, $text ) {
			return 'Hold released' === $text ? 'Retenue levée' : $translation;
		};
		add_filter( 'gettext', $filter, 10, 2 );
		$this->assertSame( 'Retenue levée', wb_listora_credit_note( array( 'reason' => 'hold_release', 'note' => 'Hold released on approval' ) ) );
		remove_filter( 'gettext', $filter, 10 );
	}

	public function test_listora_notes_name_the_listing_and_need_not_their_numbers() {
		$listing = self::factory()->post->create( array( 'post_type' => 'listora_listing', 'post_title' => 'Golden Fork' ) );
		$need    = self::factory()->post->create( array( 'post_type' => 'post', 'post_title' => 'Need a plumber' ) );

		$this->assertSame( 'Plan: Standard for listing "Golden Fork"', wb_listora_credit_note( array( 'note' => 'Plan: Standard for listing #' . $listing ) ) );
		$this->assertSame( 'Feature upgrade: listing "Golden Fork"', wb_listora_credit_note( array( 'note' => 'Feature upgrade — listing #' . $listing ) ) );
		$this->assertSame( 'Response to need "Need a plumber": listing "Golden Fork"', wb_listora_credit_note( array( 'note' => sprintf( 'Response to need #%d — listing #%d', $need, $listing ) ) ) );
		$this->assertSame( 'Plan charge returned - a removed listing', wb_listora_credit_note( array( 'note' => 'Plan charge returned - listing #99999999' ) ), 'A deleted listing reads as one, not as a number.' );
	}
}
