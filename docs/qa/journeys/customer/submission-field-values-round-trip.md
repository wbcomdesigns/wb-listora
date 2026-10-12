---
journey: submission-field-values-round-trip
plugin: wb-listora
priority: critical
roles: [subscriber, guest, admin]
covers: [submission-field-renderer, field-options-normalization, type-editor-options, search-filters-render, field-sanitize-price, listing-fields-metabox, contact-fields-collectable, listing-type-field-registry, bc-9852373335, bc-10171941201]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI access"
  - "Add Listing page = `wb_listora_get_page_url( 'submission' )`; a member {owner}"
  - "Types: {type} (business-like), a type with a `price` field ({type-b}: classified or real-estate)"
  - "Back up `get_term_meta( <{type} term id>, '_listora_field_groups', true )` to the scratch dir before step 1; baseline the `wp-content/debug.log` byte count"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:regression/string-shaped-field-options-fatal.md"
  - "ora:regression/price-scalar-sanitize-dataloss.md"
  - "ora:regression/type-contact-fields.md"
fails_on: []
---

# What a type declares is what the form renders, saves, prefills and displays

## Why
A stored field shape the renderer does not expect fatals the Add Listing page (500 for every member); a price silently saved as an empty array loses the member's data; a business-like type that does not collect the contact fields it displays leaves the sidebar empty.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Types are `listora_listing_type` terms with field groups in `_listora_field_groups` term meta, seeded from `Listing_Type_Defaults`; the contact-field change applies on (re)seed, not retroactively. To re-seed set `wb_listora_needs_defaults`, then load any page.
- Cleanup: restore the term meta backup and `wp cache flush`; `wp post delete <new ids> --force`.

## Steps

### 1. Contact fields exposed per type (REST)
- **Action**: for each type, `GET /listora/v1/listing-types/<slug>` (`real-estate` is hyphenated); read `field_groups[].fields[].key`.
- **Expect**: business-like types (restaurant, hotel, healthcare, place, real-estate, event, business) expose `phone`, `email`, `website` and `address`; classified exposes `seller_name`, `seller_phone`, `seller_email`, `address` (intentional); job exposes none of the three (intentionally out of scope); education exposes `phone`, `email`, `website` on a FRESH install (2.0.0, `schema_prop` `provider.telephone/email/url`, `education()` `includes/core/class-listing-type-defaults.php:1370`).
- **Read-back**: older seeds lack education contact fields; re-seed (Setup) before asserting. No type registers the same field key twice (script over all types: zero duplicates).
- **On fail**: confirm re-seed (`includes/core/class-listing-type-registry.php:130`).

### 2. The form renders them
- **Action**: as {owner} Add Listing > a business-like type > Details.
- **Expect**: Email and Website inputs render with Address and Phone (`input[name="meta_email"]`, `input[name="meta_website"]`; confirm exact names in the DOM).

### 3. Round trip and display
- **Action**: submit with email and website filled; open the detail page.
- **Expect**: the sidebar contact card shows the submitted website and email. With a field's "Show on listing page" switch off it must be absent from the page, cards, API and structured data (see `admin/type-field-visibility-page-api-schema.md`).

### 4. Inject string-shaped options (READ layer in isolation)
- **Action**: write term meta directly:
  ```php
  $term = get_term_by( 'slug', '{type}', 'listora_listing_type' );
  $raw  = get_term_meta( $term->term_id, '_listora_field_groups', true );
  $raw[0]['fields'][] = array( 'key' => 'qa_string_opts', 'label' => 'QA String Opts', 'type' => 'multiselect', 'options' => array( 'Alpha', 'Beta' ) );
  update_term_meta( $term->term_id, '_listora_field_groups', $raw ); wp_cache_flush();
  ```
- **Expect**: nothing yet; `Listing_Type_Registry::save_type()` normalizes on write (`class-listing-type-registry.php:304-315`), so going through it proves only the save layer.

### 5. The submission page renders (pre-fix: 500)
- **Action**: as {owner} load Add Listing.
- **Expect**: HTTP 200; the field block contains `input[type=checkbox][name="meta_qa_string_opts[]"]` with `value="alpha"` and `value="beta"` and label "QA String Opts".
- **Read-back**: positive control: a built-in select (Price range) still renders its options; `debug.log` diff has no new `Cannot access offset` or `Fatal`.
- **On fail**: `includes/core/class-field.php:67` (`normalize_options()` in the constructor), renderer `includes/submission-field-renderer.php:197-222`.

### 6. REST reader and search filter carry the canonical shape
- **Action**: mark the field `filterable` (`'filterable' => true`), `GET /listora/v1/listing-types/{type}`; then open the Directory and pick the {type} chip.
- **Expect**: `filterable_fields` entry for `qa_string_opts` has `options` `[{"value":"alpha","label":"Alpha"},{"value":"beta","label":"Beta"}]`; the filter renders Alpha / Beta with no fatal.
- **On fail**: `blocks/listing-search/render.php:62` (`get_filterable_fields()`).

