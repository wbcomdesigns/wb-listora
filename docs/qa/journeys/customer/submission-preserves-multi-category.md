---
journey: submission-preserves-multi-category
plugin: wb-listora
priority: critical
roles: [owner, other, guest, admin]
covers: [BC-10203063915, submission-controller, listora_listing_cat, allowed_categories, silent-data-loss]
prerequisites:
  - "Site reachable at $SITE_URL; {owner} session"
  - "A listing owned by {owner} carrying 2+ categories, and one whose category is NOT in its type's allowlist (create both as QA-J1- fixtures)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "category=C on a listing with [A,B] leaving only [C]"
---

# Editing a listing does not delete the categories the form cannot show

## Why
The form has one category `<select>`; the data model is multi-valued. Saving a multi-category listing used to drop every category but the one in the dropdown (silent data loss, search facets then missed the listing).

`Submission_Controller::resolve_category_terms()` treats `category` as the single slot the form shows: it replaces the first existing term and keeps the rest. `categories` (array) is a complete set and replaces wholesale. Omitting both leaves terms untouched. The edit dropdown unions the type's allowlist with the listing's own terms so a `required` field never hides the real category.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Fixture listing {listing} with terms [{cat}, B] (A and B from the listing type's categories); read term names with `wp post term list`.

## Steps

### 1. A single `category` preserves the siblings
- **Action**: as {admin} via `wp eval-file`, `POST /submit` with `listing_id` and `category=C`.
- **Expect**: HTTP 200; terms after save are C plus the sibling the form never showed (before `A | B`, send `C`, after `B | C`). Exactly one term is the regression.

### 2. `categories[]` IS a complete statement
- **Action**: repeat with `categories = [C]`.
- **Expect**: exactly `[C]` (a client must be able to remove a category).

### 3. Omitting both leaves terms untouched
- **Action**: reset terms to `[A,B]`; send an update with only `title`.
- **Expect**: still `[A,B]`.

### 4. An out-of-set category is offered and pre-selected (browser)
- **Action**: as {owner} open `$SITE_URL/<add-listing path>/?edit=<id>` (the dashboard edit route resolves to the same form) for a listing carrying a category not in its type's allowlist.
  ```js
  const sel = document.querySelector('select[name="category"]');
  ({ value: sel.value, selected: sel.options[sel.selectedIndex].text.trim(), required: sel.required, n: sel.options.length })
  ```
- **Expect**: the real category present and selected, `required: true`, options = placeholder + allowed + the out-of-set one. `value: ""` with "Select a category" on a `required` field is the dead-end regression.

### 5. The allowlist still governs NEW listings (positive control for step 4)
- **Action**: start a fresh submission for the same type.
- **Expect**: only the type's allowed categories plus the placeholder.

### 6. Boundary input
- **Action**: send `category` as empty, a nonexistent term id, a term of another type, and `categories=[]`.
- **Expect**: nonexistent/other-type ids are rejected with 400 and terms unchanged; `categories=[]` clears only if the type permits no category (otherwise 400). Not verified live.

### 7. Retry
- **Action**: save the edit form twice quickly and from two tabs.
- **Expect**: terms identical after each; no duplicate relationships.

### 8. Mobile
- **Action**: edit form at 390.
- **Expect**: Category select visible, no horizontal scroll, select >= 40px tall.

## Role ladder
| Actor | Call | Expect |
|---|---|---|
| {owner} | steps 1-5, 7-8 | as above |
| {other} | `POST /submit` with the owner's `listing_id` | 403, terms unchanged |
| {guest} | same | 401/403 |
| {admin} / {editor} | same | allowed (control); admin saving a member's listing edits it (2.0.0) |

## Pass criteria
1. A single `category` replaces only the first term and keeps the rest.
2. `categories[]` replaces wholesale; omitting both changes nothing.
3. The edit dropdown always contains and preselects the listing's real category; new submissions stay allowlist-bound.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Siblings deleted | preserve `array_slice( $existing, 1 )` lost | `includes/rest/class-submission-controller.php` (~:455-466) |
| `categories` no longer replaces | branch lost | same (~:435-438) |
| Dropdown missing the real category | union with `$edit_cat_terms` lost | `blocks/listing-submission/render.php` (~:393) |

## Not verified
Steps 1-3 ran via `rest_do_request` as {admin}; step 4 via curl as {owner} (server HTML, no browser); steps 5-8 and ladder rows were not run.

## Cleanup
Delete the probe listings. Steps 4-5 are read-only: do not save the edit form of a seeded listing.
