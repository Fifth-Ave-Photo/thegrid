<?php
/**
 * The Grid Index — Admin page background.
 *
 * Darkens the page-level background (#wpcontent / #wpbody-content)
 * on the Grid Index admin pages so the existing dark editorial cards
 * sit on a matching surface instead of floating over the default
 * light WordPress grey.
 *
 * Why this is separate from inc/admin-dark-overlay.php:
 *   That module styles cards, forms, and inputs deeply on the slider
 *   and ticker pages. The Layout Builder and Theme Options pages
 *   already render their own card chrome and form treatment — they
 *   only need the surrounding canvas to be dark. This module is
 *   intentionally minimal: backgrounds + the area outside cards.
 *   It does NOT touch buttons, inputs, postboxes, or notices.
 *
 * Targeted screen IDs (covers both pre- and post-1.10.43 menu
 * reorganization):
 *   - toplevel_page_gridindex                   (Layout Builder)
 *   - appearance_page_gridindex-theme-options   (Theme Options)
 *   - appearance_page_gip-ticker                (legacy)
 *   - appearance_page_gip-slider                (legacy)
 *   - gridindex_page_gip-ticker                 (post-reorganize)
 *   - gridindex_page_gip-slider                 (post-reorganize)
 *
 * @package The_Grid_Index
 * @since   1.10.45
 */

defined( 'ABSPATH' ) || exit;

/**
 * The pages that should have a dark canvas.
 */
function gip_admin_bg_target_screens() {
	return array(
		'toplevel_page_gridindex',
		'appearance_page_gridindex-theme-options',
		'appearance_page_gip-ticker',
		'appearance_page_gip-slider',
		'gridindex_page_gip-ticker',
		'gridindex_page_gip-slider',
	);
}

/**
 * Inject the canvas-only CSS when on a target screen.
 */
function gip_admin_bg_enqueue( $hook ) {
	if ( ! in_array( $hook, gip_admin_bg_target_screens(), true ) ) return;

	$css = '
	/* Grid Index admin canvas — dark background only.
	   Lets existing dark cards sit on a matching surface without
	   touching the cards themselves. */
	html, body {
		background: #0B0F14 !important;
	}
	#wpcontent, #wpbody, #wpbody-content {
		background: #0B0F14 !important;
	}
	#wpfooter {
		background: #0B0F14;
		color: #94a3b8 !important;
	}
	#wpfooter a, #wpfooter p {
		color: #94a3b8 !important;
	}
	/* WordPress prints update/screen-meta divs above the wrap with
	   a white default — match those to the canvas so they do not
	   show as a thin light bar. */
	#screen-meta-links {
		background: transparent;
	}
	';

	wp_register_style( 'gip-admin-bg', false );
	wp_enqueue_style( 'gip-admin-bg' );
	wp_add_inline_style( 'gip-admin-bg', $css );
}
add_action( 'admin_enqueue_scripts', 'gip_admin_bg_enqueue' );
