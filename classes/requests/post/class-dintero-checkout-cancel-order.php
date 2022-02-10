<?php //phpcs:ignore
/**
 * Class for canceling the Dintero order from WooCommerce.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for cancelling order (both from WooCommerce and Dintero).
 */
class Dintero_Checkout_Cancel_Order extends Dintero_Checkout_Request {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->request_method = 'POST';
	}

	/**
	 * Cancels the Dintero order.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @return boolean An associative array on success and failure. Check for is_error index.
	 */
	public function cancel( $dintero_id ) {
		$this->request_url  = 'https://checkout.dintero.com/v1/transactions/' . $dintero_id . '/void';
		$this->request_args = array(
			'headers' => $this->get_headers(),
			'body'    => json_encode(
				array(
					'id' => $dintero_id,
				)
			),
		);
		$response           = $this->request();

		Dintero_Logger::log(
			Dintero_Logger::format( $dintero_id, $this->request_method, 'Cancel Dintero order', $response['request'], $response['result'], $response['code'], $this->request_url )
		);

		return $response;
	}
}
