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
		$order_id = $order->get_id();

		update_post_meta( $order_id, '_dintero_transaction_id', $transaction_id );
		$dintero_order         = Dintero()->api->get_order( $transaction_id );
		$require_authorization = ( ! is_wp_error( $dintero_order ) && 'ON_HOLD' === $dintero_order['status'] );
		if ( $require_authorization ) {
			// translators: %s the Dintero transaction ID.
			$order->update_status( 'manual-review', sprintf( __( 'The order was placed successfully, but requires further authorization by Dintero. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
			$order->save();
			update_post_meta( $order_id, Dintero()->order_management->status( 'on_hold' ), $transaction_id );
			update_post_meta( $order_id, '_transaction_id', $transaction_id );
			Dintero_Checkout_Logger::log( "REDIRECT: The WC order $order_id (transaction ID: $transaction_id) will require further authorization from Dintero." );
		} else {
			// translators: %s the Dintero transaction ID.
			$order->add_order_note( sprintf( __( 'Payment via Dintero Checkout. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );

			$default_status = get_option( 'woocommerce_dintero_checkout_settings' )['order_statuses'];
			if ( 'processing' !== $default_status ) {
				update_post_meta( $order_id, '_transaction_id', $transaction_id );
				$order->update_status( $default_status, __( 'The order was placed successfully.', 'dintero-checkout-for-woocommerce' ) );
			} else {
				dintero_confirm_order( $order );
			}
		}

		// Save shipping id to the order.
		$shipping = $order->get_shipping_methods();
		if ( ! empty( $shipping ) ) {
			$shipping_option_id = $dintero_order['shipping_option']['id'] ?? reset( $shipping );
			update_post_meta( $order->get_id(), '_wc_dintero_shipping_id', $shipping_option_id );
		}

		// Update the transaction with the order number.
		Dintero()->api->update_transaction( $transaction_id, $order->get_order_number() );
		dintero_unset_sessions();
		wp_safe_redirect( $order->get_checkout_order_received_url() );

		Dintero_Checkout_Logger::log( "REDIRECT [success]: The WC order $order_id (transaction ID: $transaction_id) was placed succesfully. Redirecting customer to thank-you page." );
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

		Dintero_Checkout_Logger::log( "REDIRECT ERROR [$error]: $note WC order id: $order_id / %s: %s", $note, $order_id );
		wp_safe_redirect( wc_get_checkout_url() );
		exit;
	}

	/**
	 * Get the order from the reference.
	 *
	 * @param string $merchant_reference The merchant reference from Dintero.
	 * @return WC_Order
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
