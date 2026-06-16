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

		$this->log_title      = 'Update Dintero Session.';
		$this->request_filter = 'dintero_checkout_update_checkout_session_args';
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
		$helper              = new Dintero_Checkout_Cart();
		$is_address_callback = ! empty( $this->arguments['is_address_callback'] );

		$body = array(
			'order' => array(
				'amount'     => $helper->get_order_total(),
				'currency'   => $helper->get_currency(),
				'vat_amount' => $helper->get_tax_total(),
				'items'      => $helper->get_order_lines(),
				'store'      => array(
					'id' => preg_replace( '/(https?:\/\/|www.|\/\s*$)/i', '', get_home_url() ),
				),
			),
		);

		// Keep the lock during an address callback so Dintero retains its pending session state.
		if ( ! $is_address_callback ) {
			$body['remove_lock'] = true;
		}

		// Only non-express checkout echoes the address back. In express, Dintero owns the pending address (and the organization number entered in the iframe, which WooCommerce never stores), so re-sending WooCommerce's incomplete copy would wipe it.
		if ( ! dwc_is_express( $this->settings ) ) {
			$billing_address = $helper->get_billing_address();
			if ( ! empty( $billing_address ) ) {
				$body['order']['billing_address'] = $billing_address;
			}

			$shipping_address = $helper->get_shipping_address();
			if ( ! empty( $shipping_address ) ) {
				$body['order']['shipping_address'] = $shipping_address;
			}
		}

		// Set the allowed customer types. Skip during an address callback, as re-sending them can reset the customer's current selection mid-switch.
		if ( $this->is_express() && $this->is_embedded() && ! $is_address_callback ) {
			$this->add_express_object( $body );
		}

		$helper::add_shipping( $body, $helper, $this->is_embedded(), $this->is_express(), $this->is_shipping_in_iframe() );
		$helper::add_rounding_line( $body );

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
}
