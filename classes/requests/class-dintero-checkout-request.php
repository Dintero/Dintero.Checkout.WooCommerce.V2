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
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * The request HTTP method.
	 *
	 * @var string
	 */
	protected $request_method;

	/**
	 * The request URL.
	 *
	 * @var string
	 */
	protected $request_url;

	/**
	 * The request data.
	 *
	 * @var array
	 */
	protected $request_args;

	/**
	 * Retrieve the Dintero Checkout plugin settings.
	 *
	 * @return void
	 */
	protected function load_settings() {
		$this->settings = get_option( 'woocommerce_dintero_checkout_settings' );
	}

	/**
	 * Retrieve the environment flag
	 *
	 * @return string T for test mode or P for production mode.
	 */
	protected function environment() {
		return ( 'yes' === $this->settings['test_mode'] ) ? 'T' : 'P';
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
			'Authorization'                 => $this->get_access_token(),
			'Content-Type'                  => 'application/json; charset=utf-8',
			'Accept'                        => 'application/json',
			'Dintero-System-Name'           => 'woocommerce',
			'Dintero-System-Version'        => WC()->version,
			'Dintero-System-Plugin-Name'    => 'Dintero.Checkout.WooCommerce.V2',
			'Dintero-System-Plugin-Version' => DINTERO_CHECKOUT_VERSION,
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

		$this->request_url    = 'https://checkout.dintero.com/v1/accounts/' . $this->environment() . $this->settings['account_id'] . '/auth/token';
		$this->request_method = 'POST';

		$this->request_args = array(
			'headers' => array(
				'Authorization' => $this->calculate_auth(),
				'Content-Type'  => 'application/json; charset=utf-8',
				'Accept'        => 'application/json',
			),
			'body'    => json_encode(
				array(
					'grant_type' => 'client_credentials',
					'audience'   => 'https://api.dintero.com/v1/accounts/' . $this->environment() . $this->settings['account_id'],
				)
			),
		);

		$response = $this->request();
		Dintero_Logger::log(
			Dintero_Logger::format( '', $this->request_method, 'Generate new access token', $response['request'], $response['result'], $response['code'], $this->request_url )
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
	public function process_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return array(
				'code'     => $response->get_error_code(),
				'result'   => $response->get_error_message(),
				'request'  => $this->request_args,
				'is_error' => true,
			);
		}

		// The request succeeded, check for API errors.
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code > 200 ) {
			if ( ! is_null( json_decode( $response['body'], true ) ) ) {
				$errors = json_decode( $response['body'], true )['error'];

				return array(
					'code'     => $code,
					'result'   => $errors,
					'request'  => $this->request_args,
					'is_error' => true,
				);
			}
		}

		// All good.
		return array(
			'code'     => $code,
			'result'   => json_decode( wp_remote_retrieve_body( $response ), true ),
			'request'  => $this->request_args,
			'is_error' => false,
		);
	}

	/**
	 * Issue request.
	 *
	 * @return array An associative array on success and failure. Check for is_error index.
	 */
	public function request() {
		if ( 'post' === strtolower( $this->request_method ) ) {
			return $this->process_response(
				wp_remote_post(
					$this->request_url,
					$this->request_args
				),
			);
		}

		return $this->process_response(
			wp_remote_request(
				$this->request_url,
				$this->request_args
			),
		);

	}
}
