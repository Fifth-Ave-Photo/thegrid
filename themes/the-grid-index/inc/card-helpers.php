<?php
/**
 * The Grid Index — Card helper fallbacks
 *
 * The layout-builder card grid (Top Stories, Editor Picks, Most Discussed,
 * Accelerating Stories) calls a set of helper functions that are normally
 * provided by the Grid Index Control plugin:
 *
 *   gip_card_thumb()
 *   gip_card_title_link()
 *   gip_render_card_meta_line()
 *   gip_render_source_button()
 *   gip_render_signal_badge( $post_id )
 *   gip_resolve_card_link( $post_id )
 *
 * Each call site is wrapped in `function_exists()`, so when the plugin is
 * inactive the cards silently render with NO image, NO source button, and a
 * minimal title. This module ships theme-side fallbacks so the editorial
 * card grid stays visually complete out of the box.
 *
 * @package The_Grid_Index
 * @since   1.10.18
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render the card thumbnail. Uses the post's featured image when present,
 * otherwise falls back to the branded category fallback shipped with
 * inc/fallback-images.php (image cleaner module).
 */
if ( ! function_exists( 'gip_card_thumb' ) ) :
function gip_card_thumb( $size = 'gip-card' ) {
	$post_id = get_the_ID();
	$href    = function_exists( 'gip_resolve_card_link' )
		? gip_resolve_card_link( $post_id )
		: get_permalink( $post_id );
	$alt     = the_title_attribute( array( 'echo' => false, 'post' => $post_id ) );

	echo '<a class="gi-card__thumb" href="' . esc_url( $href ) . '" tabindex="-1" aria-label="' . esc_attr( $alt ) . '">';

	if ( has_post_thumbnail( $post_id ) ) {
		echo get_the_post_thumbnail(
			$post_id,
			$size,
			array(
				'loading'  => 'lazy',
				'decoding' => 'async',
				'class'    => 'gi-card__img',
				'alt'      => $alt,
			)
		);
	} else {
		// Branded fallback via the image cleaner module.
		$cats = get_the_category( $post_id );
		$cat  = ! empty( $cats ) ? strtolower( $cats[0]->slug ) : 'world';
		$src  = function_exists( 'gridindex_get_fallback_image' )
			? gridindex_get_fallback_image( $cat )
			: '';
		if ( $src ) {
			$label = ! empty( $cats ) ? strtoupper( $cats[0]->name ) : 'GRID INDEX';
			echo '<span class="gi-card__thumb-fallback gi-card__thumb-fallback--' . esc_attr( $cat ) . '">';
			echo '<img class="gi-card__img" src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" decoding="async" width="800" height="500" />';
			echo '<span class="gi-card__thumb-badge">' . esc_html( $label ) . '</span>';
			echo '</span>';
		} else {
			// Last-resort SVG placeholder so the slot never collapses.
			echo '<span class="gi-card__thumb-fallback gi-card__thumb-fallback--blank" aria-hidden="true"></span>';
		}
	}

	echo '</a>';
}
endif;

