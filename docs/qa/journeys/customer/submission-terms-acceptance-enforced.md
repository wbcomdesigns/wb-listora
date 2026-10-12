---
journey: submission-terms-acceptance-enforced
plugin: wb-listora
priority: critical
roles: [owner, other, guest, editor, admin]
covers: [agree_terms, submission-wizard-validation, wb_listora_require_terms_acceptance, terms-consent-meta, BC-10195308842, card-10153910549, submission-single-form-layout, submission-step-visibility, agree-terms-validation, dashboard-inline-edit, custom-required-pattern]
prerequisites:
  - "A member {owner} who can reach Add Listing; a published listing {listing} owned by {owner} with no recorded consent (`_listora_terms_accepted` empty), otherwise the box is pre-ticked"
  - "The submission block left at its default showTerms = true"
  - "For 201 outcomes: submission cost 0 and no published plan for the type (otherwise 402 `insufficient_credits` or 400 `listora_plan_required` precede), and a 20+ character `duplicate_explanation` when the title resembles an existing listing"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/terms-acceptance-enforced.md
  - ora:regression/single-form-steps-no-hidden-attr.md
fails_on: unrecorded
site_profile: required
---

# A listing cannot be created or updated without accepting the Terms, and the box is always reachable

## Why
Backend: `Submission_Controller` returns 400 `listora_terms_required` when `agree_terms` is omitted or false (`includes/rest/class-submission-controller.php:506-520`, filter `wb_listora_require_terms_acceptance`) and records acceptance in `_listora_terms_accepted` (GMT datetime, `TERMS_META_KEY` `:265`, written `:793` create, `:1137` update). Frontend: in single-form layout (dashboard edit, `blocks/listing-submission/render.php:430-445`, filter `wb_listora_edit_auto_single_form`; or Settings > Submissions "Submission form style") no step carries `hidden`, and `agree_terms` uses `data-listora-required="agree_terms"` instead of native `required`, so a theme `[hidden]{display:none!important}` cannot leave an unfocusable required control that blocks "Update Listing" silently.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Log in as {owner} (`?autologin=`). In `wp eval-file` runs add `add_filter( 'wb_listora_rate_limit_bypass', '__return_true' )` (submissions limited to 10/hour/IP, 429 `listora_rate_limit`).
- Edit URL: `$DASH/?tab=listings&action=edit&id=$LISTING_ID`; wizard: `$SITE_URL/add-listing/`.

## Steps

### 1. Server refuses a submission with no consent
- **Action**: `POST /wp-json/listora/v1/submit` as {owner} (nonce) with valid `title`, `listing_type`, `description`; `agree_terms` omitted, then `false`, then `true`.
- **Expect**: 400 `listora_terms_required` for the first two; 201 for `true` (status `pending`, meta `_listora_terms_accepted` a datetime). A 201 on either of the first two is the regression.

### 2. The wizard refuses, visibly
- **Action**: walk Add Listing to Preview, filling only active required fields (inactive type blocks are `disabled` and carry duplicate names such as several `meta_address[address]`). Leave "I agree to the Terms of Service" unticked, click Submit.
- **Expect**: no request to `/submit`; form still visible, success panel hidden; `.listora-submission__field-error--agree-terms` reads "Please accept the Terms of Service to continue." and is computed-visible (`display !== 'none'`, `visibility !== 'hidden'`, height > 0). Repeat at 390: message inside the viewport, no horizontal scroll.

### 3. Accepting works and is recorded (positive control for step 2)
- **Action**: tick the checkbox, Submit.
- **Expect**: success panel; `get_post_meta( <ID>, "_listora_terms_accepted", true )` is a datetime. Empty is the regression.

### 4. The escape hatch
- **Action**: `add_filter( 'wb_listora_require_terms_acceptance', '__return_false' )`, repeat step 1 without `agree_terms`.
- **Expect**: 201. Without the filter: 400. A filter that no longer changes the outcome strands integrators.

### 5. Consent on an update is recorded
- **Action**: {owner} edits {listing} (published, no `_listora_terms_accepted`, made in wp-admin or imported) on the frontend: update without `agree_terms`; with `agree_terms=false`; with the box ticked.
- **Expect**: 200 and nothing recorded (a partial edit is not consent); 400 `listora_terms_required`; 200 with `_listora_terms_accepted` stamped, and reopening the edit form shows the box pre-ticked. **Fail diagnostic**: box unticked on every edit -> `update_listing()` stamps consent only on the draft->live transition (`:1129-1139`).

### 6. Single-form (dashboard edit) emits no `hidden`
- **Action**: open the edit URL; evaluate `[...document.querySelectorAll('.listora-submission__step')].map(s => [s.dataset.step, s.hasAttribute('hidden')])`.
- **Expect**: wrapper has `listora-submission--single-form`; steps `type, basic, details, media, preview`, all `false`; each computes `display: block`.

