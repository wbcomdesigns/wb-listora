---
journey: rest-listing-timestamps
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [C.rest.contract.timestamps, BC-9900590343, REST single-listing schema]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one published listing accessible by ID"
estimated_runtime_minutes: 1
---

# REST single-listing exposes RFC-3339 created_at / updated_at (BC #9900590343 sentinel)

Headless clients require stable ISO-8601 timestamps. The stock parent
`WP_REST_Posts_Controller` exposes `date`/`modified` (local timezone) and
`date_gmt`/`modified_gmt` (inconsistent naming). WB Listora surfaces a single
RFC-3339 pair under `created_at` / `updated_at` on BOTH paths the headless
clients hit:

1. `GET /listora/v1/listings/{id}` — via `prepare_item_for_response()`
2. `GET /listora/v1/listings/{id}/detail` — via `get_listing()`

Originally smoke `C.rest.contract.timestamps` (2026-05-18) flagged path 1.
Fix: commit 41c4a68. Path 2 added for consistency afterward.

## Steps

### 1. List/single endpoint
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/listings/<id>?_fields=id,created_at,updated_at"`
- **Expect**: `{ "id":<id>, "created_at":"YYYY-MM-DDTHH:MM:SS", "updated_at":"YYYY-MM-DDTHH:MM:SS" }`
- Both timestamps non-empty, RFC-3339 shape (no trailing 'Z' — `mysql_to_rfc3339()` returns naive RFC-3339; that's the contract)

### 2. Enriched mobile/app endpoint
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/listings/<id>/detail?_fields=id,date,modified,created_at,updated_at"`
- **Expect**: all 4 timestamp fields present
  - `date` / `modified` — local timezone (legacy)
  - `created_at` / `updated_at` — RFC-3339 GMT (canonical for headless)

### 3. Both endpoints emit the same canonical pair
- **Action**: capture `created_at` from step 1 and step 2 for the same listing ID
- **Expect**: values are byte-identical

## Pass criteria

1. Both endpoints return `created_at` and `updated_at` for any valid listing ID
2. Both values are non-empty RFC-3339 strings
3. Values match between the two endpoints for the same listing
4. Field-selection (`?_fields=created_at`) works — fields aren't quietly dropped by `rest_filter_response_fields`

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| `/listings/{id}` missing fields | regression of BC-9900590343 / 41c4a68 | `includes/rest/class-listings-controller.php` `prepare_item_for_response()` — must assign both fields before the response is returned |
| `/listings/{id}/detail` missing fields | regression of follow-up consistency fix | same file `get_listing()` data-array — must assign both fields |
| Values present but null | `post_date_gmt`/`post_modified_gmt` empty | DB-level: confirm post has GMT timestamps populated (WP fills these on save) |
| Values shaped `Y-m-d H:i:s` not RFC-3339 | code uses post_date instead of mysql_to_rfc3339() | source: must call `mysql_to_rfc3339( $post->post_date_gmt )` |
