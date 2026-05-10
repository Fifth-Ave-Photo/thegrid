<?php
/**
 * The Grid Index — Archive / Category Card Grid
 *
 * Replaces the bare FSE block-template `archive.html` rendering on category,
 * tag, author, date, and post-type archive pages with the same editorial
 * `.gi-card` grid the homepage uses (via inc/card-helpers.php +
 * inc/layout-builder.php). Honors archive pagination.
 *
 * Self-contained: hooks into `template_include` only on archive views, never
 * touches existing files. All functions are uniquely prefixed `gip_archive_`.
 *
 * @package The_Grid_Index
 * @since   1.10.20
 */

defined( 'ABSPATH' ) || exit;

/**
 * Swap the FSE archive template for our card-grid renderer on archive views.
 *
 * Runs at priority 100 so it wins over the FSE block-template loader but
 * below front-page.php's gip_force_front_template (priority 99 only fires
 * on home/front).
 */
function gip_archive_template_include( $template ) {
	if ( is_admin() ) return $template;

	// Only category, tag, author, date, post-type archive — never single posts/pages/home.
	if ( is_category() || is_tag() || is_tax() || is_author() || is_date() || is_post_type_archive() ) {
		$render = get_template_directory() . '/inc/archive-cards-render.php';
		if ( file_exists( $render ) ) {
			return $render;
		}
	}
	return $template;
}
add_filter( 'template_include', 'gip_archive_template_include', 100 );

/**
 * Inline CSS that complements card-helpers.php: adds the responsive grid
 * shell, archive header treatment, and pagination styling. Loads on archive
 * views only.
 */
function gip_archive_styles() {
	if ( is_admin() ) return;
	if ( ! ( is_category() || is_tag() || is_tax() || is_author() || is_date() || is_post_type_archive() ) ) return;

	$css = '
	#gi-main.gi-shell { max-width:1280px; margin:0 auto; padding:32px 24px 64px; }
	.gi-archive__head { margin:0 0 28px; padding-bottom:18px; border-bottom:1px solid var(--gi-rule, rgba(127,127,127,.18)); }
	.gi-archive__title { font:700 36px/1.15 Georgia,"Times New Roman",serif; margin:0 0 6px; letter-spacing:-.01em; }
	.gi-archive__desc { color:var(--gi-text-dim, #94a3b8); font-size:14.5px; max-width:680px; }

	.gi-archive__grid {
		display:grid;
		grid-template-columns:repeat(3, minmax(0,1fr));
		gap:28px 24px;
	}
	@media (max-width:980px) { .gi-archive__grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
	@media (max-width:620px) { .gi-archive__grid { grid-template-columns:1fr; } }

	/* Card chrome on archives — give them a subtle border + lift like the homepage */
	.gi-archive__grid .gi-card {
		background:var(--gi-bg-elev, rgba(255,255,255,.02));
		border:1px solid var(--gi-rule, rgba(127,127,127,.16));
		border-radius:8px;
		overflow:hidden;
		transition:border-color .2s ease, transform .2s ease;
	}
	.gi-archive__grid .gi-card:hover {
		border-color:color-mix(in oklab, var(--gi-accent, #14b8a6) 55%, var(--gi-rule, rgba(127,127,127,.16)));
		transform:translateY(-2px);
	}
	.gi-archive__grid .gi-card__body { padding:14px 16px 16px; gap:8px; }
	.gi-archive__grid .gi-card__meta {
		display:flex; align-items:center; gap:10px;
		font:700 10.5px/1 ui-monospace,SFMono-Regular,Menlo,monospace;
		letter-spacing:.12em; text-transform:uppercase;
	}
	.gi-archive__grid .gi-kicker { color:var(--gi-accent, #14b8a6); }
	.gi-archive__grid .gi-card__title {
		font:700 18px/1.3 Georgia,"Times New Roman",serif;
		margin:2px 0 0; letter-spacing:-.005em;
	}
	.gi-archive__grid .gi-card__title a { color:inherit; text-decoration:none; }
	.gi-archive__grid .gi-card__title a:hover { color:var(--gi-accent, #14b8a6); }
	.gi-archive__grid .gi-card__excerpt {
		color:var(--gi-text-dim, #94a3b8);
		font:400 14px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
		margin:0;
	}
	.gi-archive__grid .gi-card__foot {
		display:flex; align-items:center; justify-content:space-between; gap:10px;
		margin-top:6px; padding-top:10px;
		border-top:1px solid var(--gi-rule, rgba(127,127,127,.10));
		flex-wrap:wrap;
	}

	/* Pagination */
	.gi-archive__pagination { margin-top:36px; }
	.gi-archive__pagination .nav-links {
		display:flex; gap:8px; flex-wrap:wrap; align-items:center;
		font:600 13px/1 ui-monospace,SFMono-Regular,Menlo,monospace;
	}
	.gi-archive__pagination .page-numbers {
		display:inline-flex; align-items:center; justify-content:center;
		min-width:36px; padding:8px 12px;
		border:1px solid var(--gi-rule, rgba(127,127,127,.18));
		border-radius:6px; text-decoration:none;
		color:inherit;
		transition:border-color .15s, background .15s, color .15s;
	}
	.gi-archive__pagination .page-numbers:hover {
		border-color:var(--gi-accent, #14b8a6);
		color:var(--gi-accent, #14b8a6);
	}
	.gi-archive__pagination .page-numbers.current {
		background:var(--gi-accent, #14b8a6);
		border-color:var(--gi-accent, #14b8a6);
		color:#0b0f14;
	}
	.gi-archive__pagination .page-numbers.dots { border:0; }

	.gi-archive__empty { color:var(--gi-text-dim, #94a3b8); font-size:15px; padding:40px 0; text-align:center; }
	';

	wp_register_style( 'gip-archive-cards', false );
	wp_enqueue_style( 'gip-archive-cards' );
	wp_add_inline_style( 'gip-archive-cards', $css );
}
add_action( 'wp_enqueue_scripts', 'gip_archive_styles', 60 );
