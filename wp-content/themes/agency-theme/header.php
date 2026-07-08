<?php
/**
 * Theme header.
 *
 * @package Agency_Theme
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text" href="#main-content"><?php esc_html_e( 'Skip to content', 'agency-theme' ); ?></a>
<?php get_template_part( 'template-parts/layout/site-header' ); ?>
<main id="main-content" class="agency-site-main">

