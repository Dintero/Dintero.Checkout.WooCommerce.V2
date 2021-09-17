<?php

class Dintero_Serializer
{
    protected static $instance;

    /**
     *
     */
    private function __construct()
    {

    }

    /**
     * Cloning is forbidden.
     */
    public function __clone() {
        wc_doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'woocommerce' ), '2.1' );
    }

    public function __wakeup() {
        wc_doing_it_wrong( __FUNCTION__, __( 'Unserializing instances of this class is forbidden.', 'woocommerce' ), '2.1' );
    }

    /**
     * @return Dintero_Serializer
     */
    public static function instance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * @param array $data
     * @return false|string
     */
    public function serialize(array $data)
    {
        return wp_json_encode($data);
    }

    /**
     * @param string $json
     * @return mixed
     * @throws Exception
     */
    public function unserialize($json)
    {
        $result = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Unable to unserialize value. Error: " . json_last_error_msg());
        }
        return $result;
    }
}
