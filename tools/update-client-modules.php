<?php
/**
 * Install and toggle feature modules in an existing client site.
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/site-factory.php';

try {
	$options = getopt( '', array( 'target:', 'enable::', 'disable::', 'dry-run', 'wp-cli:', 'php:', 'php-ini:' ) );
	if ( empty( $options['target'] ) ) {
		throw new InvalidArgumentException( 'Usage: php tools/update-client-modules.php --target=<WordPress public> [--enable=booking,legal] [--disable=newsletter] [--dry-run] [--wp-cli=<wp-cli.phar>]' );
	}
	$target  = agency_factory_validate_target( (string) $options['target'] );
	$enable  = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $options['enable'] ?? '' ) ) ) ) );
	$disable = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $options['disable'] ?? '' ) ) ) ) );
	foreach ( array_merge( $enable, $disable ) as $module ) {
		if ( ! in_array( $module, AGENCY_FACTORY_MODULES, true ) ) {
			throw new InvalidArgumentException( "Unknown module: {$module}" );
		}
	}
	$dry_run = array_key_exists( 'dry-run', $options );
	foreach ( $enable as $module ) {
		$source      = agency_factory_path( 'wp-content', 'plugins', "agency-module-{$module}" );
		$destination = $target . "/wp-content/plugins/agency-module-{$module}";
		fwrite( STDOUT, ( $dry_run ? '[DRY RUN] ' : '' ) . "Install module: {$module}\n" );
		if ( ! $dry_run ) {
			agency_factory_backup_directory( $destination, gmdate( 'Ymd-His' ) );
			agency_factory_copy_directory( $source, $destination );
		}
	}
	if ( $dry_run ) {
		fwrite( STDOUT, 'Would enable: ' . ( implode( ', ', $enable ) ?: '(none)' ) . PHP_EOL );
		fwrite( STDOUT, 'Would disable: ' . ( implode( ', ', $disable ) ?: '(none)' ) . PHP_EOL );
		exit( 0 );
	}
	$wp_cli = isset( $options['wp-cli'] ) ? realpath( (string) $options['wp-cli'] ) : false;
	if ( ! $wp_cli ) {
		fwrite( STDOUT, "Modules were copied. Toggle them under Agency Kit > Modules, or rerun with --wp-cli.\n" );
		exit( 0 );
	}
	$command = array( (string) ( $options['php'] ?? PHP_BINARY ) );
	if ( isset( $options['php-ini'] ) ) {
		$command[] = '-c';
		$command[] = (string) $options['php-ini'];
	}
	$command[] = $wp_cli;
	foreach ( array( 'enable' => $enable, 'disable' => $disable ) as $action => $modules ) {
		foreach ( $modules as $module ) {
			$code = agency_factory_run( array_merge( $command, array( 'agency', 'module', $action, $module, '--path=' . $target ) ), $target );
			if ( 0 !== $code ) {
				throw new RuntimeException( "Could not {$action} module {$module}." );
			}
		}
	}
	fwrite( STDOUT, "Module update completed.\n" );
} catch ( Throwable $error ) {
	fwrite( STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL );
	exit( 1 );
}
