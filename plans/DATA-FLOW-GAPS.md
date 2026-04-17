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

## More findings to be appended as testing continues.

## Still to test

- [x] Homepage / search / favorites round-trip — G2, G3, G6 noted
- [x] Admin listing CPT — columns, filters — (spot-checked OK)
- [x] Transactions + Audit log + Analytics + Coupons — G1 only gap
- [x] Settings round-trip — G9 + G9a
- [x] Listing detail page — G8 (services table)
- [x] /add-listing/ start — G7 (resolved by G9), G10 (categories)
- [ ] Review submission end-to-end
- [ ] Claim submission + admin approval
- [ ] User dashboard — my listings / reviews / favorites / profile
- [ ] Admin listing trash → search_index removal (orphans)
- [ ] CSV import round-trip
- [ ] Migration runner (dry-run)
