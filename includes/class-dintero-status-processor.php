<?php
/**
 * Dintero Status Processor Class Doc Comment
 *
 * @category Dintero_Status_Processor
 * @package  Dintero
 * @author   Dintero
 */

/**
 * Dintero Status Processor
 */
class Dintero_Status_Processor {

	/**
	 * Payment processor class
	 *
	 * @var Dintero_Payment_Processor $payment_processor
	 */
	protected $payment_processor;

	/**
	 * Retrieving payment processor
	 *
	 * @return Dintero_Payment_Processor
	 */
	protected function payment_processor() {
		if ( $this->payment_processor instanceof Dintero_Payment_Processor ) {
			return $this->payment_processor;
		}
		$this->payment_processor = new Dintero_Payment_Processor( new Dintero_Payment_Gateway() );
		return $this->payment_processor;
	}

	/**
	 * Processing status changes
	 *
	 * @param integer $order_id order id.
	 * @param string  $previous_status previous status value.
	 * @param string  $current_status current status value.
	 */
	public function process_status_change( $order_id, $previous_status, $current_status ) {
		$order = wc_get_order( $order_id );
		if ( empty( $order ) || ! ( $order instanceof WC_Order ) ) {
			return '';
		}

		$manual_capture_status = str_replace(
			'wc-',
			'',
			Dintero::instance()->config()->get( 'manual_capture_status' )
		);

		switch ( $current_status ) {
			case $manual_capture_status:
				$this->payment_processor()->capture( $order );
				break;
			case 'cancelled':
				$this->payment_processor()->cancel( $order );
				break;
			case 'refunded':
				$this->payment_processor()->refund( $order );
				break;
		}
	}
}
