<?php
/**
 * The Grid Index — Featured Image Slider
 *
 * Editor-friendly hero slider with a clear admin setup screen.
 *
 * Provides:
 *   1. "Featured Slider" category auto-created on activation.
 *   2. Admin: Appearance → — Slider — pick source, autoplay, speed, transition.
 *   3. "Add to Slider / Remove from Slider" row action on Posts.
 *   4. Manual "Add custom slide" form (title + image URL + link + optional kicker).
 *   5. gip_render_featured_slider() — semantic HTML, no JS framework needed.
 *   6. assets/slider/slider.js + slider.css with autoplay (slow/medium/fast),
 *      pause on hover, swipe on touch, prev/next, dots, keyboard arrows.
 *
 * Wire from functions.php:
 *   require_once get_template_directory() . '/inc/featured-slider.php';
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---------------- Activation: create the source category ---------------- */

add_action( 'after_switch_theme', 'gip_slider_install_terms' );
add_action( 'admin_init',         'gip_slider_install_terms' );
function gip_slider_install_terms() {
	if ( get_option( 'gip_slider_terms_installed' ) === '1' ) { return; }
	if ( ! term_exists( 'featured-slider', 'category' ) ) {
		wp_insert_term( 'Featured Slider', 'category', array(
			'slug'        => 'featured-slider',
			'description' => 'Posts in this category appear in the homepage hero slider.',
		) );
	}
	update_option( 'gip_slider_terms_installed', '1' );
}

/* ---------------- Settings ---------------- */

function gip_slider_get_settings() {
	$defaults = array(
		'enabled'        => 1,
		'source'         => 'featured', // featured | manual | mixed
		'limit'          => 6,
		'autoplay'       => 1,
		'speed'          => 'medium',   // slow | medium | fast
		'transition'     => 'slide',    // slide | fade
		'show_kicker'    => 1,
		'show_dots'      => 1,
		'show_arrows'    => 1,
	);
	return wp_parse_args( get_option( 'gip_slider_settings', array() ), $defaults );
}
function gip_slider_save_settings( $new ) {
	update_option( 'gip_slider_settings', $new, false );
	delete_transient( 'gip_slider_items' );
}

function gip_slider_speed_ms( $speed ) {
	return array( 'slow' => 8000, 'medium' => 5000, 'fast' => 3000 )[ $speed ] ?? 5000;
}

/* ---------------- Manual slides ---------------- */

function gip_slider_get_manual()      { $m = get_option( 'gip_slider_manual', array() ); return is_array( $m ) ? $m : array(); }
function gip_slider_set_manual( $m )  { update_option( 'gip_slider_manual', array_values( $m ), false ); delete_transient( 'gip_slider_items' ); }

/* ---------------- Posts category membership helpers ---------------- */

function gip_slider_post_in( $post_id ) {
	return has_category( 'featured-slider', $post_id );
}
function gip_slider_add_post( $post_id ) {
	$term = get_term_by( 'slug', 'featured-slider', 'category' );
	if ( $term ) { wp_set_post_categories( $post_id, array( $term->term_id ), true ); }
	delete_transient( 'gip_slider_items' );
}
function gip_slider_remove_post( $post_id ) {
	$term = get_term_by( 'slug', 'featured-slider', 'category' );
	if ( ! $term ) { return; }
	$cats = wp_get_post_categories( $post_id );
	$cats = array_values( array_diff( $cats, array( $term->term_id ) ) );
	wp_set_post_categories( $post_id, $cats );
	delete_transient( 'gip_slider_items' );
}

/* ---------------- Data source ---------------- */

