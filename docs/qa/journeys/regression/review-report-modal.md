---
journey: review-report-modal
plugin: wb-listora
priority: high
roles: [anonymous, subscriber]
covers: [listing-reviews-block, review-report-rest, report-modal-accessible, iapi-modal-getter, no-native-prompt, focus-management]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Combo profile (Free + Pro active, buddyx theme) — the listing-reviews block renders on a listing detail page"
  - "A published listing with at least one approved review NOT authored by the reporter exists (capture LISTING_URL + REVIEW_ID)"
  - "A reporter subscriber user exists (autologin name e.g. 'combo')"
estimated_runtime_minutes: 5
covers_card: null
covers_commit: ea6e027
---

# Reporting a review opens an accessible focus-trapped dialog — never a native prompt()

Regression sentinel for M4 (`ea6e027`). The listing-reviews block's
`showReportModal()` called `window.prompt()` directly
(`src/blocks/listing-reviews/view.js`) — inaccessible to screen readers and
blocked under strict Content-Security-Policy. The fix moves the action into the
shared store (`src/interactivity/store.js`, per the all-actions-in-store rule)
and opens a page-level `role="dialog" aria-modal="true"` modal reusing the
`.listora-detail__modal` family (`templates/blocks/listing-reviews/reviews.php`).
The Reason `<select>` options resolve from `\WBListora\Admin\Report_Metabox::reasons()`
(the D11 canonical PHP source, exposed as `listoraI18n.reportReasons` from
`includes/class-assets.php`). Focus moves into the dialog on open
(`#listora-report-review-dialog[tabindex="-1"]`), Escape closes it, focus
returns to the Report button, and guests are routed to the login modal first.

## Setup

- Site: `$SITE_URL` (`http://directory.local`).
- LISTING_URL = a listing-detail page whose Reviews block shows at least one review the reporter did not author.
- Reporter subscriber autologin: `?autologin=combo`.

## Steps

### 1. The action lives in the shared store, not view.js, and uses no prompt()
- **Action**:
  ```
  grep -n "prompt(" src/blocks/listing-reviews/view.js build/blocks/listing-reviews/view.js
  grep -n "showReportModal\|submitReviewReport\|isReportReviewModalOpen\|closeReportReviewModal\|handleReportReviewKeydown" src/interactivity/store.js
  ```
- **Expect**: ZERO `prompt(` occurrences in `view.js` (src AND build). `store.js` defines `showReportModal`, `setReportReviewReason`, `submitReviewReport`, `closeReportReviewModal`, `handleReportReviewKeydown` actions and the `isReportReviewModalOpen` derived getter (bound to `state.activeModal === 'report-review'`, per the IAPI directive rule — a getter, never an inline `===` in the directive).
- **On fail**: `ea6e027` — native prompt reintroduced or the action left in view.js.

### 2. Modal markup is an accessible dialog reusing the shared family
- **Action**: `grep -n "listora-report-review-dialog\|role=\"dialog\"\|aria-modal\|data-wp-class--is-open=\"state.isReportReviewModalOpen\"\|Report_Metabox::reasons" templates/blocks/listing-reviews/reviews.php`
- **Expect**: a single page-level `#listora-report-review-modal` with `data-wp-class--is-open="state.isReportReviewModalOpen"`; the content node `#listora-report-review-dialog` carries `role="dialog" aria-modal="true" aria-labelledby="listora-report-review-title" tabindex="-1"`; the Reason `<select>` is populated by `foreach ( \WBListora\Admin\Report_Metabox::reasons() ... )`; a `<button type="submit">` Submit Report. The Report trigger on each card is `templates/blocks/listing-reviews/review-card.php` `.listora-reviews__report-btn` with `data-wp-on--click="actions.showReportModal"` and a `data-wp-context` carrying `reviewId`.
- **On fail**: modal markup missing or the reason list hardcoded instead of from `Report_Metabox::reasons()`.

