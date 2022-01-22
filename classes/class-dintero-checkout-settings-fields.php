<?php // phpcs:ignore
/**
 * Class for Dintero Checkout settings.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for Dintero settings
 */
class Dintero_Settings_Fields {

	/**
	 * Returns the settings fields.
	 *
	 * $return array List of filtered setting fields.
	 */
	public static function setting_fields() {
		$settings = array(
			'enabled'                    => array(
				'title'       => __( 'Enable', 'dintero-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Dintero Checkout', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                      => array(
				'title'       => __( 'Title', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title.', 'Dintero-checkout-for-woocommerce' ),
				'default'     => 'Dintero',
				'desc_tip'    => true,
			),
			'description'                => array(
				'title'       => __( 'Description', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description.', 'dintero-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'select_another_method_text' => array(
				'title'             => __( 'Other payment method button text', 'dintero-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than Dintero Checkout. Leave blank to use the default (and translatable) text.', 'dintero-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'test_mode'                  => array(
				'title'       => __( 'Test mode', 'dintero-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'dintero-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging'                    => array(
				'title'       => __( 'Logging', 'dintero-checkout-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log (required for troubleshooting).', 'dintero-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'profile_id'                 => array(
				'title'       => __( 'Profile ID', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'desc_tip'    => true,
				'description' => __( 'Test payment window profile ID. Found under (SETTINGS >> Payment windows) in Dintero Backoffice.', 'dinter-checkout-for-woocommerce' ),
			),
			'account_id'                 => array(
				'title'       => __( 'Account ID', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'desc_tip'    => true,
				'description' => __( 'Found under (SETTINGS >> Account) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
			),
			'client_id'                  => array(
				'title'       => __( 'Client ID', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'desc_tip'    => true,
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
			),
			'client_secret'              => array(
				'title'       => __( 'Client secret', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'desc_tip'    => true,
				'description' => __( 'Generated under (SETTINGS >> API clients) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
			),
		);

		return apply_filters( 'dintero_checkout_settings', $settings );
	}

}
