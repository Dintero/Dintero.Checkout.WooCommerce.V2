<?php
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
abstract class Dintero_API {

	/**
	 * Create a new Dintero session.
	 *
	 * @param string $dintero_id The Dintero order id.
	 * @return mixed
	 */
	abstract public function create_session( $dintero_id );

	/**
	 * Retrieve information about a WooCommerce order at Dintero.
	 *
	 * @param string $dintero_id The Dintero order id.
	 * @return mixed
	 */
	abstract public function get_order( $dintero_id);

	/**
	 * Update the state of the order in WooCommerce to match Dintero's.
	 *
	 * @param string $dintero_id The Dintero order id.
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return mixed
	 */
	abstract public function update_order( $dintero_id, $order_id);

	/**
	 * Acknowledge that the order was completed.
	 *
	 * @param string $dintero_id The Dintero order id.
	 * @return mixed
	 */
	abstract public function acknowledge( $dintero_id);

	/**
	 * Check if the request resulted in an API error.
	 *
	 * @param object $response The API response object.
	 * @return object|WP_Error The same response object on success or WP_Error on failure.
	 */
	private function check_for_api_error( $response ) {
		if ( is_wp_error( $response ) ) {
			// @TODO: print_error_message
			error_log( var_export( 'TODO: print_error_message - ' . __FILE__ . ': ' . __LINE__, true ) );
		}

		return $response;
	}
}
