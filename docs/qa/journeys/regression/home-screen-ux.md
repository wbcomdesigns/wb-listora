---
journey: home-screen-ux
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [featured-see-all-directory, categories-block-spacing, type-chip-arrows, categories-two-columns-mobile, categories-type-label]
prerequisites:
  - "Site reachable at $SITE_URL with a page holding listing-search, listing-categories and listing-featured (sort featured and newest)"
estimated_runtime_minutes: 5
---

# Home screen: See all, block spacing, type-chip arrows, phone category grid

Found by the 2026-10-10 frontend UX audit on the model site. "See all" on the featured block opened the bare CPT archive, which the theme rendered as blog posts. The categories block had no outer margin, so "View all" touched the next heading. At 390px the back arrow sat on top of the "All" chip, and the category grid went one tile per row (12 tiles, about 2,200px of scroll).

## Steps

### 1. Desktop 1440, guest
- **Action**: open the home page; read the href of each `.listora-featured__see-all`.
- **Expect**: the Directory page URL with `listora_sort=<the block's sort>` (and `listora_type` when the block is pinned to a type), never `/listing/`.
- **Action**: measure `.listora-categories` margin-bottom.
- **Expect**: equal to `.listora-featured` (the `--listora-space-6` rhythm), not 0.

### 2. Phone 390, guest
- **Action**: read `hidden` on the two `[data-listora-scroll]` buttons at scroll start, middle and end of `.listora-search__type-tabs`.
- **Expect**: start = back hidden / next shown; middle = both shown; end = back shown / next hidden. Both 40x40.
- **Action**: read `grid-template-columns` of `.listora-categories__grid`.
- **Expect**: two columns, no card clipping, no page overflow.
- **Action**: on the unpinned categories block, read each top-grid tile's `.listora-categories__type` and `aria-label`.
- **Expect**: every tile names its listing type ("Casual" / "Restaurant"), the aria-label reads "Casual, Restaurant", and the grid holds top-level terms only. A block pinned to one type, or `add_filter( 'wb_listora_categories_show_type_label', '__return_false' )`, shows no type line. The expanded "View all" list shows none either; its group headings name the type.
- **On fail**: `blocks/listing-categories/render.php` (type label + top-level), `templates/blocks/listing-categories/category-card.php`, `blocks/listing-featured/render.php` (See all), `blocks/listing-categories/style.css`, `src/blocks/listing-search/view.js` `initTypeTabsScroll()`.

## Pass criteria

ALL of the following hold:
1. "See all" lands on the Directory page in the block's sort.
2. No arrow shows where it cannot scroll.
3. Category tiles are two per row on phones.
4. Unpinned category tiles name their listing type; pinned ones do not.
