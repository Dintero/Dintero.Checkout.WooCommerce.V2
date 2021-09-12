<?php
/**
Plugin Name: Dintero Checkout
Description: Dintero Checkout - Express Checkout
Version:     2021.08.19
Author:      Dintero
Author URI:  mailto:integration@dintero.com
Text Domain: dintero-hp
Domain Path: /languages
 *
 * @package /dintero-hp
 */

defined( 'ABSPATH' ) || exit;

define( 'DINTERO_HP_VERSION', '2021.09.03' );


if ( ! defined( 'DHP_PLUGIN_FILE' ) ) {
	define( 'DHP_PLUGIN_FILE', __FILE__ );
}

spl_autoload_register('dintero_autoloader');

/**
 * Dintero class autoloader
 *
 * @param $class_name
 */
function dintero_autoloader($class_name) {
    if (strpos($class_name, 'Dintero') !== false) {
        $class_dir = realpath(plugin_dir_path(__FILE__))
            . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR.'class-';
        $class_file = str_replace(array('_'), '-', strtolower($class_name)) . '.php';
        require_once($class_dir . $class_file);
    }
}

register_activation_hook( __FILE__, array('Dintero_Activator', 'activate') );
register_deactivation_hook( __FILE__, array('Dintero_Deactivator', 'deactivate') );

Dintero::instance()->run([
    'plugin_dir' => plugin_dir_path(__FILE__),
    'base_name' => plugin_basename(__FILE__),
]);

function WCDHP() {
    return Dintero::instance();
}

// Global for backwards compatibility.
$GLOBALS['woocommerce-dintero'] = WCDHP();

