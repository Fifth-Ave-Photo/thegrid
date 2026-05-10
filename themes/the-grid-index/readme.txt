=== The Grid Index ===
Contributors: fifthavenuephotographic
Tested up to: 6.8
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.10.75
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Tags: news, blog, block-styles, block-patterns, wide-blocks, accessibility-ready, translation-ready, custom-logo, custom-menu, featured-images, threaded-comments, rtl-language-support

A premium editorial, news, and magazine theme inspired by professional publications.

== Description ==

The Grid Index is a clean, fast, accessibility-ready editorial theme for WordPress. Built around full site editing with a refined typography system (Inter + Source Serif 4), a charcoal + muted teal palette, generous spacing, and strong information hierarchy.

The theme is designed for Bloomberg/Semafor-style editorial sites: a Live Hero Deck carousel, breaking news ticker, accelerating-story signal rails, topic dashboards, and a Latest live feed. It works fully standalone — no plugin or external service required.

For RSS ingestion (pulling articles from external feeds into your install) the optional Grid Index RSS Importer plugin is recommended. The theme detects imported posts and renders them with proper source attribution and a "Continue reading at [Source]" CTA.

== Features ==

* Full Site Editing (block templates, template parts, theme.json)
* Editorial homepage with drag-to-reorder Layout Builder
* Live Hero Deck cinematic slider (auto-advances, pauses on hover)
* Breaking News Ticker with manual pin / category / tag / fallback priority
* Most Accelerating signal rail with momentum percentages
* Top Stories grid, Topic Dashboard, Live Latest feed, Source Intelligence rail, Trending Entities
* System-font typography stack with Inter and Source Serif 4 as preferred families, falling back to native OS fonts so no remote font calls are required
* Dark mode tokens (Grid Index Dark Intelligence) and Light Editorial mode
* Accessibility-ready: skip links, focus outlines, AA contrast
* Translation-ready (text domain: the-grid-index)
* Block patterns
* Custom logo + wordmark + tagline header
* In-admin Knowledge Base with documentation for every feature

== Installation ==

1. In WordPress admin, go to Appearance > Themes > Add New > Upload Theme.
2. Upload the theme zip and click Activate.
3. (Optional) Install the Grid Index RSS Importer plugin to pull articles from external feeds.
4. Visit Grid Index > Layout Builder to configure the homepage.
5. Visit Grid Index > Visual Settings to choose your appearance mode and accent color.
6. Visit Grid Index > Knowledge Base for a complete walkthrough of every feature.

== Frequently Asked Questions ==

= Does the theme require any plugins? =
No. The theme works fully on its own. The optional Grid Index RSS Importer adds external feed ingestion if you want it.

= Are external services used? =
No. The theme uses a system-font stack with Inter and Source Serif 4 as preferred families. If those fonts aren't installed on the visitor's device, the browser falls back to native system fonts. No remote font calls are made.

= Is it accessibility ready? =
Yes. Skip links, focus styles, semantic landmarks, and AA color contrast are included.

= How do I add stories to the homepage slider? =
Tick the "Featured Slider" category on a post in the editor, or use the Posts list "Add to Slider" row action. Configure max slides and behavior at Grid Index > Slider.

= How do I configure the breaking news ticker? =
Pin a post manually from the Posts list, assign the "Breaking" category (auto-expires after 24h), or use the "ticker" tag for long-lived items. Configure at Grid Index > Ticker.

== Copyright ==

The Grid Index, © 2026 Fifth Avenue Photographic, GPLv2 or later.

The theme references the Inter and Source Serif 4 type families by name in its CSS but does not bundle font files. Visitors see these fonts only if their browser/device has them; otherwise system fallbacks are used.

== Changelog ==

= 1.10.75 =
* Refactored customizer add_setting helper to pass sanitize_callback explicitly at the call site, so static analyzers can verify it without resolving array_merge expressions.

= 1.10.74 =
* Fixed remaining WordPress.org submission scanner failures:
  - Added default sanitize_callback to all customizer settings
  - Added wp_link_pages() to single.php and page.php for paginated post navigation
  - Replaced hardcoded search form with get_search_form() and added searchform.php template
  - Added editor stylesheet (assets/css/editor-style.css) for visual parity in the block editor
  - Added WordPress core CSS classes: .alignleft/.alignright/.aligncenter/.alignwide/.alignfull, .wp-caption/.wp-caption-text, .gallery-caption, .sticky, .bypostauthor, .screen-reader-text

= 1.10.73 =
* Major refactor for WordPress.org submission compliance:
  - Removed all `register_block_type()` calls (themes cannot register dynamic blocks; plugin territory). Theme now uses classic PHP templates exclusively.
  - Removed FSE block templates (`templates/`, `parts/` directories) since classic PHP templates cover the same views.
  - Added classic PHP templates: 404.php, archive.php, page.php, search.php
  - Removed `add_shortcode()` registration (plugin territory)
  - Removed `add_management_page()` Tools menu entry for Regenerate Thumbnails (use the Regenerate Thumbnails plugin instead)
  - Removed `ini_set()` calls (themes cannot change PHP runtime settings)
  - Replaced `base64_encode`-built SVG icon with the standard `dashicons-grid-view` icon
  - Replaced `get_site_url()` with `home_url()` in cache-buster filter
* Theme is no longer a Block Theme (FSE); now a hybrid classic theme with `theme.json` for color/typography presets only.
* Removed `full-site-editing` theme tag accordingly.

= 1.10.72 =
* Removed duplicate Author URI to comply with WordPress.org submission requirement (theme URI and author URI cannot both point to the same URL)

= 1.10.71 =
* Accessibility: removed `aria-hidden` from focusable card thumbnail anchors (PageSpeed 90 -> 95+ expected)
* Accessibility: bumped muted-text colors for WCAG AA contrast compliance against dark background
* SEO: added meta description tag with smart per-page-type sourcing (excerpt, term description, search query, etc.). Defers to active SEO plugins (Yoast, Rank Math, AIOSEO, SEOPress).

= 1.10.70 =
* Pre-submission cleanup pass for WordPress.org theme directory
* Removed stale references to bundled fonts (theme uses system font stack with Inter / Source Serif 4 as preferred families)
* Removed reference to nonexistent "Grid Index Control" plugin from theme description
* Removed HTML link from theme description (not allowed in style.css)
* Theme tags revised to comply with WordPress.org allowed-tags list
* Tested up to: 6.8
* Removed dead orphaned file from theme root

= 1.10.69 =
* Folder, textdomain, and package identifier renamed to "the-grid-index" for consistency with branding
* All translatable strings updated to new textdomain
* Theme is now WordPress.org submission-ready

= 1.10.68 =
* Strip development-history references from module file headers and inline comments
* Rewrite Knowledge Base troubleshooting topic in version-agnostic language
* Code now ready for WordPress.org theme review

= 1.10.67 =
* Pull theme version dynamically from style.css header instead of hardcoded constant
* Convert inline style blocks to wp_add_inline_style for proper asset queue integration
* Refresh readme to reflect current branding and feature set

= 1.10.x =
* Layout Builder, Visual Settings, Ticker, Slider, Knowledge Base admin pages
* Breaking ticker with manual pin / Breaking category / ticker tag fallback chain
* Featured slider with category-based or custom-slide sources
* Homepage dedup + image-quality gating
* Imported-post truncation with source attribution
* Comments form polish, header wordmark, footer credit, support link
* Theme screenshot redesign

= 1.0.0 =
* Initial release.
