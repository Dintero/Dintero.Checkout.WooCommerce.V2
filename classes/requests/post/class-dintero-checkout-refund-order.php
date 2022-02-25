<?php //phpcs:ignore
/**
 * Class for refunding the Dintero order from within WooCommerce.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for refunding order (both from WooCommerce and Dintero).
 */
class Dintero_Checkout_Refund_Order extends Dintero_Checkout_Request {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->request_method = 'POST';
	}

	/**
	 * Refund the Dintero order.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @param string $order_id The WooCommerce order id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function refund( $dintero_id, $order_id ) {
		$this->request_url  = 'https://checkout.dintero.com/v1/transactions/' . $dintero_id . '/refund';
		$this->request_args = array(
			'headers' => $this->get_headers(),
		);

		$items                      = ( new Dintero_Checkout_Order( $order_id ) )->items();
		$this->request_args['body'] = array(
			'capture_reference' => strval( $order_id ),
			'amount'            => $items['total_amount'],
			'reason'            => $items['reason'],
			'items'             => $items['items'],
		);

		$this->request_args['body'] = json_encode( $this->request_args['body'] );
		$response                   = $this->request();

		Dintero_Logger::log(
			Dintero_Logger::format( $dintero_id, $this->request_method, 'Refund Dintero order', $response['request'], $response['result'], $response['code'], $this->request_url )
		);

		return $response;
	}
}
