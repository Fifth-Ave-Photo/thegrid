<?php
/**
 * The Grid Index — Frontend bridge
 *
 * Translates Theme Options values into actual frontend behavior:
 *   - dynamic CSS variables (accent, background, card, fonts, borders, density)
 *   - body classes (sticky header, mobile menu, density, lazy, animations,
 *     card image ratio, archive layout, debug mode)
 *   - native lazy-loading toggle
 *   - admin debug strip on the front-end (when Debug mode is on)
 *   - cache-buster appended to enqueued asset URLs
 *   - custom CSS appended to the inline stylesheet
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map a font choice key to a CSS font-family stack.
 */
function gip_font_stack( $key ) {
	switch ( $key ) {
		case 'sans-modern':
			return '"Inter","Helvetica Neue",Helvetica,Arial,sans-serif';
		case 'mono-terminal':
			return '"JetBrains Mono","IBM Plex Mono",ui-monospace,SFMono-Regular,Menlo,monospace';
		case 'serif-editorial':
		default:
			return '"Source Serif 4","Source Serif Pro","Playfair Display",Georgia,serif';
	}
}

/**
 * Compose dynamic :root variables from saved Theme Options.
 */
function gip_dynamic_css() {
	if ( ! function_exists( 'gridindex_get_option' ) ) {
		return '';
	}
	$accent  = gridindex_get_option( 'accent_color', '#14B8A6' );
	$bg      = gridindex_get_option( 'bg_color', '' );
	$card    = gridindex_get_option( 'card_color', '' );
	$fh      = gip_font_stack( gridindex_get_option( 'font_heading', 'serif-editorial' ) );
	$fb      = gip_font_stack( gridindex_get_option( 'font_body', 'sans-modern' ) );
	$density = gridindex_get_option( 'editorial_density', 'comfortable' ) === 'compact' ? '12px' : '18px';
	$border  = gridindex_get_option( 'border_style', 'subtle' );

	switch ( $border ) {
		case 'none':     $border_w = '0px';   $border_c = 'transparent'; break;
		case 'hairline': $border_w = '1px';   $border_c = 'rgba(255,255,255,.06)'; break;
		case 'bold':     $border_w = '2px';   $border_c = 'rgba(255,255,255,.18)'; break;
		case 'subtle':
		default:         $border_w = '1px';   $border_c = 'rgba(255,255,255,.10)'; break;
	}

	$ratio_map = array(
		'16x9'     => '16/9',
		'4x3'      => '4/3',
		'3x2'      => '3/2',
		'1x1'      => '1/1',
		'portrait' => '3/4',
	);
	$ratio_key = gridindex_get_option( 'card_image_ratio', '16x9' );
	$ratio     = $ratio_map[ $ratio_key ] ?? '16/9';

	$custom = (string) gridindex_get_option( 'custom_css', '' );

	$css  = ":root{";
	$css .= "--gi-accent:{$accent};";
	if ( $bg )   $css .= "--gi-bg:{$bg};";
	if ( $card ) $css .= "--gi-card:{$card};";
	$css .= "--gi-font-heading:{$fh};";
	$css .= "--gi-font-body:{$fb};";
	$css .= "--gi-density:{$density};";
	$css .= "--gi-border-w:{$border_w};";
	$css .= "--gi-border-c:{$border_c};";
	$css .= "--gi-card-ratio:{$ratio};";
	$css .= "}";

	// Apply variables broadly.
	$css .= "body.gridindex-theme{font-family:var(--gi-font-body);}";
	$css .= ".gridindex-theme h1,.gridindex-theme h2,.gridindex-theme h3,.gi-card__title,.gi-headline,.gi-deck__title{font-family:var(--gi-font-heading);}";
	if ( $bg )   $css .= "body.gridindex-theme{background:var(--gi-bg);}";
	if ( $card ) $css .= ".gridindex-theme .gi-card,.gridindex-theme .gi-deck,.gridindex-theme .gip-card{background:var(--gi-card);}";
	$css .= ".gridindex-theme .gi-card,.gridindex-theme .gip-card,.gridindex-theme .gi-deck{border:var(--gi-border-w) solid var(--gi-border-c);}";
	$css .= ".gridindex-theme a,.gridindex-theme .gi-accent{color:var(--gi-accent);}";
	$css .= ".gridindex-theme .gip-card__media,.gridindex-theme .gi-card__media,.gridindex-theme .gi-card-img{aspect-ratio:var(--gi-card-ratio);overflow:hidden;}";
	$css .= ".gridindex-theme .gip-card__media img,.gridindex-theme .gi-card__media img,.gridindex-theme .gi-card-img img{width:100%;height:100%;object-fit:cover;}";
	$css .= ".gi-density-compact .gi-card,.gi-density-compact .gip-card{padding:var(--gi-density) !important;}";
	$css .= ".gi-no-anim *,.gi-no-anim *::before,.gi-no-anim *::after{animation:none !important;transition:none !important;}";
	$css .= ".gi-sticky-header .site-header,.gi-sticky-header .gip-masthead,.gi-sticky-header header.gi-header{position:sticky;top:0;z-index:50;}";

	if ( $custom ) {
		$css .= "\n/* Custom CSS */\n" . wp_strip_all_tags( $custom );
	}

	return $css;
}

