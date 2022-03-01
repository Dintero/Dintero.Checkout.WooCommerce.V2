<?php
/**
 * Ajax class file.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ajax class.
 */
class Dintero_Checkout_Ajax extends WC_AJAX {
	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'dintero_checkout_wc_change_payment_method' => true,
			'dintero_checkout_wc_log_js'                => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Maybe change payment method.
	 */
	public static function dintero_checkout_wc_change_payment_method() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_wc_change_payment_method' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$available_gateways         = WC()->payment_gateways()->get_available_payment_gateways();
		$switch_to_dintero_checkout = isset( $_POST['dintero_checkout'] ) ? sanitize_text_field( wp_unslash( $_POST['dintero_checkout'] ) ) : '';
		if ( 'false' === $switch_to_dintero_checkout ) {
			// Set chosen payment method to first gateway that is not Dintero Checkout for WooCommerce.
			$first_gateway = array_key_first( $available_gateways );
			if ( 'dintero_checkout' !== $first_gateway ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'dintero_checkout' );
		}

		WC()->payment_gateways()->set_current_gateway( $available_gateways );

		$redirect = wc_get_checkout_url();
		$data     = array(
			'redirect' => $redirect,
		);

		wp_send_json_success( $data );
		wp_die();
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function dintero_checkout_wc_log_js() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_wc_log_js' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$posted_message            = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$dintero_checkout_order_id = WC()->session->get( 'dintero_checkout_order_id' );
		$message                   = "Frontend JS $dintero_checkout_order_id: $posted_message";
		Dintero_Checkout_Logger::log( $message );
		wp_send_json_success();
		wp_die();
	}

}
Dintero_Checkout_Ajax::init();
