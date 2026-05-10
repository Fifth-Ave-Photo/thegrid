# Grid Index Press v1.10.15 — Featured Slider setup

Self-contained homepage hero slider with a clear admin setup screen, autoplay
with configurable speed (slow / medium / fast), and three editor-friendly ways
to add slides.

## Files

```
inc/featured-slider.php
assets/slider/slider.css
assets/slider/slider.js
```

## Install

1. Copy the files into the theme.
2. In `functions.php`, after other `inc/*` requires:
   ```php
   require_once get_template_directory() . '/inc/featured-slider.php';
   ```
3. In your homepage / front-page template, place the slider where the hero goes:
   ```php
   <?php if ( function_exists( 'gip_render_featured_slider' ) ) gip_render_featured_slider(); ?>
   ```
4. Bump `GIP_VERSION` to `1.10.15`.
5. Reload any admin page once — the `Featured Slider` category is created
   automatically.

## Editor workflow

Open **Appearance → — Slider**. Three ways to add slides:

1. **Tick "Featured Slider" category** in the post editor.
2. **Posts → All Posts** → row action **Add to Slider**.
3. **Quick add** a custom slide (image URL + headline + link), great for
   external links, ads, or one-off promos.

## Settings (admin)

- Enabled (master toggle)
- Slide source: posts / custom / mixed
- Max slides (1–12)
- Autoplay on/off
- **Autoplay speed: slow (8 s) / medium (5 s) / fast (3 s)**
- Transition: slide / fade
- Show arrows / dots / kicker
- Flush slider cache button

## Behaviour

- Autoplay pauses on hover, focus, and when the browser tab is hidden.
- Touch swipe + keyboard arrows.
- Respects `prefers-reduced-motion`.
- 60 s server-side cache via `gip_slider_items` transient.
- Falls back to topic-branded fallback image (v1.10.13) when a post has no
  featured image.

## Hooks

- `apply_filters( 'gip_slider_items', $items )` — modify slide list at runtime.
