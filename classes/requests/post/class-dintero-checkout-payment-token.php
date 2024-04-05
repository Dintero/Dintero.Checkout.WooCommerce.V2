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
		$order_id     = $this->arguments['order_id'];
		$subscription = Dintero_Checkout_Subscription::get_subscription( $order_id );
		$helper       = new Dintero_Checkout_Order( $order_id );

		$body = array(
			'session'        => array(
				'order'    => array(
					'currency'           => $helper->get_currency(),
					'merchant_reference' => $helper->get_merchant_reference(),
				),
				'url'      => array(
					'return_url' => $subscription->get_change_payment_method_url(),
				),
				'customer' => array(
					'email'        => $subscription->get_billing_email(),
					'phone_number' => $subscription->get_billing_phone(),
				),
			),
			'token_provider' => array(
				'payment_product_type' => 'payex.creditcard',
				'token_types'          => array( 'payment_token' ),
			),
		);

		if ( ! Dintero_Checkout_Callback::is_localhost() ) {
			$body['url']['callback_url'] = Dintero_Checkout_Callback::callback_url();
		}
		return $body;
	}
}
