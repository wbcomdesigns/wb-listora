---
journey: listing-already-flags
plugin: wb-listora
priority: normal
roles: [member-owner]
covers: [rest, dashboard, listings, i18n, card-10154925210]
prerequisites:
  - "A member who owns at least one published listing"
estimated_runtime_minutes: 4
---

# Deactivate and reactivate say whether they changed anything

Both routes returned an identical shape whether they acted or not - `{deactivated: true, message: …}`
either way - so the only difference was an English sentence. The mobile app matched `/already/i` on
that prose, which breaks on any translated site. The account routes already answered with
`already_deactivated` / `already_active`; the listing routes now match that spelling, on both the
acting branch (`false`) and the no-op branch (`true`).

PHPUnit: `tests/unit/ListingAlreadyFlagsTest.php`.

## Steps

### 1. REST, both routes, called twice
- **Action**: as the listing owner, POST `/listora/v1/listings/{id}/deactivate` twice, then `/reactivate` twice
- **Expect**:
  - deactivate #1 → 200, `already_deactivated: false`
  - deactivate #2 → 200, `already_deactivated: true` (a no-op is a success, not an error)
  - reactivate #1 → 200, `already_active: false`
  - reactivate #2 → 200, `already_active: true`

### 2. The dashboard says which happened
- **Action**: in the member dashboard, deactivate a listing; then, in a second tab opened before that, click Deactivate on the same listing
- **Expect**: the first shows "Listing deactivated."; the stale tab shows "That listing is already deactivated." rather than claiming it just did it

### 3. Translated site
- **Expect**: the flags are unchanged by translation - nothing downstream reads the message text to decide what happened

## Pass criteria

1. Both routes carry the flag on both branches.
2. The dashboard toast follows the flag, not the prose.
