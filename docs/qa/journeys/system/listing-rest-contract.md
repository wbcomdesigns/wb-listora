---
journey: listing-rest-contract
plugin: wb-listora
priority: high
roles: [guest, owner, other, editor, admin]
covers: [rest-envelope, rest-pagination, rest-timestamps, rest-strings-decoded, related-listings, listings-bulk, renewal-quote, services-reorder, wb_listora_decode_text, BC-9900590343, BC-10202832578, BC-10195032749, D1]
prerequisites:
  - "Site reachable at $SITE_URL (REST base $SITE_URL/wp-json/listora/v1)"
  - "At least 2 published listings; a published {listing} with same-type siblings and >= 2 services, owned by {owner}; a pending listing {pending}"
  - "A published listing whose title and featured-image alt contain an ampersand (find with the jq command in step 10)"
estimated_runtime_minutes: 15
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, unbridged]
site_profile: required
supersedes: [ora:regression/rest-listings-envelope.md, ora:regression/rest-listing-timestamps.md, ora:regression/rest-strings-arrive-decoded.md, ora:regression/listing-extra-rest.md]
fails_on: "BC-10202832578 (encoded entities in REST strings)"
---

# The listing REST routes return the documented envelope, timestamps and decoded strings, and answer owner-only routes with the right 401/403

## Why
Headless clients and the native app parse these payloads literally: a missing `has_more`, a `Z`-less timestamp mistaken for local time, `&#038;` shown in a title, or a draft leaking through bulk fetch breaks the app or exposes content. Templates still escape at output; REST emits plain text via `wb_listora_decode_text()`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `TOTAL` = `wp post list --post_type=listora_listing --post_status=publish --format=count`. `ID` = {listing}.
- Cookie + nonce for {owner}, {other}, {editor}, {admin}: `X-WP-Nonce` from `/wp-admin/admin-ajax.php?action=rest-nonce`.

## Steps

### 1. OFFSET envelope
- **Action**: `curl -s -D - "$SITE_URL/wp-json/listora/v1/listings?per_page=2"`.
- **Expect**: top-level `listings`, `total` (= TOTAL), `pages` = ceil(total/per_page), `has_more` (true while page < pages), `cursor` null, `next_cursor` = last row id while more exist else null; rows are WP post objects plus `listing_type`, `listora_meta`, `rating`, `created_at`, `updated_at`. Headers `X-WP-Total`, `X-WP-TotalPages`, `X-WP-NextCursor`.
- **Read-back**: only published rows are returned to {guest} (every `status` is `publish`).

### 2. CURSOR branch
- **Action**: `.../listings?cursor=0&per_page=2`.
- **Expect**: same envelope; `cursor` = 0; `next_cursor` = last id; headers `X-WP-Total` and `X-WP-NextCursor`, no `X-WP-TotalPages`.

### 3. /search shares the envelope
- **Action**: `.../search?per_page=2` versus `.../listings?per_page=2`.
- **Expect**: both have `listings`, `total`, `pages`, `has_more`; `/search` has no `cursor`/`next_cursor`.

### 4. End of pagination (N=0, N=1, N>1)
- **Action**: last page (`page=<pages>&per_page=2`); then `page=9999`; then a filter that matches nothing and one that matches exactly one listing.
- **Expect**: last page `has_more: false`, `next_cursor` set to the last id; past the end HTTP 400 `rest_post_invalid_page_number` (core behaviour, not an empty envelope: clients stop at `has_more: false`); empty filter returns `listings: []`, `total: 0`, `has_more: false`; one match returns `total: 1`. **Positive control**: `page=1` returns rows.

### 5. Big page and scale
- **Action**: `per_page=100`; then on a scale-seeded site (QA-prefixed N published listings from the fixtures script) read page 1 and the last page.
- **Expect**: up to 100 rows in one envelope; drafts are never served; counts equal N. Not verified at scale live.

### 6. Single-listing timestamps
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/listings/ID?_fields=id,created_at,updated_at"` and `.../listings/ID/detail?_fields=id,date,modified,created_at,updated_at`.
- **Expect**: `created_at`/`updated_at` are `YYYY-MM-DDTHH:MM:SS` (RFC-3339 from `post_date_gmt`/`post_modified_gmt`, no trailing `Z`), non-empty and byte-identical between both endpoints; `date`/`modified` stay `Y-m-d H:i:s` in site time. `?_fields=created_at` returns only that key.
- **Timezone**: repeat with the site timezone at UTC+14 and UTC-12; `created_at` is unchanged (GMT based); restore UTC.
- **Negative control**: a draft or missing id returns 404 for {guest}, not a timestamp pair.

### 7. Related listings
- **Action**: `GET /listings/ID/related` as {guest}, then `?limit=3`, then `?limit=50`.
- **Expect**: 200, array of card objects with keys `id,title,link,featured_image,rating,listing_type,listing_type_name,is_featured,location`; never contains ID; `limit=3` returns 3; `limit=50` returns 400 (max 12, default 4); no `owner` key on these rows (only `/detail` carries it).

