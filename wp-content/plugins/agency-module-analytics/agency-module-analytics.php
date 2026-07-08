<?php
/**
 * Plugin Name: Agency Module: Analytics
 * Description: Consent-aware GA4 integration with safe admin configuration.
 * Version: 1.12.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Agency Starter
 * Text Domain: agency-module-analytics
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENCY_ANALYTICS_VERSION', '1.12.0' );

function agency_analytics_settings() {
	return wp_parse_args( (array) get_option( 'agency_analytics_settings', array() ), array( 'measurement_id' => '', 'consent_required' => 1, 'anonymize_ip' => 1, 'respect_dnt' => 1 ) );
}

function agency_analytics_enqueue() {
	if ( is_admin() ) return;
	$settings = agency_analytics_settings();
	if ( ! preg_match( '/^G-[A-Z0-9]{5,15}$/', $settings['measurement_id'] ) ) return;
	wp_enqueue_script( 'agency-analytics', plugins_url( 'analytics.js', __FILE__ ), array(), AGENCY_ANALYTICS_VERSION, true );
	wp_localize_script( 'agency-analytics', 'agencyAnalytics', array(
		'measurementId' => $settings['measurement_id'],
		'consentRequired' => ! empty( $settings['consent_required'] ),
		'respectDnt' => ! empty( $settings['respect_dnt'] ),
		'consentCookie' => 'agency_analytics_consent',
	) );
}
add_action( 'wp_enqueue_scripts', 'agency_analytics_enqueue' );

function agency_analytics_admin_menu() {
	add_submenu_page( 'agency-core-settings', __( 'Analytics', 'agency-module-analytics' ), __( 'Analytics', 'agency-module-analytics' ), 'manage_options', 'agency-module-analytics', 'agency_analytics_admin' );
}
add_action( 'admin_menu', 'agency_analytics_admin_menu', 30 );

function agency_analytics_admin() {
	$s = agency_analytics_settings();
	?><div class="wrap"><h1><?php esc_html_e( 'Analytics', 'agency-module-analytics' ); ?></h1><?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success"><p><?php esc_html_e( 'Analytics settings saved.', 'agency-module-analytics' ); ?></p></div><?php endif; ?><p><?php esc_html_e( 'GA4 loads only after analytics consent by default. No tracking code is injected when the ID is empty or invalid.', 'agency-module-analytics' ); ?></p><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="agency_analytics_save"><?php wp_nonce_field( 'agency_analytics_save' ); ?><table class="form-table"><tr><th><label for="agency-measurement-id">GA4 Measurement ID</label></th><td><input id="agency-measurement-id" class="regular-text" name="measurement_id" value="<?php echo esc_attr( $s['measurement_id'] ); ?>" placeholder="G-XXXXXXXXXX"><p class="description"><?php esc_html_e( 'Create this in Google Analytics â†’ Admin â†’ Data streams.', 'agency-module-analytics' ); ?></p></td></tr><tr><th><?php esc_html_e( 'Privacy', 'agency-module-analytics' ); ?></th><td><label><input type="checkbox" name="consent_required" value="1" <?php checked( $s['consent_required'] ); ?>> <?php esc_html_e( 'Require explicit analytics consent', 'agency-module-analytics' ); ?></label><br><label><input type="checkbox" name="respect_dnt" value="1" <?php checked( $s['respect_dnt'] ); ?>> <?php esc_html_e( 'Respect browser Do Not Track', 'agency-module-analytics' ); ?></label></td></tr></table><?php submit_button(); ?></form><h2><?php esc_html_e( 'Consent integration', 'agency-module-analytics' ); ?></h2><p><?php esc_html_e( 'Your cookie banner can grant consent by dispatching this browser event:', 'agency-module-analytics' ); ?> <code>window.dispatchEvent(new CustomEvent('agency:analytics-consent', {detail:{granted:true}}))</code></p></div><?php
}

function agency_analytics_save() {
	if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_agency_portal' ) ) wp_die( esc_html__( 'Insufficient permissions.', 'agency-module-analytics' ) );
	check_admin_referer( 'agency_analytics_save' );
	$id = strtoupper( sanitize_text_field( wp_unslash( $_POST['measurement_id'] ?? '' ) ) );
	if ( $id && ! preg_match( '/^G-[A-Z0-9]{5,15}$/', $id ) ) wp_die( esc_html__( 'Invalid GA4 Measurement ID.', 'agency-module-analytics' ), 400 );
	update_option( 'agency_analytics_settings', array( 'measurement_id' => $id, 'consent_required' => ! empty( $_POST['consent_required'] ), 'anonymize_ip' => 1, 'respect_dnt' => ! empty( $_POST['respect_dnt'] ) ), false );
	$return = esc_url_raw( wp_unslash( $_POST['return_url'] ?? '' ) );
	wp_safe_redirect( $return ? add_query_arg( 'updated', 1, $return ) : admin_url( 'admin.php?page=agency-module-analytics&updated=1' ) ); exit;
}
add_action( 'admin_post_agency_analytics_save', 'agency_analytics_save' );
add_action( 'agency_client_portal_action_agency_analytics_save', 'agency_analytics_save' );

function agency_analytics_client_portal_tabs( $tabs ) {
	$tabs['analytics'] = array( 'label' => __( 'Visitors & interactions', 'agency-module-analytics' ), 'callback' => 'agency_analytics_client_portal', 'order' => 40 );
	return $tabs;
}
add_filter( 'agency_client_portal_tabs', 'agency_analytics_client_portal_tabs' );

function agency_analytics_client_portal() {
	global $wpdb;
	$table = function_exists( 'agency_core_client_admin_table' ) ? agency_core_client_admin_table() : $wpdb->prefix . 'agency_events';
	$range = absint( $_GET['range'] ?? 30 );
	$range = in_array( $range, array( 7, 30, 90 ), true ) ? $range : 30;
	$from  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * $range );
	$totals = array(
		'views' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_type='pageview' AND created_at >= %s", $from ) ),
		'visitors' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT session_hash) FROM {$table} WHERE event_type='pageview' AND created_at >= %s AND session_hash<>''", $from ) ),
		'clicks' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_name='booking_click' AND created_at >= %s", $from ) ),
		'bookings' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE event_name='booking_completed' AND created_at >= %s", $from ) ),
	);
	$pages = $wpdb->get_results( $wpdb->prepare( "SELECT page_path,MAX(page_title) page_title,COUNT(*) views,COUNT(DISTINCT session_hash) visitors FROM {$table} WHERE event_type='pageview' AND created_at >= %s GROUP BY page_path ORDER BY views DESC LIMIT 100", $from ) );
	$events = $wpdb->get_results( $wpdb->prepare( "SELECT event_name,COUNT(*) total FROM {$table} WHERE event_type IN ('interaction','conversion') AND created_at >= %s GROUP BY event_name ORDER BY total DESC", $from ) );
	$click_rate = $totals['clicks'] ? round( $totals['bookings'] / $totals['clicks'] * 100, 1 ) : 0;
	$base = function_exists( 'agency_core_client_admin_url' ) ? agency_core_client_admin_url( 'analytics' ) : get_permalink();
	?>
	<div class="agency-report-toolbar"><div><h2><?php esc_html_e( 'Performance overview', 'agency-module-analytics' ); ?></h2><p><?php esc_html_e( 'Privacy-conscious, consented first-party statistics.', 'agency-module-analytics' ); ?></p></div><div><?php foreach ( array( 7, 30, 90 ) as $days ) : ?><a class="<?php echo $range === $days ? 'is-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( array( 'portal' => 'analytics', 'range' => $days ), $base ) ); ?>"><?php echo absint( $days ); ?>d</a><?php endforeach; ?></div></div>
	<div class="agency-report-stats"><?php foreach ( array( 'views' => __( 'Page views', 'agency-module-analytics' ), 'visitors' => __( 'Visitors', 'agency-module-analytics' ), 'clicks' => __( 'Booking clicks', 'agency-module-analytics' ), 'bookings' => __( 'Completed bookings', 'agency-module-analytics' ) ) as $key => $label ) : ?><article><span><?php echo esc_html( $label ); ?></span><strong><?php echo number_format_i18n( $totals[ $key ] ); ?></strong></article><?php endforeach; ?><article><span><?php esc_html_e( 'Click â†’ booking', 'agency-module-analytics' ); ?></span><strong><?php echo esc_html( $click_rate ); ?>%</strong></article></div>
	<div class="agency-report-grid"><section class="agency-client-panel"><h2><?php esc_html_e( 'Every visited page', 'agency-module-analytics' ); ?></h2><div class="agency-report-table"><table><thead><tr><th><?php esc_html_e( 'Page', 'agency-module-analytics' ); ?></th><th><?php esc_html_e( 'Views', 'agency-module-analytics' ); ?></th><th><?php esc_html_e( 'Visitors', 'agency-module-analytics' ); ?></th></tr></thead><tbody><?php if ( ! $pages ) : ?><tr><td colspan="3"><?php esc_html_e( 'No consented data yet.', 'agency-module-analytics' ); ?></td></tr><?php endif; foreach ( $pages as $page ) : ?><tr><td><strong><?php echo esc_html( $page->page_title ?: $page->page_path ); ?></strong><small><?php echo esc_html( $page->page_path ); ?></small></td><td><?php echo absint( $page->views ); ?></td><td><?php echo absint( $page->visitors ); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
	<section class="agency-client-panel"><h2><?php esc_html_e( 'Interactions', 'agency-module-analytics' ); ?></h2><div class="agency-report-table"><table><thead><tr><th><?php esc_html_e( 'Event', 'agency-module-analytics' ); ?></th><th><?php esc_html_e( 'Count', 'agency-module-analytics' ); ?></th></tr></thead><tbody><?php if ( ! $events ) : ?><tr><td colspan="2"><?php esc_html_e( 'No interactions yet.', 'agency-module-analytics' ); ?></td></tr><?php endif; foreach ( $events as $event ) : ?><tr><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $event->event_name ) ) ); ?></td><td><?php echo absint( $event->total ); ?></td></tr><?php endforeach; ?></tbody></table></div></section></div>
	<?php agency_analytics_client_settings( $base ); ?>
	<?php
}

function agency_analytics_client_settings( $return_url ) {
	$s = agency_analytics_settings();
	$action_url = function_exists( 'agency_core_client_admin_action_url' ) ? agency_core_client_admin_action_url( 'agency_analytics_save', 'analytics' ) : admin_url( 'admin-post.php' );
	?><details class="agency-client-panel agency-report-settings"><summary><?php esc_html_e( 'Analytics settings', 'agency-module-analytics' ); ?></summary><form method="post" action="<?php echo esc_url( $action_url ); ?>"><input type="hidden" name="action" value="agency_analytics_save"><input type="hidden" name="agency_portal_action" value="agency_analytics_save"><input type="hidden" name="return_url" value="<?php echo esc_url( $return_url ); ?>"><?php wp_nonce_field( 'agency_analytics_save' ); ?><label><span>GA4 Measurement ID</span><input name="measurement_id" value="<?php echo esc_attr( $s['measurement_id'] ); ?>" placeholder="G-XXXXXXXXXX"></label><label><input type="checkbox" name="consent_required" value="1" <?php checked( $s['consent_required'] ); ?>> <?php esc_html_e( 'Require analytics consent', 'agency-module-analytics' ); ?></label><label><input type="checkbox" name="respect_dnt" value="1" <?php checked( $s['respect_dnt'] ); ?>> <?php esc_html_e( 'Respect Do Not Track', 'agency-module-analytics' ); ?></label><button class="agency-client-button"><?php esc_html_e( 'Save analytics settings', 'agency-module-analytics' ); ?></button></form></details><?php
}
