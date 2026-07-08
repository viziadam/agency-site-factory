<?php
/**
 * Run with: wp eval-file tests/wp-module-setup-integration.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$checks = array();
$result = agency_core_apply_module_setup( 'booking', array( 'repair' => true, 'repair_display' => true ) );
$checks['booking_setup'] = ! is_wp_error( $result );
$registry = agency_core_get_module_registry();
$checks['dependencies_active'] = ! empty( $registry['booking']['active'] ) && ! empty( $registry['auth']['active'] ) && ! empty( $registry['legal']['active'] );

$required = array(
	'bejelentkezes' => '[agency_auth_login]',
	'regisztracio' => '[agency_auth_register]',
	'fiokom' => '[agency_auth_account]',
	'idopontfoglalas' => '[agency_booking_form]',
	'foglalasaim' => '[agency_booking_customer_list]',
	'adatkezelesi-tajekoztato' => '',
	'cookie-tajekoztato' => '',
);
foreach ( $required as $slug => $shortcode ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	$checks[ 'page_' . $slug ] = $page && agency_core_page_has_required_content( $page->post_content, $shortcode );
}

$booking = get_page_by_path( 'idopontfoglalas', OBJECT, 'page' );
$before  = $booking ? $booking->post_content : '';
agency_core_apply_module_setup( 'booking' );
$after = $booking ? get_post_field( 'post_content', $booking->ID ) : '';
$checks['idempotent_non_overwrite'] = $before === $after;

$components = agency_core_get_component_registry();
$checks['component_auth'] = isset( $components['auth']['variants']['login'], $components['auth']['variants']['register'], $components['auth']['variants']['account'] );
$checks['component_booking'] = isset( $components['booking']['variants']['form'], $components['booking']['variants']['customer-list'] );
$checks['component_legal'] = isset( $components['legal']['variants']['links'] );
$checks['shortcode_booking'] = str_contains( do_shortcode( '[agency_booking_form]' ), 'agency-booking' );
$checks['shortcode_login'] = str_contains( do_shortcode( '[agency_auth_login]' ), 'agency-auth' );
$checks['booking_url'] = '/idopontfoglalas/' === ( get_option( 'agency_core_settings', array() )['booking_url'] ?? '' );
$checks['email_health_warning'] = isset( agency_core_get_email_health()['production_ready'] );
$checks['homepage_booking_display'] = agency_core_booking_display_present();

$failed = array_keys( array_filter( $checks, static fn( $passed ) => ! $passed ) );
if ( $failed ) {
	fwrite( STDERR, 'Module setup integration failed: ' . implode( ', ', $failed ) . PHP_EOL );
	exit( 1 );
}
echo 'Module setup integration passed: ' . implode( ', ', array_keys( $checks ) ) . PHP_EOL;
