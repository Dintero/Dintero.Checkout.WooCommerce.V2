<?php
/**
 * Request for listing transactions from Dintero.
 *
 * @package Dintero_Checkout/Requests
 */

namespace Krokedil\Dintero\Requests;

defined( 'ABSPATH' ) || exit;

/**
 * Lists Dintero transactions matching the given query parameters, such as merchant_reference.
 */
class GetTransactions extends \Dintero_Checkout_Request_Get {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments. 'params' holds the query parameters.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Get Dintero transactions.';
		$this->request_filter = 'dintero_checkout_get_transactions_args';
	}

	/**
	 * Gets the request URL.
	 *
	 * @return string
	 */
	public function get_request_url() {
		$params = array_map( 'rawurlencode', (array) ( $this->arguments['params'] ?? array() ) );
		return add_query_arg( $params, "{$this->get_api_url_base()}transactions" );
	}
}
