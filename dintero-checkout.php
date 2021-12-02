<?php
/**
Plugin Name: Dintero Checkout V2
Description: Dintero Checkout - Express Checkout
Version:     2.0.0
Author:      Dintero
Author URI:  mailto:integration@dintero.com
Text Domain: dintero-checkout-v2
Domain Path: /languages
 *
 * @package /dintero-checkout-v2
 */

defined( 'ABSPATH' ) || exit;

define( 'DINTERO_CHECKOUT_VERSION', '2.0.0' );


if ( ! defined( 'DINTERO_CHECKOUT_PLUGIN_FILE' ) ) {
	define( 'DINTERO_CHECKOUT_PLUGIN_FILE', __FILE__ );
}

spl_autoload_register( 'dintero_autoloader' );

/**
 * Dintero class autoloader
 *
 * @param string $class_name class name.
 */
function dintero_autoloader( $class_name ) {
	if ( strpos( $class_name, 'Dintero' ) !== false ) {
		$class_dir  = realpath( plugin_dir_path( __FILE__ ) )
			. DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'class-';
		$class_file = str_replace( array( '_' ), '-', strtolower( $class_name ) ) . '.php';
		require_once $class_dir . $class_file;
	}
}

register_activation_hook( __FILE__, array( 'Dintero_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Dintero_Deactivator', 'deactivate' ) );

Dintero::instance()->run(
	array(
		'plugin_dir' => plugin_dir_path( __FILE__ ),
		'base_name'  => plugin_basename( __FILE__ ),
	)
);

// @codingStandardsIgnoreStart
/**
 * Global function to retrieve dintero checkout object
 *
 * @return Dintero|null
 */
function DINTERO_WC() {
	return Dintero::instance();
}

// Global for backwards compatibility.
$GLOBALS['woocommerce-dintero-checkout-v2'] = DINTERO_WC();

// @codingStandardsIgnoreEnd

