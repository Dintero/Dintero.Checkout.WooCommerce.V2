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
				'store'      => array(
					'id' => preg_replace( '/(https?:\/\/|www.|\/\s*$)/i', '', get_home_url() ),
				),
			),
			'remove_lock' => true,
		);

		// Set if express or not.
		if ( $this->is_express() && $this->is_embedded() ) {
			$this->add_express_object( $body );
		}

		// Set customer address for embedded.
		if ( ! $this->is_express() && $this->is_embedded() ) {
			$this->add_customer_address( $body );
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

	/**
	 * Adds customer addresst to the body if entered in WooCommerce.
	 *
	 * @param array $body The body array.
	 * @return array
	 */
	public function add_customer_address( &$body ) {

		// Billing address.
		if ( ! empty( WC()->customer->get_billing_first_name() ) ) {
			$body['billing_address']['first_name'] = WC()->customer->get_billing_first_name();
		}
		if ( ! empty( WC()->customer->get_billing_last_name() ) ) {
			$body['billing_address']['last_name'] = WC()->customer->get_billing_last_name();
		}
		if ( ! empty( WC()->customer->get_billing_address_1() ) ) {
			$body['billing_address']['address_line'] = WC()->customer->get_billing_address_1();
		}
		if ( ! empty( WC()->customer->get_billing_address_2() ) ) {
			$body['billing_address']['address_line_2'] = WC()->customer->get_billing_address_2();
		}
		if ( ! empty( WC()->customer->get_billing_postcode() ) ) {
			$body['billing_address']['postal_code'] = WC()->customer->get_billing_postcode();
		}
		if ( ! empty( WC()->customer->get_billing_city() ) ) {
			$body['billing_address']['postal_place'] = WC()->customer->get_billing_city();
		}
		if ( ! empty( WC()->customer->get_billing_country() ) ) {
			$body['billing_address']['country'] = WC()->customer->get_billing_country();
		}
		if ( ! empty( WC()->customer->get_billing_company() ) ) {
			$body['billing_address']['business_name'] = WC()->customer->get_billing_company();
		}
		if ( ! empty( WC()->customer->get_billing_phone() ) ) {
			$body['billing_address']['phone_number'] = WC()->customer->get_billing_phone();
		}
		if ( ! empty( WC()->customer->get_billing_email() ) ) {
			$body['billing_address']['email'] = WC()->customer->get_billing_email();
		}

		// Shipping address.
		if ( ! empty( WC()->customer->get_shipping_first_name() ) ) {
			$body['shipping_address']['first_name'] = WC()->customer->get_shipping_first_name();
		}
		if ( ! empty( WC()->customer->get_shipping_last_name() ) ) {
			$body['shipping_address']['last_name'] = WC()->customer->get_shipping_last_name();
		}
		if ( ! empty( WC()->customer->get_shipping_address_1() ) ) {
			$body['shipping_address']['address_line'] = WC()->customer->get_shipping_address_1();
		}
		if ( ! empty( WC()->customer->get_shipping_address_2() ) ) {
			$body['shipping_address']['address_line_2'] = WC()->customer->get_shipping_address_2();
		}
		if ( ! empty( WC()->customer->get_shipping_postcode() ) ) {
			$body['shipping_address']['postal_code'] = WC()->customer->get_shipping_postcode();
		}
		if ( ! empty( WC()->customer->get_shipping_city() ) ) {
			$body['shipping_address']['postal_place'] = WC()->customer->get_shipping_city();
		}
		if ( ! empty( WC()->customer->get_shipping_country() ) ) {
			$body['shipping_address']['country'] = WC()->customer->get_shipping_country();
		}
		if ( ! empty( WC()->customer->get_shipping_company() ) ) {
			$body['shipping_address']['business_name'] = WC()->customer->get_shipping_company();
		}
		if ( ! empty( WC()->customer->get_shipping_phone() ) ) {
			$body['shipping_address']['phone_number'] = WC()->customer->get_shipping_phone();
		}
		if ( ! empty( WC()->customer->get_shipping_email() ) ) {
			$body['shipping_address']['email'] = WC()->customer->get_shipping_email();
		}

		return $body;
	}
}
