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
class Dintero_Checkout_Create_Session extends Dintero_Checkout_Request {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->load_settings();
		$this->request_method = 'POST';
		$this->request_url    = 'https://checkout.dintero.com/v1/sessions-profile';
	}

	/**
	 * Creates a Dintero session.
	 *
	 * @param int $order_id|false WooCommerce order id (for redirect). Defaults to FALSE (for embedded).
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function create( $order_id = false ) {
		$order              = wc_get_order( $order_id );
		$this->request_args = array(
			'headers' => $this->get_headers(),
			'body'    =>
				array(
					'url'        => array(
						'return_url' => add_query_arg(
							array(
								'gateway' => 'dintero',
								'key'     => ( $order ) ? $order->get_order_key() : '',
							),
							home_url()
						),
					),
					'order'      => ( new Dintero_Checkout_Cart() )->cart( $order_id ),
					'profile_id' => get_option( 'woocommerce_dintero_checkout_settings' )['profile_id'],
				),
		);

		// Callbacks require a public HTTP URL.
		if ( ! Dintero_Checkout_Callback::is_localhost() ) {
			$this->request_args['body']['url']['callback_url'] = Dintero_Checkout_Callback::callback_url( $order->get_order_key() );
		}

		$this->request_args['body'] = json_encode( $this->request_args['body'] );
		$response                   = $this->request();

		Dintero_Logger::log(
			Dintero_Logger::format( '', $this->request_method, 'Create new Dintero session', $response['request'], $response['result'], $response['code'], $this->request_url )
		);

		return $response;
	}
}
