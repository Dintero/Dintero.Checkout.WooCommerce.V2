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
		$allowed_html = array(
			'div' => array(
				'id'    => array(),
				'class' => array(),
			),
		);
		echo wp_kses( $args['before_widget'], $allowed_html );
		$this->print_icon( $instance['icon_color'], $instance['background_color'] );
		echo wp_kses( $args['after_widget'], $allowed_html );
	}

	/**
	 * Outputs the settings form (in the Widgets' admin page).
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Current settings.
	 * @return void
	 */
	public function form( $instance ) {
		$use_default      = $this->get_field_id( 'use_default' );
		$icon_color       = $this->get_field_id( 'icon_color' );
		$background_color = $this->get_field_id( 'background_color' );
		?>
		<p>
			<input type="checkbox" class="checkbox" id="<?php echo esc_attr( $use_default ); ?>" name="<?php echo esc_attr( $use_default ); ?>" /></label>
			<label for="<?php echo esc_attr( $use_default ); ?>"><?php esc_html_e( 'Default icon color', 'dintero-checkout-for-woocommerce' ); ?>
		</p>
		<p>
			<label for="<?php echo esc_attr( $icon_color ); ?>"><?php esc_html_e( 'Icon color:', 'dintero-checkout-for-woocommerce' ); ?>
			<input type="color" class="widefat colorpick" value="#cecece" id="<?php echo esc_attr( $icon_color ); ?>" name="<?php echo esc_attr( $icon_color ); ?>"  /></label>
		</p>
		<p>
			<label for="<?php echo esc_attr( $background_color ); ?>"><?php esc_html_e( 'Background color:', 'dintero-checkout-for-woocommerce' ); ?>
			<input type="color" class="widefat colorpick" value="#ffffff" id="<?php echo esc_attr( $background_color ); ?>" name="<?php echo esc_attr( $background_color ); ?>"  /></label>
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
		$instance                     = $old_instance;
		$instance['use_default']      = ( isset( $new_instance['use_default'] ) ) ? $new_instance['use_default'] : $old_instance['use_default'];
		$instance['background_color'] = ( isset( $new_instance['background_color'] ) ) ? wp_strip_all_tags( $new_instance['background_color'] ) : '';

		if ( 'on' !== $instance['use_default'] ) {
			$instance['icon_color'] = ( isset( $new_instance['icon_color'] ) ) ? wp_strip_all_tags( $new_instance['icon_color'] ) : '';
		} else {
			$instance['icon_color'] = 'cecece';
		}

		return $instance;
	}

	/**
	 * Outputs the HTML content for showing the icons.
	 *
	 * @param string         $icon_color The color of the icon (hexadecimal).
	 * @param string|boolean $background_color The background color (hexadecimal) or FALSE for default color.
	 *
	 * @return void
	 */
	private function print_icon( $icon_color = 'cecece', $background_color = false ) {
		$icon_url = dintero_get_brand_image_url( $icon_color );

		?>
			<div style="padding: 20px 0; <?php echo ( ! empty( $background_color ) ) ? esc_attr( "background-color: $background_color" ) : ''; ?> ">
			<a href="https://www.dintero.com" target="_blank" title="<?php echo dintero_keyword_backlinks(); ?>">
				<img loading="lazy" style="margin: 0 auto;" src="<?php echo esc_attr( $icon_url ); ?>" style="max-width: 90%" alt="Dintero Logo" />
			</a>
			</div>
		<?php
	}
}

new Dintero_Checkout_Widget();
