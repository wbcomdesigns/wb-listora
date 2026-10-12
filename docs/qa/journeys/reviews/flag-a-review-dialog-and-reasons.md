---
journey: flag-a-review-dialog-and-reasons
group: reviews
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, owner, administrator]
covers: [listing-reviews-block, review-report-rest, report-reason-enum, report-modal-accessible, iapi-modal-getter, no-native-prompt, focus-management, rest-arg-validation]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published {listing} with an approved review NOT authored by the reporter (capture LISTING_URL + REVIEW_ID)"
  - "A reporter {other} (subscriber)"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:regression/review-report-modal.md", "ora:regression/review-report-reason-enum.md"]
---

# Flagging a review opens an accessible focus-trapped dialog and accepts only the review reasons

## Why
Visitors flag abusive reviews through one shared dialog and `POST /reviews/{id}/report`; a native `prompt()`, an inaccessible dialog or an enum that drifts from the dialog loses reports or lets junk through.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Clean before/after: `delete_option( '_listora_review_reports_' . REVIEW_ID )` (`Reviews_Model::REPORTS_OPTION_PREFIX`).
- Store actions `src/interactivity/store.js`: `showReportModal`, `setReportReviewReason`, `handleReportReviewKeydown`, `closeReportReviewModal`, `submitReviewReport`, getter `isReportReviewModalOpen`. Dialog template `templates/blocks/reviews/report-modal.php` (included by the Reviews block and `blocks/listing-detail/tabs.php`): `#listora-report-review-modal` > `#listora-report-review-dialog[role=dialog][aria-modal=true][aria-labelledby=listora-report-review-title][tabindex=-1]`, title "Report This Review", `select#listora-report-review-reason`, **Submit Report** / **Cancel**; trigger `.listora-reviews__report-btn` (`review-card.php:74`).
- Reasons come from ONE source, `wb_listora_get_review_report_reasons()` (`includes/class-template-helpers.php:805`, filter `wb_listora_review_report_reasons`): `spam | offensive | off_topic | fake | private_info | conflict | other`. The listing vocabulary (`inaccurate, spam, closed, duplicate, offensive, other`) is a DIFFERENT list used by `listing-detail/flag-a-listing.md`.

## Steps

### 1. No `prompt()`; action in the store
- **Action**: `grep -n "prompt(" src/blocks/listing-reviews/view.js build/blocks/listing-reviews/view.js`.
- **Expect**: only a comment in `view.js`, no call; none in `build/`.

### 2. Modal markup and reason list
- **Action**: read `report-modal.php` and the rendered dialog.
- **Expect**: single page-level modal with the attributes above; options rendered by `foreach ( wb_listora_get_review_report_reasons() )`, first option `value=""` "Select a reason...".

### 3. Guest clicking Report gets the login modal
- **Action**: as {guest} click **Report** on a review.
- **Expect**: `state.activeModal==='login'`; `#listora-report-review-modal` has no `.is-open`; no `POST .../report`.
- **Read-back**: Network panel shows zero `/reviews/*/report` requests.

### 4. Logged in: dialog opens, focus moves in, reason required
- **Action**: as {other} click **Report** on a review they did not author; Submit with no reason.
- **Expect**: `.is-open`; `document.activeElement===#listora-report-review-dialog`; select value `''`; Submit does nothing (select `required`, `submitReviewReport` returns early): zero requests.

### 5. Pick a reason and submit
- **Action**: choose `fake`, **Submit Report**.
- **Expect**: exactly one `POST /wp-json/listora/v1/reviews/REVIEW_ID/report` `200 {"reported":true}`; toast "Report submitted. Thank you." (`listoraI18n.reportSubmitted`); dialog closes; focus returns to `.listora-reviews__report-btn`.
- **Read-back**: option `_listora_review_reports_REVIEW_ID` holds one row for {other}.

### 6. Every valid reason succeeds; invalid ones are 400
- **Action**: curl with cookie + `X-WP-Nonce`: each of the seven keys on distinct reviews (or delete the option between calls); then `{"reason":"banana"}`, `{"reason":""}`, `{"reason":"the food was cold"}`, and a LISTING key `{"reason":"inaccurate"}`.
- **Expect**: `200 {"reported":true}` for the seven; `400 rest_invalid_param`, `params.reason` "reason is not one of spam, offensive, off_topic, fake, private_info, conflict, and other." for the rest. Positive control: the valid call succeeds first.
- **Read-back**: no option row written by a 400.

### 7. Duplicate
- **Action**: {other} reports the same review twice.
- **Expect**: second `409 listora_already_reported`. (Pending/missing review and reviews-off refusals: `reviews/review-actions-gated-on-every-route.md` step 2.)

### 8. Lockstep with the dialog
- **Action**: `wp eval 'echo implode(",", array_keys( wb_listora_get_review_report_reasons() ));'`.
- **Expect**: equals the option values of `#listora-report-review-reason` and the enum in step 6's message. A reason added through the filter appears in both.

### 9. Escape and backdrop
- **Action**: reopen, press Escape; separately click `.listora-detail__modal-backdrop`.
- **Expect**: closes, `reportReviewId`/`reportReviewReason` reset, focus back on the trigger.

### 10. Mobile (390) and dark
- **Expect**: dialog fits, close/Submit/Cancel >= 40px, no horizontal scroll; readable in dark mode.

## Role ladder
| Actor | Expect |
|---|---|
| {guest} | login modal; REST `401 listora_unauthorized` (code-read, not called) |
| {other} (non-author) | dialog, `200` |
| Review's author / listing {owner} | no Report offered on own review (Not verified live) |
| {admin} (control) | can report; same enum; row in the Reports option |

## Pass criteria
1. No native prompt; dialog accessible with focus management and Escape/backdrop close.
2. Seven review reasons accepted, anything else `400 rest_invalid_param`; duplicate `409`.
3. REST enum, dialog options and filter stay in lockstep.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Native prompt back / action in view.js | regression | `src/blocks/listing-reviews/view.js`, `src/interactivity/store.js` |
| Dialog not accessible | markup | `templates/blocks/reviews/report-modal.php:18-20` |
| Reason drift | enum/dialog source differ | `includes/class-template-helpers.php:805`, `includes/rest/class-reviews-controller.php:202`, `:976` (docblock `:968` is stale) |

## Not verified
Steps 3, 4, 5 (UI), 9, 10 need a logged-in browser; the REST half of 5, 6, 7 was verified in-process; logged-out 401 and admin control were read from code (`review_interaction_permissions()` ~1183).

## Cleanup
`delete_option( '_listora_review_reports_' . REVIEW_ID )` for every review used.
