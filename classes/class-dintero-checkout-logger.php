<?php
/**
 * Logger class file.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class.
 */
class Dintero_Logger {

	/**
	 * The message to log.
	 *
	 * @var WC_Logger $log
	 */
	public static $log;

	/**
	 * Logs a single event.
	 *
	 * @param string $data The data to log.
	 * @return void
	 */
	public static function log( $data ) {
		$settings = get_option( 'woocommerce_dintero_checkout_settings', array() );
		if ( 'yes' === $settings['logging'] ) {
			$message = self::format_data( $data );
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}

			self::$log->add( 'dintero-checkout-for-woocommerce', wp_json_encode( $message ) );
		}
	}

	/**
	 * Formats the log data to prevent JSON error.
	 *
	 * @param string $data A possibly JSON encoded response.
	 * @return string The same data or the same data with JSON decoded.
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['body'] ) ) {
			$body                    = json_decode( $data['request']['body'], true );
			$data['request']['body'] = $body;
		}

		return $data;
	}

	/**
	 * Reformat the data for logging purpose.
	 *
	 * @param string $dintero_id The Dintero order id.
	 * @param string $method The HTTP method.
	 * @param string $title The name of the this log entry.
	 * @param string $request_args The data sent in the API request.
	 * @param string $response The data received from the API request.
	 * @param string $code The HTTP response code.
	 * @param string $request_url The request URL.
	 * @return array A formatted associative array.
	 */
	public static function format( $dintero_id, $method, $title, $request_args, $response, $code, $request_url ) {
		return array(
			'id'             => $dintero_id,
			'type'           => $method,
			'title'          => $title,
			'request'        => $request_args,
			'request_url'    => $request_url,
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => current_time( ' Y-m-d H:i:s' ),
			'plugin_version' => DINTERO_CHECKOUT_VERSION,
			'php_version'    => phpversion(),
			'wc_version'     => WC()->version,
			'wp_version'     => get_bloginfo( 'version' ),
			'stack'          => self::stacktrace(),
		);
	}

	/**
	 * Get the stacktrace.
	 *
	 * @return array
	 */
	public static function stacktrace() {
		$debug_data = debug_backtrace(); // phpcs:ignore 
		$stack      = array();
		foreach ( $debug_data as $data ) {
			$extra_data = '';
			if ( ! in_array( $data['function'], array( 'get_stack', 'format_log' ), true ) ) {
				if ( in_array( $data['function'], array( 'do_action', 'apply_filters' ), true ) ) {
					if ( isset( $data['object'] ) ) {
						$priority   = $data['object']->current_priority();
						$name       = key( $data['object']->current() );
						$extra_data = $name . ' : ' . $priority;
					}
				}
			}
			$stack[] = $data['function'] . $extra_data;
		}
		return $stack;
	}
}
