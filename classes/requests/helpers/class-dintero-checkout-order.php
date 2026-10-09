<?php //phpcs:ignore
/**
 * Class for processing order items to be used with order management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for processing orders items to be used with order management.
 */
class Dintero_Checkout_Order extends Dintero_Checkout_Helper_Base {

	/**
	 * The WooCommerce order.
	 *
	 * @var WC_Order|WC_Order_Refund
	 */
	public $order;

	/**
	 * Whether the order being processed is a refund.
	 *
	 * @var bool
	 */
	public $is_refund = false;

	/**
	 * Class constructor.
	 *
	 * @param WC_Order|WC_Order_Refund $order The Woo order.
	 */
	public function __construct( $order ) {
		$this->order     = $order;
		$this->is_refund = $this->order instanceof WC_Order_Refund;
	}

	/**
	 * Get the order total.
	 *
	 * @return int
	 */
	public function get_order_total() {
		$order_total = self::format_number( $this->order->get_total() );
		if ( $this->order instanceof WC_Order ) {
			// Only available and relevant for WC_Order.
			$refunded_total = self::format_number( $this->order->get_total_refunded() );
			return absint( $order_total - $refunded_total );
		}

		return absint( $order_total );
	}

	/**
	 * Get the tax total.
	 *
	 * @return int
	 */
	public function get_tax_total() {
		$order_total_tax = self::format_number( $this->order->get_total_tax() );

		if ( ! $this->is_refund ) {
			// Only available and relevant for WC_Order.
			$refunded_total_tax = self::format_number( $this->order->get_total_tax_refunded() );
			return absint( $order_total_tax - $refunded_total_tax );
		}

		return absint( $order_total_tax );
	}

	/**
	 * Get the order currency.
	 *
	 * @return string
	 */
	public function get_currency() {
		return $this->order->get_currency();
	}

	/**
	 * Get the merchant reference to send to Dintero for the order.
	 *
	 * The stored reference wins, so that Dintero always receives exactly what callbacks are matched against.
	 *
	 * @return string
	 */
	public function get_merchant_reference() {
		$merchant_reference = strval( $this->order->get_meta( '_dintero_merchant_reference' ) );
		return empty( $merchant_reference ) ? self::get_order_reference( $this->order ) : $merchant_reference;
	}

	/**
	 * Store a merchant reference on the order before a Dintero session is created for it.
	 *
	 * An existing reference is kept, so that callbacks for an earlier payment attempt still find the order.
	 * It is only replaced when another order shares it, which orders from before references were unique can.
	 *
	 * @param WC_Order $order The WC order.
	 * @return void
	 */
	public static function maybe_set_merchant_reference( $order ) {
		$stored = strval( $order->get_meta( '_dintero_merchant_reference' ) );
		if ( ! empty( $stored ) && ! self::is_reference_shared( $stored, $order ) ) {
			return;
		}

		$merchant_reference = self::get_order_reference( $order );
		if ( self::is_reference_shared( $merchant_reference, $order ) ) {
			$merchant_reference = uniqid( "{$merchant_reference}-" );
		}

		$order->update_meta_data( '_dintero_merchant_reference', $merchant_reference );
		$order->save();
	}

	/**
	 * Derive a merchant reference from the order alone, so that two orders in the same WC session never share one.
	 *
	 * @param WC_Order $order The WC order.
	 * @return string
	 */
	private static function get_order_reference( $order ) {
		$merchant_reference = strval( $order->get_order_number() );
		return empty( $merchant_reference ) ? strval( $order->get_id() ) : $merchant_reference;
	}

	/**
	 * Whether another order already has the merchant reference.
	 *
	 * @param string   $merchant_reference The merchant reference.
	 * @param WC_Order $order The order the reference is for.
	 * @return bool
	 */
	private static function is_reference_shared( $merchant_reference, $order ) {
		$order_ids = wc_get_orders(
			array(
				'meta_key'     => '_dintero_merchant_reference', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'   => $merchant_reference, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare' => '=',
				'limit'        => 2,
				'return'       => 'ids',
			)
		);

		return ! empty( array_diff( array_map( 'intval', $order_ids ), array( $order->get_id() ) ) );
	}

