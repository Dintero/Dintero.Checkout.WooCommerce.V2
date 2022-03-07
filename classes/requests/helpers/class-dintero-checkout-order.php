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
		$this->order = wc_get_order( $order_id );
	}

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
		foreach ( $this->order->get_items( 'shipping' ) as $order_item ) {
			$order_lines[] = $this->get_shipping( $order_item );
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
			'quantity'    => $order_item->get_quantity(),
			'amount'      => self::format_number( $order_item->get_total() + $order_item->get_total_tax() ),
			'vat_amount'  => self::format_number( $order_item->get_total_tax() ),
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
			'amount'      => self::format_number( $order_item->get_total() + $order_item->get_total_tax() ),
			'vat_amount'  => self::format_number( $order_item->get_total_tax() ),
		);
	}

	/**
	 * Gets the formated order line from shipping.
	 *
	 * @param WC_Order_Item_Shipping $order_item WooCommerce order item shipping.
	 * @return array
	 */
	public function get_shipping( $order_item ) {
		return array(
			/* NOTE: The id and line_id must match the same id and line_id on capture and refund. */
			'id'          => $order_item->get_id(),
			'line_id'     => $order_item->get_id(),
			'amount'      => self::format_number( $order_item->get_total() + $order_item->get_total_tax() ),
			'operator'    => '',
			'description' => '',
			'title'       => $order_item->get_name(),
			'vat_amount'  => self::format_number( $order_item->get_total_tax() ),
			'vat'         => ( 0 !== $order_item->get_total() ) ? self::format_number( $order_item->get_total_tax() / $order_item->get_total() ) : 0,
			'quantity'    => 1,
		);
	}

	/**
	 * Get the formated shipping object.
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
								'id'          => $shipping_method->get_id(),
								'line_id'     => $shipping_method->get_id(),
								'amount'      => self::format_number( $shipping_method->get_cost() + $shipping_method->get_shipping_tax() ),
								'operator'    => '',
								'description' => '',
								'title'       => $shipping_method->get_label(),
								'vat_amount'  => self::format_number( $shipping_method->get_shipping_tax() ),
								'vat'         => ( 0 !== $shipping_method->get_cost() ) ? self::format_number( $shipping_method->get_shipping_tax() / $shipping_method->get_cost() ) : 0,
							);
						} else {
							return array(
								'id'          => $shipping_method->get_id(),
								'line_id'     => $shipping_method->get_id(),
								'amount'      => 0,
								'operator'    => '',
								'description' => '',
								'title'       => $shipping_method->get_label(),
								'vat_amount'  => 0,
								'vat'         => 0,
							);
						}
					}
				}
			}
		}

		return null;
	}
}