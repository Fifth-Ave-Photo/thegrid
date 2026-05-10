<?php
/**
 * The Grid Index — Latest feed thumbnail fallback.
 *
 * Fixes the empty grey box issue in the homepage Latest feed (and any
 * other place gi-latest__thumb is used) when a post has no featured
 * image. The original layout-builder code always emits the .gi-latest__thumb
 * anchor but only puts an <img> inside if has_post_thumbnail() is true,
 * leaving an empty rounded grey rectangle.
 *
 * Strategy: CSS-only. Use :empty + branded category fallback as background.
 * Plus: a lightweight DOM walker as a JS fallback for browsers/cases where
 * :empty doesn't fire (e.g. when the anchor has whitespace-only content).
 *
 * @package The_Grid_Index
 * @since   1.10.25
 */

defined( 'ABSPATH' ) || exit;

function gip_latest_thumb_fallback_css() {
	if ( is_admin() ) return;

	$tpl = get_template_directory_uri();
	$fallback_dir = get_template_directory() . '/assets/fallbacks/';

	// Pick a default fallback image — first available .jpg in the fallbacks folder.
	$default_url = $tpl . '/assets/fallbacks/world.jpg';
	if ( ! file_exists( $fallback_dir . 'world.jpg' ) ) {
		// Look for any jpg.
		$candidates = is_dir( $fallback_dir ) ? glob( $fallback_dir . '*.jpg' ) : array();
		if ( ! empty( $candidates ) ) {
			$default_url = $tpl . '/assets/fallbacks/' . basename( $candidates[0] );
		} else {
			$default_url = '';
		}
	}

	if ( ! $default_url ) return;

	$css = sprintf( '
	/* Latest feed: when the thumb anchor has no <img>, use a branded fallback
	   so we never show empty grey boxes. */
	.gi-latest__thumb:empty,
	.gi-latest__thumb.is-empty {
		background-image: url(%1$s);
		background-size: cover;
		background-position: center;
		background-color: var(--gi-bg-elev-2);
		position: relative;
	}
	/* Subtle dark overlay so text-only posts read like editorial cards */
	.gi-latest__thumb:empty::after,
	.gi-latest__thumb.is-empty::after {
		content: "";
		position: absolute; inset: 0;
		background: linear-gradient(180deg, rgba(11,18,32,.15) 0%%, rgba(11,18,32,.55) 100%%);
		border-radius: inherit;
	}
	',
		esc_url( $default_url )
	);

	wp_register_style( 'gip-latest-thumb-fallback', false );
	wp_enqueue_style( 'gip-latest-thumb-fallback' );
	wp_add_inline_style( 'gip-latest-thumb-fallback', $css );
}
add_action( 'wp_enqueue_scripts', 'gip_latest_thumb_fallback_css', 80 );

/**
 * Tiny JS shim — adds .is-empty class to any .gi-latest__thumb that has
 * no <img> child. Belt-and-braces for browsers that handle :empty
 * inconsistently when whitespace is present inside the anchor.
 */
function gip_latest_thumb_fallback_js() {
	if ( is_admin() ) return;
	?>
	<script>
	(function(){
		var thumbs = document.querySelectorAll('.gi-latest__thumb');
		for (var i = 0; i < thumbs.length; i++) {
			if ( ! thumbs[i].querySelector('img') ) {
				thumbs[i].classList.add('is-empty');
			}
		}
	})();
	</script>
	<?php
}
add_action( 'wp_footer', 'gip_latest_thumb_fallback_js', 100 );
