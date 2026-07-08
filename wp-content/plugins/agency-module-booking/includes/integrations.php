<?php
/**
 * Backward-compatible loader.
 *
 * @deprecated 1.11.0 Use includes/services/component-registry.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/includes/services/component-registry.php';
