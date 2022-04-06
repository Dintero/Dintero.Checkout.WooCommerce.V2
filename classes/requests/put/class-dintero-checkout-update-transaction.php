<?php //phpcs:ignore
/**
 * Class for updating a Dintero transaction.
 *
 * @package Dintero_Checkout/Classes/Requests/Put
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for updating a Dintero transaction.
 */
class Dintero_Checkout_Update_Transaction extends Dintero_Checkout_Request_Put {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title = 'Update Dintero Transaction.';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return "{$this->get_api_url_base()}transactions/{$this->arguments['transaction_id']}";
	}

	/**
	 * Returns the body for the request.
	 *
	 * @return array
	 */
	public function get_body() {
		$body = array(
			'merchant_reference_2' => $this->arguments['order_number'],
		);
		return $body;
	}
}
