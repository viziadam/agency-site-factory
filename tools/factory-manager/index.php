<?php
/**
 * Agency Factory Manager localhost UI.
 */

declare(strict_types=1);

session_start();
require_once __DIR__ . '/manager-lib.php';

$remote = $_SERVER['REMOTE_ADDR'] ?? '';
if ( ! in_array( $remote, array( '127.0.0.1', '::1' ), true ) ) {
	http_response_code( 403 );
	exit( 'Local access only.' );
}
header( 'X-Frame-Options: DENY' );
header( 'X-Content-Type-Options: nosniff' );
header( "Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; form-action 'self'; base-uri 'none'" );
header( 'Referrer-Policy: no-referrer' );

if ( empty( $_SESSION['agency_factory_csrf'] ) ) {
	$_SESSION['agency_factory_csrf'] = bin2hex( random_bytes( 24 ) );
}

function agency_manager_e( mixed $value ): string {
	return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
}

function agency_manager_check_csrf(): void {
	$token = (string) ( $_POST['csrf'] ?? '' );
	if ( ! hash_equals( (string) $_SESSION['agency_factory_csrf'], $token ) ) {
		throw new RuntimeException( 'Lejárt vagy érvénytelen biztonsági token. Töltsd újra az oldalt.' );
	}
}

function agency_manager_redirect( string $slug, string $notice ): never {
	header( 'Location: ?project=' . rawurlencode( $slug ) . '&notice=' . rawurlencode( $notice ) );
	exit;
}

