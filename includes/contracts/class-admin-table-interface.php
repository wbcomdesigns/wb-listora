<?php
/**
 * Admin table contract.
 *
 * The shared list table every Listora custom-table screen uses (Reviews,
 * Claims, Needs, Moderators, Coupons, Badges, Transactions, the logs): views,
 * filters, member search, sortable columns, row actions and drawers, bulk
 * actions, pagination and stat cards, rendered from GET state.
 *
 * Resolve via:
 *   $table = wb_listora_service( 'admin_table' );
 *
 * Pro narrows the result with `instanceof Admin_Table_Interface` rather than
 * importing \WBListora\Admin\Admin_Table, which INV-3 forbids.
 *
 * @package WBListora\Contracts
 */

namespace WBListora\Contracts;

defined( 'ABSPATH' ) || exit;

/**
 * Admin table contract.
 *
 * @since 1.9.0
 */
interface Admin_Table_Interface {

	/**
	 * Read the list state (page, size, search, sort, filters) from the request.
	 *
	 * @param string   $id       Table id.
	 * @param string[] $filters  Extra query args to read.
	 * @param string[] $sortable Column keys that may be sorted on.
	 * @param string   $orderby  Default sort column.
	 * @param int      $per_page Page size until the user picks one.
	 * @return array{paged:int, per_page:int, offset:int, s:string, orderby:string, order:string, filters:array<string,string>}
	 */
	public function request( $id, array $filters = array(), array $sortable = array(), $orderby = '', $per_page = 0 );

	/**
	 * Member ids matching a name, username or email (a number is an id).
	 *
	 * @param string $search Typed text.
	 * @return int[] Ids; array( 0 ) when nothing matches.
	 */
	public function user_ids( $search );

	/**
	 * Render the table. See Admin_Table::render() for the arguments.
	 *
	 * @param array<string, mixed> $args Table arguments.
	 * @return void
	 */
	public function render( array $args ): void;

	/**
	 * Render a row of stat cards.
	 *
	 * @param array<int, array<string, mixed>> $cards    Cards: label, value, icon, variant, url, hint.
	 * @param string                           $modifier Extra grid class.
	 * @return void
	 */
	public function stat_cards( array $cards, $modifier = '' ): void;
}
