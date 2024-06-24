<?php
/**
 * Class for handling session creation request.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dintero_Checkout_Create_Session class.
 */
class Dintero_Checkout_Create_Session extends Dintero_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Create Dintero session.';
		$this->request_filter = 'dintero_checkout_create_session_args';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return "{$this->get_api_url_base()}sessions-profile";
	}

	/**
	 * Returns the body for the request.
	 *
	 * @return array
	 */
	public function get_body() {
		$helper = ! empty( $this->arguments['order_id'] ) ? new Dintero_Checkout_Order( wc_get_order( $this->arguments['order_id'] ) ) : new Dintero_Checkout_Cart();

		$body = array(
			'url'        => array(
				'return_url' => add_query_arg( 'gateway', 'dintero', home_url() ),
			),
			'order'      => array(
				'amount'             => $helper->get_order_total(),
				'currency'           => $helper->get_currency(),
				'merchant_reference' => $helper->get_merchant_reference(),
				'vat_amount'         => $helper->get_tax_total(),
				'items'              => $helper->get_order_lines(),
				'store'              => array(
					'id' => preg_replace( '/(https?:\/\/|www.|\/\s*$)/i', '', get_home_url() ),
				),
			),
			'profile_id' => $this->settings['profile_id'],
		);
    
		// 'billing' => Default to customer billing address
		// 'shipping' => Default to customer shipping address
		// 'billing_only' => Force shipping to the customer billing address only.
		$shipping_destination = get_option( 'woocommerce_ship_to_destination' );

		$separate_shipping = wc_string_to_bool( $this->settings['express_allow_different_billing_shipping_address'] ?? 'no' );
		if ( 'billing_only' !== $shipping_destination && $separate_shipping ) {
			$customer_type = $this->settings['express_customer_type'];
			$customer_type = 'b2bc' === $customer_type ? array( 'b2c', 'b2b' ) : array( $customer_type );
			$body['configuration']['allow_different_billing_shipping_address'] = $customer_type;

			// By default this configuration is an empty array, therefore, we don't have to set it if $separate_shipping is set to false.
		}

    $billing_address = $helper->get_billing_address();
		if ( ! empty( $billing_address ) ) {
			$body['order']['billing_address'] = $billing_address;
		}

		$shipping_address = $helper->get_shipping_address();
		if ( ! empty( $shipping_address ) ) {
			$body['order']['shipping_address'] = $shipping_address;
		}

		if ( ! Dintero_Checkout_Callback::is_localhost() ) {
			$body['url']['callback_url'] = Dintero_Checkout_Callback::callback_url();
		}

		// Set if express or not. For order-pay, we default to redirect flow.
		if ( ! is_wc_endpoint_url( 'order-pay' ) && $this->is_express() && $this->is_embedded() ) {
			$this->add_express_object( $body );
		}

		$helper::add_shipping( $body, $helper, $this->is_embedded(), $this->is_express(), $this->is_shipping_in_iframe() );
		$helper::add_rounding_line( $body );

		return $body;
	}

	/**
	 * Adds the Express object to the body.
	 *
	 * @param array $body The body array.
	 * @return array
	 */
	public function add_express_object( &$body ) {
		// Set allowed customer types.
		$customer_types = $this->settings['express_customer_type'];
		switch ( $customer_types ) {
			case 'b2c':
				$body['express']['customer_types'] = array( 'b2c' );
				break;
			case 'b2b':
				$body['express']['customer_types'] = array( 'b2b' );
				break;
			case 'b2bc':
			default:
				$body['express']['customer_types'] = array( 'b2c', 'b2b' );
				break;
		}

		return $body;
	}
}
