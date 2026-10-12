---
journey: submission-rest-feature-gate
plugin: wb-listora
priority: high
roles: [administrator, member, anonymous]
covers: [submission-feature-toggle, submit-rest-permissions, feature-gate-disable-bypass]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Submission feature ON at start (default: `wb_listora_features['submission']` true)"
estimated_runtime_minutes: 4
covers_card: 9910208175
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Submission feature toggle is enforced at REST (no direct-POST bypass)

When the Submission feature is off the block UI hides the wizard, but `POST /wp-json/listora/v1/submit` must also refuse. `Submission_Controller::submit_listing_permissions()` (`includes/rest/class-submission-controller.php:228`) checks `wb_listora_feature_enabled( 'submission' )` first and returns `WP_Error( 'listora_submission_disabled', ..., 403 )` before any capability or guest logic. Toggle = Listora > Settings > Features (option `wb_listora_features`); the filter `wb_listora_feature_submission_enabled` forces it per request.

## Steps (wp eval-file, `wp_set_current_user( 1 )` unless stated)

### 1. Baseline ON
- `wb_listora_feature_enabled( 'submission' )` true; `$controller->submit_listing_permissions( $req )` returns `true`.

### 2. OFF via filter: REST gate fires
- `add_filter( 'wb_listora_feature_submission_enabled', '__return_false' )`.
- **Expect**: `feature:off`, error code `listora_submission_disabled`, `status 403`. Full dispatch: `rest_do_request( POST /listora/v1/submit )` returns HTTP 403 with `code: listora_submission_disabled` (verified 2026-10-12).

### 3. Admin is gated too (feature wins over caps)
- Same with `current_user_can( 'submit_listora_listing' )` true (verified `cap:yes`).
- **Expect**: still 403. `allowed-DESPITE-OFF` means the feature check is no longer the first line of the callback.

### 4. Real HTTP POST
- With the toggle OFF in Settings (not the filter), `curl -X POST $SITE_URL/wp-json/listora/v1/submit` with an admin cookie + `X-WP-Nonce` (`GET /wp-admin/admin-ajax.php?action=rest-nonce`) and body `{"title":"QA-J1 gate","listing_type":"business"}`.
- **Expect**: HTTP 403, body `"code":"listora_submission_disabled"`. A `rest_cookie_invalid_nonce` means auth plumbing, not the gate.

### 5. Frontend half (UI) with the toggle OFF
- As a member open `/add-listing/`.
- **Expect**: no wizard (`.listora-submission__type-card` absent) and a message instead; the Dashboard offers no Add Listing button. Not covered by REST: see `customer/02-submit-a-listing-wizard-end-to-end.md`.

### 6. Re-enable (positive control)
- Remove the filter / toggle back ON; step 1 returns `allowed` and the wizard renders type cards again.

### 7. Role ladder
| Actor | OFF | ON |
|---|---|---|
| Admin | 403 `listora_submission_disabled` | allowed (control) |
| Member | 403 same code | allowed (402/400 for credits or plan are other gates) |
| Logged out | 403 (feature gate runs before the login check) | 401/403 `rest_forbidden` |
| Editor | 403 | allowed |

## Notes
- The 403 body `code` is the contract for apps ("Submissions disabled" UX); changing it needs versioning.
- Rate limit: repeated test POSTs from one IP return `429 listora_rate_limit` after 10/hour; bypass in the runner with `add_filter( 'wb_listora_rate_limit_bypass', '__return_true' )`.

## Fail diagnostics
| Symptom | File |
|---|---|
| Feature OFF but POST accepted | `includes/rest/class-submission-controller.php:228-240` ordering |
| Block still renders wizard | `blocks/listing-submission/render.php` feature gate |
| Setting does not toggle | `includes/class-features.php` `wb_listora_feature_enabled()` |

## Not verified
Steps 1-3 and the dispatch in step 2 were run on 2026-10-12. Steps 4, 5 (browser/HTTP with the Settings toggle) and the member / logged-out rows of the ladder were not run.

Verified via: wp-cli `rest_do_request`.
