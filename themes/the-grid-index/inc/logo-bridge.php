<?php
/**
 * The Grid Index — Logo bridge.
 *
 * The header in inc/site-chrome.php reads the logo via WordPress's
 * standard custom_logo system: has_custom_logo() and
 * get_theme_mod( 'custom_logo' ).
 *
 * The Theme Options page stores the logo in a totally different place:
 * gridindex_theme_options['logo_id'].
 *
 * Without a bridge, uploading a logo via Theme Options has no effect —
 * the header reads from a different option key and finds nothing.
 *
 * This module bridges the two by filtering theme_mod_custom_logo so
 * WordPress thinks the Theme Options attachment IS the custom logo.
 * The Customize → Site Identity → Logo path still works as a manual
 * override: if the user sets it there too, that wins (we only fill in
 * when Customize is empty).
 *
 * Bridges in this direction (Theme Options -> custom_logo) rather than
 * the reverse so we don't have to touch site-chrome.php (which under
 * our no-PHP-edits rule we never touch) and so the standard WP
 * functions like has_custom_logo() / the_custom_logo() also "just work"
 * everywhere.
 *
 * @package The_Grid_Index
 * @since   1.10.32
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return the Theme Options logo attachment ID, or 0 if none configured.
 */
function gip_logo_bridge_get_theme_options_logo_id() {
	if ( ! function_exists( 'gridindex_get_option' ) ) return 0;
	$id = (int) gridindex_get_option( 'logo_id', 0 );
	if ( $id <= 0 ) return 0;
	// Sanity check that the attachment still exists.
	$mime = get_post_mime_type( $id );
	if ( ! $mime || strpos( (string) $mime, 'image/' ) !== 0 ) return 0;
	return $id;
}

/**
 * When something asks for the custom_logo theme mod and Customize has
 * not set one, return the Theme Options logo ID instead.
 */
function gip_logo_bridge_filter_theme_mod( $value ) {
	// Customize has set one — respect it (manual override wins).
	if ( ! empty( $value ) ) return $value;
	$id = gip_logo_bridge_get_theme_options_logo_id();
	return $id ? $id : $value;
}
add_filter( 'theme_mod_custom_logo', 'gip_logo_bridge_filter_theme_mod', 10, 1 );
