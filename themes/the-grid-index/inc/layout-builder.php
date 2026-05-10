<?php
/**
 * Grid Index Layout Builder
 *
 * Custom editorial layout system that replaces reliance on the default
 * WordPress Widgets UI for the homepage. Provides:
 *
 *   • Top-level admin menu  : Grid Index → Layout Builder
 *   • Section registry      : 15 editorial section types
 *   • Drag/drop reorder     : jQuery UI sortable, persisted to options
 *   • Per-section settings  : category, count, card style, density, bg, visibility, mobile
 *   • Live preview pane     : iframe of homepage, desktop/tablet/mobile, instant refresh
 *   • Render dispatcher     : do_action( 'gip_render_layout' ) used by front-page.php
 *
 * Storage:
 *   option `gridindex_layout_sections` — ordered array of section configs.
 *
 * @package The_Grid_Index
 */

defined( 'ABSPATH' ) || exit;

/* ============================================================
 * Section type registry
 * ============================================================ */

if ( ! function_exists( 'gip_lb_section_types' ) ) :
function gip_lb_section_types() {
	return array(
		'live_hero_deck'        => array( 'label' => __( 'Live Hero Deck', 'the-grid-index' ),         'desc' => __( 'Cinematic carousel with accelerating signal stack.', 'the-grid-index' ),  'icon' => '◉' ),
		'breaking_strip'        => array( 'label' => __( 'Breaking Strip', 'the-grid-index' ),         'desc' => __( 'Marquee ticker of latest signals.', 'the-grid-index' ),                  'icon' => '⟶' ),
		'accelerating_stories'  => array( 'label' => __( 'Accelerating Stories', 'the-grid-index' ),   'desc' => __( 'Stories gaining momentum across sources.', 'the-grid-index' ),           'icon' => '↗' ),
		'top_stories_grid'      => array( 'label' => __( 'Top Stories Grid', 'the-grid-index' ),       'desc' => __( 'Editorial 3-column card grid.', 'the-grid-index' ),                      'icon' => '▦' ),
		'latest_feed'           => array( 'label' => __( 'Latest Feed', 'the-grid-index' ),            'desc' => __( 'Continuous list of newest posts.', 'the-grid-index' ),                   'icon' => '☰' ),
		'topic_dashboard'       => array( 'label' => __( 'Topic Dashboard', 'the-grid-index' ),        'desc' => __( 'Per-category mini desks (AI, Tech, Startups, Cyber).', 'the-grid-index' ), 'icon' => '◰' ),
		'source_intel_rail'     => array( 'label' => __( 'Source Intelligence Rail', 'the-grid-index' ), 'desc' => __( 'Sticky rail of source distribution + signals.', 'the-grid-index' ),    'icon' => '┃' ),
		'trending_entities'     => array( 'label' => __( 'Trending Entities', 'the-grid-index' ),      'desc' => __( 'Entities mentioned across stories.', 'the-grid-index' ),                 'icon' => '#' ),
		'most_discussed'        => array( 'label' => __( 'Most Discussed', 'the-grid-index' ),         'desc' => __( 'Stories with highest engagement.', 'the-grid-index' ),                   'icon' => '✦' ),
		'editor_picks'          => array( 'label' => __( 'Editor Picks', 'the-grid-index' ),           'desc' => __( 'Curated by editorial (sticky posts).', 'the-grid-index' ),               'icon' => '★' ),
		'video_rail'            => array( 'label' => __( 'Video Rail', 'the-grid-index' ),             'desc' => __( 'Posts tagged with video content.', 'the-grid-index' ),                   'icon' => '▷' ),
		'ai_summary_rail'       => array( 'label' => __( 'AI Summary Rail', 'the-grid-index' ),        'desc' => __( 'AI-generated cross-source summaries.', 'the-grid-index' ),               'icon' => '◈' ),
		'newsletter_cta'        => array( 'label' => __( 'Newsletter CTA', 'the-grid-index' ),         'desc' => __( 'Daily intelligence brief signup.', 'the-grid-index' ),                   'icon' => '✉' ),
		'market_data'           => array( 'label' => __( 'Market / Data Module', 'the-grid-index' ),   'desc' => __( 'Market tickers / data widget.', 'the-grid-index' ),                      'icon' => '$' ),
		'sponsored'             => array( 'label' => __( 'Sponsored Module', 'the-grid-index' ),       'desc' => __( 'Editorial-styled sponsored slot.', 'the-grid-index' ),                   'icon' => '◆' ),
	);
}
endif;

