---
journey: card-image-alt-fallback
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [f2-image-alt-fallback, wcag-2.1-aa-alt-text, visual_required_no_enforcement, decorative-placeholder]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Free active"
  - "One published listora_listing WITHOUT a featured image"
  - "One published listora_listing WITHOUT a featured image AND without a title (untitled import/draft) for the deterministic-fallback assertion"
estimated_runtime_minutes: 5
covers_card: null
covers_commit: HEAD
---

# Listings without a featured image render a deterministic placeholder with correct alt semantics

The wave-2 `f2-image-alt-fallback` fix enforces WCAG 2.1 AA alt-text on every
featured-image surface and clears the `visual_required_no_enforcement`
detector. The contract:

- **Real featured image** → informative alt = listing title, falling back to a
  deterministic `Listing #ID` when the listing has no title (never an empty
  alt).
- **No featured image** → the bundled placeholder is DECORATIVE: `alt=""` +
  `aria-hidden="true"` (NOT a misleading title alt), and the placeholder is the
  same deterministic SVG (`wb_listora_placeholder_url()`).

Applies on the grid card, the detail gallery (hero + thumbnails), and the map
popup marker. This journey locks placeholder determinism + alt semantics across
all three surfaces.

## Setup

- Site: `$SITE_URL`, anonymous browser
- `<NOIMG>` ← a published listing WITH a title but NO featured image
- `<UNTITLED>` ← a published listing with NO featured image AND empty title

## Steps

### 1. Grid card without a featured image → decorative placeholder
- **Action**: `playwright_navigate` a grid/archive page containing `<NOIMG>`;
  inspect that card's `.listora-card__image`.
- **Expect**: `src` is the bundled placeholder
  (`assets/images/placeholder.svg` via `wb_listora_placeholder_url()`),
  `alt=""`, and `aria-hidden="true"` is present. The `<a>` wrapper is
  `tabindex="-1" aria-hidden="true"` (decorative link).
- **On fail**: `templates/blocks/listing-card/card-image.php` (the
  `$has_featured_image` ternary on `alt` + `aria-hidden`)

### 2. Grid card WITH a featured image → informative title alt
- **Action**: inspect a card that DOES have a featured image.
- **Expect**: `alt` equals the listing title (non-empty), no `aria-hidden` on
  the `<img>`.
- **On fail**: same template - `$card_image_alt` branch

### 3. Untitled listing card → deterministic "Listing #ID" alt
- **Action**: inspect `<UNTITLED>`'s card (when it has a real featured image,
  the alt fallback applies; placeholder cards stay decorative).
- **Expect**: a featured-image card for an untitled listing uses
  `Listing #<ID>` as alt, never an empty string. A placeholder card stays
  `alt=""` + `aria-hidden`.
- **On fail**: `card-image.php` `sprintf( __( 'Listing #%d' ), $id )` fallback

### 4. Detail page gallery - hero + thumbnails resolve a non-empty alt
- **Action**: `playwright_navigate` the single page of an untitled (or
  attachment-alt-less) listing WITH images; inspect the hero `<img>` and each
  thumbnail `<img>`.
- **Expect**: alt resolves attachment `_wp_attachment_image_alt` first, then
  the listing title, then `Listing #<ID>` - never empty. Thumbnails read
  `Listing #<ID> photo N` when untitled (never a leading-space `" photo N"`).
- **On fail**: `templates/blocks/listing-detail/gallery.php`
  (`$gallery_main_alt` / `$gallery_alt_title` / `$gallery_thumb_alt`)

### 5. Map popup - deterministic marker title + explicit imageAlt
- **Action**: `playwright_navigate` a page with the listing-map block; click the
  marker for an untitled listing that has a thumbnail.
- **Expect**: the popup `<img>` carries a non-empty alt resolved to
  `Listing #<ID>`. The marker JSON surfaces an explicit `imageAlt` field (set
  to the marker title when a thumbnail exists, else empty).
- **On fail**: `blocks/listing-map/render.php` (`$marker_title` fallback +
  `imageAlt` field in `$markers_json`)

### 6. Screen-reader / aria assertion
- **Action**: via `playwright_snapshot` (accessibility tree), confirm the
  decorative placeholder image is NOT announced (aria-hidden), and that
  informative images expose their resolved alt as the accessible name.
- **Expect**: no listing image announces a raw attachment ID or an empty name;
  placeholders are absent from the a11y tree.
- **On fail**: any of the three templates above

## Pass criteria

ALL of the following hold:
1. No-featured-image cards render the deterministic placeholder with `alt=""` +
   `aria-hidden="true"` (decorative).
2. Real featured images carry a title alt, falling back to `Listing #ID` when
   untitled - never an empty alt.
3. Detail hero + thumbnails resolve attachment-alt → title → `Listing #ID`.
4. Map popup marker resolves a deterministic title + explicit `imageAlt`.
5. Accessibility tree never exposes a raw ID or an empty image name; decorative
   placeholders are not announced.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Placeholder card has a title alt | decorative branch dropped | `templates/blocks/listing-card/card-image.php` |
| Untitled real image emits empty alt | `Listing #ID` fallback missing | `card-image.php` / `gallery.php` |
| Thumbnail alt is `" photo N"` | title token not hoisted | `templates/blocks/listing-detail/gallery.php` |
| Map popup img alt empty | marker title fallback / imageAlt missing | `blocks/listing-map/render.php` |
| Placeholder differs per render | not using `wb_listora_placeholder_url()` | `includes/class-template-helpers.php:121` |
