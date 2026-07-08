<?php
/**
 * Agency Factory Manager application services.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/lib/site-factory.php';

define( 'AGENCY_MANAGER_ROOT', agency_factory_root() );
define( 'AGENCY_MANAGER_PROJECTS', agency_factory_path( 'site-factory', 'projects' ) );

/**
 * @return array<string,array<string,mixed>>
 */
function agency_manager_projects(): array {
	$projects = array();
	if ( ! is_dir( AGENCY_MANAGER_PROJECTS ) ) {
		return $projects;
	}
	foreach ( new DirectoryIterator( AGENCY_MANAGER_PROJECTS ) as $item ) {
		if ( $item->isDot() || ! $item->isDir() || ! agency_manager_valid_slug( $item->getFilename() ) ) {
			continue;
		}
		try {
			$config = agency_factory_read_config( $item->getPathname() . '/config.json' );
			$meta   = agency_manager_read_json( $item->getPathname() . '/manager.json' );
			$projects[ $item->getFilename() ] = array(
				'config'   => $config,
				'meta'     => $meta,
				'manifest' => agency_manager_manifest( (string) ( $meta['target_path'] ?? '' ) ),
				'logs'     => agency_manager_read_json( $item->getPathname() . '/operations.json' ),
			);
		} catch ( Throwable $error ) {
			$projects[ $item->getFilename() ] = array( 'error' => $error->getMessage() );
		}
	}
	ksort( $projects );
	return $projects;
}

function agency_manager_valid_slug( string $slug ): bool {
	return 1 === preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug );
}

function agency_manager_project_directory( string $slug ): string {
	if ( ! agency_manager_valid_slug( $slug ) ) {
		throw new InvalidArgumentException( 'Érvénytelen projektazonosító.' );
	}
	return AGENCY_MANAGER_PROJECTS . '/' . $slug;
}

/**
 * @return array<string,mixed>
 */
function agency_manager_read_json( string $path ): array {
	if ( ! is_readable( $path ) ) {
		return array();
	}
	$data = json_decode( (string) file_get_contents( $path ), true );
	return is_array( $data ) ? $data : array();
}

/**
 * @param array<string,mixed> $input Form values.
 * @return array<string,mixed>
 */
function agency_manager_build_config( array $input ): array {
	$modules = array();
	foreach ( AGENCY_FACTORY_MODULES as $module ) {
		$modules[ $module ] = in_array( $module, (array) ( $input['modules'] ?? array() ), true );
	}
	if ( ! empty( $modules['booking'] ) ) {
		$modules['auth']  = true;
		$modules['legal'] = true;
	}
	$slug = strtolower( trim( (string) ( $input['project_slug'] ?? '' ) ) );
	return array(
		'site'      => array(
			'name'         => trim( (string) ( $input['project_name'] ?? '' ) ),
			'slug'         => $slug,
			'local_domain' => trim( (string) ( $input['local_domain'] ?? ( $slug . '.local' ) ) ),
			'admin_user'   => trim( (string) ( $input['admin_user'] ?? 'admin' ) ),
			'admin_email'  => trim( (string) ( $input['admin_email'] ?? $input['email'] ?? '' ) ),
			'language'     => trim( (string) ( $input['language'] ?? 'hu_HU' ) ),
		),
		'brand'     => array(
			'name'             => trim( (string) ( $input['brand_name'] ?? '' ) ),
			'primary_color'    => trim( (string) ( $input['primary_color'] ?? '' ) ),
			'secondary_color'  => trim( (string) ( $input['secondary_color'] ?? '' ) ),
			'background_color' => trim( (string) ( $input['background_color'] ?? '' ) ),
			'phone'            => trim( (string) ( $input['phone'] ?? '' ) ),
			'email'            => trim( (string) ( $input['email'] ?? '' ) ),
			'address'          => trim( (string) ( $input['address'] ?? '' ) ),
			'booking_url'      => trim( (string) ( $input['booking_url'] ?? '' ) ),
			'instagram_url'    => trim( (string) ( $input['instagram_url'] ?? '' ) ),
			'facebook_url'     => trim( (string) ( $input['facebook_url'] ?? '' ) ),
			'linkedin_url'     => trim( (string) ( $input['linkedin_url'] ?? '' ) ),
		),
		'blueprint' => array( 'slug' => trim( (string) ( $input['blueprint'] ?? '' ) ) ),
		'modules'   => $modules,
	);
}

