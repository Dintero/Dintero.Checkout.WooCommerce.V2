<?php //phpcs:ignore
/**
 * Class for refunding the Dintero order from within WooCommerce.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for refunding order (both from WooCommerce and Dintero).
 */
class Dintero_Checkout_Refund_Order extends Dintero_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title = 'Refund Dintero order.';
	}

	/**
	 * Get the request URL.
	 *
	 * @return string
	 */
	public function get_request_url() {
		return "{$this->get_api_url_base()}transactions/{$this->arguments['dintero_id']}/refund";
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
			'reason'            => $items['reason'],
			'items'             => $items['items'],
		);
	}
}
