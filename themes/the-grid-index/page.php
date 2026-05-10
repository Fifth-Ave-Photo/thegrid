<?php
/**
 * Single Page template.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="gi-main" class="gi-shell" role="main">
	<div class="gi-grid">
		<div class="gi-main-col">
			<?php while ( have_posts() ) : the_post(); ?>
				<article id="post-<?php the_ID(); ?>" <?php post_class( 'gi-page' ); ?>>
					<header class="gi-page__head">
						<h1 class="gi-page__title"><?php the_title(); ?></h1>
					</header>
					<div class="gi-page__body">
						<?php
						the_content();
						wp_link_pages( array(
							'before' => '<nav class="gi-page__pages" aria-label="' . esc_attr__( 'Page', 'the-grid-index' ) . '"><span>' . esc_html__( 'Pages:', 'the-grid-index' ) . '</span>',
							'after'  => '</nav>',
						) );
						?>
					</div>
					<?php
					if ( comments_open() || get_comments_number() ) {
						comments_template();
					}
					?>
				</article>
			<?php endwhile; ?>
		</div>
	</div>
</main>

<?php
get_footer();
