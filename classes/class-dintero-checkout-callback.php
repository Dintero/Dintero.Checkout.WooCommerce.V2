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
		$get_params = wp_json_encode( filter_var_array( $_GET, FILTER_SANITIZE_FULL_SPECIAL_CHARS ) );
		Dintero_Checkout_Logger::log( "CALLBACK: Callback triggered by Dintero. Data: $get_params" );
		$merchant_reference = filter_input( INPUT_GET, 'merchant_reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$transaction_id     = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$error              = filter_input( INPUT_GET, 'error', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $merchant_reference ) ) {
			Dintero_Checkout_Logger::log( 'CALLBACK ERROR [merchant_reference]: The merchant reference is missing from the callback.' );
			http_response_code( 400 );
			die;
		}

		// Get the order relevant for the callback.
		$order = $this->get_order_from_reference( $merchant_reference );
		if ( empty( $order ) ) {
			http_response_code( 400 );
			die;
		}

		// Handle any error callbacks from Dintero.
		if ( ! empty( $error ) ) {
			$this->handle_error_callback( $error, $order );
		}

		$this->handle_callback( $transaction_id, $order );
	}

	/**
	 * Handle callback error events. Sent by Dintero.
	 *
	 * @param string   $error The error type.
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function handle_error_callback( $error, $order ) {
		$order_note = true;
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
				$note       = 'Unknown event.';
				$order_note = false;
				break;
		}

		Dintero_Checkout_Logger::log( "CALLBACK: $note" );
		if ( $order_note ) {
			$order->add_order_note( $note );
		}
		http_response_code( 200 );
		die;
	}

	/**
	 * Handle normal callbacks from Dintero.
	 *
	 * @param string   $transaction_id The Transaction id from Dintero.
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function handle_callback( $transaction_id, $order ) {
		if ( empty( $transaction_id ) ) {
			Dintero_Checkout_Logger::log( 'CALLBACK ERROR [transaction_id]: The transaction id is missing from the callback.' );
			http_response_code( 400 );
			die;
		}

		// Get the order from Dintero.
		$dintero_order = Dintero()->api->get_order( $transaction_id );
		if ( is_wp_error( $dintero_order ) ) {
			http_response_code( 400 );
			die;
		}

		switch ( $dintero_order['status'] ) {
			case 'AUTHORIZED':
				Dintero_Checkout_Logger::log( 'CALLBACK: Handling AUTHORIZED order status. Maybe triggering payment_complete.' );
				dintero_confirm_order( $order );
				break;
			case 'AUTHORIZATION_VOIDED':
				Dintero_Checkout_Logger::log( 'CALLBACK: Handling AUTHORIZATION_VOIDED order status. Setting order status to CANCELLED.' );
				$order->update_status( 'cancelled', __( 'The order was CANCELED in the Dintero.', 'dintero-checkout-for-woocommerce' ) );
				$order->save();
				break;
			case 'CAPTURED':
				Dintero_Checkout_Logger::log( 'CALLBACK: Handling CAPTURED order status.' );
				break;
			case 'REFUNDED':
				Dintero_Checkout_Logger::log( 'CALLBACK: Handling REFUNDED order status.' );
				break;
			case 'DECLINED':
			case 'FAILED':
				Dintero_Checkout_Logger::log( "CALLBACK: Handling {$dintero_order['status']} order status. Setting order status to FAILED." );
				$order->update_status( 'failed', __( 'The order was not approved by Dintero.', 'dintero-checkout-for-woocommerce' ) );
				$order->save();
				break;
			default:
				Dintero_Checkout_Logger::log( "CALLBACK: Unknown order status on callback. {$dintero_order['status']}" );
				break;
		}

		http_response_code( 200 );
		die;
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
			Dintero_Checkout_Logger::log( "CALLBACK ERROR [order_id]: Could not get an order_id from the merchant reference $merchant_reference" );
			return null;
		}

		$order = wc_get_order( $order_id );

		// Check if we get a valid order.
		if ( empty( $order ) ) {
			Dintero_Checkout_Logger::log( "CALLBACK ERROR [order]: Could not get an order from the merchant reference $merchant_reference" );
			return null;
		}

		return $order;
	}

	/**
	 * Handles any errors in the callback from Dintero.
	 *
	 * @param string   $error The error code.
	 * @param WC_Order $order The WooCommerce order.
	 * @return string
	 */
	public function get_error_message( $error, $order ) {
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

		if ( $show_on_order_page ) {
			$order->add_order_note( $note );
		}

		return $note;
	}

	/**
	 * Retrieve the callback URL.
	 *
	 * @static
	 * @return string The callback URL (relative to the home URL).
	 */
	public static function callback_url() {
		// Events to listen to when they happen in the back office.
		$events = '&report_event=CAPTURE&report_event=REFUND&report_event=VOID';

		return add_query_arg(
			array(
				'delay_callback'     => 120, /* seconds. */
				'report_error'       => 'true',
				'sid_parameter_name' => 'sid',
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
		return ( isset( $_SERVER['REMOTE_ADDR'] ) && in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ), true ) || isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === substr( wp_unslash( $_SERVER['HTTP_HOST'] ), 0, 9 ) ); // phpcs:ignore
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

} new Dintero_Checkout_Callback();
