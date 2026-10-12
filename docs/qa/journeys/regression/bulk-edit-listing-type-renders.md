---
journey: bulk-edit-listing-type-renders
plugin: wb-listora
priority: high
roles: [administrator, editor]
covers: [BC-10190576873, bulk-edit, admin-columns]
prerequisites:
  - "At least 3 listings of mixed listing types (create QA-J1-bulk-a as business and QA-J1-bulk-b as restaurant; delete them afterwards)"
  - "2+ registered listing types"
estimated_runtime_minutes: 6
covers_card: 10190576873
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default]
changed: true
---

# The bulk-edit listing type control must actually render

`bulk_edit_custom_box` fires per column, and WordPress SKIPS the core columns (`cb`, `title`, `author`, `date`, `comments`). The listing-type control was registered against `title`, so the callback was never invoked: the Bulk Edit panel opened, looked normal, and contained nothing of ours. There is no error anywhere in this failure: the hook is registered, the callback is correct, the panel renders. It is only ever visible by looking.

Current implementation: `includes/admin/class-listing-bulk-actions.php:80` registers `bulk_edit_custom_box`; `render_bulk_edit_type_field()` (`:91-132`) returns unless the column is the plugin-owned `listora_type` and the user has `manage_listora_settings`, then prints a "Listing Type" `select[name="listora_bulk_listing_type"]` with "— No Change —" (value `""`) plus every registered type, and the nonce `_wb_listora_bulk_type_nonce`; the save runs on `save_post_listora_listing` (`:144-180`) and goes through `Listing_Type_Metabox::assign_type()`, which returns without writing for `""`.

There is NO Quick Edit listing-type control (`quick_edit_custom_box` is not registered): a single listing's type changes in its Listing Type metabox, or by Bulk Edit with one row selected.

## Steps (verified 2026-10-12)

### 1. Open Bulk Edit
- **Action**: as administrator, Listora > Listings (`edit.php?post_type=listora_listing`), tick 2+ rows, Bulk actions -> Edit -> Apply.

### 2. The control is present AND computed-visible
- **Assert**: `select[name="listora_bulk_listing_type"]` exists inside `#bulk-edit` **and** `getComputedStyle(el).display !== 'none'`. The first option reads "— No Change —". Served HTML verified: the select and nonce are in the `#bulk-edit` panel with the default column set (the `listora_type` column stays bound even when its column is hidden in Screen Options).
- **Fails if** absent: the column binding is a core column again.

### 3. It is bound to a non-core column
```bash
grep -n "bulk_edit_custom_box\|quick_edit_custom_box\|'listora_type' !== \$column_name" includes/admin/class-listing-bulk-actions.php
```
- **Fails if** the column argument is any of `cb`, `title`, `author`, `date`, `comments`. Bind to a plugin-owned column (`listora_type`).

### 4. Applying it changes the data
- **Action**: select rows of MIXED types (business + restaurant), choose a type (e.g. Hotel), Update. Re-read the term for each selected listing.
- **Expect**: each listing's `listora_listing_type` term is the chosen type (verified: business and restaurant both became hotel; redirect `updated=2&skipped=0`), and `wb_listora_listing_type_changed` fired per changed listing. Field values saved under the previous type are kept, per the panel's note "Field values saved under the previous type are kept, not deleted."
- **Fails if** the list table shows the new type but the term did not change, or vice versa: the counter-vs-list class in a different costume.

### 5. "— No Change —" changes nothing
- **Action**: re-run leaving the control at its default, with listings of DIFFERENT types selected.
- **Expect**: every listing keeps its own type; none is reassigned or cleared. A bulk action that overwrites on a no-op silently rewrites the whole directory. (`assign_type()` returns false for `''`; source-verified. Note the 2026-10-12 run used two listings that already shared a type at that point, so the mixed-type no-op was not exercised: use fresh mixed rows.)

### 6. Role ladder
| Role | Control in Bulk Edit |
|---|---|
| administrator (`manage_listora_settings`) (control) | present (verified) |
| editor (qa_editor) | ABSENT: the control needs `manage_listora_settings` (verified: 0 matches), although editors can bulk-edit status; their Bulk Edit saves must not touch types |
| subscriber / logged out | cannot open the list |
| listing owner | cannot reach wp-admin list; changes type only via the frontend editor where allowed |

### 7. Large site
2,800 listings: select all rows on a page (20) and a filtered view; paging and search work and the control is rendered once per panel, not per row.

## Test-data trap

Selecting listings that already share one type makes step 4 pass without the save path working at all. Use rows of MIXED types and assert each one individually.

## Fail diagnostics
| Symptom | File |
|---|---|
| Panel has no type control | `includes/admin/class-listing-bulk-actions.php:91-102` (column binding / capability) |
| Posted type ignored | `:144-180` nonce `_wb_listora_bulk_type_nonce`, `bulk_edit` in `$_REQUEST` |
| All rows reset on no-op | `includes/admin/class-listing-type-metabox.php:171-173` |

Verified via: curl as administrator and editor (panel markup, role gate) and real bulk-edit POSTs (`action=edit&bulk_edit=Update`) against two QA-J1- listings, removed afterwards.
