---
journey: rest-nonce-gate-authenticated-app-writes
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber, administrator]
covers: [wb_listora_verify_rest_nonce, wb_listora_require_rest_nonce, wb_listora_contact_rate_limit_identity, contact-form-app-password, anon-spam-hole-guard, carrier-nat-rate-limit, lead-saved]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "Free active, Contact form feature on (`contact_form`)"
  - "A published {listing} owned by someone other than the sender"
  - "An Application Password for an existing user: wp user application-password create <real user_login> qa-app --porcelain (the real user_login); revoke only that one afterwards by its uuid, never --all"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:regression/app-nonce-exempt-authenticated-writes.md
fails_on: unknown (pre-fix sha not recorded; step 3 failing means an open spam relay shipped)
site_profile: required
---

# An authenticated app can contact a listing owner; an anonymous client still cannot

## Why
`POST /listora/v1/listings/{id}/contact-form` (the single contact route; Pro's `/contact` is a deprecated alias) gates on a per-listing nonce printed into the listing page ("proof of page render"). A native client never renders that page and a nonce is session-bound, so `wb_listora_verify_rest_nonce()` treats an authenticated request as proof too. The anti-spam is NOT removed. **The critical assertion is step 3: anonymous with no nonce must STILL be rejected.** Every accepted message is also saved as a lead (`${PREFIX}listora_leads`); the Pro analytics sibling is `pro:system/app-nonce-exempt-pro-writes.md`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `AP='<real user_login>:<application password>'`; `LISTING` = {listing} id. Reset counters: `wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE \"%transient%wb_listora_contact%\"");'`

## Steps

### 1. Authenticated and NO nonce is allowed
- **Action**: `curl -s -w "\n%{http_code}\n" -u "$AP" -X POST "$SITE_URL/wp-json/listora/v1/listings/$LISTING/contact-form" -H 'Content-Type: application/json' -d '{"name":"QA App","email":"qa-app@example.test","message":"QA hello from the native app, no nonce present."}'`
- **Expect**: 200 `{"sent":true,"message":"Your message has been sent to the listing owner."}` and one new lead row (`id, listing_id, owner_id, sender_id, name, email, phone, message, created_at`; the form also takes an optional `phone`).
- **On fail**: `401 rest_not_logged_in` is an environment fault (wrong `user_login`, non-local non-HTTPS, stripped `Authorization` header). Otherwise `check_permission()` in `includes/class-contact-form.php`.

### 2. Authenticated with an INVALID nonce is still 403
- **Action**: repeat with `"_wpnonce":"deadbeef"`.
- **Expect**: 403 `listora_invalid_nonce` (any nonce sent must be verified).

### 3. Anonymous with NO nonce is still 403 (spam-hole guard)
- **Action**: the same POST without `-u`.
- **Expect**: 403 `listora_invalid_nonce`. STOP and block the release if it passes.
- **Positive control**: steps 1 and 3 together: the same body accepted authenticated and refused anonymously, so the 403 is the auth gate, not a malformed request.

### 4. Escape hatch restores strict browser-only behaviour
- **Action**: `wp eval 'wp_set_current_user(1); var_dump(is_wp_error(wb_listora_verify_rest_nonce("", "x"))); add_filter("wb_listora_require_rest_nonce","__return_true"); echo wb_listora_verify_rest_nonce("", "x")->get_error_code();'`
- **Expect**: `bool(false)` (allowed) then `listora_invalid_nonce` (strict). End to end: put the filter in `wp-content/mu-plugins/zz-qa-strict-nonce.php`, re-run step 1 -> 403, remove the file, re-run -> 200.

### 5. Rate limit still fires
- **Action**: send the step 1 request four times as the same user to the same listing.
- **Expect**: 200, 200, 200, 429; the 429 body `{"code":"listora_rate_limit","message":"Too many messages. Try again in an hour.","data":{"status":429,"retry_after":3600}}` and header `Retry-After: 3600`. Cap is 3 per hour per sender and listing; the per-listing daily cap (default 20, filter `wb_listora_contact_form_per_listing_daily_cap`) answers `listora_listing_rate_limit`.

### 6. The bucket is per USER when authenticated (carrier-NAT fix)
- **Action**: list `_transient_wb_listora_contact_%` option names and compute `"_transient_wb_listora_contact_user_" . md5( "<user id>|<listing id>" )`.
- **Expect**: a `..._contact_user_<md5>` key (not `..._contact_ip_<md5>`) equal to the computed key, plus the per-listing key `_transient_wb_listora_contact_listing_<id>`.

### 7. Guests still bucket per IP
- **Action**: `wp eval '$_SERVER["REMOTE_ADDR"]="203.0.113.9"; wp_set_current_user(0); echo json_encode(wb_listora_contact_rate_limit_identity(<LISTING>)),"\n"; wp_set_current_user(1); echo json_encode(wb_listora_contact_rate_limit_identity(<LISTING>)),"\n";'`
- **Expect**: `{"scope":"ip","id":"203.0.113.9"}` then `{"scope":"user","id":"1"}`.

### 8. Boundary input (added)
- **Action**: empty message, 10,000-char message, markup and emoji, invalid email, a request just after the 3-per-hour limit.
- **Expect**: 400 validation errors with clear codes, markup stripped, no fatal. Not verified live.

### 9. Retry / parallel (added)
- **Action**: fire 4 requests concurrently.
- **Expect**: at most 3 accepted (the limiter is atomic enough not to over-admit). Not verified live.

## Role ladder

| Caller | No nonce | Bad nonce |
|---|---|---|
| {guest} | 403 `listora_invalid_nonce` | 403 |
| {other} (subscriber, Application Password) | 200 | 403 |
| the listing {owner} messaging own listing | allowed by the nonce gate (own-listing/blocking rules: `blocking-enforced-on-live-contact-route.md`) | 403 |
| {admin} (control) | 200 | 403 |

## Pass criteria
Steps 1-7 as stated; `wp-content/debug.log` gains no new notices.

## Fail diagnostics

| Symptom | Suspect |
|---|---|
| Step 1 = 403 | `includes/class-template-helpers.php` `wb_listora_verify_rest_nonce` auth branch |
| Step 2 = 200 | helper skips verification for logged-in users |
| **Step 3 = 200** | release blocker: open spam relay (final `WP_Error` must be unreachable only for authenticated callers) |
| Step 4 unchanged | `wb_listora_require_rest_nonce` filter not honoured |
| Step 5 no 429 | limiter skipped: `includes/class-contact-form.php` |
| Step 6 shows `_ip_` key | `wb_listora_contact_rate_limit_identity()` not consulted |

## Not verified
Steps 8, 9. Steps 1-7 were executed 2026-10-12 with Application Password auth.

## Cleanup
Delete `mu-plugins/zz-qa-strict-nonce.php`; clear contact transients (command in Setup); `DELETE FROM ${PREFIX}listora_leads WHERE message LIKE 'QA%'`; revoke the Application Password by uuid (`wp user application-password delete <user> <uuid>`). The run sends real mail to the listing owner through `wp_mail()`.
