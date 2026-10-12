---
journey: edit-permission-same-on-form-and-rest
plugin: wb-listora
priority: critical
roles: [administrator, editor, member-owner, member-other, anonymous]
covers: [edit-permission-one-rule, wb_listora_user_can_edit_listing, frontend-edit-form, submit-rest-routes, 2.0.0-admin-edit-no-terms-prompt]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published {listing} owned by {owner}; a second member {other}; {admin} and {editor}"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light]
supersedes:
  - ora:regression/one-rule-per-behaviour.md
fails_on: unknown (found by the 2026-10-11 duplicate-logic audit, copies disagreed; pre-fix sha not recorded)
site_profile: required
---

# One edit-permission rule governs the frontend form and both save routes

## Why
Edit permission was coded twice and the copies disagreed. Now `wb_listora_user_can_edit_listing()` decides for the edit form, `POST /listora/v1/submit` and `PUT /listora/v1/submit/<id>`: a stranger must be refused on all three, an admin/moderator allowed on all three. Sibling one-rule behaviours of the same audit: moderation transitions (`admin/listing-approve-reject-and-bulk-actions.md`), partial type PUT (`admin/listing-types-crud.md`), demo pack list (`admin/demo-import-quality.md`), page registry (`admin/settings-tabs-save-validate-reset.md`), open-now (`customer/open-now-badge-matches-search.md`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- REST nonce: `admin-ajax.php?action=rest-nonce` as the acting user.

## Steps

### 1. Admin edits a member's listing from the frontend
- **Action**: as {admin} open `/add-listing/?action=edit&id=<{listing} id>`, tick Terms only if shown (editing as admin or editor no longer asks again), "Update Listing".
- **Expect**: "Listing Updated!"; same ID, still owned by {owner}; no duplicate-review panel; no new listing.

### 2. Another member is refused on both save routes
- **Action**: as {other} `POST /listora/v1/submit` with that `listing_id`, and `PUT /listora/v1/submit/<id>`.
- **Expect**: POST 403 `listora_forbidden` "You do not have permission to edit this listing."; PUT 403 `listora_forbidden` "You do not have permission to perform this action."; title unchanged; no listing created. The edit form URL for {other} shows a refusal, not the form.

### 3. The owner and a moderator are allowed (positive control)
- **Expect**: {owner} PUT 200; {editor} allowed via `wb_listora_user_can_edit_listing()`; {admin} 200.

### 4. Logged out
- **Expect**: both routes 401; the edit URL redirects to login.

### 5. Boundary input and retry (added)
- **Action**: PUT as {owner} with an empty title, 300-char title, markup, a foreign `listing_id` of another type; double-submit.
- **Expect**: validation errors not fatals; one listing, last write wins. Not verified live.

### 6. Lifecycle seeds (added)
- **Action**: repeat step 2/3 for a pending, draft, expired and trashed listing and a listing whose author was deleted.
- **Expect**: permission follows the same rule for each; no fatal. Not verified live.

## Role ladder

| Rung | Form | POST /submit | PUT /submit/<id> |
|---|---|---|---|
| {admin} (control) | allowed | 200 | 200 |
| {editor} | allowed | 200 | 200 |
| {owner} | allowed | 200 | 200 |
| {other} | refused | 403 | 403 |
| {guest} | login redirect | 401 | 401 |

## Pass criteria
The form and both save routes give the same answer for every rung; nothing is created or changed on a refusal.

## Fail diagnostics

| Symptom | File |
|---|---|
| Form and route disagree | `wb_listora_user_can_edit_listing()` (`includes/helpers.php`) bypassed by a controller |
| Admin edit asks for terms again | submission handler terms check |
| Stranger can PUT | `includes/rest/class-submission-controller.php` permission callback |

## Not verified
Steps 1 (form) and 4 in a browser; 5; 6. REST rungs were driven with `rest_do_request()`.

## Cleanup
None (no data created); revert any title changed in step 5.
