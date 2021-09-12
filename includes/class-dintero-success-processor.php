<?php

class Dintero_Success_Processor
{

    /**
     * @var Dintero_Adapter $adapter
     */
    protected $adapter;

    /**
     * @return Dintero_Adapter
     */
    protected function adapter()
    {
        if (!$this->adapter) {
            $this->adapter = new Dintero_Adapter();
        }
        return $this->adapter;
    }

    /**
     * @return Dintero_Helper|null
     */
    protected function helper()
    {
        return Dintero_Helper::instance();
    }

    /**
     * @param integer $order_id
     * @return mixed
     * @throws WC_Data_Exception
     */
    public function execute($order_id)
    {
        /** @var WC_Order $order */
        $order = wc_get_order($order_id);

        if ($order->get_payment_method() !== Dintero_Payment_Gateway::METHOD_CODE) {
            return $order_id;
        }

        $transaction_id = isset($_GET['transaction_id']) ? $_GET['transaction_id'] : null;
        if (!$transaction_id) {
            return $order_id;
        }

        $response = $this->adapter()->get_transaction($transaction_id);
        if ($this->helper()->extract('error', $response)) {
            $order->add_order_note(__('Could not fetch transaction information.'));
            return $order_id;
        }

        if ($this->helper()->extract('merchant_reference', $response) != $order_id) {
            $order->add_order_note(
                __( 'Transaction id is not valid for the order. Transaction ID: ' ) . $transaction_id
            );
            return $order_id;
        }

        $order->set_transaction_id($this->helper()->extract('id', $response));
        $order->save();
        return $order_id;
    }
}
