<?php
/**
 * The Grid Index — Rename "Theme Options" to "Visual Settings".
 *
 * Swaps the visible string "Theme Options" → "Visual Settings" via a
 * gettext filter, scoped to the specific admin page. The page handler
 * itself (inc/theme-options.php) is unchanged; we just intercept the
 * translation pipeline that converts source strings to display text.
 *
 * Targets:
 *   - Page H1 / heading shown at the top of the page
 *   - Browser tab title
 *   - Submit button label "Save Theme Options" if any
 *
 * Scoped to the gridindex-theme-options screen so it cannot leak.
 *
 * @package The_Grid_Index
 * @since   1.10.57
 */

defined( 'ABSPATH' ) || exit;

function gip_visual_settings_rename( $translated, $original, $domain ) {
	// Only act on Grid Index theme strings.
	if ( $domain !== 'the-grid-index' ) return $translated;

	// Only act when we're rendering the Theme Options admin page.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( $page !== 'gridindex-theme-options' ) return $translated;

	// Swap any occurrence of "Theme Options" with "Visual Settings"
	// in the text that's about to be rendered.
	if ( strpos( $translated, 'Theme Options' ) !== false ) {
		$translated = str_replace( 'Theme Options', 'Visual Settings', $translated );
	}
	return $translated;
}
add_filter( 'gettext', 'gip_visual_settings_rename', 20, 3 );

/**
 * Browser tab title — admin pages set this via WordPress's admin
 * <title> system. We hook admin_title to swap the same string.
 */
function gip_visual_settings_rename_admin_title( $admin_title, $title ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( $page !== 'gridindex-theme-options' ) return $admin_title;
	return str_replace( 'Theme Options', 'Visual Settings', $admin_title );
}
add_filter( 'admin_title', 'gip_visual_settings_rename_admin_title', 20, 2 );
