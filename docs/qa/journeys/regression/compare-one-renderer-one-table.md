---
journey: compare-one-renderer-one-table
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers:
  - BC 10208510133
likely_files:
  - ../wb-listora-pro/includes/features/class-comparison.php
  - ../wb-listora-pro/blocks/comparison/render.php
  - ../wb-listora-pro/blocks/comparison/style.css
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Compare: one renderer, one table, one aligned edge

Three guarantees on the Pro Compare page (block `listora-pro/comparison`, shortcode `[listora_compare]`):

- **One renderer.** The shortcode delegates to the block (`Comparison::render_comparison_page()`), so the two entry points cannot drift.
- **One table.** Listings of different types (a Restaurant and a Hotel) are one side-by-side table with the field union, not one stacked table per type.
- **One aligned edge.** Thumbnail, title and rating in each header cell share one left edge with the value cells below.

Roles: the table is public and role-independent (anonymous, member, owner, editor, admin see the same markup for published listings); the ladder is anonymous plus one logged-in member, admin as control. Compare must be enabled (Pro Settings > Comparison, `Comparison::is_enabled()`); when it is off the block renders the empty state "Listing comparison is currently disabled."

## Setup

- Compare page: the "Compare Listings" page (`wb_listora_pro_compare_page_id` = 9), `$SITE_URL/compare-listings/`; selection is the `?compare=ID1,ID2` query (up to 4 IDs; unpublished or non-listing IDs are pruned server-side).
- Two published listings of different types. QA site: `38` (restaurant) and `220` (hotel) -> `$SITE_URL/compare-listings/?compare=38,220`.
- A page containing `[listora_compare]` (any page; the QA Compare page itself uses the block `<!-- wp:listora-pro/comparison /-->`).

## Steps

### 1. Different types give ONE table
- **Action**: open `$SITE_URL/compare-listings/?compare=38,220`.
- **Expect**: exactly one `table.listora-comparison-table[data-listora-compare-table]`; two `th.listora-comparison-table__listing-col` header cells (one per listing); rows present on BOTH listings come first, partially-present rows follow (on the QA site: Phone, Email, Website first, then Cuisine ... Booking URL, then Features); `—` (`.listora-comparison-table__na`, `aria-label="Not available"`) where a field does not apply; rows empty for every listing are omitted.
- **Fail**: two tables (the old per-type grouping).

### 2. Shortcode and block are the same renderer
- **Action**: put `[listora_compare]` on a page and open it with the same `?compare=38,220`. Compare counts of `<table class="listora-comparison-table`, `data-listora-compare-table` and `listora-compare-row` against step 1.
- **Expect**: equal counts (1 table, 1 marker, 16 rows on the QA data). `data-listora-compare-table` is what `view.js` uses to hide the server table before the client redraws; without it removing a column leaves a stale table under the new one.

### 3. One listing selected: the same rich empty card from both entry points
- **Action**: open `?compare=38` on the block page and on the shortcode page.
- **Expect**: `.listora-compare-empty-card` with the title "Compare Listings Side-by-Side", the description, and a **Browse Listings** button that links to the Directory (not the site home). Both entry points identical.
- **Fail**: a bare `<p>` (the old shortcode empty state).

### 4. Alignment (header vs values)
- **Action**: at 1440 and 390 measure `getBoundingClientRect().left` of the thumbnail (`.listora-comparison-table__thumb`), title link (`.listora-comparison-table__title-link`) and rating (`.listora-comparison-table__rating`) inside one header cell, then the first value cell beneath.
- **Expect**: identical left offsets, matching the value cells. Repeat with `dir="rtl"` (`text-align: start`, `margin-inline`): same edge, mirrored.
- **Mobile 390**: the table scrolls inside its own wrapper, the page has no horizontal scroll, "Remove" / "View Listing" buttons are >= 40px tall.

### 5. Opt back in to per-type grouping
- **Action**: add `add_filter( 'wb_listora_pro_compare_group_by_type', '__return_true' );` (mu-plugin) and reload step 1.
- **Expect**: the grouped rendering (one table per type) returns; remove the filter and one table returns. This is the positive control for "one table" in step 1: it proves the grouping code is reachable and was switched off by default, not missing.

### 6. Positive control for the empty state
- **Control**: step 1 with two IDs shows the table; step 3 with one ID shows the card. Both on the same page proves the empty card appears only below two selected listings.
- **Invalid IDs**: `?compare=38,999999` shows the empty card (one valid ID left) and the URL is rewritten to drop the invalid ID on reload.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Two tables | grouping on by default | `wb-listora-pro/blocks/comparison/render.php:295` (`wb_listora_pro_compare_group_by_type`) |
| Shortcode markup differs from block | shortcode stopped delegating | `wb-listora-pro/includes/features/class-comparison.php:304` (`render_comparison_page`) |
| Stale table after removing a column | marker missing | `wb-listora-pro/blocks/comparison/render.php:331` (`data-listora-compare-table`) |
| Bare `<p>` empty state | shortcode empty branch re-added | `wb-listora-pro/blocks/comparison/render.php:150-172` |
| Ragged header edge | centring rules back | `wb-listora-pro/blocks/comparison/style.css:68-80`, `:254` |

## Not verified

Steps 1, 2 and 3 were run live (curl on the page with `?compare=38,220` and `?compare=38`; `do_shortcode` vs `render_block` counts via wp-cli: 1 table, 1 marker, 16 rows on both). Step 4 alignment, the 390 layout and RTL, step 5 (filter on) and the invalid-ID rewrite were NOT run in a browser.
