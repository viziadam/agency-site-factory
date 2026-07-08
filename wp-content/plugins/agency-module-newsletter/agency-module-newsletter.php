<?php
/**
 * Plugin Name: Agency Module: Newsletter
 * Description: Consent-based newsletter subscriptions with double opt-in and export.
 * Version: 1.12.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Agency Starter
 * Text Domain: agency-module-newsletter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENCY_NEWSLETTER_VERSION', '1.12.0' );

function agency_newsletter_table() {
	global $wpdb;
	return $wpdb->prefix . 'agency_newsletter_subscribers';
}

function agency_newsletter_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table = agency_newsletter_table();
	dbDelta( "CREATE TABLE {$table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		email varchar(190) NOT NULL,
		name varchar(190) NOT NULL DEFAULT '',
		status varchar(20) NOT NULL DEFAULT 'pending',
		consent_text text NULL,
		confirm_hash varchar(255) NOT NULL DEFAULT '',
		created_at datetime NOT NULL,
		confirmed_at datetime NULL,
		unsubscribed_at datetime NULL,
		PRIMARY KEY (id),
		UNIQUE KEY email (email),
		KEY status (status)
	) " . $wpdb->get_charset_collate() . ';' );
	update_option( 'agency_module_newsletter_db_version', '1.0.0', false );
}
register_activation_hook( __FILE__, 'agency_newsletter_install' );
add_action( 'init', static function () { if ( '1.0.0' !== get_option( 'agency_module_newsletter_db_version' ) ) { agency_newsletter_install(); } }, 5 );

function agency_newsletter_form() {
	$settings = wp_parse_args( (array) get_option( 'agency_newsletter_settings', array() ), array( 'title' => __( 'Useful ideas, without spam', 'agency-module-newsletter' ), 'consent' => __( 'I consent to receiving newsletters and understand that I can unsubscribe at any time.', 'agency-module-newsletter' ) ) );
	ob_start();
	?>
	<section class="agency-newsletter"><div><span><?php esc_html_e( 'Newsletter', 'agency-module-newsletter' ); ?></span><h2><?php echo esc_html( $settings['title'] ); ?></h2></div>
	<?php if ( isset( $_GET['newsletter'] ) ) : ?><p class="agency-newsletter-notice"><?php echo 'confirmed' === $_GET['newsletter'] ? esc_html__( 'Subscription confirmed. Thank you!', 'agency-module-newsletter' ) : esc_html__( 'Check your inbox to confirm the subscription.', 'agency-module-newsletter' ); ?></p><?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="agency_newsletter_subscribe"><?php wp_nonce_field( 'agency_newsletter_subscribe' ); ?><label><span><?php esc_html_e( 'Name', 'agency-module-newsletter' ); ?></span><input name="name" autocomplete="name"></label><label><span><?php esc_html_e( 'Email', 'agency-module-newsletter' ); ?></span><input required type="email" name="email" autocomplete="email"></label><label class="agency-newsletter-consent"><input required type="checkbox" name="consent" value="1"><span><?php echo esc_html( $settings['consent'] ); ?></span></label><button><?php esc_html_e( 'Subscribe', 'agency-module-newsletter' ); ?></button></form></section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'agency_newsletter', 'agency_newsletter_form' );

function agency_newsletter_subscribe() {
	check_admin_referer( 'agency_newsletter_subscribe' );
	$redirect = wp_get_referer() ?: home_url( '/' );
	$email    = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
	if ( ! is_email( $email ) || empty( $_POST['consent'] ) || ( function_exists( 'agency_core_rate_limit' ) && ! agency_core_rate_limit( 'newsletter_subscribe', 5, HOUR_IN_SECONDS ) ) ) {
		wp_safe_redirect( add_query_arg( 'newsletter', 'error', $redirect ) ); exit;
	}
	global $wpdb;
	$token    = bin2hex( random_bytes( 24 ) );
	$settings = wp_parse_args( (array) get_option( 'agency_newsletter_settings', array() ), array( 'consent' => '' ) );
	$data     = array( 'email' => $email, 'name' => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ), 'status' => 'pending', 'consent_text' => $settings['consent'], 'confirm_hash' => wp_hash_password( $token ), 'created_at' => current_time( 'mysql', true ), 'confirmed_at' => null, 'unsubscribed_at' => null );
	$existing = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . agency_newsletter_table() . ' WHERE email=%s', $email ) );
	$existing ? $wpdb->update( agency_newsletter_table(), $data, array( 'id' => $existing ) ) : $wpdb->insert( agency_newsletter_table(), $data );
	$id  = $existing ?: $wpdb->insert_id;
	$url = add_query_arg( array( 'agency_newsletter_confirm' => $id, 'token' => rawurlencode( $token ) ), home_url( '/' ) );
	$unsubscribe = add_query_arg( array( 'agency_newsletter_unsubscribe' => $id, 'token' => hash_hmac( 'sha256', $id . '|' . $email, wp_salt( 'nonce' ) ) ), home_url( '/' ) );
	$body = '<p>' . esc_html__( 'Confirm your newsletter subscription:', 'agency-module-newsletter' ) . '</p><p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Confirm subscription', 'agency-module-newsletter' ) . '</a></p><p><a href="' . esc_url( $unsubscribe ) . '">' . esc_html__( 'Cancel this request', 'agency-module-newsletter' ) . '</a></p>';
	function_exists( 'agency_core_email_send' ) ? agency_core_email_send( $email, __( 'Confirm newsletter subscription', 'agency-module-newsletter' ), $body ) : wp_mail( $email, __( 'Confirm newsletter subscription', 'agency-module-newsletter' ), $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	wp_safe_redirect( add_query_arg( 'newsletter', 'pending', $redirect ) ); exit;
}
add_action( 'admin_post_nopriv_agency_newsletter_subscribe', 'agency_newsletter_subscribe' );
add_action( 'admin_post_agency_newsletter_subscribe', 'agency_newsletter_subscribe' );

function agency_newsletter_confirm() {
	if ( empty( $_GET['agency_newsletter_confirm'] ) || empty( $_GET['token'] ) ) return;
	global $wpdb;
	$id  = absint( $_GET['agency_newsletter_confirm'] );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . agency_newsletter_table() . ' WHERE id=%d', $id ) );
	if ( ! $row || ! wp_check_password( sanitize_text_field( wp_unslash( $_GET['token'] ) ), $row->confirm_hash ) ) wp_die( esc_html__( 'Invalid or expired confirmation link.', 'agency-module-newsletter' ), 403 );
	$wpdb->update( agency_newsletter_table(), array( 'status' => 'active', 'confirm_hash' => '', 'confirmed_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
	wp_safe_redirect( add_query_arg( 'newsletter', 'confirmed', home_url( '/' ) ) ); exit;
}
add_action( 'template_redirect', 'agency_newsletter_confirm' );

function agency_newsletter_unsubscribe() {
	if ( empty( $_GET['agency_newsletter_unsubscribe'] ) || empty( $_GET['token'] ) ) return;
	global $wpdb;
	$id  = absint( $_GET['agency_newsletter_unsubscribe'] );
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . agency_newsletter_table() . ' WHERE id=%d', $id ) );
	$expected = $row ? hash_hmac( 'sha256', $id . '|' . $row->email, wp_salt( 'nonce' ) ) : '';
	if ( ! $row || ! hash_equals( $expected, sanitize_text_field( wp_unslash( $_GET['token'] ) ) ) ) wp_die( esc_html__( 'Invalid unsubscribe link.', 'agency-module-newsletter' ), 403 );
	$wpdb->update( agency_newsletter_table(), array( 'status' => 'unsubscribed', 'unsubscribed_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
	wp_safe_redirect( add_query_arg( 'newsletter', 'unsubscribed', home_url( '/' ) ) ); exit;
}
add_action( 'template_redirect', 'agency_newsletter_unsubscribe' );

add_action( 'wp_enqueue_scripts', static function () { wp_enqueue_style( 'agency-newsletter', plugins_url( 'newsletter.css', __FILE__ ), array(), AGENCY_NEWSLETTER_VERSION ); } );
add_action( 'admin_menu', static function () { add_submenu_page( 'agency-core-settings', __( 'Newsletter', 'agency-module-newsletter' ), __( 'Newsletter', 'agency-module-newsletter' ), 'manage_options', 'agency-module-newsletter', 'agency_newsletter_admin' ); }, 30 );
function agency_newsletter_admin() {
	global $wpdb;
	$rows = $wpdb->get_results( 'SELECT * FROM ' . agency_newsletter_table() . ' ORDER BY created_at DESC LIMIT 500' );
	?><div class="wrap"><h1><?php esc_html_e( 'Newsletter subscribers', 'agency-module-newsletter' ); ?></h1><p><?php esc_html_e( 'Only confirmed subscribers are included in the export.', 'agency-module-newsletter' ); ?> <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=agency_newsletter_export' ), 'agency_newsletter_export' ) ); ?>"><?php esc_html_e( 'Export active CSV', 'agency-module-newsletter' ); ?></a></p><table class="widefat striped"><thead><tr><th>Email</th><th>Name</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach ( $rows as $row ) : ?><tr><td><?php echo esc_html( $row->email ); ?></td><td><?php echo esc_html( $row->name ); ?></td><td><?php echo esc_html( $row->status ); ?></td><td><?php echo esc_html( $row->created_at ); ?></td></tr><?php endforeach; ?></tbody></table></div><?php
}
function agency_newsletter_export() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-newsletter' ) );
	check_admin_referer( 'agency_newsletter_export' );
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT email,name,confirmed_at FROM " . agency_newsletter_table() . " WHERE status='active' ORDER BY email", ARRAY_A );
	nocache_headers(); header( 'Content-Type: text/csv; charset=UTF-8' ); header( 'Content-Disposition: attachment; filename="newsletter-active-' . gmdate( 'Y-m-d' ) . '.csv"' );
	$out = fopen( 'php://output', 'w' ); fputs( $out, "\xEF\xBB\xBF" ); fputcsv( $out, array( 'Email', 'Name', 'Confirmed UTC' ) );
	foreach ( $rows as $row ) { fputcsv( $out, array_map( static fn( $value ) => preg_match( '/^[=+\-@]/', (string) $value ) ? "'" . $value : $value, $row ) ); }
	fclose( $out ); exit;
}
add_action( 'admin_post_agency_newsletter_export', 'agency_newsletter_export' );
