<?php //phpcs:ignore
/**
 * Class for capturing the Dintero order from within WooCommerce.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for capturing order (both from WooCommerce and Dintero).
 */
class Dintero_Checkout_Capture_Order extends Dintero_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title = 'Capture Dintero order.';
	}

	/**
	 * Get the request URL.
	 *
	 * @return string
	 */
	public function get_request_url() {
		return "{$this->get_api_url_base()}transactions/{$this->arguments['dintero_id']}/capture";
	}

	/**
	 * Get the request body.
	 *
	 * @return array
	 */
	public function get_body() {
		$items = ( new Dintero_Checkout_Order( $this->arguments['order_id'] ) )->items();
		return array(
			'capture_reference' => strval( $this->arguments['order_id'] ),
			'amount'            => $items['total_amount'],
			'items'             => $items['items'],
		);
	}
}
