---
journey: submission-preserves-multi-category
plugin: wb-listora
roles: [member, other-member, logged-out, admin]
priority: critical
covers: [BC-10203063915, submission-controller, listora_listing_cat, allowed_categories, silent-data-loss]
prerequisites:
  - "Site reachable at $SITE_URL; member session ?autologin=QA-J1-owner"
  - "A listing owned by the member that carries 2+ categories, and one whose category is NOT in its type's allowlist (create both as QA-J1- fixtures; seeded data has many)"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Editing a listing does not delete the categories the form cannot show

The form has one category `<select>`; the data model is multi-valued. `Submission_Controller::resolve_category_terms()` (`includes/rest/class-submission-controller.php:425-470`) treats `category` as the single slot the form shows: it replaces the first existing term and keeps the rest. `categories` (array) is a complete set and replaces wholesale. Omitting both leaves terms untouched. The edit dropdown unions the type's allowlist with the listing's own terms (`blocks/listing-submission/render.php:385-393`) so a `required` field never hides the listing's real category.

## Steps

### 1. A single `category` preserves the siblings
```bash
wp eval-file x.php   # as admin; creates QA-J1- listing, 2 terms [A,B], POST /submit with category=C
```
- **Expect** after the save: two terms, C plus the sibling the form never showed (2026-10-12 run: before `Accommodation | Admin`, send `category=American`, after `Admin | American`; HTTP 200). One term only is the regression.

### 2. `categories[]` IS a complete statement
- Repeat with `categories = [C]` (array of one).
- **Expect** exactly `[C]` (2026-10-12: `American`). A client must be able to remove a category.

### 3. Omitting both leaves terms untouched
- Reset the terms to `[A,B]`, send an update with only `title`.
- **Expect** `[A,B]` (2026-10-12: `Accommodation | Admin`).

### 4. An out-of-set category is offered and pre-selected (browser)
- **Action**: as the owner open `$SITE_URL/add-listing/?edit=<id>` (the dashboard route `/my-listings/?tab=listings&action=edit&id=<id>` resolves to the same form), for a Business listing carrying "Accommodation" (not in Business's allowlist).
```js
const sel = document.querySelector('select[name="category"]');
({ value: sel.value, selected: sel.options[sel.selectedIndex].text.trim(), required: sel.required, n: sel.options.length })
```
- **Expect**: the real category present and selected, `required: true`. Verified 2026-10-12 via curl on the server HTML: 12 options (placeholder + 10 allowed + the out-of-set one), option `value="454"` `selected`.
- `value: ""` with "Select a category" on a `required` field is the dead-end regression.

### 5. The allowlist still governs NEW listings (positive control for step 4)
- **Action**: start a fresh submission for the same type.
- **Expect**: only the type's allowed categories (Business: 10 + placeholder). The union in step 4 applies to the edited listing only.

### 6. Role ladder
| Actor | Call | Expect |
|---|---|---|
| Owner | steps 1-5 | as above |
| Another member | `POST /submit` with `listing_id` of the owner's listing | 403, terms unchanged |
| Logged out | same | 401/403 |
| Admin / editor | same | allowed (control); admin saving a member's listing edits it (2.0.0) |

### 7. Mobile
Edit form at 390: Category select visible, no horizontal scroll, select >= 40px tall.

## Cleanup
Delete the probe listings. Steps 4-5 are read-only: do not save the edit form of a seeded listing.

## Fail diagnostics
| Symptom | File |
|---|---|
| Siblings deleted | `includes/rest/class-submission-controller.php:455-466` (the `array_slice( $existing, 1 )` preserve) |
| `categories` no longer replaces | `class-submission-controller.php:435-438` |
| Dropdown missing the real category | `blocks/listing-submission/render.php:393` (union with `$edit_cat_terms`) |

## Not verified
Steps 1-3 were run via `rest_do_request` as admin; step 4 via curl as the owner (server HTML, no browser); steps 5 (only the Restaurant/new-listing count was seen elsewhere: 16 options), 6 and 7 were not run.

Verified via: wp-cli REST, curl/DOM.
