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
		$merchant_reference = filter_input( INPUT_GET, 'merchant_reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( 'dintero' !== $gateway || empty( $merchant_reference ) ) {
			return;
		}

		/* The transaction_id is only guaranteed when the payment is complete and authorized. That is, not on cancel. */
		$transaction_id = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( empty( $transaction_id ) ) {
			Dintero_Checkout_Logger::log( 'REDIRECT ERROR [transaction_id]: The transaction ID is missing. Redirecting customer back to checkout page.' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Get the order from the merchant reference.
		$order = $this->get_order_from_reference( $merchant_reference );
		if ( empty( $order ) ) {
			wc_add_notice( __( 'Something went wrong with completing the order. Please try again or contact the store.', 'dintero-checkout-for-woocommerce' ), 'error' );
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
		// The return URL can be edited by the customer, so the transaction must be shown to belong to this order.
		$dintero_order = Dintero()->api->get_order( $transaction_id, array(), false );

		// The customer has usually paid by now, so retry a momentary failure once before giving up.
		if ( dintero_is_transient_error( $dintero_order ) ) {
			$dintero_order = Dintero()->api->get_order( $transaction_id, array(), false );
		}

		if ( dintero_is_transient_error( $dintero_order ) ) {
			$this->handle_unverified( $transaction_id, $order, $dintero_order );
			return;
		}

		$verified = is_wp_error( $dintero_order ) ? $dintero_order : dintero_verify_transaction_for_order( $order, $dintero_order, $transaction_id );
		if ( is_wp_error( $verified ) ) {
			Dintero_Checkout_Logger::log( "REDIRECT ERROR [transaction]: {$verified->get_error_message()} WC order id: {$order->get_id()} (transaction ID: $transaction_id). Redirecting customer back to checkout page." );
			if ( ! is_wp_error( $dintero_order ) ) {
				dintero_add_rejected_transaction_note( $order, $dintero_order, $verified );
			}
			wc_add_notice( __( 'Something went wrong with completing the order. Please try again or contact the store.', 'dintero-checkout-for-woocommerce' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		// Removing the error from the return URL must not turn a failed or voided payment into a paid order.
		if ( ! dintero_is_payable_transaction( $dintero_order ) ) {
			Dintero_Checkout_Logger::log( "REDIRECT ERROR [status]: The transaction status {$dintero_order['status']} does not allow the order to be confirmed. WC order id: {$order->get_id()} (transaction ID: $transaction_id). Redirecting customer back to checkout page." );
			wc_add_notice( __( 'Something went wrong with completing the order. Please try again or contact the store.', 'dintero-checkout-for-woocommerce' ), 'error' );
			wp_safe_redirect( wc_get_checkout_url() );
			exit;
		}

		Dintero_Checkout_Logger::log( "REDIRECT [success]: The WC order id: {$order->get_id()} (transaction ID: $transaction_id) was placed successfully. Redirecting customer to thank-you page." );

		dintero_confirm_order( $order, $transaction_id );
		dintero_unset_sessions();
		wp_safe_redirect( $order->get_checkout_order_received_url() );

		exit;
	}

	/**
	 * Handles a redirect whose transaction could not be fetched from Dintero, leaving the order to the callback.
	 *
	 * The order-received URL carries the order key, so it is never used for a transaction that was not verified.
	 *
	 * @param string   $transaction_id The Transaction ID from Dintero.
	 * @param WC_Order $order The WooCommerce order.
	 * @param WP_Error $error The error from the request to Dintero.
	 * @return void
	 */
	public function handle_unverified( $transaction_id, $order, $error ) {
		Dintero_Checkout_Logger::log( "REDIRECT ERROR [unverified]: Could not reach Dintero to verify the transaction: {$error->get_error_message()} WC order id: {$order->get_id()} (transaction ID: $transaction_id). Emptying the cart and leaving the order to the callback." );

		// The transaction id comes from the return URL and is unverified, so it is left out of the note.
		$order->add_order_note( __( 'The customer returned from Dintero, but the payment could not be verified because Dintero could not be reached. The order will be updated when Dintero sends its callback.', 'dintero-checkout-for-woocommerce' ) );

		// The customer has most likely paid, so the cart and session must not let them pay again.
		WC()->cart->empty_cart();
		dintero_unset_sessions();

		wc_add_notice( __( 'Thank you. We are confirming your payment, and you will receive an order confirmation by email once it is done.', 'dintero-checkout-for-woocommerce' ), 'notice' );
		wp_safe_redirect( wc_get_cart_url() );
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
		$order_id = dintero_get_order_id_by_merchant_reference( $merchant_reference );

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
	 * Lock a Dintero transaction id and WooCommerce order id combination to prevent multiple simultaneous confirmations.
	 *
	 * @param string $transaction_id The Dintero transaction id.
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return bool True if the lock was successful, false if there is already a lock for the given combination.
	 */
	public static function lock_dintero_confirmation( $transaction_id, $order_id ) {
		$key = "dintero_confirm_{$transaction_id}_{$order_id}";
		if ( wp_using_ext_object_cache() ) {
			return wp_cache_add( $key, true, 'dintero_locks', MINUTE_IN_SECONDS );
		}

		return set_transient( $key, true, MINUTE_IN_SECONDS );
	}

	/**
	 * Unlock a Dintero transaction id and WooCommerce order id combination after the confirmation process is done.
	 *
	 * @param string $transaction_id The Dintero transaction id.
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return void
	 */
	public static function unlock_dintero_confirmation( $transaction_id, $order_id ) {
		$key = "dintero_confirm_{$transaction_id}_{$order_id}";
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $key, 'dintero_locks' );
			return;
		}

		delete_transient( $key );
	}
}
new Dintero_Checkout_Redirect();
