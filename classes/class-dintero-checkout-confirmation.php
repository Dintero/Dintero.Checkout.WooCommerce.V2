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

		// The WC order is used for generating the redirect URL. If it doesn't exist, the method calls will result in a fatal error.
		$order_id = filter_input( INPUT_GET, 'merchant_reference', FILTER_SANITIZE_STRING );
		if ( empty( $order_id ) ) {
			return;
		}

		// If the 'error' parameter is set, something went wrong.
		$error = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_STRING );
		if ( ! empty( $error ) ) {
			$order = wc_get_order( $order_id );

			switch ( $error ) {
				case 'authorization':
					$note = __( 'Customer failed to authorize the payment.', 'dintero-checkout-for-woocommerce' );
					break;
				case 'failed':
					$note = __( 'The transaction was rejected by Dintero, or an error occurred during transaction processing.', 'dintero-checkout-for-woocommerce' );
					break;
				case 'canceled':
					$note = __( 'Customer canceled the checkout payment.', 'dintero-checkout-for-woocommerce' );
					break;
			}
			$order->add_order_note( $note );
			wc_add_notice( $note, 'error' );

			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		// The 'transaction_id' is only set if the transaction was completed successfully.
		$transaction_id = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_STRING );
		if ( empty( $transaction_id ) ) {
			return;
		}

		// At this point, the gateway is Dintero, and the transaction has succeeded.
		$order = wc_get_order( $order_id );

		update_post_meta( $order_id, '_dintero_transaction_id', $transaction_id );
		update_post_meta( $order_id, '_transaction_id', $transaction_id );

		// translators: %s The Dintero transaction ID.
		$order->add_order_note( sprintf( __( 'Payment via Dintero Checkout. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
		$order->set_status( 'processing' );
		$order->save();

		wp_redirect(
			add_query_arg(
				array(
					'merchant_reference' => $order_id,
					'transaction_id'     => $transaction_id,
				),
				$order->get_checkout_order_received_url()
			),
		);

		exit;
	}


} new Dintero_Checkout_Redirect();
