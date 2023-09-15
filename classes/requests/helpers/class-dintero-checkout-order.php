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
	 * @var WC_Order
	 */
	public $order;

	/**
	 * Class constructor.
	 *
	 * @param int $order_id The WooCommerce order id.
	 */
	public function __construct( $order_id ) {
		$order   = wc_get_order( $order_id );
		$refunds = $order->get_refunds();

		if ( count( $refunds ) > 0 ) {
			$this->order = $refunds[0];
		} else {
			$this->order = $order;
		}
	}

	/**
	 * Get the order total.
	 *
	 * @return int
	 */
	public function get_order_total() {
		return absint( self::format_number( $this->order->get_total() ) );
	}

	/**
	 * Get the tax total.
	 *
	 * @return int
	 */
	public function get_tax_total() {
		return absint( self::format_number( $this->order->get_total_tax() ) );
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
	 * @param $order WC_Order
	 * @return string
	 */
	public function get_merchant_reference( $order ) {
		return $order->get_order_number() ?? strval( $order->get_id() ) ?? uniqid( 'dwc' );
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
		foreach ( $this->order->get_items() as $order_item ) {
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
		if ( count( $this->order->get_items( 'shipping' ) ) > 1 ) {
			/* If there is more than one shipping option, it will be part of the order.items to support multiple shipping packages. */
			foreach ( $this->order->get_items( 'shipping' ) as $order_item ) {
				$order_line = $this->get_shipping_option( $order_item );
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
	 * Get the formatted order line from a cart item.
	 *
	 * @param WC_Order_Item_Product $order_item WooCommerce order item product.
	 * @return array
	 */
	public function get_order_line( $order_item ) {
		$id      = ( empty( $order_item['variation_id'] ) ) ? $order_item['product_id'] : $order_item['variation_id'];
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

		$order_line = array(
			'id'          => $this->get_product_sku( $product ),
			'line_id'     => $line_id,
			'description' => $order_item->get_name(),
			'quantity'    => absint( $order_item->get_quantity() ),
			'amount'      => absint( self::format_number( $order_item->get_total() + $order_item->get_total_tax() ) ),
			'vat_amount'  => absint( self::format_number( $order_item->get_total_tax() ) ),
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
		$name       = $order_item->get_name();
		$amount     = absint( self::format_number( $order_item->get_total() + $order_item->get_total_tax() ) );
		$vat_amount = absint( self::format_number( $order_item->get_total_tax() ) );

		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'          => $name,
			'line_id'     => $name,
			'description' => $name,
			'quantity'    => 1,
			'amount'      => $amount,
			'vat_amount'  => $vat_amount,
			'vat'         => empty( $amount ) ? 0 : $vat_amount / $amount,
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
	 * @param WC_Shipping_rate $shipping_method The shipping method from WooCommerce.
	 * @return array
	 */
	public function get_shipping_item( $shipping_method ) {
		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'         => $shipping_method->get_method_id() . ':' . $shipping_method->get_instance_id(),
			'line_id'    => $shipping_method->get_method_id() . ':' . $shipping_method->get_instance_id(),
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
	 * Gets the formatted order line from shipping.
	 *
	 * @param WC_Order_Item_Shipping $shipping_method WooCommerce order item shipping.
	 * @return array
	 */
	public function get_shipping_option( $shipping_method = null ) {
		if ( empty( $shipping_method ) ) {
			if ( count( $this->order->get_items( 'shipping' ) ) === 1 ) {
				/**
				 * Process order item shipping.
				 *
				 * @var WC_Order_Item_Shipping $order_item WooCommerce order item shipping.
				 */
				foreach ( $this->order->get_items( 'shipping' ) as $order_item ) {
					$shipping_method = $order_item;
				}
			}
		}

		if ( empty( $shipping_method ) ) {
			return array();
		}

		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'              => $shipping_method->get_method_id() . ':' . $shipping_method->get_instance_id(),
			'line_id'         => $shipping_method->get_method_id() . ':' . $shipping_method->get_instance_id(),
			'amount'          => self::format_number( $shipping_method->get_total() + $shipping_method->get_total_tax() ),
			'operator'        => '',
			'description'     => '',
			'title'           => $shipping_method->get_method_title(),
			'delivery_method' => 'unspecified',
			'vat_amount'      => self::format_number( $shipping_method->get_total_tax() ),
			'vat'             => ( empty( floatval( $shipping_method->get_total() ) ) ) ? 0 : self::format_number( $shipping_method->get_total_tax() / $shipping_method->get_total() ),
		);
	}

	/**
	 * Get the formatted shipping object.
	 *
	 * @return array|null
	 */
	public function get_shipping_object() {
		$shipping_lines = $this->order->get_items( 'shipping' );
		if ( count( $shipping_lines ) === 1 ) {
			/**
			 * Process the shipping line.
			 *
			 * @var WC_Order_Item_Shipping $shipping_line The shipping line.
			 */
			$shipping_line = array_values( $shipping_lines )[0];

			// Retrieve the shipping id from the order object itself.
			$shipping_id = get_post_meta( $this->order->get_id(), '_wc_dintero_shipping_id', true );
			if ( empty( $shipping_id ) ) {
				$shipping_id = $shipping_line->get_method_id() . ':' . $shipping_line->get_instance_id();
			}

			return array(
				'id'          => $shipping_id,
				'line_id'     => $shipping_id,
				'amount'      => absint( self::format_number( $shipping_line->get_total() + $shipping_line->get_total_tax() ) ),
				'operator'    => '',
				'description' => '',
				'title'       => $shipping_line->get_method_title(),
				'vat_amount'  => self::format_number( $shipping_line->get_total_tax() ),
				'vat'         => ( ! empty( floatval( $shipping_line->get_total() ) ) ) ? self::format_number( $shipping_line->get_total_tax() / $shipping_line->get_total() ) : 0,
			);
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
	 * Retrieve the customer's billing address.
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return array An associative array representing the billing address.
	 */
	public function get_billing_address( $order ) {
		$billing_address = array(
			'first_name'     => $order->get_billing_first_name(),
			'last_name'      => $order->get_billing_last_name(),
			'address_line'   => $order->get_billing_address_1(),
			'address_line_2' => $order->get_billing_address_2(),
			'business_name'  => $order->get_billing_company(),
			'postal_code'    => $order->get_billing_postcode(),
			'postal_place'   => $order->get_billing_city(),
			'country'        => $order->get_billing_country(),
			'phone_number'   => dintero_sanitize_phone_number( $order->get_billing_phone() ),
			'email'          => $order->get_billing_email(),
		);

		/* Sanitize all values. Remove all empty elements (required by Dintero). */
		return array_filter(
			$billing_address,
			function ( $value ) {
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
	public function get_shipping_address( $order ) {
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
		$phone                            = $order->get_shipping_phone();
		$shipping_address['phone_number'] = dintero_sanitize_phone_number( ! empty( $phone ) ? $phone : $order->get_billing_phone() );

		/* Sanitize all values. Remove all empty elements (required by Dintero). */
		return array_filter(
			$shipping_address,
			function ( $value ) {
				return ! empty( sanitize_text_field( $value ) );
			}
		);
	}
}
