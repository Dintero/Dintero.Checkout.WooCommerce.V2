<?php
/**
 * Handle callbacks from Dintero.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling callbacks from Dintero.
 */
class Dintero_Checkout_Callback {

	/**
	 * Register callback action.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_dintero_callback', array( $this, 'callback' ) );
	}

	/**
	 * Handle the callback from Dintero.
	 *
	 * @return void
	 */
	public function callback() {
		$transaction_id = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_STRING ); /* The transaction_id is guaranteed unless 'error' is set. */
		$error          = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_STRING );

		/* If the 'order_key' does not exist, we cannot identify the WC order. */
		$order_key = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );
		if ( empty( $order_key ) ) {
			Dintero_Logger::log( sprintf( 'CALLBACK [order_key]: No order key was found (transaction ID: %s). Cannot identify the WC order. ', ( $transaction_id ) ? $transaction_id : 'Not available' ) );
			return;
		}

		$order_id = wc_get_order_id_by_order_key( $order_key );
		if ( empty( $order_id ) ) {
			Dintero_Logger::log( sprintf( 'CALLBACK [order_key]: Failed to retrieve the order id from the order key (transaction ID: %s).', ( $transaction_id ) ? $transaction_id : 'Not available' ) );
			return;
		}

		$order = wc_get_order( $order_id );

		// If the 'error' query parameter exist, the 'transaction_id' is not sent. We need to handle the error.
		if ( empty( $transaction_id ) && ! empty( $error ) ) {

			$show_on_order_page = true;
			switch ( $error ) {
				case 'authorization':
					$note = 'The customer failed to authorize the payment.';
					break;
				case 'failed':
					$note = 'The transaction was rejected by Dintero, or an error occurred during transaction processing.';
					break;
				case 'cancelled':
					$note = 'The customer canceled the checkout payment.';
					break;
				case 'captured':
					$note = 'The transaction capture operation failed during auto-capture.';
					break;
				default:
					$note               = 'Unknown event. ' . json_encode( filter_var_array( $_GET, FILTER_SANITIZE_STRING ) ) . '.';
					$show_on_order_page = false;
					break;
			}

			Dintero_Logger::log( sprintf( 'CALLBACK [%s]: %s WC order id: %s.', $error, $note, $order_id ) );
			if ( $show_on_order_page ) {
				$order->add_order_note( $note );
			}

			return;
		}

		// Check if the order exist in WooCommerce.
		if ( empty( $order ) ) {
			$event = filter_input( INPUT_GET, 'event', FILTER_SANITIZE_STRING );
			Dintero_Logger::log( sprintf( 'CALLBACK%s: WC ERROR: No order with the WC id %s (transaction id: %s) could be found.', ( empty( $event ) ) ? '' : " [$event]", $order_id, $transaction_id ) );

			return;
		}

		// If the 'event' query parameter exist, the order status was changed in the back office.
		$event = filter_input( INPUT_GET, 'event', FILTER_SANITIZE_STRING );
		if ( ! empty( $event ) ) {
			switch ( $event ) {

				/* Dintero does not trigger these events for for partial actions (e.g., partial refund). */
				case 'CAPTURE':
					Dintero_Logger::log( sprintf( 'CALLBACK [%s]: The status for the WC order id %s (transaction id: %s) was changed to CAPTURE in the back office.', $event, $order_id, $transaction_id ) );
					if ( ! Dintero()->order_management->is_captured( $order_id, true ) ) {
						$order->add_order_note( __( 'The order was CAPTURED in the Dintero backoffice.', 'dintero-checkout-for-woocommerce' ) );
					}
					break;

				case 'REFUND':
					Dintero_Logger::log( sprintf( 'CALLBACK [%s]: The status for the WC order id %s (transaction id: %s) was changed to REFUND (or PARTIALLY REFUNDED) in the back office.', $event, $order_id, $transaction_id ) );
					if ( ! Dintero()->order_management->is_refunded( $order_id, true ) ) {
						$order->add_order_note( __( 'The order was REFUNDED in the Dintero backoffice.', 'dintero-checkout-for-woocommerce' ) );
					}
					break;

				case 'VOID':
					Dintero_Logger::log( sprintf( 'CALLBACK [%s]: The status for the WC order id %s (transaction id: %s) was changed to VOID in the back office.', $event, $order_id, $transaction_id ) );
					if ( ! Dintero()->order_management->is_canceled( $order_id, false ) ) {
						$order->update_status( 'cancelled', __( 'The order was CANCELED in the Dintero backoffice.', 'dintero-checkout-for-woocommerce' ) );
					}
					break;

				default:
					Dintero_Logger::log( sprintf( 'CALLBACK [%s] ignored for WC order id: %s (transaction id: %s).', $event, $order_id, $transaction_id ) );
					break;
			}

			return;
		}

		// Check if the order is set to on-hold, awaiting authorization.
		$dintero_order = Dintero()->api->get_order( $transaction_id );
		$is_authorized = ( 'AUTHORIZED' === $dintero_order['result']['status'] );
		if ( $is_authorized && get_post_meta( $order_id, '_dintero_on_hold', true ) ) {
			$order->add_order_note( __( 'The order has been authorized by Dintero.', 'dintero-checkout-for-woocommerce' ) );
			$order->set_status( 'processing' );
			$order->save();

			delete_meta( $order_id, '_dintero_on_hold' );

			Dintero_Logger::log(
				sprintf( 'CALLBACK [%s]: The WC order ID: %s (transaction ID: %s) was authorized by Dintero. Changing status from "%s" to "processing".', $dintero_order['result']['status'], $order_id, $transaction_id, $order->get_status() )
			);
			return;
		}

		$is_failed = ( 'FAILED' === $dintero_order['result']['status'] );
		if ( $is_failed && get_post_meta( $order_id, '_dintero_on_hold', true ) ) {
			$order->add_order_note( __( 'The order was not approved by Dintero.', 'dintero-checkout-for-woocommerce' ) );
			$order->set_status( 'failed' );
			$order->save();

			delete_meta( $order_id, '_dintero_on_hold' );

			Dintero_Logger::log(
				sprintf( 'CALLBACK [%s]: The WC order ID: %s (transaction ID: %s) was not approved by Dintero. Changing status from "%s" to "failed".', $dintero_order['result']['status'], $order_id, $transaction_id, $order->get_status() )
			);
			return;
		}

		// At this point, the 'event' query parameter does not exist which means the order was completed through WooCommerce.
		if ( 'dintero_checkout' === $order->get_payment_method() && empty( $order->get_transaction_id() ) ) {
			Dintero_Logger::log( sprintf( 'CALLBACK [CREATE]: The customer might have closed the browser or never returned during payment processing. Order ID: %s (transaction ID: %s).', $order_id, $transaction_id ) );
			$order->set_transaction_id( $transaction_id );
			$order->payment_complete();

			// translators: the transaction ID.
			$order->add_order_note( sprintf( __( 'The customer has completed the payment, but did not return to the confirmation page. Transaction ID: %s.', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
		}
	}

	/**
	 * Retrieve the callback URL.
	 *
	 * @static
	 * @return string The callback URL (relative to the home URL).
	 */
	public static function callback_url( $order_key ) {
		// Events to listen to when they happen in the back office.
		$events = '&report_event=CAPTURE&report_event=REFUND&report_event=VOID';

		// FIXME: Set the delay_callback to 60 seconds.
		return add_query_arg(
			array(
				'delay_callback' => 30, /* seconds. */
				'report_error'   => true,
				'key'            => $order_key,
			),
			home_url( '/wc-api/dintero_callback/' )
		) . $events;
	}

	/**
	 * Determine whether Dintero is running on localhost.
	 *
	 * @return boolean TRUE if running on localhost, otherwise FALSE.
	 */
	public static function is_localhost() {
		return ( isset( $_SERVER['REMOTE_ADDR'] ) && in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ), true ) || isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === substr( wp_unslash( $_SERVER['HTTP_HOST'] ), 0, 9 ) );
	}

} new Dintero_Checkout_Callback();
