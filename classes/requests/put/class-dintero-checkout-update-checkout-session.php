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

		// Non-express checkout sends the address from the WC form fields. Express must not send WooCommerce's copy (it lacks the organization_number), but during an address callback it must echo back the address Dintero supplied in the event — that copy carries the organization_number, and Dintero reverts the customer's selection if it is not confirmed back.
		if ( ! dwc_is_express( $this->settings ) ) {
			$billing_address = $helper->get_billing_address();
			if ( ! empty( $billing_address ) ) {
				$body['order']['billing_address'] = $billing_address;
			}

			$shipping_address = $helper->get_shipping_address();
			if ( ! empty( $shipping_address ) ) {
				$body['order']['shipping_address'] = $shipping_address;
			}
		} elseif ( $is_address_callback ) {
			$address_data      = $this->arguments['address_callback_data'] ?? array();
			$callback_billing  = $address_data['billing_address'] ?? array();
			$callback_shipping = $address_data['shipping_address'] ?? array();

			// The business flow often supplies only a shipping address; use it for billing too so the organization_number reaches Dintero on both.
			if ( empty( $callback_billing ) && ! empty( $callback_shipping ) ) {
				$callback_billing = $callback_shipping;
			}

			// If the callback data is missing entirely (failed to parse, or posted by an older version of the checkout script), fall back to the WC customer copy so the pending address is still confirmed back — an unconfirmed callback leaves the session locked with the address reverted.
			if ( empty( $callback_billing ) && empty( $callback_shipping ) ) {
				$callback_billing  = $helper->get_billing_address();
				$callback_shipping = $helper->get_shipping_address();
			}

			// If separate billing/shipping addresses are not allowed, ship to billing — the checkout form already does this (dintero-checkout-express.js), so echo it back here too rather than trust the client-supplied callback data. Gated on the billing address, not the shipping one: a callback carrying only a billing address must still overwrite a differing shipping address that Dintero kept from an earlier event.
			if ( ! empty( $callback_billing ) && ! dwc_allow_separate_shipping_address( $this->settings ) ) {
				foreach ( array( 'organization_number', 'business_name' ) as $key ) {
					if ( empty( $callback_billing[ $key ] ) && ! empty( $callback_shipping[ $key ] ) ) {
						$callback_billing[ $key ] = $callback_shipping[ $key ];
					}
				}

				$callback_shipping = $callback_billing;
			}

			if ( ! empty( $callback_billing ) ) {
				$body['order']['billing_address'] = $callback_billing;
			}

			if ( ! empty( $callback_shipping ) ) {
				$body['order']['shipping_address'] = $callback_shipping;
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
