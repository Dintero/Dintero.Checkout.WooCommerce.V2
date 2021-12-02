<?php
/**
 * Fired during plugin activation
 *
 * @package    dintero
 * @subpackage dintero/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @package    dintero
 * @subpackage dintero/includes
 */
class Dintero_Activator {

	/**
	 * Activate Plugin.
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$default = array(
			// options should be here.
		);
		update_option( 'dintero_checkout_option', $default );
	}
}
