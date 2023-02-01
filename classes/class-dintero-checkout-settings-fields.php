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
			'dintero_configuration'                   => array(
				'title' => __( 'Dintero configuration', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'account_id'                              => array(
				'title'             => __( 'Account ID', 'dintero-checkout-for-woocommerce' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'Found under (SETTINGS → Account) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'client_id'                               => array(
				'title'             => __( 'Client ID', 'dintero-checkout-for-woocommerce' ),
				'type'              => 'text',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'Generated under (SETTINGS → API clients) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'client_secret'                           => array(
				'title'             => __( 'Client secret', 'dintero-checkout-for-woocommerce' ),
				'type'              => 'password',
				'default'           => '',
				'desc_tip'          => true,
				'description'       => __( 'Generated under (SETTINGS → API clients) in Dintero Backoffice.', 'dintero-checkout-for-woocommerce' ),
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
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
			'checkout_type_title'                     => array(
				'title' => __( 'Checkout type', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'checkout_type'                           => array(
				'default' => 'express',
				'type'    => 'select',
				'options' => array(
					'checkout' => __( 'Dintero Checkout', 'dintero-checkout-for-woocommerce' ),
					'express'  => __( 'Dintero Checkout Express', 'dintero-checkout-for-woocommerce' ),
				),
			),
			'checkout_layout'                         => array(
				'title'       => __( 'Checkout layout', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'one_column_checkout' => __( 'One column checkout', 'dintero-checkout-for-woocommerce' ),
					'two_column_right'    => __( 'Two column checkout (Dintero checkout in right column)', 'dintero-checkout-for-woocommerce' ),
					'two_column_left'     => __( 'Two column checkout (Dintero checkout in left column)', 'dintero-checkout-for-woocommerce' ),
				),
				'description' => __( 'Select the Checkout layout.', 'dintero-checkout-for-woocommerce' ),
				'default'     => 'two_column_right',
				'desc_tip'    => false,
			),
			'form_factor_title'                       => array(
				'title' => __( 'Form factor', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'form_factor'                             => array(
				'default' => 'redirect',
				'type'    => 'select',
				'options' => array(
					'embedded' => __( 'Embedded', 'dintero-checkout-for-woocommerce' ),
					'redirect' => __( 'Redirect', 'dintero-checkout-for-woocommerce' ),
				),
			),
			/* The "Redirect box" is hidden until the form factor Redirect is selected. */
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
			'redirect_logo_color'                     => array(
				'title'   => __( 'Logo color', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Default color', 'dintero-checkout-for-woocommerce' ),
				'default' => 'yes',
			),
			'redirect_logo_color_custom'              => array(
				'title' => __( 'Custom color (HEX)', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'color',
			),
			'redirect_select_another_method_text'     => array(
				'title'       => __( '"Go to payment" button', 'dintero-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Customize the <em>"Go to payment"</em> button text that is displayed in checkout if other payment methods than Dintero Checkout are available. Leave blank to use the default (and translatable) text.', 'dintero-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
				'placeholder' => __( 'Enter your text here (optional)', 'dintero-checkout-for-woocommerce' ),
			),
			/* End of "Redirect box". */
			'order_statuses_title'                    => array(
				'title' => __( 'Order statuses', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'order_statuses'                          => array(
				'title'   => 'Default order status when authorized',
				'type'    => 'select',
				'default' => 'processing',
				'options' => array(
					'processing' => __( 'Processing', 'dintero-checkout-for-woocommerce' ),
					'on-hold'    => __( 'On-hold', 'dintero-checkout-for-woocommerce' ),
				),
			),
			/* Order Management */
			'order_management_title'                  => array(
				'title' => __( 'Order Management', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'order_management_manual_refund'          => array(
				'title'   => __( 'Refund by changing order status', 'dintero-checkout-for-woocommerce' ),
				'label'   => __( 'Trigger refund in Dintero when WooCommerce order status is manually changed to <u>Refunded</u>.', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
			),
			/* The Dintero Checkout Express Settings */
			'dintero_checkout_express_settings_title' => array(
				'title' => __( 'Dintero Checkout Express Settings', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'express_customer_type'                   => array(
				'title'   => __( 'Allowed customer types', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'b2bc',
				'options' => array(
					'b2bc' => __( 'Consumers and businesses', 'dintero-checkout-for-woocommerce' ),
					'b2b'  => __( 'Business only', 'dintero-checkout-for-woocommerce' ),
					'b2c'  => __( 'Consumer only', 'dintero-checkout-for-woocommerce' ),
				),
			),
			'express_shipping_in_iframe'              => array(
				'title'    => __( 'Display Shipping in the iframe', 'dintero-checkout-for-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'no',
				'label'    => __( 'Enable', 'dintero-checkout-for-woocommerce' ),
				'desc_tip' => __( 'Will make the shipping selection happen in the Dintero checkout iframe instead of the shipping section in WooCommerce.', 'dintero-checkout-for-woocommerce' ),
			),

			/*
			TODO: These options will be added in a future iteration.
			'express_show_shipping'                   => array(
				'title'    => __( 'Show shipping info in Express', 'dintero-checkout-for-woocommerce' ),
				'type'     => 'checkbox',
				'default'  => 'no',
				'label'    => __( 'Enable', 'dintero-checkout-for-woocommerce' ),
				'desc_tip' => __( 'Disable if you sell digital products.', 'dintero-checkout-for-woocommerce' ),
			),
			'express_show_product_button'             => array(
				'title'   => __( 'Show Express button on product page', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'no',
				'label'   => __( 'Enable', 'dintero-checkout-for-woocommerce' ),
			),
			'express_button_image'                    => array(
				'title'   => __( 'Express button image', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'select',
				'default' => 'dark',
				'options' => array(
					'dark'  => __( 'Dark', 'dintero-checkout-for-woocommerce' ),
					'light' => __( 'Light', 'dintero-checkout-for-woocommerce' ),
				),
			),
			'express_button_corner_radius'            => array(
				'title' => __( 'Express button corner radius', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'text',
			),
			*/
			/* End of "Dintero Checkout Express Settings". */
			'branding_title'                          => array(
				'title' => __( 'Branding', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'branding_logo_color'                     => array(
				'title'   => __( 'Logo color', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'yes',
				'label'   => __( 'Default color', 'dintero-checkout-for-woocommerce' ),
			),
			'branding_logo_color_custom'              => array(
				'title' => __( 'Custom color (HEX)', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'color',
			),
			'branding_footer_background_color'        => array(
				'title'   => __( 'Footer background color', 'dintero-checkout-for-woocommerce' ),
				'type'    => 'checkbox',
				'default' => 'yes',
				'label'   => __( 'Default color', 'dintero-checkout-for-woocommerce' ),
			),
			'branding_footer_background_color_custom' => array(
				'title' => __( 'Custom color (HEX)', 'dintero-checkout-for-woocommerce' ),
				'type'  => 'color',
			),
		);

		return apply_filters( 'dintero_checkout_settings', $settings );
	}

	/**
	 * Retrieve the text for the order button.
	 *
	 * Hook: woocommerce_order_button_text
	 * Hook: woocommerce_pay_order_button_text
	 *
	 * @param  string $button_text
	 * @return string
	 */
	public static function order_button_text( $button_text ) {
		$settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( ! empty( $settings['redirect_select_another_method_text'] ) ) {
			return $settings['redirect_select_another_method_text'];
		}

		return $button_text;
	}

	/**
	 * Delete the access token when the merchant switch between test v. production mode.
	 *
	 * @param  array $new_settings The Dintero WooCommerce settings that were changed.
	 * @param  array $old_settings The Dintero WooCommerce settings before the change.
	 * @return void Maybe delete the dintero_checkout_access_token transient.
	 */
	public static function maybe_update_access_token( $new_settings, $old_settings ) {
		if ( $new_settings['test_mode'] !== $old_settings['test_mode'] ) {
			delete_transient( 'dintero_checkout_access_token' );
		}
	}
}
