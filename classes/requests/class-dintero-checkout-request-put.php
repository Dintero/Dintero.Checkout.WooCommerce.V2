<?php
/**
 * Base class for all PUT requests.
 *
 * @package Dintero_Checkout_For_WooCommerce/Classes/Request
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 *  The main class for PUT requests.
 */
abstract class Dintero_Checkout_Request_Put extends Dintero_Checkout_Request {

	/**
	 * Dintero_Checkout_Request_Put constructor.
	 *
	 * @param  array $arguments  The request arguments.
	 */
	public function __construct( $arguments = array() ) {
		parent::__construct( $arguments );
		$this->method = 'PUT';
	}

	/**
	 * Build and return proper request arguments for this request type.
	 *
	 * @return array Request arguments
	 */
	protected function get_request_args() {
		$body = wp_json_encode( apply_filters( 'qliro_one_request_args', $this->get_body() ) );
		return array(
			'headers'    => $this->get_request_headers( $body ),
			'user-agent' => $this->get_user_agent(),
			'method'     => $this->method,
			'timeout'    => apply_filters( 'qliro_one_request_timeout', 10 ),
			'body'       => $body,
		);
	}

	/**
	 * Builds the request args for a POST request.
	 *
	 * @return array
	 */
	abstract protected function get_body();
}