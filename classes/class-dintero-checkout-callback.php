<?php
/**
 * Handle callbacks from Dintero.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling callbacks from Dintero.
 */
class Dintero_Checkout_Callback {

	/**
	 * The reference to the *Singleton* instance of this class.
	 *
	 * @var $instance Dintero_Checkout_Callback.
	 */
	private static $instance;

	/**
	 * Return the *Singleton* instance of this class.
	 *
	 * @return Dintero_Checkout_Callback The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Register callback action.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_dintero_callback', array( $this, 'callback' ) );
	}

	/**
	 * Handle the callback from Dintero.
	 *
	 * @return void
	 */
	public function callback() {
		$transaction_id = filter_input( INPUT_GET, 'transaction_id', FILTER_SANITIZE_STRING );

		if ( empty( $transaction_id ) ) {
			return;
		}
	}

	/**
	 * Retrieve the callback URL.
	 *
	 * @return string The callback URL (relative to the home URL).
	 */
	public static function callback_url() {
		// Events to listen to when they happen in the back office.
		$events = '&report_event=CAPTURE&report_event=REFUND&report_event=VOID';

		return add_query_arg(
			array(
				// 'delay_callback' => 60, /* seconds. */
				'method'       => 'POST',
				'report_error' => true,
				'includes'     => 'session',
			),
			home_url( '/wc-api/dintero_callback/' )
		) . $events;
	}

	/**
	 * Determine whether Dintero is running on localhost.
	 *
	 * @return boolean TRUE if running on localhost, otherwise FALSE.
	 */
	public static function is_localhost() {
		return ( isset( $_SERVER['REMOTE_ADDR'] ) && in_array( $_SERVER['REMOTE_ADDR'], array( '127.0.0.1', '::1' ), true ) || isset( $_SERVER['HTTP_HOST'] ) && 'localhost' === substr( wp_unslash( $_SERVER['HTTP_HOST'] ), 0, 9 ) );
	}

}

// Instantiate this class.
Dintero_Checkout_Callback::get_instance();
