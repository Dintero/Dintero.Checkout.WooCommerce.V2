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
class Dintero_Checkout_Order {

	/**
	 * All the items in the cart, but may contain other items that behave similar to products (used by third-part plugins).
	 *
	 * @var array
	 */
	private $items = array();

	/**
	 * The total amount for all items as an integer (in the smallest unit for the currency).
	 *
	 * @var int
	 */
	private $total_amount;

	/**
	 * The WooCommerce order id.
	 *
	 * @var int
	 */
	private $order_id;

	/**
	 * Class constructor
	 *
	 * @param int $order_id The WooCommerce order id.
	 */
	public function __construct( $order_id ) {
		$this->order_id = $order_id;
	}

	/**
	 * The order items and total amount to be refunded.
	 *
	 * @return array An associative array with with the integer 'total_amount', array 'items' and string 'reason'.
	 */
	public function items() {
		$this->order_items();
		$this->shipping_option();
		$this->fee_items();

		return array(
			'total_amount' => $this->total_amount,
			'items'        => $this->items,
			'reason'       => ( method_exists( $this->order(), 'get_reason' ) ) ? strval( $this->order()->get_reason() ) : '',
		);
	}

	/**
	 * Retrieve the refunded order.
	 *
	 * @return WC_Order|WC_Order_Refund
	 */
	private function order() {
		$order   = wc_get_order( $this->order_id );
		$refunds = $order->get_refunds();

		if ( count( $refunds ) > 0 ) {
			return $refunds[0];
		}

		return $order;
	}

	/**
	 * Retrieve all the cart products.
	 *
	 * @return void
	 */
	private function order_items() {
		foreach ( $this->order()->get_items() as $item ) {
			$id      = ( empty( $item['variation_id'] ) ) ? $item['product_id'] : $item['variation_id'];
			$product = wc_get_product( $id );

			$order_item = array(
				/* NOTE: The id and line_id must match the same id and line_id in session creation. */
				'id'          => strval( $id ),
				'line_id'     => strval( $id ),
				'description' => $product->get_name(),
				'quantity'    => absint( $item['quantity'] ),
				'amount'      => absint( number_format( $item['line_total'] * 100, 0, '', '' ) ),
				'vat_amount'  => absint( number_format( $item['line_tax'] * 100, 0, '', '' ) ),
			);

			if ( $product->is_taxable() ) {
				$tax_rate          = WC_TAX::get_base_tax_rates( $product->get_tax_class() );
				$order_item['vat'] = empty( $tax_rate ) ? 0 : reset( $tax_rate )['rate'];
			}

			$order_item['amount'] += $order_item['vat_amount'];
			$this->total_amount   += $order_item['amount'];
			$this->items[]         = $order_item;
		}

	}

	/**
	 * Retrieve all the fee items.
	 *
	 * @param WC_Order $order WooCommerce Order.
	 * @return void
	 */
	private function fee_items() {

		foreach ( $this->order()->get_fees() as $fee ) {
			$name     = $fee->get_name();
			$fee_item = array(
				/* NOTE: The id and line_id must match the same id and line_id in session creation. */
				'id'          => $name,
				'line_id'     => $name,
				'description' => $name,
				'quantity'    => $fee->get_quantity(),
				'amount'      => absint( number_format( $fee->get_total() * 100, 0, '', '' ) ),
				'vat_amount'  => absint( number_format( $fee->get_total_tax() * 100, 0, '', '' ) ),
			);

			$fee_item['amount'] += $fee_item['vat_amount'];
			$fee_item['vat']     = ( ! empty( $fee_item['vat_amount'] ) ) ? intval( number_format( $fee_item['vat_amount'] / $fee_item['amount'] * 100, 0, '', '' ) ) : 0;
			$this->total_amount += $fee_item['amount'];
			$this->items[]       = $fee_item;
		}
	}

	/**
	 * Retrieve all the shipping options.
	 *
	 * @return void
	 */
	private function shipping_option() {

		$shipping_methods = $this->order()->get_shipping_methods();
		foreach ( $shipping_methods as $shipping_method ) {

			$shipping_id     = $shipping_method->get_method_id() . ':' . $shipping_method->get_instance_id();
			$shipping_option = array(
				'id'          => strval( $shipping_id ),
				'line_id'     => strval( $shipping_id ),
				'amount'      => absint( number_format( $shipping_method->get_total() * 100, 0, '', '' ) ),
				'description' => $shipping_method->get_name(),
				'quantity'    => $shipping_method->get_quantity(),
				'vat_amount'  => absint( number_format( $shipping_method->get_total_tax() * 100, 0, '', '' ) ),
				'vat'         => ( 0 === absint( $shipping_method->get_total_tax() ) ) ? 0 : intval( number_format( ( $shipping_method->get_total_tax() / $shipping_method->get_total() ) * 100, 0, '', '' ) ),
			);

			// Dintero needs to know this is an order with multiple shipping options by setting the 'type'. */
			// FIXME: This ENUM has not yet been added in production by Dintero. We'll omit it for now per agreement
			// $shipping_option['type'] = 'shipping';

			$shipping_option['amount'] += $shipping_option['vat_amount'];
			$this->total_amount        += $shipping_option['amount'];
			$this->items[]              = $shipping_option;
		}
	}

}
