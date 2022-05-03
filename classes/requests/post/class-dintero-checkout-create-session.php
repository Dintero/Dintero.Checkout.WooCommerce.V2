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
		if ( ! empty( $this->arguments['order_id'] ) || is_wc_endpoint_url( 'order-pay' ) ) {
			$order_id = is_wc_endpoint_url( 'order-pay' ) ? wc_get_order_id_by_order_key( sanitize_key( $_GET['key'] ) ) : $this->arguments['order_id'];

			$helper           = new Dintero_Checkout_Order( $order_id );
			$order            = wc_get_order( $order_id );
			$shipping_address = $helper->get_shipping_address( $order );
			$billing_address  = $helper->get_billing_address( $order );
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
				'store'              => array(
					'id' => preg_replace( '/(https?:\/\/|www.|\/\s*$)/i', '', get_home_url() ),
				),
			),
			'profile_id' => $this->settings['profile_id'],
		);

		if ( isset( $order ) ) {
			$body['order']['shipping_address'] = $shipping_address;
			$body['order']['billing_address']  = $billing_address;
		}

		if ( ! Dintero_Checkout_Callback::is_localhost() ) {
			$body['url']['callback_url'] = Dintero_Checkout_Callback::callback_url();
		}

		// Set if express or not. For order-pay, we default to redirect flow.
		if ( ! is_wc_endpoint_url( 'order-pay' ) && 'express' === $this->settings['checkout_type'] && 'embedded' === $this->settings['form_factor'] ) {
			$this->add_express_object( $body );
		}

		$this->add_shipping( $body, $helper );

		return $body;
	}

	/**
	 * Adds the Express object to the body.
	 *
	 * @param array $body The body array.
	 * @return array
	 */
	public function add_express_object( &$body ) {
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

	/**
	 * Add shipping to the body depending on settings.
	 *
	 * @param array                                        $body The request body.
	 * @param Dintero_Checkout_Order|Dintero_Checkout_Cart $helper The helper class to use.
	 * @return void
	 */
	public function add_shipping( &$body, $helper ) {
		if ( ! is_wc_endpoint_url( 'order-pay' ) && 'express' === $this->settings['checkout_type'] && 'embedded' === $this->settings['form_factor'] ) {
			if ( isset( $this->settings['express_shipping_in_iframe'] ) && 'yes' === $this->settings['express_shipping_in_iframe'] ) {
				$body['express']['shipping_options'] = $helper->get_express_shipping_options();
				return;
			}

			$packages        = WC()->shipping()->get_packages();
			$chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
			$chosen_shipping = $chosen_methods[0];
			foreach ( $packages as $i => $package ) {
				foreach ( $package['rates'] as $method ) {
					if ( $chosen_shipping === $method->id ) {
						$body['express']['shipping_options'] = array( $helper->get_shipping_option( $method ) );
					}
				}
			}
		} else {
			// Add single shipping option if needed.
			$shipping_option = array( $helper->get_shipping_object() );
			if ( empty( $shipping_option ) || count( $shipping_option ) === 1 ) {
				$body['order']['shipping_option'] = $shipping_option[0];
			}
		}
	}
}
