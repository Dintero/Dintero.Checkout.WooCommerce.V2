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
				/* order.shipping_option expects an object rather than an array: */
				'shipping_option'    => $helper->get_shipping_objects()[0],
				'store'              => array(
					'id' => preg_replace( '/(https?:\/\/|www.|\/\s*$)/i', '', get_home_url() ),
				),
			),
			'profile_id' => $this->settings['profile_id'],
		);

		if ( ! Dintero_Checkout_Callback::is_localhost() ) {
			$body['url']['callback_url'] = Dintero_Checkout_Callback::callback_url();
		}

		/* If we have more than one shipping package, we've added them to the order.items. */
		$shipping_option = $helper->get_shipping_objects();
		if ( empty( $shipping_option ) || count( $shipping_option ) > 1 ) {
			unset( $body['order']['shipping_option'] );
		}

		// Set if express or not.
		if ( 'express' === $this->settings['checkout_type'] && 'embedded' === $this->settings['form_factor'] ) {
			$shipping_option = ( empty( $shipping_option ) ) ? array() : $shipping_option;
			$body            = $this->add_express_object( $body, $shipping_option );
		}

		return $body;
	}

	/**
	 * Adds the Express object to the body.
	 *
	 * @param array $body The body array.
	 * @return array
	 */
	public function add_express_object( $body, $shipping ) {

		/* If we only have _one_ shipping package, we'll show it in Dintero Express. */
		$body['express']['shipping_options'] = $shipping;

		/* Otherwise, it is embedded in order.items, and hidden in Dintero Express for now. */
		if ( empty( $shipping ) || count( $shipping ) > 1 ) {
			$body['express']['shipping_options'] = array();
			$body['express']['shipping_mode']    = 'shipping_not_required';

			/* The shipping option is embedded in order.items instead. */
			unset( $body['order']['shipping_option'] );
		}

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
