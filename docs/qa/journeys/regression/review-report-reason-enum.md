---
journey: review-report-reason-enum
plugin: wb-listora
priority: normal
roles: [subscriber, administrator, anonymous, owner]
covers: [review-report-rest, report-reason-enum, rest-arg-validation]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "An approved review NOT authored by the reporter exists (capture REVIEW_ID)"
  - "A reporter subscriber user exists"
estimated_runtime_minutes: 4
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# Review-report reason is enum-validated against the review vocabulary

`POST /listora/v1/reviews/{id}/report` accepts only the REVIEW reason keys and rejects anything else with 400 `rest_invalid_param`.
One source: `wb_listora_get_review_report_reasons()` (`includes/class-template-helpers.php:805`, filterable with
`wb_listora_review_report_reasons`, applies to the REST enum and the dialog together). The reviews controller's private
`report_reasons()` (`includes/rest/class-reviews-controller.php:976`) returns it and the arg enum is
`array_keys( $this->report_reasons() )` (`:202`).

**Keys (2026-10-12): `spam | offensive | off_topic | fake | private_info | conflict | other`.** The listing-report vocabulary
(`inaccurate | spam | closed | duplicate | offensive | other`, `Report_Metabox::reasons()`, `includes/admin/class-report-metabox.php:51`)
is a DIFFERENT list used by `POST /listings/{id}/report` and the admin Reports metabox. The old "one enum, three consumers" claim
is gone (the controller docblock at `:968` still describes it and is stale).

Role ladder: logged out -> 401; a member who did not write the review -> 200/400 as below; the same member twice -> 409
`listora_already_reported`; a review that is pending or gone -> 403 `listora_review_not_approved` / 404; admin is the control (same enum).

## Steps (verified 2026-10-12 in-process as subscriber2 on review 86; report option removed afterwards)

### 1. Valid reason succeeds
- **Action**: `curl -s -b <cookie> -H "X-WP-Nonce: <nonce>" -X POST "$SITE_URL/wp-json/listora/v1/reviews/REVIEW_ID/report" -H "Content-Type: application/json" -d '{"reason":"fake","details":"QA test"}'`
- **Expect**: 200 `{"reported":true}`. Repeat each of the seven keys on distinct reviews (or delete option
  `_listora_review_reports_<id>` between calls).
- **Positive control for the 400s**: this step must succeed first.

### 2. Invalid reason is rejected
- **Action**: same call with `{"reason":"banana"}`, `{"reason":""}`, `{"reason":"the food was cold"}`, and a LISTING key `{"reason":"inaccurate"}`.
- **Expect**: HTTP 400 `rest_invalid_param`, `params.reason` "reason is not one of spam, offensive, off_topic, fake, private_info, conflict, and other."

### 3. Duplicate and state gates
- Second report by the same member -> 409 `listora_already_reported`.
- A pending review (e.g. id 181 here) -> 403 `listora_review_not_approved` "This review is not published yet."; the same for
  `helpful` and `reply`. Reviews off for the listing's type -> 403 `listora_reviews_disabled`.

### 4. Lockstep with the dialog
- **Action**: `wp eval 'echo implode(",", array_keys( wb_listora_get_review_report_reasons() ));'`
- **Expect**: equals the option values of `#listora-report-review-reason` in the rendered dialog and the enum in step 2's message.
  Adding a reason through the filter must appear in both.

### Cleanup
`delete_option( '_listora_review_reports_' . REVIEW_ID )`.

## Fail diagnostics
- Enum drift: `includes/rest/class-reviews-controller.php:202`, `includes/class-template-helpers.php:805`.
- Dialog list: `templates/blocks/reviews/report-modal.php:28-32`.

## Not verified
Logged-out 401 and admin control on this route were read from code (`review_interaction_permissions()` `:1183`), not called.
