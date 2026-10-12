---
journey: claim-report-single-notice
plugin: wb-listora
priority: normal
roles: [member]
covers: [claims, reports, toast, listing-detail, card-10336063667, card-10336378720]
prerequisites:
  - "A member and a listing they don't own or have claimed"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# A claim or report confirms once, inside the dialog

Regression sentinel for `submitClaim` / `submitReport` in `src/interactivity/store.js`, the `.listora-detail__report-message` / `.listora-detail__claim-message` styling in `blocks/listing-detail/style.css`, and the `.listora-toast` wrapping in `assets/css/listora-base.css`. Both actions once wrote the inline dialog message AND fired a floating toast, so the confirmation showed twice; the report message had no styling; and the toast ran past its background on desktop. Contract now: one message, inside the dialog, no toast; the toast is only a fallback when a theme template override removed the message element.

Roles: a logged-in member who is not the author and has not claimed or reported this listing (`qa_vendor_03`). Negative controls: logged out (clicking Report/Claim opens the login modal, no form), the listing author (neither control renders).

## Setup

- `LISTING_URL`: a published listing the member does not own (QA site: listing 24 `Sakura House`, `$SITE_URL/listing/sakura-house/`; confirm with `wp eval 'echo get_permalink(24);'`). Use one that has no claim row and no report option from this member.
- The Report control needs the `report_listings` feature on (default on).

## Steps

### 1. Report shows one styled message in the dialog
- **Action**: as the member click **Report**, choose any reason in `#listora-report-reason` (options: Inaccurate information, Spam or misleading, Permanently closed, Duplicate listing, Offensive or inappropriate, Something else), click **Submit Report**.
- **Expect**: the form body (`.listora-detail__report-body`) is hidden and `.listora-detail__report-message.listora-detail__report-message--success` reads "Report submitted. Thank you." with a tinted success background. NO `.listora-toast` appears in `.listora-toast-container`. REST: `POST /listora/v1/listings/{id}/report` returned 200 `{"reported":true}`.

### 2. A second report shows the error only in the dialog
- **Action**: report the same listing again as the same member.
- **Expect**: `.listora-detail__report-message--error` reads "You have already reported this listing." (REST 409 `listora_already_reported`); no toast; the submit button is re-enabled with its "Submit Report" label.

### 3. Claim shows one message with "View my claims"
- **Action**: as the same member click **Claim**, type proof text in `#listora-claim-proof`, click **Submit Claim**.
- **Expect**: `.listora-detail__claim-body` and the submit button are hidden; `.listora-detail__claim-message--success` shows "Claim submitted — we'll email you when it's reviewed." and a **View my claims** button that links to the dashboard `#claims`. NO toast. REST `POST /listora/v1/claims` returned 201.

### 4. Long toast wraps
- **Action**: at 1440 and 390, in the console: `listoraToast( 'Claim submitted — we will email you when it is reviewed, plus some extra words to wrap.', 'success' )`.
- **Expect**: the text wraps inside the toast box (max-width 360px on desktop, full width with `white-space: normal` under 480px); nothing runs past the background.

### 5. Positive control and role ladder
- **Control**: step 4 proves the toast component itself still works, so "no toast" in steps 1 to 3 is a real absence, not a broken toast.
- **Logged out**: open `LISTING_URL`, click **Report** or **Claim**: the login modal opens (title "Log in to report this listing" / "Log in to claim this listing"), no form, no request.
- **Owner** (`varundubey` for listing 24): neither **Claim** nor **Report** renders.
- **Editor** and **administrator** (non-authors) get both controls, like another member.

## Cleanup

Delete the test claim (Claims screen **Delete** or `DELETE /listora/v1/claims/{id}`) and the report: `wp option delete _listora_listing_reports_{id}`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Report confirms twice (dialog + toast) | toast fired alongside the message | `src/interactivity/store.js:1968` (`submitReport`, toast only in the `else if`) |
| Claim confirms twice | same in claim | `src/interactivity/store.js:2454` (`submitClaim`, toast only when `! msgEl`) |
| Report message unstyled | selector lost | `blocks/listing-detail/style.css:1644-1670` |
| Toast text runs past its box | `white-space: nowrap` back on `.listora-toast` | `assets/css/listora-base.css:739` and the `@media` override at `:916` |
| Message elements missing | template override | `blocks/listing-detail/render.php:979` (claim), `:1020` (report) |
| Strings differ | i18n map | `includes/class-assets.php:294-301` |

## Not verified

REST behaviour and strings were verified live (`POST /listings/24/report` 200 then 409, logged-out 401; claim 201 in the sibling journeys). The in-dialog DOM states (steps 1 to 3), the toast wrap (step 4) and the login-modal copy for Report were NOT run in a browser; they are verified against source only.
