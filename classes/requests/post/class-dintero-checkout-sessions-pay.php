<?php //phpcs:ignore
/**
 * Class for initiating a Dintero payment without involving the customer.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for initiating a payment without involving the customer.
 */
class Dintero_Checkout_Sessions_Pay extends Dintero_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Sessions Pay.';
		$this->request_filter = 'dintero_checkout_sessions_pay_args';
	}

	/**
	 * Get the request URL.
	 *
	 * @return string
	 */
	public function get_request_url() {
		return "{$this->get_api_url_base()}sessions/pay";
	}

	/**
	 * Get the request body.
	 *
	 * @return array
	 */
	public function get_body() {
		$order          = wc_get_order( $this->arguments['order_id'] );
		$helper         = new Dintero_Checkout_Order( $order );
		$token_provider = Dintero_Checkout_Subscription::get_token_provider_string_from_order( $order );

		$body = array(
			'session' => array(
				'order'         => array(
					'amount'             => $helper->get_order_total(),
					'currency'           => $helper->get_currency(),
					'merchant_reference' => $helper->get_merchant_reference(),
					'vat_amount'         => $helper->get_tax_total(),
					'items'              => $helper->get_order_lines(),
				),
				'customer'      => array(
					'email'        => $helper->get_billing_address()['email'],
					'phone_number' => $helper->get_billing_address()['phone_number'],
					'tokens'       => array(
						$token_provider => array(
							'payment_token' => Dintero_Checkout_Subscription::get_payment_token( $helper->order->get_id() ),
						),
					),
				),
				'configuration' => array(
					'auto_capture' => false,
				),
			),
			'payment' => array(
				'payment_product_type' => $token_provider,
				'operation'            => 'unscheduled_purchase',
			),
		);

		$shipping = $helper->get_shipping_object();
		if ( ! empty( $shipping ) ) {
			$body['session']['order']['items'][] = $helper::format_shipping_for_om( $shipping );
		}

		if ( ! Dintero_Checkout_Callback::is_localhost() ) {
			$body['url']['callback_url'] = Dintero_Checkout_Callback::callback_url();
		}

		return $body;
	}
}
