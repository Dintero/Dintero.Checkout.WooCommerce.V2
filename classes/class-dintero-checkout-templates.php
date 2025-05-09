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
class Dintero_Checkout_Templates {
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
	 * The layout for embedded checkout
	 *
	 * @var mixed|string
	 */
	protected $checkout_layout = null;

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
		$this->settings        = get_option( 'woocommerce_dintero_checkout_settings' );
		$this->checkout_layout = $this->settings['checkout_layout'] ?? 'two_column_right';
		if ( dwc_is_embedded( $this->settings ) && wc_string_to_bool( $this->settings['enabled'] ?? 'no' ) ) {
			// Common.
			add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );
			add_action( 'dintero_iframe', array( $this, 'iframe' ) );

			// Express.
			add_action( 'dintero_express_after_order_review', array( $this, 'dintero_express_add_extra_checkout_fields' ) );
			add_action( 'dintero_express_order_review', array( $this, 'express_order_review' ) );
			add_action( 'dintero_express_order_review', 'dintero_checkout_wc_show_another_gateway_button', 20 );
			add_action( 'dintero_express_form', array( $this, 'express_form' ), 20 );
			add_action( 'dintero_express_iframe', array( $this, 'add_wc_form' ) );
			add_filter( 'body_class', array( $this, 'add_body_class' ) );
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

		// Dintero is not available for free orders except for free trial subscriptions. Refer to Dintero_Checkout_Subscription::cart_needs_payment().
		if ( ! WC()->cart->needs_payment() ) {
			return $template;
		}

		/* For all form factors, redirect is used for order-pay since the cart object (used for embedded) is not available. */
		if ( is_checkout_pay_page() ) {
			return $template;
		}

		return dwc_is_express( $this->settings )
			? $this->maybe_replace_checkout( $template, $template_name )
			: $this->replace_payment_method( $template, $template_name );
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

			// Check if Dintero is available. This is important when a non-zero subscription converts to a zero subscription. When that happens, we no longer want to override the checkout page. Let WC display its template instead.
			if ( ! array_key_exists( 'dintero_checkout', $available_gateways ) ) {
				return $template;
			}

			// Retrieve the template for Dintero Checkout Express.
			$maybe_template            = locate_template( 'woocommerce/dintero-checkout-express.php' );
			$checkout_express_template = ! empty( $maybe_template ) ? $maybe_template : DINTERO_CHECKOUT_PATH . '/templates/dintero-checkout-express.php';

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
			if ( ! isset( $available_gateways[ $chosen_payment_method ] ) && 'dintero_checkout' === array_key_first( $available_gateways ) ) {
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
			$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
			if ( ! array_key_exists( 'dintero_checkout', $available_gateways ) ) {
				return $template;
			}

			WC()->session->set( 'chosen_payment_method', 'dintero_checkout' );
			// Retrieve the template for Dintero Checkout template.
			$maybe_template             = locate_template( 'woocommerce/dintero-checkout-embedded.php' );
			$checkout_embedded_template = empty( $maybe_template ) ? DINTERO_CHECKOUT_PATH . '/templates/dintero-checkout-embedded.php' : $template;

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

	/**
	 * Add checkout page body class, embedded only.
	 *
	 * @param array $class CSS classes used in body tag.
	 *
	 * @return array
	 */
	public function add_body_class( $class ) {
		if ( is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {

			// Dintero is not available for free orders except for free trial subscriptions. Refer to Dintero_Checkout_Subscription::cart_needs_payment().
			if ( method_exists( WC()->cart, 'needs_payment' ) && ! WC()->cart->needs_payment() ) {
				return $class;
			}

			if ( WC()->session->get( 'chosen_payment_method' ) ) {
				$first_gateway = WC()->session->get( 'chosen_payment_method' );
			} else {
				$available_payment_gateways = WC()->payment_gateways->get_available_payment_gateways();
				reset( $available_payment_gateways );
				$first_gateway = key( $available_payment_gateways );
			}

			if ( 'dintero_checkout' === $first_gateway && 'two_column_left' === $this->checkout_layout ) {
				$class[] = 'dintero-checkout-one-selected';
				$class[] = 'dintero-checkout-two-column-left';
			}

			if ( 'dintero_checkout' === $first_gateway && 'two_column_right' === $this->checkout_layout ) {
				$class[] = 'dintero-checkout-one-selected';
				$class[] = 'dintero-checkout-two-column-right';
			}

			if ( 'dintero_checkout' === $first_gateway && 'one_column_checkout' === $this->checkout_layout ) {
				$class[] = 'dintero-checkout-one-selected';
			}
		}
		return $class;
	}

	/**
	 * Adds the extra checkout field div to the checkout page.
	 */
	public function dintero_express_add_extra_checkout_fields() {
		do_action( 'dintero_express_before_extra_fields' );
		?>
		<div id="dintero-express-extra-checkout-fields"></div>
		<?php
		do_action( 'dintero_express_after_extra_fields' );
	}
}

Dintero_Checkout_Templates::get_instance();
