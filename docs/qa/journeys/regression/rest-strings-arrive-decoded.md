---
journey: rest-strings-arrive-decoded
plugin: wb-listora
roles: [anonymous, member]
priority: high
covers: [BC-10202832578, BC-10195032749, wb_listora_decode_text, term-names, KSES, app-contract]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A published listing whose title contains an ampersand and whose featured image alt contains it too (e.g. 746, 269, 579)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Every human-facing string leaves REST decoded

`wb_listora_decode_text()` (`includes/class-template-helpers.php:2669`) is the output rule for API payloads (native clients render
plain text); templates still escape at their own output point. Role ladder: anonymous (public reads), member (add-listing dropdown).

## Steps (verified 2026-10-12)

### 1. Title and alt agree
```bash
curl -s "$SITE_URL/wp-json/listora/v1/search?per_page=100" | jq -r '.listings[] | select(.title|test("&")) | "\(.id) \(.title) | \(.featured_image.alt // "(none)")"'
```
- **Expect**: both fields contain a literal `&` (e.g. `579 Sunrise Yoga & Wellness | Sunrise Yoga & Wellness`). `&#038;` / `&amp;`
  in either is the regression.

### 2. Excerpts too
- **Expect**: `excerpt` shows `Manhattan's` / `&`, not `Manhattan&#8217;s` / `&#038;`.
- **Observed 2026-10-12: `/listings/{id}/detail` IS decoded** (`Flames & Smoke serves ...`, `Manhattan's`) **but `/search` rows are NOT**:
  listings 59, 122, 164, 185, 234, 262, 269, 579 return `&#038;` / `&#8217;` in `excerpt`. Cause: `includes/rest/class-search-controller.php:690`
  `'excerpt' => get_the_excerpt( $post )` has no `wb_listora_decode_text()` (title at `:685` has). See PRODUCT BUG in the report.

### 3. Term names, including KSES-encoded ones (verified)
```bash
wp eval '$r = wp_insert_term("QA-J1 Fitness Centers & Gyms", "listora_listing_cat"); $t = get_term($r["term_id"]);
echo $t->name, "\n", wb_listora_decode_text($t->name), "\n"; wp_delete_term($r["term_id"], "listora_listing_cat");'
```
- **Expect**: DB holds `QA-J1 Fitness Centers &amp; Gyms` (core KSES, not our bug); decoded output reads `... & Gyms`.

### 4. The dropdown the card reported
- **Action**: `$SITE_URL/add-listing/`, pick a type with a category containing `&` (e.g. "Health & Wellness", "Bar & Grill").
- **Expect**: option reads `Health & Wellness`, not `Health &amp; Wellness` (`opt.textContent` fed by
  `GET /listing-types/{slug}/categories`, decoded at `includes/rest/class-listing-types-controller.php:287`).

### 5. Term names decoded everywhere
- **Expect** (verified in `/search` rows): `categories[].name` reads `Bar & Grill`, `Health & Wellness`.

### 6. Nothing is double-decoded
- `grep -rn "wb_listora_decode_text( wb_listora_decode_text" includes` returns nothing (verified).

## Fail diagnostics
`includes/rest/class-search-controller.php:685` (title decoded) vs `:690` (excerpt not), `includes/rest/class-listings-controller.php:887-888`,
`includes/core/class-image-schema.php:64`, `includes/class-template-helpers.php:1305-1334`.

## Not verified
Step 4 (browser dropdown at 390).
