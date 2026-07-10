<?php
/**
 * Auth form handlers.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_auth_unique_username_from_email( $email ) {
	$base = sanitize_user( strstr( $email, '@', true ), true );

	if ( ! $base ) {
		$base = 'customer';
	}

	$username = $base;

	for ( $index = 2; username_exists( $username ); $index++ ) {
		$username = $base . $index;
	}

	return $username;
}

function agency_auth_register() {
	check_admin_referer( 'agency_auth_register' );
	if ( ! agency_auth_settings()['registration_enabled'] || ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'auth_register', 4, HOUR_IN_SECONDS ) ) ) {
		agency_auth_redirect_status( 'rate-limited' );
	}
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$name  = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
	$pass  = (string) wp_unslash( $_POST['password'] ?? '' );
	if ( ! is_email( $email ) || strlen( $pass ) < 10 || empty( $_POST['privacy'] ) || email_exists( $email ) ) {
		agency_auth_redirect_status( 'registration-error' );
	}
	$id = wp_insert_user(
	array(
		'user_login'   => agency_auth_unique_username_from_email( $email ),
		'user_email'   => $email,
		'display_name' => $name ?: $email,
		'user_pass'    => $pass,
		'role'         => 'agency_customer',
		)
	);
	if ( is_wp_error( $id ) ) {
		agency_auth_redirect_status( 'registration-error' );
	}
	$verified = agency_auth_settings()['require_verification'] ? '0' : '1';

	update_user_meta( $id, '_agency_email_verified', $verified );

	if ( '1' === $verified ) {
		update_user_meta( $id, '_agency_email_verified_at', current_time( 'mysql', true ) );
	}
	if ( agency_auth_settings()['require_verification'] ) {
		agency_auth_send_verification( $id );
	}
	wp_safe_redirect( add_query_arg( 'agency_auth', 'registered', wp_get_referer() ?: home_url( '/' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_agency_auth_register', 'agency_auth_register' );

function agency_auth_login() {
	check_admin_referer( 'agency_auth_login' );

	if ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'auth_login', 8, 15 * MINUTE_IN_SECONDS ) ) {
		agency_auth_redirect_status( 'rate-limited' );
	}

	$login = sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) );
	$user  = is_email( $login ) ? get_user_by( 'email', sanitize_email( $login ) ) : get_user_by( 'login', $login );

	if ( $user && get_user_meta( $user->ID, '_agency_auth_blocked', true ) ) {
		agency_auth_redirect_status( 'blocked' );
	}

	if ( $user && agency_auth_settings()['require_verification'] && ! agency_auth_is_verified( $user->ID ) ) {
		agency_auth_redirect_status( 'login-error' );
	}

	$signed = wp_signon(
		array(
			'user_login'    => $user ? $user->user_login : $login,
			'user_password' => (string) wp_unslash( $_POST['password'] ?? '' ),
			'remember'      => true,
		),
		is_ssl()
	);

	if ( is_wp_error( $signed ) ) {
		agency_auth_redirect_status( 'login-error' );
	}

	wp_safe_redirect( agency_auth_settings()['login_redirect'] );
	exit;
}

function agency_auth_reset() {
	check_admin_referer( 'agency_auth_reset' );
	if ( function_exists( 'agency_core_rate_limit' ) && agency_core_rate_limit( 'auth_reset', 4, HOUR_IN_SECONDS ) ) {
		retrieve_password( sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
	}
	wp_safe_redirect( add_query_arg( 'agency_auth', 'reset-sent', wp_get_referer() ?: home_url( '/' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_agency_auth_reset', 'agency_auth_reset' );

function agency_auth_resend() {
	check_admin_referer( 'agency_auth_resend' );
	if ( function_exists( 'agency_core_rate_limit' ) && agency_core_rate_limit( 'auth_resend', 3, HOUR_IN_SECONDS ) ) {
		$user = get_user_by( 'email', sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );
		if ( $user && ! agency_auth_is_verified( $user->ID ) ) {
			agency_auth_send_verification( $user->ID );
		}
	}
	wp_safe_redirect( add_query_arg( 'agency_auth', 'verification-sent', wp_get_referer() ?: home_url( '/' ) ) );
	exit;
}
add_action( 'admin_post_nopriv_agency_auth_resend', 'agency_auth_resend' );
