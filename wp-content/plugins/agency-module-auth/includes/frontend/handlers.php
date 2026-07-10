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

function agency_auth_send_password_reset_email( $user ) {
	if ( ! $user instanceof WP_User ) {
		return new WP_Error( 'agency_auth_invalid_user', __( 'Invalid user.', 'agency-module-auth' ) );
	}

	$key = get_password_reset_key( $user );

	if ( is_wp_error( $key ) ) {
		return $key;
	}

	$reset_url = network_site_url(
		'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $user->user_login ),
		'login'
	);

	$subject = __( 'Jelszó visszaállítása', 'agency-module-auth' );
	$message = sprintf(
		'<p>%s</p><p><a href="%s">%s</a></p><p>%s</p>',
		esc_html__( 'Jelszó-visszaállítást kértél a fiókodhoz.', 'agency-module-auth' ),
		esc_url( $reset_url ),
		esc_html__( 'Új jelszó beállítása', 'agency-module-auth' ),
		esc_html__( 'Ha nem te kérted, ezt az emailt figyelmen kívül hagyhatod.', 'agency-module-auth' )
	);

	if ( function_exists( 'agency_core_email_send' ) ) {
		return agency_core_email_send( $user->user_email, $subject, $message, array(), false );
	}

	return wp_mail(
		$user->user_email,
		wp_strip_all_tags( $subject ),
		$message,
		array( 'Content-Type: text/html; charset=UTF-8' )
	);
}

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

	if ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'auth_reset', 4, HOUR_IN_SECONDS ) ) {
		agency_auth_redirect_status( 'rate-limited' );
	}

	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$user  = $email ? get_user_by( 'email', $email ) : false;

	if ( $user ) {
		$result = agency_auth_send_password_reset_email( $user );

		if ( is_wp_error( $result ) && function_exists( 'agency_core_audit_log' ) ) {
			agency_core_audit_log(
				'auth',
				'password_reset_failed',
				array(
					'user_id' => $user->ID,
					'error'   => $result->get_error_message(),
				)
			);
		}
	}

	wp_safe_redirect( add_query_arg( 'agency_auth', 'reset-sent', wp_get_referer() ?: home_url( '/' ) ) );
	exit;
}

function agency_auth_resend() {
	check_admin_referer( 'agency_auth_resend' );

	if ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'auth_resend', 3, HOUR_IN_SECONDS ) ) {
		agency_auth_redirect_status( 'rate-limited' );
	}

	$user = get_user_by( 'email', sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ) );

	if ( $user && ! agency_auth_is_verified( $user->ID ) ) {
		agency_auth_send_verification( $user->ID );
	}

	wp_safe_redirect( add_query_arg( 'agency_auth', 'verification-sent', wp_get_referer() ?: home_url( '/' ) ) );
	exit;
}

add_action( 'admin_post_nopriv_agency_auth_register', 'agency_auth_register' );
add_action( 'admin_post_agency_auth_register', 'agency_auth_register' );

add_action( 'admin_post_nopriv_agency_auth_login', 'agency_auth_login' );
add_action( 'admin_post_agency_auth_login', 'agency_auth_login' );

add_action( 'admin_post_nopriv_agency_auth_reset', 'agency_auth_reset' );
add_action( 'admin_post_agency_auth_reset', 'agency_auth_reset' );

add_action( 'admin_post_nopriv_agency_auth_resend', 'agency_auth_resend' );
add_action( 'admin_post_agency_auth_resend', 'agency_auth_resend' );
