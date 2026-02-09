<?php //phpcs:ignore
/**
 * Class for Dintero Checkout Gateway.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( class_exists( 'WC_Payment_Gateway' ) ) {

	/**
	 * Class Dintero_Checkout_Gateway
	 */
	class Dintero_Checkout_Gateway extends WC_Payment_Gateway {

		/**
		 * Whether test mode is enabled.
		 *
		 * @var boolean
		 */
		public $test_mode;
		/**
		 * Whether logging to WC log is enabled.
		 *
		 * @var boolean
		 */
		public $logging;
		/**
		 * Checkout form factor.
		 *
		 * @var string
		 */
		public $form_factor;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			$this->id                 = 'dintero_checkout';
			$this->method_title       = __( 'Dintero Checkout', 'dintero-checkout-for-woocommerce' );
			$this->method_description = __( 'Dintero Checkout', 'dintero-checkout-for-woocommerce' );
			$this->supports           = apply_filters(
				$this->id . '_supports',
				array(
					'products',
					'refunds',
					'subscriptions',
					'subscription_cancellation',
					'subscription_suspension',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
					'subscription_payment_method_change',
					'subscription_payment_method_change_customer',
					'subscription_payment_method_change_admin',
					'multiple_subscriptions',
				)
			);
			$this->init_form_fields();
			$this->init_settings();
			$this->title       = $this->get_option( 'redirect_title' );
			$this->description = $this->get_option( 'redirect_description' );
			$this->enabled     = $this->get_option( 'enabled' );
			$this->test_mode   = 'yes' === $this->get_option( 'test_mode' );
			$this->logging     = 'yes' === $this->get_option( 'logging' );
			$this->has_fields  = false;

			// Migrate existing settings to use the new "checkout_flow" setting.
			if ( ! isset( $this->settings['checkout_flow'] ) ) {
				$checkout_type = $this->settings['checkout_type'] ?? 'express'; // embedded|express.
				$form_factor   = $this->settings['form_factor'] ?? 'redirect'; // express|redirect.
				$popout        = $this->settings['checkout_popout'] ?? 'no'; // yes|no.

				if ( 'embedded' === $form_factor ) {
					$display = 'yes' === $popout ? 'popout' : 'embedded';
					$flow    = "{$checkout_type}_{$display}";
				} else {
					$flow = 'checkout_redirect';
				}

				$this->update_option( 'checkout_flow', $flow );
				$this->settings['checkout_flow'] = $flow;
			}

			add_action(
				'woocommerce_update_options_payment_gateways_' . $this->id,
				array(
					$this,
					'process_admin_options',
				)
			);

			/**
			 * Adds cart_item_key to the order item's meta data to be used as a unique line id. This applies to both embedded and redirect flow.
			 */
			add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'create_order_line_item' ), 10, 2 );
			add_action( 'woocommerce_checkout_create_order_shipping_item', array( $this, 'create_order_shipping_item' ), 10, 3 );

			/**
			 * By default, a custom meta data will be displayed on the order page. Since the meta data _dintero_checkout_line_id is an implementation detail,
			 * we should hide it on the order page. The meta key has to be prefixed with an underscore (_) to also hide the meta data beyond the order page (e.g., in emails, PDF documents).
			 */
			add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hidden_order_itemmeta' ) );
			// Add billing organization number to the order admin page.
			add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'add_billing_org_nr' ) );
		}

		/**
		 * Add line ID to uniquely identify order item.
		 *
		 * @param WC_Order_Item_Product $item The WC order item.
		 * @param string                $cart_item_key The order item's unique key.
		 *
		 * @return void
		 */
		public function create_order_line_item( $item, $cart_item_key ) {
			$item->add_meta_data( '_dintero_checkout_line_id', $cart_item_key, true );
		}

		/**
		 * Add line ID to uniquely identify order shipping item.
		 *
		 * @hook woocommerce_checkout_create_order_shipping_item
		 *
		 * @param WC_Order_Item_Shipping $item The WC order shipping item.
		 * @param string                 $package_key The shipping package key.
		 * @param array                  $package The shipping package.
		 *
		 * @return void
		 */
		public function create_order_shipping_item( $item, $package_key, $package ) {
			$shipping_id = WC()->session->get( 'chosen_shipping_methods' )[ $package_key ];
			if ( isset( $package['seller_id'] ) ) {
				$shipping_id .= ":{$package['seller_id']}";
			}

			$item->add_meta_data( '_dintero_checkout_line_id', WC()->cart->generate_cart_id( $shipping_id ) );
		}

		/**
		 * Hide order line ID on the order page.
		 *
		 * @param array $hidden_meta The itemmeta.
		 * @return array
		 */
		public function hidden_order_itemmeta( $hidden_meta ) {
			$hidden_meta[] = '_dintero_checkout_line_id';
			return $hidden_meta;
		}

		/**
		 * Initialize settings fields.
		 *
		 * @return void
		 */
		public function init_form_fields() {
			$this->form_fields = Dintero_Settings_Fields::setting_fields();

			add_filter( 'woocommerce_order_button_text', array( 'Dintero_Settings_Fields', 'order_button_text' ) );
			add_filter( 'woocommerce_pay_order_button_text', array( 'Dintero_Settings_Fields', 'order_button_text' ) );
			add_action( 'update_option_woocommerce_dintero_checkout_settings', array( 'Dintero_Settings_Fields', 'maybe_update_access_token' ), 10, 2 );
		}


		/**
		 * Add payment gateway icon on the checkout page.
		 *
		 * @return string
		 */
		public function get_icon() {
			return '<img class="dintero-logos" src="' . esc_attr( dintero_get_brand_image_url() ) . '" style="max-width: 90%" alt="Dintero logo" />';
		}

		/**
		 * Check if payment method should be available.
		 *
		 * @return boolean
		 */
		public function is_available() {
			return apply_filters( 'dintero_checkout_is_available', $this->check_availability(), $this );
		}

		/**
		 * Check if the gateway should be available.
		 *
		 * This function is extracted to create the 'dintero_checkout_is_available' filter.
		 *
		 * @return bool
		 */
		private function check_availability() {
			if ( ! wc_string_to_bool( $this->enabled ) ) {
				return false;
			}

			if ( is_wc_endpoint_url( 'order-pay' ) ) {
				$order_id = absint( get_query_var( 'order-pay', 0 ) );
				$order    = wc_get_order( $order_id );
				if ( empty( $order ) || 0.0 === floatval( $order->get_total() ) ) {
					return false;
				}

				return true;
			}

			if ( ! isset( WC()->cart ) ) {
				return false;
			}

			return 0.0 < floatval( WC()->cart->total );
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id WooCommerced order id.
		 * @return array An associative array containing the success status and redirect URl.
		 */
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			$shipping_line_id = WC()->session->get( 'dintero_shipping_line_id' );
			if ( ! empty( $shipping_line_id ) ) {
				$order->update_meta_data( '_dintero_shipping_line_id', $shipping_line_id );
				$order->save();
			}

			if ( Dintero_Checkout_Subscription::is_change_payment_method() ) {
				return $this->process_redirect_payment( $order );
			}

			/* For all form factors, redirect is used for order-pay since the cart object (used for embedded) is not available. */
			if ( dwc_is_embedded( $this->settings ) && ! is_wc_endpoint_url( 'order-pay' ) ) {
				$result = $this->process_embedded_payment( $order );
			} else {
				$result = $this->process_redirect_payment( $order );
			}

			$result['order_id'] = $order->get_id();
			return $result;
		}

		/**
		 * Process an embedded payment method.
		 *
		 * @param WC_Order $order The Woo order.
		 * @return array
		 */
		public function process_embedded_payment( $order ) {
			$reference = WC()->session->get( 'dintero_merchant_reference' );

			if ( empty( $reference ) ) {
				$session_id = WC()->session->get( 'dintero_checkout_session_id' );
				Dintero_Checkout_Logger::log( 'PROCESS PAYMENT EMBEDDED ERROR [reference]: Could not get a merchant reference from the session for order id: ' . $order->get_id() . ' session id: ' . $session_id );
				return array(
					'result' => 'error',
				);
			}

			$order->update_meta_data( '_dintero_merchant_reference', $reference );
			$order->save();

			return array(
				'result' => 'success',
			);
		}

		/**
		 * Process a redirect payment.
		 *
		 * @param WC_Order $order The Woo Order.
		 * @return array
		 */
		public function process_redirect_payment( $order ) {
			$session = 0.0 === floatval( $order->get_total() ) ? Dintero()->api->create_payment_token( $order->get_id() ) : Dintero()->api->create_session( $order->get_id() );

			$reference = WC()->session->get( 'dintero_merchant_reference' );

			if ( empty( $reference ) ) {
				$session_id = WC()->session->get( 'dintero_checkout_session_id' );
				Dintero_Checkout_Logger::log( 'PROCESS PAYMENT REDIRECT ERROR [reference]: Could not get a merchant reference from the session for order id: ' . $order->get_id() . ' session id: ' . $session_id );
				return array(
					'result' => 'error',
				);
			}

			$order->update_meta_data( '_dintero_merchant_reference', $reference );
			$order->save();

			if ( is_wp_error( $session ) ) {
				return array(
					'result' => 'error',
				);
			}

			$order->add_order_note( __( 'Customer redirected to Dintero payment page.', 'dintero-checkout-for-woocommerce' ) );
			$order->save();

			return array(
				'result'   => 'success',
				'redirect' => $session['url'],
				'order_id' => $order->get_id(),
			);
		}

		/**
		 * Process the refund request.
		 *
		 * @param int    $order_id The WooCommerce order id.
		 * @param float  $amount The amount to refund.
		 * @param string $reason The reason for the refund.
		 * @return boolean|null|WP_Error
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order        = wc_get_order( $order_id );
			$capture_date = $order->get_meta( '_wc_dintero_captured' );

			if ( empty( $capture_date ) ) {
				return new WP_Error(
					'dintero_refund_not_supported',
					__(
						'Refunds are only possible for orders that have been completed. If you have an order that has not been completed and needs to be canceled, set the order status to "Canceled".',
						'dintero-checkout-for-woocommerce'
					)
				);

			}

			return Dintero()->order_management->refund_order( $order_id, $reason );
		}

		/**
		 * Maybe adds the billing org number to the address in an order.
		 *
		 * @param WC_Order $order The WooCommerce order.
		 * @return void
		 */
		public function add_billing_org_nr( $order ) {
			if ( $this->id === $order->get_payment_method() ) {
				$billing_org_nr = $order->get_meta( '_billing_org_nr', true );
				if ( $billing_org_nr ) {
					?>
					<p>
						<strong>
							<?php esc_html_e( 'Organization number:', 'dintero-checkout-for-woocommerce' ); ?>
						</strong>
						<?php echo esc_html( $billing_org_nr ); ?>
					</p>
					<?php
				}
			}
		}
	}
}
