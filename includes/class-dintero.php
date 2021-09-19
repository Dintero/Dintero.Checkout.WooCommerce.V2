<?php
/**
 * Core Dintero WooCommerce Extension
 *
 * @class   Dintero
 * @package Dintero/Classes
 */

/**
 * Dinteor app class
 */
final class Dintero {

	/**
	 * Version.
	 *
	 * @var string
	 */
	private $version = '2.0.0';

	/**
	 * The single instance of the class.
	 *
	 * @var $instance
	 */
	protected static $instance = null;

	/**
	 * Application id
	 *
	 * @var string
	 */
	protected $app_id = 'dintero';

	/**
	 * Plugin directory
	 *
	 * @var string $plugin_dir
	 */
	protected $plugin_dir;

	/**
	 * Base name
	 *
	 * @var string $basename
	 */
	protected $basename;

	/**
	 * Config class
	 *
	 * @var Dintero_Config $config
	 */
	private $config;

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		wc_doing_it_wrong(
			__FUNCTION__,
			__( 'Cloning is forbidden.', 'woocommerce' ),
			'2.1'
		);
	}

	/**
	 * Wakeup
	 */
	public function __wakeup() {
		wc_doing_it_wrong(
			__FUNCTION__,
			__( 'Unserializing instances of this class is forbidden.', 'woocommerce' ),
			'2.1'
		);
	}

	/**
	 * Initializing class
	 */
	private function __construct() {
		add_action( 'woocommerce_init', array( $this, 'init_hooks' ) );
		$this->init_assets();
	}

	/**
	 * Retrieving instance of the class
	 *
	 * @return Dintero|null
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Running logic
	 *
	 * @param array $config configurations.
	 */
	public function run( $config = array() ) {
		$this->plugin_dir = isset( $config['plugin_dir'] ) ? $config['plugin_dir'] : null;
		$this->basename   = isset( $config['base_name'] ) ? $config['base_name'] : null;
	}

	/**
	 * Retrieving app id
	 *
	 * @return string
	 */
	public function get_app_id() {
		return $this->app_id;
	}

	/**
	 * Retrieving current version
	 *
	 * @return string
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Retrieving path inside plugins folder
	 *
	 * @param array|string $relative_path relative file path.
	 * @return string
	 */
	public function get_file_path( $relative_path ) {
		if ( is_array( $relative_path ) ) {
			$relative_path = implode( DIRECTORY_SEPARATOR, $relative_path );
		}
		return $this->get_plugin_dir() . DIRECTORY_SEPARATOR . $relative_path;
	}

	/**
	 * Retrieving plugin directory
	 *
	 * @return mixed
	 */
	public function get_plugin_dir() {
		return $this->plugin_dir;
	}

	/**
	 * Retrieving plugin url
	 *
	 * @param string $path path.
	 * @return string
	 */
	public function get_url( $path ) {
		return plugins_url( $path, $this->basename );
	}

	/**
	 * Registering hooks
	 */
	public function init_hooks() {
		add_action( 'dintero_payment_gateway_init_after', array( $this, 'init_config' ) );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_payment_method' ) );

		add_action( 'woocommerce_cancelled_order', array( $this, 'cancel_order' ) );
		add_action( 'woocommerce_order_status_changed', array( new Dintero_Status_Processor(), 'process_status_change' ), 10, 3 );
		add_filter( 'woocommerce_thankyou_order_id', array( new Dintero_Success_Processor(), 'execute' ) );
	}

	/**
	 * Retrieving payment gateway
	 *
	 * @return Dintero_Payment_Gateway|null
	 */
	public function get_payment_gateway() {
		foreach ( WC()->payment_gateways() as $payment_gateway ) {
			if ( $payment_gateway instanceof Dintero_Payment_Gateway ) {
				return $payment_gateway;
			}
		}
		return null;
	}

	/**
	 * Initializing config
	 *
	 * @param WC_Payment_Gateway $payment_gateway payment gateway class name.
	 */
	public function init_config( $payment_gateway ) {
		// no need to re-initialize configuration.
		if ( $this->config ) {
			return;
		}

		if ( $payment_gateway instanceof Dintero_Payment_Gateway ) {
			$this->config           = new Dintero_Config( $payment_gateway->settings );
			$payment_gateway->title = $this->config()->get( 'title' );
		}
	}

	/**
	 * Retrieving configurations
	 *
	 * @return Dintero_Config
	 */
	public function config() {
		if ( ! $this->config ) {

			// @codingStandardsIgnoreStart
			_doing_it_wrong(
				__FUNCTION__,
				__( 'Config is not initialized yet.', 'dintero' ),
				$this->version
			);
			// @codingStandardsIgnoreEnd
		}

		return $this->config;
	}

	/**
	 * Initializing static resources (js|css)
	 */
	public function init_assets() {

	}

	/**
	 * Registering new payment method
	 *
	 * @param array $methods payment methods list.
	 * @return mixed
	 */
	public function register_payment_method( $methods ) {
		array_push( $methods, new Dintero_Payment_Gateway() );
		return $methods;
	}
}