/* ============================================================
 * Defaults + storage
 * ============================================================ */

if ( ! function_exists( 'gip_lb_default_sections' ) ) :
function gip_lb_default_sections() {
	return array(
		array( 'type' => 'breaking_strip',       'enabled' => 1, 'category' => 0, 'count' => 8,  'card' => 'minimal',    'bg' => 'transparent', 'density' => 'comfortable', 'mobile' => 'show', 'visibility' => 'all', 'placement' => 'main' ),
		array( 'type' => 'live_hero_deck',       'enabled' => 1, 'category' => 0, 'count' => 5,  'card' => 'editorial',  'bg' => 'transparent', 'density' => 'comfortable', 'mobile' => 'show', 'visibility' => 'all', 'placement' => 'main' ),
		array( 'type' => 'accelerating_stories', 'enabled' => 1, 'category' => 0, 'count' => 3,  'card' => 'signal',     'bg' => 'panel',       'density' => 'comfortable', 'mobile' => 'show', 'visibility' => 'all', 'placement' => 'main' ),
		array( 'type' => 'top_stories_grid',     'enabled' => 1, 'category' => 0, 'count' => 6,  'card' => 'editorial',  'bg' => 'transparent', 'density' => 'comfortable', 'mobile' => 'show', 'visibility' => 'all', 'placement' => 'main' ),
		array( 'type' => 'topic_dashboard',      'enabled' => 1, 'category' => 0, 'count' => 4,  'card' => 'editorial',  'bg' => 'panel',       'density' => 'comfortable', 'mobile' => 'collapse', 'visibility' => 'all', 'placement' => 'main' ),
		array( 'type' => 'latest_feed',          'enabled' => 1, 'category' => 0, 'count' => 10, 'card' => 'minimal',    'bg' => 'transparent', 'density' => 'compact',     'mobile' => 'show', 'visibility' => 'all', 'placement' => 'main' ),
		array( 'type' => 'source_intel_rail',    'enabled' => 1, 'category' => 0, 'count' => 8,  'card' => 'minimal',    'bg' => 'panel',       'density' => 'compact',     'mobile' => 'collapse', 'visibility' => 'all', 'placement' => 'rail' ),
		array( 'type' => 'trending_entities',    'enabled' => 1, 'category' => 0, 'count' => 8,  'card' => 'minimal',    'bg' => 'transparent', 'density' => 'compact',     'mobile' => 'collapse', 'visibility' => 'all', 'placement' => 'rail' ),
		array( 'type' => 'newsletter_cta',       'enabled' => 1, 'category' => 0, 'count' => 0,  'card' => 'editorial',  'bg' => 'accent',      'density' => 'comfortable', 'mobile' => 'show', 'visibility' => 'all', 'placement' => 'rail' ),
	);
}
endif;

if ( ! function_exists( 'gip_lb_get_sections' ) ) :
function gip_lb_get_sections() {
	$saved = get_option( 'gridindex_layout_sections', null );
	if ( ! is_array( $saved ) || empty( $saved ) ) return gip_lb_default_sections();
	return $saved;
}
endif;

if ( ! function_exists( 'gip_lb_sanitize_sections' ) ) :
function gip_lb_sanitize_sections( $raw ) {
	if ( ! is_array( $raw ) ) return gip_lb_default_sections();
	$types = gip_lb_section_types();
	$out   = array();
	foreach ( $raw as $row ) {
		if ( ! is_array( $row ) || empty( $row['type'] ) || ! isset( $types[ $row['type'] ] ) ) continue;
		$out[] = array(
			'type'       => sanitize_key( $row['type'] ),
			'enabled'    => ! empty( $row['enabled'] ) ? 1 : 0,
			'category'   => isset( $row['category'] ) ? (int) $row['category'] : 0,
			'count'      => isset( $row['count'] ) ? max( 0, min( 30, (int) $row['count'] ) ) : 6,
			'card'       => in_array( $row['card'] ?? 'editorial', array( 'minimal', 'editorial', 'signal', 'cinematic' ), true ) ? $row['card'] : 'editorial',
			'bg'         => in_array( $row['bg'] ?? 'transparent', array( 'transparent', 'panel', 'accent', 'inverse' ), true ) ? $row['bg'] : 'transparent',
			'density'    => in_array( $row['density'] ?? 'comfortable', array( 'compact', 'comfortable', 'spacious' ), true ) ? $row['density'] : 'comfortable',
			'mobile'     => in_array( $row['mobile'] ?? 'show', array( 'show', 'collapse', 'hide' ), true ) ? $row['mobile'] : 'show',
			'visibility' => in_array( $row['visibility'] ?? 'all', array( 'all', 'guests', 'logged_in' ), true ) ? $row['visibility'] : 'all',
			'placement'  => in_array( $row['placement'] ?? 'main', array( 'main', 'rail', 'full' ), true ) ? $row['placement'] : 'main',
		);
	}
	return empty( $out ) ? gip_lb_default_sections() : $out;
}
endif;

