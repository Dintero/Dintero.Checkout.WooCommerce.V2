<?php
/**
 * Class for managing actions during the checkout process.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to manage actions during the checkout process for the embeded flow.
 */
class Dintero_Checkout_Embeded {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		// add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_dintero_checkout_session' ), 9999 );
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

		if ( ! is_ajax() ) {
			return;
		}

		if ( 'dintero_checkout' !== WC()->session->chosen_payment_method ) {
			return;
		}

		$session_id = WC()->session->get( 'dintero_checkout_session_id' );
		if ( empty( $session_id ) ) {
			return;
		}

		Dintero()->api->update_checkout_session( $session_id );
	}

} new Dintero_Checkout_Embeded();
