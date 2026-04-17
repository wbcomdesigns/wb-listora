# Data-flow gaps — WB Listora (Free)

Findings from a live walk-through on directory.local (2026-04-17). Test account: `varundubey` (admin). Each gap has file:line, severity, reproduction steps, suggested fix.

Status legend:
- ✅ FIXED — one-liner or small patch shipped to main
- ❌ OPEN — logged for Phase 1+ work
- 🔶 PARTIAL — follow-up needed

---

## ROUND-TRIPS VERIFIED ✅

- **Settings save** — now correctly preserves other-tab values (G9 fix).
- **Favorite toggle** — inserts into `wp_listora_favorites (user_id, listing_id, collection, created_at)`. Button state updates.
- **Analytics aggregation** — `wp_listora_analytics.count` aggregated via `INSERT … ON DUPLICATE KEY UPDATE`. Admin dashboard `SUM(count)` matches reality.
- **Audit log** — admin page renders entries with filters / IP / details.

---

## G1 · Pro · Credit ledger table-name typo — ✅ FIXED `eedc569`

`Pro_Plugin::get_ledger_table()` was looking up `wp_listora_credits_ledger` (plural) instead of the SDK's real `wp_listora_credit_ledger`. Admin Transactions page showed "Ledger table not found" forever. One-char fix.

---

## G2 · Free · Homepage "Showing 1–0 of 0 listings" counter wrong — ❌ OPEN

**Where:** Homepage directory block — live region status.

**What:** Says 0 of 0 while 16+ cards render. Pagination / status text disconnected from actual result set.

**Impact:** Screen readers announce "0 listings" to users seeing 16. A11y violation + confusing for everyone.

**Fix location:** `src/interactivity/store.js` initial state vs pagination template. Seed from `wp_interactivity_state()` server-side OR bind status to rendered count.

**Severity:** MEDIUM.

---

## G3 · Free · Search endpoint param inconsistency (`q` vs `keyword`) — ❌ OPEN

**Where:** `includes/rest/class-search-controller.php`

- `/search/suggest` takes `?q=` (line 59)
- `/search` takes `?keyword=` (line 89)

**What:** `/search?q=foo` silently returns ALL listings because `q` isn't in the `/search` args schema. Only `/search?keyword=foo` filters.

**Fix:** Accept both names (coalesce in handler) or rename one. Pre-release = OK to break.

**Severity:** LOW — works when called correctly.

---

## G4 · Free · Onboarding banner persists on a seeded site — ❌ OPEN

`wp_listora_settings[setup_complete]=false` even on a site with 20 listings + 25 reviews + taxonomies. Banner "Welcome to WB Listora!" never goes away.

**Fix:** detect "already configured" (≥1 listing AND default pages exist) and mark complete on first admin load, or add "Mark as set up" CTA on banner.

**Severity:** LOW.

---

## G5 · Free · No auto-created submission/dashboard pages — ❌ OPEN

`wp_listora_settings[submission_page]=0`, `dashboard_page=0`. If user skips the setup wizard (banner dismissed), the `listing-submission` / `user-dashboard` blocks have no canonical page. "Submit listing" / "My account" CTAs point to post ID 0.

**Fix:** `Activator::create_default_pages()` should seed both pages idempotently on first activation.

**Severity:** MEDIUM.

---

## G6 · Free · Favorites table has no surrogate id — ❌ OPEN (INFO)

`wp_listora_favorites` PK is `(user_id, listing_id)`. REST route is documented as `DELETE /favorites/{id}` but `{id}` is actually `listing_id`. Rename or doc-clarify.

**Severity:** INFO.

---

## G7 · Free · /add-listing/ page blank — ✅ ROOT CAUSE WAS G9 (now fixed)

**Originally:** `listora-page-wrap` div rendered empty; submission form missing entirely.

**Root cause:** G9 settings-sanitize bug had silently set `enable_submission=false`, and `blocks/listing-submission/render.php:28` returned early.

**Status:** G9 fix (`46284a5`) + settings restore makes the page render the multi-step form correctly. No separate G7 fix needed.

---

## G8 · Free · `wp_listora_services` table missing on upgrade — ✅ FIXED `266edd3`

**Where:** Every listing-detail page load.

**What:** `WBListora\Core\Services::get_services()` queried `wp_listora_services` which didn't exist — `wp_content/debug.log` filled with "Table doesn't exist" on every request.

