<?php
/**
 * Main request class
 *
 * @package Dintero_Checkout/Classes/Requests
 */

defined( 'ABSPATH' ) || exit;

/**
 * Base class for all request classes.
 */
abstract class Dintero_Checkout_Request {

	/**
	 * The request method.
	 *
	 * @var string
	 */
	protected $method;

	/**
	 * The request title.
	 *
	 * @var string
	 */
	protected $log_title;

	/**
	 * The Dintero order id.
	 *
	 * @var string
	 */
	protected $dintero_order_id;

	/**
	 * The request arguments.
	 *
	 * @var array
	 */
	protected $arguments;

	/**
	 * The plugin settings.
	 *
	 * @var array
	 */
	protected $settings;


	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request args.
	 */
	public function __construct( $arguments = array() ) {
		$this->arguments = $arguments;
		$this->load_settings();
	}

	/**
	 * Loads the Dintero Checkout settings and sets them to be used here.
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
	 * Get the API base URL.
	 *
	 * @return string
	 */
	protected function get_api_url_base() {
		return 'https://checkout.dintero.com/v1/';
	}

	/**
	 * Create the required request headers.
	 *
	 * @return array Required request headers.
	 */
	protected function get_request_headers() {
		return array(
			'authorization'                 => $this->get_access_token(),
			'content-type'                  => 'application/json; charset=utf-8',
			'accept'                        => 'application/json',
			'dintero-system-name'           => 'woocommerce',
			'dintero-system-version'        => WC()->version,
			'dintero-system-plugin-name'    => 'Dintero.Checkout.WooCommerce.V2',
			'dintero-system-plugin-version' => DINTERO_CHECKOUT_VERSION,
		);
	}

	/**
	 * Get the access token from Dintero.
	 *
	 * @return string
	 */
	private function get_access_token() {
		$access_token = get_transient( 'dintero_checkout_access_token' );
		if ( $access_token ) {
			return $access_token;
		}

		$response = Dintero()->api->get_access_token();

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$access_token = $response['token_type'] . ' ' . $response['access_token'];
		set_transient( 'dintero_checkout_access_token', $access_token, absint( $response['expires_in'] ) );
		return $access_token;
	}

	/**
	 * Get the user agent.
	 *
	 * @return string
	 */
	protected function get_user_agent() {
		return apply_filters(
			'http_headers_useragent',
			'WordPress/' . get_bloginfo( 'version' ) . '; ' . get_bloginfo( 'url' )
		) . ' - WooCommerce: ' . WC()->version . ' - Dintero Checkout: ' . DINTERO_CHECKOUT_VERSION . ' - PHP Version: ' . phpversion() . ' - Krokedil';
	}

	/**
	 * Get the request args.
	 *
	 * @return array
	 */
	abstract protected function get_request_args();

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	abstract protected function get_request_url();

	/**
	 * Make the request.
	 *
	 * @return object|WP_Error
	 */
	public function request() {
		$url      = $this->get_request_url();
		$args     = $this->get_request_args();
		$response = wp_remote_request( $url, $args );
		return $this->process_response( $response, $args, $url );
	}

	/**
	 * Processes the response checking for errors.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request url.
	 * @return array|WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->log_response( $response, $request_args, $request_url );

		// The request succeeded, check for API errors.
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code > 200 ) {
			if ( ! is_null( json_decode( $response['body'], true ) ) ) {
				$data   = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
				$errors = json_decode( $response['body'], true )['error'];

				return new WP_Error( $code, $errors, $data );
			}

			return array(
				'code'     => $code,
				'result'   => $errors,
				'request'  => $this->request_args,
				'is_error' => true,
			);
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}

	/**
	 * Logs the response from the request.
	 *
	 * @param object|WP_Error $response The response from the request.
	 * @param array           $request_args The request args.
	 * @param string          $request_url The request URL.
	 * @return void
	 */
	protected function log_response( $response, $request_args, $request_url ) {
		$body = json_decode( $response['body'], true );

		$method   = $this->method;
		$title    = $this->log_title;
		$code     = wp_remote_retrieve_response_code( $response );
		$order_id = $body['id'] ?? null;
		$log      = Dintero_Checkout_Logger::format_log( $order_id, $method, $title, $request_args, $response, $code, $request_url );
		Dintero_Checkout_Logger::log( $log );
	}

	/**
	 * Returns if we are currently using the embedded flow.
	 *
	 * @return boolean
	 */
	public function is_embedded() {
		if ( 'embedded' !== $this->settings['form_factor'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns if we are currently uses the express flow.
	 *
	 * @return boolean
	 */
	public function is_express() {
		if ( 'express' !== $this->settings['checkout_type'] ) {
			return false;
		}

		return true;
	}

	/**
	 * Returns if shipping is handled by the iframe.
	 *
	 * @return boolean
	 */
	public function is_shipping_in_iframe() {
		if ( ! $this->is_embedded() ||
			! $this->is_express() ||
			! isset( $this->settings['express_shipping_in_iframe'] ) ||
			'yes' !== $this->settings['express_shipping_in_iframe']
		) {
			return false;
		}

		return true;
	}
}
