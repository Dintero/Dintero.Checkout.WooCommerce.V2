<?php
/**
 * Dintero Converter Class Doc Comment
 *
 * @category Dintero_Converter
 * @package  Dintero
 * @author   Dintero
 */

/**
 * Dintero Converter used to convert woocommerce numbers to dintero numbers
 */
class Dintero_Converter {

	protected static $instance;

	private function __construct()
	{

	}

	private function __clone()
	{

	}

	public static function instance()
	{
		if (!self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function get_order_tax_amount($order)
	{
		return absint( strval( floatval( $order->get_total_tax() ) * 100 ) )
	}
}