function gip_slider_get_items() {
	$cached = get_transient( 'gip_slider_items' );
	if ( is_array( $cached ) ) { return $cached; }

	$s     = gip_slider_get_settings();
	$items = array();

	if ( in_array( $s['source'], array( 'manual', 'mixed' ), true ) ) {
		foreach ( gip_slider_get_manual() as $m ) {
			if ( empty( $m['title'] ) || empty( $m['image'] ) ) { continue; }
			$items[] = array(
				'title'  => $m['title'],
				'kicker' => $m['kicker'] ?? '',
				'image'  => $m['image'],
				'url'    => $m['url'] ?? '#',
				'source' => $m['source_label'] ?? '',
			);
		}
	}

	if ( in_array( $s['source'], array( 'featured', 'mixed' ), true ) ) {
		$posts = get_posts( array(
			'category_name'    => 'featured-slider',
			'posts_per_page'   => (int) $s['limit'],
			'suppress_filters' => true,
		) );
		foreach ( $posts as $p ) {
			$img = get_the_post_thumbnail_url( $p, 'large' );
			if ( ! $img && function_exists( 'gridindex_get_fallback_image' ) ) {
				$cats = wp_get_post_categories( $p->ID, array( 'fields' => 'slugs' ) );
				$img  = gridindex_get_fallback_image( $cats[0] ?? 'world' );
			}
			$cats = wp_get_post_categories( $p->ID, array( 'fields' => 'names' ) );
			$items[] = array(
				'title'  => get_the_title( $p ),
				'kicker' => $cats[0] ?? '',
				'image'  => $img,
				'url'    => get_permalink( $p ),
				'source' => '',
			);
		}
	}

	$items = array_slice( $items, 0, (int) $s['limit'] );
	$items = apply_filters( 'gip_slider_items', $items );
	set_transient( 'gip_slider_items', $items, 60 );
	return $items;
}

/* ---------------- Front-end render ---------------- */

function gip_render_featured_slider() {
	$s     = gip_slider_get_settings();
	if ( empty( $s['enabled'] ) ) { return; }
	$items = gip_slider_get_items();
	if ( empty( $items ) ) { return; }

	wp_enqueue_style(  'gip-slider', get_template_directory_uri() . '/assets/slider/slider.css', array(), GIP_VERSION ?? '1.10.15' );
	wp_enqueue_script( 'gip-slider', get_template_directory_uri() . '/assets/slider/slider.js',  array(), GIP_VERSION ?? '1.10.15', true );

	$attrs = array(
		'data-autoplay'   => $s['autoplay'] ? '1' : '0',
		'data-interval'   => gip_slider_speed_ms( $s['speed'] ),
		'data-transition' => $s['transition'],
		'data-arrows'     => $s['show_arrows'] ? '1' : '0',
		'data-dots'       => $s['show_dots'] ? '1' : '0',
	);
	?>
	<section class="gi-slider" aria-roledescription="carousel" aria-label="Featured stories"<?php
		foreach ( $attrs as $k => $v ) { echo ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"'; }
	?>>
		<ul class="gi-slider__track">
			<?php foreach ( $items as $i => $item ) : ?>
				<li class="gi-slider__slide<?php echo $i === 0 ? ' is-active' : ''; ?>" aria-roledescription="slide" aria-label="<?php printf( '%d of %d', $i + 1, count( $items ) ); ?>">
					<a class="gi-slider__link" href="<?php echo esc_url( $item['url'] ); ?>">
						<img class="gi-slider__img" src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['title'] ); ?>" loading="<?php echo $i === 0 ? 'eager' : 'lazy'; ?>" decoding="async">
						<span class="gi-slider__overlay" aria-hidden="true"></span>
						<div class="gi-slider__caption">
							<?php if ( ! empty( $s['show_kicker'] ) && ! empty( $item['kicker'] ) ) : ?>
								<span class="gi-slider__kicker"><?php echo esc_html( $item['kicker'] ); ?></span>
							<?php endif; ?>
							<h2 class="gi-slider__title"><?php echo esc_html( $item['title'] ); ?></h2>
						</div>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( ! empty( $s['show_arrows'] ) ) : ?>
			<button class="gi-slider__btn gi-slider__btn--prev" type="button" aria-label="Previous slide">‹</button>
			<button class="gi-slider__btn gi-slider__btn--next" type="button" aria-label="Next slide">›</button>
		<?php endif; ?>

		<?php if ( ! empty( $s['show_dots'] ) ) : ?>
			<ol class="gi-slider__dots" role="tablist">
				<?php foreach ( $items as $i => $_ ) : ?>
					<li><button type="button" role="tab" aria-label="Go to slide <?php echo $i + 1; ?>"<?php echo $i === 0 ? ' aria-selected="true"' : ''; ?>></button></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</section>
	<?php
}

