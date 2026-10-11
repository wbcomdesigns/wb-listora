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

	public function test_listora_and_admin_notes_are_kept() {
		$this->assertSame( 'Plan: Basic Listing for listing #8572', wb_listora_credit_note( array( 'reason' => 'spend', 'note' => 'Plan: Basic Listing for listing #8572' ) ) );
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
}
