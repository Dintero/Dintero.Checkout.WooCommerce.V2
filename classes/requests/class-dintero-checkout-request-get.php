<?php
/**
 * Main class for GET requests.
 *
 * @package Dintero_Checkout_For_WooCommerce/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * The main class for GET requests.
 */
abstract class Dintero_Checkout_Request_Get extends Dintero_Checkout_Request {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );
		$this->method = 'GET';
	}

	/**
	 * Builds the request args for a GET request.
	 *
	 * @return array
	 */
	public function get_request_args() {
		return apply_filters(
			$this->request_filter,
			array(
				'headers'    => $this->get_request_headers(),
				'user-agent' => $this->get_user_agent(),
				'method'     => $this->method,
			)
		);
	}
}
