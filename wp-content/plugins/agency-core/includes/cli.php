<?php
/**
 * WP-CLI integration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	class Agency_Core_CLI_Command {
		/**
		 * List installed blueprints.
		 */
		public function list_( $args, $assoc_args ) {
			$rows = array();
			foreach ( agency_core_get_blueprints() as $slug => $manifest ) {
				$rows[] = array(
					'slug'        => $slug,
					'name'        => $manifest['name'] ?? $slug,
					'version'     => $manifest['version'] ?? '',
					'description' => $manifest['description'] ?? '',
				);
			}
			\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'slug', 'name', 'version', 'description' ) );
		}

		/**
		 * Import a blueprint.
		 *
		 * ## OPTIONS
		 *
		 * <slug>
		 * : Blueprint slug shown by `wp agency blueprint list`.
		 */
		public function import( $args ) {
			$result = agency_core_import_blueprint( sanitize_key( $args[0] ?? '' ) );
			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}
			\WP_CLI::success( 'Blueprint imported.' );
		}
	}

	class Agency_Core_Project_CLI_Command {
		/**
		 * Display the current project manifest.
		 */
		public function show( $args, $assoc_args ) {
			$manifest = agency_core_get_project_manifest();
			if ( ! $manifest ) {
				\WP_CLI::error( 'No agency-project.json manifest was found.' );
			}
			$rows = array();
			foreach ( $manifest as $key => $value ) {
				$rows[] = array(
					'field' => $key,
					'value' => is_array( $value ) ? implode( ', ', $value ) : $value,
				);
			}
			\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'field', 'value' ) );
		}

		/**
		 * Apply a validated project config from wp-content.
		 *
		 * ## OPTIONS
		 *
		 * <path>
		 * : JSON config path. For safety the file must be inside wp-content.
		 */
		public function apply_config( $args ) {
			$requested = $args[0] ?? '';
			$path      = realpath( $requested );
			$base      = realpath( WP_CONTENT_DIR );
			if ( ! $path || ! $base || ! is_file( $path ) || ! str_starts_with( wp_normalize_path( $path ), trailingslashit( wp_normalize_path( $base ) ) ) ) {
				\WP_CLI::error( 'Config must be an existing JSON file inside wp-content.' );
			}
			$config = json_decode( (string) file_get_contents( $path ), true );
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				\WP_CLI::error( 'Invalid project config JSON: ' . json_last_error_msg() );
			}
			$result = agency_core_apply_project_config( $config );
			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}
			\WP_CLI::success( 'Project settings and manifest updated.' );
		}
	}

	class Agency_Core_Module_CLI_Command {
		/**
		 * List Agency feature modules.
		 */
		public function list_( $args, $assoc_args ) {
			$rows = array();
			foreach ( agency_core_get_module_registry() as $module ) {
				$rows[] = array(
					'slug'       => $module['slug'],
					'label'      => $module['label'],
					'installed'  => $module['installed'] ? 'yes' : 'no',
					'active'     => $module['active'] ? 'yes' : 'no',
					'status'     => $module['status'],
					'dependency' => implode( ',', $module['dependencies'] ),
				);
			}
			\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'slug', 'label', 'installed', 'active', 'status', 'dependency' ) );
		}

		/**
		 * Enable an installed module.
		 *
		 * ## OPTIONS
		 *
		 * <slug>
		 * : Module slug shown by `wp agency module list`.
		 */
		public function enable( $args ) {
			$this->set_state( $args[0] ?? '', true );
		}

		/**
		 * Disable an installed module.
		 *
		 * ## OPTIONS
		 *
		 * <slug>
		 * : Module slug shown by `wp agency module list`.
		 */
		public function disable( $args ) {
			$this->set_state( $args[0] ?? '', false );
		}

		/**
		 * Apply idempotent module setup.
		 *
		 * ## OPTIONS
		 *
		 * <slug>
		 * : Production module slug or "all".
		 *
		 * [--repair]
		 * : Append missing required shortcode content to existing pages.
		 */
		public function setup( $args, $assoc_args ) {
			$requested = sanitize_key( $args[0] ?? '' );
			$slugs     = 'all' === $requested ? array_keys( agency_core_get_module_setup_definitions() ) : array( $requested );
			foreach ( $slugs as $slug ) {
				$result = agency_core_apply_module_setup( $slug, array( 'repair' => isset( $assoc_args['repair'] ), 'repair_display' => isset( $assoc_args['repair-display'] ) ) );
				if ( is_wp_error( $result ) ) {
					\WP_CLI::error( $slug . ': ' . $result->get_error_message() );
				}
				\WP_CLI::log( $slug . ': ' . ( $result['status'] ?? 'ready' ) );
			}
			\WP_CLI::success( 'Module setup completed.' );
		}

		/**
		 * Show setup status.
		 */
		public function status( $args, $assoc_args ) {
			$rows = array();
			foreach ( agency_core_get_module_setup_definitions() as $slug => $definition ) {
				$status = agency_core_get_module_setup_status( $slug );
				$rows[] = array(
					'module'       => $slug,
					'status'       => $status['status'] ?? 'error',
					'dependencies' => implode( ',', $status['missing_dependencies'] ?? array() ),
					'pages'        => implode( ',', $status['missing_pages'] ?? array() ),
					'email'        => isset( $status['email']['production_ready'] ) ? ( $status['email']['production_ready'] ? 'ready' : 'warning' ) : 'n/a',
				);
			}
			\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'module', 'status', 'dependencies', 'pages', 'email' ) );
		}

		private function set_state( $slug, $enable ) {
			$result = agency_core_set_module_state( sanitize_key( $slug ), $enable );
			if ( is_wp_error( $result ) ) {
				\WP_CLI::error( $result->get_error_message() );
			}
			\WP_CLI::success( $enable ? 'Module enabled.' : 'Module disabled.' );
		}
	}

	\WP_CLI::add_command( 'agency blueprint list', array( new Agency_Core_CLI_Command(), 'list_' ) );
	\WP_CLI::add_command( 'agency blueprint import', array( new Agency_Core_CLI_Command(), 'import' ) );
	\WP_CLI::add_command( 'agency project show', array( new Agency_Core_Project_CLI_Command(), 'show' ) );
	\WP_CLI::add_command( 'agency project apply-config', array( new Agency_Core_Project_CLI_Command(), 'apply_config' ) );
	\WP_CLI::add_command( 'agency module list', array( new Agency_Core_Module_CLI_Command(), 'list_' ) );
	\WP_CLI::add_command( 'agency module enable', array( new Agency_Core_Module_CLI_Command(), 'enable' ) );
	\WP_CLI::add_command( 'agency module disable', array( new Agency_Core_Module_CLI_Command(), 'disable' ) );
	\WP_CLI::add_command( 'agency module setup', array( new Agency_Core_Module_CLI_Command(), 'setup' ) );
	\WP_CLI::add_command( 'agency module status', array( new Agency_Core_Module_CLI_Command(), 'status' ) );
}
