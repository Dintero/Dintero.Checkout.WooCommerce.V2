<?php // phpcs:ignore
/**
 * Extends the shipping instance with Dintero specific settings.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for extending shipping instance settings.
 */
class Dintero_Shipping_Method_Instance {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_shipping_instance_settings' ) );
	}

	/**
	 * Register the shipping instance settings for each shipping method that exists.
	 */
	public function register_shipping_instance_settings() {
		$available_shipping_methods = WC()->shipping()->load_shipping_methods();
		foreach ( $available_shipping_methods as $shipping_method ) {
			$shipping_method_id = $shipping_method->id;
			add_filter( 'woocommerce_shipping_instance_form_fields_' . $shipping_method_id, array( $this, 'add_shipping_method_fields' ), 9 );
		}
	}

	/**
	 * Add External delivery method to shipping method fields.
	 *
	 * @param array $shipping_method_fields      Array of shipping method fields.
	 *
	 * @return array
	 */
	public function add_shipping_method_fields( $shipping_method_fields ) {
		$settings_fields = array(
			'dintero_checkout_settings' => array(
				'title'       => __( 'Dintero Checkout', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'These settings apply to the Dintero Checkout only.', 'dintero-checkout-for-woocommerce' ),
			),
			'dintero_description'       => array(
				'title'       => __( 'Description', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'description' => __( 'For Express checkouts, the description will appear next to the shipping option, provided shipping is displayed within the Dintero checkout. Maximum length is 200 characters; longer text will be cut off.', 'dintero-checkout-for-woocommerce' ),
				'desc_tip'    => true,
			),
		);

		$shipping_method_fields = array_merge( $shipping_method_fields, $settings_fields );
		return $shipping_method_fields;
	}
}
new Dintero_Shipping_Method_Instance();
