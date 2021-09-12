<?php

class Dintero_Status_Processor
{
    /**
     * @var Dintero_Payment_Processor $payment_processor
     */
    protected $payment_processor;

    /**
     * @return Dintero_Payment_Processor
     */
    protected function payment_processor()
    {
        if ($this->payment_processor instanceof Dintero_Payment_Processor) {
            return $this->payment_processor;
        }
        $this->payment_processor = new Dintero_Payment_Processor(new Dintero_Payment_Gateway());
        return $this->payment_processor;
    }

    /**
     * @param integer $order_id
     * @param string $previous_status
     * @param string $current_status
     */
    public function process_status_change($order_id, $previous_status, $current_status)
    {
        $order = wc_get_order( $order_id );
        if ( empty($order) || !($order instanceof WC_Order)) {
            return '';
        }

        $manual_capture_status = str_replace(
            'wc-',
            '',
            Dintero::instance()->config()->get('manual_capture_status')
        );

        switch ($current_status) {
            case $manual_capture_status:
                $this->payment_processor()->capture($order);
                break;
            case 'cancelled':
                $this->payment_processor()->cancel($order);
                break;
            case 'refunded':
                $this->payment_processor()->refund($order);
                break;
        }
    }
}
