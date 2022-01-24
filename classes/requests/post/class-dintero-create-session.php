<?php
/**
 * Create session class.
 *
 * @package Dintero_Checkout/Classes/Requests/Post
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a new session.
 */
class Dintero_Create_Session extends Dintero_Checkout_Request {

	public function request() {
		$request_url  = 'https://checkout.dintero.com/v1/session-profile';
		$request_args = array(
			'headers' => $this->get_headers(),
		);
	}
}
