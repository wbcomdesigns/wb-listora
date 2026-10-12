---
journey: listing-api-read-rate-limit
plugin: wb-listora
priority: high
roles: [guest, owner, other, admin]
covers: [public-read-rate-limit, retry-after-header, rate-limit-no-sliding-window, member-exempt, 2.0.0-security, bc-10393672688]
prerequisites:
  - "Site reachable at $SITE_URL; nothing else making logged-out REST reads from this machine's IP during the run (the limit is per IP: a parallel QA run is throttled by yours)"
  - "A published listing ({listing}) from docs/qa/fixtures/2.0-fixtures.php or any published listing"
  - "Default limits (120 logged-out reads per minute per IP); no mu-plugin touching `wb_listora_rate_limit_config`"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [n/a]
themes: [n/a]
site_profile: required
supersedes: [ora:system/2.0-listing-api-read-rate-limit.md]
fails_on: 2394e3c
---

# Logged-out reads of listing data stop at 120 a minute per IP; members and admins are never counted

## Why
Bulk scraping of listing fields is bounded in 2.0.0. If the limiter double-counts, slides while a client retries, or counts members, real visitors and apps are locked out or the guard is a no-op.

Only handlers that carry listing fields count (`GET /listings`, `GET /listings/{id}`, related, bulk, `GET /search`); typeahead, map clusters, listing types and the reviews list do not. A refusal says when to retry (`Retry-After`) and the wait counts DOWN while the client keeps trying (refused hits are not counted).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `ID` = {listing} (a published listing id). REST root `$SITE_URL/wp-json/listora/v1`.
- Run from ONE client IP; wait 60 s before starting so no earlier window is open.
- Cookie sessions for `{owner}`, `{other}` and `{admin}` via the profile autologin param.

## Steps

### 1. Guests are counted
- **Action**: from a fresh IP window send 121 sequential requests:
  ```bash
  for i in $(seq 1 121); do curl -s -o /dev/null -w "%{http_code}\n" "$SITE_URL/wp-json/listora/v1/listings/$ID"; done | sort | uniq -c
  ```
- **Expect**: `120 200` and `1 429`.
- **Read-back**: the same split from PHP with `REMOTE_ADDR` set (429 starts on the 121st counted read).

### 2. The refusal is well-formed
- **Action**: `curl -si "$SITE_URL/wp-json/listora/v1/listings/$ID"` right after step 1.
- **Expect**: `HTTP 429`; `Retry-After: <n>` with 1 <= n <= 60; JSON `{"code":"listora_rate_limit","message":"Too many requests from this network. Please try again later.","data":{"status":429,"action":"public_read","scope":"ip","retry_after":<n>}}`.

### 3. The wait counts down and does not grow
- **Action**: call again after 3 s, after 6 s, then 10 times in a row; then sleep the first `Retry-After` and call once more.
- **Expect**: `retry_after` strictly decreases (for example 52, 49, 46) and never resets upward while hammering; after the full wait the next read is 200.
- **Read-back**: record `Retry-After` from the header on each retry; the third value is strictly lower than the first. **fails_on**: 2394e3c (no `Retry-After`, window kept sliding). Not verified live.

### 4. Which routes count (positive and negative controls)
While limited:
| Request | Expect |
|---|---|
| `GET /listora/v1/listings` (list) | 429 |
| `GET /listora/v1/search` | 429 |
| `GET /listora/v1/listing-types` | 200 (not listing data) |
| `GET /listora/v1/listings/<ID>/reviews` | 200 |
| the Directory page in a browser | 200 (server-rendered; not the REST route) |
- **Expect**: a refused read never blocks human-facing pages, so the limit cannot lock a visitor out of the site.

### 5. Members and admins are exempt (role ladder)
See the Role ladder table; run 150 reads per rung with `X-WP-Nonce` while the guest rung is in its 429 window (positive control: the guest rung is 429 in the same minute).

### 6. Tunable, and restorable
- **Action**: mu-plugin `add_filter( 'wb_listora_rate_limit_config', function ( $c, $action ) { if ( 'public_read' === $action ) { $c['ip_max'] = 5; } return $c; }, 10, 2 );`
- **Expect**: the 6th guest read in a minute is 429. Remove the mu-plugin; the default returns.
- **Action**: `add_filter( 'wb_listora_rate_limit_bypass', '__return_true' );` **Expect**: no 429 at all. Remove it.

### 7. Contact form uses its own, tighter limit (control)
- **Action**: 4 contact-form posts from one IP to one listing within an hour (see `customer/16-listing-contact-form.md`).
- **Expect**: the 4th returns 429 `listora_rate_limit` "Too many messages from your network. Try again in an hour." with `retry_after` 3600, independent of the read limit.

## Role ladder
| Rung | Action (same IP, inside the 429 window) | Expect |
|---|---|---|
| {guest} | step 1 | 429 after 120 |
| {owner} (cookie + nonce) | 150 reads of the same route | all 200 |
| {other} | 150 reads | all 200 (accounts on one network do not share a budget) |
| {admin} (control) | 150 reads | all 200 |

## Pass criteria
1. A guest gets 120 reads per minute then 429 with `Retry-After` and the documented JSON.
2. The wait decreases and a patient client always passes.
3. Only listing-data routes count; browsing pages is never refused.
4. Members and admins are never counted; the config filter and bypass work.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| 429 at 60 requests | two limiter registrations (each read counted twice) | `includes/class-plugin.php` hook added twice |
| No `Retry-After` | `rest_post_dispatch` filter missing or code prefix mismatch | `includes/class-rate-limiter.php` `retry_after_header` |
| Members limited | `is_user_logged_in()` false in REST (cookie without nonce is a guest) | send `X-WP-Nonce` |
| Window grows while hammering | refused hits written back | `Rate_Limiter::hit` |
| Registered handler not reached | `limit_public_reads` not on `rest_request_before_callbacks` | `includes/class-rate-limiter.php`, `DEFAULTS['public_read']` |

## Not verified
Step 3 read-back and the contact-form control were not run live. N=0 / timezone / scale rules do not apply (limit is a rolling 60 s window).

## Cleanup
Remove any mu-plugin added in step 6; wait 60 s so the window is clear for the next run.
