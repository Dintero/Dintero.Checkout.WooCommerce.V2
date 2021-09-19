<?php
/**
 * Dintero Payment Processor Class Doc Comment
 *
 * @category Dintero_Payment_Processor
 * @package  Dintero
 * @author   Dintero
 */

/**
 * Dintero Payment Processor
 */
class Dintero_Payment_Processor {

	/**
	 * Payment gateway class instance
	 *
	 * @var WC_Payment_Gateway $paymente_gateway
	 */
	private $paymente_gateway;

	/**
	 * Initializing payment processor
	 *
	 * @param WC_Payment_Gateway $paymente_gateway payment gateway object.
	 */
	public function __construct( WC_Payment_Gateway $paymente_gateway ) {
		$this->paymente_gateway = $paymente_gateway;
	}

	/**
	 * Processing payment
	 *
	 * @param integer $order_id order number.
	 * @return string|void
	 */
	public function execute( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( empty( $order ) || ! ( $order instanceof WC_Order ) ) {
			return '';
		}

		return $this->resolve( $order )->process();
	}

	/**
	 * Resolving payment processor
	 *
	 * @param WC_Order $order order object.
	 * @return Dintero_Checkout_Interface
	 */
	protected function resolve( $order ) {
		return new Dintero_Checkout_Redirect(
			$this->paymente_gateway,
			new Dintero_Adapter(),
			$order
		);
	}

	/**
	 * Capturing order
	 *
	 * @param WC_Order $order order object.
	 * @return string
	 */
	public function capture( $order ) {
		return $this->resolve( $order )->capture();
	}

	/**
	 * Cancelling order
	 *
	 * @param WC_Order $order order object.
	 * @return bool
	 */
	public function cancel( $order ) {
		return $this->resolve( $order )->cancel();
	}

	/**
	 * Refunding
	 *
	 * @param WC_Order $order order object.
	 * @return bool
	 */
	public function refund( $order ) {
		return $this->resolve( $order )->refund();
	}
}
