<?php
/**
 * Backward-compatible loader.
 *
 * @deprecated 1.11.0 Use includes/repositories/bookings.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/includes/repositories/bookings.php';