	/**
	 * Gets formatted cart items.
	 *
	 * @return array Formatted cart items.
	 */
	public function get_order_lines() {
		$order_lines = array();

		/**
		 * Process order item products.
		 *
		 * @var WC_Order_Item_Product $order_item WooCommerce order item product.
		 */
		foreach ( $this->get_items() as $order_item ) {
			$order_line = $this->get_order_line( $order_item );
			if ( ! empty( $order_line ) ) {
				$order_lines[] = $order_line;
			}
		}

		/**
		 * Process order item shipping.
		 *
		 * @var WC_Order_Item_Shipping $order_item WooCommerce order item shipping.
		 */
		$shipping_items = $this->get_items( 'shipping' );
		if ( count( $shipping_items ) > 1 ) {
			/* If there is more than one shipping option, it will be part of the order.items to support multiple shipping packages. */
			foreach ( $shipping_items as $order_item ) {
				$order_line = $this->get_shipping_item( $order_item );
				if ( ! empty( $order_line ) ) {
					$order_lines[] = $order_line;
				}
			}
		}

		/**
		 * Process order item fee.
		 *
		 * @var WC_Order_Item_Fee $order_item WooCommerce order item fee.
		 */
		foreach ( $this->order->get_items( 'fee' ) as $order_item ) {
			$order_lines[] = $this->get_fee( $order_item );
		}

		/**
		 * Process order item gift card.
		 *
		 * @var WC_GC_Order_Item_Gift_Card $gift_card WooCommerce order item gift card.
		 */
		foreach ( $this->order->get_items( 'gift_card' ) as $gift_card ) {
			$order_lines[] = $this->get_gift_card( $gift_card );
		}

		foreach ( $this->order->get_items( 'pw_gift_card' ) as $gift_card ) {
			$order_lines[] = $this->get_gift_card( $gift_card );
		}

		if ( class_exists( 'YITH_YWGC_Cart_Checkout' ) ) {
			$yith_gift_cards = get_post_meta( $this->order->get_id(), '_ywgc_applied_gift_cards', true );
			if ( $yith_gift_cards ) {
				foreach ( $yith_gift_cards as $code => $amount ) {
					$order_lines[] = $this->get_gift_card( YITH_YWGC()->get_gift_card_by_code( $code ) );
				}
			}
		}

		return array_values( $order_lines );
	}

	/**
	 * Return an array of items/products within this order.
	 *
	 * Filters out any refunded items. This should ensure manual refunds are taken into consideration.
	 *
	 * @param string|array $types Types of line items to get (array or string).
	 * @return WC_Order_Item[]
	 */
	private function get_items( $types = 'line_item' ) {
		$order_items = $this->order->get_items( $types );

		if ( $this->is_refund ) {
			return $order_items;
		}

		if ( ! ( is_string( $types ) && in_array( $types, array( 'line_item', 'shipping' ), true ) ) ) {
			return $order_items;
		}

		$refunds = $this->order->get_refunds();

		// Retrieve all the items that has been refunded.
		$refunded_order_items = array_reduce(
			$refunds,
			function ( $carry, $refund ) use ( $types ) {
				$items = $refund->get_items( $types );
				if ( ! empty( $items ) ) {
					$carry = array_merge( $carry, $items );
				}
				return $carry;
			},
			array()
		);

		// Remove refunded items from the order items.
		foreach ( $refunded_order_items as $refunded_order_item ) {
			if ( $refunded_order_item instanceof WC_Order_Item_Product ) {
				$refunded_product_id     = empty( $refunded_order_item['variation_id'] ) ? $refunded_order_item['product_id'] : $refunded_order_item['variation_id'];
				$refunded_order_item_key = $this->get_product_sku( wc_get_product( $refunded_product_id ) );

			} elseif ( $refunded_order_item instanceof WC_Order_Item_Shipping ) {
				$refunded_order_item_key = $refunded_order_item->get_method_id() . ':' . $refunded_order_item->get_instance_id();
			}

			foreach ( $order_items as $i => $order_item ) {
				if ( $order_item instanceof WC_Order_Item_Product ) {
					$product_id     = empty( $order_item['variation_id'] ) ? $order_item['product_id'] : $order_item['variation_id'];
					$order_item_key = $this->get_product_sku( wc_get_product( $product_id ) );

				} elseif ( $order_item instanceof WC_Order_Item_Shipping ) {
					$order_item_key = $order_item->get_method_id() . ':' . $order_item->get_instance_id();
				}

				if ( $refunded_order_item_key === $order_item_key ) {
					unset( $order_items[ $i ] );
				}
			}
		}

		return $order_items;
	}

