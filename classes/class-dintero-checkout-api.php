<?php //phpcs:ignore
/**
 * Class for issuing API request.
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
	public function create_session( $order_id = false ) {
		$args    = array( 'order_id' => $order_id );
		$session = new Dintero_Checkout_Create_Session( $args );
		return $session->request();
	}

	/**
	 * Retrieve information about a WooCommerce order from Dintero.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function get_order( $dintero_id ) {
		$args  = array( 'dintero_id' => $dintero_id );
		$order = new Dintero_Checkout_Get_Order( $args );
		return $order->request();
	}

	/**
	 * Retrieve information about a WooCommerce order from Dintero.
	 *
	 * @param string $session_id The Dintero session id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function get_session( $session_id ) {
		$args  = array( 'session_id' => $session_id );
		$order = new Dintero_Checkout_Get_session( $args );
		return $order->request();
	}

	/**
	 * Update a Dintero checkout session.
	 *
	 * @param string $session_id The Dintero session id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function update_checkout_session( $session_id ) {
		$args   = array( 'session_id' => $session_id );
		$update = new Dintero_Checkout_Update_Checkout_Session( $args );
		return $update->request();
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
	 * Set the Dintero order to refunded.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function refund_order( $dintero_id, $order_id ) {
		$refund = new Dintero_Checkout_Refund_Order();
		return $refund->refund( $dintero_id, $order_id );
	}

	/**
	 * Returns a access token.
	 *
	 * @return array|WP_Error
	 */
	public function get_access_token() {
		$request  = new Dintero_Checkout_Get_Access_Token( array() );
		$response = $request->request();

		return $response;
	}
}
