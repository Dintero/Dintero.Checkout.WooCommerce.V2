<?php
/**
 * Dintero Adapter Class Doc Comment
 *
 * @category Dintero_Adapter
 * @package  Dintero
 * @author   Dintero
 */

/**
 * Dintero Adapter
 */
class Dintero_Adapter {
	/*
	 * Payment api base url
	 */
	const API_ENDPOINT = 'https://api.dintero.com/v1';

	/*
	 * Checkout api endpoint
	 */
	const CHECKOUT_API_ENDPOINT = 'https://checkout.dintero.com/v1/';

	/**
	 * Setting default headers
	 *
	 * @return array
	 */
	private function default_headers() {
		return array(
			'Content-type'                  => 'application/json; charset=utf-8',
			'Accept'                        => 'application/json',
			'User-Agent'                    => sprintf(
				'Dintero.Checkout.Woocomerce.%s (+https://github.com/Dintero/Dintero.Checkout.Woocomerce),',
				WCDHP()->get_version()
			),
			'Dintero-System-Name'           => 'woocommerce',
			'Dintero-System-Version'        => WC()->version,
			'Dintero-System-Plugin-Name'    => 'Dintero.Checkout.WooCommerce.V2',
			'Dintero-System-Plugin-Version' => WCDHP()->get_version(),
		);
	}

	/**
	 * Building endpoint
	 *
	 * @param string $endpoint endpoint.
	 * @return string
	 */
	private function endpoint( $endpoint ) {
		return self::CHECKOUT_API_ENDPOINT . trim( $endpoint, '/' );
	}

	/**
	 * Initializing request
	 *
	 * @param string|null $access_token access token.
	 * @return Dintero_Request
	 */
	protected function init_request( $access_token = null ) {
		$request = ( new Dintero_Request() )
			->set_headers( $this->default_headers() );

		if ( ! empty( $access_token ) ) {
			$request->add_header( 'Authorization', 'Bearer ' . $access_token );
		}

		return $request;
	}

	/**
	 * Decoding data
	 *
	 * @param string $json json string.
	 * @return array
	 */
	protected function decode( $json ) {
		try {
			return Dintero_Serializer::instance()->unserialize( $json );
		} catch ( Exception $e ) {
			return array(
				'error' => __( 'Could not decode response.' ),
			);
		}
	}

	/**
	 * Retrieving access token
	 *
	 * @return false|string
	 */
	public function get_access_token() {
		$account_id  = WCDHP()->config()->is( 'test_mode' ) ? 'T' : 'P';
		$account_id .= WCDHP()->config()->get( 'account_id' );
		$request     = $this->init_request()
			->set_auth_params(
				WCDHP()->config()->get( 'client_id' ),
				WCDHP()->config()->get( 'client_secret' )
			)
			->set_body(
				wp_json_encode(
					array(
						'grant_type' => 'client_credentials',
						'audience'   => sprintf( '%s/accounts/%s', self::API_ENDPOINT, $account_id ),
					)
				)
			);

		$response = $this->decode(
			wp_remote_retrieve_body(
				_wp_http_get_object()->post(
					$this->endpoint( sprintf( '/accounts/%s/auth/token', $account_id ) ),
					Dintero_Request_Builder::instance()->build( $request )
				)
			)
		);

		return isset( $response['access_token'] ) ? $response['access_token'] : false;
	}

	/**
	 * Retrieving session
	 *
	 * @param string $session_id session id.
	 * @return array
	 */
	public function get_session( $session_id ) {
		$request  = $this->init_request( $this->get_access_token() );
		$response = _wp_http_get_object()->get(
			$this->endpoint( sprintf( 'sessions/%s', $session_id ) ),
			Dintero_Request_Builder::instance()->build( $request )
		);

		return $this->decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Retrieving transaction
	 *
	 * @param  string $transaction_id transaction id.
	 * @return array
	 */
	public function get_transaction( $transaction_id ) {
		$request  = $this->init_request( $this->get_access_token() );
		$response = _wp_http_get_object()->get(
			$this->endpoint( sprintf( '/transactions/%s', $transaction_id ) ),
			Dintero_Request_Builder::instance()->build( $request )
		);

		return $this->decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Update Transaction
	 *
	 * @param  string $transaction_id transaction id.
	 * @param  array  $payload paylaod.
	 * @return array
	 */
	public function update_transaction( $transaction_id, $payload ) {
		$request           = $this->init_request( $this->get_access_token() )
			->set_body( wp_json_encode( $payload ) );
		$payload           = Dintero_Request_Builder::instance()->build( $request );
		$payload['method'] = 'PUT';
		$response          = _wp_http_get_object()->request(
			$this->endpoint( sprintf( '/transactions/%s', $transaction_id ) ),
			$payload
		);
		return $this->decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Capturing transaction
	 *
	 * @param  string $transaction_id transaction id.
	 * @param  array  $payload payload.
	 * @return array
	 */
	public function capture_transaction( $transaction_id, $payload ) {
		$request  = $this->init_request( $this->get_access_token() )
			->set_body( wp_json_encode( $payload ) );
		$payload  = Dintero_Request_Builder::instance()->build( $request );
		$response = _wp_http_get_object()->post(
			$this->endpoint( sprintf( '/transactions/%s/capture', $transaction_id ) ),
			$payload
		);
		return $this->decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Refund transaction
	 *
	 * @param  string $transaction_id transaction id.
	 * @param  array  $payload payload.
	 * @return array
	 */
	public function refund_transaction( $transaction_id, $payload ) {
		$request  = $this->init_request( $this->get_access_token() )
			->set_body( wp_json_encode( $payload ) );
		$payload  = Dintero_Request_Builder::instance()->build( $request );
		$response = _wp_http_get_object()->post(
			$this->endpoint( sprintf( '/transactions/%s/refund', $transaction_id ) ),
			$payload
		);
		return $this->decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Voiding transaction
	 *
	 * @param string $transaction_id transaction id.
	 * @return array
	 */
	public function void_transaction( $transaction_id ) {
		$request  = $this->init_request( $this->get_access_token() )
			->set_body( '' );
		$payload  = Dintero_Request_Builder::instance()->build( $request );
		$response = _wp_http_get_object()->post(
			$this->endpoint( sprintf( '/transactions/%s/void', $transaction_id ) ),
			$payload
		);
		return $this->decode( wp_remote_retrieve_body( $response ) );
	}

	/**
	 * Initializing session
	 *
	 * @param array $payload payload.
	 * @return mixed
	 */
	public function init_session( $payload ) {
		$request = $this->init_request( $this->get_access_token() );
		$request->set_body( wp_json_encode( $payload ) );

		$response = _wp_http_get_object()->post(
			$this->endpoint( '/sessions-profile' ),
			Dintero_Request_Builder::instance()->build( $request )
		);

		return $this->decode( wp_remote_retrieve_body( $response ) );
	}
}
