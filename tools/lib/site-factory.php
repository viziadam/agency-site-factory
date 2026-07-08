<?php
/**
 * Standalone helpers for Agency Site Factory command-line tools.
 */

declare(strict_types=1);

const AGENCY_FACTORY_VERSION = '1.12.0';
const AGENCY_FACTORY_MODULES = array( 'auth', 'booking', 'webshop', 'newsletter', 'analytics', 'legal' );

/**
 * Return the official Agency Site Factory repository root.
 *
 * The factory tools must be movable with the repository. Do not point this at a
 * machine-specific workspace path; resolve it from this shared library instead.
 */
function agency_factory_root(): string {
	static $root = null;
	if ( null !== $root ) {
		return $root;
	}

	$resolved = realpath( dirname( __DIR__, 2 ) );
	if ( false === $resolved ) {
		throw new RuntimeException( 'Agency Site Factory root cannot be resolved.' );
	}

	foreach ( array( 'tools/lib/site-factory.php', 'wp-content/plugins/agency-core', 'wp-content/themes/agency-theme', 'site-factory/projects' ) as $required ) {
		if ( ! file_exists( $resolved . DIRECTORY_SEPARATOR . str_replace( '/', DIRECTORY_SEPARATOR, $required ) ) ) {
			throw new RuntimeException( "Agency Site Factory root is invalid; missing {$required}." );
		}
	}

	$root = rtrim( $resolved, '/\\' );
	return $root;
}

function agency_factory_path( string ...$segments ): string {
	$path = agency_factory_root();
	foreach ( $segments as $segment ) {
		$path .= DIRECTORY_SEPARATOR . trim( str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $segment ), DIRECTORY_SEPARATOR );
	}
	return $path;
}

/**
 * Read and validate a project configuration.
 *
 * @return array<string,mixed>
 */
function agency_factory_read_config( string $path ): array {
	$real = realpath( $path );
	if ( false === $real || ! is_file( $real ) ) {
		throw new RuntimeException( "Config file not found: {$path}" );
	}
	$json = file_get_contents( $real );
	if ( false === $json ) {
		throw new RuntimeException( "Config file cannot be read: {$real}" );
	}
	try {
		$config = json_decode( $json, true, 512, JSON_THROW_ON_ERROR );
	} catch ( JsonException $error ) {
		throw new RuntimeException( 'Invalid config JSON: ' . $error->getMessage() );
	}
	if ( ! is_array( $config ) ) {
		throw new RuntimeException( 'Project config must contain a JSON object.' );
	}
	$errors = agency_factory_validate_config( $config );
	if ( $errors ) {
		throw new RuntimeException( "Invalid project config:\n- " . implode( "\n- ", $errors ) );
	}
	return $config;
}

/**
 * @param array<string,mixed> $config Project configuration.
 * @return string[]
 */
function agency_factory_validate_config( array $config ): array {
	$errors   = array();
	$required = array(
		'site.name',
		'site.slug',
		'site.local_domain',
		'site.admin_user',
		'site.admin_email',
		'site.language',
		'brand.name',
		'brand.primary_color',
		'brand.secondary_color',
		'brand.background_color',
		'brand.phone',
		'brand.email',
		'brand.address',
		'brand.booking_url',
		'brand.instagram_url',
		'blueprint.slug',
	);

	foreach ( $required as $path ) {
		$value = agency_factory_array_get( $config, $path );
		if ( null === $value || ! is_string( $value ) ) {
			$errors[] = "Missing or non-string field: {$path}";
		}
	}
	$slug = (string) agency_factory_array_get( $config, 'site.slug', '' );
	if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug ) ) {
		$errors[] = 'site.slug must use lowercase letters, numbers and single hyphens.';
	}
	$blueprint = (string) agency_factory_array_get( $config, 'blueprint.slug', '' );
	if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $blueprint ) ) {
		$errors[] = 'blueprint.slug is invalid.';
	}
	$domain = (string) agency_factory_array_get( $config, 'site.local_domain', '' );
	if ( ! preg_match( '/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/i', $domain ) ) {
		$errors[] = 'site.local_domain is invalid.';
	}
	foreach ( array( 'site.admin_email', 'brand.email' ) as $email_path ) {
		$email = (string) agency_factory_array_get( $config, $email_path, '' );
		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$errors[] = "{$email_path} must be a valid email address.";
		}
	}
	foreach ( array( 'primary_color', 'secondary_color', 'background_color' ) as $color ) {
		$value = (string) agency_factory_array_get( $config, "brand.{$color}", '' );
		if ( ! preg_match( '/^#[0-9a-f]{6}$/i', $value ) ) {
			$errors[] = "brand.{$color} must be a six-digit hex color.";
		}
	}
	if ( ! isset( $config['modules'] ) || ! is_array( $config['modules'] ) ) {
		$errors[] = 'modules must be an object.';
	} else {
		foreach ( AGENCY_FACTORY_MODULES as $module ) {
			if ( ! array_key_exists( $module, $config['modules'] ) || ! is_bool( $config['modules'][ $module ] ) ) {
				$errors[] = "modules.{$module} must be true or false.";
			}
		}
		foreach ( array_keys( $config['modules'] ) as $module ) {
			if ( ! in_array( $module, AGENCY_FACTORY_MODULES, true ) ) {
				$errors[] = "Unknown module: {$module}";
			}
		}
	}
	return $errors;
}

