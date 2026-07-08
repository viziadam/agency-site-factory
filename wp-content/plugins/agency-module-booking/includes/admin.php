<?php
/**
 * Backward-compatible loader.
 *
 * @deprecated 1.11.0 Use includes/admin/bookings-admin.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/includes/admin/bookings-admin.php';
