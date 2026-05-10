<?php
/**
 * The Grid Index — Editorial Image Cleaner.
 *
 * Defensive image-handling layers used across the homepage and feeds:
 *
 *   1. Featured image from RSS/article
 *   2. OG image scraped from source page (cached 24h)
 *   3. Topic-based branded fallback
 *   4. Minimal editorial fallback card (default = world.jpg)
 *
 * Cleaner rejects:
 *   - tracking pixels / 1x1 beacons (npr-rss-pixel, doubleclick, fb pixel...)
 *   - aggregator proxy thumbnails (Google News, gstatic, yimg, feedburner)
 *   - logos / favicons / avatars / spacers
 *   - tiny URL-hinted thumbnails (=w64, _50x50, /thumbs/...)
 *   - stock-photo CDNs and lifestyle paths on sensitive topics (AI, tech,
 *     startups, cybersecurity, science, health)
 *   - .gif and .svg masquerading as hero photography
 *
 * Public API:
 *   gridindex_resolve_story_image( $url, $category )
 *   gridindex_render_story_image(  $url, $category, $alt )
 *   gridindex_get_fallback_image(  $category )
 *
 * Helpers:
 *   gridindex_image_failure_reason( $url, $category )  → string|null
 *   gridindex_has_real_image(      $url )              → bool
 *   gridindex_image_quality_score( $url, $w, $h, $src_type ) → 0..1
 *   gridindex_fetch_og_image(      $page_url )         → string|null  (cached)
 *   gridindex_resolve_story_image_full( $args )        → array        (rich)
 *
 * @package The_Grid_Index
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ─────────────────────────────────────────────────────────────────────────
 * 1. Pattern + host blocklists
 * ────────────────────────────────────────────────────────────────────── */

function gridindex_blocked_image_domains() {
	return apply_filters( 'gridindex_blocked_image_domains', array(
		'news.google.com',
		'googleusercontent.com',
		'lh3.googleusercontent.com',
		'lh4.googleusercontent.com',
		'lh5.googleusercontent.com',
		'lh6.googleusercontent.com',
		'feeds.feedburner.com',
		'feedproxy.google.com',
		'gstatic.com',
		's.yimg.com',
		'doubleclick.net',
		'googletagmanager.com',
		'scorecardresearch.com',
	) );
}

/** Tracking-pixel & beacon URL patterns. */
function gridindex_tracking_pixel_patterns() {
	return apply_filters( 'gridindex_tracking_pixel_patterns', array(
		'/npr-rss-pixel/i',
		'#/tracking/[^/]+pixel#i',
		'#/pixel\.(png|gif|jpg)#i',
		'/1x1\.(png|gif)/i',
		'#/transparent\.gif#i',
		'#/spacer\.gif#i',
		'/doubleclick\.net/i',
		'/googletagmanager/i',
		'/scorecardresearch/i',
		'/facebook\.com\/tr/i',
		'#/beacon#i',
	) );
}

/** Bad-image content patterns (logos, avatars, favicons, etc.). */
function gridindex_bad_image_patterns() {
	return apply_filters( 'gridindex_bad_image_patterns', array(
		'/1x1/i',
		'/pixel\.gif/i',
		'/spacer\.gif/i',
		'/transparent\.png/i',
		'/favicon/i',
		'#/avatar/#i',
		'#/avatars/#i',
		'/gravatar\.com/i',
		'#/logo\.(png|svg|jpg|jpeg|webp)#i',
		'/sharethis/i',
		'#news\.google\.com/.*/img#i',
		'#googleusercontent\.com/.*=w\d{1,2}(?:-h\d{1,2})?$#i',
		'#gstatic\.com/images#i',
		'#s\.yimg\.com#i',
	) );
}

