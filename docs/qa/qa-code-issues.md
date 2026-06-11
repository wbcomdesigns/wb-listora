# WB Listora — QA Code Issues

**Date:** 2026-03-20
**Score:** 72/100

---

## Critical Code Issues

### CRITICAL-001: Keyword Search $wpdb->prepare() Param Ordering
- **File:** `includes/search/class-search-engine.php:200-203`
- **Issue:** Keyword param appended to `$params` AFTER WHERE params, but the MATCH...AGAINST(%s) in SELECT appears BEFORE WHERE %s placeholders. Maps 'publish' into MATCH and keyword into status filter. ALL keyword searches return 0 results
- **Fix:** Reorder params — add keyword SELECT param before WHERE params:
```php
// After building $select with MATCH...AGAINST(%s):
$select_params = [];
if (!empty($args['keyword'])) {
    $select_params[] = $args['keyword'];
}
// Then merge: ...$select_params, ...$params
```

### CRITICAL-002: Pro REST Routes Missing permission_callback (7 endpoints)
- **Files:**
  - `wb-listora-pro/includes/features/class-advanced-search.php:34,52`
  - `wb-listora-pro/includes/features/class-analytics.php:52,68,81`
  - `wb-listora-pro/includes/features/class-comparison.php:26`
  - `wb-listora-pro/includes/features/class-lead-form.php:31`
- **Fix:** Add `'permission_callback' => '__return_true'` for public, or capability checks for admin

---

## High Code Issues

### HIGH-001: Unescaped Output in 11 render.php Files
- **Files:** `blocks/*/render.php` — listing-calendar:77, listing-categories:51, listing-featured:52, listing-grid:71, listing-map:113, listing-reviews:81, listing-search:70, listing-submission:30,112, user-dashboard:16,123
- **Fix:** Wrap dynamic output with `esc_html()`, `esc_attr()`, `wp_kses_post()`

### HIGH-002: Unsanitized Superglobal Access (12 instances)
- **Files:**
  - `blocks/listing-calendar/render.php:15-16` ($_GET)
  - `includes/admin/class-admin.php:128` ($_GET)
  - `includes/admin/class-listing-columns.php:166,190` ($_GET)
  - `includes/admin/class-setup-wizard.php:21,43-45,59` ($_POST)
  - `wb-listora-pro/includes/features/class-license.php:33,37` ($_POST)
  - `wb-listora-pro/includes/features/class-verification.php:71-87` ($_POST)
- **Fix:** Wrap with `sanitize_text_field()`, `absint()`, etc.

### HIGH-003: SQL Without $wpdb->prepare() (12 instances)
- **Files:** `includes/class-cli-commands.php:335,353`, `seed-demo.php:20-24`, `uninstall.php:39,43,46`
- **Fix:** Use `$wpdb->prepare()` or add phpcs:ignore with justification (table names only)

### HIGH-004: N+1 Query Patterns (8 locations)
- **Files:**
  - `includes/rest/class-search-controller.php:328` (meta in loop)
  - `includes/search/class-search-engine.php:517` (get_the_title in usort)
  - `includes/search/class-search-engine.php:572,621` (facet queries per field)
  - `includes/search/class-facets.php:118`
  - `includes/rest/class-reviews-controller.php:162` (get_user_by in loop)
  - `includes/admin/class-listing-columns.php:183`
  - `includes/search/class-search-indexer.php:356`
- **Fix:** Batch-fetch before loops, prime meta cache

### HIGH-005: Single Listing Detail Block Not Rendering
- **File:** `blocks/listing-detail/render.php`
- **Issue:** Block content not injected on single `listora_listing` posts. Shows plain WP post instead
- **Fix:** Auto-inject via `single_template` filter or block template for the CPT

---

## Medium Code Issues

### MED-001: Pro Plugin Has 349 WPCS Errors (267 Auto-fixable)
- **Fix:** Run `phpcbf --standard=WordPress wb-listora-pro/`

### MED-002: Suggest Endpoint Uses Prefix-Only LIKE
- **File:** `includes/rest/class-search-controller.php:466`
- **Fix:** Use `'%' . $wpdb->esc_like($query) . '%'`

### MED-003: No Dark Mode CSS Support
- **Fix:** Add `prefers-color-scheme: dark` rules in `assets/css/shared.css`

### MED-004: No RTL CSS Support
- **Fix:** Add CSS logical properties or `[dir=rtl]` overrides

### MED-005: Missing Image Alt Attributes (4 locations)
- **Files:** `listing-card:80`, `listing-detail:155`, `listing-reviews:198`, `user-dashboard:220`

### MED-006: Accessibility Warnings (24)
- Buttons without `type`, heading hierarchy skips, small touch targets

### MED-007: Hooks in Constructor (Pro, 10 classes)
- **Fix:** Move `add_action/add_filter` to `init()` methods

### MED-008: $wpdb->insert/update Without Format Arrays
- **Fix:** Add `array('%s', '%d', ...)` format params

### MED-009: Review Reports Stored in wp_options Per-Review
- **File:** `class-reviews-controller.php:434`
- **Fix:** Consider a reports table for scalability

### MED-010: Options May Autoload Large Data
- **File:** `class-setup-wizard.php:73,550`
- **Fix:** Pass `'no'` as third argument to `update_option()`

### MED-011: Deprecated Function get_settings()
- **File:** `includes/rest/class-settings-controller.php:60`
- **Fix:** Use `get_option()` instead

### MED-012: 500 Server Error on Add Listing Page
- **Page:** `/add-listing/`
- **Fix:** Check console error source and fix

### MED-013: Reviews Have Invalid User IDs (25 reviews)
- **Source:** Demo seed data creates reviews with non-existent user IDs
- **Fix:** Update `seed-demo.php` to use the admin user ID for demo reviews

---

## Architecture Scores (from QA Audit)

| Section | Score |
|---------|-------|
| Code Review | 5/10 |
| Architecture | 10/10 |
| Block QA | 8/10 |
| REST API | 7/10 |
| Plan Gaps | 6.5/10 |
| Database | 9/10 |
| Search Perf | 8/10 |
| Theme Compat | 6/10 |
| **Weighted Total** | **72/100** |

---

## Search Performance (all under target)

| Query | Time | Target |
|-------|------|--------|
| No filter | 72ms | <100ms |
| Type filter | 61-64ms | <100ms |
| Geo (5km) | 88ms | <200ms |
| Faceted | 97ms | <300ms |
| Large page (50) | 115ms | <150ms |
| Min rating | 133ms | <150ms |
| Keyword | BROKEN | - |

## Database Integrity

- 11/11 tables exist with proper schemas
- 14 indexes on search_index (including FULLTEXT)
- 0 orphaned rows across all tables
- 20 listings = 20 indexed = 20 geo (perfect sync)
