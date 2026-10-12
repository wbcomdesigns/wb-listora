---
journey: categories-block
plugin: wb-listora
priority: normal
roles: [guest, other, owner, admin]
covers: [listing-categories-block, hide-empty-categories, type-label-on-tiles, view-all-grouped, category-count-accuracy, category-term-page, phone-two-columns, categories-hide-empty-filter]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A page hosting the `listora/listing-categories` block (home page or a QA page; note its `uniqueId`)"
  - "At least 5 categories with published listings and some categories with none"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: [ora:regression/quick-wins-2026-10.md]
---

# Browse by category: tiles, counts, hidden empties, "View all" groups

## Why
A visitor browses the most-used categories as tiles (name, listing type, count), opens "View all N categories" for every non-empty category grouped by listing type, and a tile leads to the Directory filtered to that category. Tiles reading "0 listings" or a wrong count send visitors to empty pages and erode trust.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `CAT_URL` = the page hosting the block; the block is `div.listora-categories.wp-block-listora-listing-categories` with inline `--listora-cat-columns`.
- Block attribute `hideEmpty` defaults to true; filter `wb_listora_categories_hide_empty` overrides per site; `wb_listora_categories_show_type_label` controls the type label.
- Derive counts, do not hardcode: `TOTAL_TERMS` = `wp term list listora_listing_cat --format=count`; `NONEMPTY` = number of terms with at least one published listing (SQL in step 2); `{cat}` = the top tile's slug.

## Steps

### 1. Tiles render
- **Action**: open `CAT_URL` logged out; scroll to the block.
- **Expect**: `.listora-categories__grid--top[role="list"]` with up to 12 `a.listora-categories__card[role="listitem"]`, ordered by count (highest first). Each tile has `.listora-categories__name`, `.listora-categories__type` (the type the category belongs to), `.listora-categories__count` ("N listings"), an icon wrap, and `aria-label="<Name>, <Type>"`. Colours come from `--cat-color`.

### 2. Counts match the database
- **Action**: for the top tile and one with a small count run `SELECT COUNT(DISTINCT p.ID) FROM wp_term_relationships tr JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id=tr.term_taxonomy_id JOIN wp_terms t ON t.term_id=tt.term_id JOIN wp_posts p ON p.ID=tr.object_id WHERE tt.taxonomy='listora_listing_cat' AND t.slug='<slug>' AND p.post_status='publish' AND p.post_type='listora_listing'` (taxonomy is `listora_listing_cat`, not `category`).
- **Expect**: equals the tile count, `GET /wp-json/listora/v1/search?category=<slug>&per_page=1` `total`, and the category page count (step 4). Tiles read the stored term count; a term whose stored count differs from the published count shows the stored number (record it).

### 3. Empty categories are hidden, and the filter restores them
- **Action**: compare tiles + "View all" list with the taxonomy.
- **Expect**: the block shows exactly `NONEMPTY` categories ("View all NONEMPTY categories" summary, with "Show fewer" `__toggle-less` when open); no tile reads "0 listings"; a category with zero published listings has no tile anywhere in the block; groups (`.listora-categories__group-title`) are per listing type and none consists only of empty categories; each unpinned tile names its type (`.listora-categories__type`).
- **Positive control**: `add_filter( 'wb_listora_categories_hide_empty', '__return_false' )` (or `hideEmpty` false in the block settings) -> the toggle reads "View all TOTAL_TERMS categories" and `TOTAL_TERMS - NONEMPTY` "0 listings" tiles appear; publish one listing in an empty category and reload with the filter off: it appears with its count. Remove the filter afterwards.

### 4. Tile click goes to the filtered Directory
- **Action**: click the top tile (`href="$SITE_URL/listing-category/{cat}/"`).
- **Expect**: URL `/listing-category/{cat}/`, `<h1>` the category name, the Directory grid filtered to it, `.listora-grid__count` "Showing 1-N of N" with the same N as the tile.

### 5. "View all N categories"
- **Action**: click `summary.listora-categories__toggle` inside `details.listora-categories__all`.
- **Expect**: `details` gets `open`; the summary reads "Show fewer"; the top grid is swapped out so nothing shows twice; `.listora-categories__group` sections titled by listing type, each with a `.listora-categories__grid`; unpinned tiles inside a group show no type label; clicking again closes it. No script involved (native `<details>`).

### 6. Extension hook
- **Action**: mu-plugin `add_filter( 'wb_listora_category_card_data', function ( $data, $cat ) { $data['name'] .= ' *'; return $data; }, 10, 2 );`
- **Expect**: every tile name gains " *" and nothing fatals; a listener that returning a non-array is ignored.

### 7. Phone layout (390)
- **Action**: open `CAT_URL` at 390x844.
- **Expect**: two columns (computed `grid-template-columns` two equal tracks), no horizontal scroll (`scrollWidth <= 390`); the "View all" summary is >= 44px high and spaced from the next block; opening it shows groups without horizontal scroll.

### 8. Block attributes at the boundary
- **Action**: set the block's `columns`/`limit` to 0, 1, 12 and a huge value (see `system/block-attribute-trust-boundary.md` for the fatal matrix).
- **Expect**: a clamped 1..12 column count; `limit` honoured; N=0 non-empty categories renders the block's empty state, N=1 one tile. Not verified live.

## Role ladder
| Actor | Action | Expect |
|---|---|---|
| {guest} | steps 1-7 | as above |
| {other} / {admin} (control) | steps 1-2 | same tiles and counts (no per-role output); counts never include drafts or pending listings |
| {owner} with a pending listing in an empty category | reload | that category stays hidden until the listing is published |

## Pass criteria
1. Tiles show name, type and count, ordered by count; counts equal the published-listing count.
2. Empty categories are hidden; the filter or a published listing makes one appear.
3. A tile opens `/listing-category/<slug>/` with the same count.
4. "View all N categories" groups by type and toggles with no JS.
5. Two columns on phones, no horizontal scroll.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Counts always 0 / stale | term counts not recounted | `blocks/listing-categories/render.php` (~:54) |
| Empty categories visible | `hideEmpty` / filter | `blocks/listing-categories/render.php` (~:18,31,34-45) |
| No type label | label filter or no owner type | `render.php` (~:157), `templates/blocks/listing-categories/categories.php` (~:70) |
| Tile link 404 / not filtered | rewrite / term archive routing | `includes/core/class-term-archives.php` (~:47) |
| Filter not applied | hook not reached | `templates/blocks/listing-categories/categories.php` (~:77-85) |
| One column on phones | CSS | `blocks/listing-categories/style.css` |

## Not verified
Counts verified with Playwright (1440 / 390), curl and WP-CLI on 2026-10-12; step 8 not run; dark mode and unbridged theme not run.

## Cleanup
Remove the mu-plugin and the hide-empty filter; unpublish any listing added for the positive control.
