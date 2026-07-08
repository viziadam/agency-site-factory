<?php
/**
 * Backward-compatible loader.
 *
 * @deprecated 1.11.0 Use includes/frontend/booking-shortcodes.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/includes/frontend/booking-shortcodes.php';