**Why it existed:** Services feature (2026-04-05) added the table to `Activator::create_tables()` but Activator only runs on fresh activation. Upgraded installs never got the table. `WB_LISTORA_DB_VERSION` wasn't bumped so `Migrator::maybe_migrate()` never ran.

**Fix:**
- `WB_LISTORA_DB_VERSION`: 1.0.0 → 1.1.0
- `Migrator::migrate_1_1_0()` re-runs `Activator::activate()` (idempotent — dbDelta).

**Takeaway:** from now on, any schema change MUST bump the DB version + add a migration. Add this to the `plans/README.md` plan template as a hard rule.

---

## G9 · Free · Settings sanitize zeroes booleans from other tabs on save — ✅ FIXED `46284a5`

**Where:** `Settings_Page::sanitize()` at `includes/admin/class-settings-page.php:70`.

**What:** Saving ONE settings tab silently turned off every boolean on every OTHER tab. Repro: save General tab with any change → confirm `wb_listora_settings`:
- `enable_submission`: 1 → 0 (causes /add-listing/ to render empty via G7)
- `enable_schema`, `enable_breadcrumbs`, `enable_sitemap`, `enable_opengraph`: 1 → 0 (SEO meta drops out of <head>)
- `map_clustering`, `map_search_on_drag`: 1 → 0 (maps lose features)

**Root cause:** sanitize iterated every default key and wrote `false` for booleans not present in `$_POST`. Other tabs' checkboxes aren't in the form being submitted, so they all got zeroed.

**Fix:** start sanitize from the existing option value as the base; only overwrite keys that appear in `$_POST`.

**Severity:** CRITICAL — any customer hits this on their very first tab save.

---

## G9a · Free · Checkboxes without hidden fallback can't be unchecked reliably — ❌ OPEN (follow-up to G9)

**Why:** G9's fix preserves existing values when keys are absent from `$_POST`. Checkboxes behave that way when unchecked. Standard WP idiom is a hidden `value="0"` input BEFORE each checkbox so unchecking produces `0` in POST.

**Action:** audit each settings tab template (`Settings_Page::render_*_tab`) — every `<input type="checkbox">` must be preceded by a sibling `<input type="hidden" name="..." value="0">`.

**Severity:** MEDIUM — functional regression if any tab's form lacks this pattern.

---

## G10 · Free · Category dropdown empty on first-render submission form — ❌ OPEN

**Where:** `blocks/listing-submission/render.php:150` — `$type_categories` is only populated when `$listing_type` is already set server-side.

**What:** Submission block rendered without a pre-selected type (the "pick your type" first step) ends up with `$type_categories = array()`. After the user picks Restaurant and the JS auto-advances to Step 2, the Category dropdown shows ONLY the "Select a category" placeholder — 0 real options, 1 placeholder.

**Impact:** Category is a required field. Users can't submit any listing from the pick-your-type flow.

**Fix options:**
1. Client-side: after `selectSubmissionType`, fetch `/listora/v1/listing-types/{slug}/fields` (already used by search) and populate the dropdown via JS.
2. Server-side: render ALL categories with `data-type-slug` attributes, filter client-side on type selection.
3. Force a page reload with `?type=restaurant` after Step 1.

Option 1 is cleanest. Needs a small fix in `src/blocks/listing-submission/view.js`.

**Severity:** HIGH — end-to-end submission is broken for this flow, which is the default (Add Listing page with no pre-set type).

---

## G11 · Free · Type-specific submission fields never render in the "dynamic" flow — ❌ OPEN

**Where:** `templates/blocks/listing-submission/step-details.php:41`

**What:** When the submission block is used without a pre-set `listingType` attribute (the default /add-listing/ flow with the "pick your type" step), the Details step outputs:

```php
echo '<div class="listora-submission__dynamic-fields" data-wp-html="state.submissionFieldsHtml">';
echo '<p>' . __( 'Select a listing type to see fields.' ) . '</p>';
echo '</div>';
```

`state.submissionFieldsHtml` is **never populated anywhere** in the codebase — grep confirms: only reference is this template line. The JS never fetches or sets it.

**Impact:** Users who pick a type from the radios still get ZERO type-specific fields (phone, address, cuisine, business hours, price range, delivery/takeout, etc.). They can submit the form with just title/category/description and the listing saves, but with empty metadata. Restaurant listings with no phone, no hours, no cuisine.

