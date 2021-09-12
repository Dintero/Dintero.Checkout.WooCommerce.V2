<?php

class Dintero_Payment_Processor
{
    /**
     * @var WC_Payment_Gateway $paymente_gateway
     */
    private $paymente_gateway;

    /**
     * @param WC_Payment_Gateway $paymente_gateway
     */
    public function __construct(WC_Payment_Gateway $paymente_gateway)
    {
        $this->paymente_gateway = $paymente_gateway;
    }

    /**
     * @param integer $order_id
     * @return string|void
     */
    public function execute($order_id)
    {
        $order = wc_get_order($order_id);
        if ( empty( $order ) || !($order instanceof WC_Order)) {
            return '';
        }

        return $this->resolve($order)->process();
    }

    /**
     * @param WC_Order $order
     * @return Dintero_Checkout_Interface
     */
    protected function resolve($order)
    {
        return new Dintero_Checkout_Redirect(
            $this->paymente_gateway,
            new Dintero_Adapter(),
            $order
        );
    }

    /**
     * @param WC_Order $order
     * @return string
     */
    public function capture($order)
    {
        return $this->resolve($order)->capture();
    }

    /**
     * @param WC_Order $order
     * @return bool
     */
    public function cancel($order)
    {
        return $this->resolve($order)->cancel();
    }

    /**
     * @param WC_Order $order
     * @return bool
     */
    public function refund($order)
    {
        return $this->resolve($order)->refund();
    }
}