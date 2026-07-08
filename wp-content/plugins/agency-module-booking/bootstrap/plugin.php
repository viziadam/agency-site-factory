<?php
/**
 * Booking module bootstrap.
 *
 * This file is the single loader for the module. Keep public shortcode names,
 * REST route paths and admin-post actions in the included files backward-compatible.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENCY_BOOKING_VERSION', '1.12.0' );
define( 'AGENCY_BOOKING_DB_VERSION', '1.1.0' );
define( 'AGENCY_BOOKING_FILE', dirname( __DIR__ ) . '/agency-module-booking.php' );
define( 'AGENCY_BOOKING_PATH', dirname( __DIR__ ) . '/' );

$agency_booking_files = array(
	'includes/repositories/bookings.php',
	'includes/frontend/booking-shortcodes.php',
	'includes/admin/bookings-admin.php',
	'includes/frontend/client-portal.php',
	'includes/services/component-registry.php',
);

foreach ( $agency_booking_files as $agency_booking_file ) {
	require_once AGENCY_BOOKING_PATH . $agency_booking_file;
}

register_activation_hook( AGENCY_BOOKING_FILE, 'agency_booking_activate' );
