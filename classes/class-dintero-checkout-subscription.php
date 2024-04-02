<?php //phpcs:ignore
/**
 * Class for handling Woo subscriptions.
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
		private const GATEWAY_ID     = 'dintero_checkout';
		public const RECURRING_TOKEN = '_' . self::GATEWAY_ID . '_recurring_token';
		public const PAYMENT_TOKEN   = '_' . self::GATEWAY_ID . '_payment_token';

		/**
		 * Register hooks.
		 */
		public function __construct() {
			add_action( 'woocommerce_scheduled_subscription_payment_' . self::GATEWAY_ID, array( $this, 'process_scheduled_payment' ), 10, 2 );
			add_action( 'woocommerce_subscription_cancelled_' . self::GATEWAY_ID, array( $this, 'cancel_scheduled_payment' ) );

			add_action( 'wcs_renewal_order_created', array( $this, 'copy_meta_fields_to_renewal_order' ), 10, 2 );

			add_filter(
				'dintero_checkout_create_session_args',
				function ( $request ) {
					$body = json_decode( $request['body'], true );

					if ( self::cart_has_subscription() ) {
						// Dintero only supports subscriptions with card payments. Required for recurring payments.
						$body['configuration']['payex']['creditcard'] = array(
							'enabled'                => true,
							'generate_payment_token' => true,
						);

						// Only allow free orders if the cart contains a subscription (not limited to trial subscription as a subscription can become free if a 100% discount coupon is applied).
						if ( 0.0 === floatval( $body['order']['amount'] ) ) {
							// TODO: Handle free trial.
						}
					}

					$request['body'] = wp_json_encode( $body );
					return $request;
				}
			);

			// TODO: Modify the GET request to always include the payment and recurrence token. This is currently handled in the dintero_confirm_order utility function.

			// TODO: For free or trial subscription, we set the order as captured to prevent OM from setting the order to on-hold when the merchant set the order to "Completed".
			add_filter( 'woocommerce_payment_complete', array( $this, 'set_subscription_as_captured' ) );

			// TODO: Override the redirect URLs to redirect back to the change payment method page on failure or to the subscription view on success.
			add_filter( 'dintero_checkout_create_hpp_args', array( $this, 'set_subscription_order_redirect_urls' ) );
			// TODO: Override the subscription cost when change payment method.
			add_filter( 'dintero_checkout_create_session_args', array( $this, 'set_subscription_to_free' ) );
			// TODO: On successful payment method change, the customer is redirected back to the subscription view page. We need to handle the redirect and create a recurring token.
			add_action( 'woocommerce_account_view-subscription_endpoint', array( $this, 'handle_redirect_from_change_payment_method' ) );

			// Show the recurring token on the subscription page in the billing fields.
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_payment_token' ) );
			// Ensure wp_safe_redirect do not redirect back to default dashboard or home page.
			add_filter( 'allowed_redirect_hosts', array( $this, 'extend_allowed_domains_list' ) );
		}

		}

		/**
		 * Process subscription renewal.
		 *
		 * @param float    $amount_to_charge Amount to charge.
		 * @param WC_Order $renewal_order The Woo order that will be created as a result of the renewal.
		 * @return void
		 */
		public function process_scheduled_payment( $amount_to_charge, $renewal_order ) {
			$initiate_payment = Dintero()->api->sessions_pay( $renewal_order->get_id() );
			if ( is_wp_error( $initiate_payment ) ) {
				$renewal_order->update_status( 'failed', dintero_retrieve_error_message( $initiate_payment ) );
				return;
			}

			$payment_token = wc_get_var( $initiate_payment['customer']['tokens']['payex.creditcard']['payment_token'] );
			if ( empty( $payment_token ) ) {
				$renewal_order->update_status( 'failed', __( 'The payment token could not be retrieved.', 'dintero-checkout-for-woocommerce' ) );
				return;
			}

			$renewal_order->add_order_note(
				sprintf(
					/* translators: %s: subscription id */
					__( 'Subscription renewal was made successfully via Dintero Checkout. Payment token: %s', 'dintero-checkout-for-woocommerce' ),
					$payment_token
				)
			);

			$dintero_order_id = wc_get_var( $initiate_payment['id'] );
			$subscriptions    = wcs_get_subscriptions_for_renewal_order( $renewal_order->get_id() );
			foreach ( $subscriptions as $subscription ) {
				if ( isset( $dintero_order_id ) ) {
					$subscription->payment_complete( $dintero_order_id );
				} else {
					$subscription->payment_failed();
				}

				// Save to the subscription.
				self::save_payment_token( $subscription->get_id(), $payment_token );
			}

			dintero_confirm_order( $renewal_order, $dintero_order_id );
		}

		/**
		 * Copy meta fields to renewal order.
		 *
		 * @param  WC_Order        $renewal_order Woo renewal order.
		 * @param  WC_Subscription $subscription Woo subscription.
		 * @return WC_Order
		 */
		public function copy_meta_fields_to_renewal_order( $renewal_order, $subscription ) {
			$parent_order = $subscription->get_parent();
			if ( $parent_order->get_meta( '_wc_dintero_shipping_id', true ) ) {
				$renewal_order->update_meta_data( '_wc_dintero_shipping_id', $parent_order->get_meta( '_wc_dintero_shipping_id' ) );
				$renewal_order->save();
			}

			return $renewal_order;
		}


		/**
		 * Save the recurring token to the order and its subscription(s).
		 *
		 * @param string $order_id The WooCommerce order id.
		 * @param string $recurring_token The recurring token ("customer token").
		 * @return void
		 */
		public static function save_recurring_token( $order_id, $recurring_token ) {
			self::save_token( $order_id, $recurring_token, 'recurrence' );
		}

		/**
		 * Save the recurring token to the order and its subscription(s).
		 *
		 * @param string $order_id The WooCommerce order id.
		 * @param string $payment_token The payment token ("customer token").
		 * @return void
		 */
		public static function save_payment_token( $order_id, $payment_token ) {
			self::save_token( $order_id, $payment_token, 'payment' );
		}

		/**
		 * Save a token to the order and its subscription(s).
		 *
		 * @param string $order_id The WooCommerce order id.
		 * @param string $token The token ("customer token").
		 * @param string $token_type The type of token may be either 'payment' or 'recurrence'.
		 * @return void
		 */
		public static function save_token( $order_id, $token, $token_type ) {
			$order      = wc_get_order( $order_id );
			$token_type = 'recurrence' === $token_type ? self::RECURRING_TOKEN : self::PAYMENT_TOKEN;
			$order->update_meta_data( $token_type, $token );

			foreach ( wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) ) as $subscription ) {
				$subscription->update_meta_data( $token_type, $token );
				$subscription->save();
			}

			$order->save();
		}

		/**
		 * Retrieve the recurrence token for subscriptions (unattended) transactions.
		 *
		 * @param  int $order_id The WooCommerce order id.
		 * @return string The recurring token. If none is found, an empty string is returned.
		 */
		public static function get_recurring_token( $order_id ) {
			return self::get_token( $order_id, 'recurrence' );
		}

		/**
		 * Retrieve the payment token required for unscheduled (unattended) transactions.
		 *
		 * @param  int $order_id The WooCommerce order id.
		 * @return string The payment token. If none is found, an empty string is returned.
		 */
		public static function get_payment_token( $order_id ) {
			return self::get_token( $order_id, 'payment' );
		}

		/**
		 * Retrieve a token from a Woo order.
		 *
		 * @param mixed  $order_id The Woo order ID.
		 * @param string $token_type The type of token may be either 'payment' or 'recurrence'.
		 * @return string The token or empty string.
		 */
		public static function get_token( $order_id, $token_type ) {
			$order      = wc_get_order( $order_id );
			$token_type = 'recurrence' === $token_type ? self::RECURRING_TOKEN : self::PAYMENT_TOKEN;
			$token      = $order->get_meta( $token_type );

			if ( empty( $token ) ) {
				$subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );
				foreach ( $subscriptions as $subscription ) {
					$parent_order = $subscription->get_parent();
					$token        = $parent_order->get_meta( $token_type );

					if ( ! empty( $token ) ) {
						break;
					}
				}
			}

			return $token;
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

		/**
		 * Check if the current request is for changing the payment method.
		 *
		 * @return bool
		 */
		public static function is_change_payment_method() {
			return isset( $_GET['change_payment_method'] );
		}

		/**
		 * Check if an order contains a subscription.
		 *
		 * @param WC_Order $order The WooCommerce order or leave empty to use the cart (default).
		 * @return bool
		 */
		public static function order_has_subscription( $order ) {
			if ( empty( $order ) ) {
				return false;
			}

			return function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order, array( 'parent', 'resubscribe', 'switch', 'renewal' ) );
		}

		/**
		 * Check if a cart contains a subscription.
		 *
		 * @return bool
		 */
		public static function cart_has_subscription() {
			if ( ! is_checkout() ) {
				return false;
			}

			return ( class_exists( 'WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription() ) || ( function_exists( 'wcs_cart_contains_failed_renewal_order_payment' ) && wcs_cart_contains_failed_renewal_order_payment() );
		}

		/**
		 * Add Dintero redirect payment page as allowed external url for wp_safe_redirect.
		 * We do this because WooCommerce Subscriptions use wp_safe_redirect when processing a payment method change request (from v5.1.0).
		 *
		 * @param array $hosts Domains that are allowed when wp_safe_redirect is used.
		 * @return array
		 */
		public function extend_allowed_domains_list( $hosts ) {
			// TODO: Add hosts.
			return $hosts;
		}

		/**
		 * Shows the recurring token for the order.
		 *
		 * @param WC_Order $order The WooCommerce order.
		 * @return void
		 */
		public function show_payment_token( $order ) {
			if ( 'shop_subscription' === $order->get_type() && $order->get_meta( self::PAYMENT_TOKEN ) ) {
				?>
			<div class="order_data_column" style="clear:both; float:none; width:100%;">
				<div class="address">
					<p>
						<strong><?php echo esc_html( 'Dintero payment token' ); ?>:</strong><?php echo esc_html( $order->get_meta( self::PAYMENT_TOKEN ) ); ?>
					</p>
				</div>
				<div class="edit_address">
				<?php
					woocommerce_wp_text_input(
						array(
							'id'            => self::PAYMENT_TOKEN,
							'label'         => __( 'Dintero payment token', 'dintero-checkout-for-woocommerce' ),
							'wrapper_class' => '_billing_company_field',
						)
					);
				?>
				</div>
			</div>
					<?php
			}
		}
	}

	new Dintero_Checkout_Subscription();
}