	/**
	 * Get the formatted order line from a cart item.
	 *
	 * @param WC_Order_Item_Product $order_item WooCommerce order item product.
	 * @return array
	 */
	public function get_order_line( $order_item ) {
		$id      = empty( $order_item['variation_id'] ) ? $order_item['product_id'] : $order_item['variation_id'];
		$product = wc_get_product( $id );

		// Check if the product has been permanently deleted.
		if ( empty( $product ) ) {
			if ( method_exists( $this->order, 'add_order_note' ) ) {
				$this->order->add_order_note( __( 'This order contain a product that was permanently deleted. Cannot proceed with action.', 'dintero-checkout-for-woocommerce' ) );
				$this->order->save();
			}
			return array();
		}

		if ( is_a( $this->order, 'WC_Order_Refund' ) ) {
			$line_id = wc_get_order_item_meta( $order_item->get_meta( '_refunded_item_id' ), '_dintero_checkout_line_id', true );
		} else {
			$line_id = $order_item->get_meta( '_dintero_checkout_line_id' );
		}

		/* If $line_id is empty, the order was most likely placed in an older version of Dintero (< v1.1.0). */
		if ( empty( $line_id ) ) {
			$line_id = $this->get_product_sku( $product );
		}

		$vat_rate = WC_Tax::get_base_tax_rates( $product->get_tax_class() );

		$item_total     = floatval( $order_item->get_total() );
		$item_total_tax = floatval( $order_item->get_total_tax() );
		$order_line     = array(
			'id'          => $this->get_product_sku( $product ),
			'line_id'     => $line_id,
			'description' => $order_item->get_name(),
			'quantity'    => absint( $order_item->get_quantity() ),
			'amount'      => absint( self::format_number( $item_total + $item_total_tax ) ),
			'vat_amount'  => absint( self::format_number( $item_total_tax ) ),
			'vat'         => reset( $vat_rate )['rate'] ?? 0,
		);

		$thumbnail_url = self::get_product_image_url( $product );
		if ( ! empty( $thumbnail_url ) ) {
			$order_line['thumbnail_url'] = $thumbnail_url;
		}

		return $order_line;
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
	 * Get the formatted order line from a fee.
	 *
	 * @param WC_Order_Item_Fee $order_item WooCommerce order item fee.
	 * @return array
	 */
	public function get_fee( $order_item ) {
		$fee_total     = floatval( $order_item->get_total() );
		$fee_total_tax = floatval( $order_item->get_total_tax() );

		$name       = $order_item->get_name();
		$amount     = absint( self::format_number( $fee_total + $fee_total_tax ) );
		$vat_amount = absint( self::format_number( $fee_total_tax ) );

		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'          => $name,
			'line_id'     => $name,
			'description' => $name,
			'quantity'    => 1,
			'amount'      => $amount,
			'vat_amount'  => $vat_amount,
			'vat'         => $fee_total <= 0 ? 0 : self::format_number( $fee_total_tax / $fee_total ),
		);
	}

