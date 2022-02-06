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
	 * The meta fields used for indicating status.
	 *
	 * @var array
	 */
	private $status = array(
		'captured' => '_wc_dintero_captured',
		'canceled' => '_wc_dintero_canceled',
	);

	/**
	 * An instance of the capture order management.
	 *
	 * @var Dintero_Checkout_Capture_Order
	 */
	private $capture;

	/**
	 * An instance of the cancel order management.
	 *
	 * @var Dintero_Checkout_Cancel_Order
	 */
	private $cancel;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_order' ) );
		add_action( 'woocommerce_order_status_refund', array( $this, 'refund_order' ) );

		$this->capture = new Dintero_Checkout_Capture_Order();
		$this->cancel  = new Dintero_Checkout_Cancel_Order();
	}

	/**
	 * Captures the Dintero order that the WooCommerce order corresponds to.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return void
	 */
	public function capture_order( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( 'dintero_checkout' !== $order->get_payment_method() ) {
			return;
		}

		// Check if the order has at least been processed.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		if ( empty( $order->get_transaction_id() ) ) {
			$order->add_order_note( __( 'The order is missing a transaction ID.', 'dintero-chekcout-for-woocommerce' ) );
			return;
		}

		if ( get_post_meta( $order_id, $this->status['captured'], true ) ) {
			$order->add_order_note( __( 'The Dintero order has already been captured.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( get_post_meta( $order_id, $this->status['canceled'], true ) ) {
			$order->add_order_note( __( 'The Dintero order was canceled and can no longer be captured.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( ! $this->is_captured( $order_id ) ) {
			$response = $this->capture->capture( $order->get_transaction_id(), $order_id );

			if ( $response['is_error'] ) {
				// translators: the error code, the error message.
				$order->update_status( 'on-hold', ucfirst( $response['result']['message'] . '.' ) );
				return;
			}
			// translators: the amount, the currency.
			$order->add_order_note( sprintf( __( 'The Dintero order successfully captured. Captured amount: %1$.2f %2$s.', 'dintero-checkout-for-woocommerce' ), substr_replace( $response['result']['amount'], wc_get_price_decimal_separator(), -2, 0 ), $response['result']['currency'] ) );
		}

		update_post_meta( $order_id, $this->status['captured'], 'yes', true );
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

		// Check if the order has at least been processed.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		if ( get_post_meta( $order_id, $this->status['captured'], true ) ) {
			$order->add_order_note( __( 'The Dintero order has been captured, and can therefore no longer be canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( get_post_meta( $order_id, $this->status['canceled'], true ) ) {
			$order->add_order_note( __( 'The Dintero order has already been canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( ! $this->is_canceled( $order_id ) ) {
			$response = $this->cancel->cancel( $order->get_transaction_id() );

			if ( $response['is_error'] ) {
				$order->update_status( 'on-hold', ucfirst( $response['result']['message'] . '.' ) );
				return;
			}
		}

		$order->add_order_note( __( 'The Dintero order is canceled.', 'dintero-checkout-for-woocommerce' ) );
		update_post_meta( $order_id, $this->status['canceled'], 'yes', true );
	}

	public function refund_order( $order_id ) {
		// TODO: Add support for refund order.
		return;
	}

	/**
	 * Whether the order has already been captured.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return boolean TRUE if already captured otherwise FALSE.
	 */
	public function is_captured( $order_id ) {
		$order         = wc_get_order( $order_id );
		$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

		if ( ! $dintero_order['is_error'] ) {
			return ( 'CAPTURED' === $dintero_order['result']['status'] );
		}

		return ! empty( get_post_meta( $order_id, $this->status['captured'], true ) );
	}

	/**
	 * Whether the order has already been canceled.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return boolean TRUE if already canceled otherwise FALSE.
	 */
	public function is_canceled( $order_id ) {
		$order         = wc_get_order( $order_id );
		$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

		if ( ! $dintero_order['is_error'] ) {
			return ( 'AUTHORIZATION_VOIDED' === $dintero_order['result']['status'] );
		}
		return ! empty( get_post_meta( $order_id, $this->status['canceled'], true ) );
	}

	public function is_refunded( $order_id ) {
		// TODO: Check if is_refunded.
		return;
	}
}
