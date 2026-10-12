---
journey: home-screen-ux
plugin: wb-listora
priority: high
roles: [guest, member, admin]
covers: [categories-block-spacing, type-chip-arrows, categories-two-columns-mobile, categories-type-label]
prerequisites:
  - "Site reachable at $SITE_URL with a page holding listing-search, listing-categories and listing-featured ($HOME_URL, e.g. the front page)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - "ora:regression/home-screen-ux.md"
site_profile: required
---

# Home screen: block spacing, type-chip arrows, and a two-column category grid on phones

## Why
Found by the 2026-10-10 frontend UX audit: stacked blocks touching each other, a scroll arrow sitting over the "All" chip, and one-per-row category tiles on phones make the home page feel broken. The Featured "See all" target is owned by `featured-filter-and-see-all`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `{type}` pins the categories block to one type for the control in step 3.

## Steps

### 1. Block spacing (guest, 1440)
- **Action**: measure `.listora-categories` and `.listora-featured` `margin-block`.
- **Expect**: both `var(--listora-space-6)` (`blocks/listing-categories/style.css:5-6`, `blocks/listing-featured/style.css:4-5`), not 0.

### 2. Type-chip arrows only where they can scroll (phone 390)
- **Action**: read `hidden` on the two `button[data-listora-scroll]` ("Scroll listing types back" / "forward") at scroll start, middle and end of `.listora-search__type-tabs`.
- **Expect**: start = back hidden / next shown; middle = both shown; end = back shown / next hidden (`src/blocks/listing-search/view.js:37` `initTypeTabsScroll()`); each 40x40.

### 3. Category tiles name their type
- **Action**: on the unpinned categories block read each `a.listora-categories__card`.
- **Expect**: a `.listora-categories__type` line naming the type and `aria-label` "<Category>, <Type>" (`templates/blocks/listing-categories/category-card.php:36`); only top-level terms; empty categories hidden by default (`wb_listora_categories_hide_empty`). The expanded "View all" list (`details.listora-categories__all`) shows no type line; its group headings name the type.
- **Positive control**: a block pinned to `{type}`, or `add_filter( 'wb_listora_categories_show_type_label', '__return_false' )`, shows no type line.

### 4. Two columns on phones
- **Action**: read `grid-template-columns` of `.listora-categories__grid` at 390 and 820.
- **Expect**: two columns at <= 480px and <= 768px, no card clipped, no horizontal page scroll (`blocks/listing-categories/style.css:197`, `:204`).

### 5. Scale and N=0/1/>1
- **Action**: with 500 QA categories and with 0 and 1 categories having listings.
- **Expect**: only categories with listings show; "View all" expands the rest; 0 -> the block renders its shared empty state; 1 -> one tile, no stretched layout. Not verified live.

### 6. Role ladder
- Public page: guest, member, owner, editor and admin see the same blocks; admin is only the control for toggling the filter in step 3.

## Role ladder
See step 6.

## Pass criteria
1. Block margins are the spacing token.
2. No arrow shows where it cannot scroll.
3. Category tiles are two per row on phones.
4. Unpinned tiles name their listing type; pinned ones do not.

## Fail diagnostics
| Symptom | File |
|---|---|
| Tile has no type / wrong aria | `blocks/listing-categories/render.php`, `templates/blocks/listing-categories/category-card.php:36-57` |
| One tile per row on phones | `blocks/listing-categories/style.css:197,204` |
| Arrow over the "All" chip | `src/blocks/listing-search/view.js:37` |
| Zero margin between blocks | `blocks/listing-categories/style.css:5-6`, `blocks/listing-featured/style.css:4-5` |

## Not verified
Computed margins, arrow hidden states and 390px column count in a browser; step 5.

## Cleanup
Remove QA categories and the filter mu-plugin.
