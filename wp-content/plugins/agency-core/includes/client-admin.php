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

function agency_core_client_portal_can_manage_users() {
	agency_core_client_admin_bootstrap_session();

	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}

	$user = wp_get_current_user();

	return $user && in_array( 'agency_client_admin', (array) $user->roles, true );
}

function agency_core_client_portal_tabs() {
	$tabs = array(
		'overview' => array(
			'label'    => __( 'Overview', 'agency-core' ),
			'callback' => 'agency_core_client_portal_overview',
			'order'    => 10,
		),
		'settings' => array(
			'label'    => __( 'Website settings', 'agency-core' ),
			'callback' => 'agency_core_client_portal_settings',
			'order'    => 100,
		),
	);

	if ( agency_core_client_portal_can_manage_users() ) {
		$tabs['users'] = array(
			'label'    => __( 'Users', 'agency-core' ),
			'callback' => 'agency_core_client_portal_users',
			'order'    => 90,
		);
	}

	$tabs = apply_filters( 'agency_client_portal_tabs', $tabs );

	uasort(
		$tabs,
		static fn( $a, $b ) => ( $a['order'] ?? 50 ) <=> ( $b['order'] ?? 50 )
	);

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

function agency_core_client_portal_user_roles() {
	$roles = array(
		'agency_client_admin' => __( 'Client admin', 'agency-core' ),
	);

	if ( get_role( 'agency_booking_manager' ) ) {
		$roles['agency_booking_manager'] = __( 'Booking manager', 'agency-core' );
	}

	if ( get_role( 'agency_customer' ) ) {
		$roles['agency_customer'] = __( 'Customer', 'agency-core' );
	}

	return $roles;
}

function agency_core_client_portal_user_status( $user ) {
	$is_customer = in_array( 'agency_customer', (array) $user->roles, true );
	$blocked     = get_user_meta( $user->ID, '_agency_auth_blocked', true );

	if ( $blocked ) {
		return __( 'Blocked', 'agency-core' );
	}

	if ( $is_customer ) {
		$verified = function_exists( 'agency_auth_is_verified' )
			? agency_auth_is_verified( $user->ID )
			: '1' === get_user_meta( $user->ID, '_agency_email_verified', true );

		return $verified ? __( 'Email verified', 'agency-core' ) : __( 'Pending email verification', 'agency-core' );
	}

	return __( 'Active', 'agency-core' );
}

function agency_core_client_portal_users() {
	if ( ! agency_core_client_portal_can_manage_users() ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ), 403 );
	}

	$roles = agency_core_client_portal_user_roles();
	$users = get_users(
		array(
			'role__in' => array_keys( $roles ),
			'number'   => 300,
			'orderby'  => 'registered',
			'order'    => 'DESC',
		)
	);

	$action_url = agency_core_client_admin_action_url( 'agency_core_portal_user_save', 'users' );
	?>
	<div class="agency-client-panel agency-client-users-panel">
		<h2><?php esc_html_e( 'Create portal user', 'agency-core' ); ?></h2>
		<p><?php esc_html_e( 'Use this for client admins, booking managers or customer accounts that should be visible in the client portal.', 'agency-core' ); ?></p>

		<form class="agency-client-user-create-form" method="post" action="<?php echo esc_url( $action_url ); ?>">
			<input type="hidden" name="agency_portal_action" value="agency_core_portal_user_save">
			<input type="hidden" name="user_save_mode" value="create">
			<?php wp_nonce_field( 'agency_core_portal_user_save' ); ?>

			<label>
				<span><?php esc_html_e( 'Name', 'agency-core' ); ?></span>
				<input required name="display_name" autocomplete="name">
			</label>

			<label>
				<span><?php esc_html_e( 'Email', 'agency-core' ); ?></span>
				<input required type="email" name="email" autocomplete="email">
			</label>

			<label>
				<span><?php esc_html_e( 'Role', 'agency-core' ); ?></span>
				<select name="role">
					<?php foreach ( $roles as $role_key => $role_label ) : ?>
						<option value="<?php echo esc_attr( $role_key ); ?>"><?php echo esc_html( $role_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>

			<label>
				<span><?php esc_html_e( 'Password', 'agency-core' ); ?></span>
				<input type="password" name="password" minlength="12" autocomplete="new-password">
				<small><?php esc_html_e( 'Leave empty to generate a strong password and send a reset email.', 'agency-core' ); ?></small>
			</label>

			<label class="agency-client-check">
				<input type="checkbox" name="send_invite" value="1" checked>
				<?php esc_html_e( 'Send password setup / reset email', 'agency-core' ); ?>
			</label>

			<button class="agency-client-button"><?php esc_html_e( 'Save user', 'agency-core' ); ?></button>
		</form>
	</div>

	<div class="agency-client-panel agency-client-users-panel">
		<h2><?php esc_html_e( 'Users', 'agency-core' ); ?></h2>

		<div class="agency-client-users-table">
			<table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'User', 'agency-core' ); ?></th>
						<th><?php esc_html_e( 'Role', 'agency-core' ); ?></th>
						<th><?php esc_html_e( 'Email status', 'agency-core' ); ?></th>
						<th><?php esc_html_e( 'Registered', 'agency-core' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'agency-core' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $users ) : ?>
						<tr>
							<td colspan="5"><?php esc_html_e( 'No users found.', 'agency-core' ); ?></td>
						</tr>
					<?php endif; ?>

					<?php foreach ( $users as $user ) : ?>
						<?php
						$user_roles  = array_intersect_key( $roles, array_flip( (array) $user->roles ) );
						$is_customer = in_array( 'agency_customer', (array) $user->roles, true );
						$verified_at = get_user_meta( $user->ID, '_agency_email_verified_at', true );
						$blocked     = get_user_meta( $user->ID, '_agency_auth_blocked', true );
						$status      = agency_core_client_portal_user_status( $user );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $user->display_name ?: $user->user_login ); ?></strong><br>
								<a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ?: '—' ); ?></a>
							</td>
							<td><?php echo esc_html( implode( ', ', $user_roles ?: $user->roles ) ); ?></td>
							<td>
								<span class="agency-client-user-status <?php echo $blocked ? 'is-blocked' : ( $is_customer && ! $verified_at ? 'is-pending' : 'is-ok' ); ?>">
									<?php echo esc_html( $status ); ?>
								</span>
								<?php if ( $verified_at ) : ?>
									<br><small><?php echo esc_html( get_date_from_gmt( $verified_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( get_date_from_gmt( $user->user_registered, get_option( 'date_format' ) ) ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="agency-client-user-actions">
									<input type="hidden" name="agency_portal_action" value="agency_core_portal_user_save">
									<input type="hidden" name="user_save_mode" value="action">
									<input type="hidden" name="user_id" value="<?php echo absint( $user->ID ); ?>">
									<?php wp_nonce_field( 'agency_core_portal_user_save' ); ?>

									<?php if ( $is_customer ) : ?>
										<?php if ( function_exists( 'agency_auth_is_verified' ) && ! agency_auth_is_verified( $user->ID ) ) : ?>
											<button name="user_action" value="resend"><?php esc_html_e( 'Resend verification', 'agency-core' ); ?></button>
											<button name="user_action" value="verify"><?php esc_html_e( 'Mark verified', 'agency-core' ); ?></button>
										<?php else : ?>
											<button name="user_action" value="unverify"><?php esc_html_e( 'Mark unverified', 'agency-core' ); ?></button>
										<?php endif; ?>

										<button name="user_action" value="<?php echo $blocked ? 'unblock' : 'block'; ?>">
											<?php echo $blocked ? esc_html__( 'Unblock', 'agency-core' ) : esc_html__( 'Block', 'agency-core' ); ?>
										</button>
									<?php endif; ?>

									<button name="user_action" value="reset"><?php esc_html_e( 'Send password reset', 'agency-core' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php
}

function agency_core_client_portal_user_save() {
	if ( ! agency_core_client_portal_can_manage_users() ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ), 403 );
	}

	check_admin_referer( 'agency_core_portal_user_save' );

	$mode  = sanitize_key( wp_unslash( $_POST['user_save_mode'] ?? '' ) );
	$roles = agency_core_client_portal_user_roles();

	if ( 'create' === $mode ) {
		$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
		$name  = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
		$role  = sanitize_key( wp_unslash( $_POST['role'] ?? '' ) );
		$pass  = (string) wp_unslash( $_POST['password'] ?? '' );

		if ( ! is_email( $email ) || ! isset( $roles[ $role ] ) ) {
			wp_safe_redirect( add_query_arg( 'users', 'invalid', agency_core_client_admin_url( 'users' ) ) );
			exit;
		}

		if ( '' !== $pass && strlen( $pass ) < 12 ) {
			wp_safe_redirect( add_query_arg( 'users', 'weak-password', agency_core_client_admin_url( 'users' ) ) );
			exit;
		}

		$user_id = email_exists( $email );

		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );
			$user->add_role( $role );

			wp_update_user(
				array(
					'ID'           => $user_id,
					'display_name' => $name ?: $user->display_name,
				)
			);
		} else {
			$username = sanitize_user( strstr( $email, '@', true ), true ) ?: 'portal-user';

			for ( $index = 2; username_exists( $username ); $index++ ) {
				$username = sanitize_user( strstr( $email, '@', true ), true ) . $index;
			}

			$user_id = wp_insert_user(
				array(
					'user_login'   => $username,
					'user_email'   => $email,
					'display_name' => $name ?: $email,
					'user_pass'    => $pass ?: wp_generate_password( 24, true, true ),
					'role'         => $role,
				)
			);

			if ( is_wp_error( $user_id ) ) {
				wp_safe_redirect( add_query_arg( 'users', 'error', agency_core_client_admin_url( 'users' ) ) );
				exit;
			}
		}

		if ( 'agency_customer' === $role ) {
			update_user_meta( $user_id, '_agency_email_verified', '0' );
		}

		if ( ! empty( $_POST['send_invite'] ) ) {
			$user = get_user_by( 'id', $user_id );

			if ( $user ) {
				retrieve_password( $user->user_login );
			}
		}

		if ( function_exists( 'agency_core_audit_log' ) ) {
			agency_core_audit_log(
				'portal',
				'user_saved',
				array(
					'user_id' => absint( $user_id ),
					'role'    => $role,
				)
			);
		}

		wp_safe_redirect( add_query_arg( 'users', 'saved', agency_core_client_admin_url( 'users' ) ) );
		exit;
	}

	$user_id = absint( $_POST['user_id'] ?? 0 );
	$action  = sanitize_key( wp_unslash( $_POST['user_action'] ?? '' ) );
	$user    = get_userdata( $user_id );

	if ( ! $user ) {
		wp_safe_redirect( add_query_arg( 'users', 'missing', agency_core_client_admin_url( 'users' ) ) );
		exit;
	}

	if ( 'resend' === $action && function_exists( 'agency_auth_send_verification' ) ) {
		agency_auth_send_verification( $user_id );
	} elseif ( 'verify' === $action ) {
		update_user_meta( $user_id, '_agency_email_verified', '1' );
		update_user_meta( $user_id, '_agency_email_verified_at', current_time( 'mysql', true ) );
		delete_user_meta( $user_id, '_agency_verify_hash' );
		delete_user_meta( $user_id, '_agency_verify_expires' );
	} elseif ( 'unverify' === $action ) {
		update_user_meta( $user_id, '_agency_email_verified', '0' );
		delete_user_meta( $user_id, '_agency_email_verified_at' );
	} elseif ( 'block' === $action ) {
		update_user_meta( $user_id, '_agency_auth_blocked', '1' );
	} elseif ( 'unblock' === $action ) {
		delete_user_meta( $user_id, '_agency_auth_blocked' );
	} elseif ( 'reset' === $action ) {
		retrieve_password( $user->user_login );
	}

	if ( function_exists( 'agency_core_audit_log' ) ) {
		agency_core_audit_log(
			'portal',
			'user_action',
			array(
				'user_id' => $user_id,
				'action'  => $action,
			)
		);
	}

	wp_safe_redirect( add_query_arg( 'users', 'updated', agency_core_client_admin_url( 'users' ) ) );
	exit;
}

add_action( 'agency_client_portal_action_agency_core_portal_user_save', 'agency_core_client_portal_user_save' );

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
