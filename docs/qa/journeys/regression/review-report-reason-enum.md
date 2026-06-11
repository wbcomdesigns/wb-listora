---
journey: review-report-reason-enum
plugin: wb-listora
priority: normal
roles: [subscriber, administrator]
covers: [review-report-rest, report-reason-enum, report-metabox-single-source, rest-arg-validation]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "An approved review NOT authored by the reporter exists (capture REVIEW_ID)"
  - "A reporter subscriber user exists"
estimated_runtime_minutes: 4
covers_card: null
covers_commit: 1d87a15
---

# Review-report reason is enum-validated and shares one vocabulary with listing-report + admin labels

Regression sentinel for M5 (`1d87a15`). `POST /listora/v1/reviews/{id}/report`
must accept only the canonical reason vocabulary and reject anything else with a
400 `rest_invalid_param`. The enum, the listing-report enum, and the admin
Reports meta-box labels must all resolve from ONE source:
`WBListora\Admin\Report_Metabox::reasons()`
(`includes/admin/class-report-metabox.php:57` — keys
`inaccurate | spam | closed | duplicate | offensive | other`). The reviews
controller's `report_reasons()` delegates to it
(`includes/rest/class-reviews-controller.php:916`), and the `reason` REST arg
enum is `array_keys( $this->report_reasons() )` (`:202`).

## Setup

- Site: `$SITE_URL`; reporter = a subscriber who did not author `REVIEW_ID`.
- Obtain a REST nonce for the reporter.

### 1. Single source of truth — controller delegates to Report_Metabox
- **Action**:
  ```
  grep -n "Report_Metabox::reasons\|array_keys( \$this->report_reasons" includes/rest/class-reviews-controller.php
  grep -n "public static function reasons" includes/admin/class-report-metabox.php
  ```
- **Expect**: the review-report `reason` arg `enum` is `array_keys( $this->report_reasons() )`; `report_reasons()` returns `\WBListora\Admin\Report_Metabox::reasons()`. No hardcoded reason list in the controller.
- **On fail**: `1d87a15` — the enum must derive from `Report_Metabox::reasons()`.

### 2. A valid reason succeeds (200)
- **Action**:
  ```
  curl -s -o /dev/null -w "%{http_code}" --cookie "<reporter-cookie>" -H "X-WP-Nonce: <nonce>" \
    -X POST "$SITE_URL/wp-json/listora/v1/reviews/REVIEW_ID/report" \
    -H "Content-Type: application/json" -d '{"reason":"inaccurate","details":"QA test"}'
  ```
- **Expect**: `200`. Repeat for each of `spam | closed | duplicate | offensive | other` (reset the dedup state between calls or use distinct reviews) — all 200.
- **On fail**: a canonical key missing from `Report_Metabox::reasons()`.

### 3. An invalid reason is rejected (400 rest_invalid_param)
- **Action**:
  ```
  curl -s --cookie "<reporter-cookie>" -H "X-WP-Nonce: <nonce>" \
    -X POST "$SITE_URL/wp-json/listora/v1/reviews/REVIEW_ID/report" \
    -H "Content-Type: application/json" -d '{"reason":"banana"}'
  ```
- **Expect**: HTTP `400`, body `code: "rest_invalid_param"`. Repeat with an empty string `""` and a free-form `"the food was cold and the staff rude"` — both 400. REST arg validation (the `enum`) rejects them before the callback runs.
- **On fail**: the `reason` arg has no `enum`, or it isn't bound to `report_reasons()`.

### 4. Lockstep with the listing-report enum + admin labels
- **Action**:
  ```
  wp eval "echo implode(',', array_keys(\WBListora\Admin\Report_Metabox::reasons()));"
  grep -n "Report_Metabox::reasons\|array_keys.*report_reasons" includes/rest/class-listings-controller.php
  ```
- **Expect**: the listing-report REST `reason` enum (`/listings/{id}/report`) resolves from the SAME `Report_Metabox::reasons()`; the admin Reports meta box renders its option labels from the same method. All three (review-report enum, listing-report enum, admin labels) move together.
- **On fail**: a surface hardcodes its own reason list.

### Cleanup
- Clear the QA reports on `REVIEW_ID`.

## Notes
- This is a "single enum, three consumers" sentinel — adding a 7th reason means editing ONLY `Report_Metabox::reasons()`; this journey then automatically covers all three surfaces.
- Pairs with Free's `regression/report-listing.md` (the listing-report flow uses the same vocabulary).
