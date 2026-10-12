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
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# REST single-listing exposes RFC-3339 created_at / updated_at

Headless clients need stable ISO-8601 timestamps. Both `GET /listora/v1/listings/{id}` and `GET /listora/v1/listings/{id}/detail`
carry `created_at` / `updated_at` as `mysql_to_rfc3339( post_date_gmt / post_modified_gmt )`: naive RFC-3339 with no trailing `Z`.
`date` / `modified` stay in site time (legacy). Role ladder: public read; anonymous is the contract, a member and admin get the same
values (published listings only; a draft is 404 anonymously).

## Steps (verified 2026-10-12 on listing 7351 at the time)

### 1. Single endpoint
- `curl -s "$SITE_URL/wp-json/listora/v1/listings/<id>?_fields=id,created_at,updated_at"`
- **Expect**: `{"id":<id>,"created_at":"YYYY-MM-DDTHH:MM:SS","updated_at":"YYYY-MM-DDTHH:MM:SS"}`; both non-empty.

### 2. Enriched app endpoint
- `curl -s ".../listings/<id>/detail?_fields=id,date,modified,created_at,updated_at"`
- **Expect**: all four present; `date`/`modified` are `Y-m-d H:i:s` (site time), `created_at`/`updated_at` are `Y-m-dTH:i:s`.

### 3. Same canonical pair
- **Expect**: `created_at` / `updated_at` byte-identical between steps 1 and 2.

### 4. Field selection works
- **Expect**: `?_fields=created_at` returns only that key (not dropped by `rest_filter_response_fields`).

### 5. Negative control
- A draft or non-existent id returns 404 (not a timestamp pair).

## Fail diagnostics
`includes/rest/class-listings-controller.php` `prepare_item_for_response()` (both fields before return) and `get_listing()` data array;
must call `mysql_to_rfc3339( $post->post_date_gmt )`.

## Not verified
Steps 4 and 5 on a draft were checked only through the sibling journeys.