/** URL hints that indicate a too-small image (thumbnails, =w120, _50x50, etc.). */
function gridindex_small_image_hints() {
	return apply_filters( 'gridindex_small_image_hints', array(
		'/[_-]\d{2}x\d{2,3}\./i',
		'/[_-]\d{2,3}x\d{2}\./i',
		'/[?&]w=([1-9]\d?|1\d{2}|2\d{2})(?!\d)/i',
		'/[?&]width=([1-9]\d?|1\d{2}|2\d{2})(?!\d)/i',
		'/=s\d{2}(-c)?$/i',
		'#/thumbs?/#i',
		'#/thumbnail/#i',
	) );
}

/** Stock / lifestyle hosts and paths — only apply to sensitive topics. */
function gridindex_stock_hints() {
	return apply_filters( 'gridindex_stock_hints', array(
		'irrelevant_stock_image' => array(
			'#(?:^|\.)shutterstock\.com/#i',
			'#(?:^|\.)istockphoto\.com/#i',
			'#(?:^|\.)pexels\.com/#i',
			'#(?:^|\.)unsplash\.com/#i',
			'#(?:^|\.)pixabay\.com/#i',
			'#(?:^|\.)adobe\.com/.*stock#i',
		),
		'lifestyle_mismatch' => array(
			'#(?:^|\.)gettyimages\.com/[^?]*/(?:lifestyle|fashion|beauty|portrait)#i',
			'#/(?:lifestyle|beauty|fashion|wellness|home-and-garden|food-and-drink|recipes|travel)/#i',
		),
		'generic_portrait' => array(
			'#/(?:portrait|headshot|profile-pic|profile_pic)#i',
			'#/(?:author|byline|contributor|staff)/.*\.(jpe?g|png|webp)#i',
		),
		'logo_only' => array(
			'#[-_/](?:logo|brand|wordmark)[-_./]#i',
		),
	) );
}

function gridindex_sensitive_topics() {
	return apply_filters( 'gridindex_sensitive_topics', array(
		'ai', 'tech', 'technology', 'startups', 'cybersecurity', 'science', 'health',
	) );
}

/* ─────────────────────────────────────────────────────────────────────────
 * 2. URL normalization + classification
 * ────────────────────────────────────────────────────────────────────── */

function gridindex_normalize_image_url( $url ) {
	if ( ! is_string( $url ) ) { return ''; }
	$url = trim( $url );
	$url = str_ireplace( array( '&amp;', '&#038;', '&#0038;', '&#x26;' ), '&', $url );
	return $url;
}

function gridindex_image_host( $url ) {
	$host = wp_parse_url( $url, PHP_URL_HOST );
	if ( ! $host ) { return ''; }
	return strtolower( preg_replace( '/^www\./i', '', $host ) );
}

function gridindex_match_any( $patterns, $subject ) {
	foreach ( $patterns as $re ) {
		if ( @preg_match( $re, $subject ) ) { return true; }
	}
	return false;
}

/**
 * Returns a string reason if the image should be rejected, or null if usable.
 * Reasons mirror the React app for consistent reporting:
 *   missing_image | invalid_url | tracking_pixel | small_image |
 *   blocked_domain | google_news_bad_image | suspicious_filetype |
 *   irrelevant_stock_image | lifestyle_mismatch | generic_portrait |
 *   logo_only | weak_aggregator_image
 */
