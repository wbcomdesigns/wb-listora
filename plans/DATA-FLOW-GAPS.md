# Data-flow gaps — WB Listora (Free)

Findings from a live walk-through on directory.local (2026-04-17). Test account: `varundubey` (admin). Each gap has file:line, severity, reproduction steps, suggested fix.

---

## ROUND-TRIPS VERIFIED ✅

These flows write → read correctly:

- **Settings save** — changing `per_page` on `?page=listora-settings` updates `wp_options.wb_listora_settings` (serialized). Value reflects on next load. Form still uses `options.php` (legacy), not REST. (Planned migration in Phase 1.)
- **Favorite toggle** — clicking heart on listing detail inserts `wp_listora_favorites (user_id, listing_id, collection, created_at)`. Button state updates to "pressed". REST call succeeds.
- **Analytics aggregation** — `wp_listora_analytics` uses `count` column with `INSERT … ON DUPLICATE KEY UPDATE`. Admin dashboard sums with `SUM(count)` — 18 page views, 2 clicks, 11.1% CTR displayed correctly.
- **Audit log** — `wp_listora_audit_log` has 1 row from a prior test; admin page renders it with correct filters, date, IP, details payload.

---

## G1 · Pro · Credit system table-name mismatch (HIGH)

**Where:** `wb-listora-pro/includes/class-pro-plugin.php:276`

```php
$table = $wpdb->prefix . WB_LISTORA_TABLE_PREFIX . 'credits_ledger';  // ← typo
```

**What:** Three different table names exist for credits across the codebase:

| Source | Table | Status |
|---|---|---|
| SDK `Ledger::table_name()` (`vendor/wbcom-credits-sdk/src/Ledger.php:35`) | `wp_listora_credit_ledger` | Exists, used for actual writes via `Credits::topup/adjust/get_ledger` |
| Pro `Pro_Migrator::create_tables()` (`class-pro-migrator.php:30`) | `wp_listora_credit_log` | Exists, legacy fallback + gateway_txn_id idempotency markers |
| Pro `Pro_Plugin::get_ledger_table()` (`class-pro-plugin.php:276`) | `wp_listora_credits_ledger` (plural `credits_`) | **Does not exist** — typo |

**Reproduction:** `wp-admin/admin.php?page=listora-transactions` shows "Ledger table not found — The credits ledger table has not been created yet. It will appear after the first credit transaction." This message will never go away even after real purchases because the SDK writes to `credit_ledger` (singular) while this page searches for `credits_ledger` (plural).

**Fix:** change line 276 from `'credits_ledger'` to `'credit_ledger'`. Single-character bug.

**Severity:** HIGH — silently hides all admin monetization visibility.

---

## G2 · Free · Search results counter wrong on homepage (MEDIUM)

**Where:** Homepage directory block at `http://directory.local/`

**What:** The live region status text reads **"Showing 1–0 of 0 listings"** while 16+ listing cards render below. The counter is clearly disconnected from the actual result set.

**Reproduction:** Load homepage, inspect the `<div role="status">` near the grid. Says 0 of 0 even though cards are visible.

**Impact:** Screen readers announce "0 listings" to users who are looking at 16 visible listings. A11y violation, confuses sighted users too.

**Likely cause:** Initial render uses server-side state, while status text is hydrated client-side after Interactivity boots. Status ref may read from `context.totalResults` before it's populated.

**Fix location:** `src/interactivity/store.js` initial state or `templates/blocks/listing-grid/pagination.php` — need to confirm. Either seed the status text server-side with `wp_interactivity_state()` or bind it to the rendered count.

**Severity:** MEDIUM — functional but misleading, a11y-impacting.

---

## G3 · Free · Search endpoint parameter naming inconsistency (LOW)

**Where:** `wb-listora/includes/rest/class-search-controller.php`

- `/listora/v1/search/suggest` accepts param **`q`** (line 59)
- `/listora/v1/search` accepts param **`keyword`** (line 89)

**What:** Two endpoints that do conceptually the same thing (text search) take differently-named parameters. If a consumer hits `/search?q=foo`, the `q` is silently dropped (not in args schema) and the query runs with empty keyword → returns ALL listings.

**Reproduction:**
```
GET /wp-json/listora/v1/search?q=xyzxyzneverexists → total=20 (all listings)
GET /wp-json/listora/v1/search?keyword=xyzxyzneverexists → total=0
```

**Fix options:**
- Accept both `q` and `keyword` in `/search` args, coalescing inside the handler
- OR rename `/search` param to `q` for consistency with `/search/suggest` (breaking change — but pre-release is OK)

**Severity:** LOW — everything works when called with the right name. Inconsistency cost shows up in docs + integration tests.

---

## G4 · Free · First-run admin notice persists even on a seeded site (LOW)

**Where:** Settings page shows "Welcome to WB Listora! Complete the setup wizard to get started." banner

**What:** `wp_options.wb_listora_settings['setup_complete']` is `false` even though the site has 20 listings, 25 reviews, taxonomies, and pages configured. Setup was never explicitly completed via the wizard.

**Impact:** Admins see the onboarding banner forever until they walk through the wizard.

**Fix:** Detect "already configured" state (≥1 listing + default pages exist) and set `setup_complete=true` on first admin page load, OR add a "Mark as set up" CTA on the banner.

**Severity:** LOW — cosmetic but persistent nag.

---

## G5 · Free · No auto-created submission/dashboard pages (MEDIUM)

**Where:** `wp_options.wb_listora_settings` → `submission_page=0`, `dashboard_page=0`

**What:** On a fresh activation, the plugin doesn't auto-create the submission + dashboard pages. The user must run the setup wizard to create them.

**Impact:** If a user never runs the wizard (common — banners get dismissed), the `user-dashboard` and `listing-submission` blocks have no canonical page to live on. Frontend "My Account" and "Submit" CTAs silently point to post ID 0.

**Reproduction:** `SELECT option_value FROM wp_options WHERE option_name='wb_listora_settings'` → grep for `submission_page` / `dashboard_page` — both `0`.

**Fix:** `Activator::create_default_pages()` should seed both pages on first activation if they don't exist (idempotent — check by block content first).

**Severity:** MEDIUM — silent UX failure for users who skip the wizard.

---

## G6 · Free · Favorites table has no surrogate `id` column (INFO)

**Where:** `wp_listora_favorites` — primary key is `(user_id, listing_id)` composite.

**What:** The REST endpoint signature is `DELETE /favorites/{id}` (documented), but there's no `id` column. The `{id}` param must actually be `listing_id`. Needs a rename or clearer docs to avoid future confusion.

**Severity:** INFO — works because the controller correctly uses `listing_id` internally, but the route param naming is misleading.

---

## More findings to be appended as testing continues.

## Still to test

- [ ] Listing submission end-to-end (frontend form → post + postmeta + search_index + geo + hours rows)
- [ ] Review submission + helpful vote
- [ ] Claim submission + admin approval
- [ ] Admin listing trash → search_index removal (orphans)
- [ ] CSV import round-trip
- [ ] User dashboard — my listings / reviews / favorites / profile
- [ ] Migration runner (dry-run)
