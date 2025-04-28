<?php //phpcs:ignore
/**
 * Class for retrieving session information from Dintero.
 *
 * @package Dintero_Checkout/Classes/Requests/Get
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for retrieving session information.
 */
class Dintero_Checkout_Get_Session extends Dintero_Checkout_Request_Get {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Get Dintero session.';
		$this->request_filter = 'dintero_checkout_get_session_args';
	}

	/**
	 * Gets the request URL.
	 *
	 * @return string
	 */
	public function get_request_url() {
		return "{$this->get_api_url_base()}sessions/{$this->arguments['session_id']}";
	}
}
