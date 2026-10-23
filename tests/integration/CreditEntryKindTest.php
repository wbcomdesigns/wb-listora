<?php
/**
 * The Type a member reads on a credit-history row comes from what happened,
 * not only from the row's ledger type.
 *
 * @package WBListora\Tests
 */

/**
 * @group listora
 * @group credits
 */
class CreditEntryKindTest extends WP_UnitTestCase {

	/**
	 * @return array<string, array{0: array<string,mixed>, 1: string}>
	 */
	public function rows(): array {
		return array(
			'gateway refund (stored as deduction)' => array( array( 'entry_type' => 'deduction', 'reason' => 'gateway_refund', 'amount' => -500 ), 'refund' ),
			'administrator removal'                => array( array( 'entry_type' => 'deduction', 'reason' => 'admin_adjust', 'amount' => -500 ), 'adjustment' ),
			'administrator addition'               => array( array( 'entry_type' => 'topup', 'reason' => 'admin_adjust', 'amount' => 500 ), 'adjustment' ),
			'hold release (stored as refund)'      => array( array( 'entry_type' => 'refund', 'reason' => 'hold_release', 'hold_id' => 4 ), 'hold_release' ),
			'spend'                                => array( array( 'entry_type' => 'deduction', 'reason' => 'spend', 'item_id' => 9 ), 'deduction' ),
			'pack bought'                          => array( array( 'entry_type' => 'topup', 'reason' => 'purchase' ), 'purchase' ),
			'credits expired'                      => array( array( 'entry_type' => 'expiry', 'reason' => 'expiry' ), 'expiry' ),
			'old spend on a listing'               => array( array( 'entry_type' => 'deduction', 'reason' => '', 'item_id' => 9, 'note' => 'Plan: Standard for listing #9' ), 'deduction' ),
			'old removal with its own note'        => array( array( 'entry_type' => 'deduction', 'reason' => '', 'item_id' => 0, 'note' => 'Correction' ), 'adjustment' ),
			'old fixed release note'               => array( array( 'entry_type' => 'refund', 'reason' => '', 'note' => 'Hold released on approval' ), 'hold_release' ),
			'old refund'                           => array( array( 'entry_type' => 'refund', 'reason' => '', 'note' => 'Listing Submission - credits refunded' ), 'refund' ),
		);
	}

	/**
	 * @dataProvider rows
	 *
	 * @param array<string,mixed> $row  Ledger row.
	 * @param string              $kind Expected kind.
	 */
	public function test_kind( array $row, string $kind ) {
		$this->assertSame( $kind, wb_listora_credit_entry_kind( $row ) );
		$this->assertArrayHasKey( $kind, wb_listora_credit_entry_types(), 'Every kind has a label.' );
	}

	public function test_a_gateway_refund_never_reads_as_spent() {
		$types = wb_listora_credit_entry_types();
		$label = $types[ wb_listora_credit_entry_kind( array( 'entry_type' => 'deduction', 'reason' => 'gateway_refund' ) ) ]['label'];
		$this->assertNotSame( $types['deduction']['label'], $label );
	}
}
