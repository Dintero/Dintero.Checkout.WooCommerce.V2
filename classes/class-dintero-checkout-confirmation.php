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
		$gateway            = filter_input( INPUT_GET, 'gateway', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$transaction_id     = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$merchant_reference = filter_input( INPUT_GET, 'merchant_reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$error              = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( 'dintero' !== $gateway || empty( $merchant_reference ) ) {
			return;
		}

		/* The transaction_id is only guaranteed when the payment is complete and authorized. That is, not on cancel. */
		if ( empty( $transaction_id ) ) {
			Dintero_Checkout_Logger::log( 'REDIRECT ERROR [transaction_id]: The transaction ID is missing. Redirecting customer back to checkout page.' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Get the order from the merchant reference.
		$order = $this->get_order_from_reference( $merchant_reference );
		if ( empty( $order ) ) {
			wc_add_notice( __( 'Something went wrong with completing the order. Please try again or contact the store', 'dintero-checkout-for-woocommerce' ) );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		$error = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( ! empty( $error ) ) {
			$this->handle_error( $error, $order );
		}

		$this->handle_success( $transaction_id, $order );
	}

	/**
	 * Handles a successful redirect from Dintero.
	 *
	 * @param string   $transaction_id The Transaction ID from Dintero.
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function handle_success( $transaction_id, $order ) {
		Dintero_Checkout_Logger::log( "REDIRECT [success]: The WC order id: {$order->get_id()} (transaction ID: $transaction_id) was placed successfully. Redirecting customer to thank-you page." );

		dintero_confirm_order( $order, $transaction_id );
		dintero_unset_sessions();
		wp_safe_redirect( $order->get_checkout_order_received_url() );

		exit;
	}

	/**
	 * Handles a error from the redirect by Dintero.
	 *
	 * @param string   $error The error from Dintero.
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function handle_error( $error, $order ) {
		$order_id         = $order->get_id();
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

		Dintero_Checkout_Logger::log( "REDIRECT ERROR [$error]: $note WC order id: $order_id" );
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Get the order from the reference.
	 *
	 * @param string $merchant_reference The merchant reference from Dintero.
	 * @return WC_Order|null On error, null is returned. Otherwise, WC_Order.
	 */
	public function get_order_from_reference( $merchant_reference ) {
		$order_id = $this->get_order_id_from_reference( $merchant_reference );

		// Check that we get a order id.
		if ( empty( $order_id ) ) {
			Dintero_Checkout_Logger::log( "REDIRECT ERROR [order_id]: Could not get an order_id from the merchant reference $merchant_reference" );
			return null;
		}

		$order = wc_get_order( $order_id );

		// Check if we get a valid order.
		if ( empty( $order ) ) {
			Dintero_Checkout_Logger::log( "REDIRECT ERROR [order]: Could not get an order from the merchant reference $merchant_reference" );
			return null;
		}

		return $order;
	}

	/**
	 * Get a order id from the merchant reference.
	 *
	 * @param string $merchant_reference The merchant reference from dintero.
	 * @return int
	 */
	public function get_order_id_from_reference( $merchant_reference ) {
		$key    = '_dintero_merchant_reference';
		$orders = wc_get_orders(
			array(
				'meta_key'   => $key,
				'meta_value' => $merchant_reference,
				'limit'      => 1,
				'orderby'    => 'date',
				'order'      => 'DESC',
			)
		);

		$order = reset( $orders );
		if ( $merchant_reference !== $order->get_meta( $key ) ) {
			return 0;
		}

		return $order->get_id() ?? 0;
	}
}
new Dintero_Checkout_Redirect();
