
<?php //phpcs:ignore
/**
 * Class for updating a checkout session.
 *
 * @package Dintero_Checkout/Classes/Requests/Put
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for a Dintero checkout session.
 */
class Dintero_Checkout_Update_Checkout_Session extends Dintero_Checkout_Request {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->request_method = 'PUT';
	}

	/**
	 * Update the Dintero order.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function update_session( $session_id ) {
		$this->request_url  = 'https://checkout.dintero.com/v1/sessions/' . $session_id;
		$this->request_args = array(
			'headers' => $this->get_headers(),
			'body'    => array(
				'order'       => ( new Dintero_Checkout_Cart() )->cart(),
				'remove_lock' => true,
			),
		);

		$response = $this->request();

		Dintero_Logger::log(
			Dintero_Logger::format( '', $this->request_method, 'Update Dintero checkout session', $response['request'], $response['result'], $response['code'], $this->request_url )
		);

		return $response;
	}
}
