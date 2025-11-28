<?php //phpcs:ignore
/**
 * Class for handling for handling order management request from within WooCommerce.
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
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var Dintero $instance
	 */
	private static $instance;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @static
		 * @return Dintero The *Singleton* instance.
		 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

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
		'on_hold'            => '_wc_dintero_on_hold',
		'rejected'           => '_wc_dintero_rejected',
	);

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_order_status_completed', array( $this, 'capture_order' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'cancel_order' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'refund_order' ) );
	}

	/**
	 * Retrieve the meta field id for a specific status.
	 *
	 * @param string $status The status whose meta field id you want.
	 * @return string The meta field id.
	 */
	public function status( $status ) {
		return $this->status[ $status ];
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

		if ( empty( $order->get_transaction_id() ) ) {
			$order->add_order_note( __( 'The order is missing a transaction ID.', 'dintero-checkout-for-woocommerce' ) );
			$order->update_status( 'on-hold' );
			return;
		}

		if ( $order->get_meta( $this->status['rejected'] ) ) {
			$order->add_order_note( __( 'The Dintero order was rejected, and can therefore not be captured.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		// An unauthorized order cannot be captured. Check if the order requires further authorization.
		if ( ! $this->is_authorized( $order_id ) ) {
			$order->add_order_note( __( 'The order must be authorized by Dintero before it can be captured.', 'dintero-checkout-for-woocommerce' ) );
			$order->update_status( 'on-hold' );
			return;
		}

		if ( $order->get_meta( $this->status['captured'] ) ) {
			$order->add_order_note( __( 'The Dintero order has already been captured.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( $order->get_meta( $this->status['canceled'] ) ) {
			$order->add_order_note( __( 'The Dintero order was canceled and can no longer be captured.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( $order->get_meta( $this->status['refunded'] ) ) {
			$order->add_order_note( __( 'The Dintero order has been refunded and can no longer be captured.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		// Check if the Dintero order has been captured in the back-office.
		if ( ! $this->is_captured( $order_id ) ) {
			$response = Dintero()->api->capture_order( $order->get_transaction_id(), $order_id );

			if ( is_wp_error( $response ) ) {
				/**
				 * Handling of a WP_Error.
				 *
				 * @var WP_Error $response The WP_Error response.
				 */
				if ( is_array( $response->get_error_message() ) ) {
					$note = sprintf( '[%s] %s', $response->get_error_code(), $response->get_error_message()['message'] );
				} else {
					$note = ucfirst( $response->get_error_message() ) . ': ' . $response->get_error_code() . '.';
				}

				$order->add_order_note( $note );
				$order->update_status( 'on-hold' );
				return;
			}

			if ( $response['amount'] > 0 ) {
				// The last capture event is most likely the current capture event.
				$event = array_filter(
					array_reverse( $response['events'] ),
					function ( $event ) {
						return 'CAPTURE' === $event['event'];
					}
				);

				// Since the amount in the response is the total amount, not the total captured amount. We need to get the amount from the last capture event.
				$event  = reset( $event );
				$amount = isset( $event['event'] ) ? $event['amount'] : $response['amount'];

				$note = sprintf(
					// translators: the amount, the currency.
					__( 'The Dintero order has been captured. Captured amount: %1$.2f %2$s.', 'dintero-checkout-for-woocommerce' ),
					substr_replace( $amount, wc_get_price_decimal_separator(), -2, 0 ),
					$response['currency']
				);

			} else {
				$note = __( 'The Dintero order has been captured.', 'dintero-checkout-for-woocommerce' );
			}
		}

		if ( ! isset( $note ) ) {
			// Dintero will capture the order immediately for some payment methods (e.g., Swish).
			$note = __( 'The Dintero order has been captured.', 'dintero-checkout-for-woocommerce' );
		}

		$order->add_order_note( $note );
		$order->update_meta_data( $this->status['captured'], current_time( ' Y-m-d H:i:s' ) );
		$order->save();
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

		if ( $order->get_meta( $this->status['rejected'] ) ) {
			$order->add_order_note( __( 'The Dintero order was rejected, and can therefore not be canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		// An unauthorized order cannot be canceled. Check if the order requires further authorization.
		if ( ! $this->is_authorized( $order_id ) ) {
			$order->add_order_note( __( 'The order must be authorized by Dintero before it can be canceled.', 'dintero-checkout-for-woocommerce' ) );
			$order->update_status( 'on-hold' );
			return;
		}

		// Check if the order has at least been processed.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		if ( empty( $order->get_transaction_id() ) ) {
			$order->add_order_note( __( 'The order is missing a transaction ID.', 'dintero-checkout-for-woocommerce' ) );
			$order->update_status( 'on-hold' );
			return;
		}

		$payment_method = $order->get_meta( '_dintero_payment_method' ) ?? '';

		if ( $order->get_meta( $this->status['captured'] ) && 'swish' !== strtolower( $payment_method ) ) {
			$order->add_order_note( __( 'The Dintero order has been captured, and can therefore no longer be canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( $order->get_meta( $this->status['canceled'] ) ) {
			$order->add_order_note( __( 'The Dintero order has already been canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( $order->get_meta( $this->status['refunded'] ) ) {
			$order->add_order_note( __( 'The Dintero order has been refunded and can no longer be canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		// If the payment method is Swish, we need to refund instead of cancel.
		if ( 'swish' === strtolower( $payment_method ) ) {
			$refund = wc_create_refund(
				array(
					'amount'         => $order->get_total(),
					'reason'         => 'Swish order canceled by customer.',
					'order_id'       => $order_id,
					'refund_payment' => true,
				)
			);
			return;
		}

				// Check if Dintero order has been canceled in the back-office.
		if ( ! $this->is_canceled( $order_id ) ) {
			$response = Dintero()->api->cancel_order( $order->get_transaction_id() );

			if ( is_wp_error( $response ) ) {
				/**
				 * Handling of a WP_Error.
				 *
				 * @var WP_Error $response The WP_Error response.
				 */
				if ( is_array( $response->get_error_message() ) ) {
					$note = sprintf( '[%s] %s', $response->get_error_code(), $response->get_error_message()['message'] );
				} else {
					$note = ucfirst( $response->get_error_message() ) . ': ' . $response->get_error_code() . '.';
				}

				$order->add_order_note( $note );
				$order->update_status( 'on-hold' );
				return;
			}
		}

				$order->add_order_note( __( 'The Dintero order has been canceled.', 'dintero-checkout-for-woocommerce' ) );
				$order->update_meta_data( $this->status['canceled'], current_time( ' Y-m-d H:i:s' ) );
				$order->save();
	}

	/**
	 * Refunds the Dintero order that the WooCommerce order corresponds to.
	 *
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $reason The reason for the refund.
	 * @return boolean|null TRUE on success, FALSE on unrecoverable failure, and null if not relevant or valid.
	 */
	public function refund_order( $order_id, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( 'dintero_checkout' !== $order->get_payment_method() ) {
			return;
		}

		if ( did_action( 'woocommerce_order_status_refunded' ) ) {
			$settings      = get_option( 'woocommerce_dintero_checkout_settings' );
			$manual_refund = $settings['order_management_manual_refund'] ?? 'no';
			if ( 'no' === $manual_refund || $order->get_meta( $this->status['refunded'] ) ) {
				return;
			}
		}

		// Check if the order has at least been processed. This also covers for checking if the order requires further authorization.
		if ( empty( $order->get_date_paid() ) ) {
			return;
		}

		if ( empty( $order->get_transaction_id() ) ) {
			$order->add_order_note( __( 'The order is missing a transaction ID.', 'dintero-checkout-for-woocommerce' ) );
			$order->update_status( 'on-hold' );
			return;
		}

		if ( ! $order->get_meta( $this->status['captured'] ) ) {
			$order->add_order_note( __( 'There is nothing to refund. The order has not yet been captured in WooCommerce.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( $order->get_meta( $this->status['canceled'] ) ) {
			$order->add_order_note( __( 'The Dintero order cannot be refunded since it is canceled.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		if ( $order->get_meta( $this->status['refunded'] ) ) {
			$order->add_order_note( __( 'The Dintero order has already been refunded.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		// Check if the Dintero order has been _fully_ refunded in the back-office.
		if ( ! $this->is_refunded( $order_id ) ) {
			$response = Dintero()->api->refund_order( $order->get_transaction_id(), $order_id, $reason );

			if ( is_wp_error( $response ) ) {
				/**
				 * Handling of a WP_Error.
				 *
				 * @var WP_Error $response The WP_Error response.
				 */
				if ( is_array( $response->get_error_message() ) ) {
					$note = sprintf( '[%s] %s', $response->get_error_code(), $response->get_error_message()['message'] );
				} else {
					$note = ucfirst( $response->get_error_message() ) . ': ' . $response->get_error_code() . '.';
				}

				$order->add_order_note( $note );
				return;
			}
		}

		if ( $this->is_partially_refunded( $order_id ) ) {
			// The last refund event is most likely the current refund event .
			$event = array_filter(
				array_reverse( $response['events'] ),
				function ( $event ) {
					return 'REFUND' === $event['event'];
				}
			);

			// Since the amount in the response is the total order amount, not the refunded amount. We need to get the amount from the last refund event.
			$event = reset( $event );
			if ( isset( $event['event'] ) ) {
				$amount = $event['amount'];
				$note   = sprintf(
				// translators: the amount, the currency.
					__( 'The Dintero order has been partially refunded. Refunded amount: %1$.2f %2$s.', 'dintero-checkout-for-woocommerce' ),
					substr_replace( $amount, wc_get_price_decimal_separator(), -2, 0 ),
					$response['currency']
				);
			} else {
				$note = __( 'The Dintero order has been partially refunded.', 'dintero-checkout-for-woocommerce' );
			}

			$order->add_order_note( $note );
			$order->update_meta_data( $this->status['partially_refunded'], current_time( ' Y-m-d H:i:s' ) );

		} else {
			$order->add_order_note( __( 'The Dintero order has been refunded.', 'dintero-checkout-for-woocommerce' ) );
			$order->update_meta_data( $this->status['refunded'], current_time( ' Y-m-d H:i:s' ) );
		}

		$order->save();

		return true;
	}

	/**
	 * Whether the order has already been captured.
	 *
	 * @param int     $order_id The WooCommerce order id.
	 * @param boolean $in_woocommerce Whether the order is captured in WooCommerce (rather than through the backoffice; identified by the presence of a meta field).
	 * @return boolean TRUE if already captured otherwise FALSE.
	 */
	public function is_captured( $order_id, $in_woocommerce = false ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order->get_transaction_id() && ! $in_woocommerce ) ) {
			$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

			if ( ! is_wp_error( $dintero_order ) ) {
				return ( 'CAPTURED' === $dintero_order['status'] );
			}
		}

		return ! empty( $order->get_meta( $this->status['captured'] ) );
	}

	/**
	 * Whether the order has already been canceled.
	 *
	 * @param int     $order_id The WooCommerce order id.
	 * @param boolean $in_woocommerce Whether the order is canceled in WooCommerce (rather than through the backoffice; identified by the presence of a meta field).
	 * @return boolean TRUE if already canceled otherwise FALSE.
	 */
	public function is_canceled( $order_id, $in_woocommerce = false ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order->get_transaction_id() ) && ! $in_woocommerce ) {
			$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

			if ( ! is_wp_error( $dintero_order ) ) {
				return ( 'AUTHORIZATION_VOIDED' === $dintero_order['status'] );
			}
		}

		return ! empty( $order->get_meta( $this->status['canceled'] ) );
	}

	/**
	 * Whether the order has already been fully refunded.
	 *
	 * @param int     $order_id The WooCommerce order id.
	 * @param boolean $in_woocommerce Whether the order is refunded in WooCommerce (rather than through the backoffice; identified by the presence of a meta field).
	 * @return boolean TRUE if _fully_ refunded otherwise FALSE.
	 */
	public function is_refunded( $order_id, $in_woocommerce = false ) {
		$order = wc_get_order( $order_id );

		if ( ! empty( $order->get_transaction_id() && ! $in_woocommerce ) ) {
			$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );

			if ( ! is_wp_error( $dintero_order ) ) {
				return ( 'REFUNDED' === $dintero_order['status'] );
			}
		}

		return ! empty( $order->get_meta( $this->status['refunded'] ) );
	}

	/**
	 * Check if the Dintero order has been authorized.
	 *
	 * Only used for managing orders.
	 *
	 * @param  int $order_id The WooCommerce order id.
	 * @return boolean
	 */
	public function is_authorized( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( empty( $order->get_meta( $this->status( 'on_hold' ) ) ) ) {
			return true;
		}

		$dintero_order = Dintero()->api->get_order( $order->get_transaction_id() );
		if ( is_wp_error( $dintero_order ) ) {
			return false;
		}

		if ( 'ON_HOLD' !== $dintero_order['status'] ) {
			dintero_confirm_order( $order, $order->get_transaction_id() );
		}

		return 'ON_HOLD' !== $dintero_order;
	}

	/**
	 * Whether the order has been partially refunded.
	 *
	 * @param int $order_id The WooCommerce order id.
	 * @return boolean TRUE if partially refunded otherwise FALSE.
	 */
	public function is_partially_refunded( $order_id ) {
		$order = wc_get_order( $order_id );

		$total_refund_amount = 0;
		foreach ( $order->get_refunds() as $refund ) {
			$total_refund_amount += $refund->get_amount();
		}

		return ( $order->get_total() > $total_refund_amount );
	}
}
