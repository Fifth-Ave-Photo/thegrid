<?php
/**
 * Archive template — categories, tags, taxonomies, dates, authors.
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
				<h1 class="gi-section__title"><?php the_archive_title(); ?></h1>
				<?php
				$desc = get_the_archive_description();
				if ( ! empty( $desc ) ) {
					echo '<div class="gi-section__sub">' . wp_kses_post( $desc ) . '</div>';
				}
				?>
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

				<nav class="gi-pagination" aria-label="<?php esc_attr_e( 'Archive pagination', 'the-grid-index' ); ?>">
					<?php
					the_posts_pagination( array(
						'prev_text' => esc_html__( '&larr; Previous', 'the-grid-index' ),
						'next_text' => esc_html__( 'Next &rarr;', 'the-grid-index' ),
					) );
					?>
				</nav>
			<?php else : ?>
				<section class="gi-section">
					<p><?php esc_html_e( 'No stories in this archive yet.', 'the-grid-index' ); ?></p>
				</section>
			<?php endif; ?>
		</div>
	</div>
</main>

<?php
get_footer();
