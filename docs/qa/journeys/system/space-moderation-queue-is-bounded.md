---
journey: space-moderation-queue-is-bounded
plugin: wb-listora
priority: normal
roles: [admin, space-moderator, other, guest]
covers: [spaces, space-moderation-queue, pagination, card-10314572968]
prerequisites:
  - "A host plugin or temporary mu-plugin answering `wb_listora_user_can_moderate_space` (Free ships no space host; with none EVERY user including an administrator gets 403)"
  - "A space (any integer id) with 25+ pending rows in ${PREFIX}listora_space_listings seeded in one burst (QA- listings, same created_at second)"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: []
themes: []
supersedes:
  - ora:regression/space-queue-pagination.md
fails_on: unrecorded
site_profile: required
---

# The space moderation queue returns bounded, non-overlapping pages with a true total

## Why
`GET /wp-json/listora/v1/spaces/{space_id}/listings/pending` (`includes/rest/class-space-listings-controller.php:91-114,321-368`) returned every pending row and paged unstably when rows shared a timestamp. Permission is `can_moderate()` -> filter `wb_listora_user_can_moderate_space` (`:207-214`), else 403 `listora_forbidden` "Only the space team can do that.". Order is `ORDER BY created_at DESC, listing_id DESC` (`includes/core/class-space-listings-model.php:197`); headers `X-WP-Total`, `X-WP-TotalPages` (`:367-368` and the empty branch `:335-336`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- REST journey: authenticate with the nonce as the moderator; install the host filter as a temporary mu-plugin returning true for $SPACE_MODERATOR.
- Seed the rows in one burst so they share a timestamp (the condition that broke it).

## Steps

1. **Default response is bounded**: `GET .../spaces/{id}/listings/pending` with no params as $SPACE_MODERATOR. **Expect**: at most 20 rows (default `per_page` 20).
2. **Headers report the real total**: **Expect** `X-WP-Total` = the full pending count (not the page size); `X-WP-TotalPages = ceil(total / per_page)`.
3. **Paging reaches the end**: `page=2` at `per_page=20` on 25 rows. **Expect**: the remaining 5.
4. **Pages do not overlap**: page 1 and 2 at `per_page=10`, intersect `listing_id`s. **Expect**: empty intersection.
5. **A page past the end still reports the total**: `?page=99`. **Expect**: 200, `[]`, `X-WP-Total` still correct.
6. **`per_page` is refused above 100**: `?per_page=1000`. **Expect**: 400 `rest_invalid_param` (argument `maximum: 100`); `per_page=100` is accepted and returns up to 100 rows (positive control).
7. **Scale** (added): seed 2000+ pending rows; page 1 stays 20 rows and the query uses the table's index on `(space_id, status, created_at)`. Not verified live; check `SHOW INDEX FROM ${PREFIX}listora_space_listings`.

## Role ladder
| Actor | Expect |
|---|---|
| $SPACE_MODERATOR (host filter true) | 200 as above |
| {other} (no filter) | 403 `listora_forbidden` |
| {guest} | 403 (observed; this route does not return 401 `listora_unauthorized`) |
| {admin} without the host filter | 403 (observed); with the filter returning true 200 |

## Pass criteria
1. Default and maximum page sizes are bounded.
2. `X-WP-Total` and `X-WP-TotalPages` are the real totals, also past the end.
3. Pages never overlap, even with identical timestamps.
4. Only a host-approved moderator gets 200.

## Fail diagnostics
| Symptom | File |
|---|---|
| Unbounded response | `includes/rest/class-space-listings-controller.php:91-114` |
| Overlapping pages | tie-break `listing_id` in `class-space-listings-model.php:197` |
| Total = page size | headers `:367-368`, empty branch `:335-336` |
| Admin gets 200 with no host | permission callback `:207-214` |

## Not verified
Only the refusals (`per_page=1000` -> 400, `page=99` -> 403 as admin, logged out -> 403) were observed; paging steps need the host filter and seeded rows. Automated: `tests/integration/SpacePendingQueuePaginationTest.php` (7 tests).

## Cleanup
Delete the seeded rows and the mu-plugin.
