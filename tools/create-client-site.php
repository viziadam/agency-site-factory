<?php
/**
 * Install Agency Site Factory into an existing WordPress public directory.
 *
 * Usage: php tools/create-client-site.php --config=... --target=... [--dry-run]
 */

declare(strict_types=1);

require_once __DIR__ . '/lib/site-factory.php';

/**
 * @param string $message Message.
 */
function agency_factory_output( string $message ): void {
	fwrite( STDOUT, $message . PHP_EOL );
}

$agency_factory_backups = array();
try {
	$options = getopt( '', array( 'config:', 'target:', 'dry-run', 'wp-cli:', 'php:', 'php-ini:' ) );
	if ( empty( $options['config'] ) || empty( $options['target'] ) ) {
		throw new InvalidArgumentException( 'Usage: php tools/create-client-site.php --config=<config.json> --target=<WordPress public> [--dry-run] [--wp-cli=<wp-cli.phar>] [--php=<php.exe>] [--php-ini=<php.ini>]' );
	}

	$config       = agency_factory_read_config( (string) $options['config'] );
	$target       = agency_factory_validate_target( (string) $options['target'] );
	$dry_run      = array_key_exists( 'dry-run', $options );
	$timestamp    = gmdate( 'Ymd-His' );
	$wp_content   = $target . DIRECTORY_SEPARATOR . 'wp-content';
	$enabled      = agency_factory_enabled_modules( $config );
	$installables = array(
		agency_factory_path( 'wp-content', 'themes', 'agency-theme' ) => $wp_content . '/themes/agency-theme',
		agency_factory_path( 'wp-content', 'plugins', 'agency-core' ) => $wp_content . '/plugins/agency-core',
	);
	foreach ( $enabled as $module ) {
		$installables[ agency_factory_path( 'wp-content', 'plugins', "agency-module-{$module}" ) ] = $wp_content . "/plugins/agency-module-{$module}";
	}

	agency_factory_output( ( $dry_run ? '[DRY RUN] ' : '' ) . 'Validated project: ' . $config['site']['slug'] );
	agency_factory_output( 'Target: ' . $target );
	$existing_manifest = is_file( $wp_content . '/agency-project.json' ) ? json_decode( (string) file_get_contents( $wp_content . '/agency-project.json' ), true ) : array();
	agency_factory_output( 'Framework version: ' . ( $existing_manifest['framework_version'] ?? 'not installed' ) . ' -> ' . AGENCY_FACTORY_VERSION );
	foreach ( $installables as $source => $destination ) {
		if ( ! is_dir( $source ) ) {
			throw new RuntimeException( "Package is missing: {$source}" );
		}
		agency_factory_output( 'Install: ' . basename( $source ) . ' -> ' . $destination );
		if ( ! $dry_run ) {
			$backup = agency_factory_backup_directory( $destination, $timestamp );
			if ( $backup ) {
				$agency_factory_backups[] = $backup;
				agency_factory_output( 'Backup: ' . $backup );
			}
			agency_factory_copy_directory( $source, $destination );
		}
	}

	$manifest_path = $wp_content . '/agency-project.json';
	$old_manifest  = array();
	if ( is_file( $manifest_path ) ) {
		$decoded      = json_decode( (string) file_get_contents( $manifest_path ), true );
		$old_manifest = is_array( $decoded ) ? $decoded : array();
	}
	$manifest = agency_factory_build_manifest( $config, $old_manifest );
	agency_factory_output( 'Write manifest: ' . $manifest_path );
	agency_factory_output( 'Enabled modules: ' . ( $enabled ? implode( ', ', $enabled ) : '(none)' ) );
	if ( ! $dry_run ) {
		agency_factory_write_json( $manifest_path, $manifest );
		agency_factory_write_json( $wp_content . '/agency-project-config.json', $config );
	}

	$wp_cli = isset( $options['wp-cli'] ) ? realpath( (string) $options['wp-cli'] ) : false;
	if ( false === $wp_cli ) {
		$wp_cli = null;
	}
	if ( $dry_run ) {
		agency_factory_output( 'Would activate Agency Core and Agency Theme, configure permalinks, import ' . $config['blueprint']['slug'] . ', apply project settings and activate selected modules when WP-CLI is provided.' );
		exit( 0 );
	}

	if ( $wp_cli ) {
		$php     = isset( $options['php'] ) ? (string) $options['php'] : PHP_BINARY;
		$command = array( $php );
		if ( isset( $options['php-ini'] ) ) {
			$command[] = '-c';
			$command[] = (string) $options['php-ini'];
		}
		$command[] = $wp_cli;
		$commands  = array(
			array( 'plugin', 'activate', 'agency-core' ),
			array( 'theme', 'activate', 'agency-theme' ),
			array( 'option', 'update', 'permalink_structure', '/%postname%/' ),
			array( 'agency', 'blueprint', 'import', $config['blueprint']['slug'] ),
			array( 'agency', 'project', 'apply-config', $wp_content . '/agency-project-config.json' ),
		);
		foreach ( $enabled as $module ) {
			$commands[] = array( 'agency', 'module', 'enable', $module );
		}
		foreach ( $commands as $arguments ) {
			agency_factory_output( 'WP-CLI: wp ' . implode( ' ', $arguments ) );
			$exit_code = agency_factory_run( array_merge( $command, $arguments, array( '--path=' . $target ) ), $target );
			if ( 0 !== $exit_code ) {
				throw new RuntimeException( 'WP-CLI command failed with exit code ' . $exit_code . ': wp ' . implode( ' ', $arguments ) );
			}
		}
		agency_factory_output( 'Client site installation completed.' );
	} else {
		agency_factory_output( 'WP-CLI was not provided. Complete these manual steps:' );
		agency_factory_output( '1. Activate Agency Core under Plugins.' );
		agency_factory_output( '2. Activate Agency Theme under Appearance > Themes.' );
		agency_factory_output( '3. Set the permalink structure to Post name.' );
		agency_factory_output( '4. Import the ' . $config['blueprint']['slug'] . ' blueprint under Agency Kit > Import Blueprint.' );
		agency_factory_output( '5. Apply brand data under Agency Kit > Settings.' );
		agency_factory_output( '6. Activate modules under Agency Kit > Modules: ' . ( $enabled ? implode( ', ', $enabled ) : '(none)' ) );
	}
} catch ( Throwable $error ) {
	fwrite( STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL );
	if ( $agency_factory_backups ) {
		fwrite( STDERR, "ROLLBACK: stop the site, rename the current Agency directories, then rename these backups to their original names:\n- " . implode( "\n- ", $agency_factory_backups ) . PHP_EOL );
	}
	exit( 1 );
}