/**
 * @param array<string,mixed> $config Validated project config.
 */
function agency_manager_save_project( array $config, string $target ): void {
	$errors = agency_factory_validate_config( $config );
	if ( $errors ) {
		throw new InvalidArgumentException( implode( ' ', $errors ) );
	}
	if ( ! array_key_exists( $config['blueprint']['slug'], agency_manager_blueprints() ) ) {
		throw new InvalidArgumentException( 'A kiválasztott blueprint nem elérhető.' );
	}
	$target    = agency_factory_validate_target( $target );
	$directory = agency_manager_project_directory( $config['site']['slug'] );
	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0775, true ) && ! is_dir( $directory ) ) {
		throw new RuntimeException( 'A projektmappa nem hozható létre.' );
	}
	agency_factory_write_json( $directory . '/config.json', $config );
	$old = agency_manager_read_json( $directory . '/manager.json' );
	agency_factory_write_json(
		$directory . '/manager.json',
		array(
			'target_path' => $target,
			'created_at'  => $old['created_at'] ?? gmdate( 'c' ),
			'updated_at'  => gmdate( 'c' ),
		)
	);
}

/**
 * @return array<string,mixed>
 */
function agency_manager_manifest( string $target ): array {
	try {
		$target = agency_factory_validate_target( $target );
	} catch ( Throwable $error ) {
		return array();
	}
	return agency_manager_read_json( $target . '/wp-content/agency-project.json' );
}

/**
 * Discover LocalWP command-line helpers without reading credentials.
 *
 * @return array<string,string>
 */
function agency_manager_runtime( string $target ): array {
	$runtime = array( 'php' => PHP_BINARY, 'wp_cli' => '', 'php_ini' => '' );
	$home    = getenv( 'LOCALAPPDATA' );
	if ( $home ) {
		$wp_cli = $home . '/Programs/Local/resources/extraResources/bin/wp-cli/wp-cli.phar';
		if ( is_file( $wp_cli ) ) {
			$runtime['wp_cli'] = realpath( $wp_cli ) ?: $wp_cli;
		}
	}
	$appdata = getenv( 'APPDATA' );
	if ( $appdata && is_dir( $appdata . '/Local/run' ) ) {
		$normalized_target = strtolower( str_replace( '\\', '/', $target ) );
		foreach ( new DirectoryIterator( $appdata . '/Local/run' ) as $site ) {
			if ( $site->isDot() || ! $site->isDir() ) {
				continue;
			}
			$nginx = $site->getPathname() . '/conf/nginx/site.conf';
			$ini   = $site->getPathname() . '/conf/php/php.ini';
			if ( is_readable( $nginx ) && is_file( $ini ) ) {
				$contents = strtolower( str_replace( '\\', '/', (string) file_get_contents( $nginx ) ) );
				if ( str_contains( $contents, $normalized_target ) ) {
					$runtime['php_ini'] = realpath( $ini ) ?: $ini;
					break;
				}
			}
		}
	}
	return $runtime;
}

/**
 * @param string[] $command Process command.
 * @return array{success:bool,exit_code:int,output:string}
 */