function gridindex_image_failure_reason( $url, $category = '' ) {
	if ( empty( $url ) || ! is_string( $url ) ) { return 'missing_image'; }
	$normalized = gridindex_normalize_image_url( $url );
	if ( ! preg_match( '#^https?://#i', $normalized ) ) { return 'invalid_url'; }
	if ( strlen( $normalized ) < 60 ) { return 'tracking_pixel'; }

	if ( gridindex_match_any( gridindex_tracking_pixel_patterns(), $normalized ) ) {
		return 'tracking_pixel';
	}
	if ( gridindex_match_any( gridindex_small_image_hints(), $normalized ) ) {
		return 'small_image';
	}
	if ( preg_match( '/news\.google\.com/i', $normalized ) ) {
		return 'google_news_bad_image';
	}
	if ( gridindex_match_any( gridindex_bad_image_patterns(), $normalized ) ) {
		return 'blocked_domain';
	}

	$host = gridindex_image_host( $normalized );
	foreach ( gridindex_blocked_image_domains() as $blocked ) {
		if ( $host === $blocked || ( $host && str_ends_with( $host, '.' . $blocked ) ) ) {
			return ( false !== stripos( $host, 'google' ) )
				? 'google_news_bad_image'
				: 'blocked_domain';
		}
	}

	if ( preg_match( '/\.(gif|svg)(?:$|\?)/i', $normalized ) ) {
		return 'suspicious_filetype';
	}

	// Topic-aware stock/lifestyle rejection (sensitive topics only).
	$norm_cat = gridindex_normalize_category( $category );
	if ( in_array( $norm_cat, gridindex_sensitive_topics(), true ) ) {
		foreach ( gridindex_stock_hints() as $reason => $patterns ) {
			if ( gridindex_match_any( $patterns, $normalized ) ) {
				return $reason;
			}
		}
	}

	return null;
}

function gridindex_has_real_image( $url ) {
	return null === gridindex_image_failure_reason( $url );
}

/* ─────────────────────────────────────────────────────────────────────────
 * 3. Quality score
 * ────────────────────────────────────────────────────────────────────── */

function gridindex_image_quality_score( $url, $w = 0, $h = 0, $source_type = '' ) {
	if ( ! gridindex_has_real_image( $url ) ) { return 0.0; }

	$res = 0.5;
	if ( $w >= 1200 )      { $res = 1.0; }
	elseif ( $w >= 800 )   { $res = 0.85; }
	elseif ( $w >= 600 )   { $res = 0.7; }
	elseif ( $w >= 400 )   { $res = 0.5; }
	elseif ( $w > 0 )      { $res = 0.3; }

	$aspect = 0.7;
	if ( $w > 0 && $h > 0 ) {
		$r = $w / $h;
		if ( $r >= 1.5 && $r <= 2.2 )      { $aspect = 1.0; }
		elseif ( $r >= 1.2 )               { $aspect = 0.85; }
		elseif ( $r >= 0.95 )              { $aspect = 0.5; }
		else                               { $aspect = 0.3; }
	}

	$weights = array(
		'og' => 1.0, 'opengraph' => 1.0, 'firecrawl' => 0.95, 'article' => 0.9,
		'rss_media' => 0.7, 'rss' => 0.6, 'thumbnail' => 0.4, 'proxy' => 0.3,
	);
	$st = strtolower( (string) $source_type );
	$sw = isset( $weights[ $st ] ) ? $weights[ $st ] : 0.6;

	return ( $res * 0.45 ) + ( $aspect * 0.35 ) + ( $sw * 0.2 );
}

/* ─────────────────────────────────────────────────────────────────────────
 * 4. OG image scraper (cached 24h) — used as layer 2 of the hierarchy
 * ────────────────────────────────────────────────────────────────────── */

