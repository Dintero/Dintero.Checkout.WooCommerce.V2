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
	 * Keys the key name masking catches by substring but that hold nothing sensitive, restored
	 * after masking. '*' applies anywhere, any other entry only directly below that parent key.
	 *
	 * @var array<string, string[]>
	 */
	private static $kept_keys = array(
		'*'               => array( 'token_type', 'token_types' ),
		// A pickup point is a store, not the customer.
		'pick_up_address' => array( 'address_line', 'business_name' ),
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

			if ( ! is_array( $data ) ) {
				return KeyMasker::mask( $data );
			}

			$masked = KeyMasker::mask( FieldMasker::mask( $data, self::fields() ) );
			return self::restore_kept( $masked, $data );
		} catch ( \Throwable $e ) {
			return array( 'error' => KeyMasker::FAILED );
		}
	}

	/**
	 * Put back the values of the kept keys, walking the masked and the original entry side by side.
	 *
	 * @param mixed  $masked   The masked node.
	 * @param mixed  $original The same node before masking.
	 * @param string $parent   The key the node sits under, lowercased.
	 * @return mixed
	 */
	private static function restore_kept( $masked, $original, $parent = '' ) {
		if ( ! is_array( $masked ) || ! is_array( $original ) ) {
			return $masked;
		}

		foreach ( $masked as $key => $value ) {
			if ( ! array_key_exists( $key, $original ) ) {
				continue;
			}

			// A list entry has no name of its own, so it sits under the list's parent.
			$name = is_int( $key ) ? $parent : strtolower( (string) $key );

			// The value shape checks still apply, so a kept key holding a token stays masked.
			if ( ! is_int( $key ) && self::is_kept( $name, $parent ) && self::is_plain( $original[ $key ] ) ) {
				$masked[ $key ] = KeyMasker::mask( $original[ $key ] );
				continue;
			}

			$masked[ $key ] = self::restore_kept( $value, $original[ $key ], $name );
		}

		return $masked;
	}

	/**
	 * Whether a key is one of the kept keys where it sits.
	 *
	 * @param string $name   The key, lowercased.
	 * @param string $parent The parent key, lowercased.
	 * @return bool
	 */
	private static function is_kept( $name, $parent ) {
		return in_array( $name, self::$kept_keys['*'], true ) || in_array( $name, self::$kept_keys[ $parent ] ?? array(), true );
	}

	/**
	 * Whether a value is a scalar or a list of scalars, the only shapes a kept key is restored with.
	 *
	 * @param mixed $value The original value.
	 * @return bool
	 */
	private static function is_plain( $value ) {
		if ( is_array( $value ) ) {
			return count( array_filter( $value, 'is_scalar' ) ) === count( $value );
		}

		return is_scalar( $value ) || null === $value;
	}
}
