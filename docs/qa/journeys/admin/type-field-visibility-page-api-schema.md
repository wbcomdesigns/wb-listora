---
journey: type-field-visibility-page-api-schema
plugin: wb-listora
priority: critical
roles: [administrator, member-owner, member-other, guest]
covers: [field-show-on-listing-page, field-show-in-api, hidden-field-structured-data, api-mirrors-page, field-settings-preserved, built-in-type-kept, 2.0.0-field-visibility]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} autologin"
  - "Fixtures seeded (docs/qa/fixtures/2.0-fixtures.php seed): a published {type} listing {listing} owned by {owner}. Give it contact data: `wp eval 'update_post_meta($id,\"_listora_phone\",\"555-0142\"); update_post_meta($id,\"_listora_email\",\"visible@example.com\");'`"
  - "This journey flips two switches on the shared {type} type for a few minutes: run it alone and restore both at the end"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default, buddyx-pro light for the front end]
site_profile: required
supersedes: []
fails_on: []
---

# A field switched off for the listing page disappears from page, cards, API and structured data; "Show in API" only hides it from the API

## Why
An owner who hides Email from the listing page expects it gone everywhere a visitor can look; if it stays in the REST API, JSON-LD or a card, private data leaks. Owners and editors still need every value from the API to edit.

Each field in Listing Types > {type} has two switches (default on): **Show on listing page** and **Show in API**. The API never shows more than the page does. Hidden fields are also dropped from cards, gallery data and JSON-LD. (A short sentinel `system/api-mirrors-the-page.md` exists outside this area; see the cross-area note.)

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `$ID` = the {listing} id; `$SLUG` its slug. Restore both switches in step 7.

## Steps

### 1. Baseline: everything shows
- **Action**: as {guest} open {listing}; then
  ```bash
  curl -s "$SITE_URL/wp-json/listora/v1/listings/$ID" | jq '.listora_meta | {phone,email}'
  curl -s "$SITE_URL/listing/$SLUG/" | grep -o '"telephone":"[^"]*"\|"email":"[^"]*"'
  ```
- **Expect**: page shows `555-0142` and a `mailto:visible@example.com` link in the contact card; API `listora_meta` has `phone` and `email`; JSON-LD has `"telephone"` and `"email"`.

### 2. Switch the field off for the page (admin, 1440)
- **Action**: `admin.php?page=listora-listing-types&edit={type}` > open the **Email** field > untick "Show on listing page" > **Save Type**.
- **Expect**: success notice; re-opening shows the box unticked and "Show in API" still ticked (each switch saved independently).
- **On fail**: `assets/js/admin/type-editor.js` (~483), `Type_Editor` save handler, `Listing_Type_Registry` persistence.

### 3. Email is gone everywhere the visitor can look
As {guest} and as {other}:
- **Expect page**: no `mailto:` link and no email text in the contact card; Phone is still there (positive control).
- **Expect structured data**: no `"email"` key; `"telephone"` still present.
- **Expect API**: `GET /listora/v1/listings/$ID` `.listora_meta` has `phone`, NOT `email`; the same for `GET /listora/v1/listings?fields=...` (full mode `meta`), `GET /listora/v1/search` (`meta`) and `GET /wp/v2/listings/$ID`.
- **Expect cards**: turn on "Show on Card" for Email first to make this a real test; the Directory card shows no email; turn it off again.
- **Expect contact form**: still works (the owner is emailed): the contact form is not a field display.
- **On fail**: `class-schema-generator.php` (`get_visible_values` ~43, ~592), `class-search-controller.php` ~681.

### 4. Role ladder: owner and admin still get the value from the API
| Rung | `GET /listora/v1/listings/$ID` `.listora_meta` | Listing page |
|---|---|---|
| {guest} | phone only | no email |
| {other} | phone only | no email |
| {owner} (cookie + nonce) | phone, email (+ owner-only internals such as plan/expiry) | no email (the owner's own page follows the same display rule) |
| {admin} (control) | phone, email, plus internals | no email |
- **Expect**: public responses never include `plan_id`, `expiration_date`, demo/consent state (anonymous `curl`; only owner/admin have them).
- **On fail**: `wb_listora_user_can_edit_listing` regression (`helpers.php` ~938).

### 5. "Show in API" hides only from the API
- **Action**: tick Email's "Show on listing page" again; untick **Phone**'s "Show in API"; Save Type.
- **Expect** ({guest}/{other}): page shows phone and email again; JSON-LD still has `telephone`; API `listora_meta` has `email` but NOT `phone`. Owner/admin API still returns `phone`.
- **Action**: with Phone "Show on listing page" OFF and "Show in API" ON.
- **Expect**: absent from the API for visitors too (the API never exceeds the page).

### 6. Field settings are preserved (2.0.0 fixes)
- **Action**: before saving in steps 2/5 note on the Email field: Required, Searchable, Filterable, Show on Card, Schema.org property, Placeholder, Help text. After "Save Type", reopen.
- **Expect**: all unchanged except the toggled switch; "Show in API" saved (it did not before 2.0.0). Saving a built-in type keeps it built in and keeps its Schema.org type: `wp listora listing-types` before and after shows `Built-in = Yes` and an unchanged Schema column (Restaurant stays `Restaurant`; Classified `Product`, Education `Course`, Healthcare `Physician`; Job and Real Estate keep theirs and never fall back to `LocalBusiness`). Check one of Job, Classified, Education, Healthcare, Real Estate as well.
- **On fail**: `type-editor.js` (~851: fields the editor has no control for must be carried through), `Listing_Types_Controller` partial update (~583).

### 7. Restore
- **Action**: re-tick Email "Show on listing page" and Phone "Show in API"; Save Type.
- **Expect**: step 1 passes again.

### 8. Boundary and lifecycle
- **Action**: repeat step 3 on a pending, expired and trashed copy of the listing and on one whose author was deleted; set the hidden field's value to an empty string and to markup.
- **Expect**: the hidden field is absent from every public surface in every status; the draft/pending listing is unreachable for visitors regardless; empty and markup values never reappear. Not verified live (added in consolidation).

### 9. 390px
- **Expect**: the type editor field panel stacks its checkboxes (Required, Searchable, Filterable, Show on Card, Show on listing page, Show in API) one per line or wrapped, no horizontal scroll; the front-end contact card stays intact.

## Pass criteria
1. Show on listing page OFF removes the field from page, cards, JSON-LD and public API; contact form unaffected.
2. Show in API OFF removes it only from the API; owner/editor/admin always see all values in the API.
3. Public API responses carry no plan/expiry/demo/consent internals.
4. Saving a type does not reset field settings it has no control for and keeps built-ins built in.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Email still in JSON-LD | schema reading raw meta | `class-schema-generator.php` (`get_visible_values` ~43, ~592) |
| Email in search `meta` | search shaper skipping the visibility filter | `class-search-controller.php` ~681 |
| Switch reverts on save | editor not posting `show_in_detail`/`show_in_rest` | `type-editor.js` ~483, ~851 |
| Owner cannot see own email in API | `wb_listora_user_can_edit_listing` regression | `helpers.php` ~938 |

## Not verified
- Step 8 and the 390px layout were not run; the other steps were authored against the 2.0.0 fixtures.

## Cleanup
Step 7 restore; `wp eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php clean`.
