<?php
/**
 * File for handling all things related to the template on the checkout page.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dintero_Checkout_Templates class.
 */
class Dintero_Checkout_Template {
	/**
	 * The reference the *Singleton* instance of this class.
	 *
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Settings for the dintero plugin.
	 *
	 * @var array
	 */
	public $settings;

	/**
	 * Returns the *Singleton* instance of this class.
	 *
	 * @return self::$instance The *Singleton* instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'woocommerce_dintero_checkout_settings' );
		if ( 'embedded' === $this->settings['form_factor'] || 'yes' === $this->settings['enabled'] ) {
			// Common.
			add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );
			add_action( 'dintero_iframe', array( $this, 'iframe' ) );

			// Express.
			add_action( 'dintero_express_order_review', array( $this, 'express_order_review' ) );
			add_action( 'dintero_express_order_review', 'dintero_checkout_wc_show_another_gateway_button', 20 );
			add_action( 'dintero_express_form', array( $this, 'express_form' ), 20 );
			add_action( 'dintero_express_iframe', array( $this, 'add_wc_form' ) );
		}
	}

	/**
	 * Override the checkout form template if Dintero Checkout is the selected payment method.
	 *
	 * @param string $template The absolute path to the template.
	 * @param string $template_name The relative path to the template (known as the 'name').
	 * @return string
	 */
	public function override_template( $template, $template_name ) {
		if ( ! is_checkout() ) {
			return $template;
		}

		if ( ! WC()->cart->needs_payment() ) {
			return $template;
		}

		if ( 'express' === $this->settings['checkout_type'] ) {
			return $this->maybe_replace_checkout( $template, $template_name );
		}

		if ( 'checkout' === $this->settings['checkout_type'] ) {
			return $this->replace_payment_method( $template, $template_name );
		}

		return $template;
	}

	/**
	 * Maybe replaces the checkout form template in Dintero is the selected payment method
	 *
	 * @param string $template The absolute path to the template.
	 * @param string $template_name The relative path to the template (known as the 'name').
	 * @return string
	 */
	public function maybe_replace_checkout( $template, $template_name ) {
		if ( 'checkout/form-checkout.php' === $template_name ) {
			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
			if ( ! array_key_exists( 'dintero_checkout', $available_gateways ) ) {
				return $template;
			}

			// Retrieve the template for Dintero Checkout Express.
			$maybe_template            = locate_template( 'woocommerce/dintero-checkout-express.php' );
			$checkout_express_template = ( $maybe_template ) ? $maybe_template : DINTERO_CHECKOUT_PATH . '/templates/dintero-checkout-express.php';

			// If Dintero Checkout is already selected as a gateway...
			$chosen_payment_method = WC()->session->chosen_payment_method;
			if ( 'dintero_checkout' === $chosen_payment_method ) {
				return $checkout_express_template;
			}

			// If no gateway is selected, but Dintero Checkout is available, and is the first gateway...
			if ( empty( $chosen_payment_method ) && 'dintero_checkout' === array_key_first( $available_gateways ) ) {
				return $checkout_express_template;
			}

			// If another gateway was, but is made unavailable during checkout, and Dintero Checkout is available, and is the first gateway...
			if ( ! in_array( $chosen_payment_method, $available_gateways ) && 'dintero_checkout' === array_key_first( $available_gateways ) ) {
				return $checkout_express_template;
			}
		}

		return $template;
	}

	/**
	 * Replaces the payment method template.
	 *
	 * @param string $template The absolute path to the template.
	 * @param string $template_name The relative path to the template (known as the 'name').
	 * @return string
	 */
	public function replace_payment_method( $template, $template_name ) {
		if ( 'checkout/payment.php' === $template_name ) {
			WC()->session->set( 'chosen_payment_method', 'dintero_checkout' );
			// Retrieve the template for Dintero Checkout template.
			$maybe_template             = locate_template( 'woocommerce/dintero-checkout-embedded.php' );
			$checkout_embedded_template = ( $maybe_template ) ? $maybe_template : DINTERO_CHECKOUT_PATH . '/templates/dintero-checkout-embedded.php';

			return $checkout_embedded_template;
		}

		return $template;
	}

	/**
	 * Adds the WooCommerce form and other fields to the checkout page (although hidden) for Dintero Express.
	 *
	 * @return void
	 */
	public function express_form() {
		do_action( 'woocommerce_checkout_billing' );
		do_action( 'woocommerce_checkout_shipping' );
		if ( version_compare( WOOCOMMERCE_VERSION, '3.4', '<' ) ) {
			wp_nonce_field( 'woocommerce-process_checkout' );
		} else {
			wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
		}
		wc_get_template( 'checkout/terms.php' );
		?>
		<input id="payment_method_dintero_checkout" type="radio" class="input-radio" name="payment_method" value="dintero_checkout" checked="checked" />
		<?php
	}

	/**
	 * Adds the order review for Dintero Express.
	 *
	 * @return void
	 */
	public function express_order_review() {
		woocommerce_order_review();
	}

	/**
	 * Prints the iframe wrapper for Dintero.
	 *
	 * @return void
	 */
	public function iframe() {
		?>
		<div id='dintero-checkout-iframe'></div>
		<?php
	}
}

Dintero_Checkout_Template::get_instance();
