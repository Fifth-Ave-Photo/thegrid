<?php
/**
 * Front Page — Grid Index Layout Builder driven editorial homepage.
 *
 * Section composition is controlled from Grid Index → Layout Builder.
 * This template only provides the shell; sections render via dispatcher.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( current_user_can( 'manage_options' ) ) {
	echo "\n<!-- GridIndex template: front-page.php (layout-builder driven) -->\n";
	echo '<!-- GridIndex CSS loaded: version ' . esc_html( defined( 'GIP_VERSION' ) ? GIP_VERSION : '0' ) . " -->\n";
}
?>

<main id="gi-main" class="gi-shell gi-shell--lb" role="main">
	<div class="gi-grid">
		<div class="gi-main-col">
			<?php gip_lb_render_layout( 'main' ); ?>
		</div>

		<aside class="gi-rail" role="complementary" aria-label="<?php esc_attr_e( 'Intelligence rail', 'the-grid-index' ); ?>">
			<?php gip_lb_render_layout( 'rail' ); ?>
		</aside>
	</div>

	<?php
	// Full-width sections render below the grid.
	ob_start(); gip_lb_render_layout( 'full' ); $full = ob_get_clean();
	if ( trim( $full ) !== '' ) echo '<div class="gi-fullband">' . $full . '</div>';
	?>
</main>

<?php get_footer();
