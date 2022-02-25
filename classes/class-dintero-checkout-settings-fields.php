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
	 * @static
	 * @return array List of filtered setting fields.
	 */
	public static function setting_fields() {
		$settings = array(
			'enabled'                                 => array(
				'title'       => __( 'Enable/Disable', 'dintero-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Dintero Checkout', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
			'redirect_title'                          => array(
				'title'       => __( 'Title', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title (appears on checkout page if more than one payment method is available).', 'Dintero-checkout-for-woocommerce' ),
				'default'     => 'Dintero Checkout',
				'desc_tip'    => true,
			),
			'redirect_description'                    => array(
				'title'       => __( 'Description', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description (appears on checkout page if more than one payment method is available).', 'Dintero-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Choose your payment method in our checkout.', 'dintero-checkout-for-woocommerce' ),
			),

			'dintero_configuration'                   => array(
				'title' => __( 'Dintero configuration', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'account_id'                              => array(
				'title'       => __( 'Account ID', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'desc_tip'    => true,
				'description' => __( 'Found under (SETTINGS → Account) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
			),
			'client_id'                               => array(
				'title'       => __( 'Client ID', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'default'     => '',
				'desc_tip'    => true,
				'description' => __( 'Generated under (SETTINGS → API clients) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
			),
			'client_secret'                           => array(
				'title'       => __( 'Client secret', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'password',
				'default'     => '',
				'desc_tip'    => true,
				'description' => __( 'Generated under (SETTINGS → API clients) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
			),
			'profile_id'                              => array(
				'title'       => __( 'Profile ID', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'default'     => 'default',
				'desc_tip'    => true,
				'description' => __( 'Test payment window profile ID. Found under (SETTINGS → Payment windows) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
			),
			'test_mode'                               => array(
				'title'       => __( 'Enable test mode:', 'dintero-checkout-for-woocommerce' ),
				'label'       => ' ',
				'type'        => 'checkbox',
				'description' => __( 'While in test mode, the customers will NOT be charged. Useful for testing and debugging purposes.', 'dintero-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'logging'                                 => array(
				'title'       => __( 'Enable logging', 'dintero-checkout-for-woocommerce' ),
				'label'       => ' ',
				'type'        => 'checkbox',
				'description' => __( 'Required for troubleshooting. Log messages older than 30 days are automatically deleted.', 'dintero-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),

			'branding_title'                          => array(
				'title' => __( 'Branding', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'branding_enable_footer_logo'             => array(
				'title'   => __( 'Enable logos in footer', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => __( 'Enable', 'dintero-checkout-for-woocommerce' ),
			),
			'branding_logo_color'                     => array(
				'title'   => __( 'Logo color', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'yes',
				'label'   => __( 'Default color', 'dintero-checkout-for-woocommerce' ),
			),
			'branding_logo_color_custom'              => array(
				'title' => __( 'Custom color', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'color',
			),
			'branding_footer_background_color'        => array(
				'title'   => __( 'Footer background color', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'yes',
				'label'   => __( 'Default color', 'dintero-checkout-for-woocommerce' ),
			),
			'branding_footer_background_color_custom' => array(
				'title' => __( 'Custom color', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'color',
			),
		);

		return apply_filters( 'dintero_checkout_settings', $settings );
	}

}
