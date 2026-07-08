<?php
/**
 * Agency Kit settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_settings_fields() {
	$header_options = array(
		'default'  => __( 'Default', 'agency-core' ),
		'centered' => __( 'Centered', 'agency-core' ),
		'compact'  => __( 'Compact', 'agency-core' ),
		'split'    => __( 'Split with CTA', 'agency-core' ),
	);
	$registry = agency_core_get_component_registry();
	if ( isset( $registry['header']['variants'] ) ) {
		$header_options = array();
		foreach ( $registry['header']['variants'] as $variant => $definition ) {
			$header_options[ $variant ] = $definition['label'] ?? $variant;
		}
	}

	return array(
		'brand_name'         => array( __( 'Brand name', 'agency-core' ), 'text' ),
		'primary_color'      => array( __( 'Primary color', 'agency-core' ), 'color' ),
		'secondary_color'    => array( __( 'Secondary color', 'agency-core' ), 'color' ),
		'background_color'   => array( __( 'Background color', 'agency-core' ), 'color' ),
		'phone'              => array( __( 'Phone', 'agency-core' ), 'text' ),
		'email'              => array( __( 'Email', 'agency-core' ), 'email' ),
		'address'            => array( __( 'Full address fallback', 'agency-core' ), 'text' ),
		'street_address'     => array( __( 'Street address', 'agency-core' ), 'text' ),
		'postal_code'        => array( __( 'Postal code', 'agency-core' ), 'text' ),
		'locality'           => array( __( 'City / locality', 'agency-core' ), 'text' ),
		'region'             => array( __( 'Region', 'agency-core' ), 'text' ),
		'country_code'       => array( __( 'Country code', 'agency-core' ), 'text' ),
		'latitude'           => array( __( 'Latitude', 'agency-core' ), 'decimal' ),
		'longitude'          => array( __( 'Longitude', 'agency-core' ), 'decimal' ),
		'opening_hours'      => array( __( 'Opening hours (schema.org format)', 'agency-core' ), 'textarea' ),
		'price_range'        => array( __( 'Price range', 'agency-core' ), 'text' ),
		'default_currency'   => array( __( 'Default currency', 'agency-core' ), 'text' ),
		'booking_url'        => array( __( 'Booking URL', 'agency-core' ), 'url' ),
		'facebook_url'       => array( __( 'Facebook URL', 'agency-core' ), 'url' ),
		'instagram_url'      => array( __( 'Instagram URL', 'agency-core' ), 'url' ),
		'linkedin_url'       => array( __( 'LinkedIn URL', 'agency-core' ), 'url' ),
		'seo_title_suffix'   => array( __( 'Default SEO title suffix', 'agency-core' ), 'text' ),
		'localbusiness_type' => array( __( 'LocalBusiness type', 'agency-core' ), 'text' ),
		'header_variant'     => array( __( 'Header variant', 'agency-core' ), 'select', $header_options ),
		'schema_mode'        => array(
			__( 'Schema output', 'agency-core' ),
			'select',
			array(
				'auto'     => __( 'Auto – disable when an SEO plugin is detected', 'agency-core' ),
				'force'    => __( 'Always output Agency Core schema', 'agency-core' ),
				'disabled' => __( 'Disabled', 'agency-core' ),
			),
		),
	);
}

function agency_core_add_admin_menu() {
	add_menu_page( 'Agency Kit', 'Agency Kit', 'manage_options', 'agency-core-settings', 'agency_core_render_settings_page', 'dashicons-layout', 58 );
	add_submenu_page( 'agency-core-settings', 'Settings', 'Settings', 'manage_options', 'agency-core-settings', 'agency_core_render_settings_page' );
	add_submenu_page( 'agency-core-settings', 'Import Blueprint', 'Import Blueprint', 'manage_options', 'agency-core-import', 'agency_core_render_import_page' );
}
add_action( 'admin_menu', 'agency_core_add_admin_menu' );

function agency_core_register_settings() {
	register_setting(
		'agency_core_settings_group',
		'agency_core_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'agency_core_sanitize_settings',
			'default'           => array(),
		)
	);
}
add_action( 'admin_init', 'agency_core_register_settings' );

function agency_core_enqueue_admin_styles( $hook ) {
	if ( ! str_contains( $hook, 'agency-core' ) ) {
		return;
	}
	wp_enqueue_style( 'agency-core-admin', plugins_url( 'assets/css/admin.css', AGENCY_CORE_FILE ), array(), AGENCY_CORE_VERSION );
}
add_action( 'admin_enqueue_scripts', 'agency_core_enqueue_admin_styles' );

function agency_core_sanitize_settings( $input ) {
	$output = array();
	foreach ( agency_core_settings_fields() as $key => $field ) {
		$value = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : '';
		switch ( $field[1] ) {
			case 'email':
				$output[ $key ] = sanitize_email( $value );
				break;
			case 'url':
				$output[ $key ] = esc_url_raw( $value );
				break;
			case 'color':
				$output[ $key ] = sanitize_hex_color( $value ) ?: '';
				break;
			case 'decimal':
				$output[ $key ] = is_numeric( $value ) ? (string) (float) $value : '';
				break;
			case 'textarea':
				$output[ $key ] = sanitize_textarea_field( $value );
				break;
			case 'select':
				$fallback       = 'header_variant' === $key ? 'default' : 'auto';
				$output[ $key ] = isset( $field[2][ $value ] ) ? sanitize_key( $value ) : $fallback;
				break;
			default:
				$output[ $key ] = sanitize_text_field( $value );
		}
	}
	return $output;
}

function agency_core_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You do not have permission to access this page.', 'agency-core' ) );
	}
	$settings = get_option( 'agency_core_settings', array() );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Agency Kit Settings', 'agency-core' ); ?></h1>
		<form action="options.php" method="post">
			<?php settings_fields( 'agency_core_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( agency_core_settings_fields() as $key => $field ) : ?>
					<tr>
						<th scope="row"><label for="agency-core-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
						<td>
							<?php if ( 'textarea' === $field[1] ) : ?>
								<textarea class="large-text" rows="3" id="agency-core-<?php echo esc_attr( $key ); ?>" name="agency_core_settings[<?php echo esc_attr( $key ); ?>]"><?php echo esc_textarea( $settings[ $key ] ?? '' ); ?></textarea>
							<?php elseif ( 'select' === $field[1] ) : ?>
								<select id="agency-core-<?php echo esc_attr( $key ); ?>" name="agency_core_settings[<?php echo esc_attr( $key ); ?>]">
									<?php foreach ( $field[2] as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings[ $key ] ?? 'auto', $value ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							<?php else : ?>
								<input class="regular-text" id="agency-core-<?php echo esc_attr( $key ); ?>" name="agency_core_settings[<?php echo esc_attr( $key ); ?>]" type="<?php echo esc_attr( 'decimal' === $field[1] ? 'number' : $field[1] ); ?>" <?php echo 'decimal' === $field[1] ? 'step="any"' : ''; ?> value="<?php echo esc_attr( $settings[ $key ] ?? '' ); ?>">
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
