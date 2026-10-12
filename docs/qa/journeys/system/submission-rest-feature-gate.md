---
journey: submission-rest-feature-gate
plugin: wb-listora
priority: high
roles: [admin, owner, editor, guest]
covers: [submission-feature-toggle, submit-rest-permissions, feature-gate-disable-bypass, card-9910208175]
prerequisites:
  - "Site reachable at $SITE_URL; Submission feature ON at start (`wb_listora_features['submission']` true)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: []
fails_on: "feature check no longer first in submit_listing_permissions()"
---

# With the Submission feature off, direct POSTs to /submit are refused for every role

## Why
When Submission is off the block hides the wizard, but a direct `POST /listora/v1/submit` must refuse too, otherwise an owner who switched the feature off still receives spam listings through the API.

`Submission_Controller::submit_listing_permissions()` checks `wb_listora_feature_enabled( 'submission' )` first and returns `WP_Error( 'listora_submission_disabled', ..., 403 )` before any capability or guest logic. Toggle: Listora > Settings > Features (`wb_listora_features`); the filter `wb_listora_feature_submission_enabled` forces it per request.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Runner bypasses the 10/hour submit limiter: `add_filter( 'wb_listora_rate_limit_bypass', '__return_true' )`, or 429 `listora_rate_limit` is misread as a gate result.
- `wp eval-file` with `wp_set_current_user( {admin} )` unless stated.

## Steps

### 1. Baseline ON
- **Expect**: `wb_listora_feature_enabled( 'submission' )` true; `$controller->submit_listing_permissions( $req )` returns `true`.

### 2. OFF via filter: REST gate fires
- **Action**: `add_filter( 'wb_listora_feature_submission_enabled', '__return_false' )`; `rest_do_request( POST /listora/v1/submit )`.
- **Expect**: HTTP 403, `code: listora_submission_disabled`.

### 3. Admin is gated too
- **Action**: same with `current_user_can( 'submit_listora_listing' )` true.
- **Expect**: still 403. `allowed-DESPITE-OFF` means the feature check is no longer the first line.

### 4. Real HTTP POST with the Settings toggle OFF
- **Action**: toggle OFF in Settings (not the filter); `curl -X POST $SITE_URL/wp-json/listora/v1/submit` with an {admin} cookie + `X-WP-Nonce` and body `{"title":"QA-J1 gate","listing_type":"business"}`.
- **Expect**: 403 `"code":"listora_submission_disabled"`. `rest_cookie_invalid_nonce` means auth plumbing, not the gate. No listing row was created.

### 5. Frontend half with the toggle OFF
- **Action**: as {owner} open Add Listing; open the dashboard.
- **Expect**: no wizard (`.listora-submission__type-card` absent) and a message; the dashboard offers no Add Listing button. At 390 the message is readable with no horizontal scroll.

### 6. Re-enable (positive control)
- **Action**: remove the filter / toggle ON.
- **Expect**: step 1 returns allowed and the wizard renders type cards again.

### 7. Retry
- **Action**: replay the step 4 POST twice and from two tabs.
- **Expect**: identical 403 every time; no state change.

## Role ladder
| Actor | OFF | ON |
|---|---|---|
| {admin} | 403 `listora_submission_disabled` | allowed (control) |
| {owner} (member) | 403 same code | allowed (402/400 for credits or plan are other gates) |
| {guest} | 403 (feature gate runs before the login check) | 401/403 `rest_forbidden` |
| {editor} | 403 | allowed |

## Pass criteria
1. OFF: every role gets 403 `listora_submission_disabled`; no row is created.
2. ON: step 1 allowed, wizard renders.
3. The 403 `code` is stable (it is the app contract for "Submissions disabled" UX; changing it needs versioning).

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Feature OFF but POST accepted | ordering of the callback | `includes/rest/class-submission-controller.php` `submit_listing_permissions()` (~:228-240) |
| Block still renders wizard | feature gate in render | `blocks/listing-submission/render.php` |
| Setting does not toggle | helper | `includes/class-features.php` `wb_listora_feature_enabled()` |

## Not verified
Steps 1-3 and the dispatch in step 2 run on 2026-10-12; steps 4, 5, 7 and the {owner}/{guest}/{editor} rows were not run.

## Cleanup
Restore the Submission toggle and remove the bypass filter.
