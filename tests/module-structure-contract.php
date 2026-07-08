<?php
/**
 * Feature plugin structure contract.
 */

declare(strict_types=1);

$root     = dirname( __DIR__ );
$failures = array();
$modules  = array( 'analytics', 'auth', 'booking', 'legal', 'newsletter', 'webshop' );
$required = array(
	'bootstrap',
	'includes/services',
	'includes/repositories',
	'includes/migrations',
	'includes/admin',
	'includes/frontend',
	'includes/rest',
	'templates/frontend',
	'templates/admin',
	'assets/css',
	'assets/js',
);

foreach ( $modules as $module ) {
	$base = $root . "/wp-content/plugins/agency-module-{$module}";
	if ( ! is_dir( $base ) ) {
		$failures[] = "Module directory missing: {$module}";
		continue;
	}
	foreach ( $required as $dir ) {
		if ( ! is_dir( $base . '/' . $dir ) ) {
			$failures[] = "Module {$module} missing directory: {$dir}";
		}
	}
	if ( ! is_file( $base . '/README.md' ) ) {
		$failures[] = "Module {$module} missing README.md";
	}
}

foreach ( array( 'auth', 'booking', 'legal' ) as $module ) {
	$main = (string) file_get_contents( $root . "/wp-content/plugins/agency-module-{$module}/agency-module-{$module}.php" );
	if ( ! str_contains( $main, '/bootstrap/plugin.php' ) ) {
		$failures[] = "Module {$module} is not bootstrapped through bootstrap/plugin.php";
	}
}

if ( $failures ) {
	fwrite( STDERR, implode( PHP_EOL, $failures ) . PHP_EOL );
	exit( 1 );
}

echo 'Module structure contract passed: feature plugins expose the shared developer layout.' . PHP_EOL;
