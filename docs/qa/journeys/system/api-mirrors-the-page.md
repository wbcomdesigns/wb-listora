---
journey: api-mirrors-the-page
plugin: wb-listora
priority: critical
roles: [guest, owner, other, editor, admin]
covers: [field-visibility-page, field-visibility-api, listing-api-meta, internal-meta-keys, bc-10393531355, card-10369254010]
prerequisites:
  - "A Restaurant listing {listing} with phone and email, owned by {owner} (create QA-J1-api with `Meta_Handler::set_value` for email and phone; delete afterwards)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: 0d3e778f
---

# The listing API shows what the listing page shows, and nothing internal

## Why
A site owner who hides a field from the listing page (an email, a phone) expects it hidden everywhere. If REST still returns it, a scraper reads private contact data the page deliberately withholds.

A Listing Types field has "Show on listing page" (`show_in_detail`) and "Show in API" (`show_in_rest`), both default on. The API never shows more than the page; "Show in API" off hides it from the API only. The single gate is `Meta_Handler::get_visible_values( $post_id, $context )`: a viewer who cannot edit the listing gets only visible fields; an editor of the listing (owner, moderator, admin: `wb_listora_user_can_edit_listing()`) gets every value through REST. Consumers: the listing page and JSON-LD, `GET /listings/{id}` (`listora_meta`), `GET /listings/{id}/detail` (`meta`), `GET /search?q=` (each `listings[].meta`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Save the Restaurant type definition first; restore it byte-for-byte at the end.
- `GET /wp/v2/listings/{id}` carries no listing meta (no leak path); do not use it as a control.

## Steps

### 1. Hide a field from the page
- **Action**: Listing Types > Restaurant > Email > untick "Show on listing page", Save Type.
- **Expect, {guest}**: no email in the contact card or JSON-LD `email`; `GET /listings/{id}` `listora_meta`, the `/search?q=<title>` item `meta`, and `/detail` `meta` have no `email` and no `schema.email`; phone still present.
- **Expect, {owner} and {admin}**: all three still include the email.
- **Positive control**: with the switch back on (step 3) the same {guest} requests include the email.
- **Expect**: the contact form still emails the owner (server-side use, not exposed); with "Show on Card" also on, directory cards do not show it either; the gallery is unchanged.

### 2. Hide a field from the API only
- **Action**: tick "Show on listing page" again, untick "Show in API" on Phone.
- **Expect, {guest}**: the page shows the phone; `listora_meta`, search `meta` and `detail.meta` have no `phone`. {owner} and {admin} still get it.
- **expected-fail: `detail.schema.telephone` carries the phone**: the page's JSON-LD embedded in `/detail` still includes it because the page shows it; owner question open whether "API only" should also remove it from embedded schema.

### 3. Restore both toggles
Back to defaults; compare the saved type definition byte-for-byte.

### 4. Internal meta keys never reach a logged-out reader (bc-10393531355)
- **Action**: {guest}: `GET /listings/{id}`, `/detail`, `/search?q=<title>`; `jq '[.listora_meta,.meta]|map(keys?)|add'`.
- **Expect**: none of `plan_id`, `expiration_date`, `listing_active`, `demo_content`, `claimed_by`, `verified` internals appears; shown fields (phone, website) do.
- **Positive control**: the same GET as {owner} and {admin} returns the internal keys they own. fails_on: 0d3e778f. Not verified live.

### 5. Lifecycle seeds
- **Action**: repeat step 4 for a draft, pending, expired and trashed listing and for a listing whose author was deleted.
- **Expect**: {guest} gets 404 (never the meta); {owner} and {admin} per their rights. Not verified live.

### 6. Sibling surface: single gate on a large result set
- **Action**: search results page 1 and the last page of a scale-seeded site.
- **Expect**: `meta` goes through the same gate, one `get_visible_values()` per row (measure query count). Not verified.

## Role ladder
| Viewer | email hidden from page | phone hidden from API only |
|---|---|---|
| {guest} | absent everywhere | page yes, API no |
| {other} | same as guest | same as guest |
| {owner} | API still has it | API still has it |
| {editor} / {admin} (control) | API still has it | API still has it |

## Pass criteria
1. A field hidden from the page is absent from page, JSON-LD and every REST meta for non-editors.
2. "Show in API" off hides only from REST.
3. No internal key is exposed to {guest}.
4. Editors of the listing keep every value; positive controls pass.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Hidden field in the API | gate bypassed at a call site | `includes/core/class-meta-handler.php:353-385`; callers `class-template-helpers.php:1233`, `schema/class-schema-generator.php:43,592`, `rest/class-listings-controller.php:775,922`, `rest/class-search-controller.php:681` |
| Switch not saved | type editor / REST save | `assets/js/admin/type-editor.js:483-484` |
| Owner loses the value | edit check | `wb_listora_user_can_edit_listing()` |

## Not verified
The page HTML and contact-form email were not re-checked in the observed run; steps 1-2 results were verified with wp eval dispatch; steps 4-6 not live.

## Cleanup
Restore the type definition; delete the QA-J1-api listing.
