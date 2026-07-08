<?php
/**
 * Archive template.
 *
 * @package Agency_Theme
 */

get_header();
?>
<section class="agency-section">
	<div class="agency-container">
		<?php get_template_part( 'template-parts/layout/breadcrumbs' ); ?>
		<header class="agency-section-heading">
			<h1><?php the_archive_title(); ?></h1>
			<?php the_archive_description( '<div class="agency-muted">', '</div>' ); ?>
		</header>
		<?php get_template_part( 'template-parts/blog/blog-grid', null, array( 'data' => array( 'use_main_query' => true ) ) ); ?>
		<?php the_posts_pagination(); ?>
	</div>
</section>
<?php
get_footer();

