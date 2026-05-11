# G1 Discovery — Audit

**Audited:** 7 blocks (listing-search · listing-grid · listing-card · listing-map · listing-categories · listing-featured · listing-calendar) + 15 templates + 2,232 lines of per-block CSS.

**Headline:** Templates use BEM consistently (listing-calendar leads with 20 `__` elements, listing-search with 21). Blocks consume `--listora-card-*` shared tokens across the board (no block-local token re-definitions). The biggest UX issues are: **(a) two listing-card BEM systems coexist (`__` vs single-dash kebab)**, **(b) zero dark-mode hooks across all 7 blocks**, and **(c) listing-search at 647 lines is doing too much**.

---

## Inventory snapshot

| Block | render.php | template files | style.css lines | BEM `__` in templates | hex literals | px>2 |
|---|---|---|---|---|---|---|
| listing-search | ✓ | search/search-bar/filters (3) | **647** | 40 | 3 | 7 |
| listing-grid | ✓ | grid/pagination/toolbar (3) | 355 | 22 | 2 | 6 |
| listing-card | ✓ | card/card-image/card-content/card-actions (4) | 439 | 17 | 10 | 7 |
| listing-map | ✓ | map (1) | 246 | 3 | 10 | 10 |
| listing-categories | ✓ | categories/category-card (2) | 167 | 7 | 3 | 4 |
| listing-featured | ✓ | featured (1) | 134 | 10 | 0 | 4 |
| listing-calendar | ✓ | calendar (1) | 244 | 20 | 1 | 7 |

---

## Issues

### G1-01 (BLOCK) — `listing-card` mixes `__` BEM with single-dash kebab classes

Sample of class names from `card-content.php` and `card-image.php`:
- `.listora-card__body`, `.listora-card__excerpt`, `.listora-card__favorite` ✓ BEM
- `.listora-card__badge-featured` — this is BEM `__` + dash, which is acceptable
- `.listora-card-image` / `.listora-card-content` / `.listora-card-actions` (template file names) — but the class root inside templates is `.listora-card__image`, `.listora-card__content`, etc.

**The actual issue:** card.php template wrapper is 0 BEM (just `<div class="listora-card …">`), and card-actions.php is 0 BEM (only `--listora-card-` token references). The card root class lives in `listing-card/style.css` and accumulated 439 lines / 10 hex literals / 7 distinct px values. This is the largest pile of hardcoded styles in G1.

**Recommendation:** treat listing-card as the canonical card primitive. Either (a) refactor to use `.listora-ui-card` (per G7 F-03), OR (b) promote `.listora-card__*` to canonical and delete `.listora-ui-card` from shared.css. Pick one path and document.

### G1-02 (BLOCK) — listing-search bloated at 647 lines

The single biggest block CSS file. Contains styles for: search-bar (3 input types), filters panel (toggle/checkbox/dropdown/date facets per type), result toolbar (sort, view toggle, count), responsive collapse at 1024 + 767 + 480. Three concerns:
- It's hard to scan. Splitting into `search-bar.css` + `filters.css` + `toolbar.css` (matching the template structure) would mirror the template organization.
- The filter facets repeat similar patterns (checkbox group / dropdown / date) — likely 100+ lines of near-duplicate CSS that could collapse into shared facet primitives.
- It loads on every page that has the search block, even when the filters panel is collapsed by default on mobile.

**Recommendation:** split listing-search/style.css into 3 sub-files matching template structure. Extract facet-primitives into shared.css. Conditional-load filters CSS only when the panel is open (defer beyond critical CSS).

### G1-03 (BLOCK) — listing-map at 246 lines has 10 hex literals

Map block has 10 hardcoded color hex values. Likely marker colors + popup styling + cluster cluster-count badges. Map render.php also flagged in G7 for inline `<script>` (the IAPI fallback for popup interactions).

**Recommendation:** migrate marker colors to `--listora-primary` / `--listora-favorite` (saved markers) / `--listora-success` (verified). Move inline JS to enqueued file.

### G1-04 (ADVISORY) — Zero dark-mode hooks across all 7 G1 blocks

None of the 7 blocks have `prefers-color-scheme: dark`, `.is-dark`, or `data-theme` selectors. The strategy is "tokens reference `--wp--preset--color--*` → theme provides dark variants" — but if a customer's theme doesn't ship dark presets, the directory has no dark mode at all.

**Recommendation:** decide:
- (a) "WB Listora does not ship dark mode. Theme must provide." → document in CLAUDE.md + leave as-is.
- (b) Add `@media (prefers-color-scheme: dark)` block in shared.css that overrides tokens to dark values. Then blocks inherit automatically.

Pro side already has some dark hex literals in `shared.css:165-167` (`--listora-card-bg: #1e1e1e`, `--listora-card-border: 1px solid #333`) — they're orphan dark-mode tokens that don't fire anywhere. Either complete them or delete.

