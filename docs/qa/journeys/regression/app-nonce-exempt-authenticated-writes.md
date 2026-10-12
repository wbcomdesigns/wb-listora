---
journey: app-nonce-exempt-authenticated-writes
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber, administrator]
covers: [wb_listora_verify_rest_nonce, wb_listora_require_rest_nonce, wb_listora_contact_rate_limit_identity, contact-form-app-password, anon-spam-hole-guard, carrier-nat-rate-limit]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora active, Contact form feature on (feature `contact_form`)"
  - "At least one published listora_listing (note its ID as $LISTING, owned by someone other than the sender)"
  - "An Application Password for an existing user: wp user application-password create <real user_login> qa-j1-app --porcelain (use the real user_login, not 'admin'); revoke only that one afterwards by its uuid, not --all"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [REST only]
changed: true
---

# An authenticated app can contact a listing owner; an anonymous client still cannot

`POST /listora/v1/listings/{id}/contact-form` (the single contact route since 2.0.0; Pro's old `/contact` is a deprecated alias that calls the same send path) gates on a per-listing nonce printed into the listing page by `render_form()`: a deliberate anti-spam design ("proof of page render"). A native client never renders that page and a nonce is session-bound, so it cannot be minted from an Application Password. `wb_listora_verify_rest_nonce()` (`includes/class-template-helpers.php:2353-2401`) teaches the gate that an authenticated request is also proof. The anti-spam is NOT removed. This journey locks the four corners of the decision table plus the escape hatch, and proves the rate limit still fires and is bucketed per user.

**The critical assertion is step 3: anonymous with no nonce must STILL be rejected.** If that ever passes, an open spam relay shipped.

2.0.0 notes: every accepted message is also saved as a lead (Leads table `{prefix}listora_leads`: `id, listing_id, owner_id, sender_id, name, email, phone, message, created_at`); the contact form takes an optional `phone`; the response is `{"sent":true,"message":"Your message has been sent to the listing owner."}`; a 429 carries both `retry_after` in the body and a `Retry-After` header.

## Setup

```bash
SITE=http://wb-listora.local
LISTING=551        # any published listing the sender does not own
AP='<real_user_login>:<application password>'
# start the rate-limit steps clean
wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%transient%wb_listora_contact%\"");'
```

## Steps (all executed 2026-10-12 with Application Password auth)

### 1. Authenticated and NO nonce is allowed (the app's core action)
- **Action**:
  ```bash
  curl -s -w "\n%{http_code}\n" -u "$AP" -X POST "$SITE/wp-json/listora/v1/listings/$LISTING/contact-form" \
    -H 'Content-Type: application/json' \
    -d '{"name":"QA-J1 App","email":"qa-j1-app@example.test","message":"QA-J1 hello from the native app, no nonce present."}'
  ```
- **Expect**: `200`, body `{"sent":true,"message":"Your message has been sent to the listing owner."}`, and one new lead row.
- **On fail**: `wb_listora_verify_rest_nonce()` not reached or `is_user_logged_in()` false. A `401 rest_not_logged_in` means the Application Password is not authenticating (wrong `user_login`, non-local non-HTTPS site, stripped `Authorization` header): an environment fault, not a regression. Suspect `includes/class-contact-form.php:160-175` `check_permission()`.

### 2. Authenticated with an INVALID nonce is still 403 (browser path not weakened)
- **Action**: repeat step 1 with `"_wpnonce":"deadbeef"` in the JSON body.
- **Expect**: `403`, code `listora_invalid_nonce`.
- **On fail**: the helper skips verification whenever a user is logged in; any nonce that is sent must be verified (`class-template-helpers.php:2376-2386`).

### 3. Anonymous with NO nonce is still 403 (the spam-hole guard)
- **Action**: the same POST without `-u`.
- **Expect**: `403`, code `listora_invalid_nonce`.
- **On fail**: STOP, release blocker. The final `WP_Error` (`:2396`) must be unreachable only for authenticated callers.
- **POSITIVE CONTROL**: steps 1 and 3 together: the same body is accepted authenticated and refused anonymously, so the 403 is the auth gate, not a malformed request.

### 4. Escape hatch restores strict, browser-only behaviour
- **Action** (in-process, no mu-plugin needed): `wp eval 'wp_set_current_user(1); var_dump(is_wp_error(wb_listora_verify_rest_nonce("", "x"))); add_filter("wb_listora_require_rest_nonce","__return_true"); echo wb_listora_verify_rest_nonce("", "x")->get_error_code();'`
- **Expect**: `bool(false)` (allowed) then `listora_invalid_nonce` (strict). To prove it end to end, drop `add_filter( "wb_listora_require_rest_nonce", "__return_true" );` into `wp-content/mu-plugins/zz-qa-strict-nonce.php`, re-run step 1 -> `403`, then `rm` the file and re-run -> `200`.
- **On fail**: the filter is not applied or is read after the auth short-circuit (`:2372` must gate the `is_user_logged_in()` branch at `:2391`).

### 5. Rate limit still fires (anti-spam intact)
- **Action**: send the step 1 request four times as the same authenticated user to the same listing.
- **Expect**: `200, 200, 200, 429`. The 429 body is `{"code":"listora_rate_limit","message":"Too many messages. Try again in an hour.","data":{"status":429,"retry_after":3600}}` and the response header `Retry-After: 3600` is present. Cap is 3 per hour per sender and listing; a separate per-listing daily cap (default 20, filter `wb_listora_contact_form_per_listing_daily_cap`) answers `listora_listing_rate_limit`.
- **On fail**: the limiter was bypassed along with the nonce: `includes/class-contact-form.php:245-290`.

### 6. The bucket is per USER when authenticated (carrier-NAT fix)
- **Action**: `wp eval 'global $wpdb; foreach ( $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE \"_transient_wb_listora_contact_%\"") as $k ) echo "$k\n"; echo "_transient_wb_listora_contact_user_" . md5( "<user id>|<listing id>" ) . "\n";'`
- **Expect**: a `_transient_wb_listora_contact_user_<md5>` key, NOT `..._contact_ip_<md5>`, and the printed expected key equals it (verified: `...user_9d336301...` for user 1, listing 551), plus the per-listing key `_transient_wb_listora_contact_listing_<id>`.

### 7. Guests still bucket per IP
- **Action**: `wp eval '$_SERVER["REMOTE_ADDR"]="203.0.113.9"; wp_set_current_user(0); echo json_encode(wb_listora_contact_rate_limit_identity(551)),"\n"; wp_set_current_user(1); echo json_encode(wb_listora_contact_rate_limit_identity(551)),"\n";'`
- **Expect**: `{"scope":"ip","id":"203.0.113.9"}` then `{"scope":"user","id":"1"}`.

### 8. Role ladder
| Caller | No nonce | Bad nonce |
|---|---|---|
| anonymous | 403 `listora_invalid_nonce` | 403 |
| subscriber (Application Password) | 200 | 403 |
| the listing owner messaging their own listing | allowed by the nonce gate; blocking/own-listing rules are in `blocking-enforced-on-live-contact-route.md` | 403 |
| administrator (control) | 200 | 403 |

## Pass criteria
- Step 1 -> `200 {"sent":true,...}` (authenticated, no nonce).
- Step 2 -> `403` (invalid nonce still verified).
- Step 3 -> `403` (anonymous with no nonce rejected; non-negotiable).
- Step 4 -> strict with the filter, allowed without.
- Step 5 -> 4th request `429` with `Retry-After`.
- Step 6 -> counter key is `..._contact_user_<md5(user_id|listing_id)>`.
- Step 7 -> guest `scope: ip`, authenticated `scope: user`.
- `wp-content/debug.log` gains no new notices.

## Fail diagnostics

| Symptom | Suspect |
|---|---|
| Step 1 = 403 | `includes/class-template-helpers.php:2391` auth branch |
| Step 1 = 401 `rest_not_logged_in` | environment: wrong `user_login`, non-local non-HTTPS, stripped `Authorization` header |
| Step 2 = 200 | helper skips verification for logged-in users: `:2376` |
| **Step 3 = 200** | release blocker: open spam relay (`:2396`) |
| Step 4 unchanged | `wb_listora_require_rest_nonce` filter not honoured (`:2372`) |
| Step 5 no 429 | limiter skipped: `includes/class-contact-form.php:250-262` |
| Step 6 shows `_ip_` key | `wb_listora_contact_rate_limit_identity()` not consulted (`class-template-helpers.php:2417`) |

## State restored (the 2026-10-12 run did all of these)
- Delete `wp-content/mu-plugins/zz-qa-strict-nonce.php` if step 4 used it.
- Clear counters: `wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%transient%wb_listora_contact%\"");'`
- Delete the QA leads the run created (`DELETE FROM {prefix}listora_leads WHERE message LIKE 'QA-J1%'`); the journey also sends real mail through `wp_mail()` to the listing owner, expected on a test site.
- Revoke the Application Password you created (by uuid; `wp user application-password delete <user> <uuid>`).
