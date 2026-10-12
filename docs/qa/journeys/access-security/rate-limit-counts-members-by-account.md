---
journey: rate-limit-counts-members-by-account
group: access-security
actor: system
mode: cli
plugin: wb-listora
priority: high
roles: [owner, other, guest, admin]
covers: [rate-limiter, submission, reviews, claims, favorites, retry-after, covers_card 10332343411]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI against $WP_PATH"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
site_profile: required
supersedes:
  - ora:regression/rate-limit-counts-members-by-account.md
---

# Members on one network are limited by their own account, guests by IP

## Why
An office or school behind one IP must not lock its members out because colleagues used the budget. `Rate_Limiter::check( $action, $user_id )` (`includes/class-rate-limiter.php`) counts a signed-in member against the per-USER cap only; the per-IP counter applies to guests and to actions with no per-user cap. `wb_listora_rate_limit_ip_counts_members` (default false, `:276`) restores the shared-IP behaviour. Caps live in `Rate_Limiter::DEFAULTS` (tunable via `wb_listora_rate_limit_config`): `submission` = 30/hour per user, 10/hour per IP; `review_create` = 30 / 15.

2.0.0: a 429 carries `data.retry_after` and a `Retry-After` header (`:446-460`), and the wait does not grow while a client keeps trying (refused hits are not counted and do not move the window, `hit()`). The logged-out `public_read` 120/minute limit is owned by `access-security/listing-api-read-rate-limit.md`; the every-route `Retry-After` contract is `access-security/spam-protection-layers.md` steps 12-13.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Run in-process with `REMOTE_ADDR` forced to a documentation IP (`203.0.113.77`); clean the `listora_rl_*` transients afterwards (`wp transient delete --all` is too broad; delete by prefix).
- Caps: read `Rate_Limiter::DEFAULTS['submission']` instead of trusting the numbers below if a site tuned them.

## Steps

### 1. Office: three members, one IP
- **Action**: three members (ids from throwaway users) on one IP, `Rate_Limiter::check( 'submission', $uid )` four times each.
- **Expect**: all 12 return `true`.
- **Read-back**: IP counter transient stays untouched for members.

### 2. One member over the user cap
- **Action**: one account, per-user-cap + 1 checks (31).
- **Expect**: hit 31 is a `WP_Error` `listora_rate_limit`, status 429, `scope: "user"`, `retry_after: 3600`.
- **Read-back**: hit 32 does not extend `retry_after` (window does not slide).

### 3. Guests use the IP budget
- **Action**: `check( 'submission', 0 )` 12 times from the same IP.
- **Expect**: hits 11 and 12 are 429 with `scope: "ip"`. Positive control for step 1: members on that IP were not stopped at 10.
- **Read-back**: error `data.retry_after` is set; over HTTP the same refusal carries a `Retry-After` header. Provoking the HTTP form blocks your IP for up to a minute: do it on a scratch site.

### 4. Escape hatch
- **Action**: `add_filter( 'wb_listora_rate_limit_ip_counts_members', '__return_true' );`
- **Expect**: members share the IP budget again (with the guest counter already at 10 the next member hit is refused immediately).
- **Read-back**: remove the filter; step 1 passes again.

### 5. Sibling actions use the same check
- **Action**: repeat step 2 for `review_create`, claim submit and favourite add (per-user cap each).
- **Expect**: the same 429 shape per action.
- **Read-back**: route response `429` with `Retry-After`. Not verified live (read in code).

### 6. Roles and retry
- **Action**: run step 2 as {admin}; fire the same over-cap request twice in parallel.
- **Expect**: {admin} is NOT exempt (same per-user cap); both parallel calls are refused.
- **Read-back**: counter does not exceed cap + 0. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {owner}, {other} | own budget (steps 1-2) |
| {guest} | IP budget (step 3); logged-out review creation is a 401 before any limit, so guests exercise the IP counter through `submission` check or the `public_read` GET limit |
| {admin} | control, not exempt |

## Pass criteria
1. Three members on one IP are all allowed up to their own cap.
2. A member over the user cap gets 429 `scope: "user"` with `retry_after`; guests get `scope: "ip"`.
3. The filter restores shared-IP counting.

## Fail diagnostics
`includes/class-rate-limiter.php:223` (`check()`, user vs IP decision `:262-285`), `hit()` `:322`, `limit_public_reads()` `:406`, `error()` `:428`.

## Not verified
The HTTP 429 + `Retry-After` header was read in code, not provoked over HTTP; reviews/claims/favorites use the same `check()` (step 5).

## Cleanup
Delete the `listora_rl_*` transients and throwaway users; remove the filter.
