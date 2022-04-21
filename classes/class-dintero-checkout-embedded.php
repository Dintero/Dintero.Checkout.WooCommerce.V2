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
 * Class to manage actions during the checkout process for the embedded flow.
 */
class Dintero_Checkout_Embedded {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( 'embedded' === $settings['form_factor'] ) {
			add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_dintero_checkout_session' ), 9999 );
		}
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

		// We can only do this during AJAX, so if it is not an ajax call, we should just bail.
		if ( ! wp_doing_ajax() ) {
			return;
		}

		// Only when its an actual AJAX request to update the order review (this is when update_checkout is triggered).
		$ajax = filter_input( INPUT_GET, 'wc-ajax', FILTER_SANITIZE_STRING );
		if ( 'update_order_review' !== $ajax ) {
			return;
		}

		if ( 'dintero_checkout' !== WC()->session->chosen_payment_method ) {
			return;
		}

		// Dintero is not available for free orders.
		if ( ! WC()->cart->needs_payment() ) {
			WC()->session->reload_checkout = true;
		}

		// Check if we have locked the iframe first, if not then this should not happen since it will return an error.
		$raw_post_data = filter_input( INPUT_POST, 'post_data', FILTER_SANITIZE_STRING );
		parse_str( $raw_post_data, $post_data );
		if ( ! isset( $post_data['dintero_locked'] ) ) {
			return;
		}

		// Only if we have a current session active.
		$session_id = WC()->session->get( 'dintero_checkout_session_id' );
		if ( empty( $session_id ) ) {
			return;
		}

		Dintero()->api->update_checkout_session( $session_id );
	}

} new Dintero_Checkout_Embedded();
