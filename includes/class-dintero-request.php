<?php
/**
 * Dinteor API Request
 *
 * @package Dintero
 */

/**
 * Dintero Request Class
 */
class Dintero_Request {

	/**
	 * Headers
	 *
	 * @var array
	 */
	protected $headers = array();

	/**
	 * Payload
	 *
	 * @var string $payload
	 */
	protected $payload;

	/**
	 * URL
	 *
	 * @var string $url
	 */
	protected $url;

	/**
	 * Auth user
	 *
	 * @var string $auth_user
	 */
	protected $auth_user;

	/**
	 * Auth password
	 *
	 * @var string $auth_pass
	 */
	protected $auth_pass;

	/**
	 * Setting headers
	 *
	 * @param array $headers headers array.
	 * @return $this
	 */
	public function set_headers( $headers ) {
		$this->headers = (array) $headers;
		return $this;
	}

	/**
	 * Adding headers
	 *
	 * @param string $name header name.
	 * @param string $value header value.
	 * @return $this
	 */
	public function add_header( $name, $value ) {
		$this->headers[ $name ] = $value;
		return $this;
	}

	/**
	 * Setting body
	 *
	 * @param string $data payload.
	 * @return $this
	 */
	public function set_body( $data ) {
		$this->payload = (string) $data;
		return $this;
	}

	/**
	 * Setting username and password for request
	 *
	 * @param string $user username.
	 * @param string $pass password.
	 * @return $this
	 */
	public function set_auth_params( $user, $pass ) {
		// @codingStandardsIgnoreStart
		$this->add_header( 'Authorization', 'Basic ' . base64_encode( $user . ':' . $pass ) );

		// @codingStandardsIgnoreEnd
		return $this;
	}

	/**
	 * Retrieving headers
	 *
	 * @return array
	 */
	public function get_headers() {
		return $this->headers;
	}

	/**
	 * Retrieving user
	 *
	 * @return string
	 */
	public function get_auth_user() {
		return $this->auth_user;
	}

	/**
	 * Retrieving auth password
	 *
	 * @return string
	 */
	public function get_auth_pass() {
		return $this->auth_pass;
	}

	/**
	 * Retrieving body
	 *
	 * @return string
	 */
	public function get_body() {
		return $this->payload;
	}

	/**
	 * Retrieving URL
	 *
	 * @return string
	 */
	public function get_url() {
		return $this->url;
	}
}
