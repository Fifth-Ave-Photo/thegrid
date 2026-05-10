<?php
/**
 * The Grid Index — Knowledge Base.
 *
 * Adds a "Knowledge Base" page under the Grid Index admin menu with
 * documentation covering how to use the site's editorial features:
 * Layout Builder, Visual Settings, Ticker, Slider, RSS Importer,
 * Posts, Categories, etc.
 *
 * The page uses the same dark editorial admin UI as other Grid Index
 * pages. Topics are rendered in a left-rail nav + content area
 * structure. Each topic is a self-contained block of help copy.
 *
 * @package The_Grid_Index
 * @since   1.10.63
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the Knowledge Base submenu under the Grid Index parent.
 */
function gip_kb_register_page() {
	add_submenu_page(
		'gridindex',
		__( 'Grid Index Knowledge Base', 'the-grid-index' ),
		__( 'Knowledge Base', 'the-grid-index' ),
		'manage_options',
		'gip-knowledge-base',
		'gip_kb_render_page'
	);
}
add_action( 'admin_menu', 'gip_kb_register_page', 99999 );

/**
 * The topics that appear in the left rail. Order in this array =
 * order in the rail. Each topic has an id (anchor), label, and
 * a callable that renders its body content.
 */
function gip_kb_topics() {
	return array(
		array(
			'id'    => 'getting-started',
			'label' => __( 'Getting Started', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_getting_started',
		),
		array(
			'id'    => 'layout-builder',
			'label' => __( 'Layout Builder', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_layout_builder',
		),
		array(
			'id'    => 'visual-settings',
			'label' => __( 'Visual Settings', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_visual_settings',
		),
		array(
			'id'    => 'ticker',
			'label' => __( 'Breaking Ticker', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_ticker',
		),
		array(
			'id'    => 'slider',
			'label' => __( 'Featured Slider', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_slider',
		),
		array(
			'id'    => 'rss-importer',
			'label' => __( 'RSS Importer', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_rss_importer',
		),
		array(
			'id'    => 'posts',
			'label' => __( 'Writing Posts', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_posts',
		),
		array(
			'id'    => 'categories',
			'label' => __( 'Categories & Tags', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_categories',
		),
		array(
			'id'    => 'menus',
			'label' => __( 'Header Menu', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_menus',
		),
		array(
			'id'    => 'comments',
			'label' => __( 'Comments', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_comments',
		),
		array(
			'id'    => 'troubleshooting',
			'label' => __( 'Troubleshooting', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_troubleshooting',
		),
		array(
			'id'    => 'support',
			'label' => __( 'Support', 'the-grid-index' ),
			'body'  => 'gip_kb_topic_support',
		),
	);
}

/**
 * Render the Knowledge Base page.
 */
function gip_kb_render_page() {
	$topics = gip_kb_topics();
	?>
	<div class="wrap gip-kb">
		<header class="gip-kb__hero">
			<div class="gip-kb__hero-inner">
				<div>
					<p class="gip-kb__kicker">GRID INDEX PRESS</p>
					<h1 class="gip-kb__title"><?php esc_html_e( 'Knowledge Base', 'the-grid-index' ); ?></h1>
					<p class="gip-kb__sub"><?php esc_html_e( 'How to use the editorial features of your Grid Index site.', 'the-grid-index' ); ?></p>
				</div>
				<a class="gip-kb__support-link" href="https://thegridindex.com/" target="_blank" rel="noopener">
					<?php esc_html_e( 'Need more help? Visit thegridindex.com →', 'the-grid-index' ); ?>
				</a>
			</div>
		</header>

		<div class="gip-kb__layout">
			<aside class="gip-kb__rail" aria-label="<?php esc_attr_e( 'Topics', 'the-grid-index' ); ?>">
				<div class="gip-kb__rail-header"><?php esc_html_e( 'TOPICS', 'the-grid-index' ); ?></div>
				<nav>
					<ul>
						<?php foreach ( $topics as $i => $t ) : ?>
							<li>
								<a href="#<?php echo esc_attr( $t['id'] ); ?>"
								   class="gip-kb__rail-link<?php echo $i === 0 ? ' is-active' : ''; ?>"
								   data-topic="<?php echo esc_attr( $t['id'] ); ?>">
									<?php echo esc_html( $t['label'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
			</aside>

			<main class="gip-kb__content">
				<?php foreach ( $topics as $t ) : ?>
					<section id="<?php echo esc_attr( $t['id'] ); ?>" class="gip-kb__topic">
						<h2 class="gip-kb__topic-title"><?php echo esc_html( $t['label'] ); ?></h2>
						<?php if ( is_callable( $t['body'] ) ) call_user_func( $t['body'] ); ?>
					</section>
				<?php endforeach; ?>
			</main>
		</div>
	</div>

	<style>
		body.gip-kb-host #wpcontent,
		body.gip-kb-host #wpbody-content { background: #0B0F14 !important; padding-bottom: 80px; }
		body.gip-kb-host #wpfooter { background: #0B0F14; color: #94a3b8 !important; }
		body.gip-kb-host #wpfooter a, body.gip-kb-host #wpfooter p { color: #94a3b8 !important; }

		.gip-kb { max-width: 1280px; margin: 24px auto 0; padding: 0 28px 80px; color: #e2e8f0; }
		.gip-kb h1, .gip-kb h2, .gip-kb h3, .gip-kb p, .gip-kb li { color: #e2e8f0; }
		.gip-kb a { color: #14b8a6; text-decoration: none; }
		.gip-kb a:hover { color: #2dd4bf; text-decoration: underline; }

		/* Hero */
		.gip-kb__hero {
			background: linear-gradient(135deg, #0F1A2E 0%, #0B1320 100%);
			border: 1px solid #1B2A40;
			border-radius: 12px;
			padding: 28px 32px;
			margin-bottom: 24px;
		}
		.gip-kb__hero-inner {
			display: flex;
			justify-content: space-between;
			align-items: flex-end;
			gap: 24px;
			flex-wrap: wrap;
		}
		.gip-kb__kicker {
			font: 700 11px/1 -apple-system, sans-serif;
			letter-spacing: 1.6px;
			color: #14b8a6;
			margin: 0 0 8px;
			text-transform: uppercase;
		}
		.gip-kb__title {
			font: 700 28px/1.1 Georgia, "Times New Roman", serif;
			margin: 0 0 6px;
			letter-spacing: -.01em;
			color: #f8fafc;
		}
		.gip-kb__sub {
			margin: 0;
			color: #cbd5e1;
			font: 14px/1.5 Georgia, serif;
		}
		.gip-kb__support-link {
			padding: 10px 16px;
			border: 1px solid #14b8a6;
			border-radius: 8px;
			font: 600 12px/1 -apple-system, sans-serif;
			letter-spacing: .04em;
			text-transform: uppercase;
			white-space: nowrap;
		}
		.gip-kb__support-link:hover { background: rgba(20,184,166,.1); }

		/* Layout */
		.gip-kb__layout {
			display: grid;
			grid-template-columns: 240px minmax(0, 1fr);
			gap: 24px;
			align-items: start;
		}
		@media (max-width: 900px) {
			.gip-kb__layout { grid-template-columns: 1fr; }
		}

		/* Rail */
		.gip-kb__rail {
			background: #0F1A2C;
			border: 1px solid #1B2A40;
			border-radius: 12px;
			padding: 16px 0;
			position: sticky;
			top: 50px;
		}
		.gip-kb__rail-header {
			font: 700 10px/1 -apple-system, sans-serif;
			letter-spacing: 1.6px;
			color: #64748b;
			padding: 0 18px 12px;
			border-bottom: 1px solid #1B2A40;
			margin-bottom: 8px;
		}
		.gip-kb__rail ul { list-style: none; margin: 0; padding: 0; }
		.gip-kb__rail-link {
			display: block;
			padding: 10px 18px;
			color: #cbd5e1;
			text-decoration: none;
			font: 500 13px/1.3 -apple-system, sans-serif;
			border-left: 3px solid transparent;
			transition: background .12s ease, color .12s ease, border-color .12s ease;
		}
		.gip-kb__rail-link:hover {
			background: rgba(20,184,166,.06);
			color: #f8fafc;
		}
		.gip-kb__rail-link.is-active {
			color: #14b8a6;
			border-left-color: #14b8a6;
			background: rgba(20,184,166,.08);
			font-weight: 600;
		}

		/* Content */
		.gip-kb__content {
			background: #0F1A2C;
			border: 1px solid #1B2A40;
			border-radius: 12px;
			padding: 32px 36px;
		}
		.gip-kb__topic {
			padding-bottom: 36px;
			margin-bottom: 36px;
			border-bottom: 1px solid #1B2A40;
		}
		.gip-kb__topic:last-child { border-bottom: 0; margin-bottom: 0; padding-bottom: 0; }
		.gip-kb__topic-title {
			font: 700 22px/1.2 Georgia, "Times New Roman", serif;
			color: #f8fafc;
			margin: 0 0 18px;
			letter-spacing: -.01em;
		}
		.gip-kb__topic h3 {
			font: 700 14px/1.3 -apple-system, sans-serif;
			color: #f8fafc;
			margin: 24px 0 8px;
			letter-spacing: .01em;
		}
		.gip-kb__topic p {
			font: 14px/1.6 Georgia, serif;
			color: #cbd5e1;
			margin: 0 0 12px;
		}
		.gip-kb__topic ul, .gip-kb__topic ol {
			margin: 0 0 16px 22px;
			color: #cbd5e1;
			font: 14px/1.65 Georgia, serif;
		}
		.gip-kb__topic li { margin-bottom: 6px; }
		.gip-kb__topic code {
			background: #1f2937;
			color: #5eead4;
			padding: 2px 6px;
			border-radius: 4px;
			font-family: SFMono-Regular, Menlo, Consolas, monospace;
			font-size: 12.5px;
		}
		.gip-kb__topic strong { color: #f8fafc; }
		.gip-kb__callout {
			background: rgba(20,184,166,.08);
			border-left: 3px solid #14b8a6;
			padding: 14px 18px;
			border-radius: 4px;
			margin: 16px 0;
		}
		.gip-kb__callout p { margin-bottom: 0; }
	</style>

	<script>
		(function(){
			// Highlight active topic in rail as user scrolls
			var links = document.querySelectorAll('.gip-kb__rail-link');
			var topics = document.querySelectorAll('.gip-kb__topic');
			if (!links.length || !topics.length) return;

			function setActive(id) {
				links.forEach(function(l){
					l.classList.toggle('is-active', l.getAttribute('data-topic') === id);
				});
			}

			// IntersectionObserver to detect which topic is in view
			if ('IntersectionObserver' in window) {
				var io = new IntersectionObserver(function(entries){
					entries.forEach(function(e){
						if (e.isIntersecting) setActive(e.target.id);
					});
				}, { rootMargin: '-30% 0px -60% 0px' });
				topics.forEach(function(t){ io.observe(t); });
			}

			// Smooth scroll on rail click + immediate highlight
			links.forEach(function(l){
				l.addEventListener('click', function(e){
					var id = l.getAttribute('data-topic');
					setActive(id);
				});
			});
		})();
	</script>
	<?php
}

/**
 * Body class so we can darken the page canvas on the KB page.
 */
add_filter( 'admin_body_class', function( $classes ) {
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	if ( $page === 'gip-knowledge-base' ) {
		$classes .= ' gip-kb-host';
	}
	return $classes;
} );

/* ============================================================
 * Topic body callbacks
 * Each function echoes the HTML for one topic section.
 * ============================================================ */

function gip_kb_topic_getting_started() {
	?>
	<p>Welcome to your Grid Index editorial site. This knowledge base covers everything you need to run a Bloomberg/Semafor-style news destination — from publishing posts to tuning the homepage layout.</p>

	<h3>The first 30 minutes</h3>
	<ol>
		<li>Visit <strong>Visual Settings</strong> and pick your default appearance mode (Dark Intelligence is the signature look).</li>
		<li>Set your site logo and wordmark text under the Header section of Visual Settings.</li>
		<li>Open <strong>Layout Builder</strong> and review the homepage section order. Toggle off anything you don't want; drag to reorder.</li>
		<li>Add a few posts (or use the RSS Importer to pull from external sources).</li>
		<li>Mark some posts with the <strong>Featured Slider</strong> category to populate the homepage hero carousel.</li>
		<li>Tick the <strong>Breaking</strong> category on a high-priority post to surface it in the ticker.</li>
	</ol>

	<div class="gip-kb__callout">
		<p><strong>Tip:</strong> The homepage is built dynamically from your posts. You don't manually arrange the homepage layout post-by-post — instead, you publish posts and the theme decides where they land based on category, recency, and the rules you set in Layout Builder.</p>
	</div>
	<?php
}

function gip_kb_topic_layout_builder() {
	?>
	<p>The Layout Builder controls which homepage sections appear and in what order. Each section is a row on the homepage — Breaking ticker, Live Hero Deck, Top Stories grid, Latest feed, and so on.</p>

	<h3>How to use it</h3>
	<ul>
		<li><strong>Drag the handle</strong> on the left of any section to reorder it.</li>
		<li><strong>Toggle the switch</strong> on the right to enable or disable a section without losing its settings.</li>
		<li><strong>Click the gear icon</strong> on a section to configure its specific settings (post count, source category, etc.).</li>
		<li><strong>Click the X</strong> to remove a section permanently.</li>
		<li><strong>Add Section</strong> at the top adds back any section you removed.</li>
		<li><strong>Save Layout</strong> commits all changes.</li>
		<li><strong>Reset to defaults</strong> restores the original homepage configuration.</li>
	</ul>

	<h3>Live Preview</h3>
	<p>The bottom of the Layout Builder page shows a live preview of your homepage. Toggle Desktop / Tablet / Mobile to see how the layout responds at different sizes.</p>
	<?php
}

function gip_kb_topic_visual_settings() {
	?>
	<p>Visual Settings (formerly "Theme Options") controls the editorial aesthetic of your site — colors, density, header style, footer, and admin UI preview.</p>

	<h3>Sections</h3>
	<ul>
		<li><strong>General</strong> — default frontend design mode (Dark Intelligence vs Light Editorial), appearance mode, editorial density, accent color.</li>
		<li><strong>Design</strong> — typography choices, color overrides, custom CSS.</li>
		<li><strong>Header</strong> — logo upload, wordmark text, tagline, sticky behavior, mobile menu style.</li>
		<li><strong>Homepage</strong> & <strong>Homepage Sections</strong> — global homepage controls (overlap with Layout Builder for some settings).</li>
		<li><strong>Cards</strong> — how article cards render across the site.</li>
		<li><strong>Article Page</strong> — single-post layout, author byline, comment behavior.</li>
		<li><strong>Archive / Category</strong> — how category and tag pages display.</li>
		<li><strong>Footer</strong> — newsletter CTA, social links, copyright text.</li>
		<li><strong>Ads</strong> — ad slot configuration.</li>
		<li><strong>Performance / Advanced</strong> — caching, preload, query optimization.</li>
		<li><strong>Debug</strong> — diagnostics for troubleshooting.</li>
	</ul>

	<div class="gip-kb__callout">
		<p><strong>Save Changes</strong> at the top or bottom of the page commits all sections at once. Changes are non-destructive — the <strong>Reset</strong> button restores defaults.</p>
	</div>
	<?php
}

function gip_kb_topic_ticker() {
	?>
	<p>The Breaking Ticker is the thin red-and-text strip below the masthead that scrolls high-priority headlines.</p>

	<h3>Four ways to put a story on the ticker (in priority order)</h3>
	<ol>
		<li><strong>Pin manually</strong> — go to Posts → All Posts, hover any published post, click <strong>Pin to Ticker</strong>. Pinned items always show first.</li>
		<li><strong>Breaking category (last 24h)</strong> — assign the <code>Breaking</code> category to a post in the editor. Auto-expires after 24 hours.</li>
		<li><strong>Ticker tag</strong> — add the <code>ticker</code> tag to any post. Used after Breaking is empty. Doesn't expire.</li>
		<li><strong>Latest posts (auto fallback)</strong> — if nothing else qualifies, the most recent published posts fill the ticker so it's never empty.</li>
	</ol>

	<h3>Custom ticker items</h3>
	<p>The Ticker admin page has a <strong>Quick Add</strong> form for one-off items that aren't actual posts (external links, sponsor messages, urgent banners). These go in alongside post-based items.</p>

	<h3>LIVE red dot</h3>
	<p>Tick "Show LIVE red dot" on a custom ticker item to add a pulsing red indicator next to it — useful for live-blog or developing-story callouts.</p>
	<?php
}

function gip_kb_topic_slider() {
	?>
	<p>The Featured Slider is the cinematic carousel at the top of the homepage. It auto-advances and pauses when the visitor hovers.</p>

	<h3>Three ways to add slides</h3>
	<ol>
		<li><strong>Tick "Featured Slider" on a post</strong> — in the post editor under Categories, check <code>Featured Slider</code>. The slider uses the post's featured image (falls back to the topic-branded image if missing).</li>
		<li><strong>Use the row action</strong> — Posts → All Posts → hover a row → click <strong>Add to Slider</strong>.</li>
		<li><strong>Add a custom slide</strong> — use the form on the Slider admin page for external links, ads, or one-off promos.</li>
	</ol>

	<h3>Settings</h3>
	<ul>
		<li><strong>Max slides</strong> — how many slides to show (default 6).</li>
		<li><strong>Autoplay</strong> — auto-advance on/off, with a per-slide duration setting.</li>
		<li><strong>Transition</strong> — slide horizontal, fade, or none.</li>
		<li><strong>Controls</strong> — show/hide prev/next arrows, pagination dots, kicker labels.</li>
	</ul>

	<div class="gip-kb__callout">
		<p><strong>Flush slider cache</strong> if a slide looks stuck after edits. The slider caches its rendered HTML for performance.</p>
	</div>
	<?php
}

function gip_kb_topic_rss_importer() {
	?>
	<p>The RSS Importer is a separate plugin that pulls articles from external news feeds and creates posts in your WordPress install. Find it in the left sidebar at <strong>RSS Importer</strong>.</p>

	<h3>How it works</h3>
	<ol>
		<li>Add an RSS feed URL (the Importer ships with 16 starter feeds — NYT, BBC, NPR, Guardian, AP, TechCrunch, Verge, etc.).</li>
		<li>The plugin runs every hour (or whatever cron interval you set) and fetches new items from each feed.</li>
		<li>Each item becomes a published post in the <code>RSS</code> category, with the source URL stored as post meta.</li>
		<li>The theme detects RSS-imported posts and shows the source attribution + "Continue reading at [Source] →" CTA.</li>
	</ol>

	<h3>Settings worth knowing</h3>
	<ul>
		<li><strong>Default post status</strong> — ships set to <code>publish</code> (was <code>draft</code> in early versions).</li>
		<li><strong>Min image width</strong> — posts whose featured images are below this width get skipped (default 1000px). Prevents low-quality thumbnails.</li>
		<li><strong>Skip codes</strong> in the run log: <code>skip (dup)</code>, <code>skip (no image)</code>, <code>skip (image too small WxH)</code>.</li>
	</ul>

	<h3>Per-feed Fetch button</h3>
	<p>Each feed row has a Fetch button to manually run that feed once. Status badge shows: gray (never run), green (ok), blue (all-dup), yellow (empty), red (error).</p>

	<h3>Force re-import last 24h</h3>
	<p>Bulk button at the top of the page deletes recent imports and re-fetches them with current settings — useful after changing the min image width or other gates.</p>
	<?php
}

function gip_kb_topic_posts() {
	?>
	<p>Posts are the basic content unit. The theme treats every post as a potential story.</p>

	<h3>What matters for the homepage</h3>
	<ul>
		<li><strong>Featured image</strong> — required. Posts without a featured image are filtered out of homepage queries (set under Visual Settings → Homepage Sections if you want to relax this).</li>
		<li><strong>Category</strong> — controls which homepage section the post can appear in. Posts in <code>Featured Slider</code> appear in the slider. Posts in <code>Breaking</code> appear in the ticker for 24h. Other posts go into Top Stories, Latest, etc.</li>
		<li><strong>Excerpt</strong> — used as the dek on cards and in the hero block. Keep it tight — 1–2 sentences.</li>
		<li><strong>Tags</strong> — used by the Trending Entities rail.</li>
	</ul>

	<h3>Single-post page</h3>
	<p>Configure under Visual Settings → Article Page. Options include hero image style, byline display, related posts, and comment toggle.</p>

	<h3>Imported post truncation</h3>
	<p>RSS-imported posts (detected via the <code>_gridindex_source_url</code> meta) automatically truncate to the first 3 paragraphs on display, with a "Continue reading at [Source] →" CTA. To opt out for a specific post, add custom field <code>gip_show_full_content = 1</code>.</p>
	<?php
}

function gip_kb_topic_categories() {
	?>
	<p>Categories drive the editorial structure. The theme has special-purpose categories that trigger features:</p>

	<h3>Reserved categories</h3>
	<ul>
		<li><code>Featured Slider</code> — posts here appear in the homepage slider.</li>
		<li><code>Breaking</code> — posts here go to the ticker for 24h.</li>
		<li><code>RSS</code> — auto-created by the RSS Importer; every imported post is forced into it.</li>
	</ul>

	<h3>Editorial categories</h3>
	<p>Create whatever categories make sense for your beats — Tech, Business, AI, World, Politics, Culture. The header menu (configured at Appearance → Menus) typically links to category archives.</p>

	<h3>Tags</h3>
	<p>Tags are entity markers — companies, people, products, places. The Trending Entities rail on the homepage surfaces tags that have momentum (multiple recent posts).</p>
	<?php
}

function gip_kb_topic_menus() {
	?>
	<p>The header navigation menu is configured at <strong>Appearance → Menus</strong>.</p>

	<h3>Setup</h3>
	<ol>
		<li>Go to Appearance → Menus.</li>
		<li>Create a menu (or edit the existing "Main" menu).</li>
		<li>Add categories from the left side panel (Categories accordion → check the ones you want → Add to Menu).</li>
		<li>Drag to reorder.</li>
		<li>Under <strong>Menu Settings → Display location</strong>, tick <strong>Primary Menu</strong>.</li>
		<li>Save Menu.</li>
	</ol>

	<h3>Display</h3>
	<p>The theme shows the menu in the header strip, between the wordmark and the LIVE badge. Items are styled in small uppercase letterspaced caps separated by middle dots.</p>

	<div class="gip-kb__callout">
		<p><strong>Note:</strong> If your menu items disappear on the homepage but show on article pages, that's almost certainly a query-filter conflict. Check that you're on the latest theme version — this was a known bug in earlier 1.10.x releases.</p>
	</div>
	<?php
}

function gip_kb_topic_comments() {
	?>
	<p>Comments are enabled by default on posts. The theme renders them in a dark editorial card matching the rest of the article page.</p>

	<h3>Configuration</h3>
	<ul>
		<li><strong>Settings → Discussion</strong> (WordPress core) controls global comment behavior — moderation, allow/disallow, threading depth.</li>
		<li><strong>Per-post toggle</strong> — disable comments on a specific post via the Discussion meta box in the editor (enable Discussion under Screen Options if you don't see it).</li>
		<li><strong>Visual Settings → Article Page</strong> — global show/hide toggle for comments across the site.</li>
	</ul>

	<h3>Themed comment form</h3>
	<p>The comment form is themed to match the editorial UI: serif heading, dark textarea with teal focus ring, teal "Post Comment" button. The "Posting as" line and login prompt are styled subtly.</p>
	<?php
}

function gip_kb_topic_troubleshooting() {
	?>
	<h3>Header menu missing on homepage but works on article pages</h3>
	<p>Caused by a homepage query filter that doesn't exclude nav menu items. Make sure you're running the current theme version where this is handled, then purge LiteSpeed cache.</p>

	<h3>Source Intelligence rail only shows a few items instead of the configured number</h3>
	<p>The homepage dedup module limits how many secondary queries the main column runs to keep page generation fast. If you need a deeper rail, lower the dedup budget in <code>inc/homepage-dedup.php</code> by editing the <code>MAIN_COLUMN_QUERY_BUDGET</code> constant. Default is 4.</p>

	<h3>Theme screenshot in Themes admin doesn't update after upload</h3>
	<p>WordPress caches screenshots aggressively keyed by the version string in <code>style.css</code>. After updating, try a hard refresh (Cmd+Shift+R / Ctrl+Shift+R) or open the Themes page in an incognito window.</p>

	<h3>Imported posts are too long / republishing full source content</h3>
	<p>Imported posts are auto-truncated to 3 paragraphs with a "Continue reading at [Source] →" CTA. To show the full content for a specific post, add custom field <code>gip_show_full_content = 1</code>.</p>

	<h3>Comments form has doubled border or oversized textarea</h3>
	<p>The comment polish module flattens the doubled-border treatment and sets the textarea to 140px. If you're seeing the older default, your browser may be caching old CSS — purge LiteSpeed cache and hard-refresh.</p>

	<h3>Admin pages look like default WordPress instead of dark editorial UI</h3>
	<p>Caused by an asset enqueue mismatch — the theme's admin CSS detects which page you're on by URL rather than internal hook suffix. If you see this, purge LiteSpeed cache and hard-refresh; the styles should reload.</p>

	<h3>LiteSpeed Cache stale after theme update</h3>
	<p>Always purge cache after activating a new theme version: <strong>LiteSpeed Cache → Toolbox → Purge → Purge All</strong>. Then hard refresh the browser.</p>
	<?php
}

function gip_kb_topic_support() {
	?>
	<p>For documentation, theme updates, and additional resources visit <a href="https://thegridindex.com/" target="_blank" rel="noopener">thegridindex.com</a>.</p>

	<h3>Quick links</h3>
	<ul>
		<li>Theme homepage and changelog: <a href="https://thegridindex.com/" target="_blank" rel="noopener">thegridindex.com</a></li>
		<li>WordPress core documentation: <a href="https://wordpress.org/documentation/" target="_blank" rel="noopener">wordpress.org/documentation</a></li>
		<li>Hostinger LiteSpeed cache controls: in your WP admin sidebar under <strong>LiteSpeed Cache</strong></li>
	</ul>

	<h3>Reporting issues</h3>
	<p>If you find a bug, note the version (<strong>Visual Settings</strong> page → top-right corner shows the current version) and a description of what you expected vs. what happened. Screenshots help.</p>

	<h3>The Support button</h3>
	<p>The <strong>Support</strong> button in the top admin bar (next to "Howdy, [your email]") opens thegridindex.com in a new tab.</p>
	<?php
}
