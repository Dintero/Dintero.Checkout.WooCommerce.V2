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
class Dintero_Checkout_Get_Order extends Dintero_Checkout_Request_Get {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title = 'Get Dintero order.';
	}

	/**
	 * Gets the request URL.
	 *
	 * @return string
	 */
	public function get_request_url() {
		if ( ! empty( $this->arguments['params'] ) ) {
			$params = http_build_query( $this->arguments['params'] );
			return "{$this->get_api_url_base()}transactions/{$this->arguments['dintero_id']}?{$params}";
		} else {
			return "{$this->get_api_url_base()}transactions/{$this->arguments['dintero_id']}";
		}
	}
}
