<?php
/**
 * The Grid Index — Archive card grid renderer.
 *
 * Loaded by gip_archive_template_include() (in inc/archive-cards.php) on
 * category, tag, taxonomy, author, date, and post-type archive views.
 * Renders the same `.gi-card` editorial card grid the homepage uses,
 * via the helpers in inc/card-helpers.php.
 *
 * @package The_Grid_Index
 * @since   1.10.20
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="gi-main" class="gi-shell" role="main">
	<header class="gi-archive__head">
		<h1 class="gi-archive__title"><?php the_archive_title(); ?></h1>
		<?php
		$desc = get_the_archive_description();
		if ( $desc ) : ?>
			<div class="gi-archive__desc"><?php echo wp_kses_post( $desc ); ?></div>
		<?php endif; ?>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="gi-archive__grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<article class="gi-card">
					<?php if ( function_exists( 'gip_card_thumb' ) ) gip_card_thumb(); ?>
					<div class="gi-card__body">
						<div class="gi-card__meta">
							<?php
							if ( function_exists( 'gip_render_signal_badge' ) ) gip_render_signal_badge( get_the_ID() );
							$cc = get_the_category();
							if ( ! empty( $cc ) ) echo '<span class="gi-kicker">' . esc_html( $cc[0]->name ) . '</span>';
							?>
						</div>
						<h3 class="gi-card__title">
							<?php
							if ( function_exists( 'gip_card_title_link' ) ) gip_card_title_link();
							else echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
							?>
						</h3>
						<?php
						$excerpt = get_the_excerpt();
						if ( $excerpt ) : ?>
							<p class="gi-card__excerpt"><?php echo esc_html( wp_trim_words( $excerpt, 24 ) ); ?></p>
						<?php endif; ?>
						<div class="gi-card__foot">
							<?php
							if ( function_exists( 'gip_render_card_meta_line' ) ) gip_render_card_meta_line();
							if ( function_exists( 'gip_render_source_button' ) )  gip_render_source_button();
							?>
						</div>
					</div>
				</article>
			<?php endwhile; ?>
		</div>

		<nav class="gi-archive__pagination" aria-label="<?php esc_attr_e( 'Archive pagination', 'the-grid-index' ); ?>">
			<?php
			the_posts_pagination( array(
				'mid_size'  => 1,
				'prev_text' => __( '← Previous', 'the-grid-index' ),
				'next_text' => __( 'Next →', 'the-grid-index' ),
			) );
			?>
		</nav>

	<?php else : ?>
		<p class="gi-archive__empty"><?php esc_html_e( 'Nothing here yet.', 'the-grid-index' ); ?></p>
	<?php endif; ?>
</main>
<?php get_footer();
