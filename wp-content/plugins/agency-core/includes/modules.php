<?php
/**
 * Feature module registry and administration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_get_module_registry() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$definitions = array(
		'booking'    => array( 'Booking', 'Appointment and availability workflows.', array( 'agency-core', 'auth', 'legal', 'email-service' ) ),
		'auth'       => array( 'Auth / Login', 'Client authentication and protected areas.' ),
		'webshop'    => array( 'Webshop', 'Commerce and payment integration layer.' ),
		'newsletter' => array( 'Newsletter', 'Double opt-in mailing-list subscriptions.', array( 'agency-core', 'legal', 'email-service' ) ),
		'analytics'  => array( 'Analytics', 'Consent-aware GA4 measurement.', array( 'agency-core', 'legal' ) ),
		'legal'      => array( 'Legal', 'Cookie and legal-document tooling.' ),
	);
	$registry = array();
	foreach ( $definitions as $slug => $definition ) {
		$plugin_path      = "agency-module-{$slug}/agency-module-{$slug}.php";
		$installed        = is_file( WP_PLUGIN_DIR . '/' . $plugin_path );
		$plugin_data      = $installed ? get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_path, false, false ) : array();
		$registry[ $slug ] = array(
			'slug'         => $slug,
			'label'        => $definition[0],
			'description'  => $definition[1],
			'status'       => in_array( $slug, array( 'auth', 'booking', 'newsletter', 'analytics', 'legal' ), true ) ? 'production' : 'placeholder',
			'version'      => $plugin_data['Version'] ?? '',
			'db_version'   => (string) get_option( 'agency_module_' . $slug . '_db_version', 'not-required' ),
			'plugin_path'  => $plugin_path,
			'dependencies' => $definition[2] ?? array( 'agency-core' ),
			'settings_url' => admin_url( 'admin.php?page=agency-module-' . $slug ),
			'installed'    => $installed,
			'active'       => is_plugin_active( $plugin_path ),
		);
	}
	return $registry;
}

function agency_core_sync_manifest_modules() {
	$manifest = agency_core_get_project_manifest();
	if ( ! $manifest ) {
		return;
	}
	$active = array();
	$versions = array();
	$migrations = array();
	foreach ( agency_core_get_module_registry() as $slug => $module ) {
		if ( $module['active'] ) {
			$active[] = $slug;
		}
		if ( $module['installed'] ) {
			$versions[ $slug ]   = $module['version'];
			$migrations[ $slug ] = $module['db_version'];
		}
	}
	$manifest['enabled_modules'] = $active;
	$manifest['framework_version'] = AGENCY_CORE_VERSION;
	$manifest['module_versions'] = $versions;
	$manifest['module_migrations'] = $migrations;
	$setup = array();
	foreach ( $active as $slug ) {
		if ( function_exists( 'agency_core_get_module_setup_status' ) ) {
			$status = agency_core_get_module_setup_status( $slug );
			$setup[ $slug ] = array(
				'status'     => $status['status'] ?? 'setup-required',
				'updated_at' => gmdate( 'c' ),
				'pages'      => array_filter( array_map( static fn( $page ) => absint( $page['id'] ?? 0 ), (array) ( $status['pages'] ?? array() ) ) ),
				'warnings'   => $status['warnings'] ?? array(),
				'health'     => $status['health'] ?? array(),
			);
		}
	}
	$manifest['module_setup'] = $setup;
	agency_core_write_project_manifest( $manifest );
}

function agency_core_maybe_sync_manifest_modules() {
	$manifest = agency_core_get_project_manifest();
	if ( ! $manifest ) { return; }
	$registry = agency_core_get_module_registry();
	foreach ( $registry as $slug => $module ) {
		if ( $module['installed'] && ( ( $manifest['module_versions'][ $slug ] ?? '' ) !== $module['version'] || ( $manifest['module_migrations'][ $slug ] ?? '' ) !== $module['db_version'] ) ) {
			agency_core_sync_manifest_modules();
			return;
		}
	}
}
add_action( 'init', 'agency_core_maybe_sync_manifest_modules', 30 );

function agency_core_set_module_state( $slug, $enable, $apply_setup = true ) {
	$slug     = sanitize_key( $slug );
	$registry = agency_core_get_module_registry();
	if ( ! isset( $registry[ $slug ] ) ) {
		return new WP_Error( 'agency_core_unknown_module', __( 'Unknown Agency module.', 'agency-core' ) );
	}
	$module = $registry[ $slug ];
	if ( ! $module['installed'] ) {
		return new WP_Error( 'agency_core_module_missing', __( 'The module plugin is not installed.', 'agency-core' ) );
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( $enable ) {
		$definitions = function_exists( 'agency_core_get_module_setup_definitions' ) ? agency_core_get_module_setup_definitions() : array();
		foreach ( (array) ( $definitions[ $slug ]['dependencies'] ?? array() ) as $dependency ) {
			if ( empty( $registry[ $dependency ]['installed'] ) ) {
				return new WP_Error( 'agency_core_dependency_missing', sprintf( __( 'Required module is not installed: %s', 'agency-core' ), $dependency ) );
			}
			if ( empty( $registry[ $dependency ]['active'] ) ) {
				$result = agency_core_set_module_state( $dependency, true, false );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}
		$result = activate_plugin( $module['plugin_path'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		do_action( 'agency_core_module_activated', $slug, $module );
		if ( function_exists( 'agency_core_audit_log' ) ) {
			agency_core_audit_log( $slug, 'activated' );
		}
		if ( $apply_setup && function_exists( 'agency_core_apply_module_setup' ) && isset( agency_core_get_module_setup_definitions()[ $slug ] ) ) {
			$setup = agency_core_apply_module_setup( $slug );
			if ( is_wp_error( $setup ) ) {
				return $setup;
			}
		}
	} else {
		$registry = agency_core_get_module_registry();
		if ( in_array( $slug, array( 'auth', 'legal' ), true ) && ! empty( $registry['booking']['active'] ) ) {
			return new WP_Error( 'agency_core_dependency_in_use', __( 'Booking requires Auth and Legal. Disable Booking first.', 'agency-core' ) );
		}
		deactivate_plugins( $module['plugin_path'], false, false );
		do_action( 'agency_core_module_deactivated', $slug, $module );
		if ( function_exists( 'agency_core_audit_log' ) ) {
			agency_core_audit_log( $slug, 'deactivated' );
		}
	}
	agency_core_sync_manifest_modules();
	return true;
}

function agency_core_handle_module_toggle() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_die( esc_html__( 'You do not have permission to manage modules.', 'agency-core' ) );
	}
	$slug   = isset( $_POST['module'] ) ? sanitize_key( wp_unslash( $_POST['module'] ) ) : '';
	$action = isset( $_POST['module_action'] ) ? sanitize_key( wp_unslash( $_POST['module_action'] ) ) : '';
	check_admin_referer( 'agency_core_module_' . $action . '_' . $slug, 'agency_core_module_nonce' );
	$result = agency_core_set_module_state( $slug, 'activate' === $action );
	wp_safe_redirect(
		add_query_arg(
			'agency_module_status',
			is_wp_error( $result ) ? 'error' : 'success',
			admin_url( 'admin.php?page=agency-core-modules' )
		)
	);
	exit;
}
add_action( 'admin_post_agency_core_toggle_module', 'agency_core_handle_module_toggle' );

function agency_core_render_modules_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'agency-core' ) );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Agency Modules', 'agency-core' ); ?></h1>
		<p><?php esc_html_e( 'Feature modules are isolated plugins. Production modules can add their own settings and pages to the /admin/ client portal.', 'agency-core' ); ?></p>
		<table class="widefat striped">
			<thead><tr><th><?php esc_html_e( 'Module', 'agency-core' ); ?></th><th><?php esc_html_e( 'Status', 'agency-core' ); ?></th><th><?php esc_html_e( 'Version / migration', 'agency-core' ); ?></th><th><?php esc_html_e( 'Dependencies', 'agency-core' ); ?></th><th><?php esc_html_e( 'Setup', 'agency-core' ); ?></th><th><?php esc_html_e( 'Action', 'agency-core' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( agency_core_get_module_registry() as $slug => $module ) : $setup = function_exists( 'agency_core_get_module_setup_status' ) ? agency_core_get_module_setup_status( $slug ) : array(); ?>
				<tr>
					<td><strong><?php echo esc_html( $module['label'] ); ?></strong><p><?php echo esc_html( $module['description'] ); ?></p></td>
					<td><?php echo esc_html( $module['installed'] ? ( $module['active'] ? __( 'Installed, active', 'agency-core' ) : __( 'Installed, inactive', 'agency-core' ) ) : __( 'Not installed', 'agency-core' ) ); ?></td>
					<td><code><?php echo esc_html( $module['version'] ?: '—' ); ?></code><br><?php echo esc_html( $module['db_version'] ); ?></td>
					<td><?php echo esc_html( implode( ', ', $module['dependencies'] ) ); ?></td>
					<td>
						<strong><?php echo esc_html( $setup['status'] ?? 'n/a' ); ?></strong>
						<?php if ( ! empty( $setup['missing_dependencies'] ) ) : ?><p class="description"><?php echo esc_html( __( 'Missing: ', 'agency-core' ) . implode( ', ', $setup['missing_dependencies'] ) ); ?></p><?php endif; ?>
						<?php if ( ! empty( $setup['warnings'] ) ) : ?><p class="description"><?php echo esc_html( implode( ' ', $setup['warnings'] ) ); ?></p><?php endif; ?>
						<?php if ( $module['active'] && isset( agency_core_get_module_setup_definitions()[ $slug ] ) ) : ?>
							<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
								<input type="hidden" name="action" value="agency_core_module_setup"><input type="hidden" name="module" value="<?php echo esc_attr( $slug ); ?>">
								<?php wp_nonce_field( 'agency_core_module_setup_' . $slug ); ?>
								<button class="button button-primary" name="setup_action" value="apply"><?php esc_html_e( 'Apply module setup', 'agency-core' ); ?></button>
								<?php if ( 'booking' === $slug ) : ?><button class="button" name="setup_action" value="dependencies"><?php esc_html_e( 'Apply required modules', 'agency-core' ); ?></button><?php endif; ?>
								<button class="button" name="setup_action" value="repair" onclick="return confirm('<?php echo esc_js( __( 'Repair only appends missing required shortcode content. Continue?', 'agency-core' ) ); ?>')"><?php esc_html_e( 'Repair module pages', 'agency-core' ); ?></button>
								<button class="button" name="setup_action" value="repair-display"><?php esc_html_e( 'Repair CTA/menu/footer links', 'agency-core' ); ?></button>
								<?php if ( in_array( $slug, array( 'auth', 'booking' ), true ) ) : ?><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=agency-core-email' ) ); ?>"><?php esc_html_e( 'Test email', 'agency-core' ); ?></a><?php endif; ?>
							</form>
							<?php foreach ( (array) ( $setup['pages'] ?? array() ) as $page ) : if ( empty( $page['id'] ) ) { continue; } ?>
								<a href="<?php echo esc_url( $page['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'agency-core' ); ?></a> · <a href="<?php echo esc_url( $page['view_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'agency-core' ); ?></a><br>
							<?php endforeach; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $module['installed'] && current_user_can( 'activate_plugins' ) ) : ?>
							<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
								<input type="hidden" name="action" value="agency_core_toggle_module">
								<input type="hidden" name="module" value="<?php echo esc_attr( $slug ); ?>">
								<input type="hidden" name="module_action" value="<?php echo esc_attr( $module['active'] ? 'deactivate' : 'activate' ); ?>">
								<?php wp_nonce_field( 'agency_core_module_' . ( $module['active'] ? 'deactivate' : 'activate' ) . '_' . $slug, 'agency_core_module_nonce' ); ?>
								<?php submit_button( $module['active'] ? __( 'Deactivate module', 'agency-core' ) : __( 'Activate module', 'agency-core' ), 'secondary', 'submit', false ); ?>
								<?php if ( $module['active'] ) : ?><a href="<?php echo esc_url( $module['settings_url'] ); ?>"><?php esc_html_e( 'Settings', 'agency-core' ); ?></a><?php endif; ?>
							</form>
						<?php else : ?>
							<?php esc_html_e( 'Install the module package first.', 'agency-core' ); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

function agency_core_add_modules_menu() {
	add_submenu_page( 'agency-core-settings', __( 'Modules', 'agency-core' ), __( 'Modules', 'agency-core' ), 'manage_options', 'agency-core-modules', 'agency_core_render_modules_page' );
}
add_action( 'admin_menu', 'agency_core_add_modules_menu', 21 );
