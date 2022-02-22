<?php //phpcs:ignore
/**
 * Class for canceling the Dintero order from within WooCommerce.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for cancelling order (both from WooCommerce and Dintero).
 */
class Dintero_Checkout_Cancel_Order extends Dintero_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title = 'Cancel Dintero order.';
	}

	/**
	 * Get the request URL.
	 *
	 * @return string
	 */
	public function get_request_url() {
		return "{$this->get_api_url_base()}transactions/{$this->arguments['dintero_id']}/void";
	}

	/**
	 * Get the request body.
	 *
	 * @return array
	 */
	public function get_body() {
		return array(
			'id' => $this->arguments['dintero_id'],
		);
	}
}
