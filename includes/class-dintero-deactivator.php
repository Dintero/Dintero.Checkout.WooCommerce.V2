<?php
/**
 * Fired during plugin deactivation
 *
 * @package    dintero-checkout-v2
 * @subpackage dintero-checkout-v2/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's
 * deactivation.
 *
 * @package    dintero
 * @subpackage dintero/includes
 */
class Dintero_Deactivator {

	/**
	 * Deactivate plugin.
	 */
	public static function deactivate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		// Unregister plugin settings on deactivation.
		unregister_setting(
			'dintero_checkout',
			'dintero_checkout_option'
		);
	}
}