function gridindex_fetch_og_image( $page_url ) {
	if ( empty( $page_url ) || ! is_string( $page_url ) ) { return null; }
	if ( ! preg_match( '#^https?://#i', $page_url ) )      { return null; }

	$key    = 'gip_og_' . md5( $page_url );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		return $cached ? $cached : null;
	}

	$resp = wp_safe_remote_get( $page_url, array(
		'timeout'     => 6,
		'redirection' => 3,
		'user-agent'  => 'GridIndexBot/1.0 (+https://thegridindex.com)',
	) );
	if ( is_wp_error( $resp ) ) {
		set_transient( $key, '', DAY_IN_SECONDS );
		return null;
	}

	$body = wp_remote_retrieve_body( $resp );
	if ( ! $body ) {
		set_transient( $key, '', DAY_IN_SECONDS );
		return null;
	}

	$found = '';
	$head  = substr( $body, 0, 200000 );
	$rxs   = array(
		'#<meta[^>]+property=["\']og:image(?::secure_url)?["\'][^>]+content=["\']([^"\']+)["\']#i',
		'#<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image(?::secure_url)?["\']#i',
		'#<meta[^>]+name=["\']twitter:image["\'][^>]+content=["\']([^"\']+)["\']#i',
	);
	foreach ( $rxs as $rx ) {
		if ( preg_match( $rx, $head, $m ) ) { $found = $m[1]; break; }
	}

	$found = $found ? gridindex_normalize_image_url( html_entity_decode( $found ) ) : '';
	if ( $found && gridindex_has_real_image( $found ) ) {
		set_transient( $key, $found, DAY_IN_SECONDS );
		return $found;
	}

	set_transient( $key, '', DAY_IN_SECONDS );
	return null;
}

/* ─────────────────────────────────────────────────────────────────────────
 * 5. Category normalization + branded fallback URLs
 * ────────────────────────────────────────────────────────────────────── */

function gridindex_normalize_category( $category ) {
	$slug = strtolower( trim( (string) $category ) );
	$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );

	$aliases = array(
		'artificial-intelligence' => 'ai',
		'machine-learning'        => 'ai',
		'genai'                   => 'ai',
		'technology'              => 'tech',
		'software'                => 'tech',
		'gadgets'                 => 'tech',
		'startup'                 => 'startups',
		'venture'                 => 'startups',
		'vc'                      => 'startups',
		'finance'                 => 'business',
		'markets'                 => 'business',
		'economy'                 => 'business',
		'cyber'                   => 'cybersecurity',
		'security'                => 'cybersecurity',
		'infosec'                 => 'cybersecurity',
		'world-news'              => 'world',
		'international'           => 'world',
		'geopolitics'             => 'world',
		'politics-news'           => 'politics',
		'us-politics'             => 'politics',
	);
	if ( isset( $aliases[ $slug ] ) ) { $slug = $aliases[ $slug ]; }

	$allowed = array( 'ai', 'tech', 'startups', 'cybersecurity', 'politics', 'business', 'world', 'science', 'health' );
	return in_array( $slug, $allowed, true ) ? $slug : 'world';
}

function gridindex_get_fallback_image( $category = 'world' ) {
	$slug = gridindex_normalize_category( $category );
	$base = trailingslashit( get_template_directory_uri() ) . 'assets/fallbacks/';
	$map  = array(
		'ai'            => $base . 'ai.jpg',
		'tech'          => $base . 'tech.jpg',
		'startups'      => $base . 'startups.jpg',
		'cybersecurity' => $base . 'cybersecurity.jpg',
		'politics'      => $base . 'politics.jpg',
		'business'      => $base . 'business.jpg',
		'world'         => $base . 'world.jpg',
		'science'       => $base . 'world.jpg', // share until art lands
		'health'        => $base . 'world.jpg',
	);
	return isset( $map[ $slug ] ) ? $map[ $slug ] : $map['world'];
}

/* ─────────────────────────────────────────────────────────────────────────
 * 6. Resolver (back-compat) + rich resolver
 * ────────────────────────────────────────────────────────────────────── */

function gridindex_resolve_story_image( $candidate_url, $category = 'world' ) {
	$reason = gridindex_image_failure_reason( $candidate_url, $category );
	if ( null === $reason ) {
		return esc_url_raw( gridindex_normalize_image_url( $candidate_url ) );
	}
	return gridindex_get_fallback_image( $category );
}

/**
 * Rich resolver that walks the full hierarchy and returns metadata.
 *
 *   $args = array(
 *     'image_url'   => string,   // RSS/article featured (layer 1)
 *     'page_url'    => string,   // canonical article URL (used for OG scrape)
 *     'category'    => string,
 *     'use_og'      => bool,     // default true
 *     'source_type' => string,
 *   );
 *
 * Returns:
 *   array(
 *     'src'         => string,
 *     'is_fallback' => bool,
 *     'reason'      => string,   // 'ok' | failure reason
 *     'layer'       => 'featured' | 'og' | 'fallback',
 *   )
 */
