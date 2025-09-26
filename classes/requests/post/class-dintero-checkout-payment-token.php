<?php //phpcs:ignore
/**
 * Class for creating payment and recurrence tokens without reserving or charging any amount.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for initiating a payment without involving the customer.
 *
 * @see https://docs.dintero.com/checkout-api.html#tag/session/operation/checkout_payment_token_session_post
 */
class Dintero_Checkout_Payment_Token extends Dintero_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Payment Token.';
		$this->request_filter = 'dintero_checkout_payment_token_args';
	}

	/**
	 * Get the request URL.
	 *
	 * @return string
	 */
	public function get_request_url() {
		return "{$this->get_api_url_base()}sessions/payment-token";
	}

	/**
	 * Get the request body.
	 *
	 * @return array
	 */
	public function get_body() {
		$order          = ! empty( $this->arguments['order_id'] ) ? wc_get_order( $this->arguments['order_id'] ) : null;
		$helper         = ! empty( $order ) ? new Dintero_Checkout_Order( $order ) : new Dintero_Checkout_Cart();
		$token_provider = Dintero_Checkout_Subscription::get_token_provider_string_from_order( $order );

		$body = array(
			'session'        => array(
				'order'    => array(
					'currency'           => $helper->get_currency(),
					'merchant_reference' => $helper->get_merchant_reference(),
				),
				'url'      => array(
					'return_url' => add_query_arg( 'gateway', 'dintero', home_url() ),
				),
				'customer' => array(
					'email'        => $helper->get_billing_address()['email'] ?? '',
					'phone_number' => $helper->get_billing_address()['phone_number'] ?? '',
				),
			),
			'token_provider' => array(
				'payment_product_type' => $token_provider,
				'token_types'          => array( 'payment_token' ),
			),
		);

		if ( ! Dintero_Checkout_Callback::is_localhost() ) {
			$body['url']['callback_url'] = Dintero_Checkout_Callback::callback_url();
		}

		$helper->add_shipping( $body['session'], $helper, $this->is_embedded(), $this->is_express(), $this->is_shipping_in_iframe() );

		// Set if express or not. For order-pay, we default to redirect flow.
		if ( ! is_wc_endpoint_url( 'order-pay' ) && $this->is_express() && $this->is_embedded() ) {
			$customer_types = $this->settings['express_customer_type'];
			switch ( $customer_types ) {
				case 'b2c':
					$body['session']['express']['customer_types'] = array( 'b2c' );
					break;
				case 'b2b':
					$body['session']['express']['customer_types'] = array( 'b2b' );
					break;
				case 'b2bc':
				default:
					$body['session']['express']['customer_types'] = array( 'b2c', 'b2b' );
					break;
			}
		}

		return $body;
	}
}
