---
journey: grid-pinned-type-empty-state
plugin: wb-listora
priority: normal
roles: [administrator, anonymous, member]
covers: [listing-grid-empty-state, listing-type-control, block-editor-type-picker]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "A listing type with zero published listings: Event; a type with listings: Hotel (20)"
estimated_runtime_minutes: 5
covers_card: 10217484053
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A grid pinned to an empty type explains itself instead of blaming the visitor

Eight blocks pick a listing type through `src/shared/components/ListingTypeControl.js` (user-dashboard, listing-calendar, listing-search, listing-map, listing-featured, listing-categories, listing-submission, listing-grid), fed by public `GET /listora/v1/listing-types`. The grid's own empty state is `templates/blocks/listing-grid/grid.php:117-142`.

Verified via: temporary pages (QA-J1, deleted) fetched logged out, 2026-10-12: typo slug, empty real type, pinned type with a visitor keyword (both `?listora_keyword=` and legacy `?keyword=`). The editor picker (step 1, 6) not exercised in a browser.

## Steps

### 1. The editor picks a type, it does not spell one
- **Action**: admin adds a Listing Grid to a new page, opens the sidebar.
- **Expect**: "Listing Type" is a select whose first option is "All types" (help: "Leave as All types to show every listing."), then every type by NAME; no free-text slug. Zero console errors. Same control on Search, Map, Featured, Categories, Calendar, Submission, Dashboard.
- **On fail**: `src/shared/components/ListingTypeControl.js:33,65`, `GET /listora/v1/listing-types`.

### 2. A slug that is not a type names itself
- **Action**: publish `<!-- wp:listora/listing-grid {"listingType":"restaurnt"} /-->`, view logged out.
- **Expect**: card title "No restaurnt listings yet", description "This section only shows listings of one type, and there are none yet."; no "Clear All Filters" button (verified).

### 3. A real but empty type renders its NAME
- **Action**: `{"listingType":"event"}`.
- **Expect**: "No Event listings yet" (verified), not "No event listings yet".

### 4. POSITIVE CONTROL: populated pinned type, then the visitor's own filter
- **Action**: `{"listingType":"hotel"}`: results show; the empty card is in the DOM with `is-hidden`. Append `?listora_keyword=nothing-matches-this-keyword` (legacy `?keyword=` too).
- **Expect**: generic copy "No listings found" / "Try adjusting your filters, or be the first to add a listing." AND the "Clear All Filters" button (verified for both param names), because there is now something to clear.

### 5. An unpinned grid is untouched
- **Action**: grid with no `listingType` and a filter that matches nothing (`/listings/?listora_keyword=xyzzy12345`).
- **Expect**: "No listings found" + "Try adjusting your filters..." + "Clear All Filters" (verified).

### 6. A saved slug whose type was deleted stays selectable
- **Action**: in the editor open a block whose saved `listingType` no longer exists.
- **Expect**: the select shows "<slug> (not a listing type on this site)" and does not reset to "All types".

### 7. Role ladder
- Editing: administrator and editor (`qa_editor`, `edit_pages`) can use the picker; a subscriber/another member cannot open the block editor. Viewing: anonymous, member, owner, editor, admin see identical copy. Admin is the control.

### 8. 390px
- **Expect**: card readable, no horizontal scroll. Not run.

## Automated coverage

`tests/integration/GridEmptyStateTest.php` covers steps 2-5 headlessly.

## Fail diagnostics

| Symptom | File |
|---|---|
| Typo not named | `blocks/listing-grid/render.php:234-269` (`pinned_type`, `pinned_type_label`, `$grid_visitor_filtered`) |
| Copy / button wrong | `templates/blocks/listing-grid/grid.php:117-140` |
| Type shows slug not name | `Listing_Type_Registry::instance()->get( $slug )->get_name()` at `render.php:234-235` |
