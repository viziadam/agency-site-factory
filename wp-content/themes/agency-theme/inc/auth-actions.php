<?php
/**
 * Header authentication action buttons.
 *
 * @package Agency_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_theme_auth_page_url( $key, $fallback = '/' ) {
	if ( function_exists( 'agency_auth_settings' ) ) {
		$settings = agency_auth_settings();
		$page_ids = (array) ( $settings['page_ids'] ?? array() );

		if ( ! empty( $page_ids[ $key ] ) ) {
			$url = get_permalink( absint( $page_ids[ $key ] ) );

			if ( $url ) {
				return $url;
			}
		}
	}

	return home_url( $fallback );
}

function agency_theme_render_auth_actions() {
	if ( ! function_exists( 'agency_auth_settings' ) ) {
		return;
	}

	$settings = agency_auth_settings();

	echo '<div class="agency-header-auth" aria-label="' . esc_attr__( 'Fiókműveletek', 'agency-theme' ) . '">';

	if ( is_user_logged_in() ) {
		$user        = wp_get_current_user();
		$account_url = agency_theme_auth_page_url( 'account', '/fiokom/' );
		$logout_url  = wp_logout_url( home_url( '/' ) );

		echo '<span class="agency-header-auth__user">' . esc_html( $user->display_name ?: $user->user_login ) . '</span>';
		echo '<a class="agency-header-auth__link" href="' . esc_url( $account_url ) . '">' . esc_html__( 'Fiókom', 'agency-theme' ) . '</a>';
		echo '<a class="agency-header-auth__button agency-header-auth__button--secondary" href="' . esc_url( $logout_url ) . '">' . esc_html__( 'Kijelentkezés', 'agency-theme' ) . '</a>';
	} else {
		$login_url    = agency_theme_auth_page_url( 'login', '/bejelentkezes/' );
		$register_url = agency_theme_auth_page_url( 'register', '/regisztracio/' );

		echo '<a class="agency-header-auth__link" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Bejelentkezés', 'agency-theme' ) . '</a>';

		if ( ! empty( $settings['registration_enabled'] ) ) {
			echo '<a class="agency-header-auth__button" href="' . esc_url( $register_url ) . '">' . esc_html__( 'Regisztráció', 'agency-theme' ) . '</a>';
		}
	}

	echo '</div>';
}
