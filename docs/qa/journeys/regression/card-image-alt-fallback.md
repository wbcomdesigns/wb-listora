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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Listings without a featured image render a deterministic placeholder with correct alt semantics

WCAG 2.1 AA alt-text on every featured-image surface. The contract:

- **Real featured image**: informative alt = listing title, falling back to a deterministic `Listing #ID` when the listing has no title (never an empty alt).
- **No featured image**: the bundled placeholder is DECORATIVE: `alt=""` + `aria-hidden="true"` (not a misleading title alt), and it is the same SVG every time (`wb_listora_placeholder_url()`).

Applies to the grid card, the detail gallery (hero + thumbnails) and the map popup marker. Role ladder: the surface is public and identical for every role, so run it as anonymous and once as admin (control); the owner, another member and an editor see the same markup.

## Setup

- Site `$SITE_URL`, anonymous browser; repeat step 1 once logged in as `varundubey`.
- `<NOIMG>`: a published listing with a title and NO featured image. On the QA site that is ID 845 (`/listing/qa-wave2-coupon-test/`). Find others with:
  `wp eval 'global $wpdb; echo implode(",", $wpdb->get_col("SELECT p.ID FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key=\"_thumbnail_id\" WHERE p.post_type=\"listora_listing\" AND p.post_status=\"publish\" AND m.meta_id IS NULL LIMIT 5"));'`
- `<WITHIMG>`: any demo listing, e.g. ID 746 (`/listing/central-park-the-mall-bethesda-terrace/`).
- `<UNTITLED>`: a published listing with a featured image and an empty `post_title`. None exists on the QA site (0 of 136 published): create `QA-J1-untitled` through `wp post create` then blank the title, and delete it afterwards.
- Directory page: `$SITE_URL/listings/` (the "Directory" page; 136 published listings, 20 cards per page).

## Steps

### 1. Positive control: a card WITH a featured image has an informative alt
- **Action**: open `$SITE_URL/listings/`, inspect any `img.listora-card__image` whose `src` is under `/uploads/`.
- **Expect**: `alt` equals the listing title (non-empty), no `aria-hidden` on the `<img>`. The wrapping `a.listora-card__image-link` is `tabindex="-1" aria-hidden="true"` (the title link is the real target).

### 2. Card without a featured image is a decorative placeholder
- **Action**: locate `<NOIMG>`'s card in the Directory (type its title in `#listora-keyword`, placeholder "Search listings...", press Enter) and inspect `img.listora-card__image`.
- **Expect**: `src` ends `assets/images/placeholder.svg`, `alt=""` and `aria-hidden="true"`. Every card image carries `data-listora-fallback-src` with the same placeholder URL.
- **Not a match**: a card whose `src` is the placeholder but whose `alt` is the listing title (decorative branch dropped).

### 3. Untitled listing with a real image uses "Listing #ID"
- **Action**: inspect `<UNTITLED>`'s card, then its detail page hero `img.listora-detail__gallery-image` and thumbnails `button.listora-detail__gallery-thumb img`.
- **Expect**: card alt `Listing #<ID>`; hero alt = the attachment's own alt text, else `Listing #<ID>`; thumbnails read `Listing #<ID> photo N` (never `" photo N"`). A placeholder card for an untitled listing stays `alt=""`.

### 4. Detail page of a listing with no images renders no gallery
- **Action**: open `<NOIMG>`'s detail page.
- **Expect**: no `.listora-detail__gallery` element (no `<img src="">`, no photo count, no carousel buttons). The placeholder is a card/popup concern only.

### 5. Map popup marker
- **Action**: open a page with the Listing Map block (the Directory renders `.listora-map`, or any page with the block) and click the marker for `<UNTITLED>` or a listing with a thumbnail.
- **Expect**: the popup `<img>` alt is the listing title or `Listing #<ID>`, never empty. The block's marker JSON carries `imageAlt` = marker title when a thumbnail exists, else `""`.

### 6. Accessibility tree
- **Action**: take an accessibility snapshot of the Directory at 1440 and 390.
- **Expect**: placeholder images are absent from the tree; informative images expose the title as their name; no image name is a raw attachment ID or file name.

## Pass criteria

1. Placeholder cards: deterministic SVG, `alt=""`, `aria-hidden="true"`.
2. Real images: title alt, `Listing #ID` when untitled, never empty.
3. Detail hero + thumbnails resolve attachment alt, then title, then `Listing #ID`.
4. Map popup marker resolves a non-empty title + explicit `imageAlt`.
5. Identical for anonymous, owner, another member, editor and admin.
6. 390px: cards stack, no horizontal page scroll.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Placeholder card has a title alt | decorative branch dropped | `templates/blocks/listing-card/card-image.php:34-59` |
| Untitled real image emits empty alt | `Listing #ID` fallback missing | `card-image.php:36-39`, `templates/blocks/listing-detail/gallery.php:70-76` |
| Thumbnail alt is `" photo N"` | title token not hoisted | `templates/blocks/listing-detail/gallery.php:159-177` |
| Map popup img alt empty | marker title fallback / `imageAlt` missing | `blocks/listing-map/render.php:141-160` |
| Placeholder differs per render | not using the helper | `includes/class-template-helpers.php:147` |

## Not verified

Steps 3 (`<UNTITLED>` live) and 5 (map popup click) were checked against source only. Steps 1, 2 and 4 markup verified via curl/DOM on the live site (card `img` attributes, detail gallery). Verified via: curl/DOM + source.
