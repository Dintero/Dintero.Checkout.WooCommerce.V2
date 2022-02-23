<?php
/**
 * Class for registering and enqueuing assets where appropriate.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dintero_Checkout_Assets
 */
class Dintero_Checkout_Assets {

	/**
	 * Hook onto enqueue actions.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Register and enqueue scripts for the admin.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_STRING );
		if ( 'dintero_checkout' !== $section ) {
			return;
		}

		wp_register_script(
			'dintero-checkout-admin',
			plugins_url( 'assets/js/dintero-checkout-admin.js', DINTERO_CHECKOUT_MAIN_FILE ),
			array( 'jquery' ),
			DINTERO_CHECKOUT_VERSION,
			true,
		);

		wp_enqueue_script( 'dintero-checkout-admin' );
	}
} new Dintero_Checkout_Assets();
