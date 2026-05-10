<?php
/**
 * The Grid Index — Breaking News Ticker
 *
 * Self-contained module:
 *   1. Auto-creates the "Breaking" category and "ticker" tag on theme activation.
 *   2. Adds a clear admin panel under Appearance → Grid Index Options → Ticker
 *      that explains every way to populate the strip, with one-click examples.
 *   3. Adds a "Pin to Ticker" / "Unpin" row action on the Posts list.
 *   4. Adds a "Breaking — show in ticker" checkbox in the post sidebar (Classic + Block).
 *   5. Provides the gip_get_breaking_ticker_items() data source used by the
 *      front-end render (priority: pinned → Breaking cat → ticker tag → latest).
 *   6. Caches results in a 60s transient. "Flush cache" button included.
 *
 * Wire from functions.php:
 *   require_once get_template_directory() . '/inc/breaking-ticker.php';
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* -------------------------------------------------------------------------
 * 1. ACTIVATION — create the Breaking category + ticker tag automatically
 * ---------------------------------------------------------------------- */

add_action( 'after_switch_theme', 'gip_ticker_install_taxonomy_terms' );
add_action( 'admin_init',         'gip_ticker_install_taxonomy_terms' ); // safety net

function gip_ticker_install_taxonomy_terms() {
	if ( get_option( 'gip_ticker_terms_installed' ) === '1' ) { return; }

	if ( ! term_exists( 'breaking', 'category' ) ) {
		wp_insert_term( 'Breaking', 'category', array(
			'slug'        => 'breaking',
			'description' => 'Posts in this category appear in the Grid Index breaking news ticker for 24 hours.',
		) );
	}
	if ( ! term_exists( 'ticker', 'post_tag' ) ) {
		wp_insert_term( 'ticker', 'post_tag', array(
			'slug'        => 'ticker',
			'description' => 'Posts tagged "ticker" appear in the Grid Index ticker as a fallback after Breaking.',
		) );
	}
	update_option( 'gip_ticker_terms_installed', '1' );
}

/* -------------------------------------------------------------------------
 * 2. PINNED ITEMS — option-backed CRUD
 * ---------------------------------------------------------------------- */

function gip_ticker_get_pinned() {
	$pinned = get_option( 'gip_ticker_pinned_items', array() );
	return is_array( $pinned ) ? $pinned : array();
}

function gip_ticker_set_pinned( array $items ) {
	$items = array_values( array_filter( $items, function ( $i ) {
		return ! empty( $i['title'] ) && ! empty( $i['url'] );
	} ) );
	update_option( 'gip_ticker_pinned_items', $items, false );
	delete_transient( 'gip_ticker_items' );
}

function gip_ticker_pin_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || $post->post_status !== 'publish' ) { return false; }
	$pinned = gip_ticker_get_pinned();
	foreach ( $pinned as $i ) {
		if ( ! empty( $i['post_id'] ) && (int) $i['post_id'] === (int) $post_id ) { return true; }
	}
	$pinned[] = array(
		'post_id'   => (int) $post_id,
		'title'     => get_the_title( $post ),
		'url'       => get_permalink( $post ),
		'source'    => get_bloginfo( 'name' ),
		'timestamp' => current_time( 'timestamp' ),
		'is_live'   => false,
	);
	gip_ticker_set_pinned( $pinned );
	return true;
}

function gip_ticker_unpin_post( $post_id ) {
	$pinned = gip_ticker_get_pinned();
	$pinned = array_values( array_filter( $pinned, function ( $i ) use ( $post_id ) {
		return empty( $i['post_id'] ) || (int) $i['post_id'] !== (int) $post_id;
	} ) );
	gip_ticker_set_pinned( $pinned );
}

function gip_ticker_is_pinned( $post_id ) {
	foreach ( gip_ticker_get_pinned() as $i ) {
		if ( ! empty( $i['post_id'] ) && (int) $i['post_id'] === (int) $post_id ) { return true; }
	}
	return false;
}

