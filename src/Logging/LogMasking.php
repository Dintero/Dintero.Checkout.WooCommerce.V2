<?php
/**
 * Masking of personal data and secrets in the plugin log.
 *
 * @package Dintero_Checkout/Logging
 */

namespace Krokedil\Dintero\Logging;

use KrokedilDinteroCheckoutDeps\Krokedil\WpApi\FieldMasker;
use KrokedilDinteroCheckoutDeps\Krokedil\WpApi\KeyMasker;

defined( 'ABSPATH' ) || exit;

/**
 * What the plugin masks out of a log entry before it is written to the log or the database.
 *
 * The rules describe the Dintero payloads we know about. The key names are the safety net
 * for every other shape that reaches a log entry, including the stack trace.
 */
class LogMasking {
	/**
	 * The address fields kept readable, since none of them identify a person on their own.
	 */
	const ADDRESS_KEPT = array( 'postal_code', 'postal_place', 'country' );

	/**
	 * Key names masked wherever they appear, on top of the package defaults.
	 *
	 * @var string[]
	 */
	private static $key_names = array(
		'first_name',
		'last_name',
		'email',
		'phone',
		'address_line',
		'co_address',
		'business_name',
		'organization_number',
		'customer_ip',
		// PSP operation links carry an access_token that lets whoever holds one submit the payment.
		'href',
		// Hosted payment pages and invoices, each reachable by anyone holding the URL.
		'session_url',
		'invoiceurl',
		'invoice_url',
	);

	/**
	 * Whether the key names have been handed to the package.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Widen the package key name masking with the names Dintero uses.
	 *
	 * @return void
	 */
	public static function register() {
		if ( self::$registered ) {
			return;
		}

		KeyMasker::add_keys( self::$key_names );
		self::$registered = true;
	}

	/**
	 * The rules for a log entry. They match at any depth, so the request and response bodies share them.
	 *
	 * @return array
	 */
	public static function fields() {
		return array(
			'billing_address'  => array( 'keep' => self::ADDRESS_KEPT ),
			'shipping_address' => array( 'keep' => self::ADDRESS_KEPT ),
			'customer'         => array( 'keep' => array() ),
		);
	}

	/**
	 * Mask a formatted log entry, or a plain log message.
	 *
	 * @param array|string $data The log entry.
	 * @return array|string The masked entry, or the failure marker in place of it.
	 */
	public static function mask( $data ) {
		// A failure here costs the entry, it never lets an unmasked one through.
		try {
			self::register();

			if ( is_array( $data ) ) {
				$data = FieldMasker::mask( $data, self::fields() );
			}

			return KeyMasker::mask( $data );
		} catch ( \Throwable $e ) {
			return array( 'error' => KeyMasker::FAILED );
		}
	}
}
