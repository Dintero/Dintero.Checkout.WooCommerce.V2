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
class Dintero_Checkout_Cart extends Dintero_Checkout_Helper_Base {
	/**
	 * Get the order total.
	 *
	 * @return int
	 */
	public function get_order_total() {
		return self::format_number( WC()->cart->total );
	}

	/**
	 * Get the tax total.
	 *
	 * @return int
	 */
	public function get_tax_total() {
		return self::format_number( WC()->cart->get_total_tax() );
	}

	/**
	 * Get the cart currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return get_woocommerce_currency();
	}

	/**
	 * Get the merchant reference.
	 *
	 * @return string
	 */
	public function get_merchant_reference() {
		return uniqid( 'dwc' );
	}

	/**
	 * Gets formatted cart items.
	 *
	 * @return array Formatted cart items.
	 */
	public function get_order_lines() {
		$cart = WC()->cart->get_cart();

		// Get cart items.
		foreach ( $cart as $cart_item ) {
			$formatted_cart_items[] = $this->get_cart_item( $cart_item );
		}

		/**
		 * Get cart fees.
		 *
		 * @var $cart_fees WC_Cart_Fees
		 */
		$cart_fees = WC()->cart->get_fees();
		foreach ( $cart_fees as $fee ) {
			$formatted_cart_items[] = $this->get_fee( $fee );
		}

		// Get cart shipping.
		if ( WC()->cart->needs_shipping() && count( WC()->shipping->get_packages() ) > 1 ) {
			$shipping_objects = $this->get_shipping_objects();

			/* The cart may have multiple shipping packages, but the same package can be used for all products which we'll treat as a single shipping option instead. */
			if ( count( $shipping_objects ) > 1 ) {
				$formatted_cart_items = array_merge( $formatted_cart_items, $this->get_shipping_objects() );
			}
		}

		$coupons = $this->process_coupons();
		if ( ! empty( $coupons ) ) {
			$formatted_cart_items = array_merge( $formatted_cart_items, $coupons );
		}

		return $formatted_cart_items;
	}

	/**
	 * Get the formatted order line from a cart item.
	 *
	 * @param array $cart_item The cart item.
	 * @return array
	 */
	public function get_cart_item( $cart_item ) {
		$id      = ( empty( $cart_item['variation_id'] ) ) ? $cart_item['product_id'] : $cart_item['variation_id'];
		$product = wc_get_product( $id );

		return array(
			'id'          => $this->get_product_sku( $product ),
			'line_id'     => $this->get_product_sku( $product ),
			'description' => $this->get_product_name( $cart_item ),
			'quantity'    => $cart_item['quantity'],
			'amount'      => self::format_number( $cart_item['line_total'] + $cart_item['line_tax'] ),
			'vat_amount'  => self::format_number( $cart_item['line_tax'] ),
		);
	}

	/**
	 * Get the product SKU.
	 *
	 * @param object $product The WooCommerce Product.
	 * @return string
	 */
	public function get_product_sku( $product ) {
		if ( $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} else {
			$item_reference = $product->get_id();
		}

		return strval( $item_reference );
	}

	/**
	 * Gets the product name.
	 *
	 * @param object $cart_item The cart item.
	 * @return string
	 */
	public function get_product_name( $cart_item ) {
		$cart_item_data = $cart_item['data'];
		$cart_item_name = $cart_item_data->get_name();
		$item_name      = apply_filters( 'dintero_cart_item_name', $cart_item_name, $cart_item );
		return strip_tags( $item_name );
	}

