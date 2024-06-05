<?php
/**
 * Utility functions.
 *
 * @package Dintero_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

/**
 * Equivalent to WP's get_the_ID() with HPOS support.
 *
 * @return int the order ID or false.
 */
//phpcs:ignore
function dintero_get_the_ID() {
	$hpos_enabled = dintero_is_hpos_enabled();
	$order_id     = $hpos_enabled ? filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT ) : get_the_ID();
	if ( empty( $order_id ) ) {
		return false;
	}

	return $order_id;
}

/**
 * Whether HPOS is enabled.
 *
 * @return bool
 */
function dintero_is_hpos_enabled() {
	// CustomOrdersTableController was introduced in WC 6.4.
	if ( class_exists( CustomOrdersTableController::class ) ) {
		return wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled();
	}

	return false;
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
 * Sometimes an error message cannot be printed (e.g., in a cronjob environment) where there is
 * no front end to display the error message, or otherwise irrelevant for a human. For that reason, we have to check if the print functions are undefined.
 *
 * @param WP_Error $wp_error A WordPress error object.
 * @return void
 */
function dintero_print_error_message( $wp_error ) {
	if ( is_ajax() ) {
		if ( function_exists( 'wc_add_notice' ) ) {
			$print = 'wc_add_notice';
		}
	} elseif ( function_exists( 'wc_print_notice' ) ) {
		$print = 'wc_print_notice';
	}

	if ( ! isset( $print ) ) {
		return;
	}

	foreach ( $wp_error->get_error_messages() as $error ) {
		$message = $error;
		if ( is_array( $error ) ) {
			$error   = array_filter(
				$error,
				function ( $e ) {
					return ! empty( $e );
				}
			);
			$message = implode( ' ', $error );
		}

		$print( $message, 'error' );
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
 * @param WC_Order $order The Woo order.
 * @param string   $transaction_id The Dintero transaction id.
 * @return void
 */
function dintero_confirm_order( $order, $transaction_id ) {
	$order_id = $order->get_id();
	$settings = get_option( 'woocommerce_dintero_checkout_settings' );

	// Save the environment mode for use in the meta box.
	$order->update_meta_data( '_wc_dintero_checkout_environment', 'yes' === $settings['test_mode'] ? 'Test' : 'Production' );
	$order->update_meta_data( '_dintero_transaction_id', $transaction_id );

	// Request the payment token in the response.
	$dintero_order = Dintero()->api->get_order( $transaction_id, array( 'includes' => 'card.payment_token' ) );

	// Save the payment token if available.
	$payment_token = wc_get_var( $dintero_order['card']['payment_token'] );
	if ( $payment_token ) {
		Dintero_Checkout_Subscription::save_payment_token( $order_id, $payment_token );
	}

	/* Remove duplicate words from the payment method type (e.g., swish.swish → Swish). Otherwise, prints as is (e.g., collector.invoice → Collector Invoice). */
	$payment_method = dintero_get_payment_method_name( wc_get_var( $dintero_order['payment_product_type'], $order->get_meta( '_dintero_payment_method' ) ) );
	$order->update_meta_data( '_dintero_payment_method', $payment_method );

	$require_authorization = ( ! is_wp_error( $dintero_order ) && 'ON_HOLD' === $dintero_order['status'] );
	if ( $require_authorization ) {
		$order->set_transaction_id( $transaction_id );
		$order->update_meta_data( Dintero()->order_management->status( 'on_hold' ), $transaction_id );

		$default_pending_authorization = $settings['order_status_pending_authorization'] ?? 'manual-review';

		// translators: %s the Dintero transaction ID.
		$order->update_status( $default_pending_authorization, sprintf( __( 'The order was placed successfully, but requires further authorization by Dintero. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
		Dintero_Checkout_Logger::log( "REDIRECT: The WC order $order_id (transaction ID: $transaction_id) will require further authorization from Dintero." );
	} else {
		// Check if the order has already been processed.
		if ( ! empty( $order->get_date_paid() ) ) {
			$order->save();
			return;
		}

		// Check if the order is currently locked due to manual review.
		if ( $order->get_meta( Dintero()->order_management->status( 'on_hold' ) ) ) {
			$order->add_order_note( __( 'The order has been authorized.', 'dintero-checkout-for-woocommerce' ) );
		} else {
			// translators: %s the Dintero transaction ID.
			$order->add_order_note( sprintf( __( 'The order was placed successfully via Dintero Checkout. Transaction ID: %s', 'dintero-checkout-for-woocommerce' ), $transaction_id ) );
		}

		$default_status_authorized = $settings['order_status_authorized'] ?? 'processing';
		if ( 'processing' !== $default_status_authorized ) {
			$order->set_transaction_id( $transaction_id );
			$order->set_status( $default_status_authorized );
			if ( ! $order->get_date_paid( 'edit' ) ) {
				$order->set_date_paid( time() );
			}
		} else {
			$order->payment_complete( $transaction_id );
		}

		$order->delete_meta_data( Dintero()->order_management->status( 'on_hold' ) );
	}

	$order->save();

	// Set the merchant_reference_2 for the Dintero order.
	Dintero()->api->update_transaction( $transaction_id, $order->get_order_number() );

	// Save shipping id to the order.
	$shipping = $order->get_shipping_methods();
	if ( ! empty( $shipping ) && empty( $order->get_meta( '_wc_dintero_shipping_id' ) ) ) {
		$shipping_option_id = $dintero_order['shipping_option']['id'] ?? reset( $shipping );
		$order->update_meta_data( '_wc_dintero_shipping_id', $shipping_option_id );
		$order->save();
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
	$color    = str_replace( '#', '', $icon_color );
	$width    = 600;
	$template = 'dintero_left_frame';
	$account  = ( ( 'yes' === $settings['test_mode'] ) ? 'T' : 'P' ) . $settings['account_id'];
	$profile  = $settings['profile_id'];

	if ( 'yes' !== $settings['branding_logo_color'] ) {
		$variant = 'mono';
		$color   = str_replace( '#', '', $settings['branding_logo_color_custom'] );
	}

	return "https://checkout.dintero.com/v1/branding/accounts/{$account}/profiles/{$profile}/variant/{$variant}/color/{$color}/width/{$width}/{$template}.svg";
}

/**
 * Generates the keyword-variant to be used for search engine optimization purposes on icons.
 *
 * @return string
 */
function dintero_keyword_backlinks() {
	$locale = get_locale();
	if ( 'sv' === substr( $locale, 0, 2 ) ) {
		$keywords = array(
			'Dintero logo - checkout med swish, visa, mastercard, walley och mobilepay',
			'Dintero logo - betalningslösning for woocommerce',
			'Dintero logo - betalningslösning med swish, walley, visa, mastercard och mobilepay',
			'Dintero logo - Split Payments för plattform och marknadsplats',
		);
	} elseif ( 'nb' === substr( $locale, 0, 2 ) ) {
		$keywords = array(
			'Dintero logo - en enkel betalingsløsning på nett',
			'Dintero logo - checkout med vipps, visa, mastercard, walley og mobilePay',
			'Dintero logo - checkout for woocommerce',
			'Dintero logo - Split Payments for plattform og markedsplass',
		);
	} else {
		/* For all other languages, default to the English variant. */
		$keywords = array(
			'Dintero logo - Dintero checkout delivers simple online payment solutions',
			'Dintero logo - Dintero payment methods for woocommerce',
			'Dintero logo - Simple payment solutions for woocommerce payment gateway',
			'Dintero logo - Split Payments for platforms and marketplaces',
		);
	}

	$index = get_transient( 'dintero_checkout_keyword_backlinks' );
	if ( empty( $index ) ) {
		$index = hexdec(
			crc32( parse_url( get_site_url(), PHP_URL_HOST ) )
		);
		set_transient( 'dintero_checkout_keyword_backlinks', $index );
	}

	return $keywords[ $index % count( $keywords ) ];
}

/**
 * Generates the 'alt' text for the image used for the purpose of backlinks.
 *
 * @return string
 */
function dintero_alt_backlinks() {
	switch ( substr( get_locale(), 0, 2 ) ) {
		case 'sv':
			return 'Dintero logo, klicka här för att visa Dinteros hemsida.';
		case 'nb':
			return 'Dintero logo, klikk for å vis Dinteros nettside.';
		default:
			return "Dintero logo, click to view Dintero's website.";
	}
}
/**
 * Get a order id from the merchant reference.
 *
 * @param string $merchant_reference The merchant reference from dintero.
 * @return int The WC order ID or 0 if no match was found.
 */
function dintero_get_order_id_by_merchant_reference( $merchant_reference ) {
	$key    = '_dintero_merchant_reference';
	$orders = wc_get_orders(
		array(
			'meta_key'   => $key,
			'meta_value' => $merchant_reference,
			'limit'      => 1,
			'orderby'    => 'date',
			'order'      => 'DESC',
		)
	);

	$order = reset( $orders );
	if ( empty( $order ) || $merchant_reference !== $order->get_meta( $key ) ) {
		return 0;
	}

	return $order->get_id() ?? 0;
}

/**
 * Retrieve the error message(s).
 *
 * @param WP_Error $error An instance of WP_Error.
 * @return string
 */
function dintero_retrieve_error_message( $error ) {
	$message = $error->get_error_message();
	if ( is_array( $message ) ) {
		$message = implode( ' ', $message );
	}
	return $message;
}

/**
 * Retrieve the payment method name from a payment product type string.
 *
 * Remove duplicate words from the payment method type (e.g., swish.swish → Swish).
 * Otherwise, prints as is (e.g., collector.invoice → Collector Invoice).
 *
 * @param string $payment_product_type The payment product type.
 * @return string
 */
function dintero_get_payment_method_name( $payment_product_type ) {
	if ( ! is_string( $payment_product_type ) ) {
		return $payment_product_type;
	}

	// Remove any dots (e.g., "collector.invoice" → "collector invoice").
	$payment_method = str_replace( '.', ' ', $payment_product_type );
	// Change to uppercase (e.g., "collector invoice" → "Collector Invoice").
	$payment_method = ucwords( $payment_method );
	// Convert into array (e.g., "Collector Invoice" → ["Collector", "Invoice"] ).
	$payment_method = explode( ' ', $payment_method );
	// Remove any duplicates ( e.g., ["Swish", "Swish"] → ["Swish"] ).
	$payment_method = array_unique( $payment_method );
	// Convert to string (e.g., "Collector Invoice" or "Swish").
	$payment_method = implode( $payment_method );

	return $payment_method;
}
