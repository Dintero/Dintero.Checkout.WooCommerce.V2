<?php
/**
 * Class for handling cart items.
 *
 * @package Dintero_Checkout/Classes/Requests/Helpers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling cart items.
 */
class Dintero_Checkout_Cart {

	/**
	 * Create the Dintero expected order object.
	 *
	 * @param int $order_id WooCommerce order id.
	 * @return array An associative array representation of Dintero expected order object.
	 */
	public function order( $order_id ) {
		$order = wc_get_order( $order_id );
		return array(
			'url'        => array(
				'return_url' => $order->get_checkout_order_received_url(),
			),
			'order'      => $this->order_lines( $order ),
			'profile_id' => get_option( 'woocommerce_dintero_checkout_settings' )['profile_id'],
		);
	}

	/**
	 * The content of the entire cart (including coupons and shipping).
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array An associative array of the cart items.
	 */
	public function order_lines( $order ) {
		$order_lines = array(
			'amount'               => $order->get_total() * 100,
			'currency'             => get_woocommerce_currency(),
			'merchant_reference'   => $order->get_order_number(),
			'vat_amount'           => $order->get_total_tax() * 100,
			'merchant_reference_2' => strval( $order->get_id() ),
			'billing_address'      => $this->billing_address( $order ),
			'items'                => $this->order_items( $order ),
		);

		if ( WC()->cart->needs_shipping() ) {
			$order_lines['shipping_address'] = $this->shipping_address( $order );
			$order_lines['shipping_option']  = $this->shipping_option( $order );
		}

		return $order_lines;
	}

	/**
	 * Retrieve all the cart items (excluding shipping).
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array An associative array representing the cart items (without shipping options).
	 */
	private function order_items( $order ) {
		$order_items = array();

		$cart = WC()->cart->get_cart();

		// The line_id is used to uniquely identify each item (local to this order).
		$line_id = 0;
		foreach ( $cart as $item ) {
			$product = ( $item['variation_id'] ) ? wc_get_product( $item['variation_id'] ) : wc_get_product( $item['product_id'] );

			$order_items[] = array(
				'id'          => $product->get_sku() ?? $product->get_id(),
				'line_id'     => strval( $line_id++ ),
				'description' => $product->get_description(),
				'quantity'    => $item['quantity'],
				'amount'      => $item['line_total'] * 100,
			);
		}

		return $order_items;
	}

	/**
	 * Retrieve all the shipping options.
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array An associative array representing the selected shipping option.
	 */
	private function shipping_option( $order ) {

		$selected_option_id       = WC()->session->get( 'chosen_shipping_methods' )[0];
		$selected_shipping_option = WC()->shipping->get_packages()['0']['rates'][ $selected_option_id ];

		$shipping_option = array(
			'id'       => $selected_option_id,
			'line_id'  => $selected_option_id,
			'amount'   => $selected_shipping_option->get_cost() * 100,
			'operator' => '',
			'title'    => $selected_shipping_option->get_label(),
		);

		return $shipping_option;
	}

	/**
	 * Retrieve the customer's billing address.
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array An associative array representing the billing address.
	 */
	private function billing_address( $order ) {
		$billing_address = array(
			'first_name'     => $order->get_billing_first_name(),
			'last_name'      => $order->get_billing_last_name(),
			'address_line'   => $order->get_billing_address_1(),
			'address_line_2' => $order->get_billing_address_2(),
			'business_name'  => $order->get_billing_company(),
			'postal_code'    => $order->get_billing_postcode(),
			'postal_place'   => $order->get_billing_city(),
			'country'        => $order->get_billing_country(),
			'phone_number'   => $order->get_billing_phone(),
			'email'          => $order->get_billing_email(),
		);

		foreach ( $billing_address as $key => $information ) {
			$billing_address[ $key ] = sanitize_text_field( (string) $information );
		}

		return $billing_address;
	}

	/**
	 * Retrieve the customer's shipping address.
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array An associative array representing the shipping address.
	 */
	private function shipping_address( $order ) {
		$shipping_address = array(
			'first_name'     => $order->get_shipping_first_name(),
			'last_name'      => $order->get_shipping_last_name(),
			'address_line'   => $order->get_shipping_address_1(),
			'address_line_2' => $order->get_shipping_address_2(),
			'business_name'  => $order->get_shipping_company(),
			'postal_code'    => $order->get_shipping_postcode(),
			'postal_place'   => $order->get_shipping_city(),
			'country'        => $order->get_shipping_country(),
			'email'          => $order->get_billing_email(),
		);

		// Check if a shipping phone number exist.
		$phone                            = $order->get_shipping_phone();
		$shipping_address['phone_number'] = ( ! empty( $phone ) ) ? $phone : $order->get_billing_phone();

		foreach ( $shipping_address as $key => $information ) {
			$billing_address[ $key ] = sanitize_text_field( (string) $information );
		}

		return $shipping_address;
	}
}
