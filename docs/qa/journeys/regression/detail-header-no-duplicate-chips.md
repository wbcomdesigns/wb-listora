---
journey: detail-header-no-duplicate-chips
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [BC-10194590939, BC-10194590988, badges, address, free-pro-seam]
prerequisites: ["Combo, badges ON, a verified listing whose address meta is a full formatted line"]
estimated_runtime_minutes: 4
---

# The listing header says each thing once

Two independent duplications on the same header.

**Verified twice.** Free draws a Verified chip; Pro then drew its own Verified
pill. Pro already de-dupes on CARDS, but the native set differs per surface -
Free's card chrome draws `featured`, Free's detail header draws `verified` - so
the card list could not be reused. A detail-specific list was needed.

**City and state twice.** Google Places and the submission autocomplete store a
FULL formatted line in `address` ("247 West Broadway, Manhattan, NY 10013")
while ALSO filling `city` and `state`. Concatenating all three produced
"…, NY 10013, Manhattan, NY".

## Steps

### 1. Verified renders once
On a verified listing, count chips reading exactly "Verified" in
`.listora-detail__header-top`: must be **1**.

### 2. Pro's other pills survive
Featured / Top Rated still render - Free does NOT draw those on detail, so
suppressing them would hide a real badge. **Fails if** they vanish.

### 3. Toggling verification off removes it from both
No Verified chip and no Verified pill.

### 4. Address renders once
Header address equals the stored `address` when that line already contains the
city and state; no trailing ", Manhattan, NY".

### 5. The other address shape still gains its parts
A listing whose `address` is a bare street ("247 West Broadway") plus separate
city/state must still render "247 West Broadway, Manhattan, NY".

### 6. Word-boundary matching
A listing on "12 Nyack Road" in state "NY" must not have "NY" suppressed as
already-present - it is a substring, not a component.

## Test-data trap

Both fixes are invisible on a listing that is not verified, or whose `address`
is a bare street. Pick a verified listing with a Places-style full address, and
check the bare-street case separately - one fixture cannot prove both.
