<?php
/**
 * Required fallback template.
 *
 * @package Agency_Theme
 */

get_header();
?>
<section class="agency-section">
	<div class="agency-container">
		<header class="agency-section-heading">
			<h1><?php echo esc_html( is_home() && ! is_front_page() ? single_post_title( '', false ) : get_bloginfo( 'name' ) ); ?></h1>
		</header>
		<?php get_template_part( 'template-parts/blog/blog-grid', null, array( 'data' => array( 'use_main_query' => true ) ) ); ?>
		<?php the_posts_pagination(); ?>
	</div>
</section>
<?php
get_footer();
