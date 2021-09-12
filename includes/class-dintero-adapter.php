<?php

class Dintero_Adapter
{
    /*
     * Payment api base url
     */
    const API_ENDPOINT = 'https://api.dintero.com/v1';

    /*
     * Checkout api endpoint
     */
    const CHECKOUT_API_ENDPOINT = 'https://checkout.dintero.com/v1/';

    /**
     * @return array
     */
    private function default_headers()
    {
        return array(
            'Content-type'  => 'application/json; charset=utf-8',
            'Accept'        => 'application/json',
            'User-Agent' => 'Dintero.Checkout.Woocomerce.1.2.1 (+https://github.com/Dintero/Dintero.Checkout.Woocomerce)',
            'Dintero-System-Name' => 'woocommerce',
            'Dintero-System-Version' =>  WC()->version,
            'Dintero-System-Plugin-Name' => 'Dintero.Checkout.WooCommerce',
            'Dintero-System-Plugin-Version' => WCDHP()->get_version(),
        );
    }

    /**
     * @param string $endpoint
     * @return string
     */
    private function endpoint($endpoint)
    {
        return self::CHECKOUT_API_ENDPOINT . trim($endpoint, '/');
    }

    /**
     * @return Dintero_Request
     */
    protected function init_request($access_token = null)
    {
        $request = (new Dintero_Request())
            ->set_headers($this->default_headers());

        if (!empty($access_token)) {
            $request->add_header('Authorization', 'Bearer ' . $access_token);
        }

        return $request;
    }

    /**
     * @return false|string
     */
    public function get_access_token()
    {
        $account_id = WCDHP()->config()->is('test_mode') ? 'T' : 'P';
        $account_id .= WCDHP()->config()->get('account_id');
        $request = $this->init_request()
            ->set_auth_params(WCDHP()->config()->get('client_id'), WCDHP()->config()->get('client_secret'))
            ->set_body(wp_json_encode(array(
                'grant_type' => 'client_credentials',
                'audience'   => sprintf('%s/accounts/%s', self::API_ENDPOINT, $account_id),
            )));

        $response = json_decode(
            wp_remote_retrieve_body(
                _wp_http_get_object()->post(
                    sprintf('%s/accounts/%s/auth/token', self::API_ENDPOINT, $account_id),
                    Dintero_Request_Builder::instance()->build($request))
            ),
            true
        );

        return isset($response['access_token']) ? $response['access_token'] : false;
    }

    /**
     * @param $session_id
     * @return array
     */
    public function get_session($session_id)
    {
        $request = $this->init_request($this->get_access_token());
        $response = _wp_http_get_object()->get(
            $this->endpoint(sprintf('sessions/%s', $session_id)),
            Dintero_Request_Builder::instance()->build($request)
        );

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * @param string $transaction_id
     * @return array
     */
    public function get_transaction($transaction_id)
    {
        $request = $this->init_request($this->get_access_token());
        $response = _wp_http_get_object()->get(
            $this->endpoint(sprintf('/transactions/%s', $transaction_id)),
            Dintero_Request_Builder::instance()->build($request)
        );

        return json_decode(wp_remote_retrieve_body( $response ), true );
    }

    /**
     * Update Transaction
     *
     * @param $transaction_id
     * @param $payload
     * @return mixed
     */
    public function update_transaction($transaction_id, $payload)
    {
        $request = $this->init_request($this->get_access_token())
            ->set_body(wp_json_encode($payload));
        $payload = Dintero_Request_Builder::instance()->build($request);
        $payload['method'] = 'PUT';
        $response = _wp_http_get_object()->request(
            $this->endpoint(sprintf('/transactions/%s', $transaction_id)),
            $payload
        );
        return json_decode(wp_remote_retrieve_body( $response ), true );
    }

    /**
     * @param string $transaction_id
     * @param array $payload
     * @return array
     */
    public function capture_transaction($transaction_id, $payload)
    {
        $request = $this->init_request($this->get_access_token())
            ->set_body(wp_json_encode($payload));
        $payload = Dintero_Request_Builder::instance()->build($request);
        $response = _wp_http_get_object()->post(
            $this->endpoint(sprintf('/transactions/%s/capture', $transaction_id)),
            $payload
        );
        return json_decode(wp_remote_retrieve_body( $response ), true );
    }

    /**
     * @param string $transaction_id
     * @param array $payload
     * @return mixed
     */
    public function refund_transaction($transaction_id, $payload)
    {
        $request = $this->init_request($this->get_access_token())
            ->set_body(wp_json_encode($payload));
        $payload = Dintero_Request_Builder::instance()->build($request);
        $response = _wp_http_get_object()->post(
            $this->endpoint(sprintf('/transactions/%s/refund', $transaction_id)),
            $payload
        );
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * @param string $transaction_id
     * @return mixed
     */
    public function void_transaction($transaction_id)
    {
        $request = $this->init_request($this->get_access_token())
            ->set_body('');
        $payload = Dintero_Request_Builder::instance()->build($request);
        $response = _wp_http_get_object()->post(
            $this->endpoint(sprintf('/transactions/%s/void', $transaction_id)),
            $payload
        );
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Initializing session
     *
     * @param array $payload
     * @return mixed
     */
    public function init_session($payload)
    {
        $request = $this->init_request($this->get_access_token());
        $request->set_body(wp_json_encode($payload));

        $response = _wp_http_get_object()->post(
            $this->endpoint('/sessions-profile'),
            Dintero_Request_Builder::instance()->build($request)
        );

        return json_decode(wp_remote_retrieve_body($response), true);
    }
}