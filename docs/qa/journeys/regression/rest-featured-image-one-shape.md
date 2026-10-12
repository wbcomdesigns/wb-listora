---
journey: rest-featured-image-one-shape
plugin: wb-listora
roles: [anonymous]
priority: high
covers: [BC-10194450677, BC-10203381688, Image_Schema, featured_image, app-contract]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing with a featured image whose attachment has thumbnail/medium/medium_large/large registered (e.g. listing 551)"
  - "That listing must appear in /search results and in some listing's /related"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
changed: true
---

# One `featured_image` shape on every endpoint

`Image_Schema` (`includes/core/class-image-schema.php`) is the only builder. The published set is `id, alt, thumbnail, medium,
medium_large, large, full` on `/detail`, `/related` and `/search`. A missing size is `""` (string), a listing with no image is `null`.
Role ladder: public read, anonymous (members and admin get the same payload; logged-out reads are limited to 120/minute per IP in 2.0.0,
so run the curls below in one short burst).

> Control for the data: compare the SAME listing across all three endpoints and confirm the attachment has every size first.

## Steps (curl against `$SITE_URL/wp-json/listora/v1`; verified 2026-10-12)

### 1. The attachment is not the variable
`wp eval 'echo implode(",", array_keys(wp_get_attachment_metadata(<ATT_ID>)["sizes"] ?? []));'` -> thumbnail, medium, medium_large, large present.

### 2. All three endpoints agree
- `GET /listings/<PARENT_ID>/related` (a bare JSON LIST, not an object: `[ {id, ..., featured_image}, ... ]`),
  `GET /listings/<ID>/detail` (object), `GET /search?per_page=100` (object with `listings`; find the row by id).
- **Expect** identical key sets: `alt, full, id, large, medium, medium_large, thumbnail` (verified for 551 on related and search,
  detail verified separately).

### 3. Types are stable
- **Expect**: `id` int, every other value a string (`""` when a size is missing), never `false`/`null`.

### 4. No image is `null`, everywhere (verified on listing 7200 and 845)
- `GET /listings/<id of listing without image>/detail`: the `featured_image` key IS present with value `null`. In `/search` and `/related`
  rows the value is `null` too. Assert with `array_key_exists`, not `??`.

### 5. `image_sizes` still trims (verified)
- `GET /listings/551/detail?image_sizes=thumbnail,medium` -> exactly `id, alt, thumbnail, medium`.
- `?image_sizes=banana` -> the full set (no error).

### 6. Nothing was removed
- `large` still on `/detail`, `medium_large` still on `/related` (verified present on both).

## Fail diagnostics
`includes/core/class-image-schema.php:64` (alt decode, schema builder); callers: `class-listings-controller.php`, `class-search-controller.php`
(`get_image_data()` at the `featured_image` row, `:697`; builder `:779`).

## Not verified
Step 1 attachment-size listing (assumed from the response keys).
