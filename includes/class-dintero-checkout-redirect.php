<?php

class Dintero_Checkout_Redirect extends Dintero_Checkout_Abstract
{
    /**
     * @return array|mixed|string
     */
    public function process()
    {
        $order_tax_amount = absint( strval( floatval( $this->order->get_total_tax() ) * 100 ) );
        $items = $this->helper()->prepare_items_from_order($this->order);

        $payload = array(
            'url'        => array(
                'return_url'   => $this->payment_gateway->get_return_url($this->order),
                'callback_url' => WC()->api_request_url(strtolower(get_class( $this ))),
            ),
            'customer'   => array(
                'email'        => $this->order->get_billing_email(),
                'phone_number' => $this->order->get_billing_phone()
            ),
            'order'      => array(
                'amount'             => array_sum(array_column($items, 'amount')),
                'vat_amount'         => $order_tax_amount,
                'currency'           => $this->order->get_currency(),
                'merchant_reference' => strval( $this->order->get_id() ),
                'shipping_address'   => array(
                    'first_name'   => $this->order->get_shipping_first_name(),
                    'last_name'    => $this->order->get_shipping_last_name(),
                    'address_line' => $this->order->get_shipping_address_1(),
                    'postal_code'  => $this->order->get_shipping_postcode(),
                    'postal_place' => $this->order->get_shipping_city(),
                    'country'      => $this->order->get_shipping_country()
                ),
                'billing_address'   => array(
                    'first_name'   => $this->order->get_billing_first_name(),
                    'last_name'    => $this->order->get_billing_last_name(),
                    'address_line' => $this->order->get_billing_address_1(),
                    'postal_code'  => $this->order->get_billing_postcode(),
                    'postal_place' => $this->order->get_billing_city(),
                    'country'      => $this->order->get_billing_country()
                ),
                'items'            => $items
            ),
            'profile_id' => Dintero::instance()->config()->get('profile_id'),
            'metadata' => array(
                'woo_customer_id' => WC()->session->get_customer_id(),
                'woo_initiated_by' => 'redirect',
            )
        );

        $response = $this->adapter->init_session($payload);
        if (isset($response['url'])) {
            return array(
                'result'    => 'success',
                'redirect'  => $response['url']
            );
        }

        return array();
    }
}
