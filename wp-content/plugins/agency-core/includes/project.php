<?php
/**
 * Client project manifest and configuration support.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_project_manifest_path() {
	return WP_CONTENT_DIR . '/agency-project.json';
}

function agency_core_get_project_manifest() {
	$path = agency_core_project_manifest_path();
	if ( ! is_readable( $path ) ) {
		return array();
	}
	$manifest = json_decode( (string) file_get_contents( $path ), true );
	return is_array( $manifest ) ? $manifest : array();
}

function agency_core_write_project_manifest( $manifest ) {
	$allowed = array( 'project_slug', 'framework_version', 'blueprint', 'enabled_modules', 'module_versions', 'module_migrations', 'module_setup', 'created_at', 'updated_at' );
	$clean   = array_intersect_key( (array) $manifest, array_flip( $allowed ) );
	$clean['project_slug']      = sanitize_key( $clean['project_slug'] ?? '' );
	$clean['framework_version'] = sanitize_text_field( $clean['framework_version'] ?? AGENCY_CORE_VERSION );
	$clean['blueprint']         = sanitize_key( $clean['blueprint'] ?? '' );
	$clean['enabled_modules']   = array_values( array_filter( array_map( 'sanitize_key', (array) ( $clean['enabled_modules'] ?? array() ) ) ) );
	$clean['module_versions']   = array_map( 'sanitize_text_field', (array) ( $clean['module_versions'] ?? array() ) );
	$clean['module_migrations'] = array_map( 'sanitize_text_field', (array) ( $clean['module_migrations'] ?? array() ) );
	$clean['module_setup']      = array_map(
		static function ( $status ) {
			return array(
				'status'     => sanitize_key( $status['status'] ?? 'setup-required' ),
				'updated_at' => sanitize_text_field( $status['updated_at'] ?? '' ),
				'pages'      => array_map( 'absint', (array) ( $status['pages'] ?? array() ) ),
				'warnings'   => array_map( 'sanitize_text_field', (array) ( $status['warnings'] ?? array() ) ),
				'health'     => array_map( 'rest_sanitize_boolean', (array) ( $status['health'] ?? array() ) ),
			);
		},
		(array) ( $clean['module_setup'] ?? array() )
	);
	$clean['created_at']        = sanitize_text_field( $clean['created_at'] ?? gmdate( 'c' ) );
	$clean['updated_at']        = gmdate( 'c' );
	$json = wp_json_encode( $clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( ! $json || false === file_put_contents( agency_core_project_manifest_path(), $json . PHP_EOL, LOCK_EX ) ) {
		return new WP_Error( 'agency_core_manifest_write_failed', __( 'The project manifest could not be written.', 'agency-core' ) );
	}
	return $clean;
}

function agency_core_validate_project_config( $config ) {
	if ( ! is_array( $config ) ) {
		return new WP_Error( 'agency_core_invalid_project_config', __( 'Project config must be a JSON object.', 'agency-core' ) );
	}
	$required = array(
		'site'      => array( 'name', 'slug', 'local_domain', 'admin_user', 'admin_email', 'language' ),
		'brand'     => array( 'name', 'primary_color', 'secondary_color', 'background_color', 'phone', 'email', 'address', 'booking_url', 'instagram_url' ),
		'blueprint' => array( 'slug' ),
	);
	foreach ( $required as $group => $fields ) {
		if ( ! isset( $config[ $group ] ) || ! is_array( $config[ $group ] ) ) {
			return new WP_Error( 'agency_core_invalid_project_config', sprintf( __( 'Missing config group: %s.', 'agency-core' ), $group ) );
		}
		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $config[ $group ] ) || ! is_string( $config[ $group ][ $field ] ) ) {
				return new WP_Error( 'agency_core_invalid_project_config', sprintf( __( 'Missing config field: %1$s.%2$s.', 'agency-core' ), $group, $field ) );
			}
		}
	}
	if ( sanitize_key( $config['site']['slug'] ) !== $config['site']['slug'] || sanitize_key( $config['blueprint']['slug'] ) !== $config['blueprint']['slug'] ) {
		return new WP_Error( 'agency_core_invalid_project_config', __( 'Project and blueprint slugs are invalid.', 'agency-core' ) );
	}
	if ( ! is_email( $config['site']['admin_email'] ) || ! is_email( $config['brand']['email'] ) ) {
		return new WP_Error( 'agency_core_invalid_project_config', __( 'Project email fields must contain valid email addresses.', 'agency-core' ) );
	}
	foreach ( array( 'primary_color', 'secondary_color', 'background_color' ) as $color ) {
		if ( ! sanitize_hex_color( $config['brand'][ $color ] ) ) {
			return new WP_Error( 'agency_core_invalid_project_config', sprintf( __( 'Invalid brand color: %s.', 'agency-core' ), $color ) );
		}
	}
	return true;
}

function agency_core_apply_project_config( $config ) {
	$valid = agency_core_validate_project_config( $config );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$brand   = $config['brand'];
	$current = get_option( 'agency_core_settings', array() );
	$mapping = array(
		'brand_name'       => 'name',
		'primary_color'    => 'primary_color',
		'secondary_color'  => 'secondary_color',
		'background_color' => 'background_color',
		'phone'            => 'phone',
		'email'            => 'email',
		'address'          => 'address',
		'booking_url'      => 'booking_url',
		'instagram_url'    => 'instagram_url',
		'facebook_url'     => 'facebook_url',
		'linkedin_url'     => 'linkedin_url',
		'price_range'      => 'price_range',
		'localbusiness_type' => 'localbusiness_type',
	);
	foreach ( $mapping as $setting => $brand_key ) {
		if ( array_key_exists( $brand_key, $brand ) ) {
			$current[ $setting ] = $brand[ $brand_key ];
		}
	}
	update_option( 'agency_core_settings', agency_core_sanitize_settings( $current ) );
	update_option( 'WPLANG', sanitize_text_field( $config['site']['language'] ) );

	$manifest = agency_core_get_project_manifest();
	$manifest['project_slug']      = sanitize_key( $config['site']['slug'] );
	$manifest['framework_version'] = AGENCY_CORE_VERSION;
	$manifest['blueprint']         = sanitize_key( $config['blueprint']['slug'] );
	$manifest['created_at']        = $manifest['created_at'] ?? gmdate( 'c' );
	$manifest['enabled_modules']   = $manifest['enabled_modules'] ?? array();
	return agency_core_write_project_manifest( $manifest );
}

function agency_core_render_project_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'agency-core' ) );
	}
	$manifest = agency_core_get_project_manifest();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Agency Project', 'agency-core' ); ?></h1>
		<?php if ( ! $manifest ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'No agency-project.json manifest was found. Install this site with the client site generator to connect it to a project.', 'agency-core' ); ?></p></div>
		<?php else : ?>
			<table class="widefat striped" style="max-width:800px">
				<tbody>
					<tr><th><?php esc_html_e( 'Project slug', 'agency-core' ); ?></th><td><code><?php echo esc_html( $manifest['project_slug'] ?? '' ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Framework version', 'agency-core' ); ?></th><td><?php echo esc_html( $manifest['framework_version'] ?? '' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Blueprint', 'agency-core' ); ?></th><td><?php echo esc_html( $manifest['blueprint'] ?? '' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Enabled modules', 'agency-core' ); ?></th><td><?php echo esc_html( implode( ', ', (array) ( $manifest['enabled_modules'] ?? array() ) ) ?: __( 'None', 'agency-core' ) ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Module versions', 'agency-core' ); ?></th><td><code><?php echo esc_html( wp_json_encode( $manifest['module_versions'] ?? array() ) ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Module migrations', 'agency-core' ); ?></th><td><code><?php echo esc_html( wp_json_encode( $manifest['module_migrations'] ?? array() ) ); ?></code></td></tr>
					<tr><th><?php esc_html_e( 'Last updated', 'agency-core' ); ?></th><td><?php echo esc_html( $manifest['updated_at'] ?? '' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'Site status', 'agency-core' ); ?></th><td><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> <?php esc_html_e( 'Connected', 'agency-core' ); ?></td></tr>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

function agency_core_add_project_menu() {
	add_submenu_page( 'agency-core-settings', __( 'Project', 'agency-core' ), __( 'Project', 'agency-core' ), 'manage_options', 'agency-core-project', 'agency_core_render_project_page' );
}
add_action( 'admin_menu', 'agency_core_add_project_menu', 20 );
