<?php //phpcs:ignore
/**
 * Class for retrieving order information from Dintero.
 *
 * @package Dintero_Checkout/Classes/Requests/Get
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for retrieving order information.
 */
class Dintero_Checkout_Get_Order extends Dintero_Checkout_Request {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->request_method = 'GET';
	}

	/**
	 * Retrieve order information from Dintero.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @return boolean An associative array on success and failure. Check for is_error index.
	 */
	public function get_order( $dintero_id ) {
		$this->request_url  = 'https://checkout.dintero.com/v1/transactions/' . $dintero_id;
		$this->request_args = array(
			'headers' => $this->get_headers(),
		);
		$response           = $this->request();

		Dintero_Logger::log(
			Dintero_Logger::format( $dintero_id, $this->request_method, 'Get order information', $response['request'], $response['result'], $response['code'], $this->request_url )
		);

		return $response;
	}
}
