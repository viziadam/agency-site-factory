<?php
/**
 * Plugin Name: Agency Module: Webshop
 * Description: Placeholder package for future commerce features.
 * Version: 1.12.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Agency Starter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'agency-core-settings',
			'Webshop Module',
			'Webshop Module',
			'manage_options',
			'agency-module-webshop',
			static function () {
				echo '<div class="wrap"><h1>' . esc_html__( 'Webshop Module', 'agency-module-webshop' ) . '</h1><div class="notice notice-info inline"><p>' . esc_html__( 'Module installed, feature implementation pending', 'agency-module-webshop' ) . '</p></div></div>';
			}
		);
	},
	30
);
