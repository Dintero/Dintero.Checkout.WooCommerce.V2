<?php //phpcs:ignore
/**
 * Class for retrieving the session profile information from Dintero.
 *
 * @package Dintero_Checkout/Classes/Requests/Get
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for retrieving session profile information.
 */
class Dintero_Checkout_Get_Session_Profile extends Dintero_Checkout_Request_Get {

	/**
	 * Class constructor.
	 *
	 * @param array $arguments The request arguments.
	 */
	public function __construct( $arguments ) {
		parent::__construct( $arguments );

		$this->log_title      = 'Get Admin session profile.';
		$this->request_filter = 'dintero_checkout_get_admin_session_profile_args';
	}

	/**
	 * Gets the request URL.
	 *
	 * @return string
	 */
	public function get_request_url() {
		if ( ! empty( $this->arguments['params'] ) ) {
			return add_query_arg( $this->arguments['params'], "{$this->get_api_url_base()}admin/session/profiles/{$this->arguments['profile_id']}" );
		} else {
			return "{$this->get_api_url_base()}admin/session/profiles/{$this->arguments['profile_id']}";
		}
	}
}