### 7. Survives a hostile theme reset
- **Action**: inject `[hidden]{display:none!important}`, re-read every step's computed `display`.
- **Expect**: all `block`; `input[name="agree_terms"]` has a non-null `offsetParent`.

### 8. Terms uses custom validation, not native `required`
- **Expect**: `input[name="agree_terms"].required === false`, `dataset.listoraRequired === 'agree_terms'`, `aria-required="true"` (`templates/blocks/listing-submission/step-preview.php:157, 173`; with `showTerms` off a hidden `agree_terms=1` is sent, `:185`).

### 9. Negative path in single-form is visible, not silent
- **Action**: leave terms unticked (listing with no consent on file), click the submit button ("Update Listing" for a live listing, "Submit Listing" for a draft).
- **Expect**: no navigation, no POST; `.listora-submission__field-error--agree-terms` un-hides with "Please accept the Terms of Service to continue."; the field wrapper gains `is-invalid`.

### 10. Happy path saves
- **Action**: tick terms, click submit.
- **Expect**: `POST /wp-json/listora/v1/submit` (body carries `listing_id`) returns 200; `post_modified` advances; success card "Listing Updated!" for a live listing, "Listing Submitted!" (with the real outcome) for a draft; no new plugin lines in `debug.log`.

### 11. Wizard mode is unchanged (positive control)
- **Action**: open `$SITE_URL/add-listing/`.
- **Expect**: `data-step="type"` visible (no `hidden`); `basic`, `details`, `media`, `preview` carry `hidden`; stepper visible; "Save Draft" button present but hidden on the Type step by JS.

### 12. The terms gate across draft, publish and edit (`POST /listora/v1/submit` as {owner}, `plan_id` sent when a plan exists)
| Request | Expect |
|---|---|
| `status=draft`, no `agree_terms` | `201`, a draft (mid-wizard autosave); `_listora_terms_accepted` empty (no consent given) |
| live create, no `agree_terms` | `400 listora_terms_required` |
| publish that draft (`listing_id`), no `agree_terms` | `400 listora_terms_required`; post stays `draft` |
| publish that draft with `agree_terms=1` | consent stamped (`_listora_terms_accepted` = a date); with 0 credits `402 insufficient_credits`, listing stays draft "Awaiting Credits" |
| edit of a LIVE listing, `agree_terms` omitted | allowed (an edit is not a fresh acceptance) |
| edit with `agree_terms=0` | `listora_terms_required` (not exercised: needs a live, planned listing) |
- **On fail**: a refused draft means autosave is dead; a publish allowed without consent is a hole in the legal gate (`check_terms_acceptance()` `:482-520`, draft exemption `:677-695`, `TERMS_META_KEY` `:265`).
- **Note**: a monetised directory answers `listora_plan_required` before the terms check on the publish transition; always send `plan_id` when probing.

## Role ladder
| Actor | Expect |
|---|---|
| {owner} | steps 1-11; edit URL shows single-form with the terms checkbox (`name="agree_terms"`) |
| {admin} / {editor} editing a member's listing | not asked to accept again (2.0.0, `edit_others_listora_listings`, `render.php:88-92`): only `<input type="hidden" name="agree_terms" value="1">`, no `agree_terms` required, 200 |
| {other} | cannot edit the listing: no edit form (404/empty), submit returns 403 `listora_forbidden` |
| {guest} | `/add-listing/` and the edit URL show sign-in; `POST /submit` refused before the terms check |

## Pass criteria
1. No create/update succeeds without consent (server) and the wizard shows the visible error (client).
2. Acceptance is stamped on create and on a consenting update.
3. Single-form steps never carry `hidden`, even under a hostile reset; terms uses the custom validation pattern.
4. The filter escape hatch changes the outcome; wizard mode keeps its hidden steps.

## Fail diagnostics
| Symptom | File |
|---|---|
| 201 without consent | `includes/rest/class-submission-controller.php:506-520` (`check_terms_acceptance()` `:482-520`) |
| Wizard submits without the box | `src/blocks/listing-submission/view.js` `handleSubmission`/`validateStep()` (message `includes/class-assets.php:279`) |
| No consent meta | `class-submission-controller.php:793` (create), `:1137` (update) |
| Error present but invisible | `blocks/listing-submission/style.css` `.listora-submission__field-error`; `:199,298-311` |
| Single-form step hidden | `blocks/listing-submission/render.php:516` (`is_single_form`), `templates/blocks/listing-submission/step-{basic,details,media,preview}.php` |

## Not verified
Steps 1 and 4 and the 201 meta in step 3 ran via `rest_do_request`; the single-form DOM (steps 6, 8, 11) was read by curl as owner and admin. Steps 2, 5, 7, 9, 10, the editor/admin rows, 390 and dark were not run in a browser.

## Cleanup
Delete every probe listing; restore plan and cost settings; remove the filter mu-plugin.
