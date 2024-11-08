<?php
/**
 * Class for handling logging to the WC log.
 *
 * @package Dintero_Checkout/Classes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class.
 */
class Dintero_Checkout_Logger {
	/**
	 * The message to log.
	 *
	 * @var WC_Logger $log
	 */
	public static $log;

	/**
	 * Logs a single event.
	 *
	 * @static
	 * @param array|string $data The data to log.
	 * @return void
	 */
	public static function log( $data ) {
		$settings = get_option( 'woocommerce_dintero_checkout_settings', array() );

		if ( 'yes' === $settings['logging'] ) {
			$message = self::format_data( $data );
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->add( 'dintero-checkout-for-woocommerce', wp_json_encode( $message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
		}

		if ( isset( $data['response']['code'] ) && ( $data['response']['code'] < 200 || $data['response']['code'] > 299 ) ) {
			self::log_to_db( $data );
		}
	}

	/**
	 * Formats the log data to prevent json error.
	 *
	 * @param array|string $data An array with request/response data or a string.
	 * @return array|string
	 */
	public static function format_data( $data ) {
		if ( isset( $data['request']['headers']['authorization'] ) ) {
			$data['request']['headers']['authorization'] = '[redacted]';
		}

		if ( isset( $data['request']['body'] ) ) {
			$request_body            = json_decode( $data['request']['body'], true );
			$data['request']['body'] = ( ! empty( $request_body ) ) ? $request_body : $data['request']['body'];
		}

		if ( isset( $data['response']['body']['body'] ) ) {
			$response_body                    = json_decode( $data['response']['body']['body'], true );
			$data['response']['body']['body'] = ( ! empty( $response_body ) ) ? $response_body : $data['response']['body']['body'];
		}
		return $data;
	}

	/**
	 * Reformat the data for logging purpose.
	 *
	 * @static
	 * @param string $dintero_id The Dintero order id.
	 * @param string $method The HTTP method.
	 * @param string $title The name of the this log entry.
	 * @param string $request_args The data sent in the API request.
	 * @param string $response The data received from the API request.
	 * @param string $code The HTTP response code.
	 * @param string $request_url The request URL.
	 * @param string $checkout_flow The checkout flow.
	 * @return array A formatted associative array.
	 */
	public static function format_log( $dintero_id, $method, $title, $request_args, $response, $code, $request_url = null ) {
		$settings = get_option( 'woocommerce_dintero_checkout_settings', array() );

		return array(
			'id'             => $dintero_id,
			'type'           => $method,
			'title'          => $title,
			'request_url'    => $request_url,
			'request'        => $request_args,
			'checkout_flow'  => $settings['checkout_flow'] ?? 'express_popout',
			'response'       => array(
				'body' => $response,
				'code' => $code,
			),
			'timestamp'      => current_time( ' Y-m-d H:i:s' ),
			'plugin_version' => DINTERO_CHECKOUT_VERSION,
			'php_version'    => phpversion(),
			'wc_version'     => WC()->version,
			'wp_version'     => get_bloginfo( 'version' ),
			'user_agent'     => wc_get_user_agent(),
			'stack'          => self::get_stack(),
		);
	}

	/**
	 * Gets the stack for the request.
	 *
	 * @return array
	 */
	public static function get_stack() {
		$debug_data = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions -- Data is not used for display.
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

	/**
	 * Logs an event in the WP DB.
	 *
	 * @param array $data The data to be logged.
	 */
	public static function log_to_db( $data ) {
		$logs = get_option( 'krokedil_debuglog_dintero_checkout', array() );

		if ( ! empty( $logs ) ) {
			$logs = json_decode( $logs );
		}

		$logs   = array_slice( $logs, -14 );
		$logs[] = $data;
		$logs   = wp_json_encode( $logs );
		update_option( 'krokedil_debuglog_dintero_checkout', $logs );
	}
}