/**
 * @param array<string,mixed> $array Data.
 * @return mixed
 */
function agency_factory_array_get( array $array, string $path, mixed $default = null ): mixed {
	$value = $array;
	foreach ( explode( '.', $path ) as $part ) {
		if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
			return $default;
		}
		$value = $value[ $part ];
	}
	return $value;
}

function agency_factory_validate_target( string $target ): string {
	$real = realpath( $target );
	if ( false === $real || ! is_dir( $real ) ) {
		throw new RuntimeException( "Target directory not found: {$target}" );
	}
	foreach ( array( 'wp-admin', 'wp-includes', 'wp-content', 'wp-config.php' ) as $required ) {
		if ( ! file_exists( $real . DIRECTORY_SEPARATOR . $required ) ) {
			throw new RuntimeException( "Target is not a WordPress public directory; missing {$required}." );
		}
	}
	return rtrim( $real, '/\\' );
}

/**
 * @param array<string,mixed> $config Project configuration.
 * @return string[]
 */
function agency_factory_enabled_modules( array $config ): array {
	$enabled = array();
	foreach ( AGENCY_FACTORY_MODULES as $module ) {
		if ( true === ( $config['modules'][ $module ] ?? false ) ) {
			$enabled[] = $module;
		}
	}
	return $enabled;
}

/**
 * @param array<string,mixed> $config Project configuration.
 * @param array<string,mixed> $old Existing manifest.
 * @return array<string,mixed>
 */
function agency_factory_build_manifest( array $config, array $old = array() ): array {
	$now = gmdate( 'c' );
	$versions   = array();
	$migrations = array();
	foreach ( agency_factory_enabled_modules( $config ) as $module ) {
		$file = agency_factory_path( 'wp-content', 'plugins', "agency-module-{$module}", "agency-module-{$module}.php" );
		$data = is_readable( $file ) ? (string) file_get_contents( $file, false, null, 0, 4096 ) : '';
		preg_match( '/^[ \t*#@]*Version:\s*(.+)$/mi', $data, $match );
		$versions[ $module ]   = trim( $match[1] ?? '' );
		$migrations[ $module ] = 'booking' === $module ? 'pending activation' : 'not-required';
	}
	return array(
		'project_slug'      => $config['site']['slug'],
		'framework_version' => AGENCY_FACTORY_VERSION,
		'blueprint'         => $config['blueprint']['slug'],
		'enabled_modules'   => agency_factory_enabled_modules( $config ),
		'module_versions'   => $versions,
		'module_migrations' => $migrations,
		'created_at'        => $old['created_at'] ?? $now,
		'updated_at'        => $now,
	);
}

/**
 * Copy a directory without following links.
 */
function agency_factory_copy_directory( string $source, string $destination ): void {
	if ( ! is_dir( $source ) ) {
		throw new RuntimeException( "Framework source is missing: {$source}" );
	}
	if ( ! is_dir( $destination ) && ! mkdir( $destination, 0775, true ) && ! is_dir( $destination ) ) {
		throw new RuntimeException( "Cannot create directory: {$destination}" );
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ( $iterator as $item ) {
		if ( $item->isLink() ) {
			continue;
		}
		$relative = $iterator->getSubPathName();
		$target   = $destination . DIRECTORY_SEPARATOR . $relative;
		if ( $item->isDir() ) {
			if ( ! is_dir( $target ) && ! mkdir( $target, 0775, true ) && ! is_dir( $target ) ) {
				throw new RuntimeException( "Cannot create directory: {$target}" );
			}
		} elseif ( ! copy( $item->getPathname(), $target ) ) {
			throw new RuntimeException( "Cannot copy file: {$relative}" );
		}
	}
}

function agency_factory_backup_directory( string $path, string $timestamp ): ?string {
	if ( ! is_dir( $path ) ) {
		return null;
	}
	$backup = $path . '.backup-' . $timestamp;
	$index  = 2;
	while ( file_exists( $backup ) ) {
		$backup = $path . '.backup-' . $timestamp . '-' . $index++;
	}
	if ( ! rename( $path, $backup ) ) {
		throw new RuntimeException( "Cannot create backup: {$backup}" );
	}
	return $backup;
}

/**
 * @param array<string,mixed> $data JSON data.
 */
function agency_factory_write_json( string $path, array $data ): void {
	$json = json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . PHP_EOL;
	if ( false === file_put_contents( $path, $json, LOCK_EX ) ) {
		throw new RuntimeException( "Cannot write file: {$path}" );
	}
}

/**
 * Run a command without invoking a shell.
 *
 * @param string[] $command Command and arguments.
 */
function agency_factory_run( array $command, string $cwd ): int {
	$process = proc_open(
		$command,
		array( 0 => STDIN, 1 => STDOUT, 2 => STDERR ),
		$pipes,
		$cwd,
		null,
		array( 'bypass_shell' => true )
	);
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'Could not start WP-CLI.' );
	}
	return proc_close( $process );
}
