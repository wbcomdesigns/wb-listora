---
journey: home-screen-ux
plugin: wb-listora
priority: high
roles: [anonymous, member, admin]
covers: [featured-see-all-directory, categories-block-spacing, type-chip-arrows, categories-two-columns-mobile, categories-type-label]
prerequisites:
  - "Site reachable at http://wb-listora.local with a page holding listing-search, listing-categories and listing-featured: the home page (page 281) has all three plus a grid"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Home screen: See all, block spacing, type-chip arrows, phone category grid

Found by the 2026-10-10 frontend UX audit. Verified via: curl + CSS read of the home page 2026-10-12; measured margins, arrow hidden states and phone grid columns need a browser at 390 and were not run.

## Steps

### 1. Desktop 1440, guest
- **Action**: read each `a.listora-featured__see-all` href.
- **Expect**: the Directory page URL with the block's sort and the featured filter: `http://wb-listora.local/listings/?listora_sort=featured&listora_featured=1` (2.0.0 adds `listora_featured=1` so See all lists EVERY featured listing; `listora_type` is added when the block is pinned to a type). Never `/listing/`, which now 301s to `/listings/`.
- **Action**: measure `.listora-categories` and `.listora-featured` `margin-block`.
- **Expect**: both `var(--listora-space-6)` (`blocks/listing-categories/style.css:5-6`, `blocks/listing-featured/style.css:4-5`), not 0.

### 2. Phone 390, guest
- **Action**: read `hidden` on the two `button[data-listora-scroll]` ("Scroll listing types back" / "forward") at scroll start, middle and end of `.listora-search__type-tabs`.
- **Expect**: start = back hidden / next shown; middle = both shown; end = back shown / next hidden (script `src/blocks/listing-search/view.js:37` `initTypeTabsScroll()`); each 40x40.
- **Action**: read `grid-template-columns` of `.listora-categories__grid`.
- **Expect**: two columns at <= 480px (and <= 768px), no card clipped, no horizontal page scroll (`blocks/listing-categories/style.css:197` and `:204`).
- **Action**: unpinned categories block: each `a.listora-categories__card` has a `.listora-categories__type` and `aria-label`.
- **Expect**: tile names its type (verified on the home page: Restaurant, Hotel, Business, Real Estate), `aria-label` reads "<Category>, <Type>" (`templates/blocks/listing-categories/category-card.php:36`); only top-level terms; empty categories are hidden by default (`wb_listora_categories_hide_empty`). A block pinned to one type, or `add_filter( 'wb_listora_categories_show_type_label', '__return_false' )`, shows no type line (POSITIVE CONTROL: compare both). The expanded "View all" list shows none; its group headings name the type.

### 3. Role ladder
- Public page: anonymous, member, owner, editor and admin see the same blocks. Admin is only the control for toggling the filter in step 2.

### 4. Large set
- Categories block with 2,811 listings behind it: only categories with listings show; "View all" expands the rest (`details.listora-categories__all`).

## Pass criteria

1. "See all" lands on the Directory page in the block's sort with the featured filter.
2. No arrow shows where it cannot scroll.
3. Category tiles are two per row on phones.
4. Unpinned category tiles name their listing type; pinned ones do not.

## Fail diagnostics

| Symptom | File |
|---|---|
| See all opens a blog loop | `blocks/listing-featured/render.php:86-95` (`listora_featured` at :91) |
| Tile has no type / wrong aria | `blocks/listing-categories/render.php`, `templates/blocks/listing-categories/category-card.php:36-57` |
| One tile per row on phones | `blocks/listing-categories/style.css:197,204` |
| Arrow over the "All" chip | `src/blocks/listing-search/view.js:37` |

## Not verified

Computed margins, arrow hidden states and 390px column count in a browser.
