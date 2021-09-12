<?php

class Dintero_Helper
{
    /*
     * Status captured
     */
    const TRANSACTION_STATUS_CAPTURED = 'CAPTURED';

    /*
     * Status partially refunded
     */
    const TRANSACTION_STATUS_PART_REFUND = 'PARTIALLY_REFUNDED';

    /*
     * Status refunded
     */
    const TRANSACTION_STATUS_REFUND = 'REFUNDED';

    /*
     * Status authorized
     */
    const TRANSACTION_STATUS_AUTH = 'AUTHORIZED';

    /*
     * Status partially captured
     */
    const TRANSACTION_STATUS_PART_CAPTURE = 'PARTIALLY_CAPTURED';

    /*
     * Transaction voided
     */
    const TRANSACTION_STATUS_VOIDED = 'AUTHORIZATION_VOIDED';

    /**
     * @var null
     */
    private static $instance = null;

    private function __construct()
    {

    }

    private function __clone()
    {

    }

    private function __wake()
    {

    }

    /**
     * @return Dintero_Helper|null
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param WC_Order $order
     * @return array
     */
    public function prepare_items_from_order($order)
    {
        $items = array();

        /** @var WC_Order_Item_Product $order_item */
        foreach ( $order->get_items() as $order_item ) {

            if ( !($order_item instanceof WC_Order_Item_Product) ) {
                continue;
            }

            $item_total_amount      = absint( strval( floatval( $order_item->get_total() ) * 100 ) );
            $item_tax_amount        = absint( strval( floatval( $order_item->get_total_tax() ) * 100 ) );
            $item_line_total_amount = absint( strval( floatval( $order->get_line_total( $order_item,
                    true ) ) * 100 ) );
            $item_tax_percentage    = $item_total_amount ? ( round( ( $item_tax_amount / $item_total_amount ),
                    2 ) * 100 ) : 0;
            $item = array(
                'id'          => (string) $order_item->get_product_id(),
                'description' => $order_item->get_name(),
                'quantity'    => $order_item->get_quantity(),
                'vat_amount'  => $item_tax_amount,
                'vat'         => $item_tax_percentage,
                'amount'      => $item_line_total_amount,
                'line_id'     => (string) $order_item->get_product_id()
            );
            array_push( $items, $item );
            $total_amount += $item_line_total_amount;
        }

        if (!$order->has_shipping_address()) {
            return $items;
        }

        $item_total_amount      = $this->format_number($order->get_shipping_total());
        $item_tax_amount        = $this->format_number($order->get_shipping_tax());
        $item_line_total_amount = $item_total_amount + $item_tax_amount;
        $item_tax_percentage    = $item_total_amount
            ? ( round( ( $item_tax_amount / $item_total_amount ), 2 ) * 100 ) : 0;

        array_push( $items, array(
            'id'          => 'shipping',
            'line_id'     => 'shipping',
            'description' => 'Shipping: ' . $order->get_shipping_method(),
            'quantity'    => 1,
            'vat_amount'  => $item_tax_amount,
            'vat'         => $item_tax_percentage,
            'amount'      => $item_line_total_amount,
        ) );

        return $items;
    }

    /**
     * @param float|int $num
     * @return int
     */
    public function format_number($num)
    {
        return absint(round($num, 2) * 100);
    }

    /**
     * @param string $key
     * @param array $data
     * @param null $default
     * @return mixed|null
     */
    public function extract($key, $data, $default = null)
    {
        return !empty($data[$key]) ? $data[$key] : $default;
    }

    /**
     * @param array $transaction_data
     * @return bool
     */
    public function can_capture($transaction_data)
    {
        return in_array($this->extract('status', $transaction_data), array(
            self::TRANSACTION_STATUS_AUTH,
            self::TRANSACTION_STATUS_PART_CAPTURE
        ));
    }

    /**
     * @param array $transaction_data
     * @return bool
     */
    public function can_refund($transaction_data)
    {
        return in_array($this->extract('status', $transaction_data), array(
            self::TRANSACTION_STATUS_CAPTURED,
            self::TRANSACTION_STATUS_PART_REFUND
        ));
    }

    /**
     * @param array $transaction_data
     * @return bool
     */
    public function can_cancel($transaction_data)
    {
        return $this->extract('status', $transaction_data) === self::TRANSACTION_STATUS_AUTH;
    }

    /**
     * @param array $transaction_data
     * @return bool
     */
    public function is_fully_refunded($transaction_data)
    {
        return $this->extract('status', $transaction_data) === self::TRANSACTION_STATUS_REFUND;
    }

    /**
     * @param array $transaction_data
     * @return bool
     */
    public function is_partially_refunded($transaction_data)
    {
        return $this->extract('status', $transaction_data) === self::TRANSACTION_STATUS_PART_REFUND;
    }

    /**
     * @param array $transaction_data
     * @return bool
     */
    public function is_cancelled($transaction_data)
    {
        return $this->extract('status', $transaction_data) === self::TRANSACTION_STATUS_VOIDED;
    }

    public function is_fully_captured($transaction_data)
    {
        return $this->extract('status', $transaction_data) === self::TRANSACTION_STATUS_CAPTURED;
    }

    /**
     * @param array $transaction_data
     * @return bool
     */
    public function is_partially_captured($transaction_data)
    {
        return $this->extract('status', $transaction_data) === self::TRANSACTION_STATUS_PART_CAPTURE;
    }
}
