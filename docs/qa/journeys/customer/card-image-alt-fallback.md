---
journey: card-image-alt-fallback
plugin: wb-listora
priority: high
roles: [guest, owner, other, editor, admin]
covers: [image-alt-fallback, wcag-2.1-aa-alt-text, decorative-placeholder, visual-required-no-enforcement]
prerequisites:
  - "Site reachable at $SITE_URL; Free active"
  - "One published listing WITHOUT a featured image ($NOIMG); one WITH ($WITHIMG); one with an image and an empty title ($UNTITLED, created in Setup)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes: []
site_profile: required
---

# Listings without a featured image render a deterministic decorative placeholder; real images always have an alt

## Why
Screen-reader users hear a misleading title for a stock placeholder, or nothing for an untitled real photo. WCAG 2.1 AA needs an informative alt for real images and an empty alt for decoration, on the card, the detail gallery and the map popup.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Contract: a real featured image has `alt` = title, else `Listing #ID`, never empty; no featured image shows the bundled placeholder `alt=""` + `aria-hidden="true"`, the same SVG every time (`wb_listora_placeholder_url()`).
- `$NOIMG`: `wp --path=$WP_PATH eval 'global $wpdb; echo implode(",", $wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key=\"_thumbnail_id\" WHERE p.post_type=\"listora_listing\" AND p.post_status=\"publish\" AND m.meta_id IS NULL LIMIT 5"));'`
- `$UNTITLED`: create a QA listing with a featured image, then blank its `post_title` (`wp post update <id> --post_title=""`); delete in Cleanup.
- Directory page `$SITE_URL/listings/`.

## Steps

### 1. Positive control: a card WITH a featured image has an informative alt
- **Action**: inspect any `img.listora-card__image` whose `src` is under `/uploads/` (guest, then `{admin}` once).
- **Expect**: `alt` equals the listing title (non-empty), no `aria-hidden` on the `<img>`; the wrapping `a.listora-card__image-link` is `tabindex="-1" aria-hidden="true"`.

### 2. Card without a featured image is a decorative placeholder
- **Action**: find `$NOIMG`'s card (type its title in `#listora-keyword`, Enter) and inspect `img.listora-card__image`.
- **Expect**: `src` ends `assets/images/placeholder.svg`, `alt=""`, `aria-hidden="true"`; every card image carries `data-listora-fallback-src` with the same placeholder URL.
- **Not a match**: a placeholder card whose `alt` is the listing title (decorative branch dropped).

### 3. Untitled listing with a real image uses "Listing #ID"
- **Action**: inspect `$UNTITLED`'s card, then its detail hero `img.listora-detail__gallery-image` and thumbnails `button.listora-detail__gallery-thumb img`.
- **Expect**: card alt `Listing #<ID>`; hero alt = the attachment's own alt, else `Listing #<ID>`; thumbnails `Listing #<ID> photo N` (never `" photo N"`). A placeholder card for an untitled listing stays `alt=""`.

### 4. Detail page of a listing with no images renders no gallery
- **Action**: open `$NOIMG`'s detail page.
- **Expect**: no `.listora-detail__gallery` (no `<img src="">`, no photo count, no carousel buttons); the placeholder is a card/popup concern only.

### 5. Map popup marker
- **Action**: on a page with the Listing Map block click the marker for `$UNTITLED` and for a listing with a thumbnail.
- **Expect**: popup `<img>` alt is the title or `Listing #<ID>`, never empty; the block's marker JSON carries `imageAlt` = marker title when a thumbnail exists, else `""`.

### 6. Accessibility tree and boundary titles
- **Action**: accessibility snapshot of the Directory at 1440 and 390; also give a QA listing a title of only whitespace, a 300-character title, and a title with markup/quotes.
- **Expect**: placeholder images absent from the tree; informative images expose the title as their name; no image name is a raw attachment ID or file name; whitespace-only title behaves as untitled (`Listing #ID`); long and markup titles are escaped in the alt.

### 7. Role ladder and layout
- Public surface identical for guest, `{owner}`, `{other}`, `{editor}`, `{admin}` (admin is the control). 390px: cards stack, no horizontal scroll.

## Role ladder
See step 7.

## Pass criteria
1. Placeholder cards: deterministic SVG, `alt=""`, `aria-hidden="true"`.
2. Real images: title alt, `Listing #ID` when untitled, never empty.
3. Detail hero and thumbnails resolve attachment alt, then title, then `Listing #ID`.
4. Map popup marker resolves a non-empty title and explicit `imageAlt`.
5. Identical for every role; 390px has no horizontal scroll.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Placeholder card has a title alt | decorative branch dropped | `templates/blocks/listing-card/card-image.php:34-59` |
| Untitled real image emits empty alt | `Listing #ID` fallback missing | `card-image.php:36-39`, `templates/blocks/listing-detail/gallery.php:70-76` |
| Thumbnail alt is `" photo N"` | title token not hoisted | `templates/blocks/listing-detail/gallery.php:159-177` |
| Map popup img alt empty | marker title fallback / `imageAlt` missing | `blocks/listing-map/render.php:141-160` |
| Placeholder differs per render | not using the helper | `includes/class-template-helpers.php:147` |

## Not verified
Steps 3 and 5 against source only; step 6 boundary titles and the a11y snapshot were not run. Steps 1, 2, 4 verified via curl/DOM.

## Cleanup
Delete `$UNTITLED` and the boundary-title listings.
