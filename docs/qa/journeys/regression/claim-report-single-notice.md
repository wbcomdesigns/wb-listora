---
journey: claim-report-single-notice
plugin: wb-listora
priority: normal
roles: [member]
covers: [claims, reports, toast, listing-detail, card-10336063667, card-10336378720]
prerequisites:
  - "A member and a listing they don't own or have claimed"
estimated_runtime_minutes: 4
---

# A claim or report confirms once, inside the dialog

Regression sentinel for `submitClaim` / `submitReport` in `src/interactivity/store.js`, `.listora-detail__report-message` styling in `blocks/listing-detail/style.css`, and `.listora-toast` wrapping in `assets/css/listora-base.css`.

## Background

Cards 10336063667 and 10336378720. Both actions wrote the inline dialog message and also fired a floating toast, so the confirmation showed twice; the report message had no styling. The toast set `white-space: nowrap` with `max-width: 360px`, so a long message ran past its background on desktop.

## Steps

### 1. Report
Report the listing (any reason) -> **one** styled message in the dialog ("Report submitted. Thank you."), no toast. An error (e.g. report twice) also shows only in the dialog.

### 2. Claim
Claim the listing with proof text -> **one** message in the dialog with "View my claims", no toast.

### 3. Long toast
In the console: `listoraToast( 'Claim submitted — we will email you when it is reviewed, plus some extra words to wrap.', 'success' )` at 1440 -> the text wraps inside the green box; nothing runs past it.

### 4. Restore
Delete the test claim (`{prefix}listora_claims`) and report (`_listora_listing_reports_{id}` option).