/* ---------------- Posts list row action ---------------- */

add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( $post->post_type !== 'post' || ! current_user_can( 'edit_post', $post->ID ) ) { return $actions; }
	$in    = gip_slider_post_in( $post->ID );
	$op    = $in ? 'remove' : 'add';
	$label = $in ? __( 'Remove from Slider', 'the-grid-index' ) : __( 'Add to Slider', 'the-grid-index' );
	$url   = wp_nonce_url(
		admin_url( 'admin-post.php?action=gip_slider_toggle&post=' . $post->ID . '&op=' . $op ),
		'gip_slider_toggle_' . $post->ID
	);
	$actions['gip_slider'] = sprintf( '<a href="%s" style="color:%s">%s</a>', esc_url( $url ), $in ? '#b91c1c' : '#1d4ed8', esc_html( $label ) );
	return $actions;
}, 10, 2 );

add_action( 'admin_post_gip_slider_toggle', function () {
	$post_id = (int) ( $_GET['post'] ?? 0 );
	$op      = sanitize_key( $_GET['op'] ?? '' );
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'gip_slider_toggle_' . $post_id );
	if ( $op === 'add' )    { gip_slider_add_post( $post_id ); }
	if ( $op === 'remove' ) { gip_slider_remove_post( $post_id ); }
	wp_safe_redirect( wp_get_referer() ?: admin_url( 'edit.php' ) );
	exit;
} );

