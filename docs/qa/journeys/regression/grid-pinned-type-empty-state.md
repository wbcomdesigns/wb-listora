---
journey: grid-pinned-type-empty-state
plugin: wb-listora
priority: normal
roles: [administrator, anonymous]
covers: [listing-grid-empty-state, listing-type-control, block-editor-type-picker]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one listing type with zero published listings"
estimated_runtime_minutes: 5
covers_card: 10217484053
---

# A grid pinned to an empty type explains itself instead of blaming the visitor

Regression sentinel for the type-aware empty state and the listing-type picker.

## Background

Every type-aware block (grid, search, map, featured, categories, calendar, submission) used to ask the editor to hand-type a listing-type slug into a plain `TextControl` - no validation, no list of what exists. A single transposed letter (`restaurnt`) rendered an empty grid whose copy read "Try adjusting your filters, or be the first to add a listing", blaming the visitor for filters they never set, next to a Clear All Filters button that reloaded the same empty grid. The owner got no warning at all.

Two halves to the fix: the editor now picks from a list (`src/shared/components/ListingTypeControl.js`, fed by `GET /listora/v1/listing-types`), and the front end says what is actually wrong.

## Steps

### 1. The editor picks a type, it does not spell one
- **Action**: admin → add a Listing Grid block to a new page → open the block sidebar.
- **Expect**: "Listing Type" is a `<select>` whose first option is "All types", followed by every listing type on the site by NAME. No free-text slug field. Zero console errors.
- **Repeat for**: Listing Search, Listing Map, Featured Listings, Listing Categories, Listing Calendar, Listing Submission - all seven use the same control.
- **On fail**: `src/shared/components/ListingTypeControl.js`, the block's `index.js`, or `GET /listora/v1/listing-types`.

### 2. A slug that is not a type on this site names itself
- **Action**: publish a page containing `<!-- wp:listora/listing-grid {"listingType":"restaurnt"} /-->` (a typo an older site could already have saved), then view it logged out.
- **Expect**: heading reads "No restaurnt listings yet"; body reads "This section only shows listings of one type, and there are none yet."; there is NO "Clear All Filters" button.
- **On fail**: `blocks/listing-grid/render.php` (`pinned_type` / `pinned_type_label` in `$view_data`) or `templates/blocks/listing-grid/grid.php`.

### 3. A real but empty type renders its NAME, not its slug
- **Action**: same, with a real type that has no published listings - e.g. `{"listingType":"education"}`.
- **Expect**: "No Education listings yet" (the type's name), not "No education listings yet".
- **On fail**: `Listing_Type_Registry::instance()->get( $slug )->get_name()` lookup in the render file.

### 4. The visitor's OWN filter keeps the clearable empty state
- **Action**: on the same pinned page, append `?keyword=nothing-matches-this-keyword`.
- **Expect**: the generic copy is back - "No listings found" / "Try adjusting your filters, or be the first to add a listing" - AND the Clear All Filters button is present, because now there IS something to clear.
- **On fail**: the `$grid_visitor_filtered` loop in `blocks/listing-grid/render.php`.

### 5. An unpinned grid is untouched
- **Action**: a grid with no `listingType`, on a site with zero published listings.
- **Expect**: "No listings found" + "Try adjusting your filters…" + Clear All Filters, exactly as before.

### 6. A saved slug whose type was deleted stays selectable
- **Action**: in the editor, open a block whose saved `listingType` no longer exists.
- **Expect**: the select still shows it, labelled "<slug> (not a listing type on this site)" - it must not silently reset to "All types" the moment the editor touches anything else.

## Automated coverage

`tests/integration/GridEmptyStateTest.php` covers steps 2-5 headlessly.
