<?php
/**
 * Search Results template.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="gi-main" class="gi-shell" role="main">
	<div class="gi-grid">
		<div class="gi-main-col">
			<header class="gi-section__head">
				<h1 class="gi-section__title">
					<?php
					/* translators: %s: search query */
					printf( esc_html__( 'Search results for: %s', 'the-grid-index' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
					?>
				</h1>
			</header>

			<?php if ( have_posts() ) : ?>
				<div class="gi-cards">
					<?php
					while ( have_posts() ) : the_post();
						if ( function_exists( 'gip_render_card' ) ) {
							gip_render_card( get_the_ID() );
						} else {
							?>
							<article <?php post_class( 'gi-card' ); ?>>
								<a href="<?php echo esc_url( get_permalink() ); ?>"><h2 class="gi-card__title"><?php the_title(); ?></h2></a>
								<p class="gi-card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
							</article>
							<?php
						}
					endwhile;
					?>
				</div>

				<nav class="gi-pagination" aria-label="<?php esc_attr_e( 'Search results pagination', 'the-grid-index' ); ?>">
					<?php
					the_posts_pagination( array(
						'prev_text' => esc_html__( '&larr; Previous', 'the-grid-index' ),
						'next_text' => esc_html__( 'Next &rarr;', 'the-grid-index' ),
					) );
					?>
				</nav>
			<?php else : ?>
				<section class="gi-section">
					<p><?php esc_html_e( 'No stories matched your search.', 'the-grid-index' ); ?></p>
					<?php get_search_form(); ?>
				</section>
			<?php endif; ?>
		</div>
	</div>
</main>

<?php
get_footer();