### 3. Guest clicking Report gets the login modal, not the report dialog
- **Action**: open LISTING_URL logged-OUT. Click the "Report" button on a review card.
- **Verify**:
  ```js
  // login modal opens, report dialog does NOT
  document.querySelector('#listora-report-review-modal').classList.contains('is-open') === false;
  // the shared login modal is the one that became active
  ```
- **Expect**: `state.activeModal === 'login'`; the report dialog stays closed (`POST /reviews/{id}/report` requires auth, so guests are gated to login first). No native `prompt()` / `alert()` fires anywhere.
- **On fail**: report dialog opens for a logged-out user, or a native dialog fires.

### 4. Logged-in: dialog opens, focus moves in, reason required
- **Action**: open LISTING_URL with `?autologin=combo`. Click "Report" on a review the reporter did not author.
- **Verify**:
  ```js
  const dlg = document.getElementById('listora-report-review-dialog');
  document.querySelector('#listora-report-review-modal').classList.contains('is-open') === true;
  document.activeElement === dlg;                 // focus moved INTO the dialog
  dlg.getAttribute('role') === 'dialog';
  dlg.getAttribute('aria-modal') === 'true';
  // Reason select present, no option pre-selected
  document.getElementById('listora-report-review-reason').value === '';
  ```
- **Expect**: dialog visible, focus inside it, Reason `<select>` empty. Clicking **Submit Report** with no reason selected does NOTHING (the `<select>` is `required` and `submitReviewReport` returns early when `! reason`) — no REST call fires (watch the Network tab; zero `/reviews/*/report` requests).
- **On fail**: focus stays at page top, or an empty-reason submit fires a request.

### 5. Pick a reason + Submit → success toast, modal closes, focus returns
- **Action**: select a reason (e.g. "Inaccurate"), click **Submit Report**. Watch the Network tab.
- **Verify**: exactly one `POST /wp-json/listora/v1/reviews/REVIEW_ID/report` returns 2xx; a success toast appears (`window.listoraToast`, text from `listoraI18n.reportSubmitted`); the dialog closes (`is-open` removed); and:
  ```js
  // focus restored to the Report button that opened the dialog
  document.activeElement.classList.contains('listora-reviews__report-btn') === true;
  ```
- **Expect**: report persists (admin Reports surface gains the row for REVIEW_ID), modal closes, focus returns to the trigger.
- **On fail**: no toast, modal stays open, or focus lands on `<body>`.

### 6. Escape closes the dialog and restores focus
- **Action**: open the report dialog again (step 4), then press the **Escape** key.
- **Expect**: `handleReportReviewKeydown` closes the modal (`state.activeModal = null`, `reportReviewId`/`reportReviewReason` reset) and focus returns to the Report button. The backdrop click (`.listora-detail__modal-backdrop`) also closes it.
- **On fail**: Escape does nothing, or focus is lost.

### Cleanup
- Clear the QA report on REVIEW_ID (`DELETE FROM {prefix}review_votes`/reports row created during the run, or revert via the admin Reports surface).

## Fail diagnostics
- Native prompt back / action in view.js → `src/blocks/listing-reviews/view.js`, `src/interactivity/store.js`.
- Dialog not accessible (missing role/aria/tabindex) → `templates/blocks/listing-reviews/reviews.php`.
- Reason list drift → `includes/class-assets.php` (`listoraI18n.reportReasons`) + `includes/admin/class-report-metabox.php::reasons()`.
- Standalone-block styling broken → `blocks/listing-reviews/style.css` + `style-rtl.css` (the `.listora-detail__modal` rules brought in for standalone render).

## Notes
- Pairs with `regression/review-report-reason-enum.md` (the enum the dialog's `<select>` feeds) and `regression/report-listing.md` (the listing-report flow that already used this modal pattern). The load-bearing sentinel here is "no native `prompt()`" — step 1's grep is the canary.
