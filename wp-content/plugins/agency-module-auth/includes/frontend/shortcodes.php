<?php
/**
 * Auth frontend shortcodes and navigation integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_auth_notice() {
	$status = sanitize_key( wp_unslash( $_GET['agency_auth'] ?? '' ) );
	$messages = array(
		'registered'         => __( 'Registration completed. Check your inbox to verify your email.', 'agency-module-auth' ),
		'verified'           => __( 'Your email address is verified. You can sign in.', 'agency-module-auth' ),
		'reset-sent'         => __( 'If the account exists, a password reset email has been sent.', 'agency-module-auth' ),
		'verification-sent'  => __( 'If verification is pending, a new email has been sent.', 'agency-module-auth' ),
		'registration-error' => __( 'Registration could not be completed. Check the fields or use another email address.', 'agency-module-auth' ),
		'login-error'        => __( 'Sign-in failed. Check your credentials and account verification.', 'agency-module-auth' ),
		'rate-limited'       => __( 'Too many attempts. Please wait and try again.', 'agency-module-auth' ),
		'blocked'            => __( 'This customer account is blocked.', 'agency-module-auth' ),
	);
	$errors = array( 'registration-error', 'login-error', 'rate-limited', 'blocked' );
	return isset( $messages[ $status ] ) ? '<div class="agency-auth-message agency-auth-message--' . ( in_array( $status, $errors, true ) ? 'error' : 'success' ) . '" role="status">' . esc_html( $messages[ $status ] ) . '</div>' : '';
}

function agency_auth_redirect_status( $status, $fallback = '' ) {
	wp_safe_redirect( add_query_arg( 'agency_auth', sanitize_key( $status ), $fallback ?: ( wp_get_referer() ?: home_url( '/' ) ) ) );
	exit;
}

function agency_auth_form( $atts = array() ) {
	$atts = shortcode_atts( array( 'mode' => 'all' ), (array) $atts, 'agency_auth' );
	$mode = in_array( $atts['mode'], array( 'all', 'login', 'register', 'reset', 'verification' ), true ) ? $atts['mode'] : 'all';
	if ( 'verification' === $mode ) {
		return '<div class="agency-auth-shell agency-auth-verification">' . agency_auth_notice() . '<p>' . esc_html__( 'Open the verification link sent to your email address. You can request a new message from the password/reset page.', 'agency-module-auth' ) . '</p></div>';
	}
	if ( is_user_logged_in() ) {
		return '<div class="agency-auth-shell agency-auth-notice"><p>' . esc_html__( 'You are signed in.', 'agency-module-auth' ) . ' <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Sign out', 'agency-module-auth' ) . '</a></p></div>';
	}
	$action = esc_url( admin_url( 'admin-post.php' ) );
	ob_start();
	?>
	<div class="agency-auth-shell agency-auth-shell--<?php echo esc_attr( $mode ); ?>">
	<?php echo wp_kses_post( agency_auth_notice() ); ?>
	<?php if ( agency_auth_settings()['registration_enabled'] && in_array( $mode, array( 'all', 'register' ), true ) ) : ?><form class="agency-auth-form agency-auth-register-form" method="post" action="<?php echo $action; ?>"><span class="agency-auth-eyebrow"><?php esc_html_e( 'Customer account', 'agency-module-auth' ); ?></span><h2><?php esc_html_e( 'Create account', 'agency-module-auth' ); ?></h2><input type="hidden" name="action" value="agency_auth_register"><?php wp_nonce_field( 'agency_auth_register' ); ?>
	<p><label><?php esc_html_e( 'Name', 'agency-module-auth' ); ?><input required name="display_name" autocomplete="name"></label></p><p><label><?php esc_html_e( 'Email', 'agency-module-auth' ); ?><input required type="email" name="email" autocomplete="email"></label></p><p><label><?php esc_html_e( 'Password', 'agency-module-auth' ); ?><input required minlength="10" type="password" name="password" autocomplete="new-password"></label></p>
	<p><label><input required type="checkbox" name="privacy" value="1"> <?php echo esc_html( function_exists( 'agency_legal_get_checkbox_text' ) ? agency_legal_get_checkbox_text( 'registration' ) : __( 'I accept the privacy policy.', 'agency-module-auth' ) ); ?></label></p><button type="submit"><?php esc_html_e( 'Register', 'agency-module-auth' ); ?></button></form><?php endif; ?>
	<?php if ( in_array( $mode, array( 'all', 'login' ), true ) ) : ?><form class="agency-auth-form agency-auth-login-form" method="post" action="<?php echo $action; ?>"><span class="agency-auth-eyebrow"><?php esc_html_e( 'Welcome back', 'agency-module-auth' ); ?></span><h2><?php esc_html_e( 'Sign in', 'agency-module-auth' ); ?></h2><input type="hidden" name="action" value="agency_auth_login"><?php wp_nonce_field( 'agency_auth_login' ); ?><p><label><?php esc_html_e( 'Email', 'agency-module-auth' ); ?><input required type="email" name="email" autocomplete="email"></label></p><p><label><?php esc_html_e( 'Password', 'agency-module-auth' ); ?><input required type="password" name="password" autocomplete="current-password"></label></p><button type="submit"><?php esc_html_e( 'Sign in', 'agency-module-auth' ); ?></button></form><?php endif; ?>
	<?php if ( in_array( $mode, array( 'all', 'reset' ), true ) ) : ?><form class="agency-auth-form agency-auth-reset-form" method="post" action="<?php echo $action; ?>"><h2><?php esc_html_e( 'Reset password', 'agency-module-auth' ); ?></h2><input type="hidden" name="action" value="agency_auth_reset"><?php wp_nonce_field( 'agency_auth_reset' ); ?><p><label><?php esc_html_e( 'Email', 'agency-module-auth' ); ?><input required type="email" name="email"></label></p><button type="submit"><?php esc_html_e( 'Send reset link', 'agency-module-auth' ); ?></button></form>
	<form class="agency-auth-form agency-auth-resend-form" method="post" action="<?php echo $action; ?>"><input type="hidden" name="action" value="agency_auth_resend"><?php wp_nonce_field( 'agency_auth_resend' ); ?><p><label><?php esc_html_e( 'Resend verification email', 'agency-module-auth' ); ?><input required type="email" name="email"></label></p><button type="submit"><?php esc_html_e( 'Resend', 'agency-module-auth' ); ?></button></form><?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'agency_auth', 'agency_auth_form' );
add_shortcode( 'agency_auth_login', static fn() => agency_auth_form( array( 'mode' => 'login' ) ) );
add_shortcode( 'agency_auth_register', static fn() => agency_auth_form( array( 'mode' => 'register' ) ) );
add_action( 'wp_enqueue_scripts', static fn() => wp_enqueue_style( 'agency-module-auth', plugins_url( 'assets/css/auth.css', AGENCY_AUTH_FILE ), array(), AGENCY_AUTH_VERSION ) );
add_action( 'admin_enqueue_scripts', static function ( $hook ) { if ( str_contains( $hook, 'agency-module-auth' ) ) { wp_enqueue_style( 'agency-module-auth-admin', plugins_url( 'assets/css/auth.css', AGENCY_AUTH_FILE ), array(), AGENCY_AUTH_VERSION ); } } );

function agency_auth_account() {
	if ( ! is_user_logged_in() ) {
		return agency_auth_form();
	}
	$user = wp_get_current_user();
	return '<section class="agency-auth-account"><header><span>' . esc_html__( 'Customer dashboard', 'agency-module-auth' ) . '</span><h2>' . esc_html__( 'My account', 'agency-module-auth' ) . '</h2><p>' . esc_html( $user->display_name ) . ' · ' . esc_html( $user->user_email ) . '</p><a class="agency-auth-logout" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Sign out', 'agency-module-auth' ) . '</a></header>' . ( shortcode_exists( 'agency_booking_customer_list' ) ? do_shortcode( '[agency_booking_customer_list]' ) : '' ) . '</section>';
}
add_shortcode( 'agency_account', 'agency_auth_account' );
add_shortcode( 'agency_auth_account', 'agency_auth_account' );

function agency_auth_admin_guard() {
	if ( is_admin() && ! wp_doing_ajax() && current_user_can( 'agency_customer' ) && ! current_user_can( 'edit_posts' ) ) {
		wp_safe_redirect( agency_auth_settings()['login_redirect'] );
		exit;
	}
}
add_action( 'admin_init', 'agency_auth_admin_guard' );
add_filter( 'show_admin_bar', static fn( $show ) => current_user_can( 'agency_customer' ) && ! current_user_can( 'edit_posts' ) ? false : $show );

function agency_auth_menu_items( $items, $args ) {
	$s = agency_auth_settings();
	$auto_links = $s['auto_add_auth_links'] ?? $s['auto_add_to_menu'] ?? true;
	if ( ! $auto_links || 'primary' !== ( $args->theme_location ?? '' ) ) {
		return $items;
	}
	$ids = (array) ( $s['page_ids'] ?? array() );
	if ( is_user_logged_in() ) {
		if ( ! empty( $ids['account'] ) ) {
			$items .= '<li class="menu-item agency-auth-menu"><a href="' . esc_url( get_permalink( $ids['account'] ) ) . '">' . esc_html__( 'My account', 'agency-module-auth' ) . '</a></li>';
		}
		$items .= '<li class="menu-item agency-auth-menu"><a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Sign out', 'agency-module-auth' ) . '</a></li>';
	} else {
		foreach ( array( 'login' => __( 'Sign in', 'agency-module-auth' ), 'register' => __( 'Register', 'agency-module-auth' ) ) as $key => $label ) {
			if ( ! empty( $ids[ $key ] ) ) {
				$items .= '<li class="menu-item agency-auth-menu"><a href="' . esc_url( get_permalink( $ids[ $key ] ) ) . '">' . esc_html( $label ) . '</a></li>';
			}
		}
	}
	return $items;
}
add_filter( 'wp_nav_menu_items', 'agency_auth_menu_items', 20, 2 );
