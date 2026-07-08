<?php
/**
 * Allow-listed site header dispatcher.
 *
 * @package Agency_Theme
 */

$registry = agency_theme_get_component_registry();
$variant  = agency_theme_get_header_variant();
$template = $registry['header']['variants'][ $variant ]['template'];

get_template_part( $template );

