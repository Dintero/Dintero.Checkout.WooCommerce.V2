<?php //phpcs:ignore
/**
 * Class for capturing the Dintero order from WooCommerce.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for capturing order (both from WooCommerce and Dintero).
 */
class Dintero_Checkout_Capture_Order extends Dintero_Checkout_Request {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->request_method = 'POST';
	}

	/**
	 * Capture the Dintero order.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @param string $order_id The WooCommerce order id.
	 * @return boolean An associative array on success and failure. Check for is_error index.
	 */
	public function capture( $dintero_id, $order_id ) {
		$order              = wc_get_order( $order_id );
		$this->request_url  = 'https://checkout.dintero.com/v1/transactions/' . $dintero_id . '/capture';
		$this->request_args = array(
			'headers' => $this->get_headers(),
			'body'    => json_encode(
				array(
					'amount'            => intval( number_format( $order->get_total() * 100, 0, '', '' ) ),
					'capture_reference' => strval( $order_id ),
					/* TODO: Add the 'items' field which is required for certain payment methods. */
				)
			),
		);
		$response = $this->request();

		Dintero_Logger::log(
			Dintero_Logger::format( $dintero_id, $this->request_method, 'Capture order', $response['request'], $response['result'], $response['code'], $this->request_url )
		);

		// TODO: Handle the response.

		return $response;
	}
}
