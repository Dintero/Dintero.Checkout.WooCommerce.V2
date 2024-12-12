<?php //phpcs:disable
function customize_php_scoper_config( array $config ): array {
	// Ignore the ABSPATH constant when scoping.
	$config['exclude-constants'][] = 'ABSPATH';
	$config['exclude-constants'][] = 'DINTERO_CHECKOUT_VERSION';
	$config['exclude-constants'][] = 'DINTERO_CHECKOUT_URL';
	$config['exclude-constants'][] = 'DINTERO_CHECKOUT_PATH';
	$config['exclude-constants'][] = 'DINTERO_CHECKOUT_MAIN_FILE';
	$config['exclude-classes'][]   = 'WooCommerce';
	$config['exclude-classes'][]   = 'WC_Product';

	$functions = array(
		'Dintero',
		'dintero_get_the_ID',
		'dintero_is_hpos_enabled',
		'dintero_checkout_wc_show_another_gateway_button',
		'dintero_unset_sessions',
		'dintero_print_error_message',
		'dintero_sanitize_phone_number',
		'dintero_update_wc_shipping',
		'dintero_confirm_order',
		'dintero_get_brand_image_url',
		'dintero_keyword_backlinks',
		'dintero_alt_backlinks',
		'dintero_get_order_id_by_merchant_reference',
		'dintero_retrieve_error_message',
		'dintero_get_payment_method_name',
		'dwc_is_express',
		'dwc_is_embedded',
		'dwc_is_redirect',
		'dwc_is_popout',
	);

	$config['exclude-functions']    = array_merge( $config['exclude-functions'] ?? array(), $functions );
	$config['exclude-namespaces'][] = 'Automattic';

	return $config;
}