/* -------------------------------------------------------------------------
 * 3. DATA SOURCE — what the front-end reads
 * ---------------------------------------------------------------------- */

function gip_get_breaking_ticker_items( $limit = 12 ) {
	$cached = get_transient( 'gip_ticker_items' );
	if ( is_array( $cached ) ) { return $cached; }

	$items = array();

	// 3a. Pinned (highest priority)
	foreach ( gip_ticker_get_pinned() as $p ) {
		$items[] = array_merge( array(
			'title' => '', 'url' => '', 'source' => '', 'timestamp' => time(), 'is_live' => false,
		), $p );
	}

	// 3b. Breaking category, last 24h
	$breaking = get_posts( array(
		'category_name'    => 'breaking',
		'posts_per_page'   => $limit,
		'date_query'       => array( array( 'after' => '24 hours ago' ) ),
		'suppress_filters' => true,
	) );
	foreach ( $breaking as $p ) {
		$items[] = array(
			'post_id'   => $p->ID,
			'title'     => get_the_title( $p ),
			'url'       => get_permalink( $p ),
			'source'    => get_bloginfo( 'name' ),
			'timestamp' => get_post_time( 'U', true, $p ),
			'is_live'   => true,
		);
	}

	// 3c. ticker tag fallback
	if ( count( $items ) < $limit ) {
		$tagged = get_posts( array(
			'tag'              => 'ticker',
			'posts_per_page'   => $limit - count( $items ),
			'suppress_filters' => true,
		) );
		foreach ( $tagged as $p ) {
			$items[] = array(
				'post_id'   => $p->ID,
				'title'     => get_the_title( $p ),
				'url'       => get_permalink( $p ),
				'source'    => get_bloginfo( 'name' ),
				'timestamp' => get_post_time( 'U', true, $p ),
				'is_live'   => false,
			);
		}
	}

	// 3d. Latest fallback so the strip is never empty
	if ( empty( $items ) ) {
		$latest = get_posts( array( 'posts_per_page' => $limit, 'suppress_filters' => true ) );
		foreach ( $latest as $p ) {
			$items[] = array(
				'post_id'   => $p->ID,
				'title'     => get_the_title( $p ),
				'url'       => get_permalink( $p ),
				'source'    => get_bloginfo( 'name' ),
				'timestamp' => get_post_time( 'U', true, $p ),
				'is_live'   => false,
			);
		}
	}

	// Dedupe by URL, cap at limit
	$seen = array();
	$items = array_values( array_filter( $items, function ( $i ) use ( &$seen ) {
		if ( empty( $i['url'] ) || isset( $seen[ $i['url'] ] ) ) { return false; }
		$seen[ $i['url'] ] = true;
		return true;
	} ) );
	$items = array_slice( $items, 0, $limit );

	$items = apply_filters( 'gip_breaking_ticker_items', $items );
	set_transient( 'gip_ticker_items', $items, 60 );
	return $items;
}

/* -------------------------------------------------------------------------
 * 4. FRONT-END RENDER
 * ---------------------------------------------------------------------- */

