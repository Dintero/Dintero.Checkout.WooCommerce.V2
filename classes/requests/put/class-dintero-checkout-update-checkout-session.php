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
				'shipping_option' => $helper->get_shipping_objects(),
			),
			'remove_lock' => true,
		);

		/* If we have more than one shipping package, we've added them to the order.items. */
		$shipping_option = $body['order']['shipping_option'];
		if ( empty( $shipping_option ) ) {
			unset( $body['order']['shipping_option'] );
		}

		// Set if express or not.
		if ( 'express' === $this->settings['checkout_type'] && 'embedded' === $this->settings['form_factor'] ) {
			$body = $this->add_express_object( $body, $helper->get_shipping_objects() );
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

		if ( count( $shipping ) > 1 ) {
			$body['express']['shipping_options'] = array();
			$body['express']['shipping_mode']    = 'shipping_not_required';
		}

		/* We must remove the shipping option from the order if we're showing it in Dintero Express. */
		unset( $body['order']['shipping_option'] );

		/* order.amount and order.vat_amount include the shipping cost, we must remove it since it is already added in Express object. */
		$amount     = 0;
		$vat_amount = 0;
		foreach ( $body['order']['items'] as $item ) {
			if ( isset( $item['amount'] ) ) {
				$amount     += $item['amount'];
				$vat_amount += $item['vat_amount'];
			}
		}

		$body['order']['amount']     = $amount;
		$body['order']['vat_amount'] = $vat_amount;

		return $body;
	}
}
