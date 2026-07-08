<?php
/**
 * Idempotent module dependency and frontend setup orchestration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function agency_core_get_module_setup_definitions() {
	return array(
		'auth' => array(
			'dependencies' => array(),
			'pages'        => array(
				'login'        => array( 'slug' => 'bejelentkezes', 'title' => __( 'Sign in', 'agency-core' ), 'content' => '[agency_auth_login]' ),
				'register'     => array( 'slug' => 'regisztracio', 'title' => __( 'Registration', 'agency-core' ), 'content' => '[agency_auth_register]' ),
				'account'      => array( 'slug' => 'fiokom', 'title' => __( 'My account', 'agency-core' ), 'content' => '[agency_auth_account]' ),
				'password'     => array( 'slug' => 'jelszo-visszaallitas', 'title' => __( 'Password reset', 'agency-core' ), 'content' => '[agency_auth mode="reset"]' ),
				'verification' => array( 'slug' => 'email-megerosites', 'title' => __( 'Email verification', 'agency-core' ), 'content' => '[agency_auth mode="verification"]' ),
			),
			'defaults'     => array( 'auto_create_required_pages' => 1, 'auto_add_auth_links' => 1, 'auto_add_pages_to_menu' => 0 ),
		),
		'booking' => array(
			'dependencies' => array( 'auth', 'legal' ),
			'pages'        => array(
				'booking'      => array( 'slug' => 'idopontfoglalas', 'title' => __( 'Book an appointment', 'agency-core' ), 'content' => '[agency_booking_form]' ),
				'customer_list'=> array( 'slug' => 'foglalasaim', 'title' => __( 'My bookings', 'agency-core' ), 'content' => '[agency_booking_customer_list]' ),
				'thank_you'    => array( 'slug' => 'foglalas-koszonjuk', 'title' => __( 'Thank you for your booking', 'agency-core' ), 'content' => '<p>' . __( 'We received your booking request and will contact you shortly.', 'agency-core' ) . '</p>' ),
			),
			'defaults'     => array( 'auto_create_required_pages' => 1, 'auto_add_pages_to_menu' => 1, 'auto_add_booking_cta' => 1 ),
		),
		'legal' => array(
			'dependencies' => array(),
			'pages'        => array(
				'privacy' => array( 'slug' => 'adatkezelesi-tajekoztato', 'title' => __( 'Privacy Policy', 'agency-core' ), 'content' => '' ),
				'cookie'  => array( 'slug' => 'cookie-tajekoztato', 'title' => __( 'Cookie Policy', 'agency-core' ), 'content' => '' ),
				'imprint' => array( 'slug' => 'impresszum', 'title' => __( 'Imprint', 'agency-core' ), 'content' => '' ),
			),
			'defaults'     => array( 'auto_create_required_pages' => 1, 'auto_add_footer_legal_links' => 1, 'create_imprint' => 1 ),
		),
		'newsletter' => array(
			'dependencies' => array( 'legal' ),
			'pages'        => array(
				'newsletter' => array( 'slug' => 'hirlevel', 'title' => __( 'Newsletter', 'agency-core' ), 'content' => '[agency_newsletter]' ),
			),
			'defaults'     => array( 'auto_create_required_pages' => 1, 'auto_add_pages_to_menu' => 0 ),
		),
		'analytics' => array(
			'dependencies' => array( 'legal' ),
			'pages'        => array(),
			'defaults'     => array(),
		),
	);
}

function agency_core_get_email_health() {
	$settings = agency_core_email_settings();
	$health   = wp_parse_args(
		get_option( 'agency_core_email_health', array() ),
		array( 'test_status' => 'not-tested', 'last_status' => 'not-sent', 'last_error' => '', 'last_tested_at' => '', 'last_sent_at' => '' )
	);
	$sender_configured = is_email( $settings['sender_email'] ?? '' ) && ! empty( $settings['sender_name'] );
	$brevo_configured  = ! empty( $settings['api_key'] ) && '' !== agency_core_decrypt_secret( (string) $settings['api_key'] );
	return array(
		'provider'          => $settings['provider'] ?? 'wp_mail',
		'sender_configured' => $sender_configured,
		'brevo_configured'  => $brevo_configured,
		'test_status'       => $health['test_status'],
		'last_error'        => $health['last_error'],
		'last_tested_at'    => $health['last_tested_at'],
		'last_status'       => $health['last_status'],
		'last_sent_at'      => $health['last_sent_at'],
		'production_ready'  => $sender_configured && 'success' === $health['test_status'] && ( 'brevo' !== ( $settings['provider'] ?? '' ) || $brevo_configured ),
	);
}

function agency_core_module_required_pages( $slug ) {
	$definitions = agency_core_get_module_setup_definitions();
	$pages       = $definitions[ $slug ]['pages'] ?? array();
	if ( 'legal' === $slug && ! agency_core_module_option( 'legal', 'create_imprint', 1 ) ) {
		unset( $pages['imprint'] );
	}
	return $pages;
}

function agency_core_booking_display_present() {
	$front_id = absint( get_option( 'page_on_front' ) );
	$sections = $front_id ? json_decode( (string) get_post_meta( $front_id, '_agency_sections', true ), true ) : array();
	foreach ( (array) $sections as $section ) {
		if ( 'cta' === ( $section['component'] ?? '' ) && 'booking' === ( $section['variant'] ?? '' ) && false !== strpos( (string) ( $section['data']['button_url'] ?? '' ), 'idopontfoglalas' ) ) {
			return true;
		}
	}
	$booking = get_page_by_path( 'idopontfoglalas', OBJECT, 'page' );
	$menu_id = absint( get_nav_menu_locations()['primary'] ?? 0 );
	foreach ( (array) ( $menu_id ? wp_get_nav_menu_items( $menu_id ) : array() ) as $item ) {
		if ( $booking && absint( $item->object_id ) === absint( $booking->ID ) ) {
			return true;
		}
	}
	return false;
}

function agency_core_page_has_required_content( $content, $required ) {
	if ( '' === $required || str_contains( (string) $content, $required ) ) {
		return true;
	}
	$legacy = array(
		'[agency_auth_login]'            => array( '[agency_auth mode="login"]', "[agency_auth mode='login']" ),
		'[agency_auth_register]'         => array( '[agency_auth mode="register"]', "[agency_auth mode='register']" ),
		'[agency_auth_account]'          => array( '[agency_account]' ),
		'[agency_booking_form]'          => array( '[agency_booking]' ),
		'[agency_booking_customer_list]' => array( '[agency_booking_list]' ),
	);
	foreach ( $legacy[ $required ] ?? array() as $compatible ) {
		if ( str_contains( (string) $content, $compatible ) ) {
			return true;
		}
	}
	return false;
}

function agency_core_get_module_setup_status( $module_slug ) {
	$slug     = sanitize_key( $module_slug );
	$registry = agency_core_get_module_registry();
	if ( ! isset( $registry[ $slug ] ) ) {
		return array( 'status' => 'error', 'error' => __( 'Unknown module.', 'agency-core' ) );
	}
	$module = $registry[ $slug ];
	if ( ! $module['installed'] ) {
		return array( 'status' => 'not-installed', 'ready' => false, 'missing_dependencies' => array(), 'pages' => array() );
	}
	if ( ! $module['active'] ) {
		return array( 'status' => 'installed', 'ready' => false, 'missing_dependencies' => array(), 'pages' => array() );
	}

	$definitions = agency_core_get_module_setup_definitions();
	$required     = $definitions[ $slug ]['dependencies'] ?? array();
	$missing      = array();
	foreach ( $required as $dependency ) {
		if ( empty( $registry[ $dependency ]['active'] ) ) {
			$missing[] = $dependency;
		}
	}
	$page_status = array();
	$missing_pages = array();
	$repair_pages = array();
	foreach ( agency_core_module_required_pages( $slug ) as $key => $page ) {
		$post = get_page_by_path( $page['slug'], OBJECT, 'page' );
		$has_required = $post && agency_core_page_has_required_content( $post->post_content, $page['content'] );
		$page_status[ $key ] = array(
			'id'               => $post ? $post->ID : 0,
			'slug'             => $page['slug'],
			'exists'           => (bool) $post,
			'has_required_content' => $has_required,
			'view_url'         => $post ? get_permalink( $post ) : '',
			'edit_url'         => $post ? get_edit_post_link( $post->ID, 'raw' ) : '',
		);
		if ( ! $post ) {
			$missing_pages[] = $page['slug'];
		} elseif ( ! $has_required ) {
			$repair_pages[] = $page['slug'];
		}
	}
	$email = in_array( $slug, array( 'auth', 'booking', 'newsletter' ), true ) ? agency_core_get_email_health() : array();
	$shortcode_map = array(
		'auth'    => array( 'agency_auth_login', 'agency_auth_register', 'agency_auth_account' ),
		'booking' => array( 'agency_booking_form', 'agency_booking_customer_list', 'agency_booking_manager_login', 'agency_booking_manager_portal' ),
		'legal'   => array( 'agency_legal_links' ),
		'newsletter' => array( 'agency_newsletter' ),
	);
	$shortcodes_ok = true;
	foreach ( $shortcode_map[ $slug ] ?? array() as $shortcode ) {
		$shortcodes_ok = $shortcodes_ok && shortcode_exists( $shortcode );
	}
	$display_ok = true;
	if ( 'auth' === $slug ) {
		$settings   = (array) get_option( 'agency_module_auth_settings', array() );
		$display_ok = ! empty( $settings['auto_add_auth_links'] ) || ! empty( $settings['page_ids']['login'] );
	} elseif ( 'booking' === $slug ) {
		$display_ok = agency_core_booking_display_present();
	} elseif ( 'legal' === $slug ) {
		$settings   = (array) get_option( 'agency_module_legal_settings', array() );
		$published  = array_filter( (array) ( $settings['page_ids'] ?? array() ), static fn( $id ) => 'publish' === get_post_status( $id ) );
		$display_ok = ! empty( $settings['auto_add_footer_legal_links'] ) && ! empty( $published );
	}
	$database_ok = true;
	if ( 'booking' === $slug ) {
		global $wpdb;
		$table       = $wpdb->prefix . 'agency_bookings';
		$database_ok = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table
			&& version_compare( (string) get_option( 'agency_module_booking_db_version', '0' ), '1.1.0', '>=' );
	}
	$admin_map   = array( 'auth' => 'agency_auth_admin_page', 'booking' => 'agency_booking_admin_page', 'legal' => 'agency_legal_admin_page', 'newsletter' => 'agency_newsletter_admin', 'analytics' => 'agency_analytics_admin' );
	$admin_ui_ok = function_exists( $admin_map[ $slug ] ?? '' );
	$email_ok    = ! $email || ! empty( $email['production_ready'] );
	$status = 'ready';
	if ( $missing ) {
		$status = 'dependencies-missing';
	} elseif ( $missing_pages ) {
		$status = 'pages-missing';
	} elseif ( $repair_pages ) {
		$status = 'setup-required';
	} elseif ( ! $shortcodes_ok || ! $display_ok || ! $admin_ui_ok || ! $database_ok ) {
		$status = 'setup-required';
	} elseif ( in_array( $slug, array( 'auth', 'booking', 'legal' ), true ) && ! get_option( 'agency_module_' . $slug . '_setup_completed' ) ) {
		$status = 'setup-required';
	} elseif ( ! $email_ok ) {
		$status = 'setup-required';
	}
	$health = array(
		'installed'       => true,
		'active'          => true,
		'dependencies_ok' => empty( $missing ),
		'pages_ok'        => empty( $missing_pages ) && empty( $repair_pages ),
		'shortcodes_ok'   => $shortcodes_ok,
		'display_ok'      => $display_ok,
		'email_ok'        => $email_ok,
		'admin_ui_ok'     => $admin_ui_ok,
		'database_ok'     => $database_ok,
	);
	return array(
		'status'               => $status,
		'ready'                => 'ready' === $status,
		'missing_dependencies' => $missing,
		'required_dependencies'=> $required,
		'missing_pages'        => $missing_pages,
		'repair_pages'         => $repair_pages,
		'pages'                => $page_status,
		'email'                => $email,
		'health'               => $health,
		'warnings'             => ( $email && ! $email['production_ready'] ) ? array( __( 'Email works through wp_mail fallback, but a successful production email test is still required.', 'agency-core' ) ) : array(),
	);
}

function agency_core_provision_module_page( $module, $key, $definition, $repair = false ) {
	$post = get_page_by_path( $definition['slug'], OBJECT, 'page' );
	if ( $post ) {
		if ( $repair && $definition['content'] && ! agency_core_page_has_required_content( $post->post_content, $definition['content'] ) ) {
			$content = rtrim( (string) $post->post_content ) . "\n\n" . $definition['content'];
			$result  = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $content ) ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			update_post_meta( $post->ID, '_agency_module_repaired_at', current_time( 'mysql', true ) );
		}
		return $post->ID;
	}
	$content = $definition['content'];
	if ( 'legal' === $module && function_exists( 'agency_legal_setup_page_content' ) ) {
		$content = agency_legal_setup_page_content( $key );
	}
	$id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => 'page',
				'post_status'  => 'legal' === $module && 'draft' === agency_core_module_option( 'legal', 'page_status', 'publish' ) ? 'draft' : 'publish',
				'post_name'    => $definition['slug'],
				'post_title'   => $definition['title'],
				'post_content' => $content,
			)
		),
		true
	);
	if ( ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_agency_module_generated', '1' );
		update_post_meta( $id, '_agency_module_owner', $module );
		update_post_meta( $id, '_agency_module_required_content', $definition['content'] );
	}
	return $id;
}

function agency_core_add_page_to_primary_menu( $page_id ) {
	$locations = get_nav_menu_locations();
	$menu_id   = absint( $locations['primary'] ?? 0 );
	if ( ! $menu_id ) {
		return;
	}
	$existing = wp_get_nav_menu_items( $menu_id );
	foreach ( (array) $existing as $item ) {
		if ( 'post_type' === $item->type && absint( $item->object_id ) === absint( $page_id ) ) {
			return;
		}
	}
	wp_update_nav_menu_item( $menu_id, 0, array( 'menu-item-object-id' => $page_id, 'menu-item-object' => 'page', 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
}

function agency_core_update_booking_cta_sections() {
	$front_id = absint( get_option( 'page_on_front' ) );
	if ( ! $front_id ) {
		return false;
	}
	$raw      = get_post_meta( $front_id, '_agency_sections', true );
	$sections = json_decode( (string) $raw, true );
	if ( ! is_array( $sections ) ) {
		return false;
	}
	$changed = false;
	$found   = false;
	foreach ( $sections as &$section ) {
		if ( 'cta' !== ( $section['component'] ?? '' ) || 'booking' !== ( $section['variant'] ?? '' ) ) {
			continue;
		}
		$found = true;
		$url = $section['data']['button_url'] ?? '';
		if ( '' === $url || '/kapcsolat/' === $url ) {
			$section['data']['button_url'] = '/idopontfoglalas/';
			$changed = true;
		}
	}
	unset( $section );
	if ( ! $found ) {
		$sections[] = array(
			'component' => 'cta',
			'variant'   => 'booking',
			'version'   => 2,
			'data'      => array(
				'title'        => __( 'Book an appointment', 'agency-core' ),
				'text'         => __( 'Choose a service and an available time online.', 'agency-core' ),
				'button_label' => __( 'Book now', 'agency-core' ),
				'button_url'   => '/idopontfoglalas/',
			),
		);
		$changed = true;
	}
	if ( $changed ) {
		update_post_meta( $front_id, '_agency_sections', agency_core_sanitize_sections_meta( $sections ) );
	}
	return $changed;
}

function agency_core_apply_module_setup( $module_slug, $args = array() ) {
	$slug        = sanitize_key( $module_slug );
	$definitions = agency_core_get_module_setup_definitions();
	$registry    = agency_core_get_module_registry();
	$args        = wp_parse_args( $args, array( 'repair' => false, 'repair_display' => false, 'auto_enable_dependencies' => true ) );
	if ( ! isset( $registry[ $slug ], $definitions[ $slug ] ) ) {
		return new WP_Error( 'agency_module_setup_unknown', __( 'This module has no automated setup.', 'agency-core' ) );
	}
	if ( ! $registry[ $slug ]['installed'] ) {
		return new WP_Error( 'agency_module_setup_missing', __( 'Install the module plugin before applying setup.', 'agency-core' ) );
	}

	if ( ! $registry[ $slug ]['active'] ) {
		$activated = agency_core_set_module_state( $slug, true, false );
		if ( is_wp_error( $activated ) ) {
			return $activated;
		}
	}
	foreach ( $definitions[ $slug ]['dependencies'] as $dependency ) {
		$registry = agency_core_get_module_registry();
		if ( empty( $registry[ $dependency ]['installed'] ) ) {
			return new WP_Error( 'agency_module_dependency_missing', sprintf( __( 'Required module is not installed: %s', 'agency-core' ), $dependency ) );
		}
		if ( empty( $registry[ $dependency ]['active'] ) ) {
			if ( ! $args['auto_enable_dependencies'] ) {
				return new WP_Error( 'agency_module_dependency_inactive', sprintf( __( 'Required module is inactive: %s', 'agency-core' ), $dependency ) );
			}
			$result = agency_core_set_module_state( $dependency, true, false );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		$dependency_setup = agency_core_apply_module_setup( $dependency, $args );
		if ( is_wp_error( $dependency_setup ) ) {
			return $dependency_setup;
		}
	}

	$settings = array_merge( $definitions[ $slug ]['defaults'], (array) get_option( 'agency_module_' . $slug . '_settings', array() ) );
	if ( $args['repair_display'] ) {
		if ( 'auth' === $slug ) {
			$settings['auto_add_auth_links'] = 1;
		} elseif ( 'booking' === $slug ) {
			$settings['auto_add_booking_cta'] = 1;
			$settings['auto_add_pages_to_menu'] = 1;
		} elseif ( 'legal' === $slug ) {
			$settings['auto_add_footer_legal_links'] = 1;
		}
	}
	$page_ids = array();
	$auto_create = $settings['auto_create_required_pages'] ?? $settings['auto_create_pages'] ?? true;
	if ( $auto_create ) {
		foreach ( agency_core_module_required_pages( $slug ) as $key => $page ) {
			$id = agency_core_provision_module_page( $slug, $key, $page, (bool) $args['repair'] );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$page_ids[ $key ] = $id;
		}
	}
	$settings['page_ids'] = array_replace( (array) ( $settings['page_ids'] ?? array() ), $page_ids );
	if ( 'auth' === $slug ) {
		$settings['account_page'] = absint( $page_ids['account'] ?? $settings['account_page'] ?? 0 );
		$settings['login_redirect'] = ! empty( $page_ids['account'] ) ? get_permalink( $page_ids['account'] ) : ( $settings['login_redirect'] ?? '' );
	}
	if ( 'booking' === $slug ) {
		$settings['thank_you_url'] = ! empty( $settings['thank_you_url'] ) ? $settings['thank_you_url'] : get_permalink( $page_ids['thank_you'] ?? 0 );
		$auto_cta = $settings['auto_add_booking_cta'] ?? $settings['auto_update_booking_cta'] ?? true;
		if ( $auto_cta ) {
			$core = (array) get_option( 'agency_core_settings', array() );
			if ( empty( $core['booking_url'] ) || '/kapcsolat/' === $core['booking_url'] ) {
				$core['booking_url'] = '/idopontfoglalas/';
				update_option( 'agency_core_settings', $core, false );
			}
			agency_core_update_booking_cta_sections();
		}
	}
	update_option( 'agency_module_' . $slug . '_settings', $settings, false );
	$auto_menu = $settings['auto_add_pages_to_menu'] ?? $settings['auto_add_to_menu'] ?? false;
	if ( $auto_menu ) {
		$menu_key = 'booking' === $slug ? 'booking' : ( 'auth' === $slug ? 'login' : '' );
		if ( $menu_key && ! empty( $page_ids[ $menu_key ] ) ) {
			agency_core_add_page_to_primary_menu( $page_ids[ $menu_key ] );
		}
	}
	update_option( 'agency_module_' . $slug . '_setup_completed', gmdate( 'c' ), false );
	$event = $args['repair'] ? 'setup_repaired' : ( $args['repair_display'] ? 'display_repaired' : 'setup_applied' );
	agency_core_audit_log( $slug, $event, array( 'page_ids' => $page_ids ) );
	agency_core_sync_manifest_modules();
	return agency_core_get_module_setup_status( $slug );
}

function agency_core_handle_module_setup() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Insufficient permissions.', 'agency-core' ) );
	}
	$slug   = sanitize_key( wp_unslash( $_POST['module'] ?? '' ) );
	$setup_action = sanitize_key( wp_unslash( $_POST['setup_action'] ?? '' ) );
	$repair = 'repair' === $setup_action;
	check_admin_referer( 'agency_core_module_setup_' . $slug );
	$result = agency_core_apply_module_setup( $slug, array( 'repair' => $repair, 'repair_display' => 'repair-display' === $setup_action ) );
	$status = is_wp_error( $result ) ? 'error' : 'success';
	wp_safe_redirect( add_query_arg( array( 'agency_module_status' => $status, 'module' => $slug ), admin_url( 'admin.php?page=agency-core-modules' ) ) );
	exit;
}
add_action( 'admin_post_agency_core_module_setup', 'agency_core_handle_module_setup' );

function agency_core_module_email_warning() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$registry = agency_core_get_module_registry();
	if ( ( ! empty( $registry['booking']['active'] ) || ! empty( $registry['auth']['active'] ) ) && ! agency_core_get_email_health()['production_ready'] ) {
		echo '<div class="notice notice-warning"><p>' . wp_kses_post( sprintf( __( 'Agency modules use wp_mail fallback, but production email is not verified. <a href="%s">Configure and test Email Service</a>.', 'agency-core' ), esc_url( admin_url( 'admin.php?page=agency-core-email' ) ) ) ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'agency_core_module_email_warning' );

function agency_core_render_module_setup_summary( $slug ) {
	$status = agency_core_get_module_setup_status( $slug );
	?>
	<div class="notice notice-<?php echo ! empty( $status['ready'] ) ? 'success' : 'warning'; ?> inline">
		<p><strong><?php esc_html_e( 'Setup status:', 'agency-core' ); ?></strong> <?php echo esc_html( $status['status'] ?? 'error' ); ?>
		<?php if ( ! empty( $status['missing_dependencies'] ) ) : ?> — <?php echo esc_html( __( 'Missing dependencies: ', 'agency-core' ) . implode( ', ', $status['missing_dependencies'] ) ); ?><?php endif; ?>
		<?php if ( ! empty( $status['missing_pages'] ) ) : ?> — <?php echo esc_html( __( 'Missing pages: ', 'agency-core' ) . implode( ', ', $status['missing_pages'] ) ); ?><?php endif; ?></p>
		<?php if ( ! empty( $status['repair_pages'] ) ) : ?><p><?php echo esc_html( __( 'Pages missing required content: ', 'agency-core' ) . implode( ', ', $status['repair_pages'] ) ); ?></p><?php endif; ?>
		<?php if ( ! empty( $status['warnings'] ) ) : ?><p><?php echo esc_html( implode( ' ', $status['warnings'] ) ); ?></p><?php endif; ?>
		<?php if ( ! empty( $status['health'] ) ) : ?><p class="agency-admin-health"><?php foreach ( $status['health'] as $label => $passed ) : ?><span class="<?php echo $passed ? 'is-ok' : 'is-bad'; ?>"><?php echo esc_html( str_replace( '_', ' ', $label ) ); ?>: <?php echo $passed ? '✓' : '✕'; ?></span> <?php endforeach; ?></p><?php endif; ?>
		<?php if ( ! empty( $status['pages'] ) ) : ?><ul><?php foreach ( $status['pages'] as $page ) : ?><li><code>/<?php echo esc_html( $page['slug'] ); ?>/</code> — <?php echo $page['exists'] ? esc_html__( 'exists', 'agency-core' ) : esc_html__( 'missing', 'agency-core' ); ?><?php if ( $page['exists'] ) : ?> · <a href="<?php echo esc_url( $page['edit_url'] ); ?>"><?php esc_html_e( 'Edit', 'agency-core' ); ?></a> · <a href="<?php echo esc_url( $page['view_url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View', 'agency-core' ); ?></a><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=agency-core-modules' ) ); ?>"><?php esc_html_e( 'Open setup actions', 'agency-core' ); ?></a></p>
	</div>
	<?php
}

function agency_core_inactive_module_component_registry( $registry ) {
	$modules = agency_core_get_module_registry();
	$known   = array(
		'auth'    => array( 'login' => 'Login', 'register' => 'Registration', 'account' => 'Account' ),
		'booking' => array( 'form' => 'Form', 'customer-list' => 'Customer list' ),
		'legal'   => array( 'links' => 'Legal links' ),
	);
	foreach ( $known as $slug => $variants ) {
		if ( ! empty( $modules[ $slug ]['active'] ) || isset( $registry[ $slug ] ) ) {
			continue;
		}
		$variant_defs = array();
		foreach ( $variants as $variant => $label ) {
			$variant_defs[ $variant ] = array( 'label' => $label . ' (' . __( 'module inactive', 'agency-core' ) . ')', 'template' => 'agency-module/inactive' );
		}
		$registry[ $slug ] = array(
			'label' => ucfirst( $slug ), 'editor_label' => ucfirst( $slug ) . ' (' . __( 'inactive', 'agency-core' ) . ')',
			'default_variant' => array_key_first( $variant_defs ), 'version' => 1, 'fields' => array(), 'default_data' => array(), 'variants' => $variant_defs,
		);
	}
	return $registry;
}
add_filter( 'agency_theme_component_registry', 'agency_core_inactive_module_component_registry', 1 );
add_filter( 'agency_core_component_registry', 'agency_core_inactive_module_component_registry', 1 );

function agency_core_render_inactive_module_component( $handled, $component ) {
	if ( ! in_array( $component, array( 'auth', 'booking', 'legal' ), true ) ) {
		return $handled;
	}
	$registry = agency_core_get_module_registry();
	if ( ! empty( $registry[ $component ]['active'] ) ) {
		return $handled;
	}
	if ( current_user_can( 'manage_options' ) ) {
		echo '<div class="agency-module-warning">' . esc_html( sprintf( __( 'The %s section is configured, but its module is inactive.', 'agency-core' ), $component ) ) . '</div>';
	}
	return true;
}
add_filter( 'agency_theme_render_module_component', 'agency_core_render_inactive_module_component', 1, 2 );