$error = '';
try {
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		agency_manager_check_csrf();
		$action = (string) ( $_POST['action'] ?? '' );
		if ( 'save_project' === $action ) {
			$config = agency_manager_build_config( $_POST );
			agency_manager_save_project( $config, (string) ( $_POST['target_path'] ?? '' ) );
			agency_manager_redirect( $config['site']['slug'], 'Projekt mentve és validálva.' );
		}

		$slug      = (string) ( $_POST['project_slug'] ?? '' );
		$directory = agency_manager_project_directory( $slug );
		$config    = agency_factory_read_config( $directory . '/config.json' );
		$meta      = agency_manager_read_json( $directory . '/manager.json' );
		$target    = agency_factory_validate_target( (string) ( $meta['target_path'] ?? '' ) );

		if ( in_array( $action, array( 'apply_module_setup', 'apply_required_modules', 'repair_module_pages', 'repair_module_display', 'module_setup_status' ), true ) ) {
			if ( 'repair_module_pages' === $action && '1' !== (string) ( $_POST['confirm_repair'] ?? '' ) ) {
				throw new RuntimeException( 'A javításhoz erősítsd meg, hogy a rendszer csak a hiányzó shortcode-ot adhatja hozzá.' );
			}
			$preflight = agency_manager_wp_cli_preflight( $target );
			if ( ! $preflight['success'] ) {
				agency_manager_record_operation( $slug, $action, $preflight );
				agency_manager_redirect( $slug, 'A LocalWP site vagy az adatbázis nem érhető el.' );
			}
			$module = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) ( $_POST['module'] ?? 'all' ) ) ) ?: 'all';
			$args   = array( 'agency', 'module', 'module_setup_status' === $action ? 'status' : 'setup' );
			if ( 'module_setup_status' !== $action ) {
				$args[] = $module;
				if ( 'repair_module_pages' === $action ) {
					$args[] = '--repair';
				} elseif ( 'repair_module_display' === $action ) {
					$args[] = '--repair-display';
				}
			}
			$result = agency_manager_run_wp( $target, $args );
			agency_manager_record_operation( $slug, $action, $result );
			agency_manager_redirect( $slug, $result['success'] ? 'A modul setup művelet sikeresen lefutott.' : 'A modul setup hibával leállt; nézd meg a naplót.' );
		}

		if ( 'check_updates' === $action ) {
			$manifest = agency_manager_manifest( $target );
			$current  = (string) ( $manifest['framework_version'] ?? '0' );
			$needed   = version_compare( $current, AGENCY_FACTORY_VERSION, '<' );
			$result   = array( 'success' => true, 'exit_code' => 0, 'output' => sprintf( 'Installed framework: %s%sAvailable source: %s%sUpdate required: %s', $current ?: 'unknown', PHP_EOL, AGENCY_FACTORY_VERSION, PHP_EOL, $needed ? 'yes' : 'no' ) );
			agency_manager_record_operation( $slug, 'check_updates', $result );
			agency_manager_redirect( $slug, $needed ? 'Új framework verzió érhető el. Futtass dry-runt.' : 'A framework naprakész.' );
		}

		if ( in_array( $action, array( 'dry_run', 'install' ), true ) ) {
			$command = array( PHP_BINARY, AGENCY_MANAGER_ROOT . '/tools/create-client-site.php', '--config=' . $directory . '/config.json', '--target=' . $target );
			if ( 'dry_run' === $action ) {
				$command[] = '--dry-run';
			} else {
				if ( ! agency_manager_has_current_dry_run( $slug ) ) {
					agency_manager_redirect( $slug, 'Update előtt kötelező egy sikeres, aktuális dry-run.' );
				}
				if ( ! empty( $_POST['use_wp_cli'] ) ) {
				$preflight = agency_manager_wp_cli_preflight( $target );
				if ( ! $preflight['success'] ) {
					agency_manager_record_operation( $slug, 'install_preflight', $preflight );
					agency_manager_redirect( $slug, 'A LocalWP site vagy az adatbázis nem érhető el; nem történt új fájlmódosítás.' );
				}
				$command = array_merge( $command, agency_manager_command_options( $target ) );
				}
			}
			$result = agency_manager_execute( $command, AGENCY_MANAGER_ROOT );
			agency_manager_record_operation( $slug, $action, $result );
			agency_manager_redirect( $slug, $result['success'] ? 'A művelet sikeresen lefutott.' : 'A művelet hibával leállt; nézd meg a naplót.' );
		}

		if ( in_array( $action, array( 'module_dry_run', 'update_modules' ), true ) ) {
			$selected = array_values( array_intersect( AGENCY_FACTORY_MODULES, (array) ( $_POST['modules'] ?? array() ) ) );
			if ( in_array( 'booking', $selected, true ) ) {
				$selected = array_values( array_unique( array_merge( $selected, array( 'auth', 'legal' ) ) ) );
			}
			if ( 'update_modules' === $action && ! agency_manager_has_current_module_dry_run( $slug ) ) {
				agency_manager_redirect( $slug, 'Modulupdate előtt kötelező az aktuális Module dry-run.' );
			}
			if ( 'update_modules' === $action && ! empty( $_POST['use_wp_cli'] ) ) {
				$preflight = agency_manager_wp_cli_preflight( $target );
				if ( ! $preflight['success'] ) {
					agency_manager_record_operation( $slug, 'module_preflight', $preflight );
					agency_manager_redirect( $slug, 'A LocalWP site vagy az adatbázis nem érhető el; a modulállapot nem változott.' );
				}
			}
			foreach ( AGENCY_FACTORY_MODULES as $module ) {
				$config['modules'][ $module ] = in_array( $module, $selected, true );
			}
			if ( 'update_modules' === $action ) {
				agency_factory_write_json( $directory . '/config.json', $config );
			}
			$active  = (array) ( agency_manager_manifest( $target )['enabled_modules'] ?? array() );
			$disable = array_values( array_diff( $active, $selected ) );
			$command = array(
				PHP_BINARY,
				AGENCY_MANAGER_ROOT . '/tools/update-client-modules.php',
				'--target=' . $target,
				'--enable=' . implode( ',', $selected ),
				'--disable=' . implode( ',', $disable ),
			);
			if ( 'module_dry_run' === $action ) {
				$command[] = '--dry-run';
			}
			if ( 'update_modules' === $action && ! empty( $_POST['use_wp_cli'] ) ) {
				$command = array_merge( $command, agency_manager_command_options( $target ) );
			}
			$result  = agency_manager_execute( $command, AGENCY_MANAGER_ROOT );
			agency_manager_record_operation( $slug, $action, $result );
			agency_manager_redirect( $slug, $result['success'] ? ( 'module_dry_run' === $action ? 'A module dry-run elkészült.' : 'A modulcsomagok frissítése elkészült.' ) : 'A modulművelet hibával leállt; nézd meg a naplót.' );
		}
	}
} catch ( Throwable $exception ) {
	$error = $exception->getMessage();
}

