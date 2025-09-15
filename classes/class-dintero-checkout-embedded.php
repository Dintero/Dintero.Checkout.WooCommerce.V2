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
			add_filter( 'woocommerce_shipping_packages', array( $this, 'maybe_set_selected_pickup_point' ) );
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

		// If Dintero is the chosen gateway while it is unavailable, reload the checkout. This can happen if the total a non-zero total amount becomes zero.
		$chosen_gateway     = WC()->session->chosen_payment_method;
		$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
		if ( 'dintero_checkout' === $chosen_gateway && ! array_key_exists( 'dintero_checkout', $available_gateways ) ) {
			WC()->session->reload_checkout = true;
			return;
		}

		if ( 'dintero_checkout' !== $chosen_gateway ) {
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

	/**
	 * Maybe set the selected pickup point in the shipping method.
	 *
	 * @param array $packages The shipping packages.
	 * @return array
	 */
	public function maybe_set_selected_pickup_point( $packages ) {
		$merchant_reference = WC()->session->get( 'dintero_merchant_reference' );
		$chosen_shipping    = get_transient( "dintero_shipping_data_{$merchant_reference}" );

		if ( empty( $chosen_shipping ) ) {
			return $packages;
		}

		$is_pickup = 'pick_up' === $chosen_shipping['delivery_method'];
		if ( ! $is_pickup ) {
			return $packages;
		}

		// Loop each package.
		foreach ( $packages as $package ) {
			/**
			 * Shipping rate object.
			 *
			 * @var WC_Shipping_Rate $rate
			 */
			foreach ( $package['rates'] as $rate ) {
				if ( $chosen_shipping['id'] !== $rate->get_id() ) {
					continue;
				}

				// Find the shipping rate that has the following operator product ID.
				$id           = $chosen_shipping['operator_product_id'];
				$pickup_point = Dintero()->pickup_points()->get_pickup_point_from_rate_by_id( $rate, $id );

				if ( empty( $pickup_point ) ) {
					continue;
				}

				Dintero()->pickup_points()->save_selected_pickup_point_to_rate( $rate, $pickup_point );
				break;
			}
		}

		return $packages;
	}
}
new Dintero_Checkout_Embedded();
