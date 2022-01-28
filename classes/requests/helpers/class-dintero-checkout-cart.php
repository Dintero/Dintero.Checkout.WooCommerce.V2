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
			'amount'               => intval( number_format( $order->get_total() * 100, 0, '', '' ) ),
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

		if ( ! empty( WC()->cart->get_coupons() ) ) {
			$discount                      = $this->discount_items();
			$order_lines['discount_lines'] = $discount['discount_lines'];
			$order_lines['discount_codes'] = $discount['discount_codes'];
		}

		if ( ! empty( WC()->cart->get_fees() ) ) {
			$order_lines['items'] = array_merge( $order_lines['items'], $this->fee_items( $order ) );
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

			$order_item = array(
				'id'          => $product->get_sku() ?? $product->get_id(),
				'line_id'     => strval( $line_id++ ),
				'description' => $product->get_description(),
				'quantity'    => $item['quantity'],
				'amount'      => intval( number_format( $item['line_total'] * 100, 0, '', '' ) ),
				'vat_amount'  => intval( number_format( $item['line_tax'] * 100, 0, '', '' ) ),
				'vat'         => ( $product->is_taxable() ) ? reset( WC_Tax::get_base_tax_rates( $product->get_tax_class() ) )['rate'] : 0,
			);

			$order_item['amount'] += $order_item['vat_amount'];
			$order_items[]         = $order_item;
		}

		return $order_items;
	}

	/**
	 * Retrieve all the discount items.
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array An associative array representing the discount items and codes.
	 */
	private function discount_items() {
		$discount_items = array();
		$discount_codes = array();

		// The line_id is used to uniquely identify each item (local to this order).
		$line_id = 0;
		foreach ( WC()->cart->get_coupons() as $coupon_code => $coupon ) {
			$discount_item            = array(
				'amount'        => intval( number_format( $coupon->get_amount() * 100, 0, '', '' ) ),
				'discount_id'   => $coupon_code,
				'discount_type' => 'manual',
				'description'   => $coupon->get_description(),
				'line_id'       => $line_id++,
			);
			$discount_item['amount'] += intval( number_format( WC()->cart->get_coupon_discount_tax_amount( $coupon_code ) * 100, 0, '', '' ) );

			$discount_items[] = $discount_item;
			$discount_codes[] = $coupon_code;

		}

		return array(
			'discount_lines' => $discount_items,
			'discount_codes' => $discount_codes,
		);
	}

	/**
	 * Retrieve all the fee items.
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array An associative array representing the fee items.
	 */
	private function fee_items( $order ) {
		$fee_items = array();

		$line_id = 0;
		foreach ( $order->get_fees() as $fee ) {
			$fee_item = array(
				'id'          => 'fee_' . $line_id,
				'line_id'     => 'fee_' . strval( $line_id++ ),
				'description' => $fee->get_name(),
				'quantity'    => $fee->get_quantity(),
				'amount'      => intval( number_format( $fee->get_total() * 100, 0, '', '' ) ),
				'vat_amount'  => intval( number_format( $fee->get_total_tax() * 100, 0, '', '' ) ),
			);

			$fee_item['amount'] += $fee_item['vat_amount'];
			$fee_item['vat']     = ( ! empty( $fee_item['vat_amount'] ) ) ? intval( number_format( $fee_item['vat_amount'] / $fee_item['amount'] * 100, 0, '', '' ) ) : 0;
			$fee_items[]         = $fee_item;
		}

		return $fee_items;
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
			'id'         => $selected_option_id,
			'line_id'    => $selected_option_id,
			'amount'     => intval( number_format( $selected_shipping_option->get_cost() * 100, 0, '', '' ) ),
			'operator'   => '',
			'title'      => $selected_shipping_option->get_label(),
			'vat_amount' => ( 0 === intval( $selected_shipping_option->get_shipping_tax() ) ) ? 0 : intval( number_format( $selected_shipping_option->get_shipping_tax() * 100, 0, '', '' ) ),
			'vat'        => ( 0 === intval( $selected_shipping_option->get_cost() ) ) ? 0 : intval( number_format( ( $selected_shipping_option->get_shipping_tax() / $selected_shipping_option->get_cost() ) * 100, 0, '', '' ) ),
		);

		$shipping_option['amount'] += $shipping_option['vat_amount'];

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
