<?php
/**
 * Related posts.
 *
 * @package Agency_Theme
 */

$data    = isset( $args['data'] ) && is_array( $args['data'] ) ? $args['data'] : array();
$post_id = isset( $data['post_id'] ) ? absint( $data['post_id'] ) : get_the_ID();
$query   = new WP_Query(
	array(
		'post_type'      => 'post',
		'posts_per_page' => 3,
		'post__not_in'   => array( $post_id ),
		'category__in'   => wp_get_post_categories( $post_id ),
	)
);

if ( $query->have_posts() ) :
	?>
	<section class="agency-section agency-related">
		<div class="agency-container">
			<h2><?php esc_html_e( 'Related articles', 'agency-theme' ); ?></h2>
			<div class="agency-grid agency-grid--three">
				<?php while ( $query->have_posts() ) : $query->the_post(); ?>
					<article class="agency-card agency-post-card">
						<p class="agency-eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
						<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
						<p class="agency-muted"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 18 ) ); ?></p>
					</article>
				<?php endwhile; ?>
			</div>
		</div>
	</section>
	<?php
endif;
wp_reset_postdata();

