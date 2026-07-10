<?php
/**
 * Hardened booking create handler with visible diagnostics.
 *
 * The original handler failed too silently before DB insert. This handler is
 * loaded after the original one, replaces the admin-post callbacks, stores the
 * last submit reason, and never lets email failure delete or block a saved row.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_booking_hotfix_record_attempt( $status, $reason, $data = array() ) {
	$payload = array(
		'time'   => current_time( 'mysql', true ),
		'status' => sanitize_key( $status ),
		'reason' => sanitize_key( $reason ),
		'data'   => array(),
	);

	foreach ( (array) $data as $key => $value ) {
		if ( in_array( $key, array( 'password', 'token', 'secret', 'api_key' ), true ) ) {
			continue;
		}

		if ( is_scalar( $value ) || null === $value ) {
			$payload['data'][ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
		}
	}

	update_option( 'agency_booking_last_create_attempt', $payload, false );

	if ( function_exists( 'agency_core_audit_log' ) ) {
		agency_core_audit_log( 'booking', 'create_' . sanitize_key( $status ), $payload );
	}
}

function agency_booking_hotfix_fail( $redirect, $reason, $data = array() ) {
	agency_booking_hotfix_record_attempt( 'failed', $reason, $data );
	wp_safe_redirect( add_query_arg( array( 'booking' => 'error', 'booking_reason' => sanitize_key( $reason ) ), $redirect ) );
	exit;
}

function agency_booking_hotfix_conflict_count( $start_at, $end_at, $settings ) {
	global $wpdb;

	$statuses = function_exists( 'agency_booking_blocking_statuses' ) ? agency_booking_blocking_statuses( $settings ) : array( 'pending', 'approved' );
	$statuses = array_values( array_filter( array_map( 'sanitize_key', (array) $statuses ) ) );

	if ( ! $statuses ) {
		return 0;
	}

	$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	$sql          = 'SELECT COUNT(*) FROM ' . agency_booking_table() . " WHERE start_at < %s AND end_at > %s AND status IN ({$placeholders})";
	$args         = array_merge( array( $end_at, $start_at ), $statuses );

	return (int) $wpdb->get_var( $wpdb->prepare( $sql, $args ) );
}

function agency_booking_create_hardened() {
	check_admin_referer( 'agency_booking_create' );

	$settings = agency_booking_settings();
	$redirect = wp_get_referer() ?: home_url( '/' );

	if ( empty( $settings['booking_enabled'] ) ) {
		agency_booking_hotfix_fail( $redirect, 'booking_disabled' );
	}

	if ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'booking_create', 20, HOUR_IN_SECONDS ) ) {
		agency_booking_hotfix_fail( $redirect, 'rate_limited' );
	}

	if ( ! empty( $settings['login_required'] ) && ! is_user_logged_in() ) {
		agency_booking_hotfix_fail( $redirect, 'login_required' );
	}

	if ( empty( $settings['guest_booking'] ) && ! is_user_logged_in() ) {
		agency_booking_hotfix_fail( $redirect, 'guest_booking_disabled' );
	}

	if ( ! empty( $settings['require_verified_email'] ) && is_user_logged_in() && function_exists( 'agency_auth_is_verified' ) && ! agency_auth_is_verified( get_current_user_id() ) ) {
		agency_booking_hotfix_fail( $redirect, 'email_not_verified' );
	}

	$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$service_id = absint( $_POST['service_id'] ?? 0 );
	$start_raw  = sanitize_text_field( wp_unslash( $_POST['start_at'] ?? '' ) );
	$name       = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
	$phone      = sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) );
	$note       = sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) );
	$privacy    = ! empty( $_POST['privacy'] );

	$debug_data = array(
		'email'      => $email,
		'service_id' => $service_id,
		'start_at'   => $start_raw,
		'name'       => $name,
		'phone'      => $phone,
		'privacy'    => $privacy ? '1' : '0',
	);

	if ( ! is_email( $email ) ) {
		agency_booking_hotfix_fail( $redirect, 'invalid_email', $debug_data );
	}

	if ( ! $service_id || 'agency_service' !== get_post_type( $service_id ) ) {
		agency_booking_hotfix_fail( $redirect, 'invalid_service', $debug_data );
	}

	$local_date = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $start_raw, wp_timezone() );
	if ( ! $local_date ) {
		agency_booking_hotfix_fail( $redirect, 'invalid_start_time', $debug_data );
	}

	if ( ! $name || ! $phone || ! $privacy ) {
		agency_booking_hotfix_fail( $redirect, 'missing_customer_fields', $debug_data );
	}

	$booking_date = $local_date->format( 'Y-m-d' );
	if ( function_exists( 'agency_booking_is_open_date' ) && ! agency_booking_is_open_date( $booking_date, $settings ) ) {
		agency_booking_hotfix_fail( $redirect, 'closed_date', $debug_data );
	}

	$duration  = agency_booking_service_duration( $service_id );
	$local_end = $local_date->modify( "+{$duration} minutes" );
	$opening   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $booking_date . ' ' . $settings['day_start'], wp_timezone() );
	$closing   = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $booking_date . ' ' . $settings['day_end'], wp_timezone() );

	if ( $opening && $closing && ( $local_date < $opening || $local_end > $closing ) ) {
		agency_booking_hotfix_fail( $redirect, 'outside_business_hours', $debug_data );
	}

	if ( $local_date <= new DateTimeImmutable( 'now', wp_timezone() ) ) {
		agency_booking_hotfix_fail( $redirect, 'past_slot', $debug_data );
	}

	if ( ! agency_booking_ensure_schema() ) {
		agency_booking_hotfix_fail( $redirect, 'booking_table_missing', $debug_data );
	}

	global $wpdb;

	$start_at  = gmdate( 'Y-m-d H:i:s', $local_date->getTimestamp() );
	$end_at    = gmdate( 'Y-m-d H:i:s', $local_end->getTimestamp() );
	$lock_name = 'agency_booking_day_' . md5( $booking_date );
	$locked    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock_name ) );

	if ( 1 !== $locked ) {
		agency_booking_hotfix_fail( $redirect, 'lock_failed', $debug_data );
	}

	if ( agency_booking_hotfix_conflict_count( $start_at, $end_at, $settings ) > 0 ) {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		agency_booking_hotfix_fail( $redirect, 'slot_conflict', $debug_data );
	}

	$status      = 'auto' === $settings['confirmation_mode'] && is_user_logged_in() ? 'approved' : 'pending';
	$guest_token = is_user_logged_in() ? '' : bin2hex( random_bytes( 24 ) );
	$now         = current_time( 'mysql', true );
	$booking     = agency_booking_insert(
		array(
			'user_id'           => get_current_user_id(),
			'service_id'        => $service_id,
			'start_at'          => $start_at,
			'end_at'            => $end_at,
			'name'              => $name,
			'email'             => $email,
			'phone'             => $phone,
			'customer_note'     => $note,
			'admin_note'        => '',
			'status'            => $status,
			'verification_hash' => $guest_token ? wp_hash_password( $guest_token ) : '',
			'created_at'        => $now,
			'updated_at'        => $now,
		)
	);

	$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );

	if ( is_wp_error( $booking ) || ! $booking ) {
		$debug_data['db_error'] = is_wp_error( $booking ) ? $booking->get_error_message() : ( $wpdb->last_error ?: 'empty_booking_row' );
		agency_booking_hotfix_fail( $redirect, 'insert_failed', $debug_data );
	}

	$email_results = array();
	$verification_message = '';
	if ( $guest_token ) {
		$verify_url = add_query_arg( array( 'agency_booking_verify' => $booking->id, 'token' => rawurlencode( $guest_token ) ), home_url( '/' ) );
		$verification_message = '<p><a href="' . esc_url( $verify_url ) . '">' . esc_html__( 'Verify this guest booking', 'agency-module-booking' ) . '</a></p>';
	}

	foreach ( array( 'received', 'admin' ) as $email_type ) {
		$result = agency_booking_send_email( $email_type, $booking, '', 'received' === $email_type ? $verification_message : '' );
		$email_results[ $email_type ] = is_wp_error( $result ) ? $result->get_error_message() : 'sent';
	}

	if ( 'approved' === $status ) {
		$result = agency_booking_send_email( 'approved', $booking );
		$email_results['approved'] = is_wp_error( $result ) ? $result->get_error_message() : 'sent';
	}

	agency_booking_hotfix_record_attempt(
		'success',
		'created',
		array_merge(
			$debug_data,
			array(
				'booking_id' => $booking->id,
				'status'     => $status,
				'emails'     => wp_json_encode( $email_results ),
			)
		)
	);

	$thank_you = $settings['thank_you_url'] ?: $redirect;
	wp_safe_redirect( add_query_arg( 'booking', 'received', $thank_you ) );
	exit;
}

remove_action( 'admin_post_nopriv_agency_booking_create', 'agency_booking_create' );
remove_action( 'admin_post_agency_booking_create', 'agency_booking_create' );
add_action( 'admin_post_nopriv_agency_booking_create', 'agency_booking_create_hardened' );
add_action( 'admin_post_agency_booking_create', 'agency_booking_create_hardened' );