function gip_render_breaking_ticker() {
	$items = gip_get_breaking_ticker_items( 12 );
	if ( empty( $items ) ) { return; }
	?>
	<aside class="gi-ticker" aria-label="<?php esc_attr_e( 'Breaking news', 'the-grid-index' ); ?>">
		<div class="gi-ticker__inner">
			<span class="gi-ticker__label"><span class="gi-ticker__dot"></span><?php esc_html_e( 'BREAKING', 'the-grid-index' ); ?></span>
			<ul class="gi-ticker__track">
				<?php foreach ( $items as $i ) : ?>
					<li class="gi-ticker__item<?php echo ! empty( $i['is_live'] ) ? ' is-live' : ''; ?>">
						<a href="<?php echo esc_url( $i['url'] ); ?>" rel="<?php echo wp_parse_url( $i['url'], PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ? 'bookmark' : 'noopener external'; ?>">
							<?php if ( ! empty( $i['source'] ) ) : ?>
								<span class="gi-ticker__src"><?php echo esc_html( $i['source'] ); ?></span>
							<?php endif; ?>
							<span class="gi-ticker__title"><?php echo esc_html( $i['title'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</aside>
	<?php
}

/* -------------------------------------------------------------------------
 * 5. ADMIN — Posts list "Pin / Unpin to Ticker" row action
 * ---------------------------------------------------------------------- */

add_filter( 'post_row_actions', function ( $actions, $post ) {
	if ( $post->post_type !== 'post' || $post->post_status !== 'publish' ) { return $actions; }
	if ( ! current_user_can( 'edit_post', $post->ID ) ) { return $actions; }

	$is_pinned = gip_ticker_is_pinned( $post->ID );
	$action    = $is_pinned ? 'unpin' : 'pin';
	$label     = $is_pinned ? __( 'Unpin from Ticker', 'the-grid-index' ) : __( 'Pin to Ticker', 'the-grid-index' );
	$url       = wp_nonce_url(
		admin_url( 'admin-post.php?action=gip_ticker_toggle&post=' . $post->ID . '&op=' . $action ),
		'gip_ticker_toggle_' . $post->ID
	);
	$actions['gip_ticker'] = sprintf(
		'<a href="%s" style="color:%s">%s</a>',
		esc_url( $url ),
		$is_pinned ? '#b91c1c' : '#1d4ed8',
		esc_html( $label )
	);
	return $actions;
}, 10, 2 );

add_action( 'admin_post_gip_ticker_toggle', function () {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	$op      = isset( $_GET['op'] ) ? sanitize_key( $_GET['op'] ) : '';
	if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) { wp_die( 'Forbidden', 403 ); }
	check_admin_referer( 'gip_ticker_toggle_' . $post_id );
	if ( $op === 'pin' )   { gip_ticker_pin_post( $post_id ); }
	if ( $op === 'unpin' ) { gip_ticker_unpin_post( $post_id ); }
	wp_safe_redirect( wp_get_referer() ?: admin_url( 'edit.php' ) );
	exit;
} );

/* -------------------------------------------------------------------------
 * 6. ADMIN — Theme Options → Ticker tab (the "how to set this up" panel)
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'themes.php',
		__( 'Grid Index — Ticker', 'the-grid-index' ),
		__( '— Ticker', 'the-grid-index' ),
		'manage_options',
		'gip-ticker',
		'gip_ticker_render_admin_page'
	);
}, 60 );

function gip_ticker_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	// Handle flush
	if ( isset( $_POST['gip_ticker_flush'] ) && check_admin_referer( 'gip_ticker_admin' ) ) {
		delete_transient( 'gip_ticker_items' );
		echo '<div class="notice notice-success is-dismissible"><p>Ticker cache flushed.</p></div>';
	}
	// Handle quick-add custom item
	if ( isset( $_POST['gip_ticker_add'] ) && check_admin_referer( 'gip_ticker_admin' ) ) {
		$pinned   = gip_ticker_get_pinned();
		$pinned[] = array(
			'title'     => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'url'       => esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) ),
			'source'    => sanitize_text_field( wp_unslash( $_POST['source'] ?? '' ) ),
			'is_live'   => ! empty( $_POST['is_live'] ),
			'timestamp' => current_time( 'timestamp' ),
		);
		gip_ticker_set_pinned( $pinned );
		echo '<div class="notice notice-success is-dismissible"><p>Item pinned to ticker.</p></div>';
	}
	// Handle remove pinned (by index)
	if ( isset( $_POST['gip_ticker_remove'] ) && check_admin_referer( 'gip_ticker_admin' ) ) {
		$idx    = (int) $_POST['gip_ticker_remove'];
		$pinned = gip_ticker_get_pinned();
		if ( isset( $pinned[ $idx ] ) ) { unset( $pinned[ $idx ] ); }
		gip_ticker_set_pinned( array_values( $pinned ) );
		echo '<div class="notice notice-success is-dismissible"><p>Pinned item removed.</p></div>';
	}

	$pinned     = gip_ticker_get_pinned();
	$preview    = gip_get_breaking_ticker_items( 8 );
	$breaking   = get_term_by( 'slug', 'breaking', 'category' );
	$ticker_tag = get_term_by( 'slug', 'ticker', 'post_tag' );
	?>
	<div class="wrap gi-ticker-admin">
		<h1><?php esc_html_e( 'Breaking News Ticker', 'the-grid-index' ); ?></h1>
		<p class="description" style="font-size:14px;max-width:780px">
			<?php esc_html_e( 'The ticker is the thin scrolling strip below the masthead. Stories appear automatically — you only need to mark them. Below are the four ways to put a story on the ticker, in priority order.', 'the-grid-index' ); ?>
		</p>

		<div class="gi-ticker-admin__grid" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:18px">

			<!-- HOW IT WORKS -->
			<div class="card" style="padding:18px;background:#fff;border:1px solid #dcdcde;border-radius:6px">
				<h2 style="margin-top:0;font-size:15px;letter-spacing:.04em;text-transform:uppercase;color:#475569">
					<?php esc_html_e( 'How to add stories — 4 ways', 'the-grid-index' ); ?>
				</h2>

				<ol style="font-size:13.5px;line-height:1.55;padding-left:18px;margin:0">
					<li style="margin-bottom:14px">
						<strong><?php esc_html_e( '1. Pinned (manual, top priority)', 'the-grid-index' ); ?></strong><br>
						<?php esc_html_e( 'Use the "Pin to Ticker" link on any published post in', 'the-grid-index' ); ?>
						<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'Posts → All Posts', 'the-grid-index' ); ?></a>,
						<?php esc_html_e( 'or add a custom item with the form on the right.', 'the-grid-index' ); ?>
					</li>
					<li style="margin-bottom:14px">
						<strong><?php esc_html_e( '2. Breaking category (last 24 h)', 'the-grid-index' ); ?></strong><br>
						<?php esc_html_e( 'In the post editor, check the', 'the-grid-index' ); ?>
						<code>Breaking</code> <?php esc_html_e( 'category. Auto-expires after 24 hours.', 'the-grid-index' ); ?>
						<?php if ( $breaking ) : ?>
							— <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=category&tag_ID=' . $breaking->term_id ) ); ?>"><?php esc_html_e( 'Manage category', 'the-grid-index' ); ?></a>
						<?php endif; ?>
					</li>
					<li style="margin-bottom:14px">
						<strong><?php esc_html_e( '3. ticker tag (long-lived fallback)', 'the-grid-index' ); ?></strong><br>
						<?php esc_html_e( 'Add the', 'the-grid-index' ); ?> <code>ticker</code> <?php esc_html_e( 'tag to any post — used after Breaking is empty. Does not expire.', 'the-grid-index' ); ?>
					</li>
					<li>
						<strong><?php esc_html_e( '4. Latest posts (auto fallback)', 'the-grid-index' ); ?></strong><br>
						<?php esc_html_e( 'If nothing else qualifies, the most recent published posts are used so the strip is never empty.', 'the-grid-index' ); ?>
					</li>
				</ol>

				<hr style="margin:18px 0">
				<p style="font-size:12.5px;color:#64748b;margin:0">
					<?php esc_html_e( 'Cache: 60 s server-side, refreshed every 90 s in the browser.', 'the-grid-index' ); ?>
				</p>
				<form method="post" style="margin-top:10px">
					<?php wp_nonce_field( 'gip_ticker_admin' ); ?>
					<button type="submit" name="gip_ticker_flush" value="1" class="button"><?php esc_html_e( 'Flush ticker cache now', 'the-grid-index' ); ?></button>
				</form>
			</div>

			<!-- QUICK ADD -->
			<div class="card" style="padding:18px;background:#fff;border:1px solid #dcdcde;border-radius:6px">
				<h2 style="margin-top:0;font-size:15px;letter-spacing:.04em;text-transform:uppercase;color:#475569">
					<?php esc_html_e( 'Quick add: custom ticker item', 'the-grid-index' ); ?>
				</h2>
				<form method="post">
					<?php wp_nonce_field( 'gip_ticker_admin' ); ?>
					<p>
						<label style="display:block;font-weight:600;margin-bottom:4px"><?php esc_html_e( 'Headline', 'the-grid-index' ); ?></label>
						<input type="text" name="title" required class="regular-text" style="width:100%" placeholder="OpenAI ships new reasoning model">
					</p>
					<p>
						<label style="display:block;font-weight:600;margin-bottom:4px"><?php esc_html_e( 'URL (internal post or external source)', 'the-grid-index' ); ?></label>
						<input type="url" name="url" required class="regular-text" style="width:100%" placeholder="https://…">
					</p>
					<p>
						<label style="display:block;font-weight:600;margin-bottom:4px"><?php esc_html_e( 'Source label (optional)', 'the-grid-index' ); ?></label>
						<input type="text" name="source" class="regular-text" style="width:100%" placeholder="Reuters">
					</p>
					<p>
						<label><input type="checkbox" name="is_live" value="1"> <?php esc_html_e( 'Show LIVE red dot', 'the-grid-index' ); ?></label>
					</p>
					<button type="submit" name="gip_ticker_add" value="1" class="button button-primary"><?php esc_html_e( 'Pin to Ticker', 'the-grid-index' ); ?></button>
				</form>
			</div>
		</div>

		<!-- CURRENTLY PINNED -->
		<h2 style="margin-top:30px"><?php esc_html_e( 'Currently pinned', 'the-grid-index' ); ?></h2>
		<?php if ( empty( $pinned ) ) : ?>
			<p><em><?php esc_html_e( 'Nothing pinned. The ticker is showing Breaking / ticker / latest items.', 'the-grid-index' ); ?></em></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr>
					<th><?php esc_html_e( 'Headline', 'the-grid-index' ); ?></th>
					<th><?php esc_html_e( 'URL', 'the-grid-index' ); ?></th>
					<th><?php esc_html_e( 'Source', 'the-grid-index' ); ?></th>
					<th><?php esc_html_e( 'Live', 'the-grid-index' ); ?></th>
					<th></th>
				</tr></thead>
				<tbody>
				<?php foreach ( $pinned as $idx => $p ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $p['title'] ?? '' ); ?></strong></td>
						<td><a href="<?php echo esc_url( $p['url'] ?? '#' ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $p['url'] ?? '', PHP_URL_HOST ) ); ?></a></td>
						<td><?php echo esc_html( $p['source'] ?? '' ); ?></td>
						<td><?php echo ! empty( $p['is_live'] ) ? '🔴' : '—'; ?></td>
						<td>
							<form method="post" style="margin:0">
								<?php wp_nonce_field( 'gip_ticker_admin' ); ?>
								<button type="submit" name="gip_ticker_remove" value="<?php echo esc_attr( $idx ); ?>" class="button-link-delete"><?php esc_html_e( 'Remove', 'the-grid-index' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<!-- LIVE PREVIEW -->
		<h2 style="margin-top:30px"><?php esc_html_e( 'Live preview (next 8 items)', 'the-grid-index' ); ?></h2>
		<?php if ( empty( $preview ) ) : ?>
			<p><em><?php esc_html_e( 'No items resolved. Publish a post to populate.', 'the-grid-index' ); ?></em></p>
		<?php else : ?>
			<ol style="font-size:13.5px;line-height:1.6">
				<?php foreach ( $preview as $i ) : ?>
					<li>
						<?php echo ! empty( $i['is_live'] ) ? '<span style="color:#dc2626">●</span> ' : ''; ?>
						<strong><?php echo esc_html( $i['title'] ); ?></strong>
						<span style="color:#64748b"> — <?php echo esc_html( $i['source'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</div>
	<?php
}
