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
		'captured'           => '_wc_dintero_captured',
		'canceled'           => '_wc_dintero_canceled',
		'refunded'           => '_wc_dintero_refunded',
		'partially_refunded' => '_wc_dintero_partially_refunded',
	);

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_order' ) );
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

		if ( get_post_meta( $order_id, $this->status['refunded'], true ) ) {
			$order->add_order_note( __( 'The Dintero order has been refunded and can no longer be captured.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( ! $this->is_captured( $order_id ) ) {
			$response = Dintero()->api->capture_order( $order->get_transaction_id(), $order_id );

			if ( $response['is_error'] ) {
				$order->update_status( 'on-hold', ucfirst( $response['result']['message'] . '.' ) );
				return;
			}
			// translators: the amount, the currency.
			$order->add_order_note( sprintf( __( 'The Dintero order successfully captured. Captured amount: %1$.2f %2$s.', 'dintero-checkout-for-woocommerce' ), substr_replace( $response['result']['amount'], wc_get_price_decimal_separator(), -2, 0 ), $response['result']['currency'] ) );
		}

		update_post_meta( $order_id, $this->status['captured'], current_time( ' Y-m-d H:i:s' ) );
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

		if ( get_post_meta( $order_id, $this->status['refunded'], true ) ) {
			$order->add_order_note( __( 'The Dintero order has been refunded and can no longer be canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( ! $this->is_canceled( $order_id ) ) {
			$response = Dintero()->api->cancel_order( $order->get_transaction_id() );

			if ( $response['is_error'] ) {
				$order->update_status( 'on-hold', ucfirst( $response['result']['message'] . '.' ) );
				return;
			}
		}

		$order->add_order_note( __( 'The Dintero order is canceled.', 'dintero-checkout-for-woocommerce' ) );
		update_post_meta( $order_id, $this->status['canceled'], current_time( ' Y-m-d H:i:s' ) );
	}

	/**
	 * Refunds the Dintero order that the WooCommerce order corresponds to.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return boolean|null TRUE on success, FALSE on unrecoverable failure, and null if not relevant or valid.
	 */
	public function refund_order( $order_id ) {
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

		if ( ! get_post_meta( $order_id, $this->status['captured'], true ) ) {
			$order->add_order_note( __( 'There is nothing to refund. The order has not yet been captured in WooCommerce.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( get_post_meta( $order_id, $this->status['canceled'], true ) ) {
			$order->add_order_note( __( 'The Dintero order cannot be refunded since it is canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( get_post_meta( $order_id, $this->status['refunded'], true ) ) {
			$order->add_order_note( __( 'The Dintero order has already been refunded.', 'dintero-chekcout-for-woocommerce' ) );
			return;
		}

		if ( ! $this->is_refunded( $order_id ) ) {
			$response = Dintero()->api->refund_order( $order->get_transaction_id(), $order_id );

			if ( $response['is_error'] ) {
				$order->add_order_note( ucfirst( $response['result']['message'] ) . ': ' . $response['result']['code'] . '.' );
				$order->update_status( 'on-hold' );
				return false;
			}
		}

		if ( $this->is_partially_refunded( $order_id ) ) {
			$order->add_order_note( __( 'The Dintero order has been partially refunded.', 'dintero-checkout-for-woocommerce' ) );
			update_post_meta( $order_id, $this->status['partially_refunded'], current_time( ' Y-m-d H:i:s' ) );

		} else {
			$order->add_order_note( __( 'The Dintero order has been refunded.', 'dintero-checkout-for-woocommerce' ) );
			update_post_meta( $order_id, $this->status['refunded'], current_time( ' Y-m-d H:i:s' ) );
		}

		return true;
	}

	/**
	 * Whether the order has already been captured.
	 *
	 * @param int     $order_id The WooCommerce order id.
	 * @param boolean $backoffice Whether the order is captured in WooCommerce (rather than through the backoffice).
	 * @return boolean TRUE if already captured otherwise FALSE.
	 */
	public function is_captured( $order_id, $backoffice = false ) {
		$order         = wc_get_order( $order_id );
		$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

		if ( ! $dintero_order['is_error'] && ! $backoffice ) {
			return ( 'CAPTURED' === $dintero_order['result']['status'] );
		}

		return ! empty( get_post_meta( $order_id, $this->status['captured'], true ) );
	}

	/**
	 * Whether the order has already been canceled.
	 *
	 * @param int     $order_id The WooCommerce order id.
	 * @param boolean $backoffice Whether the order is canceled in WooCommerce (rather than through the backoffice).
	 * @return boolean TRUE if already canceled otherwise FALSE.
	 */
	public function is_canceled( $order_id, $backoffice = false ) {
		$order         = wc_get_order( $order_id );
		$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

		if ( ! $dintero_order['is_error'] && ! $backoffice ) {
			return ( 'AUTHORIZATION_VOIDED' === $dintero_order['result']['status'] );
		}

		return ! empty( get_post_meta( $order_id, $this->status['canceled'], true ) );
	}

	/**
	 * Whether the order has already been fully refunded.
	 *
	 * @param int     $order_id The WooCommerce order id.
	 * @param boolean $backoffice Whether the order is fully refunded in WooCommerce (rather than through the backoffice).
	 * @return  True if fully refunded otherwise FALSE.
	 */
	public function is_refunded( $order_id, $backoffice = false ) {
		$order         = wc_get_order( $order_id );
		$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

		if ( ! $dintero_order['is_error'] && ! $backoffice ) {
			return ( 'REFUNDED' === $dintero_order['result']['status'] );
		}

		return ! empty( get_post_meta( $order_id, $this->status['refunded'], true ) );
	}

	public function is_partially_refunded( $order_id ) {
		$order = wc_get_order( $order_id );

		$total_refund_amount = 0;
		foreach ( $order->get_refunds() as $refund ) {
			$total_refund_amount += $refund->get_refund_amount();
		}

		return ( $order->get_total() > $total_refund_amount );
	}
}
