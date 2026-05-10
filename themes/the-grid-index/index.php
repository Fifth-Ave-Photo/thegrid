<?php
/**
 * Generic index template — last-resort editorial loop.
 * Wrapped in the Grid Index card grid; never raw WP list output.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

if ( is_front_page() || is_home() ) {
	include get_template_directory() . '/front-page.php';
	return;
}

get_header();

if ( current_user_can( 'manage_options' ) ) {
	echo "\n<!-- GridIndex template: index.php loaded -->\n";
}
?>
<main id="gi-main" class="gi-shell" role="main">
	<?php if ( have_posts() ) : ?>
		<div class="gip-fallback-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<article class="gip-card">
					<?php if ( has_post_thumbnail() ) : ?>
						<a class="gip-card__thumb" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'gip-card', array( 'loading' => 'lazy' ) ); ?></a>
					<?php endif; ?>
					<h3 class="gip-card__title"><a href="<?php the_permalink(); ?>"><?php echo esc_html( get_the_title() ); ?></a></h3>
					<p class="gip-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 24 ) ); ?></p>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'No stories yet.', 'the-grid-index' ); ?></p>
	<?php endif; ?>
</main>
<?php get_footer();
