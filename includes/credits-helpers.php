<?php
/**
 * The one way Listora (and Listora Pro) reads from the Wbcom Credits SDK.
 *
 * Spend goes through wb_listora_charge() / Core\Charge. Everything else a feature needs from
 * the SDK (balance, ledger page, currency, unit conversion, gateways, adapters, purchase URL)
 * goes through the functions below, so a rule about credits is written once: the slug, the
 * readiness guard, the unit and the failure behaviour. Callers never name an SDK class.
 *
 * Units: every amount these functions take or return is MAJOR units (a credit is one unit of
 * the store currency), except the raw ledger rows from wb_listora_credit_ledger_page(), whose
 * `amount` is the ledger's integer MINOR units; convert it with wb_listora_credit_to_major().
 *
 * @package WBListora
 * @since   2.1.2
 */

defined( 'ABSPATH' ) || exit;

/**
 * A member's credit balance.
 *
 * @since 2.1.2
 *
 * @param int $user_id User ID.
 * @return float MAJOR units; 0.0 when the SDK is not ready or the user is unknown.
 */
function wb_listora_credit_balance( int $user_id ): float {
	if ( $user_id <= 0 || ! wb_listora_credits_ready() ) {
		return 0.0;
	}
	return (float) \Wbcom\Credits\Credits::balance_money( WB_LISTORA_CREDITS_SLUG, $user_id );
}

/**
 * The currency a credit is worth one unit of.
 *
 * The SDK is the authority (it reads the `currency` setting for Listora), so every surface
 * agrees with what the ledger was written in.
 *
 * @since 2.1.2
 *
 * @return string Upper-case ISO code.
 */
function wb_listora_credit_currency(): string {
	if ( wb_listora_credits_ready() ) {
		return \Wbcom\Credits\Credits::resolve_money_currency( WB_LISTORA_CREDITS_SLUG );
	}
	return strtoupper( (string) wb_listora_get_setting( 'currency', 'USD' ) );
}

/**
 * Decimal places of the credit currency (2 for USD, 0 for KRW or JPY).
 *
 * @since 2.1.2
 *
 * @return int
 */
function wb_listora_credit_decimals(): int {
	return class_exists( '\Wbcom\Credits\Money' ) ? (int) \Wbcom\Credits\Money::decimals_for( wb_listora_credit_currency() ) : 2;
}

/**
 * Whether the ledger stores money (integer MINOR units) rather than plain credit counts.
 *
 * Listora registers in money mode, so this is true whenever the SDK is ready; it stays a
 * question the SDK answers so the conversions below are correct if that ever changes.
 *
 * @since 2.1.2
 *
 * @return bool
 */
function wb_listora_credit_is_money(): bool {
	return wb_listora_credits_ready() && \Wbcom\Credits\Credits::is_money( WB_LISTORA_CREDITS_SLUG );
}

/**
 * Ledger MINOR units to MAJOR credits, in the credit currency.
 *
 * @since 2.1.2
 *
 * @param int $minor Ledger amount in MINOR units (sign kept).
 * @return float MAJOR units.
 */
function wb_listora_credit_to_major( int $minor ): float {
	return wb_listora_credit_is_money() ? (float) \Wbcom\Credits\Money::to_major( $minor, wb_listora_credit_currency() ) : (float) $minor;
}

/**
 * MAJOR credits to ledger MINOR units, in the credit currency.
 *
 * @since 2.1.2
 *
 * @param float $major MAJOR units.
 * @return int MINOR units.
 */
function wb_listora_credit_to_minor( float $major ): int {
	return wb_listora_credit_is_money() ? (int) \Wbcom\Credits\Money::to_minor( $major, wb_listora_credit_currency() ) : (int) round( $major );
}

/**
 * How many ledger rows a member has.
 *
 * @since 2.1.2
 *
 * @param int $user_id User ID.
 * @return int
 */
function wb_listora_credit_ledger_count( int $user_id ): int {
	return $user_id > 0 && wb_listora_credits_ready() ? (int) \Wbcom\Credits\Credits::count_ledger( WB_LISTORA_CREDITS_SLUG, $user_id ) : 0;
}

/**
 * One page of a member's ledger, newest first.
 *
 * @since 2.1.2
 *
 * @param int $user_id  User ID.
 * @param int $page     1-based page.
 * @param int $per_page Rows per page.
 * @return array<int, array<string, mixed>> Raw ledger rows. `amount` is MINOR units.
 */
function wb_listora_credit_ledger_page( int $user_id, int $page = 1, int $per_page = 20 ): array {
	if ( $user_id <= 0 || ! wb_listora_credits_ready() ) {
		return array();
	}
	return (array) \Wbcom\Credits\Credits::get_ledger( WB_LISTORA_CREDITS_SLUG, $user_id, $per_page, max( 0, $page - 1 ) * $per_page );
}

/**
 * Ledger rows matching a filter (user_id, item_id, entry_type, reason, reference, hold_id,
 * since, until, limit, offset, order), newest first by default.
 *
 * @since 2.1.2
 *
 * @param array<string, mixed> $args Filters understood by the SDK's query_ledger().
 * @return array<int, array<string, mixed>> Rows; `amount` is MINOR units.
 */
function wb_listora_credit_ledger_rows( array $args ): array {
	if ( ! wb_listora_credits_ready() ) {
		return array();
	}
	return array_map( 'get_object_vars', \Wbcom\Credits\Credits::query_ledger( WB_LISTORA_CREDITS_SLUG, $args ) );
}

/**
 * One ledger row by id.
 *
 * @since 2.1.2
 *
 * @param int $id Ledger row id.
 * @return array<string, mixed>|null Row; `amount` is MINOR units.
 */
function wb_listora_credit_ledger_row( int $id ): ?array {
	$row = $id > 0 && wb_listora_credits_ready() ? \Wbcom\Credits\Credits::get_ledger_row( WB_LISTORA_CREDITS_SLUG, $id ) : null;
	return $row ? get_object_vars( $row ) : null;
}

/**
 * The ids of the payment gateways a member could buy credits through.
 *
 * Any failure while loading a gateway reads as "none", so a buy button is never offered on a
 * broken checkout.
 *
 * @since 2.1.2
 *
 * @return string[] Gateway ids (stripe, paypal, ...).
 */
function wb_listora_credit_gateways(): array {
	if ( ! class_exists( '\Wbcom\Credits\Gateways\Gateway_Registry' ) ) {
		return array();
	}

	try {
		// The SDK boots the registry itself on plugins_loaded; booting again would re-fire its register hook.
		return array_values( array_map( static fn ( $gateway ) => (string) $gateway->get_id(), (array) \Wbcom\Credits\Gateways\Gateway_Registry::for_slug( WB_LISTORA_CREDITS_SLUG )->get_available() ) );
	} catch ( \Throwable $e ) {
		return array();
	}
}

/**
 * The credit-source adapters (WooCommerce, MemberPress, PMPro, direct).
 *
 * Built fresh and not booted: boot() hooks each adapter's payment handlers, and doing it twice
 * would grant credits twice per order. The SDK boots its own copy.
 *
 * @since 2.1.2
 *
 * @return object|null The SDK's AdapterRegistry, or null when it has no adapters.
 */
function wb_listora_credit_adapters() {
	return class_exists( '\Wbcom\Credits\Adapters\AdapterRegistry' ) ? new \Wbcom\Credits\Adapters\AdapterRegistry( WB_LISTORA_CREDITS_SLUG, 'listora' ) : null;
}