**Reproduction:**
1. Navigate to /add-listing/ (or any page with the default submission block)
2. Pick Restaurant → advance to Details step
3. Inspect DOM — only placeholder text visible
4. Submit anyway → post created, 0 meta rows in wp_postmeta

**Fix options:**
1. Add `/listora/v1/listing-types/{slug}/fields-html` REST endpoint that returns rendered HTML, then have view.js call it from `selectSubmissionType`
2. Render ALL types' field groups server-side with `data-type-slug` attributes, hide/show via JS
3. Render client-side fields from the existing `/listing-types/{slug}/fields` endpoint (already returns field_groups metadata) — requires JS field renderer parity with PHP

Option 3 is the long-term right answer — keep a single source of truth for field schema, render from schema both server + client.

**Severity:** HIGH — every dynamically-typed submission creates a listing with incomplete data. Users won't notice until they view the listing and see "Phone: —" etc.

---

## G12 · Free · Self-review and self-claim blocked correctly — ✅ NOT A GAP

`POST /listings/{id}/reviews` returns `listora_own_listing` ("You cannot review your own listing") on the owner. `POST /claims` returns same ("You already own this listing"). Both validations intentional and correct.

---

## G13 · Free · search_index.listing_type empty on first submission — ✅ FIXED `2f481f0`

**Where:** `Search_Indexer::register_hooks()` only hooked `save_post_listora_listing`. Submission calls `wp_insert_post` then `wp_set_object_terms('listora_listing_type')`. The save_post index runs BEFORE the type term exists → `listing_type=""` stamped into search_index.

**Impact:** every freshly-submitted listing invisible to type-filtered search until next post save or `wp listora reindex`.

**Fix:** Added `set_object_terms` hook (scoped to our taxonomies + listora_listing post type) that triggers re-index when type/category/location/feature terms change.

**Verified:** new submission after fix → `listing_type="restaurant"` in search_index immediately.

---

## More findings to be appended as testing continues.

## Still to test

- [x] Homepage / search / favorites round-trip — G2, G3, G6 noted
- [x] Admin listing CPT — columns, filters — (spot-checked OK)
- [x] Transactions + Audit log + Analytics + Coupons — G1 only gap
- [x] Settings round-trip — G9 + G9a
- [x] Listing detail page — G8 (services table)
- [x] /add-listing/ start — G7 (resolved by G9), G10 (categories)
- [x] Submission end-to-end — G11 (type-specific fields missing), G13 (index timing)
- [x] Review submission — G12 (self-review correctly blocked)
- [x] Claim submission — G12 (self-claim correctly blocked)
- [x] User dashboard My Listings + Favorites — counts correct, test submissions visible
- [x] Delete cascade — hard-delete cleans search_index + geo rows ✅
- [ ] CSV import round-trip
- [ ] Migration runner (dry-run)
- [ ] Non-admin user perspective (subscriber / author) — some guards might be admin-only

## Summary

**Gaps found today: 13 · Fixed today: 5 (G1 + G8 + G9 + G10 + G13) · Open: 6 · Follow-ups: 1 (G9a) · Not-a-gap: 1 (G12)**

| # | Severity | Status | Commit |
|---|---|---|---|
| G1 (Pro ledger typo) | HIGH | ✅ | `eedc569` |
| G2 (homepage counter) | MEDIUM | ❌ | — |
| G3 (search `q` vs `keyword`) | LOW | ❌ | — |
| G4 (onboarding persists) | LOW | ❌ | — |
| G5 (no auto pages) | MEDIUM | ❌ | — |
| G6 (favorites no id) | INFO | ❌ | — |
| G7 → resolved by G9 | — | ✅ | via `46284a5` |
| G8 (services table upgrade) | HIGH | ✅ | `266edd3` |
| G9 (sanitize zeroes booleans) | **CRITICAL** | ✅ | `46284a5` |
| G9a (checkbox hidden inputs) | MEDIUM | 🔶 | — |
| G10 (category dropdown empty) | HIGH | ✅ | `5d3b7bd` |
| G11 (type fields never render) | HIGH | ❌ | — |
| G12 (self-review/claim) | — | ✅ not a gap | — |
| G13 (search_index type timing) | HIGH | ✅ | `2f481f0` |
