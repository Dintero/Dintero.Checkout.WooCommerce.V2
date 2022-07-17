<?php
/**
 * The Express button is placed in the "mini-cart" widget and the cart page.
 *
 * @package Dintero_Checkout/Classes
 */

class Dintero_Checkout_Express_Button {

	public function __construct() {
		add_action( 'woocommerce_widget_shopping_cart_buttons', array( $this, 'express_button_placement' ), 15 );
		add_action( 'woocommerce_cart_actions', array( $this, 'express_button_placement_cart' ) );
	}

	public function express_button_placement() {
		$settings = get_option( 'woocommerce_dintero_checkout_settings' );

		if ( 'yes' !== $settings['express_show_product_button'] ) {
			return;
		}

		$image_url     = 'https://assets.dintero.com/logo/dintero-express-btn-' . $settings['express_button_image'] . '.svg';
		$border_radius = $settings['express_button_corner_radius'];

		$style = 'background: url(' . $image_url . ') no-repeat; background-size: cover; background-position: center; min-height: 44px;';
		if ( ! empty( $border_radius ) && 0 < intval( $border_radius ) ) {
			$style .= 'border-radius: ' . $border_radius . 'px;';
		}

		echo '<a style="' . $style . '" href="#"></a>';
	}

	public function express_button_placement_cart() {
		$settings = get_option( 'woocommerce_dintero_checkout_settings' );

		if ( 'yes' !== $settings['express_show_product_button'] ) {
			return;
		}

		$image_url     = 'https://assets.dintero.com/logo/dintero-express-btn-' . $settings['express_button_image'] . '.svg';
		$border_radius = $settings['express_button_corner_radius'];

		$style = 'background: url(' . $image_url . ') no-repeat; background-size: cover; background-position: center; min-height: 44px; width: 142px; float: right; margin-left: 5px;';
		if ( ! empty( $border_radius ) && 0 < intval( $border_radius ) ) {
			$style .= 'border-radius: ' . $border_radius . 'px;';
		}

		echo '<button type="button" class="button" title="Buy now with Dintero!" style="' . $style . '"></button>';
	}

} new Dintero_Checkout_Express_Button();
