<?php
/**
 * Class for the request to get the access token.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for the request to get the access token.
 */
class Dintero_Checkout_Get_Access_Token extends Dintero_Checkout_Request_Post {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request args.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Get access token';
		$this->request_filter = 'dintero_checkout_get_access_token_args';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return "{$this->get_api_url_base()}accounts/{$this->environment()}{$this->settings['account_id']}/auth/token";
	}

	/**
	 * Gets the request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Authorization' => $this->calculate_auth(),
			'Content-Type'  => 'application/json; charset=utf-8',
			'Accept'        => 'application/json',
		);
	}

	/**
	 * Returns the body for the request.
	 *
	 * @return array
	 */
	protected function get_body() {
		return array(
			'grant_type' => 'client_credentials',
			'audience'   => "https://api.dintero.com/v1/accounts/{$this->environment()}{$this->settings['account_id']}",
		);
	}

	/**
	 * Calculate the Basic authorization parameter.
	 *
	 * @return string Basic authorization parameter.
	 */
	private function calculate_auth() {
		return 'Basic ' . base64_encode( $this->settings['client_id'] . ':' . $this->settings['client_secret'] ); // phpcs:ignore
	}
}
