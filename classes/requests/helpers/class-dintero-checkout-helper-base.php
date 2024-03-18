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
abstract class Dintero_Checkout_Helper_Base {
	/**
	 * Format the number according to what prices should be like in Dinteros API.
	 *
	 * @param mixed $number The number to format.
	 * @return int
	 */
	public static function format_number( $number ) {
		return intval( number_format( $number * 100, 0, '', '' ) );
	}

	/**
	 * Helper function to format the shipping item for the order management requests.
	 *
	 * @param array $shipping The shipping object.
	 * @return array
	 */
	public static function format_shipping_for_om( $shipping ) {
		$shipping['quantity'] = 1;
		unset( $shipping['operator'] );
		unset( $shipping['vat'] );
		return $shipping;
	}

	/**
	 * Add shipping to the body depending on settings.
	 *
	 * @param array                                        $body The request body.
	 * @param Dintero_Checkout_Order|Dintero_Checkout_Cart $helper The helper class to use.
	 * @param bool                                         $is_embedded If the request is for an embedded checkout.
	 * @param bool                                         $is_express If the request is for an express checkout.
	 * @param bool                                         $is_shipping_in_iframe If the request is for shipping selections in the iframe.
	 * @param bool                                         $show_express_shipping If we should show shipping options in express checkout.
	 * @return void
	 */
	public static function add_shipping( &$body, $helper, $is_embedded, $is_express, $is_shipping_in_iframe, $show_express_shipping = true ) {
		// We will always need this if shipping is available, so it will always be added.
		$shipping_option = $helper->get_shipping_option();
		if ( ! empty( $shipping_option ) ) {
			$body['order']['shipping_option'] = $shipping_option;
		}

		// If its express we need to add the express options.
		if ( $is_embedded && $is_express ) {
			// If the cart does not need shipping, unset shipping, set empty array and shipping_not_required.
			if ( ! $show_express_shipping || ! WC()->cart->needs_shipping() ) {
				unset( $body['order']['shipping_option'] );
				$body['express']['shipping_options'] = array();
				$body['express']['shipping_mode']    = 'shipping_not_required';
				return;
			}
			if ( $is_shipping_in_iframe ) {
				$body['express']['shipping_options'] = $helper->get_express_shipping_options();
			} else {
				$shipping_option                     = $helper->get_shipping_option();
				$body['express']['shipping_options'] = empty( $shipping_option ) ? array() : array( $shipping_option );
			}

			if ( $is_shipping_in_iframe ) {
				$body['express']['shipping_options'] = $helper->get_express_shipping_options();
			} else {
				$shipping_option                     = $helper->get_shipping_option();
				$body['express']['shipping_options'] = empty( $shipping_option ) ? array() : array( $shipping_option );
			}

			$body['express']['shipping_mode'] = 'shipping_required';
			if ( ! empty( $body['express']['shipping_options'] ) && ! $is_shipping_in_iframe ) {
				$body['express']['shipping_mode'] = 'shipping_not_required';
			}
		}
	}

	/**
	 * Add a rounding line to the body to prevent errors when decimals are off in the calculation.
	 *
	 * @param array $body The request body.
	 * @return void
	 */
	public static function add_rounding_line( &$body ) {
		$rounding_line = array(
			'id'          => 'rounding-fee',
			'line_id'     => 'rounding-fee',
			'description' => __( 'Rounding fee', 'dintero-checkout-for-woocommerce' ),
			'quantity'    => 1,
			'amount'      => 0,
			'vat_amount'  => 0,
			'vat'         => 0,
		);

		$order            = $body['order'];
		$order_total      = $order['amount'];
		$shipping_total   = isset( $order['shipping_option'] ) ? $order['shipping_option']['amount'] : 0;
		$order_line_total = 0;
		foreach ( $order['items'] as $item ) {
			$order_line_total += $item['amount'];
		}

		$rounding_line['amount'] = $order_total - ( $shipping_total + $order_line_total );

		// If the rounding amount is not zero, add it to the order.
		if ( 0 !== $rounding_line['amount'] ) {
			$body['order']['items'][] = $rounding_line;
		}
	}

	/**
	 * Add a rounding line to the body to prevent errors when decimals are off in the calculation.
	 *
	 * @param array $body The request body.
	 * @return void
	 */
	public static function add_om_rounding_line( &$body ) {
		$rounding_line = array(
			'id'          => 'rounding-fee',
			'line_id'     => 'rounding-fee',
			'description' => __( 'Rounding fee', 'dintero-checkout-for-woocommerce' ),
			'quantity'    => 1,
			'amount'      => 0,
			'vat_amount'  => 0,
			'vat'         => 0,
		);

		$order_total      = $body['amount'];
		$order_line_total = 0;
		foreach ( $body['items'] as $item ) {
			$order_line_total += $item['amount'];
		}

		$rounding_line['amount'] = $order_total - $order_line_total;

		// If the rounding amount is not zero, add it to the order.
		if ( 0 !== $rounding_line['amount'] ) {
			$body['items'][] = $rounding_line;
		}
	}

	/**
	 * Get the product's image URL.

	 * @param  WC_Product|WC_Order_Item_Product $product Product.
	 * @return string|false $image_url Product image URL. FALSE if no image is found.
	 */
	public static function get_product_image_url( $product ) {
		$image_url = false;
		if ( $product->get_image_id() > 0 ) {
			$image_id  = $product->get_image_id();
			$image_url = wp_get_attachment_image_url( $image_id, 'shop_single', false );
		}

		return $image_url;
	}
}
