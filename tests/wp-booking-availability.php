<?php
/**
 * Transactional booking availability and blocking workflow.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

global $wpdb;
$checks   = array();
$old      = get_option( 'agency_module_booking_settings', array() );
$settings = agency_booking_defaults();
$future   = new DateTimeImmutable( '+8 days', wp_timezone() );
$date     = $future->format( 'Y-m-d' );
$weekday  = (int) $future->format( 'w' );
$settings['active_weekdays'] = (string) $weekday;
$settings['day_start'] = '09:00';
$settings['day_end'] = '11:00';
$settings['slot_duration'] = 30;
$settings['closed_dates'] = '';
$settings['special_open_dates'] = '';
$settings['pending_blocks_slot'] = 1;
$wpdb->query( 'START TRANSACTION' );
try {
	update_option( 'agency_module_booking_settings', $settings, false );
	$service_id = wp_insert_post( array( 'post_type' => 'agency_service', 'post_status' => 'publish', 'post_title' => 'Availability Test Service' ) );
	$slots = agency_booking_get_available_slots( $date, $service_id, $settings );
	$checks['four_slots'] = 4 === count( $slots );
	$closed = $settings;
	$closed['closed_dates'] = $date;
	$checks['closed_date'] = array() === agency_booking_get_available_slots( $date, $service_id, $closed );
	$special = $closed;
	$special['special_open_dates'] = $date;
	$checks['special_open_override'] = 4 === count( agency_booking_get_available_slots( $date, $service_id, $special ) );
	$past_date = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS, wp_timezone() );
	$past_settings = $settings;
	$past_settings['active_weekdays'] = (string) (int) ( new DateTimeImmutable( $past_date, wp_timezone() ) )->format( 'w' );
	$checks['past_date'] = array() === agency_booking_get_available_slots( $past_date, $service_id, $past_settings );
	$now = current_time( 'mysql', true );
	$first = $slots[0]['utc'];
	$wpdb->insert( agency_booking_table(), array( 'user_id' => 0, 'service_id' => $service_id, 'start_at' => $first, 'end_at' => gmdate( 'Y-m-d H:i:s', strtotime( $first . ' UTC' ) + 1800 ), 'name' => 'Approved Test', 'email' => 'approved@example.test', 'phone' => '+360000001', 'customer_note' => '', 'admin_note' => '', 'status' => 'approved', 'verification_hash' => '', 'created_at' => $now, 'updated_at' => $now ) );
	$checks['approved_blocks'] = 3 === count( agency_booking_get_available_slots( $date, $service_id, $settings ) );
	$second = $slots[1]['utc'];
	$wpdb->insert( agency_booking_table(), array( 'user_id' => 0, 'service_id' => $service_id, 'start_at' => $second, 'end_at' => gmdate( 'Y-m-d H:i:s', strtotime( $second . ' UTC' ) + 1800 ), 'name' => 'Pending Test', 'email' => 'pending@example.test', 'phone' => '+360000002', 'customer_note' => '', 'admin_note' => '', 'status' => 'pending', 'verification_hash' => '', 'created_at' => $now, 'updated_at' => $now ) );
	$checks['pending_blocks_on'] = 2 === count( agency_booking_get_available_slots( $date, $service_id, $settings ) );
	$settings['pending_blocks_slot'] = 0;
	$checks['pending_blocks_off'] = 3 === count( agency_booking_get_available_slots( $date, $service_id, $settings ) );
	$checks['database_row'] = 2 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . agency_booking_table() . ' WHERE service_id=%d', $service_id ) );
} catch ( Throwable $error ) {
	$checks['exception_' . $error->getMessage()] = false;
}
$wpdb->query( 'ROLLBACK' );
update_option( 'agency_module_booking_settings', $old, false );
wp_cache_delete( 'agency_module_booking_settings', 'options' );
$failed = array_keys( array_filter( $checks, static fn( $passed ) => ! $passed ) );
echo wp_json_encode( array( 'success' => ! $failed, 'checks' => $checks, 'failures' => $failed ), JSON_PRETTY_PRINT ) . PHP_EOL;
if ( $failed ) {
	exit( 1 );
}
