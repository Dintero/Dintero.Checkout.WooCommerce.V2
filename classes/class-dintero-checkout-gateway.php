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
		 * Whether or not test mode is enabled.
		 *
		 * @var bool
		 */
		private $test_mode;
		/**
		 * Whether or not logging is enabled.
		 *
		 * @var bool
		 */
		private $logging;


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

			/**
			 * By default, a custom meta data will be displayed on the order page. Since the meta data _dintero_checkout_line_id is an implementation detail,
			 * we should hide it on the order page. The meta key has to be prefixed with an underscore (_) to also hide the meta data beyond the order page (e.g., in emails, PDF documents).
			 */
			add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hidden_order_itemmeta' ) );
		}

		/**
		 * Add line ID to uniquely identify order item.
		 *
		 * @param WC_Order_Item_Product $item
		 * @param string                $cart_item_key
		 *
		 * @return void
		 */
		public function create_order_line_item( $item, $cart_item_key ) {
			$item->add_meta_data( '_dintero_checkout_line_id', $cart_item_key, true );
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
			return '<img src="' . esc_attr( dintero_get_brand_image_url() ) . '" style="max-width: 90%" alt="Dintero logo" />';
		}

		/**
		 * Check if payment method should be available.
		 *
		 * @return boolean
		 */
		public function is_available() {
			if ( 'yes' !== $this->enabled ) {
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

			/* For all form factors, redirect is used for order-pay since the cart object (used for embedded) is not available. */
			if ( dwc_is_embedded( $this->settings ) && ! is_wc_endpoint_url( 'order-pay' ) ) {
				$result = $this->process_embedded_payment( $order_id );
			} else {
				$result = $this->process_redirect_payment( $order_id );
			}
			return $result;
		}

		/**
		 * Process an embedded payment method.
		 *
		 * @param int $order_id The WooCommerce Order ID.
		 * @return array
		 */
		public function process_embedded_payment( $order_id ) {
			$order     = wc_get_order( $order_id );
			$reference = WC()->session->get( 'dintero_merchant_reference' );
			$order->update_meta_data( '_dintero_merchant_reference', $reference );
			$order->add_order_note( __( 'Dintero order created with reference ', 'dintero-checkout-for-woocommerce' ) . $reference );
			$order->save();

			return array(
				'result' => 'success',
			);
		}

		/**
		 * Process a redirect payment.
		 *
		 * @param int $order_id The WooCommerce Order ID.
		 * @return array
		 */
		public function process_redirect_payment( $order_id ) {
			$order     = wc_get_order( $order_id );
			$session   = Dintero()->api->create_session( $order_id );
			$reference = WC()->session->get( 'dintero_merchant_reference' );
			$order->update_meta_data( '_dintero_merchant_reference', $reference );
			$order->save();

			if ( is_wp_error( $session ) ) {
				return array(
					'result' => 'error',
				);
			}

			$order->add_order_note( __( 'Customer redirected to Dintero payment page.', 'dintero-checkout-for-woocommerce' ) );

			return array(
				'result'   => 'success',
				'redirect' => $session['url'],
			);
		}

		/**
		 * Process the refund request.
		 *
		 * @param int    $order_id The WooCommerce order id.
		 * @param float  $amount The amount to refund.
		 * @param string $reason The reason for the refund.
		 * @return array|WP_Error
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			return Dintero()->order_management->refund_order( $order_id, $reason );
		}
	}
}
