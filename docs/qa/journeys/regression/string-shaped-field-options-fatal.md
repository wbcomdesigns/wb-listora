---
journey: string-shaped-field-options-fatal
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [submission-field-renderer, field-options-normalization, type-editor-options, search-filters-render]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Add Listing page exists (listing-submission block), at /add-listing/ here"
  - "WP-CLI access for the corrupted-shape injection"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# String-shaped field options must not fatal the submission form

Live-site fatal (WB Listora 1.4.0, PHP 8.4): `TypeError: Cannot access offset of type string on string` in the submission field renderer. The admin Type Editor persisted owner-added select/radio/multiselect options as plain strings inside `_listora_field_groups` term meta while every reader assumes `{ value, label }`. Fix is three layers: `Field::normalize_options()` in the `Field` constructor (heals stored data on read), save-path normalization in `Listing_Type_Registry::create_type_from_data()`, and the Type Editor JS writing the object shape.

## Setup

- Back up the raw meta first: `get_term_meta( <business term id>, '_listora_field_groups', true )` to a file under the scratchpad. Restore it at the end (Cleanup).
- Baseline `wp-content/debug.log` byte count.

## Steps

### 1. Inject the corrupted shape (READ layer in isolation)
Write the term meta directly, bypassing the save path:
```php
$term = get_term_by( 'slug', 'business', 'listora_listing_type' );
$raw  = get_term_meta( $term->term_id, '_listora_field_groups', true );
$raw[0]['fields'][] = array( 'key' => 'qa_string_opts', 'label' => 'QA String Opts',
    'type' => 'multiselect', 'options' => array( 'Alpha', 'Beta' ) );
update_term_meta( $term->term_id, '_listora_field_groups', $raw );
wp_cache_flush();
```
- Note: `Listing_Type_Registry::save_type()` normalizes on write (`class-listing-type-registry.php:304-315`), so going through it only proves the save layer.

### 2. Submission page renders
- **Action**: as a member (`?autologin=QA-J1-member`) load `/add-listing/`.
- **Expect**: HTTP 200 (pre-fix 500). The Business field block contains `input[type=checkbox][name="meta_qa_string_opts[]"]` with `value="alpha"` and `value="beta"`, and the visible field label "QA String Opts".
- **Positive control**: a built-in select field of the same type (Price range) still renders its options, so the page is not simply skipping fields.
- **On fail**: `includes/core/class-field.php:67` (`normalize_options()` call in the constructor).

### 3. Zero fatals
- **Action**: diff `debug.log` since the baseline.
- **Expect**: no new `Cannot access offset` or `Fatal` entries.

### 4. REST reader
- **Action**: mark the injected field filterable in the term meta (`'filterable' => true`), then `GET /listora/v1/listing-types/business`.
- **Expect**: the `filterable_fields` entry for `qa_string_opts` carries `options` as `[{"value":"alpha","label":"Alpha"},{"value":"beta","label":"Beta"}]`.

### 5. Type Editor writes the canonical shape
- **Action**: wp-admin > Listora > Listing Types > Business, edit the `qa_string_opts` field, **Add Option** `Gamma Ray`, **Save Type**.
- **Expect**: toast "Type saved successfully."; raw term meta for that field has `array( 'value' => 'gamma-ray', 'label' => 'Gamma Ray' )` and no plain string anywhere in any field's `options`.

### 6. Search filter surface (same reader family)
- **Action**: set the field `filterable` (Type Editor "Filterable" switch), open the Directory (`/listings/`) and pick the **Business** type chip.
- **Expect**: the filter renders Alpha / Beta (filters read `Field::get( 'options' )` normalized), no fatal.

## Role ladder
Member loads the form (steps 2-3); admin runs steps 1, 4-6 (admin is the author of the corruption, so it is a control only). Logged-out: `/add-listing/` shows the login prompt, and the Directory (step 6) must not fatal either.

## Cleanup
`update_term_meta( ..., '_listora_field_groups', <backup> ); wp_cache_flush();` then `GET /listora/v1/listing-types/business` must no longer list `qa_string_opts`.

## Fail diagnostics
| Symptom | File |
|---|---|
| 500 on `/add-listing/` | `includes/core/class-field.php:67`; renderer `includes/submission-field-renderer.php:197-222` (`case 'multiselect'`) |
| Corrupt shape written again | `includes/core/class-listing-type-registry.php:304-315` and `assets/js/admin/type-editor.js` Add Option (about line 554) |
| Filter fatals | `blocks/listing-search/render.php:62` (`get_filterable_fields()`) |

## Not verified
Steps 2, 3 and 4 were run (curl as QA-J1-member, REST) and the meta restored. Step 5 (Type Editor UI save) and step 6 in a browser (chip click) were not run; the REST `filterable_fields` payload was checked instead. Browser rendering was not checked in dark/unknown theme.

Verified via: curl/DOM, REST, wp-cli.
