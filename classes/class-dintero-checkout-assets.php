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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue payment scripts.
	 *
	 * @hook wp_enqueue_scripts
	 * @return void
	 */
	public function enqueue_scripts() {
		$settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( 'yes' !== $settings['enabled'] ) {
			return;
		}

		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		// TODO: Check if embedded is enabled.

		$sdk_url = 'https://unpkg.com/@dintero/checkout-web-sdk@0.3.1/dist/dintero-checkout-web-sdk.umd.min.js';
		wp_register_script(
			'dintero-checkout-sdk',
			$sdk_url,
			array( 'jquery' ),
			DINTERO_CHECKOUT_VERSION,
			false /* must be loaded early, add to <header>. */
		);

		wp_register_script(
			'dintero-checkout',
			plugins_url( 'assets/js/dintero-checkout-express.js', DINTERO_CHECKOUT_MAIN_FILE ),
			array( 'dintero-checkout-sdk', 'wc-cart', 'jquery-blockui' ),
			DINTERO_CHECKOUT_VERSION,
			true
		);

		$session_id = WC()->session->get( 'dintero_checkout_session_id' );
		if ( empty( $session_id ) ) {
			// FIXME: The shipping_option is not available at this point. The current workaround is to check for null.
			$session    = Dintero()->api->create_session();
			$session_id = $session['id'];
			WC()->session->set( 'dintero_checkout_session_id', $session_id );
		}

		/* We need our own checkout fields since we're replacing the defualt WC form. */
		$standard_woo_checkout_fields = array(
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_postcode',
			'billing_city',
			'billing_phone',
			'billing_email',
			'billing_state',
			'billing_country',
			'billing_company',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_postcode',
			'shipping_city',
			'shipping_state',
			'shipping_country',
			'shipping_company',
			'terms',
			'terms-field',
			'_wp_http_referer',
		);

		wp_localize_script(
			'dintero-checkout',
			'dinteroCheckoutParams',
			array(
				'SID'                         => $session_id,
				'change_payment_method_url'   => WC_AJAX::get_endpoint( 'dintero_checkout_wc_change_payment_method' ),
				'change_payment_method_nonce' => wp_create_nonce( 'dintero_checkout_wc_change_payment_method' ),
				'standardWooCheckoutFields'   => $standard_woo_checkout_fields,
				'log_to_file_url'             => WC_AJAX::get_endpoint( 'dintero_checkout_wc_log_js' ),
				'log_to_file_nonce'           => wp_create_nonce( 'dintero_checkout_wc_log_js' ),
			)
		);

		wp_enqueue_script( 'dintero-checkout-sdk' );
		wp_enqueue_script( 'dintero-checkout' );
	}
} new Dintero_Checkout_Assets();
