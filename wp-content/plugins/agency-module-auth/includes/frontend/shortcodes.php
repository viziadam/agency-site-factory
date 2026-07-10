<?php
/**
 * Auth frontend shortcodes and navigation integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_auth_notice() {
	$status   = sanitize_key( wp_unslash( $_GET['agency_auth'] ?? '' ) );
	$messages = array(
		'registered'         => __( 'A regisztráció sikeres. Küldtünk egy emailt a megerősítéshez.', 'agency-module-auth' ),
		'verified'           => __( 'Az email címed megerősítve. Most már be tudsz jelentkezni.', 'agency-module-auth' ),
		'reset-sent'         => __( 'Ha létezik ilyen fiók, elküldtük a jelszó-visszaállító emailt.', 'agency-module-auth' ),
		'verification-sent'  => __( 'Ha az email megerősítés még függőben van, újraküldtük a levelet.', 'agency-module-auth' ),
		'registration-error' => __( 'A regisztráció nem sikerült. Ellenőrizd az adatokat, vagy használj másik email címet.', 'agency-module-auth' ),
		'login-error'        => __( 'Sikertelen bejelentkezés. Ellenőrizd az email címet, a jelszót és az email-megerősítést.', 'agency-module-auth' ),
		'rate-limited'       => __( 'Túl sok próbálkozás történt. Várj egy kicsit, majd próbáld újra.', 'agency-module-auth' ),
		'blocked'            => __( 'Ez a fiók tiltva van.', 'agency-module-auth' ),
	);
	$errors   = array( 'registration-error', 'login-error', 'rate-limited', 'blocked' );

	if ( ! isset( $messages[ $status ] ) ) {
		return '';
	}

	return '<div class="agency-auth-message agency-auth-message--' . esc_attr( in_array( $status, $errors, true ) ? 'error' : 'success' ) . '" role="status">' . esc_html( $messages[ $status ] ) . '</div>';
}

function agency_auth_redirect_status( $status, $fallback = '' ) {
	wp_safe_redirect( add_query_arg( 'agency_auth', sanitize_key( $status ), $fallback ?: ( wp_get_referer() ?: home_url( '/' ) ) ) );
	exit;
}

function agency_auth_page_url( $key, $fallback = '/' ) {
	$page_id = absint( agency_auth_settings()['page_ids'][ $key ] ?? 0 );
	$url     = $page_id ? get_permalink( $page_id ) : '';

	return $url ?: home_url( $fallback );
}

function agency_auth_form( $atts = array() ) {
	$atts = shortcode_atts( array( 'mode' => 'all' ), (array) $atts, 'agency_auth' );
	$mode = in_array( $atts['mode'], array( 'all', 'login', 'register', 'reset', 'verification' ), true ) ? $atts['mode'] : 'all';

	if ( 'verification' === $mode ) {
		return '<div class="agency-auth-shell agency-auth-verification">' . agency_auth_notice() . '<p>' . esc_html__( 'Nyisd meg az emailben kapott megerősítő linket. Ha nem érkezett meg, kérhetsz új megerősítő emailt.', 'agency-module-auth' ) . '</p></div>';
	}

	if ( is_user_logged_in() ) {
		return '<div class="agency-auth-shell agency-auth-notice"><p>' . esc_html__( 'Már be vagy jelentkezve.', 'agency-module-auth' ) . ' <a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Kijelentkezés', 'agency-module-auth' ) . '</a></p></div>';
	}

	$action       = esc_url( admin_url( 'admin-post.php' ) );
	$password_url = agency_auth_page_url( 'password', '/jelszo-visszaallitas/' );
	ob_start();
	?>
	<div class="agency-auth-shell agency-auth-shell--<?php echo esc_attr( $mode ); ?>">
		<?php echo wp_kses_post( agency_auth_notice() ); ?>

		<?php if ( agency_auth_settings()['registration_enabled'] && in_array( $mode, array( 'all', 'register' ), true ) ) : ?>
			<form class="agency-auth-form agency-auth-register-form" method="post" action="<?php echo $action; ?>">
				<span class="agency-auth-eyebrow"><?php esc_html_e( 'Ügyfélfiók', 'agency-module-auth' ); ?></span>
				<h2><?php esc_html_e( 'Fiók létrehozása', 'agency-module-auth' ); ?></h2>
				<input type="hidden" name="action" value="agency_auth_register">
				<?php wp_nonce_field( 'agency_auth_register' ); ?>
				<p><label><?php esc_html_e( 'Név', 'agency-module-auth' ); ?><input required name="display_name" autocomplete="name"></label></p>
				<p><label><?php esc_html_e( 'Email', 'agency-module-auth' ); ?><input required type="email" name="email" autocomplete="email"></label></p>
				<p><label><?php esc_html_e( 'Jelszó', 'agency-module-auth' ); ?><input required minlength="10" type="password" name="password" autocomplete="new-password"></label></p>
				<p><label><input required type="checkbox" name="privacy" value="1"> <?php echo esc_html( function_exists( 'agency_legal_get_checkbox_text' ) ? agency_legal_get_checkbox_text( 'registration' ) : __( 'Elfogadom az adatkezelési feltételeket.', 'agency-module-auth' ) ); ?></label></p>
				<button type="submit"><?php esc_html_e( 'Regisztráció', 'agency-module-auth' ); ?></button>
			</form>
		<?php endif; ?>

		<?php if ( in_array( $mode, array( 'all', 'login' ), true ) ) : ?>
			<form class="agency-auth-form agency-auth-login-form" method="post" action="<?php echo $action; ?>">
				<span class="agency-auth-eyebrow"><?php esc_html_e( 'Üdv újra itt', 'agency-module-auth' ); ?></span>
				<h2><?php esc_html_e( 'Bejelentkezés', 'agency-module-auth' ); ?></h2>
				<input type="hidden" name="action" value="agency_auth_login">
				<?php wp_nonce_field( 'agency_auth_login' ); ?>
				<p><label><?php esc_html_e( 'Email', 'agency-module-auth' ); ?><input required type="email" name="email" autocomplete="email"></label></p>
				<p><label><?php esc_html_e( 'Jelszó', 'agency-module-auth' ); ?><input required type="password" name="password" autocomplete="current-password"></label></p>
				<button type="submit"><?php esc_html_e( 'Bejelentkezés', 'agency-module-auth' ); ?></button>
				<a class="agency-auth-forgot-link" href="<?php echo esc_url( $password_url ); ?>"><?php esc_html_e( 'Elfelejtetted a jelszavad?', 'agency-module-auth' ); ?></a>
			</form>
		<?php endif; ?>

		<?php if ( in_array( $mode, array( 'all', 'reset' ), true ) ) : ?>
			<form class="agency-auth-form agency-auth-reset-form" method="post" action="<?php echo $action; ?>">
				<h2><?php esc_html_e( 'Jelszó visszaállítása', 'agency-module-auth' ); ?></h2>
				<p><?php esc_html_e( 'Add meg az email címed, és küldünk egy jelszó-visszaállító linket.', 'agency-module-auth' ); ?></p>
				<input type="hidden" name="action" value="agency_auth_reset">
				<?php wp_nonce_field( 'agency_auth_reset' ); ?>
				<p><label><?php esc_html_e( 'Email', 'agency-module-auth' ); ?><input required type="email" name="email" autocomplete="email"></label></p>
				<button type="submit"><?php esc_html_e( 'Jelszó-visszaállító link küldése', 'agency-module-auth' ); ?></button>
			</form>

			<form class="agency-auth-form agency-auth-resend-form" method="post" action="<?php echo $action; ?>">
				<input type="hidden" name="action" value="agency_auth_resend">
				<?php wp_nonce_field( 'agency_auth_resend' ); ?>
				<p><label><?php esc_html_e( 'Megerősítő email újraküldése', 'agency-module-auth' ); ?><input required type="email" name="email" autocomplete="email"></label></p>
				<button type="submit"><?php esc_html_e( 'Újraküldés', 'agency-module-auth' ); ?></button>
			</form>
		<?php endif; ?>
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
	return '<section class="agency-auth-account"><header><span>' . esc_html__( 'Ügyfélfiók', 'agency-module-auth' ) . '</span><h2>' . esc_html__( 'Fiókom', 'agency-module-auth' ) . '</h2><p>' . esc_html( $user->display_name ) . ' · ' . esc_html( $user->user_email ) . '</p><a class="agency-auth-logout" href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Kijelentkezés', 'agency-module-auth' ) . '</a></header>' . ( shortcode_exists( 'agency_booking_customer_list' ) ? do_shortcode( '[agency_booking_customer_list]' ) : '' ) . '</section>';
}
add_shortcode( 'agency_account', 'agency_auth_account' );
add_shortcode( 'agency_auth_account', 'agency_auth_account' );

function agency_auth_admin_guard() {
	if ( ! is_admin() || wp_doing_ajax() ) {
		return;
	}

	if ( current_user_can( 'manage_options' ) || current_user_can( 'manage_agency_portal' ) || current_user_can( 'manage_agency_bookings' ) ) {
		return;
	}

	if ( current_user_can( 'agency_customer' ) && ! current_user_can( 'edit_posts' ) ) {
		wp_safe_redirect( agency_auth_settings()['login_redirect'] );
		exit;
	}
}
add_action( 'admin_init', 'agency_auth_admin_guard' );
add_filter( 'show_admin_bar', static fn( $show ) => current_user_can( 'agency_customer' ) && ! current_user_can( 'edit_posts' ) ? false : $show );

function agency_auth_menu_items( $items, $args ) {
	$s = agency_auth_settings();
	if ( function_exists( 'agency_theme_render_auth_actions' ) && apply_filters( 'agency_auth_prefer_header_actions', true ) ) {
		return $items;
	}
	$auto_links = $s['auto_add_auth_links'] ?? $s['auto_add_to_menu'] ?? true;
	if ( ! $auto_links || 'primary' !== ( $args->theme_location ?? '' ) ) {
		return $items;
	}
	$ids = (array) ( $s['page_ids'] ?? array() );
	if ( is_user_logged_in() ) {
		if ( ! empty( $ids['account'] ) ) {
			$items .= '<li class="menu-item agency-auth-menu"><a href="' . esc_url( get_permalink( $ids['account'] ) ) . '">' . esc_html__( 'Fiókom', 'agency-module-auth' ) . '</a></li>';
		}
		$items .= '<li class="menu-item agency-auth-menu"><a href="' . esc_url( wp_logout_url( home_url( '/' ) ) ) . '">' . esc_html__( 'Kijelentkezés', 'agency-module-auth' ) . '</a></li>';
	} else {
		foreach ( array( 'login' => __( 'Bejelentkezés', 'agency-module-auth' ), 'register' => __( 'Regisztráció', 'agency-module-auth' ) ) as $key => $label ) {
			if ( ! empty( $ids[ $key ] ) ) {
				$items .= '<li class="menu-item agency-auth-menu"><a href="' . esc_url( get_permalink( $ids[ $key ] ) ) . '">' . esc_html( $label ) . '</a></li>';
			}
		}
	}
	return $items;
}
add_filter( 'wp_nav_menu_items', 'agency_auth_menu_items', 20, 2 );
