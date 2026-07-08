<?php
/**
 * Standalone project factory contract test.
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
require_once $root . '/tools/lib/site-factory.php';

$failures = array();
$config   = agency_factory_read_config( $root . '/site-factory/projects/demo-beauty/config.json' );

if ( realpath( $root ) !== agency_factory_root() ) {
	$failures[] = 'Factory repo root resolver does not point at the agency-site-factory root.';
}
foreach ( array( 'wp-content/plugins/agency-core', 'wp-content/themes/agency-theme', 'site-factory/projects' ) as $required_source ) {
	if ( ! file_exists( agency_factory_path( $required_source ) ) ) {
		$failures[] = "Factory source path missing through root resolver: {$required_source}";
	}
}

if ( 'demo-beauty' !== $config['site']['slug'] ) {
	$failures[] = 'Demo project slug mismatch.';
}
$expected_modules = array();
foreach ( AGENCY_FACTORY_MODULES as $module ) {
	if ( true === ( $config['modules'][ $module ] ?? false ) ) {
		$expected_modules[] = $module;
	}
}
if ( $expected_modules !== agency_factory_enabled_modules( $config ) ) {
	$failures[] = 'Enabled module calculation mismatch.';
}
$invalid = $config;
unset( $invalid['site']['name'] );
if ( ! agency_factory_validate_config( $invalid ) ) {
	$failures[] = 'Missing required config field was accepted.';
}

$manifest = agency_factory_build_manifest( $config );
foreach ( array( 'project_slug', 'framework_version', 'blueprint', 'enabled_modules', 'created_at', 'updated_at' ) as $field ) {
	if ( ! array_key_exists( $field, $manifest ) ) {
		$failures[] = "Manifest field missing: {$field}";
	}
}

foreach ( AGENCY_FACTORY_MODULES as $module ) {
	$main = $root . "/wp-content/plugins/agency-module-{$module}/agency-module-{$module}.php";
	if ( ! is_file( $main ) ) {
		$failures[] = "Module plugin missing: {$module}";
		continue;
	}
	$source = (string) file_get_contents( $main );
	if ( in_array( $module, array( 'webshop' ), true ) && ! str_contains( $source, 'Module installed, feature implementation pending' ) ) {
		$failures[] = "Module placeholder message missing: {$module}";
	}
}

$registry_source = (string) file_get_contents( $root . '/wp-content/plugins/agency-core/includes/modules.php' );
foreach ( AGENCY_FACTORY_MODULES as $module ) {
	if ( ! str_contains( $registry_source, "'{$module}'" ) ) {
		$failures[] = "Registry module missing: {$module}";
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo 'Project factory contract passed: config, manifest and 6 module packages are valid.' . PHP_EOL;
