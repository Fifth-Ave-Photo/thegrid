<?php
/**
 * The Grid Index — Comment UI polish.
 *
 * Tightens the comment section UI. Specifically fixes:
 *
 *   1. Doubled-border look — outer .gi-comments card was wrapping
 *      another card (.gi-comments__form-card) creating a card-in-card.
 *      Now the form sits inline without its own border.
 *   2. Oversized textarea — was rendering at default WP height (~250px).
 *      Reduced to 140px with vertical resize allowed.
 *   3. Tighter spacing between Discussion heading and form.
 *   4. Subtle red asterisk on the required Comment label rather than a
 *      bright crimson chip.
 *
 * Pure CSS, attached to the main theme stylesheet via wp_add_inline_style.
 *
 * @package The_Grid_Index
 * @since   1.10.42
 */

defined( 'ABSPATH' ) || exit;

function gip_comments_polish_css() {
	if ( is_admin() ) return;
	if ( ! is_singular( 'post' ) ) return;

	$css = '
		.gi-comments .gi-comments__form-card,
		.gi-comments .comment-respond {
			background: transparent !important;
			border: 0 !important;
			border-radius: 0 !important;
			padding: 0 !important;
			margin-top: 16px !important;
			box-shadow: none !important;
		}
		.gi-comments .gi-comments__head { margin-bottom: 10px; }
		.gi-comments .gi-comments__title { margin: 0 0 4px; }
		.gi-comments .gi-comments__sub {
			color: var(--gi-faint, #94a3b8);
			margin: 0 0 18px;
			font-size: 13.5px;
		}
		.gi-comments .comment-reply-title,
		.gi-comments h3.comment-reply-title {
			font: 700 18px/1.2 Georgia, "Times New Roman", serif;
			margin: 18px 0 14px;
			color: var(--gi-text, #f8fafc);
		}
		.gi-comments .logged-in-as,
		.gi-comments .must-log-in {
			color: var(--gi-faint, #94a3b8);
			font-size: 12.5px;
			margin: 0 0 14px;
		}
		.gi-comments .logged-in-as a,
		.gi-comments .must-log-in a {
			color: var(--gi-accent, #14b8a6);
		}
		.gi-comments .comment-form-comment label,
		.gi-comments label[for="comment"] {
			font: 600 12.5px/1.2 -apple-system, sans-serif;
			color: var(--gi-text-2, #e2e8f0);
			text-transform: uppercase;
			letter-spacing: 0.08em;
			margin-bottom: 6px;
			display: inline-block;
		}
		.gi-comments .required {
			color: #f59e0b;
			opacity: 0.9;
			margin-left: 2px;
		}
		.gi-comments textarea#comment,
		.gi-comments .comment-form textarea {
			min-height: 120px !important;
			max-height: 280px !important;
			height: 140px !important;
			padding: 12px 14px !important;
			background: var(--gi-card-soft, #1f2937) !important;
			color: var(--gi-text, #f8fafc) !important;
			border: 1px solid var(--gi-border-strong, #475569) !important;
			border-radius: 8px !important;
			font: 14px/1.55 Georgia, serif !important;
			resize: vertical;
			width: 100%;
			box-sizing: border-box;
			box-shadow: none !important;
		}
		.gi-comments textarea#comment:focus,
		.gi-comments .comment-form textarea:focus {
			outline: none !important;
			border-color: var(--gi-accent, #14b8a6) !important;
			box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.18) !important;
		}
		.gi-comments textarea::placeholder { color: var(--gi-faint, #94a3b8); }
		.gi-comments .form-submit {
			margin-top: 14px;
			display: flex;
			justify-content: flex-end;
		}
		.gi-comments .form-submit input[type="submit"],
		.gi-comments #submit {
			background: var(--gi-accent, #14b8a6) !important;
			color: #0b0f14 !important;
			border: 0 !important;
			border-radius: 8px !important;
			padding: 10px 22px !important;
			font: 700 13px/1 -apple-system, sans-serif !important;
			letter-spacing: 0.02em;
			cursor: pointer;
			transition: background 0.15s ease;
			box-shadow: none !important;
		}
		.gi-comments .form-submit input[type="submit"]:hover,
		.gi-comments #submit:hover {
			background: var(--gi-accent-hover, #0d9488) !important;
		}
	';
	wp_add_inline_style( 'the-grid-index', $css );
}
add_action( 'wp_enqueue_scripts', 'gip_comments_polish_css', 20 );
