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
	 * @return void
	 */
	public static function add_shipping( &$body, $helper, $is_embedded, $is_shipping_in_iframe ) {
		$body['order']['shipping_option'] = $helper::get_single_shipping_cart( $helper ); // This will always be needed.

		if ( $is_embedded ) {
			if ( ! WC()->cart->needs_shipping() ) {
				unset( $body['order']['shipping_option'] );
				$body['express']['shipping_options'] = array();
				$body['express']['shipping_mode']    = 'shipping_not_required';
				return;
			}

			if ( $is_shipping_in_iframe ) {
				$body['express']['shipping_options'] = $helper->get_express_shipping_options();
				$body['express']['shipping_mode']    = 'shipping_required';
				return;
			}

			$packages        = WC()->shipping()->get_packages();
			$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
			$chosen_shipping = $chosen_methods[0];
			foreach ( $packages as $i => $package ) {
				foreach ( $package['rates'] as $method ) {
					if ( $chosen_shipping === $method->id ) {
						$body['express']['shipping_options'] = array( $helper::get_single_shipping_cart( $helper ) );
						$body['express']['shipping_mode']    = 'shipping_required';
					}
				}
			}
		}
	}

	/**
	 * Get the single shipping method for the cart.
	 *
	 * @param Dintero_Checkout_Order|Dintero_Checkout_Cart $helper The helper class to use.
	 * @return array
	 */
	public static function get_single_shipping_cart( $helper ) {
		$packages        = WC()->shipping()->get_packages();
		$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
		$chosen_shipping = $chosen_methods[0];
		foreach ( $packages as $i => $package ) {
			foreach ( $package['rates'] as $method ) {
				if ( $chosen_shipping === $method->id ) {
					return $helper->get_shipping_option( $method );
				}
			}
		}

		return array();
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
			'description' => 'Rounding fee',
			'quantity'    => 1,
			'amount'      => 0,
			'vat_amount'  => 0,
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
			'description' => 'Rounding fee',
			'quantity'    => 1,
			'amount'      => 0,
			'vat_amount'  => 0,
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
}
