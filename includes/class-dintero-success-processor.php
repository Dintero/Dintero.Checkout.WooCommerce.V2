<?php
/**
 * Dintero Success Processor Class Doc Comment
 *
 * @category Dintero_Success_Processor
 * @package  Dintero
 * @author   Dintero
 */

/**
 * Dintero Success Processor
 */
class Dintero_Success_Processor {
	/**
	 * Adapter
	 *
	 * @var Dintero_Adapter $adapter
	 */
	protected $adapter;

	/**
	 * Retrieving adapter
	 *
	 * @return Dintero_Adapter
	 */
	protected function adapter() {
		if ( ! $this->adapter ) {
			$this->adapter = new Dintero_Adapter();
		}
		return $this->adapter;
	}

	/**
	 * Retrieving helper
	 *
	 * @return Dintero_Helper|null
	 */
	protected function helper() {
		return Dintero_Helper::instance();
	}

	/**
	 * Order id
	 *
	 * @param integer $order_id order id.
	 * @return mixed
	 * @throws WC_Data_Exception Exception type.
	 */
	public function execute( $order_id ) {
		/**
		 * Order object
		 *
		 * @var WC_Order $order
		 */
		$order = wc_get_order( $order_id );

		if ( $order->get_payment_method() !== Dintero_Payment_Gateway::METHOD_CODE ) {
			return $order_id;
		}

		// @codingStandardsIgnoreStart
		$transaction_id = wp_unslash( $_GET['transaction_id'] );
		// @codingStandardsIgnoreEnd

		if ( ! $transaction_id ) {
			return $order_id;
		}

		$response = $this->adapter()->get_transaction( $transaction_id );
		if ( $this->helper()->extract( 'error', $response ) ) {
			$order->add_order_note( __( 'Could not fetch transaction information.' ) );
			return $order_id;
		}

		// @codingStandardsIgnoreStart
		if ( $this->helper()->extract( 'merchant_reference', $response ) != $order_id ) {
			// @codingStandardsIgnoreEnd
			$order->add_order_note(
				__( 'Transaction id is not valid for the order. Transaction ID: ' ) . $transaction_id
			);
			return $order_id;
		}

		$order->set_transaction_id( $this->helper()->extract( 'id', $response ) );
		$order->save();
		return $order_id;
	}
}
