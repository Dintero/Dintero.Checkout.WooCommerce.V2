<?php

/**
 * Utility functions.
 *
 * @package Dintero_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add a button for changing gateway (intended to be used on the checkout page).
 *
 * @return void
 */
function dintero_checkout_wc_show_another_gateway_button() {
	$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
	if ( count( $available_gateways ) > 1 ) {

		$settings                   = get_option( 'woocommerce_dintero_checkout_settings' );
		$select_another_method_text = $settings['redirect_select_another_method_text'];

		if ( empty( $select_another_method_text ) ) {
			$select_another_method_text = __( 'Select another payment method', 'dintero-checkout-for-woocommerce' );
		}

		?>
		<p class="dintero-checkout-select-other-wrapper">
			<a class="checkout-button button" href="#" id="dintero-checkout-select-other">
				<?php echo esc_html( $select_another_method_text ); ?>
			</a>
		</p>
		<?php
	}
}

/**
 * Unsets all sessions set by Dintero.
 *
 * @return void
 */
function dintero_unset_sessions() {
	WC()->session->__unset( 'dintero_checkout_session_id' );
	WC()->session->__unset( 'dintero_merchant_reference' );
}
