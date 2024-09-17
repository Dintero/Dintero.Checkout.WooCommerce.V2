<?php //phpcs:ignore
/**
 * Class for handling Woo subscriptions.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Class for handling subscriptions.
 */
class Dintero_Checkout_Subscription {
	private const GATEWAY_ID     = 'dintero_checkout';
	public const RECURRING_TOKEN = '_' . self::GATEWAY_ID . '_recurring_token';
	public const PAYMENT_TOKEN   = '_' . self::GATEWAY_ID . '_payment_token';

	public const DO_NOT_RETRY = '_' . self::GATEWAY_ID . '_do_not_retry';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'woocommerce_scheduled_subscription_payment_' . self::GATEWAY_ID, array( $this, 'process_scheduled_payment' ), 10, 2 );
		add_action( 'woocommerce_subscription_cancelled_' . self::GATEWAY_ID, array( $this, 'cancel_scheduled_payment' ) );

		// Since the metadata about the shipping is stored to the order, and it is required, we must include this in the subsequent renewal orders too.
		add_action( 'wcs_renewal_order_created', array( $this, 'copy_meta_fields_to_renewal_order' ), 10, 2 );

		// Request Dintero to create a payment token, and pass along the subscription profile ID when creating a new session.
		add_filter( 'dintero_checkout_create_session_args', array( $this, 'set_session_options' ) );

		// Set the return_url for change payment method.
		add_filter( 'dintero_checkout_payment_token_args', array( $this, 'set_subscription_order_redirect_urls' ), 10, 2 );

		// Whether the gateway should be available when handling subscriptions.
		add_filter( 'dintero_checkout_is_available', array( $this, 'is_available' ) );

		// On successful payment method change, the customer is redirected back to the subscription view page. We need to handle the redirect and create a recurring token.
		add_action( 'woocommerce_account_view-subscription_endpoint', array( $this, 'handle_redirect_from_change_payment_method' ) );

		// Show the recurring token on the subscription page in the billing fields.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'show_payment_token' ) );

		// Dintero supports free subscriptions.
		add_filter( 'woocommerce_cart_needs_payment', array( $this, 'cart_needs_payment' ) );

		// Ensure wp_safe_redirect do not redirect back to default dashboard or home page on change_payment_method.
		add_filter( 'allowed_redirect_hosts', array( $this, 'extend_allowed_domains_list' ) );

		// Save payment token to the subscription when the merchant updates the order from the subscription page.
		add_action( 'woocommerce_saved_order_items', array( $this, 'subscription_updated_from_order_page' ), 10, 2 );
	}

	/**
	 * Process subscription renewal.
	 *
	 * @param float    $amount_to_charge Amount to charge.
	 * @param WC_Order $renewal_order The Woo order that will be created as a result of the renewal.
	 * @return void
	 */
	public function process_scheduled_payment( $amount_to_charge, $renewal_order ) {
		$subscriptions = wcs_get_subscriptions_for_renewal_order( $renewal_order );

		// If the payment token is different, we should retry anyway even if the order has previously failed.
		$do_not_retry = $renewal_order->get_meta( self::DO_NOT_RETRY );
		if ( empty( $do_not_retry ) ) {
			// Maybe it is set in a subscription?
			$subscription = reset( $subscriptions );
			$do_not_retry = $subscription->get_meta( self::DO_NOT_RETRY );
		}

		$payment_token = self::get_payment_token( $renewal_order->get_id() );
		if ( ! empty( $do_not_retry ) && $do_not_retry === $payment_token ) {
			$message = __( 'This subscription has previously failed renewal, and no further renewal attempts are allowed.', 'dintero-checkout-for-woocommerce' );

			// Note: the note must be added separately since if the order status is already 'failed', the note will not be added in an update_status() call.
			$renewal_order->add_order_note( $message );

			// No need to save(). This is already done by update_status().
			$renewal_order->update_status( 'failed' );

			self::add_order_note( $subscriptions, $message );
			return;
		}

		$initiate_payment = Dintero()->api->sessions_pay( $renewal_order->get_id() );
		if ( is_wp_error( $initiate_payment ) ) {
			$renewal_order->add_order_note( dintero_retrieve_error_message( $initiate_payment ) );
			$renewal_order->update_status( 'failed' );
			return;
		}

		if ( 'FAILED' === $initiate_payment['status'] ) {
			$message = __( 'The renewal was rejected by Dintero. No further renewal attempts are allowed.', 'dintero-checkout-for-woocommerce' );

			// Store the payment token that shouldn't be allowed for renewal.
			$renewal_order->update_meta_data( self::DO_NOT_RETRY, $payment_token );
			$renewal_order->add_order_note( $message );
			$renewal_order->update_status( 'failed' );

			// Since a renewal order can have multiple subscriptions, we must add the note to each subscription.
			foreach ( $subscriptions as $subscription ) {
				$subscription->add_order_note( $message );
				$subscription->update_meta_data( self::DO_NOT_RETRY, $payment_token );
				$subscription->save();
			}

			return;
		}

		$payment_token = self::get_payment_token_from_response( $initiate_payment );
		if ( empty( $payment_token ) ) {
			$renewal_order->update_status( 'failed', __( 'The payment token could not be retrieved.', 'dintero-checkout-for-woocommerce' ) );
			return;
		}

		$success_message = sprintf(
			/* translators: %s: subscription id */
			__( 'Subscription renewal was made successfully via Dintero Checkout. Payment token: %s', 'dintero-checkout-for-woocommerce' ),
			$payment_token
		);
		$renewal_order->add_order_note( $success_message );

		$dintero_order_id = wc_get_var( $initiate_payment['id'] );
		foreach ( $subscriptions as $subscription ) {
			if ( isset( $dintero_order_id ) ) {
				$subscription->payment_complete( $dintero_order_id );
				$subscription->add_order_note( $success_message );
				$subscription->save();
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
		if ( $parent_order->get_meta( '_wc_dintero_shipping_id' ) ) {
			$renewal_order->update_meta_data( '_wc_dintero_shipping_id', $parent_order->get_meta( '_wc_dintero_shipping_id' ) );
			$renewal_order->save();
		}

		return $renewal_order;
	}

	/**
	 * Cancel the customer token to prevent further payments using the token.
	 *
	 * Note: When changing payment method, WC Subscriptions will cancel the subscription with existing payment gateway (which triggers this functions), and create a new one. Thus the new subscription must generate a new customer token.
	 *
	 * @see WC_Subscriptions_Change_Payment_Gateway::update_payment_method
	 *
	 * @param WC_Subscription $subscription The WooCommerce subscription.
	 * @return void
	 */
	public function cancel_scheduled_payment( $subscription ) {
		// Prevent a recursion of this function when we save() to the subscription.
		if ( did_action( 'woocommerce_subscription_cancelled_' . self::GATEWAY_ID ) > 1 ) {
			return;
		}

		// Dintero does not need to handle subscription cancellation.
	}

	/**
	 * Whether the gateway should be available.
	 *
	 * @param bool $is_available Whether the gateway is available.
	 * @return bool
	 */
	public function is_available( $is_available ) {
		// Allow free orders when changing subscription payment method.
		if ( self::is_change_payment_method() ) {
			return true;
		}

		$zero_order = floatval( WC()->cart->total ) === 0.0;
		if ( $zero_order ) {
			if ( ! self::cart_has_subscription() ) {
				return false;
			}

			// Mixed checkout not allowed.
			if ( class_exists( 'WC_Subscriptions_Product' ) ) {
				foreach ( WC()->cart->cart_contents as $key => $item ) {
					if ( ! WC_Subscriptions_Product::is_subscription( $item['data'] ) ) {
						return false;
					}
				}
			}

			// Only allow free orders if the cart contains a subscription (not limited to trial subscription as a subscription can become free if a 100% discount coupon is applied).
			if ( $zero_order ) {
				return true;
			}
		}

		return $is_available;
	}

	/**
	 * Whether the cart needs payment. Since Dintero supports free subscriptions, we must check if the cart contains a subscription.
	 *
	 * @param bool $needs_payment Whether the cart needs payment.
	 * @return bool
	 */
	public function cart_needs_payment( $needs_payment ) {
		if ( 'dintero_checkout' !== WC()->session->chosen_payment_method ) {
			return $needs_payment;
		}

		return $this->is_available( $needs_payment );
	}

	/**
	 * Set required session options when processing subscriptions in the checkout.
	 *
	 * @param array $request The request data.
	 * @return array
	 */
	public function set_session_options( $request ) {
		if ( ! self::cart_has_subscription() ) {
			return $request;
		}

		$body = json_decode( $request['body'], true );

		// Dintero only supports subscriptions with card payments. Required for recurring payments.
		$body['configuration']['payex']['creditcard'] = array(
			'enabled'                => true,
			'generate_payment_token' => true,
		);

		// Use the Profile ID for subscriptions.
		$settings           = get_option( 'woocommerce_dintero_checkout_settings' );
		$body['profile_id'] = wc_get_var( $settings['subscription_profile_id'] );
		if ( empty( $body['profile_id'] ) ) {
			$body['profile_id'] = wc_get_var( $settings['profile_id'] );
		}

		$request['body'] = wp_json_encode( $body );
		return $request;
	}

	/**
	 * Set the session URLs for change payment method request.
	 *
	 * Used for changing payment method.
	 *
	 * @see Dintero_Checkout_Payment_Token
	 *
	 * @param array                          $request The request data.
	 * @param Dintero_Checkout_Payment_Token $instance An instance of the request class.
	 * @return array
	 */
	public function set_subscription_order_redirect_urls( $request, $instance ) {
		if ( ! self::is_change_payment_method() ) {
			return $request;
		}

		$body                                 = json_decode( $request['body'], true );
		$subscription                         = self::get_subscription( $instance->arguments()['order_id'] );
		$body['session']['url']['return_url'] = add_query_arg( 'dwc_redirect', 'subscription', $subscription->get_view_order_url() );
		$request['body']                      = wp_json_encode( $body );

		return $request;
	}

	/**
	 * Handle the redirect from the change payment method page.
	 *
	 * @param int $subscription_id The subscription ID.
	 * @return void
	 */
	public function handle_redirect_from_change_payment_method( $subscription_id ) {
		// We need to distinguish between whether the customer has changed payment method or is viewing a subscription as this endpoint will be triggered in either case.
		if ( wc_get_var( $_GET['dwc_redirect'], '' ) !== 'subscription' ) {
			return;
		}

		$subscription = self::get_subscription( $subscription_id );
		if ( 'dintero_checkout' !== $subscription->get_payment_method() ) {
			return;
		}

		if ( isset( $_REQUEST['error'] ) ) {
			$reason = sanitize_text_field( wp_unslash( $_REQUEST['error'] ) );

			if ( 'cancelled' === $reason ) {
				$message = __( 'Change payment method to Dintero for subscription failed. Customer cancelled the checkout payment.', 'dintero-checkout-for-woocommerce' );
			} elseif ( 'authorization' === $reason ) {
				$message = __( 'Change payment method to Dintero for subscription failed. Customer did not authorize the payment.', 'dintero-checkout-for-woocommerce' );
			} else {
				$message = __( 'Change payment method to Dintero for subscription failed. An error may have occurred during transaction processing or was rejected by Dintero.', 'dintero-checkout-for-woocommerce' );
			}

			// Woo will always consider a redirect back to the view order URL as a successful payment method change.
			// And a notice saying, 'Payment method updated.'  will appear. We must therefore indicate otherwise.
			if ( function_exists( 'wc_print_notice' ) ) {
				wc_print_notice( $message, 'error' );
			}

			$subscription->add_order_note( $message );
			$subscription->save();
			return;
		}

		$transaction_id = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_SPECIAL_CHARS );
		$response       = Dintero()->api->get_order( $transaction_id, array( 'includes' => 'card.payment_token' ) );
		if ( is_wp_error( $response ) ) {
			$message = sprintf(
			/* translators: Error message. */
				__( 'Failed to create payment token. Reason: %s', 'dintero-checkout-for-woocommerce' ),
				$response->get_error_message()
			);
		} else {
			$payment_token = self::get_payment_token_from_response( $response );
			$message       = sprintf(
			/* translators: Payment token. */
				__( 'Payment token created: %s', 'dintero-checkout-for-woocommerce' ),
				$payment_token
			);

			self::save_payment_token( $subscription_id, $payment_token );
		}

		$subscription->add_order_note( $message );
		$subscription->save();
	}

	public static function add_order_note( $subscriptions, $note ) {
		if ( ! is_array( $subscriptions ) ) {
			$subscriptions->add_order_note( $note );
			$subscriptions->save();

			return;
		}

		foreach ( $subscriptions as $subscription ) {
			$subscription->add_order_note( $note );
			$subscription->save();
		}
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
	 * Retrieve the payment token from a Dintero order (API response).
	 *
	 * @param array $dintero_order The Dintero order.
	 * @return string|false The payment token or false if none is found.
	 */
	public static function get_payment_token_from_response( $dintero_order ) {
		$payment_token = wc_get_var( $dintero_order['card']['payment_token'], false );
		if ( empty( $payment_token ) ) {

			// On renewal, the payment token is stored in the customer property.
			$payment_token = wc_get_var( $dintero_order['customer']['tokens']['payex.creditcard']['payment_token'], false );
		}

		return $payment_token;
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
	 * Whether the cart contains only free trial subscriptions.
	 *
	 * If invoked from anywhere but the checkout page, this will return FALSE.
	 *
	 * @return boolean
	 */
	public static function cart_has_only_free_trial() {
		if ( ! is_checkout() ) {
			return false;
		}

		return ( class_exists( 'WC_Subscriptions_Cart' ) ) ? WC_Subscriptions_Cart::all_cart_items_have_free_trial() : false;
	}

	/**
	 * Retrieve a WC_Subscription from order ID.
	 *
	 * @param int $order_id  Woo order ID.
	 * @return bool|WC_Subscription The subscription object, or false if it cannot be found.
	 */
	public static function get_subscription( $order_id ) {
		return ! function_exists( 'wcs_get_subscription' ) ? false : wcs_get_subscription( $order_id );
	}

	/**
	 * Add Dintero redirect payment page as allowed external url for wp_safe_redirect.
	 * We do this because WooCommerce Subscriptions use wp_safe_redirect when processing a payment method change request (from v5.1.0).
	 *
	 * @param array $hosts Domains that are allowed when wp_safe_redirect is used.
	 * @return array
	 */
	public function extend_allowed_domains_list( $hosts ) {
		$hosts[] = 'checkout.dintero.com';
		return $hosts;
	}

	/**
	 * Save the payment token to the subscription when the merchant updates the order from the subscription page.
	 *
	 * @param int   $order_id The Woo order ID.
	 * @param array $items The posted data (includes even the data that was not updated).
	 * @return bool True if the payment token was updated, false otherwise.
	 */
	public function subscription_updated_from_order_page( $order_id, $items ) {
		$order = wc_get_order( $order_id );

		// The action hook woocommerce_saved_order_items is triggered for all order updates, so we must check if the payment method is Dintero.
		if ( 'dintero_checkout' !== $order->get_payment_method() ) {
			return false;
		}

		// Are we on the subscription page?
		if ( 'shop_subscription' === $order->get_type() ) {
			$token_key = self::PAYMENT_TOKEN;

			// Did the customer update the subscription's payment token?
			$payment_token  = wc_get_var( $items[ $token_key ] );
			$existing_token = $order->get_meta( $token_key );
			if ( ! empty( $payment_token ) && $existing_token !== $payment_token ) {
				$order->update_meta_data( $token_key, $payment_token );
				$order->add_order_note(
					sprintf(
					// translators: 1: User name, 2: Existing token, 3: New token.
						__( '%1$s updated the subscription payment token from "%2$s" to "%3$s".', 'dintero-checkout-for-woocommerce' ),
						ucfirst( wp_get_current_user()->display_name ),
						$existing_token,
						$payment_token
					)
				);
				$order->save();

				// If the recurring token was changed, we can assume the merchant didn't update the subscription as that would require a recurring token which as has now been modified, but not yet saved.
				return true;
			}
		}

		return true;
	}

	/**
	 * Shows the recurring token for the order.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return void
	 */
	public function show_payment_token( $order ) {
		$payment_token = $order->get_meta( self::PAYMENT_TOKEN );
		if ( 'shop_subscription' === $order->get_type() ) {
			?>
			<div class="order_data_column" style="clear:both; float:none; width:100%;">
				<div class="address">
					<p>
						<strong><?php echo esc_html( 'Dintero payment token' ); ?>:</strong><?php echo esc_html( $payment_token ); ?>
					</p>
				</div>
				<div class="edit_address">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'            => self::PAYMENT_TOKEN,
						'label'         => __( 'Dintero payment token', 'dintero-checkout-for-woocommerce' ),
						'wrapper_class' => '_billing_company_field',
						'value'         => $payment_token,
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