	/**
	 * Get the formatted order line from a gift card.
	 *
	 * @param WC_GC_Order_Item_Gift_Card|WC_Order_Item_PW_Gift_Card|YITH_YWGC_Gift_Card $gift_card WooCommerce order item gift card.
	 * @return array
	 */
	public function get_gift_card( $gift_card ) {

		if ( is_a( $gift_card, 'WC_GC_Order_Item_Gift_Card' ) ) {
			$id          = $gift_card->get_code() . ':' . $gift_card->get_giftcard_id();
			$description = $gift_card->get_code();
			$amount      = $gift_card->get_amount();
		}

		if ( is_a( $gift_card, 'WC_Order_Item_PW_Gift_Card' ) ) {
			$id          = $gift_card->get_name();
			$description = $gift_card->get_name();
			$amount      = $gift_card->get_amount();
		}

		if ( is_a( $gift_card, 'YITH_YWGC_Gift_Card' ) ) {
			$id          = $gift_card->get_code();
			$description = $gift_card->get_code();
			$amount      = $gift_card->total_amount;
		}

		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'          => $id,
			'line_id'     => $id,
			'type'        => 'gift_card',
			'description' => __( 'Gift card', 'dintero-checkout-for-woocommerce' ) . ': ' . $description,
			'quantity'    => 1,
			'tax_rate'    => 0,
			'vat_amount'  => 0,
			'vat'         => 0,
			'amount'      => self::format_number( $amount * -1 ),
		);
	}

	/**
	 * Formats the shipping method to be used in order.items.
	 *
	 * @param WC_Order_Item_Shipping $shipping_item The WooCommerce shipping method.
	 * @return array
	 */
	public function get_shipping_item( $shipping_item ) {
		$id = $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id();

		// If this is a refund, we need to retrieve the line_id from the non-refund order as the OrderRefund does not contain the original metadata in Order.
		if ( $this->is_refund ) {
			$line_id = wc_get_order_item_meta( $shipping_item->get_meta( '_refunded_item_id' ), '_dintero_checkout_line_id', true );
		} else {
			$line_id = $shipping_item->get_meta( '_dintero_checkout_line_id' );
		}

		if ( empty( $line_id ) ) {
			// If the line_id is not set, we use the id as the line_id.
			$line_id = $id;
		}

		$shipping_cost = floatval( $shipping_item->get_total() );
		$shipping_tax  = floatval( $shipping_item->get_total_tax() );

		$description = $this->get_shipping_method_option( $shipping_item, 'dintero_description' );

		$shipping_option = array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'              => $id,
			'line_id'         => $line_id,
			'amount'          => self::format_number( $shipping_cost + $shipping_tax ),
			'description'     => ! empty( $description ) ? $description : $shipping_item->get_name(),
			'title'           => $shipping_item->get_name(),
			'delivery_method' => 'unspecified',
			'vat_amount'      => $shipping_cost <= 0 ? 0 : self::format_number( $shipping_tax ),
			'vat'             => $shipping_cost <= 0 ? 0 : self::format_number( $shipping_tax / $shipping_cost ),
			'operator'        => '',
			/* Since the shipping will be added to the list of products, it needs a quantity. */
			'quantity'        => 1,
			/* Dintero needs to know this is an order with multiple shipping options by setting the 'type'. */
			'type'            => 'shipping',
		);

		// Is this a pick-up point? If it has metadata, then it is a pick-up point.
		$encoded_meta = $shipping_item->get_meta( 'udc_delivery_data' );
		if ( ! empty( $encoded_meta ) ) {
			$meta                             = json_decode( $encoded_meta, true );
			$carrier                          = $meta['carrierId'];
			$shipping_option['operator']      = $carrier;
			$shipping_option['thumbnail_url'] = $this->get_pickup_point_icon( $carrier, $shipping_item );
		}
		return $shipping_option;
	}



	/**
	 * Gets the formatted order line from shipping.
	 *
	 * The default null value is necessary to add compatibility with the Dintero_Checkout_Helper_Base::add_shipping method. If the order contains more than one shipping package, the order.shipping_option must be unset, thus we return an empty array.
	 *
	 * @param WC_Order_Item_Shipping|null $shipping_item WC order item shipping.
	 * @return array
	 */
	public function get_shipping_option( $shipping_item = null ) {
		if ( empty( $shipping_item ) ) {
			$shipping_items = $this->get_items( 'shipping' );
			if ( count( $shipping_items ) !== 1 ) {
				// Multiple shipping packages are part of order.items instead.
				return array();
			}

			$shipping_item = array_values( $shipping_items )[0];
		}

		return $this->get_shipping_item( $shipping_item );
	}

	/**
	 * Get the shipping line id from the order meta.
	 *
	 * This is needed to support refunds, as the WC_Order_Refund does not contain the original order item meta data. The shipping line id must be the same for the refund and the original order, otherwise the refund will not be able to match the correct shipping line in Dintero.
	 *
	 * @param WC_Order               $order The WooCommerce order.
	 * @param WC_Order_Item_Shipping $shipping_line The WooCommerce order item shipping.
	 * @return string
	 */
	private function get_shipping_line_id( $order, $shipping_line ) {
		$order_meta_shipping_line_id = $order->get_meta( '_dintero_shipping_line_id' );

		// If the metadata was set, return it.
		if ( ! empty( $order_meta_shipping_line_id ) ) {
			return $order_meta_shipping_line_id;
		}

		/**
		 * Otherwise use the '_wc_dintero_shipping_id' meta if it was set. This is needed to support orders placed before 1.11.0.
		 *
		 * @link https://github.com/Dintero/Dintero.Checkout.WooCommerce.V2/blob/1.10.8/classes/requests/helpers/class-dintero-checkout-order.php#L420-L445
		 */
		$order_meta_shipping_id = $order->get_meta( '_wc_dintero_shipping_id' );
		if ( ! empty( $order_meta_shipping_id ) ) {
			return $order_meta_shipping_id;
		}

		return '';
	}

	/**
	 * Get the formatted shipping object.
	 *
	 * @return array|null
	 */
	public function get_shipping_object() {
		$shipping_lines = $this->get_items( 'shipping' );

		if ( count( $shipping_lines ) === 1 ) {
			/**
			 * Process the shipping line.
			 *
			 * @var WC_Order_Item_Shipping $shipping_line The shipping line.
			 */
			$shipping_line = array_values( $shipping_lines )[0];

			// Retrieve the shipping id from the order object itself.
			$id      = $this->order->get_meta( '_wc_dintero_shipping_id' );
			$line_id = '';

			// WC_Order_Refund do not share the same meta data as WC_Order, and is thus missing the shipping id meta data.
			if ( empty( $id ) ) {
				$parent_order = wc_get_order( $this->order->get_parent_id() );
				// The initial subscription does not have a parent order, we must therefore account for this.
				$id      = ! empty( $parent_order ) ? $parent_order->get_meta( '_wc_dintero_shipping_id' ) : '';
				$line_id = ! empty( $parent_order ) ? $this->get_shipping_line_id( $parent_order, $shipping_line ) : '';
			}

			if ( empty( $line_id ) ) {
				$line_id = $this->get_shipping_line_id( $this->order, $shipping_line );
			}

			if ( empty( $line_id ) ) {
				// If we get here, use the shipping method id and instance id as a final fallback to use the same fallback the plugin has always used.
				$line_id = "{$shipping_line->get_method_id()}:{$shipping_line->get_instance_id()}";
			}

			if ( empty( $id ) ) {
				// Prevent sending the shipping line with an empty id.
				$id = "{$shipping_line->get_method_id()}:{$shipping_line->get_instance_id()}";
			}

			$shipping_total     = floatval( $shipping_line->get_total() );
			$shipping_total_tax = floatval( $shipping_line->get_total_tax() );

			return array(
				'id'          => $id,
				'line_id'     => $line_id,
				'amount'      => absint( self::format_number( $shipping_total + $shipping_total_tax ) ),
				'operator'    => '',
				'description' => '',
				'title'       => $shipping_line->get_method_title(),
				'vat_amount'  => self::format_number( $shipping_total_tax ),
				'vat'         => $shipping_total <= 0 ? 0 : self::format_number( $shipping_total_tax / $shipping_total ),
			);
		}

		return null;
	}

	/**
	 * Retrieve the customer's billing address.
	 *
	 * @return array An associative array representing the billing address.
	 */
	public function get_billing_address() {
		$billing_address = array(
			'first_name'     => $this->order->get_billing_first_name(),
			'last_name'      => $this->order->get_billing_last_name(),
			'address_line'   => $this->order->get_billing_address_1(),
			'address_line_2' => $this->order->get_billing_address_2(),
			'business_name'  => $this->order->get_billing_company(),
			'postal_code'    => $this->order->get_billing_postcode(),
			'postal_place'   => $this->order->get_billing_city(),
			'country'        => $this->order->get_billing_country(),
			'email'          => $this->order->get_billing_email(),
		);

		$phone = $this->order->get_billing_phone();
		if ( ! empty( $phone ) ) {
			$billing_address['phone_number'] = wc_sanitize_phone_number( $phone );
		}

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
			'first_name'     => $this->order->get_shipping_first_name(),
			'last_name'      => $this->order->get_shipping_last_name(),
			'address_line'   => $this->order->get_shipping_address_1(),
			'address_line_2' => $this->order->get_shipping_address_2(),
			'business_name'  => $this->order->get_shipping_company(),
			'postal_code'    => $this->order->get_shipping_postcode(),
			'postal_place'   => $this->order->get_shipping_city(),
			'country'        => $this->order->get_shipping_country(),
			'email'          => $this->order->get_billing_email(),
		);

		// Check if a shipping phone number exist. Default to billing phone.
		$phone = $this->order->get_shipping_phone();
		$phone = ! empty( $phone ) ? $phone : $this->order->get_billing_phone();
		if ( ! empty( $phone ) ) {
			$shipping_address['phone_number'] = wc_sanitize_phone_number( $phone );
		}

		// Check if a shipping email exist. Default to billing email.
		$email                     = $this->order->get_meta( '_shipping_email' );
		$email                     = ! empty( $email ) ? $email : $this->order->get_billing_email();
		$shipping_address['email'] = is_email( $email ) ? sanitize_email( $email ) : '';

		/* Sanitize all values. Remove all empty elements (required by Dintero). */
		return array_filter(
			$shipping_address,
			function ( $value ) {
				return ! empty( wc_clean( $value ) );
			}
		);
	}
}
