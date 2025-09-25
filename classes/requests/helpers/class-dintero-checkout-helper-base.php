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
	 * Retrieve the shipping method option for a given setting name.
	 *
	 * @param WC_Shipping_Rate|WC_Order_Item_Shipping $shipping The shipping rate or order item shipping to retrieve from.
	 * @param string                                  $setting_name The setting name to retrieve. The prefix 'dintero_' can be omitted.
	 * @return mixed The setting value or null if not found.
	 */
	protected function get_shipping_method_option( $shipping, $setting_name ) {
		if ( $shipping instanceof WC_Order_Item_Shipping ) {
			$settings = get_option( "woocommerce_{$shipping->get_method_id()}_{$shipping->get_instance_id()}_settings", array() );
		} elseif ( $shipping instanceof WC_Shipping_Rate ) {
			$settings = get_option( "woocommerce_{$shipping->method_id}_{$shipping->instance_id}_settings", array() );
		} else {
			$settings = array();
		}

		if ( empty( $settings ) ) {
			return null;
		}

		switch ( $setting_name ) {
			case 'dintero_description':
				// While there is no hard-limit on the description length in the API, we will limit it to 200 characters.
				return mb_substr( trim( $settings[ $setting_name ] ?? '' ), 0, 200 );
			default:
				return $settings[ $setting_name ] ?? null;
		}
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
	 * @return void
	 */
	public static function add_shipping( &$body, $helper, $is_embedded, $is_express, $is_shipping_in_iframe ) {
		// We will always need this if shipping is available, so it will always be added.
		$selected_shipping_option = $helper->get_shipping_option();
		if ( ! empty( $selected_shipping_option ) ) {
			$body['order']['shipping_option'] = $selected_shipping_option;
			WC()->session->set( 'dintero_shipping_line_id', $selected_shipping_option['line_id'] );
		}

		// If its express we need to add the express options.
		if ( $is_embedded && $is_express ) {
			// If the cart does not need shipping, unset shipping, set empty array and shipping_not_required.
			if ( ! WC()->cart->needs_shipping() || Dintero_Checkout_Subscription::cart_has_only_free_trial() ) {
				unset( $body['order']['shipping_option'] );
				$body['express']['shipping_options'] = array();
				$body['express']['shipping_mode']    = 'shipping_not_required';
				return;
			}

			if ( $is_shipping_in_iframe ) {
				$body['express']['shipping_options'] = $helper->get_express_shipping_options();

				// The transient may introduce stale data. Check if the shipping option we retrieved from the transient exist in the list of shipping options.
				$exist = false;
				foreach ( $body['express']['shipping_options'] as $express_shipping_option ) {
					if ( $express_shipping_option['line_id'] === $selected_shipping_option['line_id'] ) {
						$exist = true;
						break;
					}
				}

				if ( ! $exist ) {
					// Delete the transient to force retrieve shipping from WC session instead of transient.
					delete_transient( 'dintero_shipping_data_' . WC()->session->get( 'dintero_merchant_reference' ) );
					$selected_shipping_option = $helper->get_shipping_option();
					if ( ! empty( $selected_shipping_option ) ) {
						$body['order']['shipping_option'] = $selected_shipping_option;
						WC()->session->set( 'dintero_shipping_line_id', $selected_shipping_option['line_id'] );
					}
				}
			} else {
				$body['express']['shipping_options'] = empty( $selected_shipping_option ) ? array() : array( $selected_shipping_option );
			}

			$body['express']['shipping_mode'] = 'shipping_required';
			if ( ! empty( $body['express']['shipping_options'] ) && ! $is_shipping_in_iframe ) {
				$body['express']['shipping_mode'] = 'shipping_not_required';
			}
		}
	}

	/**
	 * Retrieve the icon URL for a given carrier.
	 *
	 * @param string                                  $carrier The carrier name.
	 * @param WC_Shipping_Rate|WC_Order_Item_Shipping $shipping_rate The shipping rate or order item shipping passed to the filter `dwc_shipping_icon`.
	 * @return string URL.
	 */
	protected function get_pickup_point_icon( $carrier, $shipping_rate ) {
		$base_url = DINTERO_CHECKOUT_URL . '/assets/img/shipping';

		$carrier = $this->get_operator( $carrier );
		switch ( strtolower( $carrier ) ) {
			case 'postnord':
			case 'plab':
				$img_url = "$base_url/icon-postnord.svg";
				break;
			case 'dhl':
			case 'dhl freight':
				$img_url = "$base_url/icon-dhl.svg";
				break;
			case 'budbee':
				$img_url = "$base_url/icon-budbee.svg";
				break;
			case 'instabox':
				$img_url = "$base_url/icon-instabox.svg";
				break;
			case 'schenker':
				$img_url = "$base_url/icon-db-schenker.svg";
				break;
			case 'bring':
				$img_url = "$base_url/icon-bring.svg";
				break;
			case 'ups':
				$img_url = "$base_url/icon-ups.svg";
				break;
			case 'fedex':
				$img_url = "$base_url/icon-fedex.svg";
				break;
			case 'local_pickup':
				$img_url = "$base_url/icon-store.svg";
				break;
			case 'deliverycheckout':
				$img_url = "$base_url/icon-neutral.svg";
				break;
			default:
				$img_url = "$base_url/icon-neutral.svg";
				break;
		}

		return apply_filters( 'dwc_shipping_icon', $img_url, $carrier, $shipping_rate );
	}

	/**
	 * Get the operator for a given carrier.
	 *
	 * @param string $carrier The carrier to get the operator for.
	 * @return string The operator.
	 */
	protected function get_operator( $carrier ) {
		$carrier = strtolower( $carrier );

		$supported_carriers = array( 'dhl', 'postnord', 'posten', 'budbee', 'instabox', 'dbschenker', 'bring', 'ups', 'fedex' );
		foreach ( $supported_carriers as $supported_carrier ) {
			if ( strpos( $carrier, $supported_carrier ) !== false ) {
				return $supported_carrier;
			}
		}

		switch ( strtolower( $carrier ) ) {
			case 'postnord':
			case 'plab':
				return 'postnord';

			case 'posten':
			case 'posten-norge':
				return 'posten';

			// What remains is not a supported carrier. We'll just return the value received.
			default:
				return $carrier;
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
