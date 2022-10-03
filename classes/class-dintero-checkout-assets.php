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
		$settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( 'embedded' === $settings['form_factor'] ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'dintero_load_css' ) );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}
	/**
	 * Loads style for the plugin.
	 */
	public function dintero_load_css() {
		$settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( 'express' !== $settings['checkout_type'] ) {
			return;
		}
		if ( ! is_checkout() ) {
			return;
		}
		if ( is_order_received_page() ) {
			return;
		}

		wp_register_style(
			'dintero-checkout-style',
			DINTERO_CHECKOUT_URL . '/assets/css/dintero-checkout-express.css',
			array(),
			DINTERO_CHECKOUT_VERSION
		);
		wp_enqueue_style( 'dintero-checkout-style' );
	}

	/**
	 * Register and enqueue scripts for the admin.
	 *
	 * @param string $hook The hook for the admin page where the script is to be enqueued.
	 * @return void
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'woocommerce_page_wc-settings' !== $hook ) {
			return;
		}

		$section = filter_input( INPUT_GET, 'section', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
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

		wp_register_style(
			'dintero-checkout-admin',
			plugins_url( 'assets/css/dintero-checkout-admin.css', DINTERO_CHECKOUT_MAIN_FILE ),
			array(),
			DINTERO_CHECKOUT_VERSION
		);

		wp_enqueue_script( 'dintero-checkout-admin' );
		wp_enqueue_style( 'dintero-checkout-admin' );
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

		if ( ! is_checkout() || is_order_received_page() || is_wc_endpoint_url( 'order-pay' ) ) {
			return;
		}

		if ( 'embedded' !== $settings['form_factor'] ) {
			return;
		}

		$sdk_url = plugins_url( 'assets/js/dintero-checkout-web-sdk.umd.min.js', DINTERO_CHECKOUT_MAIN_FILE );
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
			WC()->cart->calculate_shipping();
			$new_session = Dintero()->api->create_session();

			if ( is_wp_error( $new_session ) ) {
				return;
			}

			$session_id = $new_session['id'];
			WC()->session->set( 'dintero_checkout_session_id', $session_id );
		}

		/* We need our own checkout fields since we're replacing the default WC form. */
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
				'language'                    => substr( get_locale(), 0, 2 ),
				'change_payment_method_url'   => WC_AJAX::get_endpoint( 'dintero_checkout_wc_change_payment_method' ),
				'change_payment_method_nonce' => wp_create_nonce( 'dintero_checkout_wc_change_payment_method' ),
				'standardWooCheckoutFields'   => $standard_woo_checkout_fields,
				'submitOrder'                 => WC_AJAX::get_endpoint( 'checkout' ),
				'log_to_file_url'             => WC_AJAX::get_endpoint( 'dintero_checkout_wc_log_js' ),
				'log_to_file_nonce'           => wp_create_nonce( 'dintero_checkout_wc_log_js' ),
				'unset_session_url'           => WC_AJAX::get_endpoint( 'dintero_checkout_unset_session' ),
				'unset_session_nonce'         => wp_create_nonce( 'dintero_checkout_unset_session' ),
				'print_notice_url'            => WC_AJAX::get_endpoint( 'dintero_checkout_print_notice' ),
				'print_notice_nonce'          => wp_create_nonce( 'dintero_checkout_print_notice' ),
				'shipping_in_iframe'          => ( isset( $settings['express_shipping_in_iframe'] ) && 'yes' === $settings['express_shipping_in_iframe'] && 'express' === $settings['checkout_type'] ),
				'pip_text'                    => __( 'Payment in progress', 'dintero-checkout-for-woocommerce' ),

			)
		);

		wp_enqueue_script( 'dintero-checkout-sdk' );
		wp_enqueue_script( 'dintero-checkout' );
	}
} new Dintero_Checkout_Assets();
