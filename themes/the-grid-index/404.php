<?php
/**
 * 404 Not Found template.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<main id="gi-main" class="gi-shell" role="main">
	<div class="gi-grid">
		<div class="gi-main-col">
			<section class="gi-section gi-404">
				<h1 class="gi-section__title"><?php esc_html_e( '404', 'the-grid-index' ); ?></h1>
				<p class="gi-section__sub"><?php esc_html_e( "The page you're looking for can't be found.", 'the-grid-index' ); ?></p>
				<div class="gi-404__search">
					<?php get_search_form(); ?>
				</div>
			</section>
		</div>
	</div>
</main>

<?php
get_footer();
