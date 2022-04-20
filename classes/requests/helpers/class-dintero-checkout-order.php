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
		$order_lines = array();

		/**
		 * Process order item products.
		 *
		 * @var WC_Order_Item_Product $order_item WooCommerce order item product.
		 */
		foreach ( $this->order->get_items() as $order_item ) {
			$order_lines[] = $this->get_order_line( $order_item );
		}

		/**
		 * Process order item shipping.
		 *
		 * @var WC_Order_Item_Shipping $order_item WooCommerce order item shipping.
		 */
		if ( count( $this->order->get_items( 'shipping' ) ) > 1 ) {
			foreach ( $this->order->get_items( 'shipping' ) as $order_item ) {
				$order_lines[] = $this->get_shipping_option( $order_item );
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

		return array_values( $order_lines );
	}

	/**
	 * Gets the formated shipping lines.
	 *
	 * @return array|null
	 */
	public function get_shipping_lines() {
		return null;
	}

	/**
	 * Get the formated order line from a cart item.
	 *
	 * @param WC_Order_Item_Product $order_item WooCommerce order item product.
	 * @return array
	 */
	public function get_order_line( $order_item ) {
		$id      = ( empty( $order_item['variation_id'] ) ) ? $order_item['product_id'] : $order_item['variation_id'];
		$product = wc_get_product( $id );

		return array(
			'id'          => $this->get_product_sku( $product ),
			'line_id'     => $this->get_product_sku( $product ),
			'description' => $order_item->get_name(),
			'quantity'    => absint( $order_item->get_quantity() ),
			'amount'      => absint( self::format_number( $order_item->get_total() + $order_item->get_total_tax() ) ),
			'vat_amount'  => absint( self::format_number( $order_item->get_total_tax() ) ),
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
	 * Get the formated order line from a fee.
	 *
	 * @param WC_Order_Item_Fee $order_item WooCommerce order item fee.
	 * @return array
	 */
	public function get_fee( $order_item ) {
		$name = $order_item->get_name();
		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'          => $order_item->get_name(),
			'line_id'     => $order_item->get_name(),
			'description' => $order_item->get_name(),
			'quantity'    => 1,
			'amount'      => absint( self::format_number( $order_item->get_total() + $order_item->get_total_tax() ) ),
			'vat_amount'  => absint( self::format_number( $order_item->get_total_tax() ) ),
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
	 * Gets the formated order line from shipping.
	 *
	 * @param WC_Order_Item_Shipping $order_item WooCommerce order item shipping.
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
	 * Get the formated shipping object.
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
			return array(
				'id'          => strval( $shipping_line->get_method_id() . ':' . $shipping_line->get_instance_id() ),
				'line_id'     => strval( $shipping_line->get_method_id() . ':' . $shipping_line->get_instance_id() ),
				'amount'      => absint( self::format_number( $shipping_line->get_total() + $shipping_line->get_total_tax() ) ),
				'operator'    => '',
				'description' => '',
				'title'       => $shipping_line->get_method_title(),
				'vat_amount'  => self::format_number( $shipping_line->get_total_tax() ),
				'vat'         => ( 0 !== $shipping_line->get_total() ) ? self::format_number( $shipping_line->get_total_tax() / $shipping_line->get_total() ) : 0,
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
			function( $value ) {
				return ! empty( sanitize_text_field( $value ) );
			}
		);
	}
}
