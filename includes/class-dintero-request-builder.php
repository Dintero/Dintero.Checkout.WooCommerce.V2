<?php
/**
 * Dintero Request Builder Class Doc Comment
 *
 * @category Dintero_Request_Builder
 * @package  Dintero
 * @author   Dintero
 */

/**
 * Dintero Request Builder
 */
class Dintero_Request_Builder {

	/**
	 * Singletone
	 *
	 * @var null|Dintero_HP_Request_Builder
	 */
	protected static $instance = null;

	/**
	 * Dintero_HP_Request_Builder constructor.
	 */
	private function __construct() {
	}

	/**
	 * Preventing from cloning object
	 */
	private function __clone() {
	}

	/**
	 * Instantiating request builder
	 *
	 * @return Dintero_HP_Request_Builder
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Building request
	 *
	 * @param Dintero_Request $request request object.
	 * @return mixed|void
	 */
	public function build( Dintero_Request $request ) {
		$args = array(
			'headers' => $request->get_headers(),
			'body'    => $request->get_body(),
		);
		return (array) apply_filters( 'dhp_request_build_before', $args );
	}
}
