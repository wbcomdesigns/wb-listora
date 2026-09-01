<?php
/**
 * Every Listora check on WordPress's Site Health screen, registered in one place.
 *
 * Three features had grown a need to tell the site owner something they could
 * not see from the plugin's own screens — proof documents readable by the
 * public, maps with no tile source, a payment currency that disagrees with the
 * displayed one. Each had started registering its own `site_status_tests`
 * filter with the same eight lines of boilerplate around it.
 *
 * The checks themselves are not duplicates and belong with their features, so
 * the logic stays there. Only the wiring is collected here, which also gives
 * one list to read when asking "what does Listora check?".
 *
 * A check earns its place by answering a question the owner cannot answer from
 * the admin: something that looks fine in wp-admin and is broken, or misleading,
 * for a visitor.
 *
 * @package WBListora\Core
 * @since 1.7.0
 */

namespace WBListora\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Listora's Site Health checks.
 */
class Site_Health {

	/**
	 * Hook the registrar.
	 */
	public static function init() {
		add_filter( 'site_status_tests', array( __CLASS__, 'register' ) );
	}

	/**
	 * Every check Listora contributes.
	 *
	 * @return array<string, array<string, mixed>> Test id => label + callback.
	 */
	private static function checks(): array {
		$checks = array(
			'wb_listora_claim_proofs' => array(
				'label' => __( 'Claim proof documents are private', 'wb-listora' ),
				'test'  => array( Claim_Proofs::class, 'run_health_test' ),
			),
			'wb_listora_map_tiles'    => array(
				'label' => __( 'Maps have a tile source', 'wb-listora' ),
				'test'  => array( Map_Health::class, 'run' ),
			),
			'wb_listora_currency'     => array(
				'label' => __( 'One currency across the site', 'wb-listora' ),
				'test'  => array( __CLASS__, 'currency_check' ),
			),
		);

		/**
		 * Filter Listora's Site Health checks.
		 *
		 * @since 1.7.0
		 *
		 * @param array<string, array<string, mixed>> $checks Test id => label + callback.
		 */
		return (array) apply_filters( 'wb_listora_site_health_checks', $checks );
	}

	/**
	 * Add them to WordPress's list.
	 *
	 * @param array<string, mixed> $tests Registered tests.
	 * @return array<string, mixed>
	 */
	public static function register( $tests ) {
		foreach ( self::checks() as $id => $check ) {
			if ( is_callable( $check['test'] ) ) {
				$tests['direct'][ $id ] = $check;
			}
		}

		return $tests;
	}

	/**
	 * Warn when the store charges in a currency the site does not display.
	 *
	 * Listora has one currency: it is what prices show in and what members are
	 * charged in. Keeping a store on a different one is the site owner's to
	 * correct — but only if they find out, and nothing told them. The plugin
	 * will not paper over it by displaying two currencies, because that reads
	 * as a broken plugin rather than as the misconfiguration it is.
	 *
	 * @return array<string, mixed>
	 */
	public static function currency_check() {
		$site = function_exists( 'wb_listora_get_currency_format' )
			? (string) wb_listora_get_currency_format()['code']
			: 'USD';

		$result = array(
			'label'       => __( 'One currency across the site', 'wb-listora' ),
			'status'      => 'good',
			'badge'       => array(
				'label' => __( 'Listora', 'wb-listora' ),
				'color' => 'blue',
			),
			'description' => '<p>' . sprintf(
				/* translators: %s: currency code, e.g. USD. */
				esc_html__( 'Prices display in %s, and members are charged in it.', 'wb-listora' ),
				'<code>' . esc_html( $site ) . '</code>'
			) . '</p>',
			'actions'     => '',
			'test'        => 'wb_listora_currency',
		);

		$store = (string) get_option( 'woocommerce_currency', '' );

		if ( '' === $store || strtoupper( $store ) === strtoupper( $site ) ) {
			return $result;
		}

		$result['status']         = 'recommended';
		$result['badge']['color'] = 'orange';
		$result['label']          = __( 'WooCommerce charges in a different currency from the one your prices show', 'wb-listora' );
		$result['description']    = '<p>' . sprintf(
			/* translators: 1: Listora currency code, 2: WooCommerce currency code. */
			esc_html__( 'Listora shows prices in %1$s. WooCommerce is set to %2$s, so anything bought through WooCommerce is charged in %2$s — a member can see one currency and be billed in another.', 'wb-listora' ),
			'<code>' . esc_html( strtoupper( $site ) ) . '</code>',
			'<code>' . esc_html( strtoupper( $store ) ) . '</code>'
		) . '</p>'
			. '<p>' . esc_html__( 'Set both to the same currency. Credit packs bought through WooCommerce are priced in the WooCommerce currency, while packs bought directly use the Listora currency, so a member can see both on the Buy Credits screen at once.', 'wb-listora' ) . '</p>';

		$result['actions'] = sprintf(
			'<p><a href="%1$s">%2$s</a> &middot; <a href="%3$s">%4$s</a></p>',
			esc_url( admin_url( 'admin.php?page=listora-settings&tab=general' ) ),
			esc_html__( 'Listora currency', 'wb-listora' ),
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=general' ) ),
			esc_html__( 'WooCommerce currency', 'wb-listora' )
		);

		return $result;
	}
}
