---
journey: dashboard-badge-counts-every-status
plugin: wb-listora
priority: high
roles: [member]
covers: [dashboard, counter-vs-list, cross-cutting-check-8, credits]
prerequisites:
  - "A member whose ONLY listing is in a non-headline status (listora_payment is ideal)"
estimated_runtime_minutes: 4
---

# The My Listings badge counts every status the panel shows

The sidebar badge summed four statuses (`publish`, `pending`, `listora_expired`,
`draft`) while the rows query beside it used eight. A member whose listing sat in
`listora_payment` - "Awaiting Credits", the paused-for-credits state this release
is largely about - saw **"My Listings 0"** above a panel listing that very
listing.

Fourth instance of this class in one release, after the Favorites badge, the
review headline, and the map-vs-grid disagreement. The status list is now
defined once, above both consumers.

## Steps

### 1. Badge equals rows
Sign in as a member whose only listing is `listora_payment`.
- Badge reads `1`, panel shows 1 row.
- **Fails if** the badge reads 0 while a row renders. That exact pairing is the
  bug and is never legitimate.

### 2. Every status counts
Seed one listing in each of the eight statuses. Badge must equal 8 and the panel
must render 8 rows.
- **Fails if** any status is visible in the panel but missing from the badge.

### 3. One list, not two
```bash
grep -n "listings_statuses = array" blocks/user-dashboard/render.php
```
- **Fails if** there is more than one definition. Two lists drift; that is what
  caused this.

### 4. The cached transient cannot serve a stale shape
The stats transient caches for 60s. A transient written by an older build has no
`total` key.
- Assert the fallback renders the old four-status sum rather than 0.
- Then clear `_transient%listora_dashboard_stats%` and confirm the correct total.

## Test-data trap

A member whose listings are all `publish` passes every assertion while the bug is
fully present - `publish` was one of the four counted. The fixture MUST use a
status outside the headline four.
