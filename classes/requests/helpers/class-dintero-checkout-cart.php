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
		// A new merchant reference will be generated _every_ time this method is called. However, this is not desired behavior as when want to retrieve the merchant reference, you're already operating in a single, long-lived session.
		// Therefore, we must check if a merchant reference already exists before generating a new one.
		$merchant_reference = WC()->session->get( 'dintero_merchant_reference' );
		if ( empty( $merchant_reference ) ) {
			$merchant_reference = uniqid( 'dwc' );
			WC()->session->set( 'dintero_merchant_reference', $merchant_reference );
		}

		return $merchant_reference;
	}

	/**
	 * Gets formatted cart items.
	 *
	 * @return array Formatted cart items.
	 */
	public function get_order_lines() {
		$cart = WC()->cart->get_cart();

		// Get cart items.
		foreach ( $cart as $cart_item_key => $cart_item ) {
			$formatted_cart_items[] = $this->get_cart_item( $cart_item, $cart_item_key );
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
		if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() && count( WC()->shipping->get_packages() ) > 1 ) {
			// Handle multiple shipping lines.
			$formatted_cart_items = array_merge( $formatted_cart_items, $this->get_shipping_items() );
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
	 * @param array  $cart_item The cart item.
	 * @param string $cart_item_key The cart item hash.
	 * @return array
	 */
	public function get_cart_item( $cart_item, $cart_item_key ) {
		$id      = ( empty( $cart_item['variation_id'] ) ) ? $cart_item['product_id'] : $cart_item['variation_id'];
		$product = wc_get_product( $id );

		$cart_item = array(
			'id'          => $this->get_product_sku( $product ),
			'line_id'     => $cart_item_key,
			'description' => $this->get_product_name( $cart_item ),
			'quantity'    => absint( $cart_item['quantity'] ),
			'amount'      => self::format_number( $cart_item['line_total'] + $cart_item['line_tax'] ),
			'vat_amount'  => self::format_number( $cart_item['line_tax'] ),
			'vat'         => ( empty( floatval( $cart_item['line_total'] ) ) ) ? 0 : self::format_number( $cart_item['line_tax'] / $cart_item['line_total'] ),
		);

		$thumbnail_url = self::get_product_image_url( $product );
		if ( ! empty( $thumbnail_url ) ) {
			$cart_item['thumbnail_url'] = $thumbnail_url;
		}

		return $cart_item;
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
	 * @param array $cart_item The cart item.
	 * @return string
	 */
	public function get_product_name( $cart_item ) {
		$cart_item_data = $cart_item['data'];
		$cart_item_name = $cart_item_data->get_name();
		$item_name      = apply_filters( 'dintero_cart_item_name', $cart_item_name, $cart_item );
		return wp_strip_all_tags( $item_name );
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
			'vat'         => empty( floatval( $fee->amount + $fee->tax ) ) ? 0 : self::format_number( $fee->tax / ( $fee->amount + $fee->tax ) ),
		);
	}

	/**
	 * Get the Express Shipping Options. Used for shipping in the iframe.
	 * Returns all shipping methods in WooCommerce as Dintero shipping options,
	 * so the customer can select the shipping method in the iframe.
	 *
	 * @return array
	 */
	public function get_express_shipping_options() {
		$shipping_options = array();

		if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) {
			$chosen_shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

			// If the cart contain only free trial, we'll ignore the shipping methods. The shipping method will still be included in the subscription renewal.
			if ( class_exists( 'WC_Subscriptions_Cart' ) && \WC_Subscriptions_Cart::all_cart_items_have_free_trial() ) {

				// When a renewal fails for free trial subscription, it will need payment. Only on the initial subscription is payment not needed, and we must therefore not charge for shipping.
				if ( ! WC()->cart->needs_payment() ) {
					return $shipping_options;
				}
			}

			if ( empty( $chosen_shipping_methods ) ) {
				return $shipping_options;
			}

			$shipping_ids = array_unique( $chosen_shipping_methods );

			// Calculate shipping since WC Subscriptions will reset the shipping. See WC_Subscriptions_Cart::maybe_restore_shipping_methods().
			WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );

			// Remove shipping package for free trials. See WC_Subscriptions_Cart::set_cart_shipping_packages().
			$packages = WC()->shipping->get_packages();
			foreach ( $packages as $index => $package ) {
				foreach ( $package['contents'] as $cart_item_key => $cart_item ) {
					if ( class_exists( 'WC_Subscriptions_Product' ) && \WC_Subscriptions_Product::get_trial_length( $cart_item['data'] ) > 0 ) {
						unset( $packages[ $index ]['contents'][ $cart_item_key ] );
					}
				}

				if ( empty( $packages[ $index ]['contents'] ) ) {
					unset( $packages[ $index ] );
				}
			}

			$shipping_rates = reset( $packages )['rates'] ?? array();
			if ( empty( $shipping_rates ) ) {
				return $shipping_options;
			}

			foreach ( $shipping_ids as $key => $shipping_id ) {
				// Skip shipping lines for free trials.
				if ( class_exists( 'WC_Subscriptions_Cart' ) && \WC_Subscriptions_Cart::cart_contains_subscription() ) {
					$pattern = '/_after_a_\d+_\w+_trial/';
					if ( preg_match( $pattern, $key ) ) {
						continue;
					}
				}

				if ( $shipping_rates[ $shipping_id ] ?? false ) {
					$shipping_rate      = $shipping_rates[ $shipping_id ];
					$shipping_options[] = $this->get_shipping_option( $shipping_rate );
				}
			}
		}

		return $shipping_options;
	}

	/**
	 * Get shipping options as multiple order lines. This is used for
	 * when an cart has multiple shipping packages attached to it.
	 *
	 * @return array
	 */
	public function get_shipping_items() {
		$shipping_options = array();

		$shipping_ids   = array_unique( WC()->session->get( 'chosen_shipping_methods' ) );
		$shipping_rates = WC()->shipping->get_packages()[0]['rates'];

		foreach ( $shipping_ids as  $shipping_id ) {
			$shipping_method    = $shipping_rates[ $shipping_id ];
			$shipping_options[] = $this->get_shipping_item( $shipping_method );
		}

		return $shipping_options;
	}

	/**
	 * Gets a the single shipping method. Can be used to get a single shipping
	 * method for when shipping is not able to be selected in the iframe.
	 *
	 * @param object $shipping_method The id of the shipping method.
	 * @return array An empty array is returned if no shipping method is available.
	 */
	public function get_shipping_option( $shipping_method = null ) {
		if ( empty( $shipping_method ) ) {
			$shipping_method = $this->get_express_shipping_options();
			if ( ! empty( $shipping_method ) ) {
				return reset( $shipping_method );
			}
		}

		if ( empty( $shipping_method ) ) {
			return array();
		}

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
	}

	/**
	 * Formats the shipping method to be used in order.items.
	 *
	 * @param WC_Shipping_rate $shipping_method The WooCommerce shipping method.
	 * @return array
	 */
	public function get_shipping_item( $shipping_method ) {
		return array(
			'id'         => $shipping_method->get_id(),
			'line_id'    => $shipping_method->get_id(),
			'amount'     => self::format_number( $shipping_method->get_cost() + $shipping_method->get_shipping_tax() ),
			'title'      => $shipping_method->get_label(),
			'vat_amount' => ( empty( floatval( $shipping_method->get_cost() ) ) ) ? 0 : self::format_number( $shipping_method->get_shipping_tax() ),
			'vat'        => ( empty( floatval( $shipping_method->get_cost() ) ) ) ? 0 : self::format_number( $shipping_method->get_shipping_tax() / $shipping_method->get_cost() ),
			'quantity'   => 1,
			'type'       => 'shipping',
		);
	}

	/**
	 * Process coupons and gift cards.
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
					'vat'         => 0,
					'amount'      => $gift_card_amount,
				);

				$order_lines[] = $gift_card;

			}
		}

		// PW WooCommerce Gift Cards.
		if ( class_exists( 'PW_Gift_Cards' ) ) {
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
						'vat'         => 0,
						'amount'      => self::format_number( $value * -1 ),
					);

					$order_lines[] = $gift_card;
				}
			}
		}

		// YITH WooCommerce Gift Cards.
		if ( class_exists( 'YITH_WooCommerce_Gift_Cards' ) ) {
			if ( ! empty( WC()->cart->applied_gift_cards ) ) {
				foreach ( WC()->cart->applied_gift_cards as $coupon_key => $gift_card_code ) {
					$gift_card = array(
						'id'          => $gift_card_code,
						'line_id'     => $gift_card_code,
						'type'        => 'gift_card',
						'description' => apply_filters( 'yith_ywgc_cart_totals_gift_card_label', esc_html( __( 'Gift card:', 'yith-woocommerce-gift-cards' ) . ' ' . $gift_card_code ), $gift_card_code ),
						'quantity'    => 1,
						'tax_rate'    => 0,
						'vat_amount'  => 0,
						'vat'         => 0,
						'amount'      => isset( WC()->cart->applied_gift_cards_amounts[ $gift_card_code ] ) ? self::format_number( WC()->cart->applied_gift_cards_amounts[ $gift_card_code ] * -1 ) : 0,
					);

					$order_lines[] = $gift_card;
				}
			}
		}

		return $order_lines;
	}


	/**
	 * Retrieve the customer's billing address.
	 *
	 * @return array An associative array representing the billing address.
	 */
	public function get_billing_address() {
		$billing_address = array(
			'first_name'     => WC()->customer->get_billing_first_name(),
			'last_name'      => WC()->customer->get_billing_last_name(),
			'address_line'   => WC()->customer->get_billing_address_1(),
			'address_line_2' => WC()->customer->get_billing_address_2(),
			'business_name'  => WC()->customer->get_billing_company(),
			'postal_code'    => WC()->customer->get_billing_postcode(),
			'postal_place'   => WC()->customer->get_billing_city(),
			'country'        => WC()->customer->get_billing_country(),
			'phone_number'   => dintero_sanitize_phone_number( WC()->customer->get_billing_phone() ),
			'email'          => WC()->customer->get_billing_email(),
		);

		/* Sanitize all values. Remove all empty elements (required by Dintero). */
		return array_filter(
			$billing_address,
			function ( $value ) {
				return ! empty( wc_clean( $value ) );
			}
		);
	}

	/**
	 * Retrieve the customer's shipping address.
	 *
	 * @return array An associative array representing the shipping address.
	 */
	public function get_shipping_address() {
		$shipping_address = array(
			'first_name'     => WC()->customer->get_shipping_first_name(),
			'last_name'      => WC()->customer->get_shipping_last_name(),
			'address_line'   => WC()->customer->get_shipping_address_1(),
			'address_line_2' => WC()->customer->get_shipping_address_2(),
			'business_name'  => WC()->customer->get_shipping_company(),
			'postal_code'    => WC()->customer->get_shipping_postcode(),
			'postal_place'   => WC()->customer->get_shipping_city(),
			'country'        => WC()->customer->get_shipping_country(),
			'email'          => WC()->customer->get_billing_email(),
		);

		// Check if a shipping phone number exist. Default to billing phone.
		$phone                            = WC()->customer->get_shipping_phone();
		$shipping_address['phone_number'] = dintero_sanitize_phone_number( ! empty( $phone ) ? $phone : WC()->customer->get_billing_phone() );

		/* Sanitize all values. Remove all empty elements (required by Dintero). */
		return array_filter(
			$shipping_address,
			function ( $value ) {
				return ! empty( wc_clean( $value ) );
			}
		);
	}
}
