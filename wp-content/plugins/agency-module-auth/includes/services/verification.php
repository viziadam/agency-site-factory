<?php
/**
 * Email verification.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_auth_is_verified( $user_id ) {
	return '1' === get_user_meta( $user_id, '_agency_email_verified', true );
}

function agency_auth_send_verification( $user_id ) {
	$user  = get_userdata( $user_id );
	$token = bin2hex( random_bytes( 24 ) );
	update_user_meta( $user_id, '_agency_verify_hash', wp_hash_password( $token ) );
	update_user_meta( $user_id, '_agency_verify_expires', time() + absint( agency_auth_settings()['token_lifetime'] ) );
	$verification_page = absint( agency_auth_settings()['page_ids']['verification'] ?? 0 );
	$url     = add_query_arg( array( 'agency_verify' => $user_id, 'token' => rawurlencode( $token ) ), $verification_page ? get_permalink( $verification_page ) : home_url( '/' ) );
	$subject = agency_auth_settings()['email_subject'];
	$body    = str_replace( '{{verification_url}}', esc_url( $url ), agency_auth_settings()['email_body'] );
	return function_exists( 'agency_core_email_send' ) ? agency_core_email_send( $user->user_email, $subject, $body ) : wp_mail( $user->user_email, $subject, $body );
}

function agency_auth_verify_request() {
	if ( empty( $_GET['agency_verify'] ) || empty( $_GET['token'] ) ) {
		return;
	}
	$user_id = absint( $_GET['agency_verify'] );
	$token   = sanitize_text_field( wp_unslash( $_GET['token'] ) );
	$hash    = get_user_meta( $user_id, '_agency_verify_hash', true );
	$expires = absint( get_user_meta( $user_id, '_agency_verify_expires', true ) );
	if ( $hash && $expires >= time() && wp_check_password( $token, $hash ) ) {
		update_user_meta( $user_id, '_agency_email_verified', '1' );
		delete_user_meta( $user_id, '_agency_verify_hash' );
		delete_user_meta( $user_id, '_agency_verify_expires' );
		if ( function_exists( 'agency_core_audit_log' ) ) {
			agency_core_audit_log( 'auth', 'email_verified', array( 'verified_user_id' => $user_id ) );
		}
		$login_page = absint( agency_auth_settings()['page_ids']['login'] ?? 0 );
		wp_safe_redirect( add_query_arg( 'agency_auth', 'verified', $login_page ? get_permalink( $login_page ) : agency_auth_settings()['login_redirect'] ) );
		exit;
	}
	wp_die( esc_html__( 'This verification link is invalid or expired.', 'agency-module-auth' ), 403 );
}
add_action( 'init', 'agency_auth_verify_request' );
