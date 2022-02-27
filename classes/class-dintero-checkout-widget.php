<?php
/**
 * This file is responsible for creating the Dintero branding widget (available in Appearance → Widgets).
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for the branding widget.
 */
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

	/**
	 * Echoes the widget content (on the front-end of the store).
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args Display arguments.
	 * @param array $instance The settings for the particular instance of the widget.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		$this->print_icon( $instance['icon_color'], $instance['background_color'] );
		echo $args['after_widget'];
	}

	/**
	 * Outputs the settings form (in the Widgets' admin page).
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Current settings.
	 * @return string The HTML for the settings forms.
	 */
	public function form( $instance ) {
		?>
		<p>
			<input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id( 'use_default' ); ?>" name="<?php echo $this->get_field_name( 'use_default' ); ?>" /></label>
			<label for="<?php echo $this->get_field_id( 'use_default' ); ?>"><?php _e( 'Default icon color', 'dintero-checkout-for-woocommerce' ); ?>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'icon_color' ); ?>"><?php _e( 'Icon color:', 'dintero-checkout-for-woocommerce' ); ?>
			<input type="color" class="widefat colorpick" value="#cecece" id="<?php echo $this->get_field_id( 'icon_color' ); ?>" name="<?php echo $this->get_field_name( 'icon_color' ); ?>"  /></label>
			
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'background_color' ); ?>"><?php _e( 'Background color:', 'dintero-checkout-for-woocommerce' ); ?>
			<input type="color" class="widefat colorpick" value="#ffffff" id="<?php echo $this->get_field_id( 'background_color' ); ?>" name="<?php echo $this->get_field_name( 'background_color' ); ?>"  /></label>
		</p>
		<?php

	}

	/**
	 * Updates a particular instance of a widget.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance New settings for this instance as input by the user via WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array|boolean Settings to save or FALSE to cancel saving.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance                = $old_instance;
		$instance['use_default'] = ( isset( $new_instance['use_default'] ) ) ? $new_instance['use_default'] : $old_instance['use_default'];

		if ( 'on' !== $instance['use_default'] ) {
			$instance['icon_color']       = ( isset( $new_instance['icon_color'] ) ) ? wp_strip_all_tags( $new_instance['icon_color'] ) : '';
			$instance['background_color'] = ( isset( $new_instance['background_color'] ) ) ? wp_strip_all_tags( $new_instance['background_color'] ) : '';
		} else {
			$instance = array(
				'icon_color'       => 'cecece',
				'background_color' => 'ffffff',

			);
		}

		return $instance;
	}

	/**
	 * Outputs the HTML content for showing the icons.
	 *
	 * @param string         $icon_color The color of the icon (hexadecimal).
	 * @param string|boolean $background_color The background color (hexadecimal) or FALSE for default color.
	 *
	 * @return string Echoes HTML content.
	 */
	private function print_icon( $icon_color = 'cecece', $background_color = false ) {
		$settings    = get_option( 'woocommerce_dintero_checkout_settings' );
		$environment = ( 'yes' === $settings['test_mode'] ) ? 'T' : 'P';

		$branding = array(
			'variant'  => 'colors',
			'color'    => 'cecece',
			'width'    => 600,
			'template' => 'dintero_left_frame.svg',
		);

		$icon_color = empty( $icon_color ) ? $branding['color'] : $icon_color;
		if ( $icon_color !== $branding['color'] ) {
			$branding['variant'] = 'mono';
			$branding['color']   = str_replace( '#', '', $icon_color );
		}

		$icon_url = 'https://checkout.dintero.com/v1/branding/accounts/' . $environment . $settings['account_id'] . '/profiles/' . $settings['profile_id'];
		foreach ( $branding as $key => $value ) {
			$icon_url .= '/' . $key . '/' . $value;
		}

		$icon_html = '<img style="margin: 0 auto;" src="' . esc_attr( $icon_url ) . '" style="max-width: 90%" alt="Dintero Logo" />';
		if ( ! empty( $background_color ) ) {
			echo '<div style="padding: 20px 0; background-color:' . esc_attr( $background_color ) . '">' . $icon_html . '</div>';
		} else {
			echo $icon_html;
		}
	}
}

new Dintero_Checkout_Widget();
