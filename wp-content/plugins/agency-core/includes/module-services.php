<?php
/**
 * Shared production services for Agency feature modules.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_module_option( $module, $key = '', $default = '' ) {
	$settings = get_option( 'agency_module_' . sanitize_key( $module ) . '_settings', array() );
	return $key ? ( $settings[ $key ] ?? $default ) : $settings;
}

function agency_core_module_update_settings( $module, $settings ) {
	return update_option( 'agency_module_' . sanitize_key( $module ) . '_settings', (array) $settings, false );
}

function agency_core_module_migrate( $module, $version, $callback ) {
	$key       = 'agency_module_' . sanitize_key( $module ) . '_db_version';
	$installed = (string) get_option( $key, '0' );
	if ( version_compare( $installed, $version, '>=' ) ) {
		return;
	}
	call_user_func( $callback, $installed, $version );
	update_option( $key, $version, false );
	agency_core_audit_log( $module, 'migration', array( 'from' => $installed, 'to' => $version ) );
}

function agency_core_services_migrate() {
	if ( version_compare( (string) get_option( 'agency_module_services_db_version', '0' ), '1.0.0', '>=' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = $wpdb->prefix . 'agency_activity_log';
	$charset = $wpdb->get_charset_collate();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		module varchar(40) NOT NULL,
		event varchar(80) NOT NULL,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		context longtext NULL,
		created_at datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY module_event (module,event),
		KEY created_at (created_at)
	) {$charset};" );
	update_option( 'agency_module_services_db_version', '1.0.0', false );
}
add_action( 'admin_init', 'agency_core_services_migrate' );
add_action( 'init', 'agency_core_services_migrate', 5 );

function agency_core_audit_log( $module, $event, $context = array() ) {
	global $wpdb;
	$table = $wpdb->prefix . 'agency_activity_log';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return false;
	}
	$context = (array) $context;
	foreach ( array( 'password', 'token', 'secret', 'api_key' ) as $secret ) {
		unset( $context[ $secret ] );
	}
	return false !== $wpdb->insert(
		$table,
		array(
			'module'     => sanitize_key( $module ),
			'event'      => sanitize_key( $event ),
			'user_id'    => get_current_user_id(),
			'context'    => wp_json_encode( $context ),
			'created_at' => current_time( 'mysql', true ),
		),
		array( '%s', '%s', '%d', '%s', '%s' )
	);
}

function agency_core_rate_limit( $bucket, $limit = 5, $window = 900 ) {
	$ip  = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
	$key = 'agency_rate_' . md5( sanitize_key( $bucket ) . '|' . $ip );
	$hit = (array) get_transient( $key );
	if ( empty( $hit['started'] ) || time() - (int) $hit['started'] > $window ) {
		$hit = array( 'started' => time(), 'count' => 0 );
	}
	++$hit['count'];
	set_transient( $key, $hit, $window );
	return $hit['count'] <= $limit;
}

function agency_core_secret_key() {
	return hash( 'sha256', wp_salt( 'auth' ), true );
}

function agency_core_encrypt_secret( $value ) {
	if ( '' === $value ) {
		return '';
	}
	$key = agency_core_secret_key();
	if ( function_exists( 'sodium_crypto_secretbox' ) ) {
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		return 'sodium:' . base64_encode( $nonce . sodium_crypto_secretbox( $value, $nonce, $key ) );
	}
	$iv     = random_bytes( 16 );
	$cipher = openssl_encrypt( $value, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
	return 'openssl:' . base64_encode( $iv . hash_hmac( 'sha256', $iv . $cipher, $key, true ) . $cipher );
}

function agency_core_decrypt_secret( $stored ) {
	$key = agency_core_secret_key();
	if ( str_starts_with( $stored, 'sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
		$raw   = base64_decode( substr( $stored, 7 ), true );
		$nonce = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$value = sodium_crypto_secretbox_open( substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), $nonce, $key );
		return false === $value ? '' : $value;
	}
	if ( str_starts_with( $stored, 'openssl:' ) ) {
		$raw    = base64_decode( substr( $stored, 8 ), true );
		$iv     = substr( $raw, 0, 16 );
		$mac    = substr( $raw, 16, 32 );
		$cipher = substr( $raw, 48 );
		if ( ! hash_equals( $mac, hash_hmac( 'sha256', $iv . $cipher, $key, true ) ) ) {
			return '';
		}
		return (string) openssl_decrypt( $cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
	}
	return '';
}

function agency_core_email_settings() {
	return wp_parse_args(
		get_option( 'agency_core_email_settings', array() ),
		array( 'provider' => 'wp_mail', 'sender_name' => get_bloginfo( 'name' ), 'sender_email' => get_option( 'admin_email' ), 'reply_to' => '', 'api_key' => '' )
	);
}

function agency_core_update_email_health( $success, $error = '', $is_test = false, $meta = array() ) {
	$health = wp_parse_args(
		get_option( 'agency_core_email_health', array() ),
		array(
			'test_status'     => 'not-tested',
			'last_status'     => 'not-sent',
			'last_error'      => '',
			'last_tested_at'  => '',
			'last_sent_at'    => '',
			'last_provider'   => '',
			'last_recipient'  => '',
			'last_message_id' => '',
			'last_http_code'  => '',
		)
	);
	$health['last_status']     = $success ? 'success' : 'error';
	$health['last_error']      = $success ? '' : sanitize_text_field( $error );
	$health['last_sent_at']    = gmdate( 'c' );
	$health['last_provider']   = sanitize_key( $meta['provider'] ?? $health['last_provider'] );
	$health['last_recipient']  = sanitize_email( $meta['recipient'] ?? $health['last_recipient'] );
	$health['last_message_id'] = sanitize_text_field( $meta['message_id'] ?? $health['last_message_id'] );
	$health['last_http_code']  = sanitize_text_field( $meta['http_code'] ?? $health['last_http_code'] );
	if ( $is_test ) {
		$health['test_status']    = $success ? 'success' : 'error';
		$health['last_tested_at'] = gmdate( 'c' );
	}
	update_option( 'agency_core_email_health', $health, false );
}

function agency_core_capture_wp_mail_error( $error ) {
	if ( is_wp_error( $error ) ) {
		agency_core_update_email_health( false, $error->get_error_message(), false, array( 'provider' => 'wp_mail' ) );
		agency_core_audit_log( 'email', 'wp_mail_failed', array( 'error' => $error->get_error_message() ) );
	}
}
add_action( 'wp_mail_failed', 'agency_core_capture_wp_mail_error' );

function agency_core_email_send( $to, $subject, $message, $headers = array(), $is_test = false ) {
	$settings = agency_core_email_settings();
	$to       = sanitize_email( $to );
	$provider = $settings['provider'] ?? 'wp_mail';

	if ( ! $to ) {
		agency_core_update_email_health( false, 'Invalid email recipient.', $is_test, array( 'provider' => $provider, 'recipient' => $to ) );
		return new WP_Error( 'agency_email_recipient', __( 'Invalid email recipient.', 'agency-core' ) );
	}

	$subject  = sanitize_text_field( $subject );
	$message  = wp_kses_post( $message );
	$headers[] = 'Content-Type: text/html; charset=UTF-8';

	if ( is_email( $settings['sender_email'] ) ) {
		$headers[] = 'From: ' . sanitize_text_field( $settings['sender_name'] ) . ' <' . sanitize_email( $settings['sender_email'] ) . '>';
	}
	if ( is_email( $settings['reply_to'] ) ) {
		$headers[] = 'Reply-To: ' . sanitize_email( $settings['reply_to'] );
	}

	if ( 'brevo' === $provider ) {
		$key = agency_core_decrypt_secret( (string) $settings['api_key'] );
		if ( ! $key ) {
			agency_core_update_email_health( false, 'Brevo API key is missing or could not be decrypted.', $is_test, array( 'provider' => 'brevo', 'recipient' => $to ) );
			return new WP_Error( 'agency_email_brevo_key_missing', __( 'Brevo API key is missing or invalid.', 'agency-core' ) );
		}

		$response = wp_remote_post(
			'https://api.brevo.com/v3/smtp/email',
			array(
				'timeout' => 20,
				'headers' => array( 'api-key' => $key, 'Content-Type' => 'application/json', 'accept' => 'application/json' ),
				'body'    => wp_json_encode(
					array_filter(
						array(
							'sender'      => array( 'name' => sanitize_text_field( $settings['sender_name'] ), 'email' => sanitize_email( $settings['sender_email'] ) ),
							'to'          => array( array( 'email' => $to ) ),
							'replyTo'     => is_email( $settings['reply_to'] ) ? array( 'email' => sanitize_email( $settings['reply_to'] ) ) : null,
							'subject'     => $subject,
							'htmlContent' => $message,
							'textContent' => wp_strip_all_tags( $message ),
						)
					)
				),
			)
		);
		$http_code = is_wp_error( $response ) ? 0 : absint( wp_remote_retrieve_response_code( $response ) );
		$body      = is_wp_error( $response ) ? '' : (string) wp_remote_retrieve_body( $response );
		$decoded   = $body ? json_decode( $body, true ) : array();

		if ( ! is_wp_error( $response ) && $http_code >= 200 && $http_code < 300 ) {
			$message_id = sanitize_text_field( $decoded['messageId'] ?? '' );
			agency_core_update_email_health( true, '', $is_test, array( 'provider' => 'brevo', 'recipient' => $to, 'message_id' => $message_id, 'http_code' => $http_code ) );
			agency_core_audit_log( 'email', 'sent', array( 'provider' => 'brevo', 'recipient' => $to, 'message_id' => $message_id, 'http_code' => $http_code, 'is_test' => $is_test ? 1 : 0 ) );
			return true;
		}

		$error = is_wp_error( $response ) ? $response->get_error_message() : 'Brevo HTTP ' . $http_code . ': ' . substr( wp_strip_all_tags( $body ), 0, 300 );
		agency_core_update_email_health( false, $error, $is_test, array( 'provider' => 'brevo', 'recipient' => $to, 'http_code' => $http_code ) );
		agency_core_audit_log( 'email', 'brevo_failed', array( 'recipient' => $to, 'http_code' => $http_code, 'error' => $error, 'is_test' => $is_test ? 1 : 0 ) );
		return new WP_Error( 'agency_email_brevo_failed', $error );
	}

	$sent = wp_mail( $to, $subject, $message, $headers );
	agency_core_update_email_health( $sent, $sent ? '' : 'wp_mail delivery failed.', $is_test, array( 'provider' => 'wp_mail', 'recipient' => $to ) );
	return $sent ? true : new WP_Error( 'agency_email_failed', __( 'Email delivery failed.', 'agency-core' ) );
}

function agency_core_render_email_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ) );
	}
	$s      = agency_core_email_settings();
	$health = function_exists( 'agency_core_get_email_health' ) ? agency_core_get_email_health() : array();
	$health = array_merge( (array) get_option( 'agency_core_email_health', array() ), $health );
	?>
	<div class="wrap"><h1><?php esc_html_e( 'Agency Email Service', 'agency-core' ); ?></h1>
	<table class="widefat striped"><tbody>
	<tr><th><?php esc_html_e( 'Provider', 'agency-core' ); ?></th><td><?php echo esc_html( $health['provider'] ?? 'wp_mail' ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Sender configured', 'agency-core' ); ?></th><td><?php echo ! empty( $health['sender_configured'] ) ? esc_html__( 'Yes', 'agency-core' ) : esc_html__( 'No', 'agency-core' ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Brevo API configured', 'agency-core' ); ?></th><td><?php echo ! empty( $health['brevo_configured'] ) ? esc_html__( 'Yes', 'agency-core' ) : esc_html__( 'No', 'agency-core' ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Last test', 'agency-core' ); ?></th><td><?php echo esc_html( ( $health['test_status'] ?? 'not-tested' ) . ' ' . ( $health['last_tested_at'] ?? '' ) ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Last delivery', 'agency-core' ); ?></th><td><?php echo esc_html( ( $health['last_status'] ?? 'not-sent' ) . ' ' . ( $health['last_sent_at'] ?? '' ) ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Last error', 'agency-core' ); ?></th><td><?php echo esc_html( $health['last_error'] ?? '' ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Last provider', 'agency-core' ); ?></th><td><?php echo esc_html( $health['last_provider'] ?? '' ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Last recipient', 'agency-core' ); ?></th><td><?php echo esc_html( $health['last_recipient'] ?? '' ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Last Brevo message ID', 'agency-core' ); ?></th><td><?php echo esc_html( $health['last_message_id'] ?? '' ); ?></td></tr>
	<tr><th><?php esc_html_e( 'Last HTTP code', 'agency-core' ); ?></th><td><?php echo esc_html( $health['last_http_code'] ?? '' ); ?></td></tr>
	</tbody></table>
	<?php if ( isset( $_GET['email_status'] ) ) : ?><div class="notice notice-<?php echo 'success' === sanitize_key( $_GET['email_status'] ) ? 'success' : 'error'; ?> is-dismissible"><p><?php esc_html_e( 'Email operation completed. Check delivery and the activity log.', 'agency-core' ); ?></p></div><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="agency_core_save_email"><?php wp_nonce_field( 'agency_core_email_settings' ); ?>
	<table class="form-table"><tr><th><?php esc_html_e( 'Provider', 'agency-core' ); ?></th><td><select name="provider"><option value="wp_mail" <?php selected( $s['provider'], 'wp_mail' ); ?>>WordPress wp_mail</option><option value="brevo" <?php selected( $s['provider'], 'brevo' ); ?>>Brevo</option></select></td></tr>
	<tr><th><?php esc_html_e( 'Sender name', 'agency-core' ); ?></th><td><input class="regular-text" name="sender_name" value="<?php echo esc_attr( $s['sender_name'] ); ?>"></td></tr>
	<tr><th><?php esc_html_e( 'Sender email', 'agency-core' ); ?></th><td><input class="regular-text" type="email" name="sender_email" value="<?php echo esc_attr( $s['sender_email'] ); ?>"></td></tr>
	<tr><th><?php esc_html_e( 'Reply-to', 'agency-core' ); ?></th><td><input class="regular-text" type="email" name="reply_to" value="<?php echo esc_attr( $s['reply_to'] ); ?>"></td></tr>
	<tr><th><?php esc_html_e( 'Brevo API key', 'agency-core' ); ?></th><td><input class="regular-text" type="password" autocomplete="new-password" name="api_key" placeholder="<?php echo $s['api_key'] ? esc_attr__( 'Stored securely – leave blank to keep', 'agency-core' ) : ''; ?>"></td></tr>
	<tr><th><?php esc_html_e( 'Test recipient', 'agency-core' ); ?></th><td><input class="regular-text" type="email" name="test_email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"></td></tr></table>
	<?php submit_button( __( 'Save settings', 'agency-core' ), 'primary', 'save' ); ?> <?php submit_button( __( 'Save and send test email', 'agency-core' ), 'secondary', 'send_test', false ); ?></form></div>
	<?php
}

function agency_core_handle_email_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ) );
	}
	check_admin_referer( 'agency_core_email_settings' );
	$old = agency_core_email_settings();
	$key = sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) );
	$new = array(
		'provider'     => in_array( $_POST['provider'] ?? '', array( 'wp_mail', 'brevo' ), true ) ? sanitize_key( $_POST['provider'] ) : 'wp_mail',
		'sender_name'  => sanitize_text_field( wp_unslash( $_POST['sender_name'] ?? '' ) ),
		'sender_email' => sanitize_email( wp_unslash( $_POST['sender_email'] ?? '' ) ),
		'reply_to'     => sanitize_email( wp_unslash( $_POST['reply_to'] ?? '' ) ),
		'api_key'      => $key ? agency_core_encrypt_secret( $key ) : $old['api_key'],
	);
	update_option( 'agency_core_email_settings', $new, false );
	$status = 'success';
	if ( isset( $_POST['send_test'] ) ) {
		$result = agency_core_email_send( sanitize_email( $_POST['test_email'] ?? '' ), __( 'Agency email test', 'agency-core' ), __( '<p>The Agency email service is working.</p>', 'agency-core' ), array(), true );
		$status = is_wp_error( $result ) ? 'error' : 'success';
	}
	if ( function_exists( 'agency_core_sync_manifest_modules' ) ) {
		agency_core_sync_manifest_modules();
	}
	wp_safe_redirect( add_query_arg( 'email_status', $status, admin_url( 'admin.php?page=agency-core-email' ) ) );
	exit;
}
add_action( 'admin_post_agency_core_save_email', 'agency_core_handle_email_settings' );

function agency_core_add_email_menu() {
	add_submenu_page( 'agency-core-settings', __( 'Email Service', 'agency-core' ), __( 'Email Service', 'agency-core' ), 'manage_options', 'agency-core-email', 'agency_core_render_email_settings' );
	add_submenu_page( 'agency-core-settings', __( 'Activity Log', 'agency-core' ), __( 'Activity Log', 'agency-core' ), 'manage_options', 'agency-core-activity', 'agency_core_render_activity_log' );
}
add_action( 'admin_menu', 'agency_core_add_email_menu', 22 );

function agency_core_render_activity_log() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ) ); }
	global $wpdb;
	$module = sanitize_key( $_GET['module'] ?? '' );
	$table  = $wpdb->prefix . 'agency_activity_log';
	$rows   = $module ? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE module=%s ORDER BY id DESC LIMIT 200", $module ) ) : $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 200" );
	?><div class="wrap"><h1><?php esc_html_e( 'Agency Activity Log', 'agency-core' ); ?></h1><form method="get"><input type="hidden" name="page" value="agency-core-activity"><input name="module" value="<?php echo esc_attr( $module ); ?>" placeholder="module"><button class="button"><?php esc_html_e( 'Filter', 'agency-core' ); ?></button></form><table class="widefat striped"><thead><tr><th>Date</th><th>Module</th><th>Event</th><th>User</th><th>Context</th></tr></thead><tbody><?php if ( ! $rows ) : ?><tr><td colspan="5"><?php esc_html_e( 'No activity recorded yet.', 'agency-core' ); ?></td></tr><?php endif; foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row->created_at ); ?></td><td><?php echo esc_html( $row->module ); ?></td><td><?php echo esc_html( $row->event ); ?></td><td><?php echo absint( $row->user_id ); ?></td><td><code><?php echo esc_html( $row->context ); ?></code></td></tr><?php endforeach; ?></tbody></table></div><?php
}

function agency_core_register_secure_rest_route( $namespace, $route, $methods, $callback, $capability = 'read' ) {
	register_rest_route(
		$namespace,
		$route,
		array(
			'methods'             => $methods,
			'callback'            => $callback,
			'permission_callback' => static fn() => current_user_can( $capability ),
		)
	);
}
