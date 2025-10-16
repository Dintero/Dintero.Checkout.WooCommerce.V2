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
	 * The settings for the Dintero Checkout.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Hook onto enqueue actions.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( dwc_is_embedded( $this->settings ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			add_action( 'wp_enqueue_scripts', array( $this, 'dintero_load_css' ) );
		}
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_head', array( $this, 'backlinks_styling' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'dintero_load_general_checkout_css' ) );
	}
	/**
	 * Loads style for the plugin.
	 */
	public function dintero_load_css() {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		$settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( ! dwc_is_express( $settings ) ) {
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
	 * Loads general style for the checkout page of the plugin.
	 */
	public function dintero_load_general_checkout_css() {
		if ( ! is_checkout() ) {
			return;
		}

		wp_register_style(
			'dintero-checkout-general-style',
			DINTERO_CHECKOUT_URL . '/assets/css/dintero-checkout-general.css',
			array(),
			DINTERO_CHECKOUT_VERSION
		);
		wp_enqueue_style( 'dintero-checkout-general-style' );
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
		if ( ! wc_string_to_bool( $settings['enabled'] ?? 'no' ) ) {
			return;
		}

		if ( ! is_checkout() || is_order_received_page() || is_checkout_pay_page() ) {
			return;
		}

		if ( ! dwc_is_embedded( $settings ) ) {
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

		$asset_path = 'assets/js/dintero-checkout' . ( strpos( $settings['checkout_flow'], 'checkout' ) !== false ? '' : '-express' ) . '.js';
		wp_register_script(
			'dintero-checkout',
			plugins_url( $asset_path, DINTERO_CHECKOUT_MAIN_FILE ),
			array( 'dintero-checkout-sdk', 'wc-cart', 'jquery-blockui' ),
			DINTERO_CHECKOUT_VERSION,
			true
		);

		$session_id = WC()->session->get( 'dintero_checkout_session_id' );
		// If we don't have a session, or the cart has changed subscription status, create a new session.
		if ( empty( $session_id ) || Dintero_Checkout_Subscription::maybe_reset_session_on_subscription_change() ) {
			WC()->cart->calculate_shipping();
			// The checkout is only available for free orders if the cart contains subscriptions.
			// We therefore don't have to check if the cart contains subscriptions. Refer to Dintero_Checkout_Subscription::is_available().
			if ( 0.0 === floatval( WC()->cart->total ) && Dintero_Checkout_Subscription::cart_has_subscription() ) {
				$session = Dintero()->api->create_payment_token();
			} else {
				$session = Dintero()->api->create_session();
			}

			if ( is_wp_error( $session ) ) {
				return;
			}

			$session_id = $session['id'];
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
				'SID'                                  => $session_id,
				'language'                             => substr( get_locale(), 0, 2 ),
				'change_payment_method_url'            => WC_AJAX::get_endpoint( 'dintero_checkout_wc_change_payment_method' ),
				'change_payment_method_nonce'          => wp_create_nonce( 'dintero_checkout_wc_change_payment_method' ),
				'standardWooCheckoutFields'            => $standard_woo_checkout_fields,
				'submitOrder'                          => WC_AJAX::get_endpoint( 'checkout' ),
				'log_to_file_url'                      => WC_AJAX::get_endpoint( 'dintero_checkout_wc_log_js' ),
				'log_to_file_nonce'                    => wp_create_nonce( 'dintero_checkout_wc_log_js' ),
				'unset_session_url'                    => WC_AJAX::get_endpoint( 'dintero_checkout_unset_session' ),
				'unset_session_nonce'                  => wp_create_nonce( 'dintero_checkout_unset_session' ),
				'print_notice_url'                     => WC_AJAX::get_endpoint( 'dintero_checkout_print_notice' ),
				'print_notice_nonce'                   => wp_create_nonce( 'dintero_checkout_print_notice' ),
				'shipping_in_iframe'                   => ( isset( $settings['express_shipping_in_iframe'] ) && 'yes' === $settings['express_shipping_in_iframe'] && dwc_is_express( $settings ) ),
				'pip_text'                             => __( 'Payment in progress', 'dintero-checkout-for-woocommerce' ),
				'popOut'                               => dwc_is_popout( $settings ),
				'verifyOrderTotalURL'                  => WC_AJAX::get_endpoint( 'dintero_verify_order_total' ),
				'verifyOrderTotalNonce'                => wp_create_nonce( 'dintero_verify_order_total' ),
				'verifyOrderTotalError'                => __( 'The cart was modified. Please try again.', 'dintero-checkout-for-woocommerce' ),
				'allowDifferentBillingShippingAddress' => 'yes' === ( $settings['express_allow_different_billing_shipping_address'] ?? 'no' ) ? true : false,
				'woocommerceShipToDestination'         => get_option( 'woocommerce_ship_to_destination' ),
				'checkout_flow'                        => $settings['checkout_flow'] ?? 'express_popout',
			)
		);

		wp_enqueue_script( 'dintero-checkout-sdk' );
		wp_enqueue_script( 'dintero-checkout' );
	}

	/**
	 * Remove default styling applied to the backlinks.
	 *
	 * @hook wp_head
	 * @return void
	 */
	public function backlinks_styling() {
		?>
	<style>
		.payment_method_dintero_checkout a
		.payment_method_dintero_checkout a:hover,
		.payment_method_dintero_checkout a:focus,
		.payment_method_dintero_checkout a:active {
			margin: 0;
			padding: 0;
			border: 0;
			text-shadow: none;
			box-shadow: none;
			outline: none;
			text-decoration: none;
		}
	</style>
		<?php
	}
}
new Dintero_Checkout_Assets();
