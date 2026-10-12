---
journey: rate-limit-counts-members-by-account
plugin: wb-listora
priority: high
roles: [member, anonymous, admin]
covers: [rate-limiter, submission, reviews, claims, favorites]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 6
covers_card: 10332343411
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Members on one network are limited by their own account

`Rate_Limiter::check( $action, $user_id )` (`includes/class-rate-limiter.php`) counts a signed-in member against the per-USER cap
only; the per-IP counter applies to guests and to actions with no per-user cap. `wb_listora_rate_limit_ip_counts_members` (default
false, `class-rate-limiter.php:276`) restores the shared-IP behaviour. Caps live in `Rate_Limiter::DEFAULTS` (tunable with
`wb_listora_rate_limit_config`); `submission` = 30/hour per user, 10/hour per IP; `review_create` = 30 / 15; `public_read` (logged-out
listing reads) = 120/minute per IP, members not counted.

2.0.0 adds: a 429 carries `data.retry_after` and a `Retry-After` header (`class-rate-limiter.php:446-460`), and the wait no longer
grows while a client keeps trying (refused hits are not counted and do not move the window, `hit()`).

Role ladder: members (own budget), a guest (IP budget), admin as control (not exempt: same per-user cap). Logged-out review
creation is a 401 before any limit, so guests exercise the IP counter through a guest-reachable action (`submission` check or the
`public_read` GET limit).

## Steps (verified in-process with `REMOTE_ADDR` forced to `203.0.113.77`; clean the `listora_rl_*` transients afterwards)

### 1. Office
- **Action**: three members (ids 9001-9003) on one IP, `Rate_Limiter::check( 'submission', $uid )` four times each.
- **Expect**: all 12 return `true`.

### 2. One member
- **Action**: one account, 31 checks.
- **Expect**: hit 31 is a `WP_Error` `listora_rate_limit`, status 429, `scope: "user"`, `retry_after: 3600`.

### 3. Guests
- **Action**: `check( 'submission', 0 )` 12 times from the same IP.
- **Expect**: hits 11 and 12 are 429 with `scope: "ip"`. Positive control for step 1: members on that IP were not stopped at 10.
- **HTTP version**: 121 anonymous `GET /wp-json/listora/v1/listings` within a minute returns 429 with a `Retry-After` header.
  Warning: this blocks all anonymous reads from your IP for up to a minute (other QA runs included); do it on a scratch site.

### 4. Escape hatch
- **Action**: `add_filter( 'wb_listora_rate_limit_ip_counts_members', '__return_true' );`
- **Expect**: members share the IP budget again (with the guest counter already at 10 the next member hit is refused immediately).

## Fail diagnostics
`includes/class-rate-limiter.php:223` (`check()`, user vs IP decision at `:262-285`), `hit()` `:322`, `limit_public_reads()` `:406`, `error()` `:428`.

## Not verified
The HTTP 429 + `Retry-After` header was read in code, not provoked over HTTP; reviews/claims/favorites actions use the same `check()`.
