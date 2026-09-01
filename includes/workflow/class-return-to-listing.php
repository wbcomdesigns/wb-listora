<?php
/**
 * Carry "back to your listing" across a checkout.
 *
 * A member who leaves the submission wizard to buy credits arrives at the
 * credits screen with a `listora_return` URL, and that screen offers them the
 * way back. The moment they actually pay, the URL is gone: WooCommerce redirects
 * to Order Received with its own query string, and an off-site gateway sends
 * them somewhere else entirely before doing the same.
 *
 * So the member pays, lands on a "thank you" page, and the only listing link in
 * sight starts a brand new one. Their draft is safe but they have no way to it —
 * which is the friction the draft handoff exists to remove, reappearing one step
 * later. It was invisible until someone ran a real checkout rather than
 * simulating the redirect.
 *
 * The URL is stashed in the WooCommerce session when it is first seen, copied
 * onto the order at checkout, and read back on Order Received. The order copy is
 * what makes this survive an off-site gateway: the session can be gone by the
 * time the buyer returns, the order cannot.
 *
 * @package WBListora
 * @since 1.7.0
 */

namespace WBListora\Workflow;

defined( 'ABSPATH' ) || exit;

/**
 * Preserves the submission return URL across a WooCommerce checkout.
 */
class Return_To_Listing {

	/**
	 * Session key and order meta key.
	 *
	 * Underscore-prefixed meta so it stays out of the order's custom-fields UI.
	 */
	const KEY = '_listora_return_url';

	/**
	 * Register hooks. No-op when WooCommerce is not active.
	 */
	public static function init(): void {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}

		add_action( 'wp', array( __CLASS__, 'remember' ) );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'attach_to_order' ), 10, 1 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'render_notice' ), 5 );
	}

	/**
	 * Stash the return URL as soon as a page carries one.
	 *
	 * Runs on every front-end view rather than only on the credits screen: the
	 * member may pass through the cart, and WooCommerce's own "add to cart" links
	 * carry the parameter along with them.
	 */
	public static function remember(): void {
		if ( is_admin() || ! function_exists( 'wb_listora_get_submission_return_url' ) ) {
			return;
		}

		$url = wb_listora_get_submission_return_url();
		if ( '' === $url ) {
			return;
		}

		$session = WC()->session;
		if ( $session ) {
			$session->set( self::KEY, $url );
		}
	}

	/**
	 * Copy the stashed URL onto the order being created.
	 *
	 * @param \WC_Order $order Order under construction.
	 */
	public static function attach_to_order( $order ): void {
		$session = WC()->session;
		if ( ! $session || ! $order ) {
			return;
		}

		$url = (string) $session->get( self::KEY, '' );
		if ( '' === $url ) {
			return;
		}

		$order->update_meta_data( self::KEY, $url );
	}

	/**
	 * Show the way back on the Order Received page.
	 *
	 * @param int $order_id Completed order.
	 */
	public static function render_notice( $order_id ): void {
		$order = wc_get_order( (int) $order_id );
		if ( ! $order ) {
			return;
		}

		$url = (string) $order->get_meta( self::KEY );

		// Re-validate on the way out. The value was checked when it was stored,
		// but an order's meta is editable by anyone who can edit orders, and
		// this renders as a link the buyer is invited to click.
		$url = (string) wp_validate_redirect( $url, '' );
		if ( '' === $url ) {
			return;
		}

		// One-shot: the buyer has been shown the way back, so a later visit to
		// this receipt should not keep offering to resume something they have
		// most likely already finished.
		$session = WC()->session;
		if ( $session ) {
			$session->set( self::KEY, '' );
		}

		printf(
			'<div class="listora-return-notice" role="status"><strong>%1$s</strong> %2$s <a class="listora-return-notice__link" href="%3$s">%4$s</a></div>',
			esc_html__( 'Your credits are ready.', 'wb-listora' ),
			esc_html__( 'Your listing is saved and waiting for you.', 'wb-listora' ),
			esc_url( $url ),
			esc_html__( 'Back to your listing', 'wb-listora' )
		);
	}
}
