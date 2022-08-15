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
		$select_another_method_text = __( 'Select another payment method', 'dintero-checkout-for-woocommerce' );

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

/**
 * Prints error message as notices.
 *
 * @param WP_Error $wp_error A WordPress error object.
 * @return void
 */
function dintero_print_error_message( $wp_error ) {
	foreach ( $wp_error->get_error_messages() as $error ) {
		wc_add_notice( ( is_array( $error ) ) ? $error['message'] : $error, 'error' );
	}
}

/**
 * Sanitize phone number.
 * Allow only '+' (if at the start), and numbers.
 *
 * @param string $phone Phone number.
 * @return string
 */
function dintero_sanitize_phone_number( $phone ) {
	return preg_replace( '/(?!^)[+]?[^\d]/', '', $phone );
}

/**
 * Sets the shipping method in WooCommerce from Dintero.
 *
 * @param array|bool $data The shipping data from Dintero. False if not set.
 * @return void
 */
function dintero_update_wc_shipping( $data ) {
	// Set cart definition.
	$merchant_reference = WC()->session->get( 'dintero_merchant_reference' );

	// If we don't have a Dintero merchant reference, return void.
	if ( empty( $merchant_reference ) ) {
		return;
	}

	// If the data is empty, return void.
	if ( empty( $data ) ) {
		return;
	}

	do_action( 'dintero_update_shipping_data', $data );

	set_transient( 'dintero_shipping_data_' . $merchant_reference, $data, HOUR_IN_SECONDS );
	$chosen_shipping_methods   = array();
	$chosen_shipping_methods[] = wc_clean( $data['id'] );
	WC()->session->set( 'chosen_shipping_methods', apply_filters( 'dintero_chosen_shipping_method', $chosen_shipping_methods ) );
}

/**
 * Confirms the Dintero Order.
 *
 * @param WC_Order $order The WooCommerce order
 * @param string   $transaction_id The Dintero transaction id.
 * @return void
 */
function dintero_confirm_order( $order, $transaction_id ) {
	/* Check if the order has already been processed. */
	if ( ! empty( $order->get_date_paid() ) ) {
		return;
	}

	$order_id = $order->get_id();

	// Save the environment mode for use in the meta box.
	update_post_meta( $order_id, '_wc_dintero_checkout_environment', 'yes' === get_option( 'woocommerce_dintero_checkout_settings' )['test_mode'] ? 'Test' : 'Production' );

	update_post_meta( $order_id, '_dintero_transaction_id', $transaction_id );
	$dintero_order         = Dintero()->api->get_order( $transaction_id );
	$require_authorization = ( ! is_wp_error( $dintero_order ) && 'ON_HOLD' === $dintero_order['status'] );
	if ( $require_authorization ) {
		// translators: %s the Dintero transaction ID.
		$order->update_status( 'manual-review', sprintf( __( 'The order was placed successfully, but requires further authorization by Dintero. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
		$order->save();
		update_post_meta( $order_id, Dintero()->order_management->status( 'on_hold' ), $transaction_id );
		update_post_meta( $order_id, '_transaction_id', $transaction_id );
		Dintero_Checkout_Logger::log( "REDIRECT: The WC order $order_id (transaction ID: $transaction_id) will require further authorization from Dintero." );
	} else {
		// translators: %s the Dintero transaction ID.
		$order->add_order_note( sprintf( __( 'Payment via Dintero Checkout. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );

		$default_status = get_option( 'woocommerce_dintero_checkout_settings' )['order_statuses'];
		if ( 'processing' !== $default_status ) {
			update_post_meta( $order_id, '_transaction_id', $transaction_id );
			$order->update_status( $default_status, __( 'The order was placed successfully.', 'dintero-checkout-for-woocommerce' ) );
		} else {
			$transaction_id = get_post_meta( $order->get_id(), '_dintero_transaction_id', true );
			delete_post_meta( $order->get_id(), Dintero()->order_management->status( 'on_hold' ) );

			$order->payment_complete( $transaction_id );
		}
	}

	// Set the merchant_reference_2 for the Dintero order.
	Dintero()->api->update_transaction( $transaction_id, $order->get_order_number() );

	// Save shipping id to the order.
	$shipping = $order->get_shipping_methods();
	if ( ! empty( $shipping ) ) {
		$shipping_option_id = $dintero_order['shipping_option']['id'] ?? reset( $shipping );
		update_post_meta( $order->get_id(), '_wc_dintero_shipping_id', $shipping_option_id );
	}
}

/**
 * The URL to the branding image.
 *
 * @param  string $icon_color A hexadecimal RGB value for the foreground color. Default is #cecece.
 * @return string URL
 */
function dintero_get_brand_image_url( $icon_color = 'cecece' ) {
	$settings = get_option( 'woocommerce_dintero_checkout_settings' );

	$variant  = 'colors';
	$color    = $icon_color;
	$width    = 600;
	$template = 'dintero_left_frame';
	$account  = ( ( 'yes' === $settings['test_mode'] ) ? 'T' : 'P' ) . $settings['account_id'];
	$profile  = $settings['profile_id'];

	if ( 'yes' !== $settings['branding_logo_color'] ) {
		$variant = 'mono';
		$color   = str_replace( '#', '', $settings['branding_logo_color_custom'] );
	}

	return "https://checkout.dintero.com/v1/branding/accounts/$account/profiles/$profile/variant/$variant/color/$color/width/$width/$template.svg";
}
