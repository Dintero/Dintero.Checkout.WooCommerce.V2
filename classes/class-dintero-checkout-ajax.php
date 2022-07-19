<?php
/**
 * Ajax class file.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ajax class.
 */
class Dintero_Checkout_Ajax extends WC_AJAX {
	/**
	 * Hook in ajax handlers.
	 */
	public static function init() {
		self::add_ajax_events();
	}

	/**
	 * Hook in methods - uses WordPress ajax handlers (admin-ajax).
	 */
	public static function add_ajax_events() {
		$ajax_events = array(
			'dintero_checkout_wc_change_payment_method' => true,
			'dintero_checkout_wc_log_js'                => true,
			'dintero_checkout_unset_session'            => true,
			'dintero_checkout_print_notice'             => true,
			'dintero_checkout_express_button'           => true,
		);
		foreach ( $ajax_events as $ajax_event => $nopriv ) {
			add_action( 'wp_ajax_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_woocommerce_' . $ajax_event, array( __CLASS__, $ajax_event ) );
				// WC AJAX can be used for frontend ajax requests.
				add_action( 'wc_ajax_' . $ajax_event, array( __CLASS__, $ajax_event ) );
			}
		}
	}

	/**
	 * Maybe change payment method.
	 */
	public static function dintero_checkout_wc_change_payment_method() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_wc_change_payment_method' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$available_gateways         = WC()->payment_gateways()->get_available_payment_gateways();
		$switch_to_dintero_checkout = isset( $_POST['dintero_checkout'] ) ? sanitize_text_field( wp_unslash( $_POST['dintero_checkout'] ) ) : '';
		if ( 'false' === $switch_to_dintero_checkout ) {
			// Set chosen payment method to first gateway that is not Dintero Checkout for WooCommerce.
			$first_gateway = array_key_first( $available_gateways );
			if ( 'dintero_checkout' !== $first_gateway ) {
				WC()->session->set( 'chosen_payment_method', $first_gateway );
			} else {
				$second_gateway = next( $available_gateways );
				WC()->session->set( 'chosen_payment_method', $second_gateway->id );
			}
		} else {
			WC()->session->set( 'chosen_payment_method', 'dintero_checkout' );
		}

		WC()->payment_gateways()->set_current_gateway( $available_gateways );

		$redirect = wc_get_checkout_url();
		$data     = array(
			'redirect' => $redirect,
		);

		wp_send_json_success( $data );
		wp_die();
	}

	/**
	 * Logs messages from the JavaScript to the server log.
	 *
	 * @return void
	 */
	public static function dintero_checkout_wc_log_js() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_wc_log_js' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}
		$posted_message            = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';
		$dintero_checkout_order_id = WC()->session->get( 'dintero_checkout_order_id' );
		$message                   = "Frontend JS $dintero_checkout_order_id: $posted_message";
		Dintero_Checkout_Logger::log( $message );
		wp_send_json_success();
		wp_die();
	}

	/**
	 * Unset the WooCommerce session.
	 *
	 * @return void
	 */
	public static function dintero_checkout_unset_session() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_unset_session' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		dintero_unset_sessions();
	}


	/**
	 * Prints checkout noticesfrom Dintero.
	 *
	 * @return void
	 */
	public static function dintero_checkout_print_notice() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_key( $_POST['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_print_notice' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$notice_type = filter_input( INPUT_POST, 'notice_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		wc_add_notice( filter_input( INPUT_POST, 'message', FILTER_SANITIZE_FULL_SPECIAL_CHARS ), $notice_type );
	}

	public static function dintero_checkout_express_button() {
		$nonce = isset( $_GET['nonce'] ) ? sanitize_key( $_GET['nonce'] ) : '';
		if ( ! wp_verify_nonce( $nonce, 'dintero_checkout_express_button' ) ) {
			wp_send_json_error( 'bad_nonce' );
			exit;
		}

		$cart      = WC()->cart;
		$contents  = $cart->get_cart_contents();
		$cart_hash = md5( wp_json_encode( wc_clean( $contents ) ) . $cart->total );

		$order_id = WC()->session->get( 'order_awaiting_payment' );
		$order    = $order_id ? wc_get_order( $order_id ) : false;

		/**
		 * Since we manually create a new order, if for whatever reason the order was not completed (e.g., the customer cancel's the payment), we'll resume from that order instead of creating a new one.
		 */
		if ( $order && $order->has_cart_hash( $cart_hash ) && $order->has_status( array( 'pending', 'failed' ) ) ) {
			$order->remove_order_items();
		} else {
			$order = wc_create_order( array( 'status' => 'pending' ) );
		}

		$order->set_payment_method( 'dintero_checkout' );
		$order->set_cart_hash( $cart->get_cart_hash() );

		$order->set_billing_first_name( WC()->customer->get_billing_first_name() );
		$order->set_billing_last_name( WC()->customer->get_billing_last_name() );
		$order->set_billing_email( WC()->customer->get_billing_email() );
		$order->set_billing_phone( WC()->customer->get_billing_phone() );
		$order->set_billing_address_1( WC()->customer->get_billing_address_1() );
		$order->set_billing_address_2( WC()->customer->get_billing_address_2() );
		$order->set_billing_postcode( WC()->customer->get_billing_postcode() );
		$order->set_billing_city( WC()->customer->get_billing_city() );
		$order->set_billing_state( WC()->customer->get_billing_state() );
		$order->set_billing_country( WC()->customer->get_billing_country() );

		$order->set_shipping_first_name( WC()->customer->get_shipping_first_name() );
		$order->set_shipping_last_name( WC()->customer->get_shipping_last_name() );
		$order->set_shipping_phone( WC()->customer->get_shipping_phone() );
		$order->set_shipping_address_1( WC()->customer->get_shipping_address_1() );
		$order->set_shipping_address_2( WC()->customer->get_shipping_address_2() );
		$order->set_shipping_postcode( WC()->customer->get_shipping_postcode() );
		$order->set_shipping_city( WC()->customer->get_shipping_city() );
		$order->set_shipping_state( WC()->customer->get_shipping_state() );
		$order->set_shipping_country( WC()->customer->get_shipping_country() );

		$order->set_customer_id( apply_filters( 'woocommerce_checkout_customer_id', get_current_user_id() ) );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_prices_include_tax( 'yes' === get_option( 'woocommerce_prices_include_tax' ) );
		$order->set_customer_ip_address( WC_Geolocation::get_ip_address() );
		$order->set_customer_user_agent( wc_get_user_agent() );
		$order->set_discount_total( $cart->get_discount_total() );
		$order->set_discount_tax( $cart->get_discount_tax() );
		$order->set_cart_tax( $cart->get_cart_contents_tax() + $cart->get_fee_tax() );

		// Use these methods directly - they should be safe.
		WC()->checkout->create_order_line_items( $order, $cart );
		WC()->checkout->create_order_fee_lines( $order, $cart );
		WC()->checkout->create_order_shipping_lines( $order, WC()->session->get( 'chosen_shipping_methods' ), WC()->shipping()->get_packages() );
		WC()->checkout->create_order_tax_lines( $order, $cart );
		WC()->checkout->create_order_coupon_lines( $order, $cart );

		// Retrieve the cheapest shipping method.
		$shipping_methods = WC()->shipping->calculate_shipping( $cart->get_shipping_packages() )[0]['rates'];
		if ( ! empty( $shipping_methods ) ) {
			$shipping_method = reset( $shipping_methods );
			foreach ( $shipping_methods as $id => $shipping_rate ) {
				if ( $shipping_rate->get_cost() < $shipping_method->get_cost() ) {
					$shipping_method = $shipping_rate;
				}
			}
			if ( ! empty( $shipping_method ) ) {
				$order->add_shipping( $shipping_method );
			}
		}

		$order->calculate_totals( true );

		$order_id = $order->save();
		update_post_meta( $order_id, '_dintero_merchant_reference', $order_id );

		// We set this option to flag for resumable order (see check for this key above).
		WC()->session->set( 'order_awaiting_payment', $order_id );

		$session = Dintero()->api->create_session( $order->get_id() );
		if ( is_wp_error( $session ) ) {
			global $wp;

			// Redirect the customer back to the same page they clicked on the button in.
			wp_safe_redirect( add_query_arg( $wp->query_vars, home_url() ) );
			exit;
		}

		wp_redirect( esc_url( $session['url'] ) );
		exit;
	}
}
Dintero_Checkout_Ajax::init();
