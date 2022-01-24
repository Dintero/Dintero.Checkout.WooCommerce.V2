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
		// TODO: Used for testing purposes only.
		$this->get_access_token();
	}

	/**
	 * Retrieve the Dintero Checkout plugin settings.
	 *
	 * @return void
	 */
	protected function load_settings() {
		$this->settings = get_option( 'woocommerce_dintero_checkout_settings' );
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
	 * @return string|array An access token string on success or an associative array on failure. Check for is_error index.
	 */
	private function get_access_token() {

		// Check if the token has expired (or been deleted before expiration).
		$access_token = get_transient( 'dintero_checkout_access_token' );
		if ( $access_token ) {
			return $access_token;
		}

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
		Dintero_Logger::log(
			Dintero_Logger::format( '', 'POST', 'Generate new access token', $response['request'], $response['result'], $response['code'], $request_url )
		);

		if ( ! $response['is_error'] ) {
			$access_token = $response['result']['token_type'] . ' ' . $response['result']['access_token'];
			set_transient( 'dintero_checkout_access_token', $access_token, $response['result']['expires_in'] );
			return $access_token;
		}

		return $response;
	}

	/**
	 * Validates the API request.
	 *
	 * @param array|WP_Error $response The received response from the HTTP request.
	 * @param array          $request_args The header and payload (if applicable).
	 * @param string         $request_url The request URL.
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function process_response( $response, $request_args = array(), $request_url = '' ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'code'         => $response->get_error_code(),
				'result'       => $response->get_error_message(),
				'request_data' => $request_args,
				'is_error'     => true,
			);
		}

		// The request succeeded, check for API errors.
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code > 200 ) {
			$error_message = '';

			if ( ! is_null( json_decode( $response['body'], true ) ) ) {
				$errors = json_decode( $response['body'], true );

				foreach ( $errors['error_messages'] as $error ) {
					$error_message .= ' ' . $error;
				}
			}

			return array(
				'code'     => $code,
				'result'   => $error_message,
				'request'  => $request_args,
				'is_error' => true,
			);
		}

		// All good.
		return array(
			'code'     => $code,
			'result'   => json_decode( wp_remote_retrieve_body( $response ), true ),
			'request'  => $request_args,
			'is_error' => false,
		);
	}
}
