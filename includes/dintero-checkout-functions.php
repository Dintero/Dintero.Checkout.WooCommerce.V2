<?php

/**
 * Utility functions.
 *
 * @package Dintero_Checkout/Includes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize phone number.
 * Allow only '+' (if at the start), and numbers.
 *
 * @param string $phone Phone number.
 * @return string
 */
function dintero_sanitize_phone_number( $phone ) {
	return preg_replace( '/(?!^)[+]?[^\d]/', '', $phone );
}
