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
			'dintero_checkout_unset_session'            => true,
			'dintero_checkout_print_notice'             => true,
			'dintero_verify_order_total'                => true,
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
		}
		$posted_message            = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$dintero_checkout_order_id = WC()->session->get( 'dintero_checkout_order_id' );
		$message                   = "Frontend JS $dintero_checkout_order_id: $posted_message";
		Dintero_Checkout_Logger::log( $message );
		wp_send_json_success();
	}

	/**
	 * Unset the WooCommerce session.
	 *
	 * @return void
	 */
	public static function dintero_checkout_unset_session() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_unset_session' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		dintero_unset_sessions();
	}


	/**
	 * Prints checkout notices from Dintero.
	 *
	 * @return void
	 */
	public static function dintero_checkout_print_notice() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_print_notice' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		$notice_type = filter_input( INPUT_POST, 'notice_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		wc_add_notice( filter_input( INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), $notice_type );
	}

	/**
	 * Verify that the provided amount corresponds to the cart total.
	 *
	 * @return void
	 */
	public static function dintero_verify_order_total() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_verify_order_total' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		$id      = filter_input( INPUT_POST, 'id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$session = Dintero()->api->get_session( $id );

		$total    = $session['order']['amount'];
		$wc_total = intval( WC()->cart->total * 100 );

		if ( $total === $wc_total ) {
			wp_send_json_success( $total - $wc_total );
		}

		wp_send_json_error( -absint( $total - $wc_total ) );
	}
}
Dintero_Checkout_Ajax::init();
