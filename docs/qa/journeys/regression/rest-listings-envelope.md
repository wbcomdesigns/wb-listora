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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# REST /listings returns the Listora envelope

`GET /listora/v1/listings` wraps its rows (both OFFSET and CURSOR branches):
```
{ "listings": [...], "total": int, "pages": int, "has_more": bool, "cursor": int|null, "next_cursor": int|null }
```
`/search` returns the same top-level keys `listings`, `total`, `pages`, `has_more` (it has NO `cursor`/`next_cursor`).
WP-standard headers `X-WP-Total` / `X-WP-TotalPages` stay on the OFFSET branch; `X-WP-NextCursor` is sent too.
Role ladder: public; logged-out reads are rate-limited to 120/minute per IP (2.0.0), members and admin are not counted. Only
published listings are returned to anonymous callers (verified: all rows `status: publish`).

## Steps (verified 2026-10-12; this site: total 82, pages 41 at per_page=2)

### 1. OFFSET branch
- `curl -s -D - "$SITE_URL/wp-json/listora/v1/listings?per_page=2"`
- **Expect**: keys above; `listings` length <= 2; `pages` = ceil(total/per_page); `has_more` true while page < pages; `cursor` null;
  `next_cursor` = last row's id (e.g. 7351) while more exist, else null. Rows are WP post objects plus `listing_type`, `listora_meta`,
  `rating`, `created_at`, `updated_at`, ...

### 2. CURSOR branch
- `.../listings?cursor=0&per_page=2`
- **Expect**: same envelope; `cursor` = 0; `next_cursor` = last id; header `X-WP-Total` and `X-WP-NextCursor` (no `X-WP-TotalPages`).

### 3. Headers on OFFSET
- **Expect**: `X-WP-Total`, `X-WP-TotalPages`, `X-WP-NextCursor`.

### 4. Cross-endpoint
- `.../search?per_page=2` and `.../listings?per_page=2`: both have `listings`, `total`, `pages`, `has_more`.

### 5. End of pagination
- Last page (`page=41&per_page=2` here): `has_more: false`, `next_cursor` set to the last id.
- **Past the end** (`page=9999`): **HTTP 400 `rest_post_invalid_page_number`** "The page number requested is larger than the number of
  pages available." (core behaviour, NOT an empty envelope). Clients must stop at `has_more: false`.
- **Positive control**: `page=1` returns rows.

### 6. Big list
- `per_page=100` returns up to 100 rows in one envelope; the public list holds 81 published rows (2,700+ drafts are never served).

## Fail diagnostics
`includes/rest/class-listings-controller.php` `get_items()` (wrap on both branches); `/search` envelope in
`includes/rest/class-search-controller.php`.

## Not verified
Mobile/theme (not applicable: JSON only).
