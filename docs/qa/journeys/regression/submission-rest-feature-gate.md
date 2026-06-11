---
journey: submission-rest-feature-gate
plugin: wb-listora
priority: high
roles: [administrator, anonymous]
covers: [submission-feature-toggle, submit-rest-permissions, feature-gate-disable-bypass]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Submission feature ON at start (default)"
estimated_runtime_minutes: 4
covers_card: 9910208175
covers_commit: existing
---

# Submission feature toggle is enforced at REST (no direct-POST bypass)

Regression sentinel for BC 9910208175 — when an admin disables the Submission feature, the block UI hides the wizard, but a capable user could still POST directly to `/wp-json/listora/v1/submit` and create a listing. The card called for the gate to be enforced at the REST layer too, not just the block UI.

The fix lives at `includes/rest/class-submission-controller.php:188-200` in `submit_listing_permissions()`: the FIRST check in the permission callback is `wb_listora_feature_enabled('submission')` — when false, returns `WP_Error('listora_submission_disabled', status: 403)` before any cap or guest-submission logic runs.

## Setup

- Site: `$SITE_URL`
- Free's `wb_listora_pro_features['submission']` defaults to enabled (or the Free-side `wb_listora_features['submission']` if Pro-toggleable).
- An admin user with `submit_listora_listing` capability (administrator role has it by default).

## Steps

### 1. Baseline — submission ON, permission allowed
- **Action**:
  ```
  wp eval "
  \$controller = new \WBListora\REST\Submission_Controller();
  \$req = new WP_REST_Request('POST', '/listora/v1/submit');
  \$req->set_param('title', 'sentinel-test');
  \$req->set_param('type', 'business');
  wp_set_current_user(1);
  echo wb_listora_feature_enabled('submission') ? 'feature:on' : 'feature:off'; echo PHP_EOL;
  \$r = \$controller->submit_listing_permissions(\$req);
  echo is_wp_error(\$r) ? 'denied:'.\$r->get_error_code() : (\$r ? 'allowed' : 'denied'); echo PHP_EOL;
  "
  ```
- **Expect**: `feature:on` and `allowed`.

### 2. Disable submission feature via filter — REST gate fires
- **Action**:
  ```
  wp eval "
  add_filter('wb_listora_feature_submission_enabled', '__return_false');
  \$controller = new \WBListora\REST\Submission_Controller();
  \$req = new WP_REST_Request('POST', '/listora/v1/submit');
  wp_set_current_user(1);
  echo wb_listora_feature_enabled('submission') ? 'feature:on' : 'feature:off'; echo PHP_EOL;
  \$r = \$controller->submit_listing_permissions(\$req);
  if (is_wp_error(\$r)) {
    echo 'code:'.\$r->get_error_code(); echo PHP_EOL;
    echo 'status:'.\$r->get_error_data()['status']; echo PHP_EOL;
  }
  "
  ```
- **Expect**: `feature:off`, `code:listora_submission_disabled`, `status:403`.

### 3. Admin (highest privilege) is ALSO gated — confirms feature wins over caps
- **Action**: same as step 2 but with the cap-only check disabled by adding a debug `current_user_can` assertion before invoking:
  ```
  wp eval "
  add_filter('wb_listora_feature_submission_enabled', '__return_false');
  wp_set_current_user(1);
  echo current_user_can('submit_listora_listing') ? 'cap:yes' : 'cap:no'; echo PHP_EOL;
  \$r = (new \WBListora\REST\Submission_Controller())->submit_listing_permissions(new WP_REST_Request('POST', '/listora/v1/submit'));
  echo is_wp_error(\$r) ? 'gated' : 'allowed-DESPITE-OFF';
  "
  ```
- **Expect**: `cap:yes` (admin has the cap) AND `gated` (feature gate still wins).
- **On fail (`allowed-DESPITE-OFF`)**: the feature gate is no longer the first check — re-order `submit_listing_permissions()` so the `wb_listora_feature_enabled('submission')` test runs before `current_user_can()`.

### 4. Direct HTTP POST returns 403 with the canonical error body
- **Action** (with feature still off): hit the REST endpoint via curl. Use an admin session cookie + nonce:
  ```
  ADMIN_NONCE=$(wp eval "wp_set_current_user(1); echo wp_create_nonce('wp_rest');")
  curl -s -X POST "$SITE_URL/wp-json/listora/v1/submit" \
    -H "Content-Type: application/json" \
    -H "X-WP-Nonce: $ADMIN_NONCE" \
    --cookie-jar /tmp/sub-cookie \
    -d '{"title":"sentinel-direct","type":"business"}'
  ```
- **Expect**: HTTP 403 with body containing `"code":"listora_submission_disabled"` and the translated message.
- **Note**: getting a valid logged-in session via curl + a wp-cli-generated nonce is finicky in test environments; if the curl reports `rest_cookie_invalid_nonce` instead of `listora_submission_disabled`, that's an auth-plumbing issue, not the bug — step 2/3 already prove the gate from PHP.

### 5. Re-enable feature, regression check
- **Action**: drop the filter; re-run step 1.
- **Expect**: permission returns `allowed`.

## Notes

- The gate also fires when Free's local toggle is off (no Pro involvement needed) — `wb_listora_feature_enabled('submission')` returns false in both cases.
- Block-side gate (block render code) is the OTHER half of the protection; this journey covers only the REST half. The block side is journey-covered by `customer/02-submit-a-listing-wizard-end-to-end.md`.
- The 403 response body's `code` field is the customer contract — apps that read it can show "Submissions disabled" UX. Changing the code requires versioning.