function gridindex_resolve_story_image_full( $args ) {
	$args = wp_parse_args( $args, array(
		'image_url'   => '',
		'page_url'    => '',
		'category'    => 'world',
		'use_og'      => true,
		'source_type' => '',
	) );
	$cat = $args['category'];

	// Layer 1 — featured image
	$reason = gridindex_image_failure_reason( $args['image_url'], $cat );
	if ( null === $reason ) {
		return array(
			'src'         => esc_url_raw( gridindex_normalize_image_url( $args['image_url'] ) ),
			'is_fallback' => false,
			'reason'      => 'ok',
			'layer'       => 'featured',
		);
	}

	// Layer 2 — OG scrape from canonical page
	if ( $args['use_og'] && ! empty( $args['page_url'] ) ) {
		$og = gridindex_fetch_og_image( $args['page_url'] );
		if ( $og && null === gridindex_image_failure_reason( $og, $cat ) ) {
			return array(
				'src'         => esc_url_raw( $og ),
				'is_fallback' => false,
				'reason'      => 'ok',
				'layer'       => 'og',
			);
		}
	}

	// Layer 3/4 — branded fallback
	return array(
		'src'         => gridindex_get_fallback_image( $cat ),
		'is_fallback' => true,
		'reason'      => $reason ? $reason : 'missing_image',
		'layer'       => 'fallback',
	);
}

/* ─────────────────────────────────────────────────────────────────────────
 * 7. Renderer
 * ────────────────────────────────────────────────────────────────────── */

function gridindex_render_story_image( $image_url, $category = 'world', $alt = '' ) {
	$cat   = gridindex_normalize_category( $category );
	$label = strtoupper( $cat );
	$src   = $image_url ? $image_url : gridindex_get_fallback_image( $cat );
	$alt   = $alt ? $alt : sprintf( 'Grid Index — %s', $label );
	?>
	<figure class="gi-story-image gi-story-image--<?php echo esc_attr( $cat ); ?>">
		<div class="gi-story-image__frame">
			<img
				class="gi-story-image__img"
				src="<?php echo esc_url( $src ); ?>"
				alt="<?php echo esc_attr( $alt ); ?>"
				loading="lazy"
				decoding="async"
				width="1600"
				height="900"
			/>
			<span class="gi-story-image__overlay" aria-hidden="true"></span>
			<span class="gi-story-image__badge"><?php echo esc_html( $label ); ?></span>
		</div>
	</figure>
	<?php
}

/* ─────────────────────────────────────────────────────────────────────────
 * 8. Hooks
 * ────────────────────────────────────────────────────────────────────── */

add_filter( 'gridindex_story_image', 'gridindex_resolve_story_image', 10, 2 );

/**
 * Defensive last-mile filter for WP's own featured-image markup. If the post
 * thumbnail URL is a tracking pixel / aggregator proxy / known bad pattern,
 * swap it for the branded fallback so themes that bypass our helper still
 * stay clean.
 */
add_filter( 'wp_get_attachment_image_src', function ( $image, $attachment_id, $size, $icon ) {
	if ( ! is_array( $image ) || empty( $image[0] ) ) { return $image; }
	if ( gridindex_has_real_image( $image[0] ) )       { return $image; }

	$cat = 'world';
	$post = get_post( get_post_thumbnail_id() === $attachment_id ? get_the_ID() : 0 );
	if ( $post ) {
		$cats = get_the_category( $post->ID );
		if ( ! empty( $cats[0] ) ) { $cat = $cats[0]->slug; }
	}
	$image[0] = gridindex_get_fallback_image( $cat );
	return $image;
}, 20, 4 );
