---
journey: app-nonce-exempt-authenticated-writes
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber, administrator]
covers: [wb_listora_verify_rest_nonce, wb_listora_require_rest_nonce, wb_listora_contact_rate_limit_identity, contact-form-app-password, anon-spam-hole-guard, carrier-nat-rate-limit]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active"
  - "At least one published listora_listing (note its ID as $LISTING)"
  - "An Application Password for an existing user: wp user application-password create 1 qa --porcelain (note the real user_login — it is NOT necessarily 'admin')"
estimated_runtime_minutes: 6
---

# An authenticated app can contact a listing owner; an anonymous client still cannot

`POST /listora/v1/listings/{id}/contact-form` gates on a **per-listing nonce** printed into
the listing page by `render_form()` — a deliberate anti-spam design ("proof of page render").
A native client never renders that page, and a nonce is session-bound, so it cannot be minted
from an Application Password. Before 1.2.3 that made the route a hard 403 for the mobile app.

1.2.3 teaches the gate that **an authenticated request is also proof**, via
`wb_listora_verify_rest_nonce()` (`includes/class-template-helpers.php`). The anti-spam is NOT
removed. This journey locks all four corners of the decision table plus the escape hatch, and
proves the rate limit still fires and is now bucketed per-user (carrier-NAT fix).

**The critical assertion is step 3: anonymous + no nonce must STILL be rejected.** If that ever
passes, we have shipped an open spam relay.

## Setup

```bash
SITE=http://listora.local
LISTING=$(curl -s "$SITE/wp-json/listora/v1/search?per_page=1" | python3 -c "import sys,json;print(json.load(sys.stdin)['listings'][0]['id'])")
# Use the REAL user_login (wp user list --field=user_login --role=administrator), not "admin".
AP='varundubey:REPLACE_WITH_APP_PASSWORD'
# Clear contact counters so the rate-limit steps start clean.
wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%transient%wb_listora_contact%\"");'
```

## Steps

