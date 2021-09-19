<?php
/**
 * Interface For Dintero checkout types
 *
 * @package Dintero
 */

/**
 * Interface for Dintero checkout types
 */
interface Dintero_Checkout_Interface {

	/**
	 * Processing payment
	 *
	 * @return array
	 */
	public function process();
}
