---
journey: space-removal-context
plugin: wb-listora
priority: normal
roles: [administrator, space-moderator, member-owner, another-member, anonymous]
covers: [spaces, space-listings-rest, buddynext-extension-surface]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A host plugin (or temporary mu-plugin) answering wb_listora_user_can_moderate_space and wb_listora_user_can_view_space; with none, only the listing's own author can DELETE and nobody can moderate"
estimated_runtime_minutes: 6
covers_card: 10317739747
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Leaving a space says how it left

`DELETE /wp-json/listora/v1/spaces/{space_id}/listings/{id}` (`includes/rest/class-space-listings-controller.php:133,410-485`; permission `can_remove()`: the listing's owner OR the space team) serves three events and now reports which. The row's status is read BEFORE `Space_Listings_Model::remove()` (`:419` then `:430`).

| Actor / prior status | `context` | `$prior_status` | `wb_listora_listing_rejected_in_space` |
|---|---|---|---|
| curator, pending | `reject` | `pending` | fires |
| curator, approved | `takedown` | `approved` | does not fire |
| the listing's author (pending or approved) | `withdraw` | row's status | does not fire |

The action `wb_listora_listing_removed_from_space` is `do_action( ..., $listing, $space_id, $actor_id, $prior_status, $context )` (`:457`); listeners registered with 3 accepted args keep working. The response body is `{ "removed": true, "context": "<context>" }`.

Verified via: source read and the permission layer over REST (logged out and admin without a host get 403 `listora_forbidden` "Only the space team can do that."). The three context outcomes were NOT executed (they need a space host and rows). PHPUnit: `tests/integration/SpaceListingRemovalContextTest.php` (5 tests).

## Steps

### 1. A curator rejects a pending submission
- **Action**: member submits a listing to a space (`POST /listings/{id}/spaces`, pending); a curator calls DELETE.
- **Expect**: `context: "reject"`; the action receives `$prior_status = 'pending'`, `$context = 'reject'`; `wb_listora_listing_rejected_in_space` also fires.

### 2. A curator takes down an APPROVED listing
- **Action**: approve first (`POST .../approve`), then DELETE as curator.
- **Expect**: `context: "takedown"`, `$prior_status = 'approved'`, rejected hook does NOT fire. This is the case that used to be indistinguishable from a rejection.

### 3. The author withdraws their own
- **Action**: the author calls DELETE (pending, then separately approved).
- **Expect**: `context: "withdraw"` both times; no rejected hook. Note: the author-vs-curator test is `actor_id === post_author` (`:422`), so a curator who is also the listing's author reads as `withdraw`.

### 4. Old listeners still work
- **Verify**: a listener with `accepted_args = 3` still receives listing, space, actor; the two new arguments are additive.

### 5. The app gets the same answer
- **Verify**: the DELETE response carries `context`.

### 6. Read before delete
- **Verify**: `status_for()` (`:419`) runs BEFORE `remove()` (`:430`).

### 7. Role ladder
| Actor | DELETE | Expect |
|---|---|---|
| curator (host filter true) | any row | 200 reject/takedown |
| member-owner of the listing | own row | 200 `withdraw` |
| another member | someone else's row | 403 `listora_forbidden` |
| logged out | any | 401/403, no row change |
| administrator, no host filter (control) | any row | 403 (admin is not a curator by default) |
