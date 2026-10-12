---
journey: space-queue-pagination
plugin: wb-listora
priority: normal
roles: [administrator, space-moderator, member, anonymous]
covers: [spaces, space-moderation-queue, pagination]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A host plugin or temporary mu-plugin answering `wb_listora_user_can_moderate_space` (Free ships no space host; with none, EVERY user including administrator gets 403)"
  - "A space (any integer id) with 25+ pending rows in {prefix}listora_space_listings seeded in one burst (QA-J1- listings, same created_at second)"
estimated_runtime_minutes: 5
covers_card: 10314572968
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# The space moderation queue is bounded

REST-only journey: `GET /wp-json/listora/v1/spaces/{space_id}/listings/pending` (`includes/rest/class-space-listings-controller.php:91-114,321-368`), permission `can_moderate()` -> filter `wb_listora_user_can_moderate_space` (`:207-214`), else 403 `listora_forbidden` "Only the space team can do that.".

Verified via: curl as admin with the nonce (no moderation host installed here): `per_page=1000` -> 400 (argument `maximum: 100`), `page=99` -> 403 `listora_forbidden` (so the admin is NOT a moderator by default); logged out -> 403. The paging steps need the host filter and seeded rows and were NOT executed. Source verified: order `ORDER BY created_at DESC, listing_id DESC` (`includes/core/class-space-listings-model.php:197`), headers `X-WP-Total`, `X-WP-TotalPages` (`:367-368` and the empty branch `:335-336`).

## Steps

### 1. The default response is bounded
- **Action**: `GET .../spaces/{id}/listings/pending` with no params (as the host-approved moderator).
- **Expect**: at most 20 rows (default `per_page` 20).

### 2. Headers report the real total
- **Expect**: `X-WP-Total` is the full pending count (not the page size); `X-WP-TotalPages = ceil(total / per_page)`.

### 3. Paging reaches the end
- **Action**: `page=2` at `per_page=20` on 25 rows. **Expect**: the remaining 5.

### 4. Pages do not overlap
- **Action**: page 1 and 2 at `per_page=10`, intersect `listing_id`s. **Expect**: empty intersection. Seed the rows in one burst so they share a timestamp (the condition that broke it).

### 5. A page past the end still reports the total
- **Action**: `?page=99`. **Expect**: 200, `[]`, `X-WP-Total` still correct.

### 6. `per_page` is refused above 100
- **Action**: `?per_page=1000`. **Expect** (observed): 400 `rest_invalid_param`; `per_page=100` is accepted. Positive control: `per_page=100` returns up to 100 rows.

### 7. Role ladder
| Actor | Expect |
|---|---|
| space moderator (host filter true) | 200 as above |
| another member (no filter) | 403 `listora_forbidden` |
| logged out | 403 (observed; no `listora_unauthorized`) |
| administrator without the host filter | 403 (observed); with the filter returning true 200 |

## Automated coverage
`tests/integration/SpacePendingQueuePaginationTest.php` (7 tests, verified count).