function agency_manager_execute( array $command, string $cwd ): array {
	$process = proc_open(
		$command,
		array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		),
		$pipes,
		$cwd,
		null,
		array( 'bypass_shell' => true )
	);
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( 'A háttérfolyamat nem indítható.' );
	}
	fclose( $pipes[0] );
	$stdout = stream_get_contents( $pipes[1] );
	$stderr = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] );
	fclose( $pipes[2] );
	$code = proc_close( $process );
	return array(
		'success'   => 0 === $code,
		'exit_code' => $code,
		'output'    => agency_manager_sanitize_log( trim( $stdout . PHP_EOL . $stderr ) ),
	);
}

function agency_manager_sanitize_log( string $log ): string {
	$log = preg_replace( '/(--?(?:password|pass|token|secret|key)=)([^\s]+)/i', '$1[REDACTED]', $log ) ?: $log;
	return preg_replace( '/("?(?:password|token|secret|api_key)"?\s*:\s*")[^"]+(")/i', '$1[REDACTED]$2', $log ) ?: $log;
}

/**
 * @param array<string,mixed> $result Execution result.
 */
function agency_manager_record_operation( string $slug, string $operation, array $result ): void {
	$path = agency_manager_project_directory( $slug ) . '/operations.json';
	$logs = agency_manager_read_json( $path );
	array_unshift(
		$logs,
		array(
			'operation' => $operation,
			'success'   => (bool) ( $result['success'] ?? false ),
			'exit_code' => (int) ( $result['exit_code'] ?? 1 ),
			'output'    => agency_manager_sanitize_log( (string) ( $result['output'] ?? '' ) ),
			'source_version' => AGENCY_FACTORY_VERSION,
			'created_at'=> gmdate( 'c' ),
		)
	);
	agency_factory_write_json( $path, array_slice( $logs, 0, 20 ) );
}

function agency_manager_has_current_dry_run( string $slug ): bool {
	$logs = agency_manager_read_json( agency_manager_project_directory( $slug ) . '/operations.json' );
	foreach ( $logs as $log ) {
		if ( 'install' === ( $log['operation'] ?? '' ) ) {
			return false;
		}
		if ( 'dry_run' === ( $log['operation'] ?? '' ) ) {
			return ! empty( $log['success'] ) && AGENCY_FACTORY_VERSION === ( $log['source_version'] ?? '' );
		}
	}
	return false;
}

function agency_manager_has_current_module_dry_run( string $slug ): bool {
	$logs = agency_manager_read_json( agency_manager_project_directory( $slug ) . '/operations.json' );
	foreach ( $logs as $log ) {
		if ( 'update_modules' === ( $log['operation'] ?? '' ) ) { return false; }
		if ( 'module_dry_run' === ( $log['operation'] ?? '' ) ) {
			return ! empty( $log['success'] ) && AGENCY_FACTORY_VERSION === ( $log['source_version'] ?? '' );
		}
	}
	return false;
}

/**
 * @return array<string,array<string,string>>
 */
function agency_manager_source_module_versions(): array {
	$versions = array();
	foreach ( AGENCY_FACTORY_MODULES as $module ) {
		$file = agency_factory_path( 'wp-content', 'plugins', "agency-module-{$module}", "agency-module-{$module}.php" );
		$data = is_readable( $file ) ? (string) file_get_contents( $file, false, null, 0, 4096 ) : '';
		preg_match( '/^[ \t*#@]*Version:\s*(.+)$/mi', $data, $match );
		$versions[ $module ] = array(
			'version'   => trim( $match[1] ?? '' ),
			'migration' => in_array( $module, array( 'booking' ), true ) ? '1.1.0' : 'not-required',
		);
	}
	return $versions;
}

/**
 * @return string[]
 */
function agency_manager_command_options( string $target ): array {
	$runtime = agency_manager_runtime( $target );
	if ( ! $runtime['wp_cli'] || ! $runtime['php_ini'] ) {
		return array();
	}
	return array( '--php=' . $runtime['php'], '--php-ini=' . $runtime['php_ini'], '--wp-cli=' . $runtime['wp_cli'] );
}

/**
 * Verify WordPress and its database before any file-changing WP-CLI install.
 *
 * @return array{success:bool,exit_code:int,output:string}
 */
