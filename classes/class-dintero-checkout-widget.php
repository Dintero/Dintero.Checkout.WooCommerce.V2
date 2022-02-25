<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dintero_Checkout_Widget extends WP_Widget {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		parent::__construct(
			'dintero_checkout_branding',
			__( 'Dintero Checkout Branding', 'dintero-checkout-for-woocommerce' ),
			array(
				'description' => __( 'Display the Dintero Checkout available payment options.', 'dintero-checkout-for-woocommerce' ),
			),
		);
	}

	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		$this->print_icon();
		echo $args['after_widget'];

	}

	public function form( $instance ) {
		$this->print_icon();
	}

	public function update( $new_instance, $old_instance ) {
		/* No widget options to be saved. */
	}

	/**
	 * Outputs the HTML content for showing the icons.
	 *
	 * @return void
	 */
	private function print_icon() {
		$settings    = get_option( 'woocommerce_dintero_checkout_settings' );
		$environment = ( 'yes' === $settings['test_mode'] ) ? 'T' : 'P';

		$branding = array(
			'variant'  => 'colors',
			'color'    => 'cecece',
			'width'    => 600,
			'template' => 'dintero_left_frame.svg',
		);

		if ( 'yes' !== $settings['branding_logo_color'] ) {
			$branding['variant'] = 'mono';
			$branding['color']   = str_replace( '#', '', $settings['branding_logo_color_custom'] );
		}

		$icon_url = 'https://checkout.dintero.com/v1/branding/accounts/' . $environment . $settings['account_id'] . '/profiles/' . $settings['profile_id'];
		foreach ( $branding as $key => $value ) {
			$icon_url .= '/' . $key . '/' . $value;
		}

		$icon_html        = '<img style="margin: 0 auto;" src="' . esc_attr( $icon_url ) . '" style="max-width: 90%" alt="Dintero Logo" />';
		$background_color = ( 'yes' !== $settings['branding_footer_background_color'] ) ? esc_attr( $settings['branding_footer_background_color_custom'] ) : '';
		if ( $background_color ) {
			echo '<div style="padding: 20px 0; background-color:' . $background_color . '">' . $icon_html . '</div>';
		} else {
			echo $icon_html;
		}
	}
}

new Dintero_Checkout_Widget();
