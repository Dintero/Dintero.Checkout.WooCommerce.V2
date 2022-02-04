<?php //phpcs:ignore
/**
 * Order management class file.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle order management in WooCommerce.
 */
class Dintero_Checkout_Order_Management {

	/**
	 * Register hooks for order status changes.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ) );
	}

	/**
	 * Cancels the Dintero order that the WooCommerce order corresponds to.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return void
	 */
	public function cancel_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'dintero_checkout' !== $order->get_payment_method() ) {
			return;
		}

		// TODO: Check if the order has been captured previously.
		if ( get_post_meta( $order_id, '_wc_dintero_capture_id', true ) ) {
			$order->add_order_note( __( 'The order has been captured, and can therefore no longer be canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( get_post_meta( $order_id, '_wc_dintero_cancelled', true ) ) {
			$order->add_order_note( __( 'The order has already been canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		$cancel_request = new Dintero_Checkout_Cancel_Order();
		$response       = $cancel_request->cancel( $order->get_transaction_id() );
		$order->add_order_note( json_encode( $response['result'] ) );

		if ( $response['is_error'] ) {
			// translators: the error message, the error code.
			$order->add_order_note( sprintf( __( '%1$s due to %2$s.', 'dintero-checkout-for-woocommerce' ), ucfirst( $response['result']['message'] ), $response['result']['code'] ) );

			return;
		}

		update_post_meta( $order_id, '_wc_dintero_cancelled', 'yes', true );
	}
}
