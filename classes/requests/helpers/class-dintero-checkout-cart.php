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
	 * All the products in the cart, but may contain other items that behave similar to products (used by third-part plugins).
	 *
	 * @var array
	 */
	private $products = array();

	/**
	 * All the discounts (if any) applied to the cart.
	 *
	 * @var array
	 */
	private $discounts = array(
		'discount_lines' => array(),
		'discount_codes' => array(),
	);

	/**
	 * The selected shipping options, and information related to them.
	 *
	 * @var array
	 */
	private $shipping = array();

	/**
	 * All the fees applied to the cart.
	 *
	 * @var array
	 */
	private $fees = array();

	/**
	 * Retrieve the cart items to be passed on to Dintero.
	 *
	 * @param int $order_id order id.
	 * @return array An associative array representation of the expected Dintero cart object.
	 */
	public function cart( $order_id ) {
		return $this->order_lines( wc_get_order( $order_id ) );
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
			'vat_amount'           => intval( number_format( $order->get_total_tax() * 100, 0, '', '' ) ),
			'merchant_reference_2' => $order->get_order_number(),
			'billing_address'      => $this->billing_address( $order ),
			'store'                => array(
				'id' => preg_replace( '/(https?:\/\/|www.|\/\s*$)/i', '', get_home_url() ),
			),
		);

		$this->order_items();
		$order_lines['items'] = $this->products;

		if ( WC()->cart->needs_shipping() && ! empty( WC()->session->get( 'chosen_shipping_methods' ) ) ) {
			$this->shipping_option();
			$order_lines['shipping_address'] = $this->shipping_address( $order );

			/* Undocumented: If more than one shipping line, set 'shipping_option' to undefined, and add the shipping methods accordingly to 'items'. */
			if ( count( $this->shipping ) > 1 ) {
				$order_lines['items'] = array_merge( $order_lines['items'], $this->shipping );
			} else {
				$order_lines['shipping_option'] = $this->shipping[0];
			}
		}

		if ( ! empty( WC()->cart->get_coupons() ) ) {
			$this->discount_items();
			$order_lines['discount_lines'] = $this->discounts['discount_lines'];
			$order_lines['discount_codes'] = $this->discounts['discount_codes'];
		}

		if ( ! empty( WC()->cart->get_fees() ) ) {
			$this->fee_items();
			$order_lines['items'] = array_merge( $order_lines['items'], $this->fees );
		}

		return $order_lines;
	}

	/**
	 * Retrieve all the cart items (excluding shipping).
	 *
	 * @return void Populates $this->products.
	 */
	private function order_items() {
		$cart = WC()->cart->get_cart();

		foreach ( $cart as $item ) {
			$id      = ( empty( $item['variation_id'] ) ) ? $item['product_id'] : $item['variation_id'];
			$product = wc_get_product( $id );

			/* A misconfigured variation product will not have a unique variation id, but it will still have a unique variant. */
			if ( ! empty( $item['variation'] ) ) {
				$id .= ':' . reset( $item['variation'] );
			}

			$order_item = array(
				/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
				'id'          => strval( $id ),
				'line_id'     => strval( $id ),
				'description' => $product->get_name(),
				'quantity'    => $item['quantity'],
				'amount'      => intval( number_format( $item['line_total'] * 100, 0, '', '' ) ),
				'vat_amount'  => intval( number_format( $item['line_tax'] * 100, 0, '', '' ) ),
			);

			if ( $product->is_taxable() ) {
				$tax_rate          = WC_TAX::get_base_tax_rates( $product->get_tax_class() );
				$order_item['vat'] = empty( $tax_rate ) ? 0 : reset( $tax_rate )['rate'];
			}

			$order_item['amount'] += $order_item['vat_amount'];
			$this->products[]      = $order_item;
		}

	}

	/**
	 * Retrieve all the discount items.
	 *
	 * @return void Populates $this->discounts.
	 */
	private function discount_items() {

		foreach ( WC()->cart->get_coupons() as $coupon_code => $coupon ) {
			$discount_item            = array(
				'amount'        => intval( number_format( $coupon->get_amount() * 100, 0, '', '' ) ),
				'discount_id'   => strval( $coupon_code ),
				'discount_type' => 'manual',
				'description'   => $coupon->get_description(),
				/* line_id must be an integer. */
				'line_id'       => $coupon->get_id(),
			);
			$discount_item['amount'] += intval( number_format( WC()->cart->get_coupon_discount_tax_amount( $coupon_code ) * 100, 0, '', '' ) );

			array_push( $this->discounts['discount_lines'], $discount_item );
			array_push( $this->discounts['discount_codes'], $coupon_code );

		}
	}

	/**
	 * Retrieve all the fee items.
	 *
	 * @return void Populates $this->fees.
	 */
	private function fee_items() {

		foreach ( WC()->cart->get_fees() as $fee ) {
			$name     = $fee->name;
			$fee_item = array(
				/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
				'id'          => $name,
				'line_id'     => $name,
				'description' => $name,
				'quantity'    => 1,
				'amount'      => intval( number_format( $fee->amount * 100, 0, '', '' ) ),
				'vat_amount'  => intval( number_format( $fee->tax * 100, 0, '', '' ) ),
			);

			$fee_item['amount'] += $fee_item['vat_amount'];
			$fee_item['vat']     = ( ! empty( $fee_item['vat_amount'] ) ) ? intval( number_format( $fee_item['vat_amount'] / $fee_item['amount'] * 100, 0, '', '' ) ) : 0;
			$this->fees[]        = $fee_item;
		}
	}

	/**
	 * Retrieve all the shipping options.
	 *
	 * @return void Populates $this->shipping.
	 */
	private function shipping_option() {

		$shipping_ids   = WC()->session->get( 'chosen_shipping_methods' );
		$shipping_rates = WC()->shipping->get_packages()[0]['rates'];

		$is_multiple_shipping = ( count( WC()->shipping->get_packages() ) > 1 );
		if ( ! $is_multiple_shipping ) {
			$shipping_ids = array( $shipping_ids[0] );
		}

		foreach ( $shipping_ids as  $shipping_id ) {

			$shipping_method = $shipping_rates[ $shipping_id ];
			$shipping_option = array(
				/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
				'id'          => $shipping_id,
				'line_id'     => $shipping_id,
				'amount'      => intval( number_format( $shipping_method->get_cost() * 100, 0, '', '' ) ),
				'operator'    => '',
				'description' => '',
				'title'       => $shipping_method->get_label(),
				'vat_amount'  => ( empty( $shipping_method->get_shipping_tax() ) ) ? 0 : intval( number_format( $shipping_method->get_shipping_tax() * 100, 0, '', '' ) ),
				'vat'         => ( empty( $shipping_method->get_cost() ) ) ? 0 : intval( number_format( ( $shipping_method->get_shipping_tax() / $shipping_method->get_cost() ) * 100, 0, '', '' ) ),
			);

			// Dintero needs to know this is an order with multiple shipping options by setting the 'type'.
			if ( $is_multiple_shipping ) {
				// FIXME: This ENUM has not yet been added in production by Dintero. We'll omit it for now per agreement.
				// $shipping_option['type'] = 'shipping';

				/* Since the shipping will be added to the list of products, it needs a quantity. */
				$shipping_option['quantity'] = 1;
			}

			$shipping_option['amount'] += $shipping_option['vat_amount'];
			$this->shipping[]           = $shipping_option;
		}

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

			/* Keep the '+' at the start (if available), remove every subsequent non-digit character. */
			'phone_number'   => preg_replace( '/(?!^)[+]?[^\d]/', '', $order->get_billing_phone() ),
			'email'          => $order->get_billing_email(),
		);

		/* Sanitize all values. Remove all empty elements (required by Dintero). */
		return array_filter(
			$billing_address,
			function( $value ) {
				return ! empty( sanitize_text_field( $value ) );
			}
		);
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

		// Check if a shipping phone number exist. Default to billing phone.
		$phone = $order->get_shipping_phone();
		/* Keep the '+' at the start (if available), remove every subsequent non-digit character. */
		$shipping_address['phone_number'] = preg_replace( '/(?!^)[+]?[^\d]/', '', ( ! empty( $phone ) ) ? $phone : $order->get_billing_phone() );

		/* Sanitize all values. Remove all empty elements (required by Dintero). */
		return array_filter(
			$shipping_address,
			function( $value ) {
				return ! empty( sanitize_text_field( $value ) );
			}
		);
	}
}
