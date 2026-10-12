---
journey: review-report-modal
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, owner]
covers: [listing-reviews-block, review-report-rest, report-modal-accessible, iapi-modal-getter, no-native-prompt, focus-management]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing with at least one approved review NOT authored by the reporter (capture LISTING_URL + REVIEW_ID; listing 59 review 14 here)"
  - "A reporter subscriber (autologin, e.g. subscriber2)"
estimated_runtime_minutes: 5
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Reporting a review opens an accessible focus-trapped dialog, never a native prompt()

The report action lives in the shared store (`src/interactivity/store.js`: `showReportModal` `:2152`, `setReportReviewReason` `:2190`,
`handleReportReviewKeydown` `:2194`, `closeReportReviewModal` `:2201`, `submitReviewReport` `:2216`, derived getter
`isReportReviewModalOpen` `:521`). The dialog is ONE shared template, `templates/blocks/reviews/report-modal.php`, included by the
Reviews block and by the listing page's own review list (`blocks/listing-detail/tabs.php`). Markup: `#listora-report-review-modal`
(`data-wp-class--is-open="state.isReportReviewModalOpen"`) containing `#listora-report-review-dialog[role=dialog][aria-modal=true]
[aria-labelledby=listora-report-review-title][tabindex=-1]`, title "Report This Review", `select#listora-report-review-reason` and
**Submit Report** / **Cancel**. Each card's trigger is `.listora-reviews__report-btn[data-wp-on--click="actions.showReportModal"]`
(`templates/blocks/listing-reviews/review-card.php:74`).

**Reasons are the REVIEW vocabulary** (`wb_listora_get_review_report_reasons()`, `includes/class-template-helpers.php:805`):
`spam` Spam or advertising, `offensive` Offensive or abusive language, `off_topic` Not about this listing, `fake` Fake or incentivised
review, `private_info` Contains personal information, `conflict` Conflict of interest (owner or competitor), `other` Something else.
The old listing vocabulary (`inaccurate`, `closed`, `duplicate`) is rejected by `/reviews/{id}/report`.

Role ladder: logged out gets the login modal; a signed-in non-author reports; the review's own author and the listing owner see no
Report on their own review (not asserted by this file, see Not verified); admin is the control (can report, row appears in Reports).

## Steps

### 1. No `prompt()`; action in the store (verified by grep 2026-10-12)
- `grep -n "prompt(" src/blocks/listing-reviews/view.js` finds only a comment (`:182` mentions the removed native prompt), no call.
  `build/blocks/listing-reviews/view.js` has no `prompt(`.

### 2. Modal markup (verified in `report-modal.php`)
- Single page-level modal, the attributes above, reasons rendered with `foreach ( wb_listora_get_review_report_reasons() ... )`,
  first option `value=""` "Select a reason...".

### 3. Guest clicking Report gets the login modal
- **Action**: open LISTING_URL logged out, click **Report** on a review.
- **Expect**: `state.activeModal === 'login'`; `#listora-report-review-modal` stays without `.is-open`; no native dialog, no
  `POST .../report`.

### 4. Logged in: dialog opens, focus moves in, reason required
- **Action**: as the reporter click **Report** on a review they did not author.
- **Expect**: modal has `.is-open`; `document.activeElement === #listora-report-review-dialog`; `#listora-report-review-reason` value `''`.
  **Submit Report** with no reason does nothing (select `required`, `submitReviewReport` returns early): zero `/reviews/*/report` requests.

### 5. Pick a reason and submit
- **Action**: choose "Fake or incentivised review" (`fake`), **Submit Report**.
- **Expect**: exactly one `POST /wp-json/listora/v1/reviews/REVIEW_ID/report` -> 200 `{"reported":true}`; toast
  (`listoraI18n.reportSubmitted` "Report submitted. Thank you."); dialog closes; focus returns to `.listora-reviews__report-btn`. A
  second report by the same user -> 409 `listora_already_reported` (verified over REST).

### 6. Escape and backdrop
- **Action**: reopen, press Escape (and, separately, click the backdrop `.listora-detail__modal-backdrop`).
- **Expect**: closes, `reportReviewId`/`reportReviewReason` reset, focus back on the trigger.

### 7. Mobile (390)
- **Expect**: the dialog fits the viewport, close/Submit/Cancel >= 40px, no horizontal page scroll.

### Cleanup
`delete_option( '_listora_review_reports_' . REVIEW_ID )` (`Reviews_Model::REPORTS_OPTION_PREFIX`).

## Fail diagnostics
- Native prompt back / action in view.js: `src/blocks/listing-reviews/view.js`, `src/interactivity/store.js:2152`.
- Dialog not accessible: `templates/blocks/reviews/report-modal.php:18-20`.
- Reason drift: `includes/class-template-helpers.php:805` + `includes/rest/class-reviews-controller.php:202` (enum).

## Not verified
Steps 3-7 (interaction, focus, toast, 390px) need a browser logged in; steps 1-2 and the REST half of 5 were verified.