### 8. Batch fetch by IDs
- **Action**: `POST /listings/bulk` body `{"ids":[ID,{pending}]}` as {guest}, then as {admin} (`GET` is 404: POST only; distinct from `POST /listings/bulk-moderate`).
- **Expect**: {guest} 200 `{listings,total,requested}` containing only ID; {admin} sees both. Params: `ids` (max 50), `fields` = `card` (default) or `detail`.

### 9. Renewal quote preview and services reorder (owner-gated)
- **Action**: `GET /listings/ID/renewal-quote` as {guest}, {other}, {owner}, {admin}. `POST /listings/ID/services/reorder` body `{"order":[<its service ids>]}` as {guest}, {other}, {owner}.
- **Expect**: quote {guest} 401 `listora_unauthorized`, {other} 403 `listora_forbidden`, {owner}/{admin} 200 with `plan_id, plan_name, cost, currency, duration_days, days_until_expiry, is_expired, can_renew_now, renewal_window_days, reason, balance, expiry`; nothing changes in the DB (the mutation is `POST /listings/{id}/renew`). Reorder 401, 403 (`You do not have permission to manage services for this listing.`), 200 `{"reordered":true}`; re-submitting the existing order leaves the page unchanged.

### 10. Title and alt arrive decoded
- **Action**: `curl -s "$SITE_URL/wp-json/listora/v1/search?per_page=100" | jq -r '.listings[] | select(.title|test("&")) | "\(.id) \(.title) | \(.featured_image.alt // "(none)")"'`.
- **Expect**: both fields contain a literal `&`; `&#038;` or `&amp;` in either is the regression.

### 11. Excerpts and term names decoded
- **Expect**: `excerpt` reads `Manhattan's` / `&`, not `Manhattan&#8217;s` / `&#038;` on `/listings/{id}/detail` AND on `/search` rows; `categories[].name` reads `Bar & Grill`.
- **expected-fail: /search excerpt not decoded** (BC-10202832578 follow-up): `/detail` is decoded but `/search` rows return `&#038;`/`&#8217;`; `class-search-controller.php` `'excerpt' => get_the_excerpt( $post )` lacks `wb_listora_decode_text()` (title has it).
- **Action (term)**: `wp eval '$r = wp_insert_term("QA-J1 Fitness Centers & Gyms", "listora_listing_cat"); $t = get_term($r["term_id"]); echo $t->name, "\n", wb_listora_decode_text($t->name), "\n"; wp_delete_term($r["term_id"], "listora_listing_cat");'`
- **Expect**: DB holds `... &amp; Gyms` (core KSES, not our bug); decoded output reads `... & Gyms`.

### 12. The add-listing dropdown
- **Action**: `$SITE_URL/add-listing/` as {owner}, pick a type with a category containing `&`.
- **Expect**: option reads `Health & Wellness`, not `Health &amp; Wellness` (`GET /listing-types/{slug}/categories` is decoded). At 390 the select is >= 40px tall. Not verified live.

### 13. Nothing is double-decoded
- **Action**: `grep -rn "wb_listora_decode_text( wb_listora_decode_text" includes`.
- **Expect**: no output.

## Role ladder
| Rung | Routes | Expect |
|---|---|---|
| {guest} | steps 1-8, 10-11 | public reads; rate limited (see `system/listing-api-read-rate-limit.md`) |
| {owner} | steps 9, 12 | renewal-quote 200, reorder 200; dropdown decoded |
| {other} | step 9 | 403 on both |
| {editor} | steps 1, 6 | same values as {guest} for published listings |
| {admin} (control) | steps 8, 9 | sees pending in bulk; 200 on owner routes |

## Pass criteria
1. Envelope keys and headers match on both branches and on `/search`; past the end is 400, not an empty envelope.
2. Timestamps are RFC-3339 and identical across endpoints; field selection works.
3. Related and bulk honour limits and never leak non-published listings.
4. Owner-only routes answer 401/403/200 by role and mutate nothing on preview.
5. Titles, alts, excerpts, term names and dropdown options contain no HTML entities (except the expected-fail search excerpt).

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Envelope missing keys | wrap lost on one branch | `includes/rest/class-listings-controller.php` `get_items()`; `class-search-controller.php` |
| Timestamps empty or with `Z` | `mysql_to_rfc3339( $post->post_date_gmt )` missing | `class-listings-controller.php` `prepare_item_for_response()`, `get_listing()` |
| Field selection drops the key | `rest_filter_response_fields` | same |
| related wrong or empty | `get_related()` (~:393) | `class-listings-controller.php` |
| bulk leaks drafts | status filter | `/listings/bulk` handler |
| renewal-quote 200 for non-owner | permission callback (~:417, :2042) | `class-listings-controller.php` |
| reorder not owner-gated | `includes/rest/class-services-controller.php:187` |
| `&#038;` in search excerpt | no decode (~:690) | `includes/rest/class-search-controller.php` |
| entities in category dropdown | `includes/rest/class-listing-types-controller.php:287` |

## Not verified
Step 5 scale, step 12 at 390, timezone sub-step of 6. The app-config bootstrap secrecy check is owned by `system/app-config-contract-shape.md` (cross-area).

## Cleanup
Delete the QA-J1 term and any pending fixture; restore site timezone to UTC.
