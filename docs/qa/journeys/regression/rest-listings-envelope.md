---
journey: rest-listings-envelope
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [D1, REST envelope uniformity, backend-frontend consistency]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 2 published listings"
estimated_runtime_minutes: 1
---

# REST /listings returns the canonical Listora envelope (D1 sentinel)

Per the no-UX-gaps policy 2026-05-18, every Listora REST list endpoint emits
the same canonical envelope:

```
{
  "listings":    [...],   // array of listing items
  "total":       int,
  "pages":       int,
  "has_more":    bool,
  "cursor":      int|null,
  "next_cursor": int|null
}
```

`/search` already returned this shape. `/listings` previously returned a bare
WP_REST_Posts_Controller array on the OFFSET branch and the envelope on the
CURSOR branch — same payload, two shapes, depending on which query param the
caller used. Fixed by wrapping the OFFSET branch in the same envelope.

WP-standard pagination headers (`X-WP-Total`, `X-WP-TotalPages`) are still
emitted on the OFFSET branch for back-compat with WP-native clients; the
canonical pair lives in the response body.

## Setup

- Site: `$SITE_URL`
- Anonymous browser session

## Steps

### 1. OFFSET branch (no cursor param)
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/listings?per_page=2"`
- **Expect**: top-level object containing the envelope keys
  - `listings` is an array of length ≤ per_page
  - `total` ≥ length of `listings`
  - `pages` = ceil( total / per_page )
  - `has_more` = (page < pages)
  - `cursor` is `null`
  - `next_cursor` is an integer (last item's id) when there's a next page, else `null`

### 2. CURSOR branch (cursor param present)
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/listings?cursor=0&per_page=2"`
- **Expect**: same envelope shape
  - `cursor` reflects the requested cursor (0)
  - `next_cursor` is the last item's id when more results exist

### 3. Pagination headers still present
- **Action**: `curl -sI "$SITE_URL/wp-json/listora/v1/listings?per_page=2" | grep -i X-WP`
- **Expect**: `X-WP-Total`, `X-WP-TotalPages`, and `X-WP-NextCursor` headers present on OFFSET; `X-WP-Total` + `X-WP-NextCursor` on CURSOR

### 4. Cross-endpoint consistency
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/search?per_page=2"` and `curl -s "$SITE_URL/wp-json/listora/v1/listings?per_page=2"`
- **Expect**: BOTH responses have a `listings` array key at top level, `total`/`pages`/`has_more` fields. The shape is identical between the two endpoints.

### 5. End-of-pagination
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/listings?page=9999&per_page=2"`
- **Expect**:
  - `listings` is `[]`
  - `total` is the real total
  - `has_more` is `false`
  - `next_cursor` is `null`

## Pass criteria

1. Both branches (OFFSET and CURSOR) return the same envelope shape
2. `listings`, `total`, `pages`, `has_more`, `cursor`, `next_cursor` keys are always present
3. `next_cursor` lets a client switch from OFFSET → CURSOR without re-fetching
4. WP-standard pagination headers continue to work for clients that read them

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| OFFSET response is a bare array | regression of D1 — wrap removed from `get_items()` OFFSET branch | `includes/rest/class-listings-controller.php` ~line 71 — must call `$response->set_data( array( 'listings' => ... ) )` |
| Missing `next_cursor` field | wrap incomplete | same `get_items()` method — must include `next_cursor` even on OFFSET branch |
| `total` field is wrong | header parsing failed | `get_items()` reads `X-WP-Total` from parent response headers; check `$response->get_headers()` access |
| /search and /listings shapes differ | regression in one of the two | compare both endpoints — `/search` envelope is built in `class-search-controller.php` |