/** Card title link — wraps the post title in an anchor. */
if ( ! function_exists( 'gip_card_title_link' ) ) :
function gip_card_title_link() {
	$post_id = get_the_ID();
	$href    = function_exists( 'gip_resolve_card_link' )
		? gip_resolve_card_link( $post_id )
		: get_permalink( $post_id );
	echo '<a href="' . esc_url( $href ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a>';
}
endif;

/** Card meta line — source name + relative time. */
if ( ! function_exists( 'gip_render_card_meta_line' ) ) :
function gip_render_card_meta_line() {
	$post_id = get_the_ID();
	$source  = '';
	if ( function_exists( 'gip_get_source_name' ) ) {
		$source = (string) gip_get_source_name( $post_id );
	}
	if ( ! $source ) {
		$source = (string) get_post_meta( $post_id, '_gip_source_name', true );
	}
	if ( ! $source ) {
		$source = (string) get_post_meta( $post_id, 'source_name', true );
	}

	$time = human_time_diff( get_post_time( 'U', false, $post_id ), current_time( 'timestamp' ) );

	echo '<span class="gi-card__meta-line">';
	if ( $source ) {
		echo '<span class="gi-card__source">' . esc_html( $source ) . '</span>';
		echo '<span class="gi-card__sep" aria-hidden="true"> · </span>';
	}
	/* translators: %s: human-readable time difference, e.g. "2 hours". */
	echo '<span class="gi-card__time">' . esc_html( sprintf( __( '%s ago', 'the-grid-index' ), $time ) ) . '</span>';
	echo '</span>';
}
endif;

/** "Read at <Source>" outbound button. Skipped when no external source URL. */
if ( ! function_exists( 'gip_render_source_button' ) ) :
function gip_render_source_button() {
	$post_id = get_the_ID();

	$url = '';
	if ( function_exists( 'gip_get_source_url' ) ) {
		$url = (string) gip_get_source_url( $post_id );
	}
	if ( ! $url ) {
		$url = (string) get_post_meta( $post_id, '_gip_source_url', true );
	}
	if ( ! $url ) {
		$url = (string) get_post_meta( $post_id, 'source_url', true );
	}
	if ( ! $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) return;

	$source = '';
	if ( function_exists( 'gip_get_source_name' ) ) {
		$source = (string) gip_get_source_name( $post_id );
	}
	if ( ! $source ) {
		$source = (string) get_post_meta( $post_id, '_gip_source_name', true );
	}
	if ( ! $source ) {
		$host   = wp_parse_url( $url, PHP_URL_HOST );
		$source = $host ? preg_replace( '/^www\./', '', $host ) : __( 'source', 'the-grid-index' );
	}

	printf(
		'<a class="gi-card__source-btn" href="%1$s" rel="noopener nofollow" target="_blank">%2$s <span aria-hidden="true">↗</span></a>',
		esc_url( $url ),
		esc_html( sprintf( /* translators: %s: source name */ __( 'Read at %s →', 'the-grid-index' ), $source ) )
	);
}
endif;

/** Signal badge (BREAKING / RISING / etc.) — quiet no-op fallback. */
if ( ! function_exists( 'gip_render_signal_badge' ) ) :
function gip_render_signal_badge( $post_id = 0 ) {
	$post_id = $post_id ? (int) $post_id : get_the_ID();
	$signal  = (string) get_post_meta( $post_id, '_gip_signal', true );
	if ( ! $signal ) return;
	$label = strtoupper( $signal );
	echo '<span class="gi-signal gi-signal--' . esc_attr( sanitize_html_class( strtolower( $signal ) ) ) . '">' . esc_html( $label ) . '</span>';
}
endif;

/** Resolve the link a card should point to (internal permalink by default). */
if ( ! function_exists( 'gip_resolve_card_link' ) ) :
function gip_resolve_card_link( $post_id ) {
	return get_permalink( $post_id );
}
endif;

/* ─────────────────────────────────────────────────────────────────────────
 * Inline CSS — minimal styling so the thumbnails sit cleanly in the cards
 * even when the Grid Index Control plugin (which ships richer styles) is
 * not active.
 * ────────────────────────────────────────────────────────────────────── */
add_action( 'wp_enqueue_scripts', function () {
	$css = '
	.gi-card { display:flex; flex-direction:column; }
	.gi-card__thumb { display:block; position:relative; overflow:hidden; aspect-ratio:16/10; background:rgba(127,127,127,.08); border-radius:6px 6px 0 0; }
	.gi-card__thumb .gi-card__img,
	.gi-card__thumb img { display:block; width:100%; height:100%; object-fit:cover; object-position:center 28%; transition:transform .5s ease; }
	.gi-card:hover .gi-card__thumb img { transform:scale(1.03); }
	.gi-card__thumb-fallback { position:relative; display:block; width:100%; height:100%; }
	.gi-card__thumb-badge { position:absolute; left:10px; bottom:10px; padding:2px 8px; font:700 10px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.14em; text-transform:uppercase; background:rgba(0,0,0,.55); color:#fff; border-radius:2px; }
	.gi-card__thumb-fallback--blank { background:linear-gradient(135deg,rgba(127,127,127,.18),rgba(127,127,127,.05)); }
	.gi-card__body { padding:14px 4px 4px; display:flex; flex-direction:column; gap:8px; flex:1; }
	.gi-card__source-btn { display:inline-flex; align-items:center; gap:4px; margin-top:6px; padding:5px 10px; font:700 11px/1 ui-monospace,SFMono-Regular,Menlo,monospace; letter-spacing:.06em; border:1px solid currentColor; border-radius:3px; text-decoration:none; }
	.gi-card__source-btn:hover { background:currentColor; }
	.gi-card__source-btn:hover { color:inherit; }
	.gi-card__meta-line { font:500 11px/1.4 ui-monospace,SFMono-Regular,Menlo,monospace; opacity:.75; }
	';
	wp_register_style( 'gip-card-helpers', false );
	wp_enqueue_style( 'gip-card-helpers' );
	wp_add_inline_style( 'gip-card-helpers', $css );
}, 50 );
