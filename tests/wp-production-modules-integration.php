<?php
/**
 * Run with: wp eval-file tests/wp-production-modules-integration.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$failures = array();
$checks   = array();

foreach ( array(
	'agency_core_email_send',
	'agency_core_encrypt_secret',
	'agency_core_decrypt_secret',
	'agency_core_audit_log',
	'agency_auth_send_verification',
	'agency_auth_is_verified',
	'agency_booking_form',
	'agency_booking_list_shortcode',
	'agency_legal_get_checkbox_text',
) as $function ) {
	$checks[ 'function_' . $function ] = function_exists( $function );
}

global $wpdb, $shortcode_tags;
$checks['activity_table'] = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'agency_activity_log' ) ) === $wpdb->prefix . 'agency_activity_log';
$checks['booking_table']  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->prefix . 'agency_bookings' ) ) === $wpdb->prefix . 'agency_bookings';
$checks['service_schema'] = '1.0.0' === get_option( 'agency_module_services_db_version' );
$checks['booking_schema'] = '1.1.0' === get_option( 'agency_module_booking_db_version' );
$checks['customer_role']  = null !== get_role( 'agency_customer' );
$checks['auth_shortcode'] = isset( $shortcode_tags['agency_auth_login'], $shortcode_tags['agency_auth_register'], $shortcode_tags['agency_auth_account'] );
$checks['booking_shortcode'] = isset( $shortcode_tags['agency_booking_form'], $shortcode_tags['agency_booking_customer_list'] );
$registry = agency_core_get_component_registry();
$checks['booking_section'] = isset( $registry['booking']['variants']['form'] );
$secret = 'integration-secret-' . wp_generate_password( 12, false );
$checks['secret_roundtrip'] = $secret === agency_core_decrypt_secret( agency_core_encrypt_secret( $secret ) );
$checks['auth_markup'] = str_contains( do_shortcode( '[agency_auth]' ), 'agency-auth' );
$checks['booking_markup'] = str_contains( do_shortcode( '[agency_booking]' ), 'agency-booking' );
$checks['legal_consent'] = '' !== agency_legal_get_checkbox_text( 'booking' );
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ids' ) );
if ( $admins ) {
	wp_set_current_user( $admins[0] );
	foreach ( array( 'email_admin' => 'agency_core_render_email_settings', 'auth_admin' => 'agency_auth_admin_page', 'booking_admin' => 'agency_booking_admin_page', 'booking_settings_admin' => 'agency_booking_settings_page', 'legal_admin' => 'agency_legal_admin_page' ) as $key => $callback ) {
		ob_start();
		call_user_func( $callback );
		$checks[ $key ] = strlen( (string) ob_get_clean() ) > 100;
	}
}

$manifest = agency_core_get_project_manifest();
$checks['manifest_framework'] = '1.12.0' === ( $manifest['framework_version'] ?? '' );
$checks['manifest_versions'] = '1.12.0' === ( $manifest['module_versions']['auth'] ?? '' )
	&& '1.12.0' === ( $manifest['module_versions']['booking'] ?? '' )
	&& '1.12.0' === ( $manifest['module_versions']['legal'] ?? '' );
$checks['manifest_booking_migration'] = '1.1.0' === ( $manifest['module_migrations']['booking'] ?? '' );

foreach ( $checks as $name => $passed ) {
	if ( ! $passed ) {
		$failures[] = $name;
	}
}
echo wp_json_encode( array( 'success' => ! $failures, 'checks' => $checks, 'failures' => $failures ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL;
if ( $failures ) {
	exit( 1 );
}
