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
	 * @return array|WP_Error
	 */
	public function create_session( $order_id = false ) {
		$args     = array( 'order_id' => $order_id );
		$request  = new Dintero_Checkout_Create_Session( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Retrieve information about a WooCommerce order from Dintero.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @param array  $params Additional URL query parameters.
	 * @return array|WP_Error
	 */
	public function get_order( $dintero_id, $params = array() ) {
		$request = new Dintero_Checkout_Get_Order(
			array(
				'params'     => $params,
				'dintero_id' => $dintero_id,
			)
		);

		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Retrieve information about a WooCommerce order from Dintero.
	 *
	 * @param string $session_id The Dintero session id.
	 * @return array|WP_Error
	 */
	public function get_session( $session_id ) {
		$args     = array( 'session_id' => $session_id );
		$request  = new Dintero_Checkout_Get_session( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Update a Dintero checkout session.
	 *
	 * @param string $session_id The Dintero session id.
	 * @return array|WP_Error
	 */
	public function update_checkout_session( $session_id ) {
		$args     = array( 'session_id' => $session_id );
		$request  = new Dintero_Checkout_Update_Checkout_Session( $args );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Set the Dintero order to captured.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @param int    $order_id The WooCommerce order id.
	 * @return array|WP_Error
	 */
	public function capture_order( $dintero_id, $order_id ) {
		$request  = new Dintero_Checkout_Capture_Order(
			array(
				'dintero_id' => $dintero_id,
				'order_id'   => $order_id,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Set the Dintero order to canceled (void).
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @return array|WP_Error
	 */
	public function cancel_order( $dintero_id ) {
		$request  = new Dintero_Checkout_Cancel_Order(
			array(
				'dintero_id' => $dintero_id,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Set the Dintero order to refunded.
	 *
	 * @param string $dintero_id The Dintero transaction id.
	 * @param int    $order_id The WooCommerce order id.
	 * @param string $reason The given reason for the refund.
	 * @param string $wc_refund Whether a WooCommerce refund exists. Default 'yes'.
	 * @return array|WP_Error
	 */
	public function refund_order( $dintero_id, $order_id, $reason, $wc_refund = 'yes' ) {
		$request  = new Dintero_Checkout_Refund_Order(
			array(
				'dintero_id' => $dintero_id,
				'order_id'   => $order_id,
				'reason'     => $reason,
				'wc_refund'  => $wc_refund,
			)
		);
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Returns a access token.
	 *
	 * @return array|WP_Error
	 */
	public function get_access_token() {
		$request  = new Dintero_Checkout_Get_Access_Token( array() );
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Update a transaction with the correct order number sent as the order reference.
	 *
	 * @param string $transaction_id The Dintero Transaction id.
	 * @param string $order_number The WooCommerce order number.
	 * @return array|WP_Error
	 */
	public function update_transaction( $transaction_id, $order_number ) {
		$request  = new Dintero_Checkout_Update_Transaction(
			array(
				'transaction_id' => $transaction_id,
				'order_number'   => $order_number,
			)
		);
		$response = $request->request();

		return $this->check_for_api_error( $response );
	}

	/**
	 * Initiate payment without customer involvement.
	 *
	 * Used for renewal payments.
	 *
	 * @param int $order_id WC order ID.
	 * @return array|WP_Error
	 */
	public function sessions_pay( $order_id ) {
		$request  = new Dintero_Checkout_Sessions_Pay( array( 'order_id' => $order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Create payment and recurrence tokens without reserving or charging any amount.
	 *
	 * Used on checkout and order-pay pages. For renewal payments, @see Dintero_Checkout_API::sessions_pay.
	 *
	 * @param int|false $order_id Woo order ID. Defaults to null (used for cart).
	 * @return array|WP_Error
	 */
	public function create_payment_token( $order_id = null ) {
		$request  = new Dintero_Checkout_Payment_Token( array( 'order_id' => $order_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Get the session profile for a specific profile id.
	 * Used to get the correct token provider for subscriptions.
	 *
	 * @param int|false $profile_id The profile id to retrieve.
	 * @return array|WP_Error
	 */
	public function get_session_profile( $profile_id = null ) {
		$request  = new Dintero_Checkout_Get_Session_Profile( array( 'profile_id' => $profile_id ) );
		$response = $request->request();
		return $this->check_for_api_error( $response );
	}

	/**
	 * Checks for WP Errors and returns either the response as array.
	 *
	 * @param array $response The response from the request.
	 * @return array|WP_Error
	 */
	private function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			if ( ! is_admin() && ! wp_is_serving_rest_request() ) {
				dintero_print_error_message( $response );
			}
		}
		return $response;
	}
}
