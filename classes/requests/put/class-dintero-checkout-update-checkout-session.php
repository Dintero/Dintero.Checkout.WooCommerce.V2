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
				'amount'          => $helper->get_order_total(),
				'currency'        => $helper->get_currency(),
				'vat_amount'      => $helper->get_tax_total(),
				'items'           => $helper->get_order_lines(),
				/* order.shipping_option expects an object rather than an array: */
				'shipping_option' => $helper->get_shipping_objects()[0],
			),
			'remove_lock' => true,
		);

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
		if ( count( $shipping ) > 1 ) {
			$body['express']['shipping_options'] = array();
			$body['express']['shipping_mode']    = 'shipping_not_required';

			/* The shipping option is embedded in order.items instead. */
			unset( $body['order']['shipping_option'] );
		}

		return $body;
	}
}
