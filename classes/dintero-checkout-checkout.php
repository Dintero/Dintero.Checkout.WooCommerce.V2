<?php
/**
 * Class for managing actions during the checkout process.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dintero_Checkout_Checkout {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_dintero_checkout_session' ), 9999 );
	}

	/**
	 * Update the Dintero checkout session after calculation from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_dintero_checkout_session() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( 'dintero_checkout' !== WC()->session->chosen_payment_method ) {
			return;
		}

		$dintero_order_id = WC()->session->get( 'dintero_checkout_order_id' );
		if ( empty( $dintero_order_id ) ) {
			return;
		}

		// Check if the cart has has been changed since last update.
		$cart_hash  = WC()->cart->get_cart_hash();
		$saved_hash = WC()->session->get( 'dintero_checkout_last_update_hash' );

		// If they're the same, the checkout has not been modified.
		if ( $cart_hash === $saved_hash ) {
			return;
		}

		$dintero_order = Dintero()->api->get_order( $dintero_order_id );
		if ( 'INITIATED' === $dintero_order['result']['status'] ) {
			$session_id = WC()->session->get( 'dintero_checkout_session_id' );
			if ( $session_id ) {
				Dintero()->api->update_checkout_session( $session_id );
			}
		}
		WC()->session->set( 'dintero_checkout_last_update_hash', $cart_hash );
	}

} new Dintero_Checkout_Checkout();