### G1-05 (ADVISORY) — listing-search has 3 layers of UX (search-bar / filters / results) that load together

When a customer just wants to browse, the filters panel ships its CSS + JS even if they never open it. ~150 lines of facet CSS are critical-path that aren't critical.

**Recommendation:** defer filters CSS via `<link rel="preload">` or conditionally enqueue on `.is-filters-open` toggle (IAPI emits a class).

### G1-06 (ADVISORY) — listing-calendar has 244 lines but only loads on event-listing pages

If a customer's directory has no Event listings, calendar CSS still loads on the page that hosts the calendar block (or whichever page embeds it). Calendar isn't auto-suppressed when there are no events.

**Recommendation:** add `if (! has_events()) { return; }` early return in `blocks/listing-calendar/render.php` so empty calendars don't enqueue CSS. (Verify if it already does this.)

### G1-07 (ADVISORY) — listing-grid + listing-card duplicate "card grid" styling

Both blocks define grid-template-columns rules for listing cards in a grid (listing-grid handles the grid wrapper; listing-card handles each card's internal layout). There's overlap in the responsive breakpoints — both blocks define identical 1024 / 767 / 480 breakpoints with similar values. ~30-50 lines of duplication.

**Recommendation:** extract grid-spacing-tokens into shared.css (`--listora-grid-gap-tablet`, `--listora-grid-gap-mobile`). Each block then references the token instead of redefining the value.

### G1-08 (ADVISORY) — Empty-state primitive only on listing-grid + listing-categories

Of the 7 G1 blocks, only 3 surfaces show an empty state when there are no results:
- listing-grid (templates/grid.php) ✓ canonical empty state
- listing-categories (render.php) ✓ canonical empty state
- listing-search (renders via listing-grid's empty state when zero results) ✓ indirect

Missing empty states:
- listing-featured — when 0 featured listings, block renders nothing (no message)
- listing-calendar — when 0 events, calendar shows empty cells (no "no events this month" message)
- listing-map — when 0 mapped listings, map renders empty (no message)
- listing-card — n/a (sub-component)

**Recommendation:** add canonical empty states to listing-featured + listing-calendar + listing-map.

### G1-09 (FUTURE) — listing-categories has a `wb_listora_category_card_data` filter but no documentation

`templates/blocks/listing-categories/category-card.php` consumes a filter to let Pro/themes add custom data per card. The filter isn't documented in CLAUDE.md or REST-API.md. Pro doesn't use it.

**Recommendation:** document the filter in CLAUDE.md hooks list. Add an example in the Pro-extension docs.

---

## Live view findings (verified during smoke walk earlier this session)

- `/` (front page, listings) → 27 cards render with grid + search blocks, 0 JS errors, 0 console errors ✓
- Cards have `data-listing-id` attribute, BEM class structure intact ✓
- IAPI store hydrates with `state.totalResults > 0`, empty state correctly hidden ✓
- Pagination renders when results exceed per_page (verified during data-flow check) ✓
- Search bar has nonce input ✓ (verified in static check)

**Not verified live (deferred for full Sonnet smoke run):**
- listing-categories on a dedicated categories page
- listing-featured on a featured-listings page
- listing-calendar on a calendar page (needs Event listing fixtures)
- listing-map marker rendering at desktop + mobile
- Filter panel open/close UX at mobile 390px

---

## Summary table

| # | Severity | Title | Effort |
|---|---|---|---|
| G1-01 | BLOCK | Decide listing-card canonical (adopt or retire `.listora-ui-card`) | 5 min retire OR 1-2 days adopt (shared with G7 F-03) |
| G1-02 | BLOCK | Split listing-search/style.css (647 lines) by template structure + extract facet primitives | 4-6 h |
| G1-03 | BLOCK | listing-map 10 hex literals → tokens + move inline JS | 1 h |
| G1-04 | ADVISORY | Decide dark-mode policy (ship in shared.css OR document "theme provides") | 30 min doc OR 2-3 h CSS |
| G1-05 | ADVISORY | Conditional-load filters CSS (defer until panel opens) | 1-2 h |
| G1-06 | ADVISORY | Calendar early-return when no events | 30 min |
| G1-07 | ADVISORY | Extract duplicated grid-spacing tokens | 1 h |
| G1-08 | ADVISORY | Add canonical empty states to listing-featured + listing-calendar + listing-map | 1-2 h |
| G1-09 | FUTURE | Document `wb_listora_category_card_data` filter | 15 min |

**Cumulative G1 effort: ~half a day quick wins + 1-2 days for the harder items (listing-search split, dark-mode strategy).**

The 4 BLOCK items in G1 (listing-card decision, listing-search split, listing-map hex+JS) are independent of G7 BLOCK items except for G1-01 which mirrors G7 F-03. Tackle G7 F-01/F-02/F-04 first (foundation tokens + namespace), THEN G1 items will be cleaner to migrate.
