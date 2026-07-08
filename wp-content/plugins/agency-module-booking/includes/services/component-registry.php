<?php
/**
 * Booking assets, Agency sections and site integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_booking_enqueue_assets() {
	wp_enqueue_style( 'agency-module-booking', plugins_url( 'assets/css/booking.css', AGENCY_BOOKING_FILE ), array(), AGENCY_BOOKING_VERSION );
	wp_enqueue_script( 'agency-module-booking', plugins_url( 'assets/js/booking.js', AGENCY_BOOKING_FILE ), array(), AGENCY_BOOKING_VERSION, true );
	wp_localize_script(
		'agency-module-booking',
		'agencyBookingI18n',
		array(
			'choose'  => __( 'Choose a service and date.', 'agency-module-booking' ),
			'loading' => __( 'Loading available times…', 'agency-module-booking' ),
			'empty'   => __( 'No bookable times are available on this date.', 'agency-module-booking' ),
			'error'   => __( 'Available times could not be loaded. Please try again.', 'agency-module-booking' ),
			'select'  => __( 'Choose an available time.', 'agency-module-booking' ),
			'sending' => __( 'Sending booking…', 'agency-module-booking' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'agency_booking_enqueue_assets' );

function agency_booking_admin_assets( $hook ) {
	if ( str_contains( $hook, 'agency-module-booking' ) ) {
		wp_enqueue_style( 'agency-module-booking-admin', plugins_url( 'assets/css/admin.css', AGENCY_BOOKING_FILE ), array(), AGENCY_BOOKING_VERSION );
	}
}
add_action( 'admin_enqueue_scripts', 'agency_booking_admin_assets' );

function agency_booking_registry( $registry ) {
	$registry['booking'] = array(
		'label' => __( 'Booking', 'agency-module-booking' ), 'editor_label' => __( 'Booking', 'agency-module-booking' ), 'default_variant' => 'form', 'version' => 2,
		'fields' => array( 'title' => array( 'label' => __( 'Title', 'agency-module-booking' ), 'type' => 'text' ) ), 'default_data' => array(),
		'variants' => array(
			'form'          => array( 'label' => __( 'Booking calendar and form', 'agency-module-booking' ), 'template' => 'agency-module/booking-form' ),
			'customer-list' => array( 'label' => __( 'Customer booking list', 'agency-module-booking' ), 'template' => 'agency-module/booking-customer-list' ),
		),
	);
	return $registry;
}
add_filter( 'agency_theme_component_registry', 'agency_booking_registry' );
add_filter( 'agency_core_component_registry', 'agency_booking_registry' );
add_filter(
	'agency_theme_render_module_component',
	static function ( $handled, $component, $variant ) {
		if ( 'booking' !== $component ) {
			return $handled;
		}
		echo do_shortcode( 'customer-list' === $variant ? '[agency_booking_customer_list]' : '[agency_booking_form]' );
		return true;
	},
	10,
	3
);
