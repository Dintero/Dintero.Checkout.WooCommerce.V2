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
			$this->form_factor = $this->get_option( 'form_factor' );
			$this->has_fields  = false;
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
			add_action(
				'woocommerce_checkout_create_order_line_item',
				function( $item, $cart_item_key ) {
					$item->update_meta_data( '_dintero_checkout_line_id', $cart_item_key );
				},
				10,
				2
			);

			/**
			 * By default, a custom meta data will be displayed on the order page. Since the meta data _dintero_checkout_line_id is an implementation detail,
			 * we should hide it on the order page. The meta key has to be prefixed with an underscore (_) to also hide the meta data beyond the order page (e.g., in emails, PDF documents).
			 */
			add_filter(
				'woocommerce_hidden_order_itemmeta',
				function( $hidden_meta ) {
					$hidden_meta[] = '_dintero_checkout_line_id';
					return $hidden_meta;
				}
			);
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
			return '<a href="https://www.dintero.com" target="_blank" title="' . dintero_keyword_backlinks() . '"><img src="' . esc_attr( dintero_get_brand_image_url() ) . '" style="max-width: 90%" alt="' . dintero_alt_backlinks() . '" /></a>';
		}

		/**
		 * Check if payment method should be available.
		 *
		 * @return boolean
		 */
		public function is_available() {
			return ( 'yes' === $this->enabled );
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id WooCommerced order id.
		 * @return array An associative array containing the success status and redirect URl.
		 */
		public function process_payment( $order_id ) {

			/* For all form factors, redirect is used for order-pay since the cart object (used for embedded) is not available. */
			if ( 'embedded' === $this->form_factor && ! is_wc_endpoint_url( 'order-pay' ) ) {
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
			update_post_meta( $order_id, '_dintero_merchant_reference', $reference );
			$order->add_order_note( __( 'Dintero Order created with reference ', 'dintero-checkout-for-woocommerce' ) . $reference );

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
			update_post_meta( $order_id, '_dintero_merchant_reference', $reference );

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
