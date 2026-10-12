---
journey: single-form-steps-no-hidden-attr
plugin: wb-listora
priority: critical
roles: [subscriber, member-owner, another-member, administrator]
covers: [submission-single-form-layout, submission-step-visibility, agree-terms-validation, dashboard-inline-edit, custom-required-pattern]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing owned by the test user (verified: 845 owned by qa_vendor_01) - capture LISTING_ID"
  - "Terms checkbox enabled (the block's showTerms attribute, default true) and an owner whose listing has no recorded consent (`_listora_terms_accepted` empty), otherwise the box is pre-ticked"
estimated_runtime_minutes: 6
covers_card: 10153910549
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Single-form submission never emits `hidden` on its steps

Regression sentinel for "Update Listing does nothing". In single-form layout (the default when editing from the dashboard: `blocks/listing-submission/render.php:430-445`, filter `wb_listora_edit_auto_single_form`; or Settings > Submissions "Submission form style") no step carries `hidden`. `agree_terms` uses the block's own `data-listora-required="agree_terms"` pattern, not the native `required` attribute (`templates/blocks/listing-submission/step-preview.php:157`), so a theme `[hidden]{display:none!important}` cannot make an unfocusable required control block the submit silently.

Verified via (as qa_vendor_01 / varundubey, curl of the SSR DOM): single-form class, all five step nodes without `hidden`, `agree_terms` markup, wizard-mode hidden attributes, admin view. Steps 2, 4 and 5 (hostile CSS, error message, happy-path save) were NOT executed in a browser.

## Steps

### 1. Single-form (dashboard edit) emits no `hidden`
- **Action**: open `$SITE_URL/my-listings/?tab=listings&action=edit&id=LISTING_ID` (the dashboard page is `/my-listings/`). Evaluate:
  `[...document.querySelectorAll('.listora-submission__step')].map(s => [s.dataset.step, s.hasAttribute('hidden')])`
- **Expect** (observed): wrapper has `listora-submission--single-form`; steps `type, basic, details, media, preview`, all `false`; each computes `display: block`.

### 2. Survives a hostile theme reset
- **Action**: inject `[hidden]{display:none!important}`, re-read every step's computed `display`.
- **Expect**: all `block`; `input[name="agree_terms"]` has a non-null `offsetParent`.

### 3. Terms uses custom validation, not native `required`
- **Expect** (observed): `input[name="agree_terms"].required === false`, `dataset.listoraRequired === 'agree_terms'`, `aria-required="true"`.

### 4. Negative path is visible, not silent
- **Action**: leave terms unticked (listing with no consent on file), click the submit button (text "Update Listing" for a live listing, "Submit Listing" for a draft).
- **Expect**: no navigation, no POST; `.listora-submission__field-error--agree-terms` un-hides with "Please accept the Terms of Service to continue."; field wrapper gains `is-invalid`.

### 5. Happy path saves
- **Action**: tick terms, click submit.
- **Expect**: `POST /wp-json/listora/v1/submit` (body carries `listing_id=LISTING_ID`) returns 200; `wp post get LISTING_ID --field=post_modified` advances; success card heading "Listing Updated!" for a live listing, "Listing Submitted!" (with the real outcome) when a draft was submitted; no new plugin lines in `debug.log`.

### 6. Wizard mode is unchanged (positive control)
- **Action**: open `$SITE_URL/add-listing/`.
- **Expect** (observed): `data-step="type"` visible (no `hidden`); `basic`, `details`, `media`, `preview` carry `hidden`; stepper visible; "Save Draft" button present but hidden on the Type step by JS.

### 7. Role ladder
| Actor | Edit URL for LISTING_ID | Expect |
|---|---|---|
| member-owner (qa_vendor_01) | `/my-listings/?tab=listings&action=edit&id=845` | single-form, terms checkbox shown (`name="agree_terms"` checkbox) |
| another member (contributor1) | same URL | no edit form for a listing they cannot edit (404/empty), submit returns 403 `listora_forbidden` |
| logged out | same URL | login prompt, not the form |
| administrator / moderator (control) | same URL | single-form and **no terms checkbox** (observed: only `<input type="hidden" name="agree_terms" value="1">`) because `edit_others_listora_listings` (2.0.0, `render.php:88-92`) |

## Notes
`agree_terms` is also enforced server-side: `check_terms_acceptance()` (`includes/rest/class-submission-controller.php:482-520`) returns 400 `listora_terms_required` when it is missing/false (filter `wb_listora_require_terms_acceptance`). The older note that enforcement was client-only is stale.

## Likely files
`blocks/listing-submission/render.php:516` (`is_single_form`), `templates/blocks/listing-submission/step-{basic,details,media,preview}.php`, `step-preview.php:157-173`, `includes/class-assets.php:279` (message), `blocks/listing-submission/style.css:199,298-311`, `src/blocks/listing-submission/view.js` (`validateStep()`).
