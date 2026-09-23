---
journey: space-removal-context
plugin: wb-listora
priority: normal
roles: [administrator, subscriber]
covers: [spaces, space-listings-rest, buddynext-extension-surface]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A host plugin (or mu-plugin) answering wb_listora_user_can_moderate_space / _view_space"
estimated_runtime_minutes: 6
covers_card: 10317739747
---

# Leaving a space says how it left

Regression sentinel for the space-removal extension surface.

## Background

`DELETE /spaces/{space_id}/listings/{id}` serves three different events - a curator declining a pending submission, a curator taking an approved listing down, and a member withdrawing their own - and fired one action for all three, **after** the row had been deleted. "Your submission was declined", "your listing was removed from this space" and "you withdrew your listing" are not interchangeable things to say to a member, and the fact that separated them was in the row that had just gone.

Comparing the actor to the listing author separated "withdrew" from "the team removed it", but never a rejection from a takedown.

BuddyNext is the consumer of this API, so the seam has to carry the meaning rather than make the integration re-query state that no longer exists.

## Steps

### 1. A curator rejects a pending submission
- **Action**: member submits a listing to a space; a curator calls DELETE.
- **Expect**: response `context: "reject"`. The action receives `$prior_status = 'pending'` and `$context = 'reject'`. `wb_listora_listing_rejected_in_space` also fires.
- **On fail**: `includes/rest/class-space-listings-controller.php` → `remove()`.

### 2. A curator takes down an APPROVED listing
- **Action**: same, but the listing was approved first.
- **Expect**: `context: "takedown"`, `$prior_status = 'approved'`, and `wb_listora_listing_rejected_in_space` does **not** fire.
- **Why it matters**: this is the case that could not be told from a rejection before. If both report the same thing, the fix has regressed.

### 3. The author withdraws their own
- **Action**: the listing's author calls DELETE.
- **Expect**: `context: "withdraw"` whether the row was pending or approved, and no rejected hook. An author pulling back their own submission must not be reported as a rejection - the prior status alone would say otherwise.

### 4. Old listeners still work
- **Verify**: a listener registered with `accepted_args = 3` still receives listing, space and actor unchanged. The two new arguments are additive.

### 5. The app gets the same answer
- **Verify**: the DELETE response carries `context`, so a client does not have to infer it from who was logged in.

### 6. Read it before deleting it
- **Verify**: `status_for()` is called BEFORE `Space_Listings_Model::remove()`. Reading after the delete is how the information was lost; the order is the fix.

## Automated coverage

`tests/integration/SpaceListingRemovalContextTest.php` (5 tests) covers all three contexts, the pending-withdraw edge, and the unchanged argument order.
