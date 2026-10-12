---
journey: categories-block
plugin: wb-listora
priority: normal
roles: [anonymous, subscriber, admin]
covers: [listing-categories-block, hide-empty-categories, type-label-on-tiles, view-all-grouped, category-count-accuracy, category-term-page, phone-two-columns]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "A page hosting the listora/listing-categories block: the Home page `$SITE_URL/` (block `listing-categories`, uniqueId e7b43952)"
  - "At least 5 categories with published listings and some categories with none"
estimated_runtime_minutes: 3
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Browse by category block

A visitor sees the most-used categories as tiles (name, listing type, count), can open "View all N categories" to see every non-empty category grouped by listing type, and a tile leads to the Directory filtered to that category. Empty categories are hidden by default.

Verified via: Playwright (1440 / 390) and curl; counts cross-checked in the DB with WP-CLI.

## Setup

- `$CAT_URL = $SITE_URL/` (Home). Block markup: `div.listora-categories.wp-block-listora-listing-categories` with inline `--listora-cat-columns: 4`.
- Block attribute `hideEmpty` defaults to true; filter `wb_listora_categories_hide_empty` overrides it per site; `wb_listora_categories_show_type_label` controls the type label.

## Steps

### 1. Tiles render
- **Action**: open `$CAT_URL` logged out; scroll to the categories block.
- **Expect**: `.listora-categories__grid--top[role="list"]` with up to 12 `a.listora-categories__card[role="listitem"]`, ordered by count (highest first: "Casual", "Business", "Fine Dining", ... at verification). Each tile has `.listora-categories__name`, `.listora-categories__type` (the listing type the category belongs to: "Restaurant", "Hotel", "Place"), `.listora-categories__count` ("6 listings"), an icon wrap, and `aria-label="<Name>, <Type>"` (for example `Casual, Restaurant`). Colours come from `--cat-color`.
- **On fail**: `blocks/listing-categories/render.php:54`, `templates/blocks/listing-categories/categories.php`, `category-card.php`

### 2. Counts match the database (data flow)
- **Action**: for two tiles (the top one and one with a small count) run: `SELECT COUNT(DISTINCT p.ID) FROM wp_term_relationships tr JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id JOIN wp_terms t ON t.term_id=tt.term_id JOIN wp_posts p ON p.ID=tr.object_id WHERE tt.taxonomy='listora_listing_cat' AND t.slug='<slug>' AND p.post_status='publish' AND p.post_type='listora_listing'` (taxonomy is `listora_listing_cat`, not `category`).
- **Expect**: equals the tile count (casual 6, business 6, fine-dining 5 at verification), and equals `GET /wp-json/listora/v1/search?category=<slug>&per_page=1` `total` and the count on the category page (step 4). Note: tiles read the stored term count; a term whose stored count differs from the published count (1 of 120 on this site) shows the stored number.

### 3. Empty categories are hidden
- **Action**: compare tiles + "View all" list with the taxonomy.
- **Expect**: this site has 120 `listora_listing_cat` terms and 79 have no published listing; the block shows 41 (the "View all 41 categories" summary). No tile reads "0 listings". A category with zero published listings (for example `home-garden`, `finance`) has no tile anywhere in the block.
- **POSITIVE CONTROL**: publish one listing in an empty category (or set `hideEmpty` false in the block settings) and reload: that category appears, with its count.

### 4. Tile click goes to the filtered Directory
- **Action**: click the "Casual" tile (anchor `href="$SITE_URL/listing-category/casual/"`).
- **Expect**: URL `/listing-category/casual/`, `<h1>` "Casual", the Directory grid filtered to that category, `.listora-grid__count` "Showing 1-6 of 6" (same number as the tile).

### 5. "View all N categories"
- **Action**: click `summary.listora-categories__toggle` ("View all 41 categories") inside `details.listora-categories__all`.
- **Expect**: `details` gets `open`; the summary now reads "Show fewer"; the top grid is swapped out so nothing shows twice; `.listora-categories__group` sections titled by listing type ("Business", "Education", "Healthcare", "Hotel", "Place", "Real Estate", "Restaurant" at verification) each with a `.listora-categories__grid` of tiles; unpinned tiles inside a group show no type label. Clicking the summary again closes it. No script is involved (native `<details>`).

### 6. Extension hook
- **Action**: a site mu-plugin adds `add_filter( 'wb_listora_category_card_data', function ( $data, $cat ) { $data['name'] .= ' *'; return $data; }, 10, 2 );`, reload.
- **Expect**: every tile name gains " *" and nothing fatals; a listener that returns a non-array is ignored (`templates/blocks/listing-categories/categories.php:77-85`).

### 7. ROLE LADDER
| Actor | Action | Expect |
|---|---|---|
| Logged out | steps 1-5 | as above |
| Subscriber / admin (control) | steps 1-2 | same tiles and counts (the block has no per-role output); counts never include drafts or pending listings for anyone |
| Listing owner with a pending listing in an empty category | reload | that category stays hidden until the listing is published |

### 8. Phone layout (390)
- **Action**: open `$CAT_URL` at 390x844.
- **Expect**: two columns (`grid-template-columns` computed `166.5px 166.5px`), tiles about 167x176; `scrollWidth <= 390`; the "View all" summary is 44px high and sits 20px below the grid; opening it shows groups without horizontal scroll.

## Pass criteria

1. Tiles show name, listing type and count, ordered by count.
2. Counts equal the published-listing count.
3. Empty categories are hidden; one published listing makes a category appear.
4. A tile opens `/listing-category/<slug>/` with the same count.
5. "View all N categories" groups by type, toggles open/closed with no JS.
6. Two columns on phones, no horizontal scroll.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Counts always 0 / stale | term counts not recounted | `blocks/listing-categories/render.php:54`, term count update on publish |
| Empty categories visible | `hideEmpty` / filter | `blocks/listing-categories/render.php:18,31` |
| No type label | label filter or no owner type | `blocks/listing-categories/render.php:157`, `templates/blocks/listing-categories/categories.php:70` |
| Tile link 404 / not filtered | rewrite rules / term archive routing | `includes/core/class-term-archives.php:47` |
| Filter not applied | hook not reached | `templates/blocks/listing-categories/categories.php:77` |
| One column on phones | CSS | `blocks/listing-categories/style.css` |