$projects     = agency_manager_projects();
$selected_key = (string) ( $_GET['project'] ?? '' );
$selected     = isset( $projects[ $selected_key ] ) && empty( $projects[ $selected_key ]['error'] ) ? $projects[ $selected_key ] : null;
$new_project  = isset( $_GET['new'] );
$edit_project = isset( $_GET['edit'] );
$editing      = $new_project ? null : ( $edit_project ? $selected : null );
$config       = $selected['config'] ?? array();
$meta         = $selected['meta'] ?? array();
$manifest     = $selected['manifest'] ?? array();
$logs         = is_array( $selected['logs'] ?? null ) ? $selected['logs'] : array();
$module_defs  = agency_manager_module_definitions();
$blueprints   = agency_manager_blueprints();
$source_modules = agency_manager_source_module_versions();
$target       = (string) ( $meta['target_path'] ?? '' );
$runtime      = $target ? agency_manager_runtime( $target ) : array();
$notice       = (string) ( $_GET['notice'] ?? '' );
?>
<!doctype html>
<html lang="hu">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Agency Factory Manager</title>
	<link rel="stylesheet" href="assets/style.css">
</head>
<body>
	<header class="topbar">
		<a class="brand" href="./"><span>AF</span> Agency Factory Manager</a>
		<a class="button button-primary" href="?new=1">+ Új projekt</a>
	</header>
	<div class="shell">
		<aside class="sidebar">
			<p class="eyebrow">Client projektek</p>
			<nav>
				<?php if ( ! $projects ) : ?><p class="muted">Még nincs projekt.</p><?php endif; ?>
				<?php foreach ( $projects as $slug => $project ) : ?>
					<a class="<?php echo $slug === $selected_key ? 'active' : ''; ?>" href="?project=<?php echo agency_manager_e( $slug ); ?>">
						<strong><?php echo agency_manager_e( $project['config']['site']['name'] ?? $slug ); ?></strong>
						<small><?php echo agency_manager_e( $slug ); ?></small>
					</a>
				<?php endforeach; ?>
			</nav>
		</aside>
		<main class="content">
			<?php if ( $notice ) : ?><div class="notice success"><?php echo agency_manager_e( $notice ); ?></div><?php endif; ?>
			<?php if ( $error ) : ?><div class="notice error"><?php echo agency_manager_e( $error ); ?></div><?php endif; ?>

			<?php if ( $new_project || $editing ) : ?>
				<section class="hero">
					<p class="eyebrow"><?php echo $new_project ? 'Új client site' : 'Projekt konfiguráció'; ?></p>
					<h1><?php echo $new_project ? 'Projektwizard' : agency_manager_e( $config['site']['name'] ?? '' ); ?></h1>
					<p>A form validált JSON configot készít; kézi fájlszerkesztés nem szükséges.</p>
				</section>
				<form method="post" class="panel form-grid">
					<input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>">
					<input type="hidden" name="action" value="save_project">
					<h2>Projekt és célsite</h2>
					<label>Projekt neve<input required name="project_name" value="<?php echo agency_manager_e( $config['site']['name'] ?? '' ); ?>"></label>
					<label>Projekt slug<input required pattern="[a-z0-9]+(?:-[a-z0-9]+)*" name="project_slug" value="<?php echo agency_manager_e( $config['site']['slug'] ?? '' ); ?>" <?php echo $editing ? 'readonly' : ''; ?>></label>
					<label class="wide">WordPress public mappa<input required name="target_path" value="<?php echo agency_manager_e( $target ); ?>" placeholder="C:\Users\...\Local Sites\client\app\public"></label>
					<label>Local domain<input required name="local_domain" value="<?php echo agency_manager_e( $config['site']['local_domain'] ?? '' ); ?>" placeholder="client.local"></label>
					<label>Nyelv<input required name="language" value="<?php echo agency_manager_e( $config['site']['language'] ?? 'hu_HU' ); ?>"></label>
					<label>Admin felhasználó<input required name="admin_user" value="<?php echo agency_manager_e( $config['site']['admin_user'] ?? 'admin' ); ?>"></label>
					<label>Admin e-mail<input required type="email" name="admin_email" value="<?php echo agency_manager_e( $config['site']['admin_email'] ?? '' ); ?>"></label>

					<h2>Márka</h2>
					<label>Márkanév<input required name="brand_name" value="<?php echo agency_manager_e( $config['brand']['name'] ?? '' ); ?>"></label>
					<label>Telefon<input required name="phone" value="<?php echo agency_manager_e( $config['brand']['phone'] ?? '' ); ?>"></label>
					<label>E-mail<input required type="email" name="email" value="<?php echo agency_manager_e( $config['brand']['email'] ?? '' ); ?>"></label>
					<label>Cím<input required name="address" value="<?php echo agency_manager_e( $config['brand']['address'] ?? '' ); ?>"></label>
					<label>Primary szín<input required type="color" name="primary_color" value="<?php echo agency_manager_e( $config['brand']['primary_color'] ?? '#d4ad32' ); ?>"></label>
					<label>Secondary szín<input required type="color" name="secondary_color" value="<?php echo agency_manager_e( $config['brand']['secondary_color'] ?? '#17110d' ); ?>"></label>
					<label>Background szín<input required type="color" name="background_color" value="<?php echo agency_manager_e( $config['brand']['background_color'] ?? '#fffaf4' ); ?>"></label>
					<label>Booking URL<input required name="booking_url" value="<?php echo agency_manager_e( $config['brand']['booking_url'] ?? '/kapcsolat/' ); ?>"></label>
					<label>Instagram URL<input name="instagram_url" value="<?php echo agency_manager_e( $config['brand']['instagram_url'] ?? '' ); ?>"></label>
					<label>Facebook URL<input name="facebook_url" value="<?php echo agency_manager_e( $config['brand']['facebook_url'] ?? '' ); ?>"></label>
					<label>LinkedIn URL<input name="linkedin_url" value="<?php echo agency_manager_e( $config['brand']['linkedin_url'] ?? '' ); ?>"></label>

					<h2>Blueprint és modulok</h2>
					<label>Blueprint<select name="blueprint"><?php foreach ( $blueprints as $slug => $name ) : ?><option value="<?php echo agency_manager_e( $slug ); ?>" <?php echo $slug === ( $config['blueprint']['slug'] ?? '' ) ? 'selected' : ''; ?>><?php echo agency_manager_e( $name ); ?></option><?php endforeach; ?></select></label>
					<div class="wide module-picker">
						<?php foreach ( $module_defs as $slug => $module ) : ?>
							<label class="check-card"><input type="checkbox" name="modules[]" value="<?php echo agency_manager_e( $slug ); ?>" <?php echo ! empty( $config['modules'][ $slug ] ) ? 'checked' : ''; ?>><span><strong><?php echo agency_manager_e( $module['label'] ); ?></strong><small><?php echo agency_manager_e( $module['description'] ); ?></small></span></label>
						<?php endforeach; ?>
					</div>
					<div class="wide actions"><button class="button button-primary" type="submit">Projekt mentése és validálása</button></div>
				</form>
			<?php endif; ?>

			<?php if ( $selected && ! $new_project && ! $edit_project ) : ?>
				<section class="hero">
					<p class="eyebrow">Projekt dashboard</p>
					<h1><?php echo agency_manager_e( $selected['config']['site']['name'] ); ?></h1>
					<p><code><?php echo agency_manager_e( $target ); ?></code></p>
					<p><a href="?project=<?php echo agency_manager_e( $selected_key ); ?>&amp;edit=1">Projektkonfiguráció szerkesztése →</a></p>
				</section>
				<div class="stats">
					<div><span>Státusz</span><strong><?php echo $manifest ? 'Telepítve' : 'Nincs manifest'; ?></strong></div>
					<div><span>Framework</span><strong><?php echo agency_manager_e( $manifest['framework_version'] ?? '—' ); ?></strong></div>
					<div><span>Blueprint</span><strong><?php echo agency_manager_e( $manifest['blueprint'] ?? $selected['config']['blueprint']['slug'] ); ?></strong></div>
					<div><span>Frissítve</span><strong><?php echo agency_manager_e( $manifest['updated_at'] ?? '—' ); ?></strong></div>
					<div><span>Elérhető forrás</span><strong><?php echo agency_manager_e( AGENCY_FACTORY_VERSION ); ?></strong></div>
					<div><span>Update</span><strong><?php echo version_compare( (string) ( $manifest['framework_version'] ?? '0' ), AGENCY_FACTORY_VERSION, '<' ) ? 'Szükséges' : 'Naprakész'; ?></strong></div>
				</div>
				<section class="panel">
					<h2>Telepítés és framework update</h2>
					<p>A dry-run nem ír fájlt. Az install/update backupolja a meglévő Agency mappákat, majd telepíti a frameworköt és a kiválasztott modulokat.</p>
					<div class="runtime <?php echo ! empty( $runtime['wp_cli'] ) && ! empty( $runtime['php_ini'] ) ? 'ready' : ''; ?>">
						<strong>WP-CLI automatizálás:</strong>
						<?php echo ! empty( $runtime['wp_cli'] ) && ! empty( $runtime['php_ini'] ) ? 'elérhető – aktiválás, blueprint és settings is automatikus.' : 'nem észlelhető – a fájltelepítés után a log manuális adminlépéseket ad.'; ?>
					</div>
					<div class="actions">
						<form method="post"><input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>"><input type="hidden" name="project_slug" value="<?php echo agency_manager_e( $selected_key ); ?>"><input type="hidden" name="action" value="check_updates"><button class="button" type="submit">Check updates</button></form>
						<form method="post"><input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>"><input type="hidden" name="project_slug" value="<?php echo agency_manager_e( $selected_key ); ?>"><input type="hidden" name="action" value="dry_run"><button class="button" type="submit">Dry-run indítása</button></form>
						<form method="post" class="action-form"><input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>"><input type="hidden" name="project_slug" value="<?php echo agency_manager_e( $selected_key ); ?>"><input type="hidden" name="action" value="install"><?php if ( ! empty( $runtime['wp_cli'] ) && ! empty( $runtime['php_ini'] ) ) : ?><label class="inline-check"><input type="checkbox" name="use_wp_cli" value="1" checked> WP-CLI automatizálás (a Local site fusson)</label><?php endif; ?><button class="button button-primary" type="submit">Install / update indítása</button></form>
					</div>
				</section>
				<section class="panel">
					<h2>Modulkezelés</h2>
					<form method="post">
						<input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>">
						<input type="hidden" name="project_slug" value="<?php echo agency_manager_e( $selected_key ); ?>">
						<?php if ( ! empty( $runtime['wp_cli'] ) && ! empty( $runtime['php_ini'] ) ) : ?><label class="inline-check"><input type="checkbox" name="use_wp_cli" value="1" checked> Aktiválás/deaktiválás WP-CLI-vel (a Local site fusson)</label><?php endif; ?>
						<div class="module-table">
							<?php foreach ( $module_defs as $slug => $module ) :
								$installed = is_dir( $target . '/wp-content/plugins/agency-module-' . $slug );
								$active    = in_array( $slug, (array) ( $manifest['enabled_modules'] ?? array() ), true );
								$installed_version = (string) ( $manifest['module_versions'][ $slug ] ?? 'unknown' );
								$migration_status  = (string) ( $manifest['module_migrations'][ $slug ] ?? 'unknown' );
								?>
								<label>
									<input type="checkbox" name="modules[]" value="<?php echo agency_manager_e( $slug ); ?>" <?php echo ! empty( $selected['config']['modules'][ $slug ] ) ? 'checked' : ''; ?>>
									<span><strong><?php echo agency_manager_e( $module['label'] ); ?></strong><small><?php echo agency_manager_e( $module['description'] ); ?></small></span>
									<em class="<?php echo $installed ? 'ok' : ''; ?>"><?php echo $installed ? 'telepítve' : 'elérhető'; ?><br>installed <?php echo agency_manager_e( $installed_version ); ?><br>source <?php echo agency_manager_e( $source_modules[ $slug ]['version'] ); ?><br>migration <?php echo agency_manager_e( $migration_status ); ?></em>
									<em class="<?php echo $active ? 'ok' : ''; ?>"><?php echo $active ? 'aktív' : 'inaktív'; ?></em>
									<em>dependency: agency-core<?php if ( ! empty( $module['dependencies'] ) ) : ?>, <?php echo agency_manager_e( implode( ', ', $module['dependencies'] ) ); ?><?php endif; ?><?php if ( $installed ) : ?><br><a href="<?php echo agency_manager_e( 'http://' . $selected['config']['site']['local_domain'] . '/wp-admin/admin.php?page=agency-module-' . $slug ); ?>" target="_blank" rel="noopener">Beállítások ↗</a><?php endif; ?></em>
								</label>
							<?php endforeach; ?>
						</div>
						<div class="actions"><button class="button" name="action" value="module_dry_run" type="submit">Module dry-run</button><button class="button button-primary" name="action" value="update_modules" type="submit">Modulok telepítése / frissítése</button></div>
					</form>
					<h3>Modul setup állapot</h3>
					<p>A Booking automatikusan megköveteli az Auth és Legal modulokat. Az email fallback működik, de production-ready állapothoz sikeres tesztlevél szükséges.</p>
					<div class="module-table">
						<?php foreach ( array( 'auth', 'booking', 'legal' ) as $slug ) :
							$setup = (array) ( $manifest['module_setup'][ $slug ] ?? array() );
							$module = $module_defs[ $slug ];
							?>
							<div class="manager-module-setup">
								<strong><?php echo agency_manager_e( $module['label'] ); ?></strong>
								<span>setup: <?php echo agency_manager_e( $setup['status'] ?? 'setup-required' ); ?></span>
								<span>pages: <?php echo agency_manager_e( count( (array) ( $setup['pages'] ?? array() ) ) ); ?></span>
								<?php if ( ! empty( $setup['health'] ) ) : ?><span><?php foreach ( $setup['health'] as $health_key => $health_ok ) : ?><small class="<?php echo $health_ok ? 'ok' : 'bad'; ?>"><?php echo agency_manager_e( str_replace( '_', ' ', $health_key ) ); ?> <?php echo $health_ok ? '✓' : '✕'; ?></small> <?php endforeach; ?></span><?php endif; ?>
								<form method="post"><input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>"><input type="hidden" name="project_slug" value="<?php echo agency_manager_e( $selected_key ); ?>"><input type="hidden" name="module" value="<?php echo agency_manager_e( $slug ); ?>"><button class="button button-primary" name="action" value="apply_module_setup">Apply module setup</button></form>
								<?php if ( 'booking' === $slug ) : ?><form method="post"><input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>"><input type="hidden" name="project_slug" value="<?php echo agency_manager_e( $selected_key ); ?>"><input type="hidden" name="module" value="booking"><button class="button" name="action" value="apply_required_modules">Auto-enable dependencies</button></form><?php endif; ?>
								<form method="post"><input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>"><input type="hidden" name="project_slug" value="<?php echo agency_manager_e( $selected_key ); ?>"><input type="hidden" name="module" value="<?php echo agency_manager_e( $slug ); ?>"><label class="inline-check"><input required type="checkbox" name="confirm_repair" value="1"> Megerősítem: csak a hiányzó shortcode kerüljön hozzáadásra.</label><button class="button" name="action" value="repair_module_pages">Repair module pages</button></form>
								<form method="post"><input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>"><input type="hidden" name="project_slug" value="<?php echo agency_manager_e( $selected_key ); ?>"><input type="hidden" name="module" value="<?php echo agency_manager_e( $slug ); ?>"><button class="button" name="action" value="repair_module_display">Repair CTA/menu/footer</button></form>
								<a class="button" target="_blank" rel="noopener" href="<?php echo agency_manager_e( 'http://' . $selected['config']['site']['local_domain'] . '/wp-admin/admin.php?page=agency-module-' . $slug ); ?>">Open module admin</a>
								<?php if ( in_array( $slug, array( 'auth', 'booking' ), true ) ) : ?><a class="button" target="_blank" rel="noopener" href="<?php echo agency_manager_e( 'http://' . $selected['config']['site']['local_domain'] . '/wp-admin/admin.php?page=agency-core-email' ); ?>">Test email</a><?php endif; ?>
								<?php $page_slugs = array( 'auth' => array( 'login' => 'bejelentkezes', 'register' => 'regisztracio', 'account' => 'fiokom' ), 'booking' => array( 'booking' => 'idopontfoglalas', 'customer_list' => 'foglalasaim' ), 'legal' => array( 'privacy' => 'adatkezelesi-tajekoztato', 'cookie' => 'cookie-tajekoztato', 'imprint' => 'impresszum' ) ); foreach ( $page_slugs[ $slug ] as $page_key => $page_slug ) : ?><span><a target="_blank" rel="noopener" href="<?php echo agency_manager_e( 'http://' . $selected['config']['site']['local_domain'] . '/' . $page_slug . '/' ); ?>">View <?php echo agency_manager_e( $page_slug ); ?></a><?php if ( ! empty( $setup['pages'][ $page_key ] ) ) : ?> · <a target="_blank" rel="noopener" href="<?php echo agency_manager_e( 'http://' . $selected['config']['site']['local_domain'] . '/wp-admin/post.php?post=' . (int) $setup['pages'][ $page_key ] . '&action=edit' ); ?>">Edit</a><?php endif; ?></span><?php endforeach; ?>
							</div>
						<?php endforeach; ?>
					</div>
					<form method="post" class="actions"><input type="hidden" name="csrf" value="<?php echo agency_manager_e( $_SESSION['agency_factory_csrf'] ); ?>"><input type="hidden" name="project_slug" value="<?php echo agency_manager_e( $selected_key ); ?>"><button class="button" name="action" value="module_setup_status">Setup státusz frissítése</button></form>
				</section>
				<section class="panel logs">
					<h2>Műveleti napló</h2>
					<?php if ( ! $logs ) : ?><p class="muted">Még nincs művelet.</p><?php endif; ?>
					<?php foreach ( $logs as $log ) : ?>
						<details <?php echo $log === reset( $logs ) ? 'open' : ''; ?>>
							<summary><span class="status <?php echo ! empty( $log['success'] ) ? 'ok' : 'bad'; ?>"></span><strong><?php echo agency_manager_e( $log['operation'] ?? '' ); ?></strong><time><?php echo agency_manager_e( $log['created_at'] ?? '' ); ?></time></summary>
							<pre><?php echo agency_manager_e( $log['output'] ?? '' ); ?></pre>
						</details>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<?php if ( ! $new_project && ! $selected ) : ?>
				<section class="empty"><div class="factory-mark">AF</div><h1>Agency Factory Manager</h1><p>Válassz egy projektet, vagy indítsd el az első project wizardot.</p><a class="button button-primary" href="?new=1">Új projekt létrehozása</a></section>
			<?php endif; ?>
		</main>
	</div>
</body>
</html>