/* ============================================================
 * Admin menu
 * ============================================================ */

add_action( 'admin_menu', function() {
	add_menu_page(
		__( 'Grid Index', 'the-grid-index' ),
		__( 'Grid Index', 'the-grid-index' ),
		'manage_options',
		'gridindex',
		'gip_lb_render_admin',
		'dashicons-grid-view',
		58
	);
	add_submenu_page( 'gridindex', __( 'Layout Builder', 'the-grid-index' ), __( 'Layout Builder', 'the-grid-index' ), 'manage_options', 'gridindex', 'gip_lb_render_admin' );
	add_submenu_page( 'gridindex', __( 'Theme Options', 'the-grid-index' ), __( 'Theme Options', 'the-grid-index' ), 'manage_options', 'themes.php?page=gridindex-theme-options' );
}, 8 );

/* ============================================================
 * Save handler (admin-post)
 * ============================================================ */

add_action( 'admin_post_gip_lb_save', function() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
	check_admin_referer( 'gip_lb_save' );

	$raw = isset( $_POST['gip_lb'] ) ? wp_unslash( $_POST['gip_lb'] ) : array();
	$clean = gip_lb_sanitize_sections( $raw );
	update_option( 'gridindex_layout_sections', $clean );
	update_option( 'gridindex_layout_saved_at', time() );

	wp_safe_redirect( add_query_arg( array( 'page' => 'gridindex', 'gip_saved' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

add_action( 'admin_post_gip_lb_reset', function() {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Forbidden' );
	check_admin_referer( 'gip_lb_reset' );
	delete_option( 'gridindex_layout_sections' );
	wp_safe_redirect( add_query_arg( array( 'page' => 'gridindex', 'gip_reset' => 1 ), admin_url( 'admin.php' ) ) );
	exit;
} );

/* ============================================================
 * Admin page render
 * ============================================================ */

add_action( 'admin_enqueue_scripts', function( $hook ) {
	if ( 'toplevel_page_gridindex' !== $hook ) return;
	wp_enqueue_script( 'jquery-ui-sortable' );
	$ver = defined( 'GIP_VERSION' ) ? GIP_VERSION : '1.0.0';
	wp_enqueue_style( 'gip-layout-builder', get_template_directory_uri() . '/assets/admin/layout-builder.css', array(), $ver );
	wp_enqueue_script( 'gip-layout-builder', get_template_directory_uri() . '/assets/admin/layout-builder.js', array( 'jquery', 'jquery-ui-sortable' ), $ver, true );
} );

function gip_lb_render_admin() {
	$sections = gip_lb_get_sections();
	$types    = gip_lb_section_types();
	$cats     = get_categories( array( 'hide_empty' => false, 'number' => 200 ) );
	$home_url = home_url( '/' );
	?>
	<div class="wrap gip-lb">
		<header class="gip-lb__head">
			<div class="gip-lb__brand">
				<span class="gip-lb__mark">◧</span>
				<div>
					<h1>Grid Index — Layout Builder</h1>
					<p>Editorial control center. Drag to reorder, toggle to enable, configure to refine.</p>
				</div>
			</div>
			<div class="gip-lb__head-actions">
				<a class="button" href="<?php echo esc_url( $home_url ); ?>" target="_blank">↗ Open Homepage</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'themes.php?page=gridindex-theme-options' ) ); ?>">Theme Options</a>
			</div>
		</header>

		<?php if ( ! empty( $_GET['gip_saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Layout saved. Preview refreshed.</p></div>
		<?php endif; ?>
		<?php if ( ! empty( $_GET['gip_reset'] ) ) : ?>
			<div class="notice notice-warning is-dismissible"><p>Layout reset to defaults.</p></div>
		<?php endif; ?>

		<div class="gip-lb__layout">

			<form class="gip-lb__editor" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="gip_lb_save" />
				<?php wp_nonce_field( 'gip_lb_save' ); ?>

				<div class="gip-lb__toolbar">
					<div class="gip-lb__add">
						<label>Add section
							<select id="gip-lb-add-type">
								<?php foreach ( $types as $key => $t ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $t['icon'] . '  ' . $t['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<button type="button" class="button button-secondary" id="gip-lb-add-btn">+ Add</button>
					</div>
					<div class="gip-lb__save">
						<button class="button button-primary button-large" type="submit">💾 Save Layout</button>
					</div>
				</div>

				<ul class="gip-lb__list" id="gip-lb-list">
					<?php foreach ( $sections as $i => $s ) :
						$type  = $s['type'];
						$meta  = isset( $types[ $type ] ) ? $types[ $type ] : array( 'label' => $type, 'desc' => '', 'icon' => '◇' );
						gip_lb_render_section_row( $i, $s, $meta, $cats );
					endforeach; ?>
				</ul>

				<p class="gip-lb__foot">
					<button class="button button-primary button-large" type="submit">💾 Save Layout</button>
				</p>
			</form>

			<form class="gip-lb__reset" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Reset layout to defaults?');">
				<input type="hidden" name="action" value="gip_lb_reset" />
				<?php wp_nonce_field( 'gip_lb_reset' ); ?>
				<button class="button button-link-delete" type="submit">Reset to defaults</button>
			</form>

			<aside class="gip-lb__preview">
				<div class="gip-lb__preview-head">
					<strong>Live Preview</strong>
					<div class="gip-lb__viewports" role="tablist">
						<button type="button" class="is-active" data-vp="desktop" aria-pressed="true">Desktop</button>
						<button type="button" data-vp="tablet" aria-pressed="false">Tablet</button>
						<button type="button" data-vp="mobile" aria-pressed="false">Mobile</button>
						<button type="button" id="gip-lb-refresh" title="Refresh">↻</button>
					</div>
				</div>
				<div class="gip-lb__preview-frame" data-vp="desktop">
					<iframe id="gip-lb-iframe" src="<?php echo esc_url( add_query_arg( 'gip_preview', 1, $home_url ) ); ?>" loading="lazy"></iframe>
				</div>
			</aside>
		</div>

		<template id="gip-lb-template">
			<?php gip_lb_render_section_row( '__INDEX__', array(
				'type' => '__TYPE__', 'enabled' => 1, 'category' => 0, 'count' => 6,
				'card' => 'editorial', 'bg' => 'transparent', 'density' => 'comfortable',
				'mobile' => 'show', 'visibility' => 'all', 'placement' => 'main',
			), array( 'label' => '__LABEL__', 'desc' => '', 'icon' => '◇' ), $cats ); ?>
		</template>

		<script>
			window.gipLbTypes = <?php echo wp_json_encode( $types ); ?>;
		</script>
	</div>
	<?php
}

function gip_lb_render_section_row( $i, $s, $meta, $cats ) {
	$type = $s['type'];
	?>
	<li class="gip-lb__row<?php echo empty( $s['enabled'] ) ? ' is-disabled' : ''; ?>" data-type="<?php echo esc_attr( $type ); ?>">
		<div class="gip-lb__row-head">
			<span class="gip-lb__handle" title="Drag to reorder">⋮⋮</span>
			<span class="gip-lb__icon"><?php echo esc_html( $meta['icon'] ); ?></span>
			<div class="gip-lb__row-title">
				<strong><?php echo esc_html( $meta['label'] ); ?></strong>
				<span class="gip-lb__row-desc"><?php echo esc_html( $meta['desc'] ); ?></span>
			</div>
			<label class="gip-lb__switch">
				<input type="checkbox" name="gip_lb[<?php echo esc_attr( $i ); ?>][enabled]" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?> />
				<span></span>
			</label>
			<button type="button" class="gip-lb__toggle" aria-expanded="false" title="Configure">⚙</button>
			<button type="button" class="gip-lb__remove" title="Remove">✕</button>
		</div>
		<div class="gip-lb__row-body">
			<input type="hidden" name="gip_lb[<?php echo esc_attr( $i ); ?>][type]" value="<?php echo esc_attr( $type ); ?>" />
			<div class="gip-lb__grid">
				<label>Category
					<select name="gip_lb[<?php echo esc_attr( $i ); ?>][category]">
						<option value="0">Latest (all)</option>
						<?php foreach ( $cats as $c ) : ?>
							<option value="<?php echo (int) $c->term_id; ?>" <?php selected( (int) $s['category'], (int) $c->term_id ); ?>><?php echo esc_html( $c->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Stories
					<input type="number" min="0" max="30" name="gip_lb[<?php echo esc_attr( $i ); ?>][count]" value="<?php echo esc_attr( $s['count'] ); ?>" />
				</label>
				<label>Card style
					<select name="gip_lb[<?php echo esc_attr( $i ); ?>][card]">
						<?php foreach ( array( 'minimal', 'editorial', 'signal', 'cinematic' ) as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $s['card'], $opt ); ?>><?php echo esc_html( ucfirst( $opt ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Background
					<select name="gip_lb[<?php echo esc_attr( $i ); ?>][bg]">
						<?php foreach ( array( 'transparent', 'panel', 'accent', 'inverse' ) as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $s['bg'], $opt ); ?>><?php echo esc_html( ucfirst( $opt ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Density
					<select name="gip_lb[<?php echo esc_attr( $i ); ?>][density]">
						<?php foreach ( array( 'compact', 'comfortable', 'spacious' ) as $opt ) : ?>
							<option value="<?php echo esc_attr( $opt ); ?>" <?php selected( $s['density'], $opt ); ?>><?php echo esc_html( ucfirst( $opt ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Placement
					<select name="gip_lb[<?php echo esc_attr( $i ); ?>][placement]">
						<option value="main" <?php selected( $s['placement'], 'main' ); ?>>Main column</option>
						<option value="rail" <?php selected( $s['placement'], 'rail' ); ?>>Right rail</option>
						<option value="full" <?php selected( $s['placement'], 'full' ); ?>>Full width</option>
					</select>
				</label>
				<label>Mobile
					<select name="gip_lb[<?php echo esc_attr( $i ); ?>][mobile]">
						<option value="show" <?php selected( $s['mobile'], 'show' ); ?>>Show</option>
						<option value="collapse" <?php selected( $s['mobile'], 'collapse' ); ?>>Collapse</option>
						<option value="hide" <?php selected( $s['mobile'], 'hide' ); ?>>Hide</option>
					</select>
				</label>
				<label>Visibility
					<select name="gip_lb[<?php echo esc_attr( $i ); ?>][visibility]">
						<option value="all" <?php selected( $s['visibility'], 'all' ); ?>>Everyone</option>
						<option value="guests" <?php selected( $s['visibility'], 'guests' ); ?>>Guests only</option>
						<option value="logged_in" <?php selected( $s['visibility'], 'logged_in' ); ?>>Logged-in only</option>
					</select>
				</label>
			</div>
		</div>
	</li>
	<?php
}

/* ============================================================
 * Frontend dispatcher
 * ============================================================ */

if ( ! function_exists( 'gip_lb_render_layout' ) ) :
function gip_lb_render_layout( $placement = 'main' ) {
	$sections = gip_lb_get_sections();
	$is_user  = is_user_logged_in();
	foreach ( $sections as $s ) {
		if ( empty( $s['enabled'] ) ) continue;
		if ( ( $s['placement'] ?? 'main' ) !== $placement ) continue;
		if ( 'guests' === $s['visibility'] && $is_user ) continue;
		if ( 'logged_in' === $s['visibility'] && ! $is_user ) continue;

		$mobile_class  = 'gi-lb-mobile-' . esc_attr( $s['mobile'] );
		$density_class = 'gi-lb-d-' . esc_attr( $s['density'] );
		$bg_class      = 'gi-lb-bg-' . esc_attr( $s['bg'] );
		$card_class    = 'gi-lb-card-' . esc_attr( $s['card'] );

		echo '<div class="gi-lb-section ' . esc_attr( "$mobile_class $density_class $bg_class $card_class" ) . '" data-section="' . esc_attr( $s['type'] ) . '">';
		if ( current_user_can( 'manage_options' ) ) {
			echo "<!-- gip-lb section: {$s['type']} | placement: {$placement} | cat: {$s['category']} | count: {$s['count']} -->\n";
		}
		gip_lb_render_section( $s );
		echo '</div>';
	}
}
endif;

if ( ! function_exists( 'gip_lb_render_section' ) ) :
function gip_lb_render_section( $s ) {
	$type = $s['type'];
	switch ( $type ) {

		case 'live_hero_deck':
			// SOURCE OF TRUTH: Customizer / Theme Options (gridindex_theme_options).
			// Section-level count/category act only as a fallback if the option is unset.
			$layout    = (string) gridindex_get_option( 'hero_layout', 'live_deck' );
			$count     = (int)    gridindex_get_option( 'hero_count', max( 2, (int) $s['count'] ) );
			$count     = max( 1, min( 12, $count ?: max( 2, (int) $s['count'] ) ) );
			$category  = (int)    gridindex_get_option( 'hero_category', (int) $s['category'] );
			$autoplay  = (bool)   gridindex_get_option( 'hero_autoplay', 0 );
			$rotation  = (int)    gridindex_get_option( 'hero_rotation', 7000 );
			$show_mom  = (bool)   gridindex_get_option( 'hero_show_momentum', 1 );
			$show_cnt  = (bool)   gridindex_get_option( 'hero_show_count', 1 );
			$ticker    = (bool)   gridindex_get_option( 'enable_ticker', 1 );

			if ( current_user_can( 'manage_options' ) ) {
				echo "\n<!-- Live Deck settings source: option (gridindex_theme_options) -->\n";
				echo "<!-- Hero layout: " . esc_html( $layout ) . " -->\n";
				echo "<!-- Source category: " . esc_html( (string) $category ) . " -->\n";
				echo "<!-- Slide count: " . esc_html( (string) $count ) . " -->\n";
				echo "<!-- Autoplay: " . ( $autoplay ? 'on' : 'off' ) . " -->\n";
				echo "<!-- Rotation interval: " . esc_html( (string) $rotation ) . "ms -->\n";
				echo "<!-- Ticker: " . ( $ticker ? 'on' : 'off' ) . " -->\n";

				$cat_name = $category > 0 ? ( get_cat_name( $category ) ?: ( '#' . $category ) ) : 'All';
				echo "<!-- Live Deck summary: hero=" . esc_html( $layout ) . " category=" . esc_html( $cat_name ) . " slides=" . (int) $count . " autoplay=" . ( $autoplay ? 'on' : 'off' ) . " ticker=" . ( $ticker ? 'on' : 'off' ) . " -->\n";
				// Visible badge intentionally suppressed (v1.10.8) — admin info now in HTML comments only.
				if ( gridindex_get_option( 'debug_mode', 0 ) ) {
					echo '<div class="gi-admin-badge" style="margin:8px 0;padding:6px 10px;background:#0b1418;border:1px solid #134e4a;color:#9be7d6;font:12px ui-monospace,Menlo,monospace;border-radius:6px;display:inline-block;">';
					echo 'Hero: <strong>' . esc_html( $layout ) . '</strong> | Category: <strong>' . esc_html( $cat_name ) . '</strong> | Slides: <strong>' . (int) $count . '</strong> | Autoplay: <strong>' . ( $autoplay ? 'on' : 'off' ) . '</strong> | Ticker: <strong>' . ( $ticker ? 'on' : 'off' ) . '</strong>';
					echo '</div>' . "\n";
				}
			}

			if ( function_exists( 'gip_render_live_deck' ) ) {
				gip_render_live_deck( array(
					'count'         => $count,
					'category'      => $category,
					'autoplay'      => $autoplay,
					'rotation'      => $rotation,
					'show_momentum' => $show_mom,
					'show_count'    => $show_cnt,
					'layout'        => $layout,
				) );
			}
			break;

		case 'breaking_strip':
			$q = gip_lb_query( $s, max( 4, (int) $s['count'] ) );
			if ( ! $q->have_posts() ) return;
			echo '<div class="gi-ticker" role="region" aria-label="Breaking">';
			echo '<span class="gi-ticker__label">Breaking</span><div class="gi-ticker__track"><div class="gi-ticker__items">';
			while ( $q->have_posts() ) { $q->the_post(); echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>'; }
			$q->rewind_posts();
			while ( $q->have_posts() ) { $q->the_post(); echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>'; }
			echo '</div></div></div>';
			wp_reset_postdata();
			break;

		case 'accelerating_stories':
		case 'top_stories_grid':
		case 'editor_picks':
		case 'most_discussed':
			gip_lb_card_grid( $s, $type );
			break;

		case 'latest_feed':
			gip_lb_latest_list( $s );
			break;

		case 'topic_dashboard':
			gip_lb_topic_dashboard( $s );
			break;

		case 'source_intel_rail':
			gip_lb_rail_signals( $s );
			break;

		case 'trending_entities':
			gip_lb_rail_entities( $s );
			break;

		case 'newsletter_cta':
			gip_lb_newsletter( $s );
			break;

		case 'video_rail':
		case 'ai_summary_rail':
		case 'market_data':
		case 'sponsored':
			gip_lb_placeholder( $s, $type );
			break;
	}
}
endif;

/* ============================================================
 * Section renderers
 * ============================================================ */

if ( ! function_exists( 'gip_lb_query' ) ) :
function gip_lb_query( $s, $count = null ) {
	$args = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count ?: max( 1, (int) $s['count'] ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	if ( ! empty( $s['category'] ) ) $args['cat'] = (int) $s['category'];
	if ( function_exists( 'gridindex_should_exclude_uncategorized' )
	     && gridindex_should_exclude_uncategorized()
	     && function_exists( 'gridindex_get_uncategorized_ids' ) ) {
		$args['category__not_in'] = gridindex_get_uncategorized_ids();
	}
	if ( 'editor_picks' === ( $s['type'] ?? '' ) ) {
		$args['post__in'] = get_option( 'sticky_posts', array() );
		if ( empty( $args['post__in'] ) ) unset( $args['post__in'] );
	}
	if ( 'most_discussed' === ( $s['type'] ?? '' ) ) {
		$args['orderby'] = 'comment_count';
		$args['order']   = 'DESC';
	}
	return new WP_Query( $args );
}
endif;

if ( ! function_exists( 'gip_lb_section_header' ) ) :
function gip_lb_section_header( $title, $sub = '' ) {
	echo '<header class="gi-section__head"><h2 class="gi-section__title">' . esc_html( $title ) . '</h2>';
	if ( $sub ) echo '<span class="gi-mono">' . esc_html( $sub ) . '</span>';
	echo '</header>';
}
endif;

if ( ! function_exists( 'gip_lb_card_grid' ) ) :
function gip_lb_card_grid( $s, $type ) {
	$titles = array(
		'accelerating_stories' => array( 'Accelerating Stories', 'Momentum across sources' ),
		'top_stories_grid'     => array( 'Top Stories',          'Updated continuously' ),
		'editor_picks'         => array( 'Editor Picks',         'Curated by Grid Index' ),
		'most_discussed'       => array( 'Most Discussed',       'High engagement' ),
	);
	$h = $titles[ $type ] ?? array( ucfirst( $type ), '' );
	$q = gip_lb_query( $s );
	if ( ! $q->have_posts() ) return;
	echo '<section class="gi-section">';
	gip_lb_section_header( $h[0], $h[1] );
	echo '<div class="gi-secondary">';
	while ( $q->have_posts() ) {
		$q->the_post();
		echo '<article class="gi-card">';
		if ( function_exists( 'gip_card_thumb' ) ) gip_card_thumb();
		echo '<div class="gi-card__body"><div class="gi-card__meta">';
		if ( function_exists( 'gip_render_signal_badge' ) ) gip_render_signal_badge( get_the_ID() );
		$cc = get_the_category(); if ( ! empty( $cc ) ) echo '<span class="gi-kicker">' . esc_html( $cc[0]->name ) . '</span>';
		echo '</div><h3 class="gi-card__title">';
		if ( function_exists( 'gip_card_title_link' ) ) gip_card_title_link(); else echo '<a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a>';
		echo '</h3><div class="gi-card__foot">';
		if ( function_exists( 'gip_render_card_meta_line' ) ) gip_render_card_meta_line();
		if ( function_exists( 'gip_render_source_button' ) ) gip_render_source_button();
		echo '</div></div></article>';
	}
	echo '</div></section>';
	wp_reset_postdata();
}
endif;

if ( ! function_exists( 'gip_lb_latest_list' ) ) :
function gip_lb_latest_list( $s ) {
	$q = gip_lb_query( $s );
	if ( ! $q->have_posts() ) return;
	echo '<section class="gi-section">';
	gip_lb_section_header( 'Latest', 'Live feed' );
	echo '<ol class="gi-latest__list">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$href = function_exists( 'gip_resolve_card_link' ) ? gip_resolve_card_link( get_the_ID() ) : get_permalink();
		echo '<li class="gi-latest__row">';
		echo '<a class="gi-latest__thumb" href="' . esc_url( $href ) . '" tabindex="-1" aria-label="' . esc_attr( get_the_title() ) . '">';
		if ( has_post_thumbnail() ) the_post_thumbnail( 'gip-thumb', array( 'loading' => 'lazy' ) );
		echo '</a><div><div class="gi-latest__chips">';
		if ( function_exists( 'gip_render_signal_badge' ) ) gip_render_signal_badge( get_the_ID() );
		$cc = get_the_category(); if ( ! empty( $cc ) ) echo '<span class="gi-kicker">' . esc_html( $cc[0]->name ) . '</span>';
		echo '</div><a class="gi-latest__title" href="' . esc_url( $href ) . '">' . esc_html( get_the_title() ) . '</a></div>';
		echo '<span class="gi-latest__time">' . esc_html( human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) ) . '</span></li>';
	}
	echo '</ol></section>';
	wp_reset_postdata();
}
endif;

if ( ! function_exists( 'gip_lb_topic_dashboard' ) ) :
function gip_lb_topic_dashboard( $s ) {
	$slugs = array( 'ai', 'tech', 'startups', 'cybersecurity' );
	$cats  = array();
	foreach ( $slugs as $slug ) {
		$t = get_category_by_slug( $slug );
		if ( $t && $t->count > 0 ) $cats[] = $t;
	}
	if ( empty( $cats ) ) return;
	echo '<section class="gi-section gi-topics">';
	gip_lb_section_header( 'Topic Dashboard', 'Desks across Grid Index' );
	echo '<div class="gi-topics__grid">';
	foreach ( $cats as $c ) {
		$tq = new WP_Query( array( 'cat' => $c->term_id, 'posts_per_page' => max( 2, (int) $s['count'] ), 'no_found_rows' => true ) );
		echo '<div class="gi-topics__col"><div class="gi-topics__head"><a href="' . esc_url( get_term_link( $c ) ) . '"><strong>' . esc_html( $c->name ) . '</strong></a><span class="gi-mono">' . (int) $c->count . '</span></div><ul>';
		while ( $tq->have_posts() ) { $tq->the_post(); echo '<li><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></li>'; }
		echo '</ul></div>';
		wp_reset_postdata();
	}
	echo '</div></section>';
}
endif;

if ( ! function_exists( 'gip_lb_rail_signals' ) ) :
function gip_lb_rail_signals( $s ) {
	$q = gip_lb_query( $s );
	if ( ! $q->have_posts() ) return;
	echo '<section class="gi-rail__block"><h2 class="gi-rail__title">Source Intelligence</h2><ol class="gi-rail__list">';
	$i = 1;
	while ( $q->have_posts() ) {
		$q->the_post();
		echo '<li><span class="gi-rail__num">' . esc_html( str_pad( $i++, 2, '0', STR_PAD_LEFT ) ) . '</span><div><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a><div class="gi-rail__sub">' . esc_html( human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) ) . ' ago</div></div></li>';
	}
	echo '</ol></section>';
	wp_reset_postdata();
}
endif;

if ( ! function_exists( 'gip_lb_rail_entities' ) ) :
function gip_lb_rail_entities( $s ) {
	$tags = get_tags( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => max( 4, (int) $s['count'] ) ) );
	if ( empty( $tags ) ) return;
	echo '<section class="gi-rail__block"><h2 class="gi-rail__title">Trending Entities</h2><ul class="gi-rail__tags">';
	foreach ( $tags as $t ) echo '<li><a href="' . esc_url( get_term_link( $t ) ) . '">#' . esc_html( $t->name ) . '</a><span>' . (int) $t->count . '</span></li>';
	echo '</ul></section>';
}
endif;

if ( ! function_exists( 'gip_lb_newsletter' ) ) :
function gip_lb_newsletter( $s ) {
	echo '<section class="gi-rail__block gi-rail__cta"><h2 class="gi-rail__title">Daily Intelligence</h2>';
	echo '<p>The signal cut from today&rsquo;s sources. One brief, every morning.</p>';
	echo '<form class="gi-rail__form" method="post" action="#"><input type="email" required placeholder="you@domain.com" /><button type="submit">Subscribe →</button></form>';
	echo '</section>';
}
endif;

if ( ! function_exists( 'gip_lb_placeholder' ) ) :
function gip_lb_placeholder( $s, $type ) {
	$types = gip_lb_section_types();
	$label = $types[ $type ]['label'] ?? $type;
	echo '<section class="gi-section gi-placeholder"><header class="gi-section__head"><h2 class="gi-section__title">' . esc_html( $label ) . '</h2><span class="gi-mono">module</span></header><div class="gi-placeholder__body">Configure data source for ' . esc_html( $label ) . ' in a future update.</div></section>';
}
endif;

/* ============================================================
 * Disable generic widget output on homepage areas
 * ============================================================ */

add_action( 'widgets_init', function() {
	// Nothing destructive — sidebars still register for fallback. We only
	// suppress default core widget visual on the homepage via CSS class.
}, 99 );
