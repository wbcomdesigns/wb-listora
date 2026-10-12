---
journey: space-listings-rest
plugin: wb-listora
priority: normal
roles: [space-team, owner, other, guest, admin]
covers: [spaces, space-listings-rest, showcase, buddynext-extension-surface, card-10317739747]
prerequisites:
  - "A host plugin (or temporary mu-plugin) answering `wb_listora_user_can_moderate_space` and `wb_listora_user_can_view_space`; with none, only the listing's own author can DELETE and nobody can moderate. BuddyNext not required when the filters are forced."
  - "Throwaway space ids SPACE and SPACE2 (any unused integers)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [n/a]
themes: [n/a]
site_profile: required
supersedes: [ora:regression/space-removal-context.md, ora:regression/space-showcase-published-listings-only.md]
fails_on: "approve() accepting a pending listing or page id; remove() not reporting context"
---

# Only published listings enter a space showcase, the total counts only those, and leaving a space says how it left

## Why
A curator could "approve" any post id (another member's pending listing, a private attachment, a page): nothing displayed but the row was stored, the approval hook fired and `X-WP-Total` counted it. Removal also served three different events (reject, take-down, withdraw) with one indistinguishable hook, so host plugins notified the wrong people. REST-only: Free has no space UI (BuddyNext renders the showcase).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `wp eval-file` with `wp_set_current_user( {admin} )`; force the filters for the run: `add_filter( 'wb_listora_user_can_moderate_space', '__return_true' ); add_filter( 'wb_listora_user_can_view_space', '__return_true' );`
- Create QA-J1- fixtures: a published `listora_listing` PUB, a `pending` listing PEND, a published `page` PAGE.

## Steps

### 1. Positive control: approve a published listing
- **Action**: `POST /listora/v1/spaces/{SPACE}/listings/{PUB}/approve` as the space team.
- **Expect**: 200 `{"status":"approved"}`.
- **Read-back**: one row for `(SPACE, PUB)` in `${PREFIX}listora_space_listings` with `status = approved`.

### 2. Approve a pending listing and a page id
- **Action**: same route with `{PEND}`, then `{PAGE}`.
- **Expect**: 404 `listora_not_found` "Listing not found." for both.
- **Read-back**: no row stored for either id.

### 3. Showcase total counts only what renders
- **Action**: insert a row `(SPACE2, PAGE)` with `status = approved` straight into the table (leftover simulation); `GET /listora/v1/spaces/{SPACE2}/listings`.
- **Expect**: 200, `X-WP-Total: 0`, empty array. **Positive control**: `GET .../spaces/{SPACE}/listings` returns `X-WP-Total: 1` and the published PUB.

### 4. A curator rejects a pending submission
- **Action**: a member submits to the space (`POST /listings/{id}/spaces`, pending); a curator calls `DELETE /listora/v1/spaces/{SPACE}/listings/{id}`.
- **Expect**: body `{"removed":true,"context":"reject"}`; the action `wb_listora_listing_removed_from_space( $listing, $space_id, $actor_id, $prior_status, $context )` receives `pending` and `reject`; `wb_listora_listing_rejected_in_space` also fires.

### 5. A curator takes down an APPROVED listing
- **Action**: approve first, then DELETE as curator.
- **Expect**: `context: "takedown"`, `$prior_status = 'approved'`; the rejected hook does NOT fire (previously indistinguishable from a rejection).

### 6. The author withdraws their own
- **Action**: the listing's author calls DELETE (pending, then separately approved).
- **Expect**: `context: "withdraw"` both times; no rejected hook. The author-versus-curator test is `actor_id === post_author`, so a curator who is also the author reads as `withdraw`.

### 7. Old listeners and ordering
- **Verify**: a listener registered with `accepted_args = 3` still receives listing, space, actor (the two new arguments are additive); `status_for()` runs BEFORE `remove()` so the prior status is real.

### 8. Scale and paging
- **Action**: none restated here for the pending queue: its bound (default 20, `X-WP-Total` / `X-WP-TotalPages`, no overlap, past-the-end, `per_page` max 100) is `system/space-moderation-queue-is-bounded.md`. For the approved showcase: seed N QA-prefixed approved rows (N > one page) and read it with `per_page` and `page`.
- **Expect**: `X-WP-Total` equals N and pages cover all rows without duplicates. Not verified live.

## Role ladder
| Actor | Call | Expect |
|---|---|---|
| Space team (filter true) | approve PUB; DELETE any row | 200; reject or takedown context |
| {owner} of the listing | DELETE own row | 200 `withdraw` |
| {other} (moderate filter false, default) | approve PUB; DELETE someone else's row | 403 `listora_forbidden` "Only the space team can do that." |
| {guest} | approve / DELETE | 401/403, no row change |
| {admin}, no host filter (control) | approve / DELETE | 403 (admin is not a curator by default; the gate is the space filter, not the role) |

## Pass criteria
1. Published listing approves (200); pending listing and page 404 `listora_not_found`, nothing stored.
2. `X-WP-Total` equals items rendered even with a non-listing row in the table.
3. Reject, takedown and withdraw each report their own `context` and prior status; the response carries `context`.
4. Non-team callers never change a row.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Pending listing or page approved | `is_listing()` + `'publish' !== get_post_status()` guard lost | `includes/rest/class-space-listings-controller.php` `approve()` (~:386) |
| Total larger than items | INNER JOIN on posts / `post_status = 'publish'` lost | `includes/core/class-space-listings-model.php` `approved_count()` (~:240) |
| Non-team caller gets 200 | `can_moderate()` relaxed | `class-space-listings-controller.php` (~:206) |
| takedown reads as reject | prior status read after `remove()` | `class-space-listings-controller.php` (~:419 vs :430) |

## Not verified
Steps 1-3 were run via `rest_do_request` on 2026-10-12; steps 4-8 and the 403 rows were read from source only (they need a space host and rows). Real BuddyNext spaces were not exercised. PHPUnit: `tests/integration/SpaceListingRemovalContextTest.php`.

## Cleanup
Delete table rows for both space ids and the three QA-J1- posts; remove the forced filters.