	/**
	 * Get the formatted order line from a fee.
	 *
	 * @param object $fee The cart fee.
	 * @return array
	 */
	public function get_fee( $fee ) {
		$name = $fee->name;
		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'          => $name,
			'line_id'     => $name,
			'description' => $name,
			'quantity'    => 1,
			'amount'      => self::format_number( $fee->amount + $fee->tax ),
			'vat_amount'  => self::format_number( $fee->tax ),
		);
	}

	/**
	 * Gets the formatted order line from shipping.
	 *
	 * @param object $shipping_method The id of the shipping method.
	 * @return array
	 */
	public function get_shipping_option( $shipping_method ) {
		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'              => $shipping_method->get_id(),
			'line_id'         => $shipping_method->get_id(),
			'amount'          => self::format_number( $shipping_method->get_cost() + $shipping_method->get_shipping_tax() ),
			'operator'        => '',
			'description'     => '',
			'title'           => $shipping_method->get_label(),
			'delivery_method' => 'unspecified',
			'vat_amount'      => self::format_number( $shipping_method->get_shipping_tax() ),
			'vat'             => ( empty( floatval( $shipping_method->get_cost() ) ) ) ? 0 : self::format_number( $shipping_method->get_shipping_tax() / $shipping_method->get_cost() ),
		);
	}

	/**
	 * Formats the shipping method to be used in order.items.
	 *
	 * @param WC_Shipping_rate $shipping_method
	 * @return array
	 */
	public function get_shipping_item( $shipping_method ) {
		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'         => $shipping_method->get_id(),
			'line_id'    => $shipping_method->get_id(),
			'amount'     => self::format_number( $shipping_method->get_cost() + $shipping_method->get_shipping_tax() ),
			'title'      => $shipping_method->get_label(),
			'vat_amount' => ( empty( floatval( $shipping_method->get_cost() ) ) ) ? 0 : self::format_number( $shipping_method->get_shipping_tax() ),
			'vat'        => ( empty( floatval( $shipping_method->get_cost() ) ) ) ? 0 : self::format_number( $shipping_method->get_shipping_tax() / $shipping_method->get_cost() ),
			/* Since the shipping will be added to the list of products, it needs a quantity. */
			'quantity'   => 1,
			/* Dintero needs to know this is an order with multiple shipping options by setting the 'type'. */
			'type'       => 'shipping',
		);
	}

	/**
	 * Get the formatted shipping object.
	 *
	 * @return array|null
	 */
	public function get_shipping_object() {
		if ( WC()->cart->needs_shipping() && count( WC()->shipping->get_packages() ) === 1 ) {
			$packages        = WC()->shipping()->get_packages();
			$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
			$chosen_shipping = $chosen_methods[0];
			foreach ( $packages as $i => $package ) {
				foreach ( $package['rates'] as $shipping_method ) {
					if ( $chosen_shipping === $shipping_method->id ) {
						if ( $shipping_method->cost > 0 ) {
							return array(
								'id'              => $shipping_method->get_id(),
								'line_id'         => $shipping_method->get_id(),
								'amount'          => self::format_number( $shipping_method->get_cost() + $shipping_method->get_shipping_tax() ),
								'operator'        => '',
								'description'     => '',
								'title'           => $shipping_method->get_label(),
								'delivery_method' => 'unspecified',
								'vat_amount'      => self::format_number( $shipping_method->get_shipping_tax() ),
								'vat'             => ( empty( floatval( $shipping_method->get_cost() ) ) ) ? 0 : self::format_number( $shipping_method->get_shipping_tax() / $shipping_method->get_cost() ),
							);
						} else {
							return array(
								'id'              => $shipping_method->get_id(),
								'line_id'         => $shipping_method->get_id(),
								'amount'          => 0,
								'operator'        => '',
								'description'     => '',
								'title'           => $shipping_method->get_label(),
								'delivery_method' => 'unspecified',
								'vat_amount'      => 0,
								'vat'             => 0,
							);
						}
					}
				}
			}
		}

		return null;
	}

	/**
	 * Get the selected shipping objects (if available).
	 *
	 * @return array If no shipping is available or selected, an empty array is returned.
	 */
	public function get_shipping_objects() {

		$shipping_lines = array();

		if ( ! WC()->cart->needs_shipping() || empty( count( WC()->shipping->get_packages() ) ) ) {
			return $shipping_lines;
		}

		$shipping_ids   = array_unique( WC()->session->get( 'chosen_shipping_methods' ) );
		$shipping_rates = WC()->shipping->get_packages()[0]['rates'];

		$is_multiple_shipping = ( count( $shipping_ids ) > 1 );
		if ( ! $is_multiple_shipping ) {
			$shipping_ids = array( $shipping_ids[0] );
		}

		foreach ( $shipping_ids as  $shipping_id ) {
			$shipping_method  = $shipping_rates[ $shipping_id ];
			$shipping_lines[] = ( $is_multiple_shipping ) ? $this->get_shipping_item( $shipping_method ) : $this->get_shipping_option( $shipping_method );
		}

		return $shipping_lines;
	}

	/**
	 * process coupons and gift cards.
	 *
	 * @return array A formatted list of coupon and gift card items.
	 */
	public function process_coupons() {
		$order_lines = array();

		/* WooCommerce Gift Cards compatibility. */
		if ( class_exists( 'wc_gc_gift_cards' ) ) {
			/**
			 * Use the applied giftcards.
			 *
			 * @var WC_GC_Gift_Card_Data $wc_gc_gift_card_data
			*/
			$totals_before_giftcard = round( wc()->cart->get_subtotal() + wc()->cart->get_shipping_total() + wc()->cart->get_subtotal_tax() + wc()->cart->get_shipping_tax(), wc_get_price_decimals() );
			$giftcards_used         = wc_gc()->giftcards->cover_balance( $totals_before_giftcard, wc_gc()->giftcards->get_applied_giftcards_from_session() );

			foreach ( wc_gc()->giftcards->get_applied_giftcards_from_session() as $wc_gc_gift_card_data ) {
				$gift_card_code   = $wc_gc_gift_card_data->get_code();
				$gift_card_amount = self::format_number( $giftcards_used['total_amount'] * -1 );

				$gift_card = array(
					'id'          => $wc_gc_gift_card_data->get_code() . ':' . $wc_gc_gift_card_data->get_id(),
					'line_id'     => $wc_gc_gift_card_data->get_code() . ':' . $wc_gc_gift_card_data->get_id(),
					'type'        => 'gift_card',
					'description' => __( 'Gift card', 'dintero-checkout-for-woocommerce' ) . ': ' . $gift_card_code,
					'quantity'    => 1,
					'tax_rate'    => 0,
					'vat_amount'  => 0,
					'amount'      => $gift_card_amount,
				);

				$order_lines[] = $gift_card;

			}
		}

		// PW WooCommerce Gift Cards.
		if ( ! empty( WC()->session->get( 'pw-gift-card-data' ) ) ) {
			$pw_gift_cards = WC()->session->get( 'pw-gift-card-data' );
			foreach ( $pw_gift_cards['gift_cards'] as $gift_card_code => $value ) {

				$gift_card = array(
					'id'          => $gift_card_code,
					'line_id'     => $gift_card_code,
					'type'        => 'gift_card',
					'description' => __( 'Gift card', 'dintero-checkout-for-woocommerce' ) . ': ' . $gift_card_code,
					'quantity'    => 1,
					'tax_rate'    => 0,
					'vat_amount'  => 0,
					'amount'      => self::format_number( $value * -1 ),
				);

				$order_lines[] = $gift_card;
			}
		}

		return $order_lines;
	}

}
