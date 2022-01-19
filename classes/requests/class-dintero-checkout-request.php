<?php
/**
 * Main request class.
 *
 * @package Dintero_Checkout/Classes/Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for all API request classes.
 */
abstract class Dintero_Checkout_Request {

	/**
	 * The request method type.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->load_settings();
		$this->get_access_token();
	}

	/**
	 * Retrieve the Dintero Checkout plugin settings.
	 *
	 * @return void
	 */
	protected function load_settings() {
		$this->settings = get_option( 'woocommerce_dintero_settings' );
	}

	/**
	 * Calculate the Basic authorization parameter.
	 *
	 * @return string Basic authorization parameter.
	 */
	private function calculate_auth() {
		return 'Basic ' . base64_encode( $this->settings['client_id'] . ':' . $this->settings['client_secret'] );
	}

	/**
	 * Create the required request headers.
	 *
	 * @return array Required request headers.
	 */
	protected function get_headers() {

		return array(
			'Authorization' => $this->get_access_token(),
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Generate access token.
	 *
	 * @return string|WP_Error An access token string on success or WP_Error on failure.
	 */
	private function get_access_token() {
		$request_url = 'https://checkout.dintero.com/v1/accounts/' . ( 'yes' === $this->settings['test_mode'] ? 'T' : 'P' ) . $this->settings['account_id'] . '/auth/token';

		$request_args = array(
			'headers' => array(
				'Authorization' => $this->calculate_auth(),
				'Content-Type'  => 'application/json',
			),
			'body'    => json_encode(
				array(
					'grant_type' => 'client_credentials',
					'audience'   => 'https://api.dintero.com/v1/accounts/' . ( 'yes' === $this->settings['test_mode'] ? 'T' : 'P' ) . $this->settings['account_id'],
				)
			),
		);

		$response = $this->process_response(
			wp_remote_post(
				$request_url,
				$request_args
			),
			$request_args,
			$request_url
		);

		if ( ! is_wp_error( $response ) ) {
			return $response['token_type'] . ' ' . $response['access_token'];
		}

		return $response;
	}

	/**
	 * Validates the API request.
	 *
	 * @param object $response The received response from the HTTP request.
	 * @param array  $request_args The header and payload (if any).
	 * @param string $request_url The request URL.
	 * @return mixed The body as an associative array on success or WP_Error on failure.
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// The request succeeded, check for API errors.
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code > 200 ) {
			$data          = 'Request URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';

			if ( ! is_null( json_decode( $response['body'], true ) ) ) {
				$errors = json_decode( $response['body'], true );

				foreach ( $errors['error_messages'] as $error ) {
					$error_message .= ' ' . $error;
				}
			}

			return new WP_Error( $code, $response['body'], $error_message, $data );
		}

		// All good.
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
