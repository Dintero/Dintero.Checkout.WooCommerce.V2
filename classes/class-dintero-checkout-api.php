<?php //phpcs:ignore
/**
 * API Class file.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dintero_API class.
 *
 * Class for handling Dintero API requests.
 */
class Dintero_Checkout_API {

	/**
	 * Create a new Dintero session.
	 *
	 * @param string $order_id WooCommerce transaction id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function create_session( $order_id ) {
		$session = new Dintero_Checkout_Create_Session();
		return $session->create( $order_id );
	}

	/**
	 * Retrieve information about a WooCommerce order from Dintero.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function get_order( $dintero_id ) {
		$order = new Dintero_Checkout_Get_Order();
		return $order->get_order( $dintero_id );
	}

	/**
	 * Set the Dintero order to captured.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function capture_order( $dintero_id, $order_id ) {
		$capture = new Dintero_Checkout_Capture_Order();
		return $capture->capture( $dintero_id, $order_id );
	}

	/**
	 * Set the Dintero order to canceled (void).
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function cancel_order( $dintero_id ) {
		$cancel = new Dintero_Checkout_Cancel_Order();
		return $cancel->cancel( $dintero_id );
	}

	/**
	 * Update the state of the order in WooCommerce to match Dintero's.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function update_order( $dintero_id, $order_id ) {
		// TODO: to implement.
	}

	/**
	 * Acknowledge that the order was completed.
	 *
	 * @param string $order_id WooCommerce Order.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function acknowledge( $order_id ) {
		// TODO: to implement.
	}
}
