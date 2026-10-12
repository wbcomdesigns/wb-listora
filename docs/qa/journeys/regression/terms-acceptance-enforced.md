---
journey: terms-acceptance-enforced
plugin: wb-listora
priority: critical
roles: [member, other-member, logged-out, editor, admin]
covers: [agree_terms, submission-wizard-validation, wb_listora_require_terms_acceptance, terms-consent-meta, BC-10195308842]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member account that can reach the Add Listing form (QA-J1-member)"
  - "The submission block left at its default showTerms = true"
  - "For 201 outcomes: submission cost 0 and no published plan for the type (otherwise 402 `insufficient_credits` or 400 `listora_plan_required` precede the result), and a 20+ character `duplicate_explanation` when the title resembles an existing listing"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A listing cannot be created without accepting the Terms of Service

Backend: `Submission_Controller` refuses a submission with no consent: `agree_terms` omitted and explicit `false` both return 400 `listora_terms_required` (`includes/rest/class-submission-controller.php:506-520`, filter `wb_listora_require_terms_acceptance`), and records acceptance in post meta `_listora_terms_accepted` as a GMT datetime (`TERMS_META_KEY`, `:265`, written at `:793` on create and `:1137` on update). Frontend: the Preview step carries `input[name="agree_terms"]` and `p.listora-submission__field-error--agree-terms` (`templates/blocks/listing-submission/step-preview.php:157, 173`); with `showTerms` off a hidden `agree_terms=1` is sent (`:185`).

## Setup
Log in as the member (`?autologin=QA-J1-member`). In `wp eval-file` runs add `add_filter( 'wb_listora_rate_limit_bypass', '__return_true' )` (submissions are limited to 10/hour/IP, 429 `listora_rate_limit`).

## Steps

### 1. Server refuses a submission with no consent
- **Action**: `POST /wp-json/listora/v1/submit` as the member (nonce required) with a valid `title`, `listing_type`, `description`; `agree_terms` omitted.
- **Expect**: 400 `listora_terms_required` (verified 2026-10-12). Repeat with `agree_terms: false`: same 400 (verified). With `agree_terms: true`: 201 (verified: status `pending`, meta `_listora_terms_accepted` = `2026-10-11 21:52:18`). A 201 on either of the first two is the regression.

### 2. The wizard refuses, visibly
- **Action**: walk Add Listing to Preview, filling only active required fields at each step (inactive type blocks are `disabled` and carry duplicate names such as several `meta_address[address]`). Leave "I agree to the Terms of Service" unticked, click Submit.
- **Expect**: no request to `/submit`; form still visible, success panel hidden; `.listora-submission__field-error--agree-terms` reads "Please accept the Terms of Service to continue." and is computed-visible:
```js
const err = document.querySelector('.listora-submission__field-error--agree-terms');
const cs = getComputedStyle(err);
cs.display !== 'none' && cs.visibility !== 'hidden' && err.getBoundingClientRect().height > 0
```
- Repeat at 390: message stays inside the viewport, no horizontal scroll.

### 3. Accepting works and is recorded (positive control for step 2)
- **Action**: tick the checkbox, Submit.
- **Expect**: success panel; `wp eval 'echo get_post_meta( <ID>, "_listora_terms_accepted", true );'` is a datetime. Empty is the regression.

### 4. The escape hatch
- **Action**: `add_filter( 'wb_listora_require_terms_acceptance', '__return_false' )`, repeat step 1 without `agree_terms`.
- **Expect**: 201 (verified 2026-10-12). Without the filter: 400. A filter that no longer changes the outcome means integrators are stranded.

### 5. Consent given on an update is recorded
A published listing with no `_listora_terms_accepted` (made in wp-admin or imported), edited by its owner on the frontend:
- Update without `agree_terms` -> 200, nothing recorded (a partial edit is not consent).
- Update with `agree_terms=false` -> 400 `listora_terms_required`.
- Update with the box ticked -> 200 and `_listora_terms_accepted` stamped; reopening the edit form shows the box pre-ticked.
- **Fail diagnostic**: box unticked on every edit -> `update_listing()` stamps consent only on the draft->live transition (`class-submission-controller.php:1129-1139`).

### 6. Role ladder
| Actor | Expect |
|---|---|
| Member (owner) | steps 1-5 |
| Admin / editor editing a member's listing | not asked to accept again (2.0.0): no `agree_terms` required, 200 |
| Another member | cannot edit the listing (403) |
| Logged out | `/add-listing/` shows sign-in; `POST /submit` refused before the terms check |

## Cleanup
Delete every probe listing.

## Fail diagnostics
| Symptom | File |
|---|---|
| 201 without consent | `includes/rest/class-submission-controller.php:506-520` |
| Wizard submits without the box | `src/blocks/listing-submission/view.js` handleSubmission validation (`agree_terms` message from `includes/class-assets.php:279`) |
| No consent meta | `class-submission-controller.php:793` (create), `:1137` (update) |
| Error present but invisible | `blocks/listing-submission/style.css` `.listora-submission__field-error` display rule |

## Not verified
Steps 1 and 4 and the 201 meta in step 3 were run via `rest_do_request` as QA-J1-member (filter-bypassed rate limit, cost 0, plan unpublished, then restored). Steps 2 (wizard) and 5, and the editor/admin rows were not run; the 390 and dark checks were not run.

Verified via: wp-cli `rest_do_request`.
