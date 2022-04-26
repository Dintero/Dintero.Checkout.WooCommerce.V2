<?php //phpcs:ignore
/**
 * Class for updating a checkout session.
 *
 * @package Dintero_Checkout/Classes/Requests/Put
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for a Dintero checkout session.
 */
class Dintero_Checkout_Update_Checkout_Session extends Dintero_Checkout_Request_Put {
	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title = 'Update Dintero Session.';
	}

	/**
	 * Get the request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		return "{$this->get_api_url_base()}sessions/{$this->arguments['session_id']}?update_without_lock=true";
	}

	/**
	 * Returns the body for the request.
	 *
	 * @return array
	 */
	public function get_body() {
		$helper = new Dintero_Checkout_Cart();
		$body   = array(
			'order'       => array(
				'amount'     => $helper->get_order_total(),
				'currency'   => $helper->get_currency(),
				'vat_amount' => $helper->get_tax_total(),
				'items'      => $helper->get_order_lines(),
				/* order.shipping_option expects an object rather than an array: */
			),
			'remove_lock' => true,
		);

		// Set if express or not.
		if ( 'express' === $this->settings['checkout_type'] && 'embedded' === $this->settings['form_factor'] ) {
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
		if ( 'express' === $this->settings['checkout_type'] && 'embedded' === $this->settings['form_factor'] ) {
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
			$shipping_option = $helper->get_shipping_objects();
			if ( empty( $shipping_option ) || count( $shipping_option ) === 1 ) {
				$body['order']['shipping_option'] = $shipping_option[0];
			}
		}
	}
}
