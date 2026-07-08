<?php
/**
 * Standalone modular client administration portal and first-party event collection.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_client_admin_table() {
	global $wpdb;
	return $wpdb->prefix . 'agency_events';
}

function agency_core_client_admin_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = agency_core_client_admin_table();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		event_type varchar(40) NOT NULL,
		event_name varchar(100) NOT NULL,
		page_path varchar(255) NOT NULL DEFAULT '',
		page_title varchar(255) NOT NULL DEFAULT '',
		session_hash char(64) NOT NULL DEFAULT '',
		meta_json text NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY (id),
		KEY event_type (event_type),
		KEY event_name (event_name),
		KEY created_at (created_at),
		KEY page_path (page_path(100))
	) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'agency_core_client_admin_db_version', '1.1.0', false );

	$role = get_role( 'agency_client_admin' );
	if ( ! $role ) {
		$role = add_role( 'agency_client_admin', __( 'Agency Client Admin', 'agency-core' ), array( 'read' => true, 'manage_agency_portal' => true, 'manage_agency_bookings' => true ) );
	}
	foreach ( array( 'administrator', 'agency_booking_manager', 'agency_client_admin' ) as $role_name ) {
		$item = get_role( $role_name );
		if ( $item ) {
			$item->add_cap( 'manage_agency_portal' );
			if ( 'agency_client_admin' === $role_name ) {
				$item->add_cap( 'manage_agency_bookings' );
			}
		}
	}
	agency_core_client_admin_create_default_user();
	if ( ! wp_next_scheduled( 'agency_core_cleanup_events' ) ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'agency_core_cleanup_events' );
	}
}

function agency_core_client_admin_create_default_user() {
	if ( get_option( 'agency_core_default_portal_user_created' ) ) {
		return;
	}
	$existing_id = username_exists( 'agencyadmin' );
	if ( $existing_id ) {
		$user = get_user_by( 'id', $existing_id );
		if ( $user && ! user_can( $user, 'manage_options' ) && ! in_array( 'agency_client_admin', (array) $user->roles, true ) ) {
			$user->add_role( 'agency_client_admin' );
		}
		update_option( 'agency_core_default_portal_user_created', absint( $existing_id ), false );
		return;
	}
	$password = wp_generate_password( 20, true, true );
	$user_id  = wp_insert_user( array( 'user_login' => 'agencyadmin', 'user_pass' => $password, 'display_name' => __( 'Site manager', 'agency-core' ), 'role' => 'agency_client_admin' ) );
	if ( ! is_wp_error( $user_id ) ) {
		update_option( 'agency_core_default_portal_user_created', $user_id, false );
		set_transient( 'agency_core_initial_portal_credentials', array( 'username' => 'agencyadmin', 'password' => $password ), DAY_IN_SECONDS );
	}
}

function agency_core_client_admin_maybe_install() {
	if ( '1.1.0' !== get_option( 'agency_core_client_admin_db_version' ) ) {
		agency_core_client_admin_install();
	}
}
add_action( 'init', 'agency_core_client_admin_maybe_install', 8 );

function agency_core_client_admin_url( $tab = '' ) {
	$url = home_url( '/admin/' );
	return $tab ? add_query_arg( 'portal', sanitize_key( $tab ), $url ) : $url;
}

function agency_core_client_admin_action_url( $action = '', $tab = '' ) {
	$url = agency_core_client_admin_url( $tab );
	return $action ? add_query_arg( 'agency_portal_action', sanitize_key( $action ), $url ) : $url;
}

function agency_core_client_admin_cookie_name() {
	return 'agency_portal_session';
}

function agency_core_client_admin_cookie_path() {
	$path = wp_parse_url( home_url( '/admin/' ), PHP_URL_PATH );
	$path = $path ? trailingslashit( $path ) : '/admin/';
	return '/' === $path[0] ? $path : '/' . $path;
}

function agency_core_client_admin_session_hash( $token ) {
	return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
}

function agency_core_client_admin_set_cookie( $value, $expires ) {
	$args = array(
		'expires'  => $expires,
		'path'     => agency_core_client_admin_cookie_path(),
		'secure'   => is_ssl(),
		'httponly' => true,
		'samesite' => 'Lax',
	);
	if ( defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ) {
		$args['domain'] = COOKIE_DOMAIN;
	}
	setcookie( agency_core_client_admin_cookie_name(), $value, $args );
	$_COOKIE[ agency_core_client_admin_cookie_name() ] = $value;
}

function agency_core_client_admin_clear_cookie() {
	agency_core_client_admin_set_cookie( '', time() - YEAR_IN_SECONDS );
	unset( $_COOKIE[ agency_core_client_admin_cookie_name() ] );
}

function agency_core_client_admin_create_session( $user_id, $remember = false ) {
	$token      = bin2hex( random_bytes( 32 ) );
	$expires_in = $remember ? 14 * DAY_IN_SECONDS : 12 * HOUR_IN_SECONDS;
	$hash       = agency_core_client_admin_session_hash( $token );
	set_transient(
		'agency_portal_session_' . $hash,
		array(
			'user_id'    => absint( $user_id ),
			'created_at' => time(),
		),
		$expires_in
	);
	agency_core_client_admin_set_cookie( $token, time() + $expires_in );
	if ( function_exists( 'agency_core_audit_log' ) ) {
		agency_core_audit_log( 'portal', 'login', array( 'portal_user_id' => absint( $user_id ) ) );
	}
}

function agency_core_client_admin_destroy_session() {
	$token = (string) ( $_COOKIE[ agency_core_client_admin_cookie_name() ] ?? '' );
	if ( $token ) {
		delete_transient( 'agency_portal_session_' . agency_core_client_admin_session_hash( $token ) );
	}
	agency_core_client_admin_clear_cookie();
}

function agency_core_client_admin_bootstrap_session() {
	static $checked = false;
	static $user_id = 0;
	if ( $checked ) {
		return $user_id;
	}
	$checked = true;
	$token   = (string) ( $_COOKIE[ agency_core_client_admin_cookie_name() ] ?? '' );
	if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
		return 0;
	}
	$hash = agency_core_client_admin_session_hash( $token );
	$data = get_transient( 'agency_portal_session_' . $hash );
	if ( ! is_array( $data ) || empty( $data['user_id'] ) ) {
		agency_core_client_admin_clear_cookie();
		return 0;
	}
	$user = get_userdata( absint( $data['user_id'] ) );
	if ( ! $user || ! user_can( $user, 'manage_agency_portal' ) ) {
		delete_transient( 'agency_portal_session_' . $hash );
		agency_core_client_admin_clear_cookie();
		return 0;
	}
	wp_set_current_user( $user->ID );
	$user_id = $user->ID;
	return $user_id;
}

function agency_core_can_manage_portal() {
	agency_core_client_admin_bootstrap_session();
	return current_user_can( 'manage_options' ) || current_user_can( 'manage_agency_portal' );
}

function agency_core_client_admin_is_request() {
	$request_path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
	$admin_path   = wp_parse_url( agency_core_client_admin_url(), PHP_URL_PATH );
	return untrailingslashit( (string) $request_path ) === untrailingslashit( (string) $admin_path );
}

function agency_core_client_admin_handle_request() {
	if ( ! agency_core_client_admin_is_request() ) {
		return;
	}
	agency_core_client_admin_bootstrap_session();
	$action = sanitize_key( wp_unslash( $_REQUEST['agency_portal_action'] ?? '' ) );
	if ( $action ) {
		if ( 'agency_core_client_admin_login' === $action ) {
			agency_core_client_admin_login();
		}
		if ( 'agency_core_client_admin_logout' === $action ) {
			check_admin_referer( 'agency_core_client_admin_logout' );
			agency_core_client_admin_destroy_session();
			wp_safe_redirect( agency_core_client_admin_url() );
			exit;
		}
		if ( ! agency_core_can_manage_portal() ) {
			wp_safe_redirect( add_query_arg( 'portal_login', 'required', agency_core_client_admin_url() ) );
			exit;
		}
		if ( has_action( 'agency_client_portal_action_' . $action ) ) {
			do_action( 'agency_client_portal_action_' . $action );
			exit;
		}
		wp_die( esc_html__( 'Unknown portal action.', 'agency-core' ), '', array( 'response' => 400 ) );
	}
	agency_core_client_admin_render_document( agency_core_client_admin_shortcode(), __( 'Administration', 'agency-core' ) );
	exit;
}
add_action( 'template_redirect', 'agency_core_client_admin_handle_request', 0 );

function agency_core_client_admin_render_document( $content, $title = '' ) {
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
	$stylesheet = add_query_arg( 'ver', AGENCY_CORE_VERSION, plugins_url( 'assets/client-admin.css', AGENCY_CORE_FILE ) );
	?>
	<!doctype html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="robots" content="noindex,nofollow">
		<title><?php echo esc_html( $title ?: __( 'Administration', 'agency-core' ) ); ?> - <?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
		<link rel="stylesheet" href="<?php echo esc_url( $stylesheet ); ?>">
		<?php do_action( 'agency_client_portal_head' ); ?>
	</head>
	<body class="agency-client-admin-standalone">
		<?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Portal callbacks escape their own markup. ?>
	</body>
	</html>
	<?php
}

function agency_core_client_admin_login() {
	check_admin_referer( 'agency_core_client_admin_login' );
	$user = wp_authenticate(
		sanitize_text_field( wp_unslash( $_POST['log'] ?? '' ) ),
		(string) wp_unslash( $_POST['pwd'] ?? '' )
	);
	if ( is_wp_error( $user ) || ! user_can( $user, 'manage_agency_portal' ) ) {
		wp_safe_redirect( add_query_arg( 'portal_login', 'error', agency_core_client_admin_url() ) );
		exit;
	}
	agency_core_client_admin_create_session( $user->ID, ! empty( $_POST['remember'] ) );
	wp_safe_redirect( agency_core_client_admin_url() );
	exit;
}
add_action( 'admin_post_nopriv_agency_core_client_admin_login', 'agency_core_client_admin_login' );
add_action( 'admin_post_agency_core_client_admin_login', 'agency_core_client_admin_login' );

function agency_core_client_portal_tabs() {
	$tabs = array(
		'overview' => array( 'label' => __( 'Overview', 'agency-core' ), 'callback' => 'agency_core_client_portal_overview', 'order' => 10 ),
		'settings' => array( 'label' => __( 'Website settings', 'agency-core' ), 'callback' => 'agency_core_client_portal_settings', 'order' => 100 ),
	);
	$tabs = apply_filters( 'agency_client_portal_tabs', $tabs );
	uasort( $tabs, static fn( $a, $b ) => ( $a['order'] ?? 50 ) <=> ( $b['order'] ?? 50 ) );
	return $tabs;
}

function agency_core_client_admin_login_markup() {
	ob_start();
	?>
	<main class="agency-client-login"><div class="agency-client-login-card"><span>Agency Site Factory</span><h1><?php esc_html_e( 'Administration', 'agency-core' ); ?></h1><p><?php esc_html_e( 'Sign in to manage your website, bookings and reports.', 'agency-core' ); ?></p><?php if ( isset( $_GET['portal_login'] ) ) : ?><div class="agency-client-alert"><?php esc_html_e( 'Incorrect username or password.', 'agency-core' ); ?></div><?php endif; ?><form method="post" action="<?php echo esc_url( agency_core_client_admin_action_url( 'agency_core_client_admin_login' ) ); ?>"><input type="hidden" name="agency_portal_action" value="agency_core_client_admin_login"><?php wp_nonce_field( 'agency_core_client_admin_login' ); ?><label><span><?php esc_html_e( 'Username', 'agency-core' ); ?></span><input required name="log" autocomplete="username"></label><label><span><?php esc_html_e( 'Password', 'agency-core' ); ?></span><input required type="password" name="pwd" autocomplete="current-password"></label><label class="agency-client-check"><input type="checkbox" name="remember" value="1"> <?php esc_html_e( 'Remember this portal session', 'agency-core' ); ?></label><button><?php esc_html_e( 'Sign in', 'agency-core' ); ?></button><a href="<?php echo esc_url( wp_lostpassword_url( agency_core_client_admin_url() ) ); ?>"><?php esc_html_e( 'Forgot password?', 'agency-core' ); ?></a></form></div></main>
	<?php
	return ob_get_clean();
}

function agency_core_client_admin_shortcode() {
	if ( ! agency_core_can_manage_portal() ) {
		return agency_core_client_admin_login_markup();
	}
	$tabs    = agency_core_client_portal_tabs();
	$current = sanitize_key( wp_unslash( $_GET['portal'] ?? 'overview' ) );
	if ( ! isset( $tabs[ $current ] ) ) {
		$current = 'overview';
	}
	$logout_url = wp_nonce_url( agency_core_client_admin_action_url( 'agency_core_client_admin_logout' ), 'agency_core_client_admin_logout' );
	ob_start();
	?>
	<div class="agency-client-portal"><aside><a class="agency-client-brand" href="<?php echo esc_url( agency_core_client_admin_url() ); ?>"><span>Agency</span><strong><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong></a><nav><?php foreach ( $tabs as $key => $tab ) : ?><a class="<?php echo $key === $current ? 'is-active' : ''; ?>" href="<?php echo esc_url( agency_core_client_admin_url( $key ) ); ?>"><?php echo esc_html( $tab['label'] ); ?></a><?php endforeach; ?></nav><a class="agency-client-logout" href="<?php echo esc_url( $logout_url ); ?>"><?php esc_html_e( 'Sign out', 'agency-core' ); ?></a></aside><main><header><div><small><?php esc_html_e( 'Website administration', 'agency-core' ); ?></small><h1><?php echo esc_html( $tabs[ $current ]['label'] ); ?></h1></div><a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View website', 'agency-core' ); ?></a></header><section class="agency-client-content"><?php call_user_func( $tabs[ $current ]['callback'] ); ?></section></main></div>
	<?php
	return ob_get_clean();
}
add_shortcode( 'agency_client_admin', 'agency_core_client_admin_shortcode' );

function agency_core_client_portal_overview() {
	$tabs = agency_core_client_portal_tabs();
	?><div class="agency-client-welcome"><h2><?php printf( esc_html__( 'Welcome, %s', 'agency-core' ), esc_html( wp_get_current_user()->display_name ) ); ?></h2><p><?php esc_html_e( 'Choose a module to manage the website. New active modules can add their own pages to this standalone portal.', 'agency-core' ); ?></p></div><div class="agency-client-module-grid"><?php foreach ( $tabs as $key => $tab ) : if ( in_array( $key, array( 'overview', 'settings' ), true ) ) continue; ?><a href="<?php echo esc_url( agency_core_client_admin_url( $key ) ); ?>"><strong><?php echo esc_html( $tab['label'] ); ?></strong><span><?php esc_html_e( 'Open module', 'agency-core' ); ?> →</span></a><?php endforeach; ?></div><?php
}

function agency_core_client_portal_settings() {
	$s = (array) get_option( 'agency_core_settings', array() );
	$fields = array(
		'brand_name' => array( __( 'Brand name', 'agency-core' ), 'text' ),
		'phone' => array( __( 'Phone', 'agency-core' ), 'text' ),
		'email' => array( __( 'Email', 'agency-core' ), 'email' ),
		'address' => array( __( 'Address', 'agency-core' ), 'text' ),
		'booking_url' => array( __( 'Booking URL', 'agency-core' ), 'url' ),
		'primary_color' => array( __( 'Primary color', 'agency-core' ), 'color' ),
		'secondary_color' => array( __( 'Secondary color', 'agency-core' ), 'color' ),
		'background_color' => array( __( 'Background color', 'agency-core' ), 'color' ),
	);
	?><div class="agency-client-panel"><h2><?php esc_html_e( 'Website identity and contact details', 'agency-core' ); ?></h2><form class="agency-client-settings-form" method="post" action="<?php echo esc_url( agency_core_client_admin_action_url( 'agency_core_client_settings', 'settings' ) ); ?>"><input type="hidden" name="agency_portal_action" value="agency_core_client_settings"><?php wp_nonce_field( 'agency_core_client_settings' ); ?><?php foreach ( $fields as $key => $field ) : ?><label><span><?php echo esc_html( $field[0] ); ?></span><input type="<?php echo esc_attr( $field[1] ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $s[ $key ] ?? '' ); ?>"></label><?php endforeach; ?><button class="agency-client-button"><?php esc_html_e( 'Save website settings', 'agency-core' ); ?></button></form></div><div class="agency-client-panel agency-client-account-panel"><h2><?php esc_html_e( 'Portal session', 'agency-core' ); ?></h2><p><?php esc_html_e( 'This portal uses a dedicated access-token cookie and does not create a normal WordPress login session.', 'agency-core' ); ?></p><a class="agency-client-button" href="<?php echo esc_url( wp_lostpassword_url( agency_core_client_admin_url() ) ); ?>"><?php esc_html_e( 'Change or reset password', 'agency-core' ); ?></a></div><?php
}

function agency_core_client_settings_save() {
	if ( ! agency_core_can_manage_portal() ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ), 403 );
	}
	check_admin_referer( 'agency_core_client_settings' );
	$old = (array) get_option( 'agency_core_settings', array() );
	foreach ( array( 'brand_name', 'phone', 'address' ) as $key ) {
		$old[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) );
	}
	$old['email'] = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	$old['booking_url'] = esc_url_raw( wp_unslash( $_POST['booking_url'] ?? '' ) );
	foreach ( array( 'primary_color', 'secondary_color', 'background_color' ) as $key ) {
		$old[ $key ] = sanitize_hex_color( wp_unslash( $_POST[ $key ] ?? '' ) ) ?: '';
	}
	update_option( 'agency_core_settings', $old, false );
	wp_safe_redirect( add_query_arg( 'updated', 1, agency_core_client_admin_url( 'settings' ) ) );
	exit;
}
add_action( 'admin_post_agency_core_client_settings', 'agency_core_client_settings_save' );
add_action( 'agency_client_portal_action_agency_core_client_settings', 'agency_core_client_settings_save' );

function agency_core_record_event( $type, $name, $path = '', $title = '', $meta = array(), $session = '' ) {
	global $wpdb;
	return $wpdb->insert(
		agency_core_client_admin_table(),
		array(
			'event_type' => sanitize_key( $type ), 'event_name' => sanitize_key( $name ),
			'page_path' => substr( sanitize_text_field( $path ), 0, 255 ), 'page_title' => substr( sanitize_text_field( $title ), 0, 255 ),
			'session_hash' => preg_match( '/^[a-f0-9]{64}$/', $session ) ? $session : '',
			'meta_json' => wp_json_encode( $meta ), 'created_at' => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
	);
}

function agency_core_event_rest() {
	register_rest_route(
		'agency/v1',
		'/event',
		array(
			'methods' => WP_REST_Server::CREATABLE, 'permission_callback' => '__return_true',
			'callback' => static function ( WP_REST_Request $request ) {
				if ( empty( $_COOKIE['agency_analytics_consent'] ) || 'granted' !== $_COOKIE['agency_analytics_consent'] ) {
					return new WP_Error( 'agency_event_consent', __( 'Analytics consent is required.', 'agency-core' ), array( 'status' => 403 ) );
				}
				if ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'first_party_event', 120, HOUR_IN_SECONDS ) ) {
					return new WP_Error( 'agency_event_rate', __( 'Too many events.', 'agency-core' ), array( 'status' => 429 ) );
				}
				$type = sanitize_key( $request->get_param( 'type' ) );
				$name = sanitize_key( $request->get_param( 'name' ) );
				if ( ! in_array( $type, array( 'pageview', 'interaction' ), true ) || ! $name ) {
					return new WP_Error( 'agency_event_invalid', __( 'Invalid event.', 'agency-core' ), array( 'status' => 400 ) );
				}
				agency_core_record_event( $type, $name, $request->get_param( 'path' ), $request->get_param( 'title' ), array(), hash( 'sha256', sanitize_text_field( $request->get_param( 'session' ) ) . wp_salt( 'nonce' ) ) );
				return rest_ensure_response( array( 'stored' => true ) );
			},
		)
	);
}
add_action( 'rest_api_init', 'agency_core_event_rest' );

function agency_core_cleanup_events() {
	global $wpdb;
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - 395 * DAY_IN_SECONDS );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . agency_core_client_admin_table() . ' WHERE created_at < %s', $cutoff ) );
}
add_action( 'agency_core_cleanup_events', 'agency_core_cleanup_events' );

function agency_core_client_admin_assets() {
	if ( agency_core_client_admin_is_request() ) {
		return;
	}
	wp_enqueue_script( 'agency-first-party-analytics', plugins_url( 'assets/first-party-analytics.js', AGENCY_CORE_FILE ), array(), AGENCY_CORE_VERSION, true );
	wp_localize_script( 'agency-first-party-analytics', 'agencyFirstPartyAnalytics', array( 'endpoint' => rest_url( 'agency/v1/event' ) ) );
}
add_action( 'wp_enqueue_scripts', 'agency_core_client_admin_assets' );

function agency_core_client_admin_wp_page() {
	add_submenu_page( 'agency-core-settings', __( 'Client Admin', 'agency-core' ), __( 'Client Admin', 'agency-core' ), 'manage_options', 'agency-core-client-admin', 'agency_core_client_admin_wp_render' );
}
add_action( 'admin_menu', 'agency_core_client_admin_wp_page', 22 );

function agency_core_client_admin_wp_render() {
	$credentials = get_transient( 'agency_core_initial_portal_credentials' );
	?><div class="wrap"><h1><?php esc_html_e( 'Client Admin Portal', 'agency-core' ); ?></h1><?php if ( $credentials ) : ?><div class="notice notice-warning"><p><strong><?php esc_html_e( 'One-time initial credentials:', 'agency-core' ); ?></strong> <code><?php echo esc_html( $credentials['username'] ); ?></code> / <code><?php echo esc_html( $credentials['password'] ); ?></code></p><p><?php esc_html_e( 'Copy these now and change the password after first login. This notice expires automatically.', 'agency-core' ); ?></p></div><?php endif; ?><p><?php esc_html_e( 'The client portal is rendered as a standalone /admin/ interface. It is not a WordPress page template and it uses its own agency_portal_session access-token cookie.', 'agency-core' ); ?></p><p><a class="button button-primary" href="<?php echo esc_url( agency_core_client_admin_url() ); ?>" target="_blank"><?php esc_html_e( 'Open /admin/ portal', 'agency-core' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'users.php?role=agency_client_admin' ) ); ?>"><?php esc_html_e( 'Manage portal users', 'agency-core' ); ?></a></p><h2><?php esc_html_e( 'Set portal login', 'agency-core' ); ?></h2><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="agency_core_portal_credentials"><?php wp_nonce_field( 'agency_core_portal_credentials' ); ?><table class="form-table"><tr><th><label for="agency-portal-user"><?php esc_html_e( 'Username', 'agency-core' ); ?></label></th><td><input id="agency-portal-user" name="username" value="agencyadmin" required></td></tr><tr><th><label for="agency-portal-password"><?php esc_html_e( 'New password', 'agency-core' ); ?></label></th><td><input id="agency-portal-password" type="password" name="password" minlength="12"><p class="description"><?php esc_html_e( 'Leave empty to generate a strong random password and show it once.', 'agency-core' ); ?></p></td></tr></table><?php submit_button( __( 'Create or update portal login', 'agency-core' ) ); ?></form><p><?php esc_html_e( 'Additional portal users can be created under Users by assigning the Agency Client Admin role.', 'agency-core' ); ?></p></div><?php
}

function agency_core_portal_credentials_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ), 403 );
	}
	check_admin_referer( 'agency_core_portal_credentials' );
	$username = sanitize_user( wp_unslash( $_POST['username'] ?? '' ), true );
	if ( ! $username ) {
		wp_die( esc_html__( 'A valid username is required.', 'agency-core' ), 400 );
	}
	$password = (string) wp_unslash( $_POST['password'] ?? '' );
	if ( '' !== $password && strlen( $password ) < 12 ) {
		wp_die( esc_html__( 'Use a password with at least 12 characters.', 'agency-core' ), 400 );
	}
	$password = $password ?: wp_generate_password( 20, true, true );
	$user     = get_user_by( 'login', $username );
	if ( $user ) {
		wp_set_password( $password, $user->ID );
		$user = get_user_by( 'id', $user->ID );
		if ( $user && ! user_can( $user, 'manage_options' ) && ! in_array( 'agency_client_admin', (array) $user->roles, true ) ) {
			$user->add_role( 'agency_client_admin' );
		}
	} else {
		$user_id = wp_insert_user( array( 'user_login' => $username, 'user_pass' => $password, 'display_name' => __( 'Site manager', 'agency-core' ), 'role' => 'agency_client_admin' ) );
		if ( is_wp_error( $user_id ) ) {
			wp_die( esc_html( $user_id->get_error_message() ), 400 );
		}
	}
	set_transient( 'agency_core_initial_portal_credentials', array( 'username' => $username, 'password' => $password ), HOUR_IN_SECONDS );
	wp_safe_redirect( admin_url( 'admin.php?page=agency-core-client-admin&updated=1' ) );
	exit;
}
add_action( 'admin_post_agency_core_portal_credentials', 'agency_core_portal_credentials_save' );

function agency_core_client_admin_redirect_wpadmin() {
	global $pagenow;
	if ( is_admin() && ! wp_doing_ajax() && 'admin-post.php' !== $pagenow && current_user_can( 'manage_agency_portal' ) && ! current_user_can( 'manage_options' ) ) {
		wp_safe_redirect( agency_core_client_admin_url() );
		exit;
	}
}
add_action( 'admin_init', 'agency_core_client_admin_redirect_wpadmin', 1 );
