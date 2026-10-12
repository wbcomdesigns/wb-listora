---
journey: open-now-badge-matches-search
plugin: wb-listora
priority: high
roles: [anonymous, member-owner, administrator]
covers: [open-now-one-rule, business-hours-multi-slot, listing-detail-badge, search-open-now]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI at $WP_PATH"
  - "A published {listing} with business hours editable by {admin}"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light]
supersedes:
  - ora:regression/one-rule-per-behaviour.md
fails_on: unknown (duplicate-logic audit 2026-10-11; pre-fix sha not recorded)
site_profile: required
---

# The listing page "Open now" badge and the search "Open now" filter give the same answer

## Why
The badge and the filter were coded separately and disagreed. `Business_Hours::open_now_ids()` is the one rule (covered by `tests/integration/OpenNowTest.php`); the badge `span.listora-badge--open[role=status]` ("Open now") in `blocks/listing-detail/render.php` must match membership in `GET /listora/v1/search?open_now=1`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Give {listing} two slots today (one around now, one later).

## Steps

### 1. Inside the first slot
- **Action**: open the page as {guest}; query `GET /listora/v1/search?open_now=1`.
- **Expect**: badge "Open now" present AND {listing} in the result.

### 2. Inside the second slot
- **Action**: move the clock/slots so now is inside the second slot; repeat.
- **Expect**: same agreement (badge present, in results).

### 3. Between slots and at closing time
- **Expect**: between slots both say closed; at exactly closing time both say closed (no badge, absent from results).

### 4. Closed day, overnight slot (added)
- **Action**: a `{day, closed}` entry for today; a 22:00-02:00 slot checked at 23:00 and 01:00.
- **Expect**: badge and filter agree in each case. Not verified live.

### 5. Timezone (added)
- **Action**: site timezone UTC+14, UTC-12, then UTC.
- **Expect**: agreement uses site-local time in each. Not verified live.

### 6. N=0 / N=1 / N>1 (added)
- **Expect**: with no open listings the filter returns the empty state; with exactly one it lists it; with many it paginates; the badge matches each listing's membership.

## Role ladder
Public output identical for {guest}, {owner}, {admin}.

## Pass criteria
For every time and hours shape, badge visible exactly when the listing is in `open_now=1` results.

## Fail diagnostics

| Symptom | File |
|---|---|
| Disagreement | a surface bypasses `Business_Hours::open_now_ids()` (`includes/core/class-business-hours.php`) |
| Badge wrong | `blocks/listing-detail/render.php` |
| Filter wrong | `includes/search/class-search-engine.php` |

## Not verified
All steps (the 2026-10-12 pass covered the rule with the integration test only).

## Cleanup
Restore the listing's hours.
