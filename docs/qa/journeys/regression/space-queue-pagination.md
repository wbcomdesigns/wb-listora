---
journey: space-queue-pagination
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [spaces, space-moderation-queue, pagination]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A space with 25+ pending submissions, and wb_listora_user_can_moderate_space answered"
estimated_runtime_minutes: 5
covers_card: 10314572968
---

# The space moderation queue is bounded

Regression sentinel for `GET /spaces/{id}/listings/pending`.

## Background

The route returned every pending row a space had ever accumulated - 103 on the site where it was found - and hydrated a card for each in one response. No page size, no total, no way for a client to ask for less.

Fixing it surfaced a second defect the unbounded version had hidden: `ORDER BY created_at DESC` alone is not a stable sort. `created_at` has one-second resolution, so submissions made in the same second sorted arbitrarily, and with LIMIT/OFFSET an arbitrary sort puts a row on two pages and another on none. A curator moderates the same submission twice and never sees the one it displaced. The order is now `created_at DESC, listing_id DESC`.

## Steps

### 1. The default response is bounded
- **Action**: `GET /listora/v1/spaces/{id}/listings/pending` with no params, on a space with 25+ pending.
- **Expect**: at most 20 rows.

### 2. The headers say how many there really are
- **Expect**: `X-WP-Total` is the full pending count (not the page size) and `X-WP-TotalPages` matches. Same headers the reviews and favourites routes already send, so a client that paginates those needs no special case.

### 3. Paging reaches the end
- **Action**: request page 2 at `per_page=20` on 25 rows.
- **Expect**: the remaining 5.

### 4. Pages do not overlap
- **Action**: fetch page 1 and page 2 at `per_page=10` and intersect the listing ids.
- **Expect**: empty intersection. **Seed the rows in one burst** so they share a timestamp - that is the condition that broke it, and a bulk import reproduces it in production.

### 5. A page past the end still reports the total
- **Action**: `?page=99`.
- **Expect**: 200, empty array, and `X-WP-Total` still correct - a client must be able to tell "nothing on this page" from "nothing at all".

### 6. `per_page` is clamped
- **Expect**: `per_page=1000` is refused or clamped to 100. The point is a bounded response; an unbounded one by request is the same bug with extra steps.

## Automated coverage

`tests/integration/SpacePendingQueuePaginationTest.php` (7 tests), including the overlap case that caught the unstable sort.