### 7. Type Editor writes the canonical shape
- **Action**: wp-admin > Listing Types > {type}, edit `qa_string_opts`, **Add Option** `Gamma Ray`, **Save Type**.
- **Expect**: toast "Type saved successfully."; raw term meta for that field has `array( 'value' => 'gamma-ray', 'label' => 'Gamma Ray' )` and no plain string anywhere in any field's `options`.
- **On fail**: `assets/js/admin/type-editor.js` Add Option (~554).

### 8. Price sanitizer round-trip
- **Action**: `wp eval`:
  ```php
  $f = new \WBListora\Core\Field( array( 'key' => 'price', 'type' => 'price', 'label' => 'Price' ) );
  $c = $f->get_sanitize_callback();
  var_export( call_user_func( $c, '275' ) ); var_export( call_user_func( $c, array( 'amount' => 450, 'currency' => 'EUR' ) ) ); var_export( call_user_func( $c, '' ) );
  ```
- **Expect**: `'275'` -> `array('amount' => 275.0, 'currency' => '<site currency>')`; the canonical array is preserved (`EUR` intact); `''` -> `array()` (a cleared price stays cleared).
- **On fail**: `price` mapped to `sanitize_json()`: `class-field.php:294` (`sanitize_price()` `:474`).

### 9. Submit a price from the form
- **Action**: as {owner} choose {type-b}, fill required fields, enter `275` in **Price** (`#listora-field-price`, `name="meta_price"`, `type=number`, `step=0.01`), submit.
- **Expect**: success.
- **Read-back**: `wp post meta get <id> _listora_price` returns `amount => 275`, not `a:0:{}`.

### 10. Edit prefills the price
- **Action**: open the edit form as {owner} and as {admin}.
- **Expect**: `#listora-field-price` has `value="275"`, never `value="Array"`.
- **On fail**: `submission-field-renderer.php:275-290` (`is_scalar()` guard).

### 11. Pre-fix corrupted rows render clean
- **Action**: `update_post_meta( <id>, '_listora_price', array() )`, reload the edit form.
- **Expect**: the input has no `value` attribute; no `Array to string conversion` in `debug.log`.

### 12. wp-admin metabox uses the same sanitizer
- **Action**: as {admin} edit the listing, set Price `310`, Update.
- **Expect**: `_listora_price` stores `amount => 310`.

### 13. Boundary input
- **Action**: submit prices `0`, `-5`, `1e9`, `abc`, `  `, `12,50`.
- **Expect**: `0` stores `amount 0`; negatives, non-numeric and whitespace-only are refused or stored as `array()` with a field error, never `Array`; huge values do not overflow the column. Not verified live (added in consolidation).

### 14. Zero debug.log warnings
- **Action**: diff `debug.log` since the baseline.
- **Expect**: no new `Array to string conversion` from `submission-field-renderer.php`. (A pre-existing unrelated warning exists at `includes/search/class-search-engine.php:1513`.)

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 2-3, 5, 9-11 |
| {guest} | steps 1, the public detail page (3); the form is behind sign-in and the Directory (6) must not fatal either |
| {admin} (control) | steps 1, 4, 6-7, 10, 12; Type Editor shows the same fields |
| editor / another member | step 10: another member gets no edit form for {owner}'s listing |

## Pass criteria
1. Business-like types collect the contact fields their sidebar displays; no duplicate keys.
2. String-shaped stored options render and normalize on read; the Type Editor writes `{value,label}` only.
3. A submitted scalar price survives the save, prefills, and never prints `Array`.
4. No new PHP notice or fatal in `debug.log`.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| 500 on Add Listing | options not normalized on read | `includes/core/class-field.php:67`, `submission-field-renderer.php:197-222` |
| Corrupt shape written again | save path or editor JS | `class-listing-type-registry.php:304-315`, `type-editor.js` (~554) |
| Filter fatals | filter reader | `blocks/listing-search/render.php:62` |
| Price saves `array()` | wrong sanitizer | `class-field.php:294` |
| `value="Array"` | renderer branch | `submission-field-renderer.php:275` |
| Field missing from a type | defaults | `class-listing-type-defaults.php` |
| Fields exist but not on form | field loop | `submission-field-renderer.php` |

## Not verified
- Steps 4, 5, 6 (REST half) ran via curl as a member and the meta was restored; step 7 (Type Editor UI), the chip click, steps 9 and 12 (browser submit, metabox save), step 13, 390px and dark/unknown theme not run; steps 1 and the duplicate-key check ran via REST on 2026-10-12.

## Cleanup
Restore the term meta backup, flush cache, `GET /listora/v1/listing-types/{type}` must no longer list `qa_string_opts`; delete the created listings.
