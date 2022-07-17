<?php
/**
 * The Express button is placed in the "mini-cart" widget and the cart page.
 *
 * @package Dintero_Checkout/Classes
 */

class Dintero_Checkout_Express_Button {

	public function __construct() {
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'express_button_placement' ), 15 );

		// TODO: Add the button on the cart page.
		// add_action( 'woocommerce_proceed_to_checkout', array( $this, 'express_button_placement' ) );
		// add_action( 'woocommerce_cart_actions', array( $this, 'express_button_placement' ) );
	}

	public function express_button_placement() {
		$settings = get_option( 'woocommerce_dintero_checkout_settings' );

		if ( 'yes' !== $settings['express_show_product_button'] ) {
			return;
		}

		$image_url     = 'https://assets.dintero.com/logo/dintero-express-btn-' . $settings['express_button_image'] . '.svg';
		$corner_radius = $settings['express_button_corner_radius'];

		$style = 'background: url(' . $image_url . ') no-repeat; background-size: cover; background-position: center; min-height: 44px;';
		if ( ! empty( $corner_radius ) && 0 < intval( $corner_radius ) ) {
			$style .= 'corner-radius: ' . $corner_radius . 'px;';
		}

		echo '<a style="' . $style . '" href="#"></a>';
	}

} new Dintero_Checkout_Express_Button();
