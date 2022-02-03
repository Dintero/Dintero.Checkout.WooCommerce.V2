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
	 * @param int $order_id WooCommerce order id.
	 *
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function create( $order_id ) {
		$order = wc_get_order( $order_id );

		$this->request_args = array(
			'headers' => $this->get_headers(),
			'body'    => json_encode(
				array(
					'url'        => array(
						'return_url' => $order->get_checkout_order_received_url(),
					),
					'order'      => ( new Dintero_Checkout_Cart() )->cart( $order_id ),
					'profile_id' => get_option( 'woocommerce_dintero_checkout_settings' )['profile_id'],
				)
			),
		);
		$response           = $this->request();

		Dintero_Logger::log(
			Dintero_Logger::format( '', $this->request_method, 'Create new Dintero session', $response['request'], $response['result'], $response['code'], $this->request_url )
		);

		return $response;
	}
}
