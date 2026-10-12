---
journey: directory-empty-states
group: directory-search
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [guest, owner, editor, admin]
covers: [listing-grid-empty-state, iapi-show-empty-state-getter, server-render-hydration, term-pages, listing-type-control, block-editor-type-picker, card-10217484053]
prerequisites:
  - "A listing type with zero published listings ($EMPTY_TYPE) and one with listings ({type})"
  - "Admin able to create and delete a temporary page"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/empty-state-server-rendered.md
  - ora:regression/grid-pinned-type-empty-state.md
fails_on: unrecorded
site_profile: required
---

# A zero-result grid explains itself on first paint, after a live filter, and when pinned to a type

## Why
The empty card `.listora-card.listora-card--empty.listora-grid__empty[role="status"]` (`templates/blocks/listing-grid/grid.php:117-142`) was hidden after hydration, and a grid pinned to an empty or mistyped type told visitors to "adjust your filters" when they had none.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Temporary pages created with `wp post create --post_type=page --post_status=publish --post_title="QA grid empty" --post_content='<!-- wp:listora/listing-grid {"listingType":"$VALUE"} /-->' --porcelain`.
- `$NOMATCH` = a keyword matching nothing. The state `showEmptyState` must be true when `totalResults === 0` regardless of `hasSearched`.

## Steps

### 1. First paint of a 0-result URL
- **Action**: GET `$SITE_URL/listing-type/$EMPTY_TYPE/` and `$SITE_URL{directory}?listora_keyword=$NOMATCH`.
- **Expect**: interactivity state `totalResults` 0; the empty card is present WITHOUT `is-hidden`; title "No listings found", description "Try adjusting your filters, or be the first to add a listing.", button "Clear All Filters" (`actions.clearAllFilters`). `/listing-type/$EMPTY_TYPE/` has `<h1>` = the type name and a breadcrumb.

### 2. Positive control: results present hide it
- **Action**: GET `/listing-type/{type}/`.
- **Expect**: the same card is in the DOM WITH `is-hidden`; the class is the only difference.

### 3. Getter after hydration (browser)
- **Action**: `getComputedStyle(document.querySelector('.listora-grid__empty')).display` and the store's `showEmptyState`.
- **Expect**: `display !== 'none'` and `true`.

### 4. Clear All Filters
- **Action**: from `?listora_keyword=$NOMATCH` click "Clear All Filters".
- **Expect**: keyword stripped, `total` results equal to `GET /search` `total`, card hidden again.

### 5. Live filter to 0
- **Action**: on the Directory type `$NOMATCH` into `#listora-keyword`, Enter.
- **Expect**: count drops to 0 and the card appears.

### 6. The editor picks a type, it does not spell one
- **Action**: admin adds a Listing Grid to a new page and opens the sidebar.
- **Expect**: "Listing Type" is a select whose first option is "All types" (help "Leave as All types to show every listing."), then every type by NAME, no free-text slug; zero console errors. Same control on Search, Map, Featured, Categories, Calendar, Submission, Dashboard (`src/shared/components/ListingTypeControl.js:33,65`, `GET /listora/v1/listing-types`).

### 7. A slug that is not a type names itself
- **Action**: publish a grid with `listingType` = a misspelt slug; view logged out.
- **Expect**: card title "No $MISSPELT listings yet", description "This section only shows listings of one type, and there are none yet."; no "Clear All Filters" button.

### 8. A real but empty type renders its NAME
- **Action**: `listingType` = `$EMPTY_TYPE`.
- **Expect**: "No <Type Name> listings yet" (capitalised name from `Listing_Type_Registry`, not the slug).

### 9. Positive control: populated pinned type, then the visitor's own filter
- **Action**: `listingType` = `{type}`: results show and the empty card is in the DOM with `is-hidden`. Append `?listora_keyword=$NOMATCH` (and the legacy `?keyword=`).
- **Expect**: generic copy "No listings found" / "Try adjusting your filters, or be the first to add a listing." AND the "Clear All Filters" button, for both param names.

### 10. An unpinned grid is untouched
- **Action**: unpinned grid with `?listora_keyword=$NOMATCH`.
- **Expect**: "No listings found" + "Try adjusting your filters..." + "Clear All Filters".

### 11. A saved slug whose type was deleted stays selectable
- **Action**: open a block in the editor whose saved `listingType` no longer exists.
- **Expect**: the select shows "<slug> (not a listing type on this site)" and does not reset to "All types".

### 12. 390px
- **Expect**: card centred and readable, no horizontal scroll. Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {guest}, {owner}, {editor}, {admin} viewing | identical copy; "Add a listing" button only when `wb_listora_can_offer_submission()` is true for the viewer |
| {admin}, {editor} editing (`edit_pages`) | can use the picker (step 6, 11) |
| {other} (subscriber) | cannot open the block editor |

## Pass criteria
1. Empty card visible on first paint and after hydration at 0 results; hidden when results exist.
2. Pinned-type copy names the type and omits "Clear All Filters" until the visitor filters.
3. Editor offers a type select by name.

## Fail diagnostics
| Symptom | File |
|---|---|
| Hidden on first paint | `templates/blocks/listing-grid/grid.php:130` class `is-hidden` only when `$total > 0` |
| Visible server-side, hidden after hydration | `src/interactivity/store.js` `showEmptyState` getter |
| Term page shows blog loop | `/listing/` redirect and term templates (`includes/core/class-term-archives.php`) |
| Typo not named / slug shown | `blocks/listing-grid/render.php:234-269` (`pinned_type`, `pinned_type_label`, `$grid_visitor_filtered`) |
| Copy / button wrong | `templates/blocks/listing-grid/grid.php:117-140` |

## Not verified
Steps 3-6, 11 and 12 were not run in a browser; steps 1, 2, 7-10 were verified with curl on temporary pages. Automated: `tests/integration/GridEmptyStateTest.php` covers steps 7-10.

## Cleanup
Delete the temporary pages.