function agency_manager_wp_cli_preflight( string $target ): array {
	$runtime = agency_manager_runtime( $target );
	if ( ! $runtime['wp_cli'] || ! $runtime['php_ini'] ) {
		return array(
			'success'   => false,
			'exit_code' => 1,
			'output'    => 'WP-CLI előellenőrzés sikertelen: a LocalWP WP-CLI vagy a site php.ini fájlja nem található. Kapcsold ki a WP-CLI automatizálást a fájl-only telepítéshez.',
		);
	}
	$command = array( $runtime['php'], '-c', $runtime['php_ini'], $runtime['wp_cli'], 'core', 'is-installed', '--path=' . $target );
	$result  = agency_manager_execute( $command, $target );
	if ( ! $result['success'] ) {
		$result['output'] = "WP-CLI előellenőrzés sikertelen, ezért a manager még nem készített új backupot és nem másolt fájlokat.\n"
			. "Indítsd el a site-ot a LocalWP-ben, majd próbáld újra; vagy kapcsold ki a WP-CLI automatizálást a fájl-only telepítéshez.\n\n"
			. $result['output'];
	}
	return $result;
}

/**
 * @return array<string,array{label:string,description:string}>
 */
function agency_manager_module_definitions(): array {
	return array(
		'booking'    => array( 'label' => 'Booking', 'description' => 'Foglalási és időpontkezelési production modul.', 'dependencies' => array( 'auth', 'legal', 'email-service' ) ),
		'auth'       => array( 'label' => 'Auth / Login', 'description' => 'Bejelentkezés és védett ügyfélterület alapja.', 'dependencies' => array( 'legal-recommended', 'email-service' ) ),
		'webshop'    => array( 'label' => 'Webshop', 'description' => 'Kereskedelmi és fizetési integrációs réteg.' ),
		'newsletter' => array( 'label' => 'Newsletter', 'description' => 'Hírlevél- és kampányintegrációk alapja.' ),
		'analytics'  => array( 'label' => 'Analytics', 'description' => 'Hozzájárulás-tudatos mérési integrációk.' ),
		'legal'      => array( 'label' => 'Legal', 'description' => 'Cookie- és jogi dokumentumkezelés alapja.', 'dependencies' => array() ),
	);
}

/**
 * Run a WP-CLI command against a validated LocalWP target.
 *
 * @param string[] $arguments WP command arguments.
 * @return array{success:bool,exit_code:int,output:string}
 */
function agency_manager_run_wp( string $target, array $arguments ): array {
	$runtime = agency_manager_runtime( $target );
	if ( ! $runtime['wp_cli'] || ! $runtime['php_ini'] ) {
		return array( 'success' => false, 'exit_code' => 1, 'output' => 'WP-CLI runtime is not available for this LocalWP site.' );
	}
	$command = array_merge( array( $runtime['php'], '-c', $runtime['php_ini'], $runtime['wp_cli'] ), $arguments, array( '--path=' . $target ) );
	return agency_manager_execute( $command, $target );
}

/**
 * @return array<string,string>
 */
function agency_manager_blueprints(): array {
	$base       = agency_factory_path( 'wp-content', 'plugins', 'agency-core', 'blueprints' );
	$blueprints = array();
	if ( ! is_dir( $base ) ) {
		return $blueprints;
	}
	foreach ( new DirectoryIterator( $base ) as $item ) {
		if ( $item->isDot() || ! $item->isDir() || ! agency_manager_valid_slug( $item->getFilename() ) ) {
			continue;
		}
		$manifest = agency_manager_read_json( $item->getPathname() . '/manifest.json' );
		if ( ( $manifest['slug'] ?? '' ) === $item->getFilename() ) {
			$blueprints[ $item->getFilename() ] = (string) ( $manifest['name'] ?? $item->getFilename() );
		}
	}
	ksort( $blueprints );
	return $blueprints;
}
