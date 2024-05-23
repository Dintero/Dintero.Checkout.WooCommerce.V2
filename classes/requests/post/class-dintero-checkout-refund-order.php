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
		$order   = wc_get_order( $this->arguments['order_id'] );
		$refunds = $order->get_refunds();
		$order   = reset( $refunds );

		$helper = new Dintero_Checkout_Order( $order );

		$order_lines = $helper->get_order_lines();
		$shipping    = $helper->get_shipping_object();

		if ( ! empty( $shipping ) ) {
			$order_lines[] = $helper::format_shipping_for_om( $shipping );
		}

		return array(
			'refund_reference' => strval( $this->arguments['order_id'] ),
			'amount'           => $helper->get_order_total(),
			'items'            => $order_lines,
			'reason'           => $this->arguments['reason'],
		);
	}
}