/* ---------------- Admin page ---------------- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'themes.php',
		__( 'Grid Index — Slider', 'the-grid-index' ),
		__( '— Slider', 'the-grid-index' ),
		'manage_options',
		'gip-slider',
		'gip_slider_render_admin_page'
	);
}, 61 );

function gip_slider_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	if ( isset( $_POST['gip_slider_save'] ) && check_admin_referer( 'gip_slider_admin' ) ) {
		gip_slider_save_settings( array(
			'enabled'     => ! empty( $_POST['enabled'] ) ? 1 : 0,
			'source'      => in_array( $_POST['source'] ?? '', array( 'featured', 'manual', 'mixed' ), true ) ? $_POST['source'] : 'featured',
			'limit'       => max( 1, min( 12, (int) ( $_POST['limit'] ?? 6 ) ) ),
			'autoplay'    => ! empty( $_POST['autoplay'] ) ? 1 : 0,
			'speed'       => in_array( $_POST['speed'] ?? '', array( 'slow', 'medium', 'fast' ), true ) ? $_POST['speed'] : 'medium',
			'transition'  => in_array( $_POST['transition'] ?? '', array( 'slide', 'fade' ), true ) ? $_POST['transition'] : 'slide',
			'show_kicker' => ! empty( $_POST['show_kicker'] ) ? 1 : 0,
			'show_dots'   => ! empty( $_POST['show_dots'] ) ? 1 : 0,
			'show_arrows' => ! empty( $_POST['show_arrows'] ) ? 1 : 0,
		) );
		echo '<div class="notice notice-success is-dismissible"><p>Slider settings saved.</p></div>';
	}
	if ( isset( $_POST['gip_slider_add_manual'] ) && check_admin_referer( 'gip_slider_admin' ) ) {
		$m   = gip_slider_get_manual();
		$m[] = array(
			'title'        => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'kicker'       => sanitize_text_field( wp_unslash( $_POST['kicker'] ?? '' ) ),
			'image'        => esc_url_raw( wp_unslash( $_POST['image'] ?? '' ) ),
			'url'          => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'source_label' => sanitize_text_field( wp_unslash( $_POST['source_label'] ?? '' ) ),
		);
		gip_slider_set_manual( $m );
		echo '<div class="notice notice-success is-dismissible"><p>Slide added.</p></div>';
	}
	if ( isset( $_POST['gip_slider_remove_manual'] ) && check_admin_referer( 'gip_slider_admin' ) ) {
		$m = gip_slider_get_manual();
		unset( $m[ (int) $_POST['gip_slider_remove_manual'] ] );
		gip_slider_set_manual( $m );
		echo '<div class="notice notice-success is-dismissible"><p>Slide removed.</p></div>';
	}
	if ( isset( $_POST['gip_slider_flush'] ) && check_admin_referer( 'gip_slider_admin' ) ) {
		delete_transient( 'gip_slider_items' );
		echo '<div class="notice notice-success is-dismissible"><p>Slider cache flushed.</p></div>';
	}

	$s        = gip_slider_get_settings();
	$manual   = gip_slider_get_manual();
	$preview  = gip_slider_get_items();
	$cat_term = get_term_by( 'slug', 'featured-slider', 'category' );
	?>
	<div class="wrap gi-slider-admin">
		<h1><?php esc_html_e( 'Homepage Featured Slider', 'the-grid-index' ); ?></h1>
		<p class="description" style="font-size:14px;max-width:820px">
			<?php esc_html_e( 'The slider sits at the top of the homepage. Slides come from one of three sources, autoplay automatically, and pause when the visitor hovers. Configure everything below.', 'the-grid-index' ); ?>
		</p>

		<!-- HOW IT WORKS -->
		<div class="card" style="padding:18px;margin:18px 0;background:#fff;border:1px solid #dcdcde;border-radius:6px;max-width:980px">
			<h2 style="margin-top:0;font-size:15px;letter-spacing:.04em;text-transform:uppercase;color:#475569">
				<?php esc_html_e( 'How to add slides — 3 ways', 'the-grid-index' ); ?>
			</h2>
			<ol style="font-size:13.5px;line-height:1.55;padding-left:18px;margin:0">
				<li style="margin-bottom:10px">
					<strong><?php esc_html_e( '1. Tick "Featured Slider" on a post', 'the-grid-index' ); ?></strong> —
					<?php esc_html_e( 'in the post editor under Categories. Uses the post\'s featured image (falls back to the topic-branded image if missing).', 'the-grid-index' ); ?>
					<?php if ( $cat_term ) : ?> <a href="<?php echo esc_url( admin_url( 'edit.php?category_name=featured-slider' ) ); ?>"><?php esc_html_e( 'View posts in slider', 'the-grid-index' ); ?></a><?php endif; ?>
				</li>
				<li style="margin-bottom:10px">
					<strong><?php esc_html_e( '2. Use the row action', 'the-grid-index' ); ?></strong> —
					<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'Posts → All Posts', 'the-grid-index' ); ?></a>
					<?php esc_html_e( '→ hover a row → "Add to Slider".', 'the-grid-index' ); ?>
				</li>
				<li>
					<strong><?php esc_html_e( '3. Add a custom slide', 'the-grid-index' ); ?></strong> —
					<?php esc_html_e( 'use the form below for external links, ads, or one-off promos.', 'the-grid-index' ); ?>
				</li>
			</ol>
		</div>

		<form method="post" style="max-width:980px">
			<?php wp_nonce_field( 'gip_slider_admin' ); ?>

			<!-- SETTINGS -->
			<div class="card" style="padding:18px;background:#fff;border:1px solid #dcdcde;border-radius:6px">
				<h2 style="margin-top:0;font-size:15px;letter-spacing:.04em;text-transform:uppercase;color:#475569"><?php esc_html_e( 'Slider settings', 'the-grid-index' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Enabled', 'the-grid-index' ); ?></th>
						<td><label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'] ); ?>> <?php esc_html_e( 'Show the slider on the homepage', 'the-grid-index' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="source"><?php esc_html_e( 'Slide source', 'the-grid-index' ); ?></label></th>
						<td>
							<select name="source" id="source">
								<option value="featured" <?php selected( $s['source'], 'featured' ); ?>><?php esc_html_e( 'Posts in "Featured Slider" category', 'the-grid-index' ); ?></option>
								<option value="manual"   <?php selected( $s['source'], 'manual' ); ?>><?php esc_html_e( 'Custom slides only', 'the-grid-index' ); ?></option>
								<option value="mixed"    <?php selected( $s['source'], 'mixed' ); ?>><?php esc_html_e( 'Custom slides + posts (custom first)', 'the-grid-index' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="limit"><?php esc_html_e( 'Max slides', 'the-grid-index' ); ?></label></th>
						<td><input type="number" min="1" max="12" name="limit" id="limit" value="<?php echo (int) $s['limit']; ?>" class="small-text"></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Autoplay', 'the-grid-index' ); ?></th>
						<td><label><input type="checkbox" name="autoplay" value="1" <?php checked( $s['autoplay'] ); ?>> <?php esc_html_e( 'Advance slides automatically (pauses on hover & when tab is hidden)', 'the-grid-index' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="speed"><?php esc_html_e( 'Autoplay speed', 'the-grid-index' ); ?></label></th>
						<td>
							<select name="speed" id="speed">
								<option value="slow"   <?php selected( $s['speed'], 'slow' ); ?>><?php esc_html_e( 'Slow (8 s per slide)', 'the-grid-index' ); ?></option>
								<option value="medium" <?php selected( $s['speed'], 'medium' ); ?>><?php esc_html_e( 'Medium (5 s per slide) — recommended', 'the-grid-index' ); ?></option>
								<option value="fast"   <?php selected( $s['speed'], 'fast' ); ?>><?php esc_html_e( 'Fast (3 s per slide)', 'the-grid-index' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="transition"><?php esc_html_e( 'Transition', 'the-grid-index' ); ?></label></th>
						<td>
							<select name="transition" id="transition">
								<option value="slide" <?php selected( $s['transition'], 'slide' ); ?>><?php esc_html_e( 'Slide (horizontal)', 'the-grid-index' ); ?></option>
								<option value="fade"  <?php selected( $s['transition'], 'fade' ); ?>><?php esc_html_e( 'Fade', 'the-grid-index' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Controls', 'the-grid-index' ); ?></th>
						<td>
							<label style="margin-right:18px"><input type="checkbox" name="show_arrows" value="1" <?php checked( $s['show_arrows'] ); ?>> <?php esc_html_e( 'Prev / next arrows', 'the-grid-index' ); ?></label>
							<label style="margin-right:18px"><input type="checkbox" name="show_dots"   value="1" <?php checked( $s['show_dots'] ); ?>>   <?php esc_html_e( 'Pagination dots', 'the-grid-index' ); ?></label>
							<label><input type="checkbox" name="show_kicker" value="1" <?php checked( $s['show_kicker'] ); ?>> <?php esc_html_e( 'Show kicker / category', 'the-grid-index' ); ?></label>
						</td>
					</tr>
				</table>
				<p>
					<button type="submit" name="gip_slider_save" value="1" class="button button-primary"><?php esc_html_e( 'Save settings', 'the-grid-index' ); ?></button>
					<button type="submit" name="gip_slider_flush" value="1" class="button"><?php esc_html_e( 'Flush slider cache', 'the-grid-index' ); ?></button>
				</p>
			</div>
		</form>

		<!-- CUSTOM SLIDE QUICK ADD -->
		<form method="post" style="max-width:980px;margin-top:18px">
			<?php wp_nonce_field( 'gip_slider_admin' ); ?>
			<div class="card" style="padding:18px;background:#fff;border:1px solid #dcdcde;border-radius:6px">
				<h2 style="margin-top:0;font-size:15px;letter-spacing:.04em;text-transform:uppercase;color:#475569"><?php esc_html_e( 'Quick add: custom slide', 'the-grid-index' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr><th><label><?php esc_html_e( 'Headline', 'the-grid-index' ); ?> *</label></th><td><input type="text" name="title" required class="regular-text" style="width:100%"></td></tr>
					<tr><th><label><?php esc_html_e( 'Image URL', 'the-grid-index' ); ?> *</label></th><td><input type="url" name="image" required class="regular-text" style="width:100%" placeholder="https://…/image.jpg"></td></tr>
					<tr><th><label><?php esc_html_e( 'Link URL', 'the-grid-index' ); ?> *</label></th><td><input type="url" name="url" required class="regular-text" style="width:100%"></td></tr>
					<tr><th><label><?php esc_html_e( 'Kicker / category', 'the-grid-index' ); ?></label></th><td><input type="text" name="kicker" class="regular-text" placeholder="AI"></td></tr>
					<tr><th><label><?php esc_html_e( 'Source label', 'the-grid-index' ); ?></label></th><td><input type="text" name="source_label" class="regular-text" placeholder="Reuters"></td></tr>
				</table>
				<p><button type="submit" name="gip_slider_add_manual" value="1" class="button button-primary"><?php esc_html_e( 'Add slide', 'the-grid-index' ); ?></button></p>
			</div>
		</form>

		<!-- CUSTOM SLIDES LIST -->
		<h2 style="margin-top:30px"><?php esc_html_e( 'Custom slides', 'the-grid-index' ); ?></h2>
		<?php if ( empty( $manual ) ) : ?>
			<p><em><?php esc_html_e( 'No custom slides yet.', 'the-grid-index' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:980px">
				<thead><tr><th><?php esc_html_e( 'Image', 'the-grid-index' ); ?></th><th><?php esc_html_e( 'Headline', 'the-grid-index' ); ?></th><th><?php esc_html_e( 'Link', 'the-grid-index' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $manual as $idx => $m ) : ?>
					<tr>
						<td><img src="<?php echo esc_url( $m['image'] ); ?>" alt="" style="width:80px;height:45px;object-fit:cover;border-radius:2px"></td>
						<td><strong><?php echo esc_html( $m['title'] ); ?></strong><?php if ( ! empty( $m['kicker'] ) ) : ?><br><span style="color:#64748b;font-size:12px"><?php echo esc_html( $m['kicker'] ); ?></span><?php endif; ?></td>
						<td><a href="<?php echo esc_url( $m['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $m['url'], PHP_URL_HOST ) ); ?></a></td>
						<td><form method="post" style="margin:0"><?php wp_nonce_field( 'gip_slider_admin' ); ?><button type="submit" name="gip_slider_remove_manual" value="<?php echo esc_attr( $idx ); ?>" class="button-link-delete"><?php esc_html_e( 'Remove', 'the-grid-index' ); ?></button></form></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<!-- LIVE PREVIEW LIST -->
		<h2 style="margin-top:30px"><?php esc_html_e( 'Live preview (slide order)', 'the-grid-index' ); ?></h2>
		<?php if ( empty( $preview ) ) : ?>
			<p><em><?php esc_html_e( 'No slides resolved. Add a custom slide or tick "Featured Slider" on a post.', 'the-grid-index' ); ?></em></p>
		<?php else : ?>
			<ol style="font-size:13.5px;line-height:1.6">
				<?php foreach ( $preview as $i ) : ?>
					<li><strong><?php echo esc_html( $i['title'] ); ?></strong><?php if ( ! empty( $i['kicker'] ) ) : ?> — <span style="color:#64748b"><?php echo esc_html( $i['kicker'] ); ?></span><?php endif; ?></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</div>
	<?php
}
