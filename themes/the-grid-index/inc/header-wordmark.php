<?php
/**
 * The Grid Index — Header wordmark beside logo.
 *
 * The header in inc/site-chrome.php uses an if/else: if there's a custom
 * logo, render the image; otherwise render the wordmark text. That makes
 * the wordmark and logo mutually exclusive — but most editorial sites
 * want both, side by side (logo + nameplate, like AP, NYT, BBC).
 *
 * This module bridges the gap without editing site-chrome.php:
 *
 *   - Filters wp_get_attachment_image, detecting the header logo by its
 *     gi-mast__logo class
 *   - When the Theme Options "Wordmark text" field has content, appends
 *     a styled wordmark span after the image
 *   - The existing "Wordmark text" field becomes the implicit toggle:
 *       - Empty → just the logo (current behavior)
 *       - Has text → logo + wordmark side-by-side
 *
 * No new admin UI needed. The user already knows the field — they fill
 * it in (or clear it) to control visibility. Tagline support is also
 * handled (a second optional line of muted text under the wordmark).
 *
 * @package The_Grid_Index
 * @since   1.10.34
 */

defined( 'ABSPATH' ) || exit;

/**
 * Append a wordmark span to the header logo image when the wordmark
 * text option is set.
 */
function gip_header_wordmark_append( $html, $attachment_id, $size, $icon, $attr ) {
	// Only target the header logo, identified by its class.
	$class = is_array( $attr ) && isset( $attr['class'] ) ? (string) $attr['class'] : '';
	if ( strpos( $class, 'gi-mast__logo' ) === false ) return $html;

	// Read wordmark + tagline from Theme Options. Empty wordmark means
	// the user wants logo-only (current behavior preserved).
	$wordmark = '';
	$tagline  = '';
	if ( function_exists( 'gridindex_get_option' ) ) {
		$wordmark = (string) gridindex_get_option( 'wordmark', '' );
		$tagline  = (string) gridindex_get_option( 'tagline', '' );
	}
	// If no wordmark configured, keep behavior exactly as it was.
	if ( $wordmark === '' ) return $html;

	// Build the wordmark span. Mirrors the markup used by the no-logo
	// fallback branch of inc/site-chrome.php so existing CSS applies
	// (.gi-mast__title and .gi-mast__dot are already styled).
	$wordmark_html = '<span class="gi-mast__title gi-mast__title--with-logo">'
		. esc_html( $wordmark )
		. '<span class="gi-mast__dot">.</span>'
		. '</span>';

	if ( $tagline !== '' ) {
		$wordmark_html .= '<span class="gi-mast__tagline">' . esc_html( $tagline ) . '</span>';
	}

	return $html . $wordmark_html;
}
add_filter( 'wp_get_attachment_image', 'gip_header_wordmark_append', 10, 5 );

/**
 * Add a tiny CSS tweak so the with-logo wordmark looks right next to
 * the logo image. The default .gi-mast__title is sized for standalone
 * use (28px); when sitting next to a 40px-tall logo it can feel heavy.
 */
function gip_header_wordmark_css() {
	if ( is_admin() ) return;

	$wordmark = function_exists( 'gridindex_get_option' )
		? (string) gridindex_get_option( 'wordmark', '' )
		: '';
	if ( $wordmark === '' ) return; // nothing to style

	$css = '
	/* Logo + wordmark side-by-side: tighter sizing and proper baseline */
	.gi-mast__brand .gi-mast__title--with-logo {
		font-size: 22px;
		line-height: 1;
		letter-spacing: -.01em;
		align-self: center;
	}
	/* Make sure the brand row aligns image and text on the same axis */
	.gi-mast__brand {
		align-items: center;
	}
	/* Slightly smaller logo so the combined block is balanced */
	.gi-mast__logo {
		max-height: 36px;
	}
	';

	wp_register_style( 'gip-header-wordmark', false );
	wp_enqueue_style( 'gip-header-wordmark' );
	wp_add_inline_style( 'gip-header-wordmark', $css );
}
add_action( 'wp_enqueue_scripts', 'gip_header_wordmark_css', 90 );
