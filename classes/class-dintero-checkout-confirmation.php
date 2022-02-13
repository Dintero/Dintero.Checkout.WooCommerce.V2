<?php //phpcs:ignore
/**
 * Class for handling redirection during payment.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling redirection during payment.
 */
class Dintero_Checkout_Redirect {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'maybe_redirect' ), 9999 );
	}

	/**
	 * Redirects the customer to the appropriate page, but only if Dintero redirected the customer to the home page.
	 *
	 * @return void
	 */
	public function maybe_redirect() {
		// If the customer was not redirected by Dintero, exit.
		$gateway = filter_input( INPUT_GET, 'gateway', FILTER_SANITIZE_STRING );
		if ( 'dintero' !== $gateway ) {
			return;
		}

		// The 'merchant_reference' is guaranteed to always be available.
		$merchant_reference = filter_input( INPUT_GET, 'merchant_reference', FILTER_SANITIZE_STRING );

		// The WC_Order is used for generating the redirect URL to the thank-you page. If it doesn't exist, the method calls will result in a fatal error.
		$order_key = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
		if ( empty( $order_key ) ) {
			Dintero_Logger::log( sprintf( 'RETURN ERROR [order_key]: No order key was found for %s. Cannot identify the WC order. Redirecting customer back to checkout page. ', $merchant_reference ) );

			wc_add_notice( __( 'Something went wrong (order_key).', 'dintero-checkout-for-woocommerce' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );
		if ( empty( $order_id ) ) {
			Dintero_Logger::log( 'RETURN ERROR [order_key]: Failed to retrieve the order id from the order key. Redirecting customer back to checkout page.' );

			wc_add_notice( __( 'Something went wrong (order_key failed).', 'dintero-checkout-for-woocommerce' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		$order = wc_get_order( $order_id );

		// If the 'error' parameter is set, something went wrong or require further authorization. No 'transaction_id' is provided on error.
		$error = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_STRING );
		if ( ! empty( $error ) ) {

			$show_in_checkout = false;
			switch ( $error ) {
				case 'authorization':
					$note = __( 'The customer failed to authorize the payment.', 'dintero-checkout-for-woocommerce' );
					break;
				case 'failed':
					$note             = __( 'The transaction was rejected by Dintero, or an error occurred during transaction processing.', 'dintero-checkout-for-woocommerce' );
					$show_in_checkout = true;
					break;
				case 'cancelled':
					$note = __( 'The customer canceled the checkout payment.', 'dintero-checkout-for-woocommerce' );
					break;
				case 'captured':
					$note = __( 'The transaction capture operation failed during auto-capture.', 'dintero-checkout-for-woocommerce' );
					break;
				default:
					$note = 'Unknown event. ' . json_encode( filter_var_array( $_GET, FILTER_SANITIZE_STRING ) ) . '.';
					break;
			}
			if ( $note ) {
				$order->add_order_note( $note );
			}
			if ( $show_in_checkout ) {
				wc_add_notice( $note, 'error' );
			}

			Dintero_Logger::log( sprintf( 'RETURN ERROR [%s]: %s WC order id: %s / %s.', $error, $note, $order_id, $merchant_reference ) );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		// The 'transaction_id' is only set if the transaction was completed successfully.
		$transaction_id = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_STRING );
		if ( empty( $transaction_id ) ) {
			Dintero_Logger::log(
				sprintf( 'RETURN ERROR [transaction_id]: The transaction ID is missing for WC order %s / %s. Redirecting customer back to checkout page.', $order_id, $merchant_reference )
			);

			wc_add_notice( __( 'Something went wrong (transaction id).', 'dintero-checkout-for-woocommerce' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		// At this point, the gateway is Dintero, and the transaction has succeeded.
		$dintero_order         = Dintero()->api->get_order( $transaction_id );
		$require_authorization = ( ! $dintero_order['is_error'] && 'ON_HOLD' === $dintero_order['result']['status'] );

		if ( $require_authorization ) {
			$order->add_order_note( sprintf( __( 'The order was placed successfully, but requires further authorization by Dintero. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
			$order->set_status( 'on-hold' );
			$order->save();

			update_post_meta( $order_id, '_dintero_on_hold', $transaction_id );

			Dintero_Logger::log( sprintf( 'RETURN [%s]: The WC order %s / %s (transaction ID: %s) will require further authorization from Dintero.', $dintero_order['result']['status'], $order_id, $merchant_reference, $transaction_id ) );
		} else {

			// translators: %s the Dintero transaction ID.
			$order->add_order_note( sprintf( __( 'Payment via Dintero Checkout. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
			$order->set_status( 'processing' );
			$order->save();
		}

		update_post_meta( $order_id, '_dintero_transaction_id', $transaction_id );
		update_post_meta( $order_id, '_transaction_id', $transaction_id );

		wp_redirect(
			add_query_arg(
				array(
					'merchant_reference' => $merchant_reference,
					'transaction_id'     => $transaction_id,
				),
				$order->get_checkout_order_received_url()
			),
		);

		Dintero_Logger::log( sprintf( 'RETURN [success]: The WC order %s / %s (transaction ID: %s) was placed succesfully. Redirecting customer to thank-you page.', $order_id, $merchant_reference, $transaction_id ) );
		exit;
	}


} new Dintero_Checkout_Redirect();
