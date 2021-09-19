<?php
/**
 * Dintero Config Class Doc Comment
 *
 * @category Dintero_Config
 * @package  Dintero
 * @author   Dintero
 */

/**
 * Dintero Config
 */
class Dintero_Config extends WC_Settings_API {

	/**
	 * Options list
	 *
	 * @var array list of options.
	 */
	protected $options;

	/**
	 * Initializing configurations
	 *
	 * @param array $options list of available configurations.
	 */
	public function __construct( array $options ) {
		$this->options = (array) $options;
	}

	/**
	 * Retrieving config key
	 *
	 * @param string $key key name.
	 * @param null   $default default value.
	 * @return mixed|null
	 */
	public function get( $key, $default = null ) {
		return isset( $this->options[ $key ] ) ? $this->options[ $key ] : $default;
	}

	/**
	 * Checking if flag is set to yes or not
	 *
	 * @param string $key key name.
	 * @return bool
	 */
	public function is( $key ) {
		return $this->get( $key ) === 'yes';
	}
}
