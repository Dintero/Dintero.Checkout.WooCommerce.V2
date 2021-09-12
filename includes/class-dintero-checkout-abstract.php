<?php

abstract class Dintero_Checkout_Abstract
    implements Dintero_Checkout_Interface
{
    /**
     * @var WC_Payment_Gateway $payment_gateway
     */
    protected $payment_gateway;

    /**
     * @var Dintero_Adapter $adapter
     */
    protected $adapter;

    /**
     * @var WC_Order $order
     */
    protected $order;

    /**
     * @param WC_Payment_Gateway $payment_gateway
     */
    public function __construct(
        WC_Payment_Gateway $payment_gateway,
        Dintero_Adapter $adapter,
        WC_Order $order
    ) {
        $this->payment_gateway = $payment_gateway;
        $this->adapter = $adapter;
        $this->order = $order;
    }

    /**
     * @return array
     */
    abstract public function process();

    /**
     * @return Dintero_Helper|null
     */
    protected function helper()
    {
        return Dintero_Helper::instance();
    }

    /**
     * @param WC_Order $order
     * @return bool
     */
    public function capture()
    {
        if (Dintero_Payment_Gateway::METHOD_CODE !== $this->order->get_payment_method()) {
            return false;
        }

        $transaction_id = $this->order->get_transaction_id();

        if (empty($transaction_id)) {
            $this->order->add_order_note('Payment capture failed at Dintero because the order lacks transaction_id. Contact integration@dintero.com with order information.');
            $this->order->save_meta_data();
            return false;
        }

        $transaction = $this->adapter->get_transaction($transaction_id);

        if ($error = $this->helper()->extract('error', $transaction)) {
            $this->order->add_order_note(__('Could not capture transaction: ') . $error['message']);
            $this->order->save_meta_data();
            return false;
        }

        $merchant_reference_id = absint(strval($this->helper()->extract(
            'merchant_reference',
            $transaction,
            $this->helper()->extract('merchant_reference_2', $transaction)
        )));

        if (!$merchant_reference_id || $merchant_reference_id !== $this->order->get_id() ) {
            $this->order->add_order_note(__('Could not capture transaction: Merchant reference id not found'));
            $this->order->save_meta_data();
            return false;
        }

        $order_total_amount = absint( strval( floatval( $this->order->get_total() ) * 100 ) );

        if (!$this->helper()->can_capture($transaction) || $this->helper()->extract('amount', $transaction) != $order_total_amount) {
            $this->order->add_order_note(__(
                sprintf(
                    'Could not capture transaction: Transaction status is wrong (%s) or order and transaction amounts do not match. Transaction amount: %s. Order amount: %s',
                    $this->helper()->extract('status', $transaction),
                    $this->helper()->extract('amount', $transaction),
                    $order_total_amount
                )
            ));
            $this->order->save_meta_data();
            return false;
        }

        $payload = array(
            'amount'            => $order_total_amount,
            'capture_reference' => strval( $this->order->get_id() ),
            'items'             => $this->helper()->prepare_items_from_order($this->order)
        );

        $response = $this->adapter->capture_transaction($transaction_id, $payload);

        if ( $this->helper()->is_fully_captured($response) || $this->helper()->is_partially_captured($response)) {
            $note = __( 'Payment captured via Dintero. Transaction ID: ' ) . $transaction_id;
            $this->order->add_order_note($note);
            $this->order->set_transaction_id($transaction_id);
            $this->order->payment_complete($transaction_id);
            wc_reduce_stock_levels($this->order->get_id());
            $this->order->save();
            return true;
        }

        $note = __('Payment capture failed at Dintero. Transaction ID: ') . $transaction_id;
        $this->order->add_order_note($note);
        return false;
    }

    /**
     * @return bool
     */
    public function cancel()
    {
        $transaction = $this->adapter->get_transaction($this->order->get_transaction_id());

        if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
            return false;
        }


        if ($this->helper()->extract('merchant_reference', $transaction) != $this->order->get_id()
            || !$this->helper()->can_cancel($transaction)
        ) {
            $this->order->add_order_note(
                __('Cannot cancel transaction. Transaction status: ')
                . $this->helper()->extract('status', $transaction)
            );
            return false;
        }

        $response = $this->adapter->void_transaction($this->order->get_transaction_id());
        if ($this->helper()->is_cancelled($response)) {
            $note = __( 'Transaction cancelled via Dintero. Transaction ID: ' ) . $this->order->get_transaction_id();
            $this->order->add_order_note( $note );
            wc_increase_stock_levels( $this->order->get_id() );
            return true;
        }
        $this->order->add_order_note(
            __('Failed to cancel transaction via Dintero. Transaction ID: ')
        ) . $this->order->get_transaction_id();
        $this->order->add_order_note($this->helper()->extract('error', $response));
        return false;
    }

    /**
     * @param null $amount
     * @return bool
     */
    public function refund($amount = null)
    {
        $transaction = $this->adapter->get_transaction($this->order->get_transaction_id());

        if ( ! array_key_exists( 'merchant_reference', $transaction ) ) {
            return false;
        }

        $transaction_order_id = absint( strval( $transaction['merchant_reference'] ) );
        if ($transaction_order_id !== $this->order->get_id()
            || !$this->helper()->can_refund($transaction)
        ) {
            return false;
        }

        $amount = empty($amount) ? $this->helper()->extract('amount', $transaction) : $amount * 100;

        $response = $this->adapter->refund_transaction($this->order->get_transaction_id(), array(
            'amount' => $amount,
            'items' => $this->helper()->prepare_items_from_order($this->order),
        ));

        if ($this->helper()->is_fully_refunded($response)) {
            $note = __( 'Payment refunded via Dintero. Transaction ID: ' ) . $this->order->get_transaction_id();
            wc_increase_stock_levels( $this->order->get_id() );
            $this->order->add_order_note($note);
            return true;
        }

        if ($this->helper()->is_partially_refunded( $response )) {
            $note = ( $amount / 100 ) . ' ' . __( $this->order->get_currency() . ' refunded via Dintero. Transaction ID: ' ) . $this->order->get_transaction_id();
            $this->order->add_order_note( $note );
            return true;
        }

        $this->order->add_order_note( __( 'Payment refund failed at Dintero. Transaction ID: ' ) . $this->order->get_transaction_id() );

        return false;
    }
}
