<?php //phpcs:ignore
/**
 * Class for Dintero Checkout Gateway.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Subscription' ) ) {

	/**
	 * Class for handling subscriptions.
	 */
	class Dintero_Checkout_Subscription {

		private const GATEWAY_ID = 'dintero_checkout';

		/**
		 * Register hooks.
		 */
		public function __construct() {
			add_action( 'woocommerce_scheduled_subscription_payment_' . self::GATEWAY_ID, array( $this, 'process_scheduled_payment' ), 10, 2 );
			add_action( 'wcs_renewal_order_created', array( $this, 'copy_meta_fields_to_renewal_order' ), 10, 2 );
		}

		/**
		 * Copy meta fields to renewal order.
		 *
		 * @param  WC_Order        $renewal_order
		 * @param  WC_Subscription $subscription
		 * @return WC_Order
		 */
		public function copy_meta_fields_to_renewal_order( $renewal_order, $subscription ) {
			$parent_order = $subscription->get_parent();
			if ( $parent_order->get_meta( '_wc_dintero_shipping_id', true ) ) {
				$renewal_order->update_meta_data( '_wc_dintero_shipping_id', $parent_order->get_meta( '_wc_dintero_shipping_id' ) );
			}

			return $renewal_order;
		}

		/**
		 * Process subscription renewal.
		 *
		 * @param float    $amount_to_charge
		 * @param WC_Order $renewal_order
		 * @return void
		 */
		public function process_scheduled_payment( $amount_to_charge, $renewal_order ) {
			$initiate_payment = Dintero()->api->sessions_pay( self::get_parent_order( $renewal_order->get_id() )->get_id() );
			if ( is_wp_error( $initiate_payment ) ) {
				$renewal_order->update_status( 'failed', $initiate_payment->get_error_message() );
				return;
			}

			$renewal_order->add_order_note(
				sprintf(
					/* translators: %s: subscription id */
					__( 'Subscription renewal was made successfully via Dintero Checkout. Subscription ID: %s', 'dintero-checkout-for-woocommerce' ),
					$initiate_payment['id']
				)
			);

			dintero_confirm_order( $renewal_order, $initiate_payment['id'] );
		}

		/**
		 * Save the payment and recurring token to the order if it has a subscription.
		 *
		 * @param string $order_id The WooCommerce order id.
		 * @param string $transaction_id The Dintero transaction id.
		 * @return void
		 */
		public static function save_recurring_token( $order_id, $transaction_id ) {
			if ( ! wcs_order_contains_subscription( $order_id ) ) {
				return;
			}

			$params        = array( 'includes' => array( 'card.payment_token', 'card.recurrence_token' ) );
			$dintero_order = Dintero()->api->get_order( $transaction_id, $params );

			if ( ! is_wp_error( $dintero_order ) && isset( $dintero_order['card'] ) ) {
				$card = $dintero_order['card'];
				if ( isset( $card['recurrence_token'] ) ) {
					update_post_meta( $order_id, '_dintero_recurrence_token', $card['recurrence_token'] );
				}
			}
		}

		/**
		 * Retrieve the necessary tokens required for subscriptions (unattended) payments.
		 *
		 * @param  int $order_id The WooCommerce order id.
		 * @return array The recurrence token. If none are found, the value will be empty.
		 */
		public static function retrieve_recurring_tokens( $order_id ) {
			$recurrence_token = get_post_meta( $order_id, '_dintero_recurrence_token', true );

			if ( empty( $recurrence_token ) ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
				foreach ( $subscriptions as $subscription ) {
					$parent_order     = $subscription->get_parent();
					$recurrence_token = get_post_meta( $parent_order->get_id(), '_dintero_recurrence_token', true );

					if ( ! empty( $recurrence_token ) && ! empty( $payment_token ) ) {
						break;
					}
				}
			}

			return array(
				'recurrence_token' => $recurrence_token,
			);
		}

		/**
		 * Get a subscription's parent order.
		 *
		 * @param int $order_id The WooCommerce order id.
		 * @return WC_Order|false The parent order or false if none is found.
		 */
		public static function get_parent_order( $order_id ) {
			$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
			foreach ( $subscriptions as $subscription ) {
				$parent_order = $subscription->get_parent();
				return $parent_order;
			}

			return false;
		}

	}

	new Dintero_Checkout_Subscription();
}
