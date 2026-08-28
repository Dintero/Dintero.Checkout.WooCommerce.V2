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
			'dintero_checkout_recover_order'            => true,
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
		check_ajax_referer( 'dintero_checkout_wc_log_js', 'nonce' );
		$dintero_checkout_order_id = WC()->session->get( 'dintero_checkout_order_id' );

		// Get the content size of the request.
		$post_size = (int) $_SERVER['CONTENT_LENGTH'] ?? 0;

		// If the post data is too long, log an error message and return.
		if ( $post_size > 1024 ) {
			Dintero_Checkout_Logger::log( "Frontend JS $dintero_checkout_order_id: message too long and can't be logged." );
			wp_send_json_success(); // Return success to not stop anything in the frontend if this happens.
		}

		$posted_message = filter_input( INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$message        = "Frontend JS $dintero_checkout_order_id: $posted_message";
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

		if ( is_wp_error( $session ) ) {
			wp_send_json_error( $session->get_error_message() );
		}

		WC()->cart->calculate_totals();
		$total    = $session['order']['amount'];
		$wc_total = intval( WC()->cart->total * 100 );

		$max_diff = 10; // minor units.
		$diff     = $total - $wc_total;
		if ( $max_diff > abs( $diff ) ) {
			wp_send_json_success( $diff );
		}

		wp_send_json_error( $diff );
	}

	/**
	 * Recover a customer stuck on the checkout page after their session has already been paid.
	 *
	 * Triggered by the frontend when the SDK reports the session as not found. This can happen when the page is reloaded
	 * during payment (e.g. mobile Chrome discards the tab during the Klarna app switch), and the redirect from Dintero is
	 * never received. If the session resulted in a payable transaction, the customer is sent the same redirect URL that
	 * Dintero would have used, and the existing redirect flow takes over (refer to Dintero_Checkout_Redirect). Otherwise,
	 * an error is sent, and the frontend falls back to resetting the checkout.
	 *
	 * @return void
	 */
	public static function dintero_checkout_recover_order() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_recover_order' ) ) {
			wp_send_json_error( 'bad_nonce' );
		}

		// A session can still be read after it has been paid, and then includes the transaction id.
		$session_id = WC()->session->get( 'dintero_checkout_session_id' );
		$session    = empty( $session_id ) ? null : Dintero()->api->get_session( $session_id );
		if ( ! is_array( $session ) || empty( $session['transaction_id'] ) || empty( $session['order']['merchant_reference'] ) ) {
			wp_send_json_error( 'no_transaction' );
		}

		// The redirect flow can only confirm an order it can find. Without this check, a resolvable but order-less reference would bounce the customer between the redirect flow and the checkout page indefinitely (refer to Dintero_Checkout_Redirect::maybe_redirect).
		if ( empty( dintero_get_order_id_by_merchant_reference( $session['order']['merchant_reference'] ) ) ) {
			wp_send_json_error( 'no_order' );
		}

		$dintero_order = Dintero()->api->get_order( $session['transaction_id'] );
		if ( ! is_array( $dintero_order ) || ! in_array( $dintero_order['status'] ?? '', array( 'AUTHORIZED', 'CAPTURED', 'ON_HOLD' ), true ) ) {
			wp_send_json_error( 'not_payable' );
		}

		Dintero_Checkout_Logger::log( "[RECOVER]: The session $session_id is already paid (transaction ID: {$session['transaction_id']}). Redirecting customer to the confirmation page." );

		$redirect = add_query_arg(
			array(
				'gateway'            => 'dintero',
				'merchant_reference' => $session['order']['merchant_reference'],
				'transaction_id'     => $session['transaction_id'],
			),
			home_url()
		);
		wp_send_json_success( array( 'redirect' => $redirect ) );
	}
}
Dintero_Checkout_Ajax::init();
