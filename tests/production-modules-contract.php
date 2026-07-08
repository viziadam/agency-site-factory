<?php
/**
 * Static security and integration contract for production modules.
 */

declare(strict_types=1);

$root     = dirname( __DIR__ );
$failures = array();
$files    = array(
	'core'    => $root . '/wp-content/plugins/agency-core/includes/module-services.php',
	'auth'    => $root . '/wp-content/plugins/agency-module-auth/agency-module-auth.php',
	'booking' => $root . '/wp-content/plugins/agency-module-booking/agency-module-booking.php',
	'legal'   => $root . '/wp-content/plugins/agency-module-legal/agency-module-legal.php',
	'newsletter' => $root . '/wp-content/plugins/agency-module-newsletter/agency-module-newsletter.php',
	'analytics'  => $root . '/wp-content/plugins/agency-module-analytics/agency-module-analytics.php',
);
$source = array_map( static fn( $file ) => (string) file_get_contents( $file ), $files );
$source['core'] .= "\n" . (string) file_get_contents( $root . '/wp-content/plugins/agency-core/includes/client-admin.php' );

$append_plugin_tree = static function ( string $plugin_dir ): string {
	$buffer   = '';
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $plugin_dir, FilesystemIterator::SKIP_DOTS )
	);
	foreach ( $iterator as $file ) {
		if ( 'php' === strtolower( $file->getExtension() ) ) {
			$buffer .= "\n" . (string) file_get_contents( $file->getPathname() );
		}
	}
	return $buffer;
};

foreach ( array( 'auth', 'booking', 'legal' ) as $module ) {
	$source[ $module ] .= $append_plugin_tree( $root . "/wp-content/plugins/agency-module-{$module}" );
}

$contracts = array(
	'core' => array( 'agency_core_email_send', 'agency_core_encrypt_secret', 'agency_core_audit_log', 'agency_core_rate_limit', 'agency_core_client_admin_install', 'agency_client_portal_tabs', 'agency_core_record_event', 'manage_agency_portal', 'agency_portal_session', 'agency_core_client_admin_handle_request', 'agency_client_portal_action_', 'dbDelta', 'check_admin_referer' ),
	'auth' => array( 'agency_auth_send_verification', 'wp_hash_password', 'wp_check_password', 'agency_core_rate_limit', 'check_admin_referer', 'agency_customer', 'retrieve_password' ),
	'booking' => array( 'agency_booking_create', 'agency_booking_get_available_slots', 'agency_booking_is_slot_available', 'agency_booking_calendar_response', 'agency_booking_manager_portal', 'agency_booking_client_portal_tabs', 'agency_core_client_admin_action_url', 'agency_client_portal_action_agency_booking_status', 'booking_completed', 'manage_agency_bookings', 'dbDelta', 'KEY start_at', 'KEY status', 'KEY email', 'KEY user_id', 'KEY service_id', 'agency_core_email_send', 'agency_booking_status', 'check_admin_referer', 'agency_theme_render_module_component' ),
	'legal' => array( 'agency_legal_get_checkbox_text', '_agency_legal_generated', 'check_admin_referer', 'draft' ),
	'newsletter' => array( 'dbDelta', 'agency_newsletter_subscribe', 'wp_hash_password', 'wp_check_password', 'agency_newsletter_unsubscribe', 'agency_newsletter_export' ),
	'analytics' => array( 'agency_analytics_save', 'agency_analytics_client_portal_tabs', 'agency_client_portal_action_agency_analytics_save', 'booking_click', 'booking_completed', 'consent_required', 'respect_dnt', 'check_admin_referer' ),
);
foreach ( $contracts as $file => $needles ) {
	foreach ( $needles as $needle ) {
		if ( ! str_contains( $source[ $file ], $needle ) ) {
			$failures[] = "{$file} contract missing: {$needle}";
		}
	}
}
if ( preg_match( '/api[_-]?key\s*=\s*["\'][A-Za-z0-9_-]{16,}/i', implode( "\n", $source ) ) ) {
	$failures[] = 'A hard-coded API key appears to be present.';
}
foreach ( array( 'auth', 'booking', 'legal', 'newsletter', 'analytics' ) as $module ) {
	if ( ! str_contains( $source[ $module ], 'Version: 1.12.0' ) ) {
		$failures[] = "{$module} version is not 1.12.0.";
	}
}
if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}
echo 'Production module contracts passed: services, Auth, Booking, Legal, Newsletter and Analytics.' . PHP_EOL;
