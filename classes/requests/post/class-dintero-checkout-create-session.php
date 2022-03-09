<?php
/**
 * Class for handling session creation request.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dintero_Checkout_Create_Session class.
 */
class Dintero_Checkout_Create_Session extends Dintero_Checkout_Request_Post {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title = 'Create Dintero session.';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return "{$this->get_api_url_base()}sessions-profile";
	}

	/**
	 * Returns the body for the request.
	 *
	 * @return array
	 */
	public function get_body() {
		if ( ! empty( $this->arguments['order_id'] ) ) {
			$helper = new Dintero_Checkout_Order( $this->arguments['order_id'] );
		} else {
			$helper = new Dintero_Checkout_Cart();
		}

		$reference = $helper->get_merchant_reference();
		WC()->session->set( 'dintero_merchant_reference', $reference );

		$body = array(
			'url'        => array(
				'return_url' => add_query_arg(
					array(
						'gateway' => 'dintero',
					),
					home_url()
				),
			),
			'order'      => array(
				'amount'             => $helper->get_order_total(),
				'currency'           => $helper->get_currency(),
				'merchant_reference' => $reference,
				'vat_amount'         => $helper->get_tax_total(),
				'items'              => $helper->get_order_lines(),
				'shipping_option'    => $helper->get_shipping_object(),
			),
			'profile_id' => $this->settings['profile_id'],
		);

		if ( ! Dintero_Checkout_Callback::is_localhost() ) {
			$this->request_args['url']['callback_url'] = Dintero_Checkout_Callback::callback_url( '$order->get_order_key()' );
		}

		if ( empty( $body['order']['shipping_option'] ) ) {
			unset( $body['order']['shipping_option'] );
		}

		// Set if express or not.
		if ( 'express' === $this->settings['checkout_type'] && 'embedded' === $this->settings['form_factor'] ) {
			$body = $this->add_express_object( $body );
		}

		return $body;
	}

	/**
	 * Adds the Express object to the body.
	 *
	 * @param array $body The body array.
	 * @return array
	 */
	public function add_express_object( $body ) {
		// Add shipping options array.
		$body['express']['shipping_options'] = array();

		// Set allowed customer types.
		$customer_types = $this->settings['express_customer_type'];
		switch ( $customer_types ) {
			case 'b2c':
				$body['express']['customer_types'] = array( 'b2c' );
				break;
			case 'b2b':
				$body['express']['customer_types'] = array( 'b2b' );
				break;
			case 'b2bc':
			default:
				$body['express']['customer_types'] = array( 'b2c', 'b2b' );
				break;
		}

		return $body;
	}
}
