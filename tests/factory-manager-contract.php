<?php
/**
 * Standalone Factory Manager contract test.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
require_once $root . '/tools/factory-manager/manager-lib.php';

$failures = array();
$config   = agency_manager_build_config(
	array(
		'project_name'    => 'Manager Test',
		'project_slug'    => 'manager-test',
		'local_domain'    => 'manager-test.local',
		'admin_user'      => 'admin',
		'admin_email'     => 'admin@example.com',
		'language'        => 'hu_HU',
		'brand_name'      => 'Manager Test',
		'primary_color'   => '#123456',
		'secondary_color' => '#234567',
		'background_color'=> '#ffffff',
		'phone'           => '+36 1 234 5678',
		'email'           => 'hello@example.com',
		'address'         => 'Budapest',
		'booking_url'     => '/kapcsolat/',
		'instagram_url'   => '',
		'facebook_url'    => '',
		'linkedin_url'    => '',
		'blueprint'       => 'beauty',
		'modules'         => array( 'booking', 'legal', 'not-allowed' ),
	)
);

$errors = agency_factory_validate_config( $config );
if ( $errors ) {
	$failures[] = 'Wizard config validation failed: ' . implode( ', ', $errors );
}
if ( array( 'auth', 'booking', 'legal' ) !== agency_factory_enabled_modules( $config ) ) {
	$failures[] = 'Wizard module allowlist failed.';
}
if ( ! agency_manager_valid_slug( 'client-one' ) || agency_manager_valid_slug( '../client' ) ) {
	$failures[] = 'Project slug guard failed.';
}
$blueprints = agency_manager_blueprints();
if ( ! isset( $blueprints['beauty'], $blueprints['consulting'] ) ) {
	$failures[] = 'Dynamic blueprint discovery failed.';
}
$redacted = agency_manager_sanitize_log( '--token=supersecret {"password":"hidden"}' );
if ( str_contains( $redacted, 'supersecret' ) || str_contains( $redacted, 'hidden' ) ) {
	$failures[] = 'Sensitive log redaction failed.';
}
$index = (string) file_get_contents( $root . '/tools/factory-manager/index.php' );
foreach ( array( 'agency_manager_check_csrf', 'agency_factory_validate_target', 'agency_manager_wp_cli_preflight', 'dry_run', 'install', 'update_modules', 'apply_module_setup', 'repair_module_pages', 'repair_module_display', 'module_setup_status' ) as $contract ) {
	if ( ! str_contains( $index, $contract ) ) {
		$failures[] = "Manager UI contract missing: {$contract}";
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo 'Factory Manager contract passed: wizard, allowlists, CSRF hooks and log redaction are valid.' . PHP_EOL;
