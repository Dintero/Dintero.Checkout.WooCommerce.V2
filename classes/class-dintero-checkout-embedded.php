<?php
/**
 * Class for managing actions during the checkout process.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to manage actions during the checkout process for the embedded flow.
 */
class Dintero_Checkout_Embedded {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( dwc_is_embedded( $settings ) ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'add_shipping_data_input' ) );
			add_action( 'woocommerce_before_calculate_totals', array( $this, 'update_shipping_method' ), 1 );
			add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_dintero_checkout_session' ), 9999 );
			add_action( 'woocommerce_checkout_update_order_review', array( $this, 'update_wc_customer' ) );
		}
	}

	/**
	 * Update the WC_Customer with the entered billing and shipping data.
	 *
	 * By default, only the fields required for calculating shipping are updated.
	 *
	 * @param  string $raw_post_data The checkout form data.
	 * @return void
	 */
	public function update_wc_customer( $raw_post_data ) {
		parse_str( $raw_post_data, $post_data );
		$post_data = array_filter(
			wc_clean( wp_unslash( $post_data ) ),
			function ( $value ) {
				return ! empty( $value );
			}
		);

		isset( $post_data['billing_first_name'] ) && WC()->customer->set_billing_first_name( $post_data['billing_first_name'] );
		isset( $post_data['billing_last_name'] ) && WC()->customer->set_billing_last_name( $post_data['billing_last_name'] );
		isset( $post_data['billing_company'] ) && WC()->customer->set_billing_company( $post_data['billing_company'] );
		isset( $post_data['billing_phone'] ) && WC()->customer->set_billing_phone( $post_data['billing_phone'] );
		isset( $post_data['billing_email'] ) && WC()->customer->set_billing_email( $post_data['billing_email'] );

		if ( isset( $post_data['ship_to_different_address'] ) ) {
			isset( $post_data['shipping_first_name'] ) && WC()->customer->set_shipping_first_name( $post_data['shipping_first_name'] );
			isset( $post_data['shipping_last_name'] ) && WC()->customer->set_shipping_last_name( $post_data['shipping_last_name'] );
			isset( $post_data['shipping_company'] ) && WC()->customer->set_shipping_company( $post_data['shipping_company'] );

			// Since WC 5.6.0.
			if ( method_exists( WC()->customer, 'set_shipping_phone' ) && isset( $post_data['shipping_phone'] ) ) {
				WC()->customer->set_shipping_phone( $post_data['shipping_phone'] );
			}
		}
	}

	/**
	 * Add a hidden input field for the shipping data from Qliro One.
	 *
	 * @param array $fields The WooCommerce checkout fields.
	 * @return array
	 */
	public function add_shipping_data_input( $fields ) {
		$default = '';

		if ( is_checkout() ) {
			$merchant_reference = WC()->session->get( 'dintero_merchant_reference' );
			$shipping_data      = get_transient( 'dintero_shipping_data_' . $merchant_reference );
			$default            = wp_json_encode( $shipping_data );
		}

		$fields['billing']['dintero_shipping_data'] = array(
			'type'    => 'hidden',
			'class'   => array( 'dintero_shipping_data' ),
			'default' => $default,
		);

		return $fields;
	}

	/**
	 * Maybe updates the shipping method before calculations if its been selected in the iframe.
	 *
	 * @return void
	 */
	public function update_shipping_method() {
		if ( ! is_checkout() || 'dintero_checkout' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}

		$settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( ! isset( $settings['express_shipping_in_iframe'] ) || 'yes' !== $settings['express_shipping_in_iframe'] ) {
			return;
		}

		if ( isset( $_POST['post_data'] ) ) { // phpcs:ignore
			parse_str( $_POST['post_data'], $post_data ); // phpcs:ignore
			if ( isset( $post_data['dintero_shipping_data'] ) ) {
				WC()->session->set( 'dintero_shipping_data', $post_data['dintero_shipping_data'] );
				WC()->session->set( 'dintero_shipping_data_set', true );
				$data = json_decode( $post_data['dintero_shipping_data'], true );
				dintero_update_wc_shipping( $data );
			}
		}
	}

	/**
	 * Update the Dintero checkout session after calculation from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_dintero_checkout_session() {
		// We can only do this during AJAX, so if it is not an ajax call, we should just bail.
		if ( ! is_checkout() || ! wp_doing_ajax() ) {
			return;
		}

		// Only when its an actual AJAX request to update the order review (this is when update_checkout is triggered).
		$ajax = filter_input( INPUT_GET, 'wc-ajax', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		if ( 'update_order_review' !== $ajax ) {
			return;
		}

		if ( 'dintero_checkout' !== WC()->session->chosen_payment_method ) {
			return;
		}

		// Reload the page so the standard WooCommerce checkout page will appear.
		if ( ! WC()->cart->needs_payment() ) {
			WC()->session->reload_checkout = true;
		}

		// Check if we have locked the iframe first, if not then this should not happen since it will return an error.
		$raw_post_data = filter_input( INPUT_POST, 'post_data', FILTER_SANITIZE_URL );
		parse_str( $raw_post_data, $post_data );
		if ( ! isset( $post_data['dintero_locked'] ) ) {
			return;
		}

		// Only if we have a current session active.
		$session_id = WC()->session->get( 'dintero_checkout_session_id' );
		if ( empty( $session_id ) ) {
			return;
		}

		Dintero()->api->update_checkout_session( $session_id );
	}
}
new Dintero_Checkout_Embedded();
