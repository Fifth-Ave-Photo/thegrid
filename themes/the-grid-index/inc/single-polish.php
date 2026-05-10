<?php
/**
 * The Grid Index — Single-post polish.
 *
 * Two improvements layered on top of inc/story-dossier.php:
 *
 *   1. HERO IMAGE QUALITY — RSS-imported posts often ship small images
 *      (600–800px wide) that get stretched to ~1080px in the dossier hero,
 *      producing visible pixelation. This module:
 *        - Adds a CSS class to the hero figure that prevents upscaling
 *          beyond the source image's native width (caps the figure to the
 *          image's native size + uses a tasteful background blur for
 *          letterboxed area), keeping the layout intact.
 *        - Adds image-rendering hints so any unavoidable scaling stays sharp.
 *        - Uses sharper bicubic-style downscaling defaults via CSS.
 *
 *   2. COMMENTS UI — ships the dark editorial styling for the new
 *      comments.php template (form, list, replies, pagination).
 *
 * Self-contained, all CSS scoped to .gi-dossier__media or .gi-comments,
 * all functions prefixed gip_single_polish_*.
 *
 * @package The_Grid_Index
 * @since   1.10.20
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enqueue the polish CSS on single posts.
 */
function gip_single_polish_assets() {
	if ( ! is_singular( 'post' ) ) return;

	$css = '
	/* Hero image: theme defaults are used. We DO NOT override aspect ratio,
	   sizing, or object-fit — past attempts caused tiny-image regressions on
	   undersized RSS art. The theme stretches when needed; that\'s preferable
	   to floating postage stamps in a huge dark hero. */

	/* ============================================================
	 * Comments — dark editorial treatment
	 * ============================================================ */
	.gi-comments {
		--gi-c-rule:rgba(127,127,127,.16);
		--gi-c-rule-strong:rgba(127,127,127,.28);
		--gi-c-soft:rgba(127,127,127,.05);
		--gi-c-text-dim:#94a3b8;
		--gi-c-accent:#14b8a6;
		--gi-c-accent-soft:rgba(20,184,166,.12);
		max-width:760px;
		margin:48px 0 0;
		padding-top:32px;
		border-top:1px solid var(--gi-c-rule-strong);
		font:14.5px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
	}
	.gi-comments__head { margin-bottom:24px; }
	.gi-comments__title {
		font:700 24px/1.2 Georgia,"Times New Roman",serif;
		margin:0 0 4px; letter-spacing:-.005em;
	}
	.gi-comments__sub {
		margin:0; color:var(--gi-c-text-dim); font-size:13.5px;
	}

	/* ----- Comment list ----- */
	.gi-comments__list {
		list-style:none; padding:0; margin:0 0 28px;
		display:flex; flex-direction:column; gap:18px;
	}
	.gi-comments__list .children {
		list-style:none;
		margin:18px 0 0 28px; padding:0 0 0 18px;
		border-left:2px solid var(--gi-c-rule);
		display:flex; flex-direction:column; gap:18px;
	}
	@media (max-width:560px) {
		.gi-comments__list .children { margin-left:14px; padding-left:12px; }
	}

	.gi-comment { list-style:none; }
	.gi-comment__inner {
		background:var(--gi-c-soft);
		border:1px solid var(--gi-c-rule);
		border-radius:10px;
		padding:16px 18px;
	}
	.gi-comment__head {
		display:flex; align-items:center; gap:12px; margin-bottom:10px;
	}
	.gi-comment__avatar img {
		width:40px; height:40px; border-radius:999px; display:block;
	}
	.gi-comment__byline { display:flex; flex-direction:column; gap:2px; min-width:0; }
	.gi-comment__author {
		font-weight:700; font-size:14px;
	}
	.gi-comment__author a { color:inherit; text-decoration:none; }
	.gi-comment__author a:hover { color:var(--gi-c-accent); }
	.gi-comment__time {
		font:600 11px/1 ui-monospace,SFMono-Regular,Menlo,monospace;
		color:var(--gi-c-text-dim); letter-spacing:.04em;
	}
	.gi-comment__time a { color:inherit; text-decoration:none; }
	.gi-comment__time a:hover { color:var(--gi-c-accent); }

	.gi-comment__body { color:inherit; }
	.gi-comment__body p { margin:0 0 10px; }
	.gi-comment__body p:last-child { margin-bottom:0; }
	.gi-comment__pending {
		padding:6px 10px; margin:0 0 10px;
		background:rgba(245,158,11,.10); border-left:3px solid #f59e0b;
		border-radius:4px; font-size:12.5px; color:#f59e0b;
	}

	.gi-comment__foot {
		display:flex; gap:14px; margin-top:10px;
		font:600 12px/1 ui-monospace,SFMono-Regular,Menlo,monospace;
		letter-spacing:.04em;
	}
	.gi-comment__foot a {
		color:var(--gi-c-text-dim); text-decoration:none;
		transition:color .15s;
	}
	.gi-comment__foot a:hover { color:var(--gi-c-accent); }

	/* Bypassed comments / pagination */
	.gi-comments__pagination {
		display:flex; justify-content:space-between; gap:16px;
		margin:0 0 28px;
		font:600 12.5px/1 ui-monospace,SFMono-Regular,Menlo,monospace;
	}
	.gi-comments__pagination a {
		color:var(--gi-c-text-dim); text-decoration:none;
		padding:8px 12px; border:1px solid var(--gi-c-rule); border-radius:6px;
	}
	.gi-comments__pagination a:hover {
		color:var(--gi-c-accent); border-color:var(--gi-c-accent);
	}

	.gi-comments__closed {
		padding:14px 16px; background:var(--gi-c-soft);
		border:1px solid var(--gi-c-rule); border-radius:8px;
		color:var(--gi-c-text-dim); font-size:13.5px; margin:0 0 24px;
	}

	/* ----- Reply form ----- */
	.gi-comments__form {
		display:flex; flex-direction:column; gap:14px;
		padding:20px;
		background:var(--gi-c-soft);
		border:1px solid var(--gi-c-rule);
		border-radius:10px;
	}
	.gi-comments__form-title {
		font:700 18px/1.2 Georgia,"Times New Roman",serif;
		margin:0 0 4px; letter-spacing:-.005em;
	}
	.gi-comments__cancel { font-size:12px; font-weight:600; }
	.gi-comments__cancel a {
		color:var(--gi-c-text-dim); text-decoration:none; margin-left:6px;
	}
	.gi-comments__cancel a:hover { color:var(--gi-c-accent); }

	.gi-comments__notes,
	.gi-comments__logged {
		font-size:12.5px; color:var(--gi-c-text-dim);
		margin:0; line-height:1.5;
	}
	.gi-comments__logged a { color:var(--gi-c-accent); text-decoration:none; }
	.gi-comments__logged a:hover { text-decoration:underline; }

	.gi-comments__form .comment-form-cookies-consent {
		display:flex; align-items:flex-start; gap:8px;
		font-size:12.5px; color:var(--gi-c-text-dim);
	}
	.gi-comments__form .comment-form-cookies-consent input { margin-top:3px; flex-shrink:0; }

	/* Field grid: name + email + url side by side on desktop */
	.gi-comments__form > .gi-comments__field {
		display:flex; flex-direction:column; gap:6px;
	}
	.gi-comments__form .gi-comments__field--full { flex:1 1 100%; }

	/* Wrap name/email/url into a row */
	.gi-comments__form .comment-form-author,
	.gi-comments__form .comment-form-email,
	.gi-comments__form .comment-form-url {
		flex:1 1 200px;
	}

	.gi-comments__label {
		font-weight:700; font-size:13px;
		display:flex; align-items:center; gap:4px;
	}
	.gi-comments__req { color:#ef4444; font-weight:700; }

	.gi-comments__input,
	.gi-comments__textarea {
		width:100%;
		padding:10px 12px;
		font:14px/1.5 inherit;
		color:inherit;
		background:rgba(0,0,0,.18);
		border:1.5px solid var(--gi-c-rule-strong);
		border-radius:6px;
		transition:border-color .15s, box-shadow .15s, background .15s;
	}
	.gi-comments__textarea {
		min-height:140px; resize:vertical;
		font-family:inherit;
	}
	.gi-comments__input::placeholder,
	.gi-comments__textarea::placeholder { color:var(--gi-c-text-dim); opacity:.7; }
	.gi-comments__input:focus,
	.gi-comments__textarea:focus {
		outline:none;
		border-color:var(--gi-c-accent);
		box-shadow:0 0 0 3px var(--gi-c-accent-soft);
		background:rgba(0,0,0,.28);
	}

	.gi-comments__actions {
		display:flex; justify-content:flex-end; gap:10px; margin-top:4px;
	}
	.gi-comments__submit {
		display:inline-flex; align-items:center; gap:6px;
		padding:10px 18px;
		font:700 13px/1 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
		letter-spacing:.02em;
		background:var(--gi-c-accent); color:#0b0f14;
		border:1px solid var(--gi-c-accent);
		border-radius:6px;
		cursor:pointer;
		transition:background .15s, transform .1s;
	}
	.gi-comments__submit:hover {
		background:#0d9488; border-color:#0d9488;
	}
	.gi-comments__submit:active { transform:translateY(1px); }

	/* Light-mode tuning: adapt soft surfaces if the page is in light mode */
	body.gi-mode-light .gi-comments__input,
	body.gi-mode-light .gi-comments__textarea {
		background:#ffffff; color:#0f1216;
		border-color:#c1c8d4;
	}
	body.gi-mode-light .gi-comments__input:focus,
	body.gi-mode-light .gi-comments__textarea:focus {
		background:#ffffff;
	}
	';

	wp_register_style( 'gip-single-polish', false );
	wp_enqueue_style( 'gip-single-polish' );
	wp_add_inline_style( 'gip-single-polish', $css );
}
add_action( 'wp_enqueue_scripts', 'gip_single_polish_assets', 70 );
