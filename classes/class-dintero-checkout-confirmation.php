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

		$the_GET = json_encode( filter_var_array( $_GET, FILTER_SANITIZE_STRING ) );

		// The 'merchant_reference' is guaranteed to always be available.
		$merchant_reference = filter_input( INPUT_GET, 'merchant_reference', FILTER_SANITIZE_STRING );

		if ( empty( $merchant_reference ) ) {
			Dintero_Checkout_Logger::log( sprintf( 'REDIRECT ERROR [merchant_reference]: No order key was found for %s. Cannot identify the WC order. Redirecting customer back to checkout page: %s ', $merchant_reference, $the_GET ) );

			wc_add_notice( __( 'Something went wrong (merchant_reference).', 'dintero-checkout-for-woocommerce' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		$order_id = $this->get_order_id_from_reference( $merchant_reference );
		$error    = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_STRING );

		if ( empty( $order_id ) ) {
			Dintero_Checkout_Logger::log(
				sprintf(
					'REDIRECT ERROR [order_id]: Failed to retrieve the order id from the order key%s. Redirecting customer back to checkout page: %s',
					( ! empty( $error ) ? ' due to: ' . $error : '' ),
					$the_GET,
				)
			);

			if ( empty( $error ) ) {
				wc_add_notice( __( 'Something went wrong (order_id failed).', 'dintero-checkout-for-woocommerce' ), 'error' );
			}

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
					$note = 'Unknown event.';
					break;
			}
			if ( $note ) {
				$order->add_order_note( $note );
			}
			if ( $show_in_checkout ) {
				wc_add_notice( $note, 'error' );
			}

			Dintero_Checkout_Logger::log( sprintf( 'REDIRECT ERROR [%s]: %s WC order id: %s / %s: %s', $error, $note, $order_id, $merchant_reference, $the_GET ) );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		// The 'transaction_id' is only set if the transaction was completed successfully.
		$transaction_id = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_STRING );
		if ( empty( $transaction_id ) ) {
			Dintero_Checkout_Logger::log(
				sprintf( 'REDIRECT ERROR [transaction_id]: The transaction ID is missing for WC order %s / %s. Redirecting customer back to checkout page:', $order_id, $merchant_reference, $the_GET )
			);

			wc_add_notice( __( 'Something went wrong (transaction id).', 'dintero-checkout-for-woocommerce' ), 'error' );
			wp_redirect( wc_get_checkout_url() );
			exit;
		}

		// At this point, the gateway is Dintero, and the transaction has succeeded.
		$dintero_order = Dintero()->api->get_order( $transaction_id );

		if ( is_wp_error( $dintero_order ) ) {
			return;
		}

		$require_authorization = ( ! is_wp_error( $dintero_order ) && 'ON_HOLD' === $dintero_order['status'] );

		update_post_meta( $order_id, '_dintero_transaction_id', $transaction_id );
		update_post_meta( $order_id, '_transaction_id', $transaction_id );

		if ( $require_authorization ) {
			// translators: %s the Dintero transaction ID.
			$order->add_order_note( sprintf( __( 'The order was placed successfully, but requires further authorization by Dintero. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
			$order->set_status( 'manual-review' );
			$order->save();

			update_post_meta( $order_id, Dintero()->order_management->status( 'on_hold' ), $transaction_id );

			Dintero_Checkout_Logger::log( sprintf( 'REDIRECT [%s]: The WC order %s / %s (transaction ID: %s) will require further authorization from Dintero.', $dintero_order['status'], $order_id, $merchant_reference, $transaction_id ) );
		} else {

			// translators: %s the Dintero transaction ID.
			$order->add_order_note( sprintf( __( 'Payment via Dintero Checkout. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
			$order->payment_complete();
		}

		// Update the transaction with the order number.
		Dintero()->api->update_transaction( $transaction_id, $order->get_order_number() );

		dintero_unset_sessions();

		wp_redirect(
			add_query_arg(
				array(
					'merchant_reference' => $merchant_reference,
					'transaction_id'     => $transaction_id,
				),
				$order->get_checkout_order_received_url()
			),
		);

		Dintero_Checkout_Logger::log( sprintf( 'REDIRECT [success]: The WC order %s / %s (transaction ID: %s) was placed succesfully. Redirecting customer to thank-you page.', $order_id, $merchant_reference, $transaction_id ) );
		exit;
	}

	/**
	 * Get a order id from the merchant reference.
	 *
	 * @param string $merchant_reference The merchant reference from dintero.
	 * @return int
	 */
	public function get_order_id_from_reference( $merchant_reference ) {
		$query_args = array(
			'fields'      => 'ids',
			'post_type'   => wc_get_order_types(),
			'post_status' => array_keys( wc_get_order_statuses() ),
			'meta_key'    => '_dintero_merchant_reference', // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
			'meta_value'  => $merchant_reference, // phpcs:ignore WordPress.DB.SlowDBQuery -- Slow DB Query is ok here, we need to limit to our meta key.
		);

		$order_ids = get_posts( $query_args );

		if ( empty( $order_ids ) ) {
			return null;
		}

		return $order_ids[0];
	}
} new Dintero_Checkout_Redirect();