### 1. Authenticated + NO nonce → allowed (the app's core action)
- **Action**:
  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" -u "$AP" -X POST \
    "$SITE/wp-json/listora/v1/listings/$LISTING/contact-form" \
    -H 'Content-Type: application/json' \
    -d '{"name":"App User","email":"app@example.com","message":"Hello from the native app, no nonce present."}'
  ```
- **Expect**: `200`, body `{"sent":true,...}`.
- **On fail**: `wb_listora_verify_rest_nonce()` is not reached or `is_user_logged_in()` is false.
  If `401`/`rest_not_logged_in`, the Application Password itself is not authenticating — check the
  **username is the real `user_login`**, that `wp_get_environment_type()` is `local` (or the site is
  HTTPS), and that the `Authorization` header reaches PHP. That is an environment fault, not a
  regression. Suspect: `includes/class-contact-form.php::check_permission()`.

### 2. Authenticated + INVALID nonce → still 403 (browser path not weakened)
- **Action**: repeat step 1 with `"_wpnonce":"deadbeef"` added to the JSON body.
- **Expect**: `403`, code `listora_invalid_nonce`.
- **On fail**: the helper is skipping verification whenever a user is logged in — it must verify
  ANY nonce that is actually sent. Suspect: the `'' !== $nonce` branch in `wb_listora_verify_rest_nonce()`.

### 3. Anonymous + NO nonce → still 403 (**the spam-hole guard**)
- **Action**:
  ```bash
  curl -s -o /dev/null -w "%{http_code}\n" -X POST \
    "$SITE/wp-json/listora/v1/listings/$LISTING/contact-form" \
    -H 'Content-Type: application/json' \
    -d '{"name":"Anon","email":"anon@example.com","message":"anonymous spam attempt with no nonce"}'
  ```
- **Expect**: `403`, code `listora_invalid_nonce`.
- **On fail**: **STOP — release blocker.** The route is an open anonymous spam relay. The final
  `return new \WP_Error(...)` in `wb_listora_verify_rest_nonce()` must be unreachable only for
  authenticated callers.

### 4. Escape hatch restores strict, browser-only behaviour
- **Action**: drop a mu-plugin then re-run step 1:
  ```bash
  printf '<?php\nadd_filter( "wb_listora_require_rest_nonce", "__return_true" );\n' \
    > wp-content/mu-plugins/zz-qa-strict-nonce.php
  ```
- **Expect**: step 1 now returns `403` — a site can opt back into the pre-1.2.3 contract with one
  `add_filter` (production rule 3).
- **Cleanup**: `rm wp-content/mu-plugins/zz-qa-strict-nonce.php`; re-run step 1 → back to `200`.
- **On fail**: the filter is not applied, or is read after the auth short-circuit. Suspect:
  `wb_listora_verify_rest_nonce()` ordering — `$require_nonce` must gate the `is_user_logged_in()`
  branch.

### 5. Rate limit still fires (anti-spam intact)
- **Action**: send 4 messages as the authenticated user to the same listing (repeat step 1 ×4).
- **Expect**: `200, 200, 200, 429` — cap of 3/hour per sender+listing is unchanged.
- **On fail**: the limiter was bypassed along with the nonce. Suspect:
  `includes/class-contact-form.php::handle_submit()` — the limiter must run on BOTH paths.

### 6. Rate limit is bucketed per-USER when authenticated (carrier-NAT fix)
- **Action**:
  ```bash
  wp eval 'global $wpdb; foreach ( $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE \"_transient_wb_listora_contact_%\"") as $k ) { echo "$k\n"; }'
  ```
- **Expect**: a key of the form `_transient_wb_listora_contact_user_<md5>` exists — NOT
  `_transient_wb_listora_contact_ip_<md5>`. Confirm the hash identifies the user:
  ```bash
  wp eval 'echo "_transient_wb_listora_contact_user_" . md5( "1|" . '"$LISTING"' ) . "\n";'
  ```
- **Expect**: the printed key matches one from the list above.
- **Why**: keyed on IP, every mobile user behind one carrier NAT shares a counter and throttles
  unrelated people. Suspect: `wb_listora_contact_rate_limit_identity()`.

### 7. Guests still bucket per-IP (unchanged for anonymous traffic)
- **Action**:
  ```bash
  wp eval '$_SERVER["REMOTE_ADDR"]="203.0.113.9"; wp_set_current_user(0); echo json_encode(wb_listora_contact_rate_limit_identity('"$LISTING"'))."\n"; wp_set_current_user(1); echo json_encode(wb_listora_contact_rate_limit_identity('"$LISTING"'))."\n";'
  ```
- **Expect**: `{"scope":"ip","id":"203.0.113.9"}` then `{"scope":"user","id":"1"}`.
- **On fail**: guest bucketing changed — an anonymous regression in the only identity a guest has.

## Pass criteria

ALL must hold:
- Step 1 → `200 {"sent":true}` (authenticated, no nonce).
- Step 2 → `403` (invalid nonce still verified).
- Step 3 → `403` (**anonymous + no nonce rejected — non-negotiable**).
- Step 4 → `403` with the filter, `200` after removing it.
- Step 5 → 4th request `429`.
- Step 6 → counter key is `..._contact_user_<md5(user_id|listing_id)>`.
- Step 7 → guest resolves `scope: ip`, authenticated resolves `scope: user`.
- `wp-content/debug.log` gains no new notices.

## Fail diagnostics

| Symptom | Suspect |
|---|---|
| Step 1 = 403 | `includes/class-template-helpers.php` → `wb_listora_verify_rest_nonce()` auth branch |
| Step 1 = 401 `rest_not_logged_in` | Environment: wrong `user_login`, non-local + non-HTTPS site, or stripped `Authorization` header — not a code regression |
| Step 2 = 200 | Helper skipping verification for logged-in users — the sent-nonce branch must always verify |
| **Step 3 = 200** | **Release blocker** — open spam relay; final `WP_Error` in the helper |
| Step 4 unchanged | `wb_listora_require_rest_nonce` filter not honoured |
| Step 5 no 429 | `includes/class-contact-form.php::handle_submit()` limiter skipped |
| Step 6 shows `_ip_` key | `wb_listora_contact_rate_limit_identity()` not consulted by `handle_submit()` |

## State restored

- Delete `wp-content/mu-plugins/zz-qa-strict-nonce.php` if step 4 aborted early.
- Clear counters: `wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%transient%wb_listora_contact%\"");'`
- Revoke the Application Password: `wp user application-password delete 1 --all`
- The journey sends real email via `wp_mail()` to the listing owner — expected on a test site.
