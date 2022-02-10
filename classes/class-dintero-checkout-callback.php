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
	 * The reference to the *Singleton* instance of this class.
	 *
	 * @var $instance Dintero_Checkout_Callback.
	 */
	private static $instance;

	/**
	 * Return the *Singleton* instance of this class.
	 *
	 * @return Dintero_Checkout_Callback The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

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
		$transaction_id = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_STRING );
		$error          = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_STRING );

		// The merchant reference is the WC order id. It should always exist in the callback.
		$merchant_reference = filter_input( INPUT_GET, 'merchant_reference', FILTER_SANITIZE_STRING );
		if ( empty( $merchant_reference ) ) {
			return;
		}

		// If the 'error' query parameter exist, the 'transaction_id' is not sent. We need to handle the error.
		$order = wc_get_order( $merchant_reference );
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
					$note               = 'Unknown event.';
					$show_on_order_page = false;
					break;
			}

			Dintero_Logger::log( sprintf( 'CALLBACK [%s]: %s WC order id: %d: %s', $note, $error, $merchant_reference ) );
			if ( $show_on_order_page ) {
				$order->add_order_note( $note );
			}

			return;
		}

		// Check if the order exist in WooCommerce.
		if ( empty( $order ) ) {
			$event = filter_input( INPUT_GET, 'event', FILTER_SANITIZE_STRING );
			Dintero_Logger::log( sprintf( 'CALLBACK%s: WC ERROR: No order with the id %d (transaction id: %s) could be found.', ( empty( $event ) ) ? '' : " [$event]", $merchant_reference, $transaction_id ) );

			return;
		}

		// If the 'event' query parameter exist, the order status was changed in the back office.
		$event = filter_input( INPUT_GET, 'event', FILTER_SANITIZE_STRING );
		if ( ! empty( $event ) ) {
			switch ( $event ) {

				/* Dintero does not trigger these events for for partial actions (e.g., partial refund). */
				case 'CAPTURE':
					Dintero_Logger::log( sprintf( 'CALLBACK [%s]: The status for the order id %d (transaction id: %s) was changed to CAPTURE in the back office.', $event, $merchant_reference, $transaction_id ) );
					if ( ! Dintero()->order_management->is_captured( $merchant_reference, true ) ) {
						$order->add_order_note( __( 'The order was CAPTURED in the Dintero backoffice.', 'dintero-checkout-for-woocommerce' ) );
					}
					break;

				case 'REFUND':
					Dintero_Logger::log( sprintf( 'CALLBACK [%s]: The status for the order id %d (transaction id: %s) was changed to REFUND (or PARTIALLY REFUNDED) in the back office.', $event, $merchant_reference, $transaction_id ) );
					if ( ! Dintero()->order_management->is_refunded( $merchant_reference, true ) ) {
						$order->add_order_note( __( 'The order was REFUNDED in the Dintero backoffice.', 'dintero-checkout-for-woocommerce' ) );
					}
					break;

				case 'VOID':
					Dintero_Logger::log( sprintf( 'CALLBACK [%s]: The status for the order id %d (transaction id: %s) was changed to VOID in the back office.', $event, $merchant_reference, $transaction_id ) );
					if ( ! Dintero()->order_management->is_canceled( $merchant_reference, false ) ) {
						$order->update_status( 'cancelled', __( 'The order was CANCELED in the Dintero backoffice.', 'dintero-checkout-for-woocommerce' ) );
					}
					break;

				default:
					Dintero_Logger::log( sprintf( 'CALLBACK [%s] ignored (order id: %d | transaction id: %s)', $event, $merchant_reference, $transaction_id ) );
					break;
			}

			return;
		}

		// At this point, the 'event' query parameter does not exist which means the order was completed through WooCommerce.
		if ( 'dintero_checkout' === $order->get_payment_method() && empty( $order->get_transaction_id() ) ) {
			Dintero_Logger::log( sprintf( 'CALLBACK [CREATE]: The customer might have closed the browser or never returned during payment processing. Order ID: %d (transaction ID: %s)', $merchant_reference, $transaction_id ) );
			$order->set_transaction_id( $transaction_id );
			$order->payment_complete();

			// translators: the transaction ID.
			$order->add_order_note( sprintf( __( 'The customer has completed the payment, but did not return to the confirmation page. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
		}
	}

	/**
	 * Retrieve the callback URL.
	 *
	 * @return string The callback URL (relative to the home URL).
	 */
	public static function callback_url() {
		// Events to listen to when they happen in the back office.
		$events = '&report_event=CAPTURE&report_event=REFUND&report_event=VOID';

		return add_query_arg(
			array(
				'delay_callback' => 5, /* seconds. */
				'report_error'   => true,
				'includes'       => 'session',
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

}

// Instantiate this class.
Dintero_Checkout_Callback::get_instance();