add_action( 'wp_enqueue_scripts', function () {
	if ( wp_style_is( 'the-grid-index-layout', 'enqueued' ) ) {
		wp_add_inline_style( 'the-grid-index-layout', gip_dynamic_css() );
	} elseif ( wp_style_is( 'the-grid-index', 'enqueued' ) ) {
		wp_add_inline_style( 'the-grid-index', gip_dynamic_css() );
	}
}, 20 );

/**
 * Body classes from Theme Options.
 */
add_filter( 'body_class', function ( $classes ) {
	if ( ! function_exists( 'gridindex_get_option' ) ) return $classes;

	$classes[] = 'gi-density-' . gridindex_get_option( 'editorial_density', 'comfortable' );
	$classes[] = 'gi-cards-' . sanitize_html_class( gridindex_get_option( 'card_style', 'editorial' ) );
	$classes[] = 'gi-archive-' . sanitize_html_class( gridindex_get_option( 'archive_layout', 'intelligence' ) );
	$classes[] = 'gi-mobile-' . sanitize_html_class( gridindex_get_option( 'mobile_menu_style', 'drawer' ) );
	$classes[] = 'gi-hero-' . sanitize_html_class( gridindex_get_option( 'hero_layout', 'live_deck' ) );
	$classes[] = 'gi-pagi-' . sanitize_html_class( gridindex_get_option( 'archive_pagination_style', 'numbered' ) );

	if ( gridindex_get_option( 'sticky_header' ) )    $classes[] = 'gi-sticky-header';
	if ( gridindex_get_option( 'disable_animations' ) ) $classes[] = 'gi-no-anim';
	if ( gridindex_get_option( 'lazy_loading' ) )     $classes[] = 'gi-lazy';
	if ( gridindex_get_option( 'debug_mode' ) )       $classes[] = 'gi-debug';

	return $classes;
}, 20 );

/**
 * Native lazy-loading toggle.
 */
add_filter( 'wp_lazy_loading_enabled', function ( $enabled ) {
	if ( ! function_exists( 'gridindex_get_option' ) ) return $enabled;
	return (bool) gridindex_get_option( 'lazy_loading', 1 );
}, 20 );

/**
 * Filter posts_per_page on archives so the "Archive posts per page" option
 * actually drives output.
 */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) return;
	if ( $q->is_archive() || $q->is_search() || $q->is_home() && ! $q->is_front_page() ) {
		$ppp = (int) gridindex_get_option( 'archive_posts_per_page', 12 );
		if ( $ppp > 0 ) $q->set( 'posts_per_page', $ppp );
	}
} );

/**
 * Append cache-buster suffix to enqueued style/script URLs when set.
 */
function gip_apply_cache_buster( $src, $handle ) {
	if ( ! function_exists( 'gridindex_get_option' ) ) return $src;
	$cb = (string) gridindex_get_option( 'cache_buster', '' );
	if ( '' === $cb || strpos( (string) $src, home_url() ) === false ) return $src;
	$sep = ( false === strpos( $src, '?' ) ) ? '?' : '&';
	return $src . $sep . 'gicb=' . rawurlencode( $cb );
}
add_filter( 'style_loader_src',  'gip_apply_cache_buster', 99, 2 );
add_filter( 'script_loader_src', 'gip_apply_cache_buster', 99, 2 );

/**
 * Admin-only frontend debug strip.
 */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'gridindex_get_option' ) ) return;
	if ( ! gridindex_get_option( 'debug_mode', 0 ) ) return;
	if ( ! current_user_can( 'manage_options' ) ) return;

	global $template;
	$tpl   = $template ? basename( $template ) : 'unknown';
	$hero  = gridindex_get_option( 'hero_layout', 'live_deck' );
	$css   = wp_styles()->registered['the-grid-index-layout']->ver  ?? wp_styles()->registered['the-grid-index']->ver ?? '?';
	$js    = wp_scripts()->registered['gip-live-deck']->ver ?? '—';
	$saved = (int) get_option( 'gridindex_theme_options_saved_at', 0 );
	$arch  = gridindex_get_option( 'archive_layout', 'intelligence' );

	echo '<div id="gi-debug-strip" style="position:fixed;left:8px;right:8px;bottom:8px;z-index:9999;display:flex;flex-wrap:wrap;gap:8px;padding:8px 12px;background:rgba(0,0,0,.85);color:#9be7d6;font:11px/1.4 ui-monospace,Menlo,monospace;border:1px solid rgba(155,231,214,.25);border-radius:6px;backdrop-filter:blur(6px);">';
	echo '<strong style="color:#fff">GI DEBUG</strong>';
	echo '<span>tpl=<code>' . esc_html( $tpl ) . '</code></span>';
	echo '<span>hero=<code>' . esc_html( $hero ) . '</code></span>';
	echo '<span>archive=<code>' . esc_html( $arch ) . '</code></span>';
	echo '<span>css v<code>' . esc_html( (string) $css ) . '</code></span>';
	echo '<span>js v<code>' . esc_html( (string) $js ) . '</code></span>';
	echo '<span>saved=<code>' . esc_html( $saved ? date_i18n( 'Y-m-d H:i', $saved ) : '—' ) . '</code></span>';
	echo '<a href="' . esc_url( admin_url( 'themes.php?page=gridindex-theme-options' ) ) . '" style="margin-left:auto;color:#fff;text-decoration:underline">Theme Options →</a>';
	echo '</div>';
} );
