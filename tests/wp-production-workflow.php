<?php
/**
 * Transactional Auth + Booking workflow test. All test records are rolled back.
 */

if ( ! defined( 'ABSPATH' ) ) { exit( 1 ); }

global $wpdb;
$failures = array();
$email    = 'agency-test-' . wp_generate_password( 10, false ) . '@example.test';
$wpdb->query( 'START TRANSACTION' );
try {
	add_filter( 'pre_option_agency_core_email_settings', static fn() => array( 'provider' => 'wp_mail', 'sender_name' => 'Agency Test', 'sender_email' => 'sender@example.test', 'reply_to' => '', 'api_key' => '' ) );
	add_filter( 'pre_wp_mail', '__return_true' );
	$user_id = wp_insert_user( array( 'user_login' => $email, 'user_email' => $email, 'user_pass' => wp_generate_password( 20 ), 'role' => 'agency_customer', 'display_name' => 'Agency Test Customer' ) );
	if ( is_wp_error( $user_id ) ) { throw new RuntimeException( $user_id->get_error_message() ); }
	update_user_meta( $user_id, '_agency_email_verified', '0' );
	if ( agency_auth_is_verified( $user_id ) ) { $failures[] = 'unverified_user'; }
	$verification_sent = agency_auth_send_verification( $user_id );
	if ( is_wp_error( $verification_sent ) || ! get_user_meta( $user_id, '_agency_verify_hash', true ) || absint( get_user_meta( $user_id, '_agency_verify_expires', true ) ) <= time() ) { $failures[] = 'verification_token'; }
	update_user_meta( $user_id, '_agency_email_verified', '1' );
	if ( ! agency_auth_is_verified( $user_id ) ) { $failures[] = 'verified_user'; }
	$nonce = wp_create_nonce( 'agency_booking_create' );
	if ( ! wp_verify_nonce( $nonce, 'agency_booking_create' ) ) { $failures[] = 'nonce'; }

	$now = current_time( 'mysql', true );
	$ok  = $wpdb->insert(
		agency_booking_table(),
		array( 'user_id' => $user_id, 'service_id' => 0, 'start_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 'end_at' => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS + 1800 ), 'name' => 'Agency Test Customer', 'email' => $email, 'phone' => '+360000000', 'customer_note' => 'transactional test', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now )
	);
	if ( ! $ok ) { $failures[] = 'booking_insert'; }
	$booking_id = $wpdb->insert_id;
	$status     = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . agency_booking_table() . ' WHERE id=%d', $booking_id ) );
	if ( 'pending' !== $status ) { $failures[] = 'pending_status'; }
	$wpdb->update( agency_booking_table(), array( 'status' => 'approved', 'updated_at' => $now ), array( 'id' => $booking_id ) );
	$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM ' . agency_booking_table() . ' WHERE id=%d', $booking_id ) );
	if ( 'approved' !== $status ) { $failures[] = 'approved_status'; }
} catch ( Throwable $error ) {
	$failures[] = $error->getMessage();
}
remove_all_filters( 'pre_wp_mail' );
remove_all_filters( 'pre_option_agency_core_email_settings' );
$wpdb->query( 'ROLLBACK' );
$remaining_booking = isset( $booking_id ) ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . agency_booking_table() . ' WHERE id=%d', $booking_id ) ) : 0;
$remaining_user    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email=%s", $email ) );
if ( $remaining_booking || $remaining_user ) { $failures[] = 'rollback_cleanup'; }
echo wp_json_encode( array( 'success' => ! $failures, 'failures' => $failures, 'rolled_back' => ! $remaining_booking && ! $remaining_user ), JSON_PRETTY_PRINT ) . PHP_EOL;
if ( $failures ) { exit( 1 ); }
