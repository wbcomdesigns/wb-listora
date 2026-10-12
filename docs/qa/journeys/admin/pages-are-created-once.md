---
journey: pages-are-created-once
plugin: wb-listora
priority: critical
roles: [admin, editor, guest]
covers: [page-registry, ensure-page, create-page, pages-table]
prerequisites:
  - "Site reachable at $SITE_URL; WP-CLI against $WP_PATH"
  - "WB Listora Pro active for the Pro-owned keys (compare, buy_credits, post_need, browse_needs); Free alone shows six rows"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
site_profile: required
requires: [wb-listora-pro (Pro page keys only)]
supersedes:
  - ora:regression/pages-are-created-once.md
---

# A Listora page is created once, and editing it does not spawn a second

## Why
A mapped page belongs to the owner whatever they put on it. `Page_Registry::get_id()` resolves it (mapping, then the Settings-stored id, then a live page carrying the registered block) and `Page_Registry::ensure()` creates at most once per key per site, recorded in the ledger option `wb_listora_created_pages` (key => page id). A deleted page is never silently re-created; the owner asks for it back with **Create page** in Settings. A duplicate `-2` page or a resurrected page breaks every link to the directory.

The actor is the administrator; visitors only meet the result as a 404 or a page. Only `manage_listora_settings` can open Settings > General > Pages.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Back up `wb_listora_created_pages` and the page-mapping options; do destructive steps on a scratch site. `KEY` = a Free key with a mapped page (`buy_credits` needs Pro); `PAGE` = its mapped id (`wb_listora_get_page_id( KEY )`).

## Steps

### 1. First call creates or returns, second call does not create
- **Action**: `wp eval 'echo wb_listora_ensure_page("buy_credits"), " ", wb_listora_ensure_page("buy_credits");'`
- **Expect**: the same non-zero id twice, no new page in Pages.
- **Read-back**: positive control (a never-created key): on a scratch site with the key unmapped and absent from the ledger, the first call creates (id > 0, `wb_listora_created_pages` gains the key), the second returns the same id. `wp post list --post_type=page --format=count` unchanged after the second call.

### 2. Editing the page does NOT create a second one
- **Action**: replace the mapped page's content with a shortcode or plain copy, call `wb_listora_ensure_page( KEY )` again.
- **Expect**: the same id; exactly one page for the key; `Page_Registry::status_for( KEY )` stays `linked`.
- **Read-back**: no `-2` slug page. On fail: `includes/core/class-page-registry.php:101` (`get_id()`) and `:585` (`ensure()`); resolution must not test post content or `post_status`.

### 3. Unpublishing does not create a second one
- **Action**: set the page to draft, call `ensure` again.
- **Expect**: same id (a draft is still the owner's page; `get_id()` only drops trashed/missing).
- **Read-back**: page count unchanged.

### 4. A deleted page is NOT resurrected
- **Action**: trash or delete the page, call `ensure` again.
- **Expect**: `0`, no new page; the Settings row reads **Missing** (ledger entry exists but the page is gone).
- **Read-back**: `wp post list --post_type=page --format=count` unchanged. On fail: `ensure()` at `class-page-registry.php:585` must return 0 when `$created[ $key ]` is set.

### 5. Enabling a feature creates its pages once
- **Action**: Pro Reverse Listings OFF with neither Post a Need nor Browse Needs present, turn it ON; then OFF and ON again.
- **Expect**: both pages exist and are mapped (Browse Needs on slug `needs`), and no second pair after the toggle cycle.
- **Read-back**: page counts before/after. Note: a site that already had the feature ON before 1.7.0 gets no pages automatically (it sees the admin notice); the option `wb_listora_pro_feature_pages_backfilled` does not exist.

### 6. Create page recovers a deleted one
- **Action**: `Listora > Settings > General > Pages` (`admin.php?page=listora-settings&tab=general`). A deleted page's row shows the **Missing** badge and a **Create page** button (`.button.button-secondary`). Click it.
- **Expect**: redirect back to the same tab with a green notice "Page created and mapped." and an **Edit it** link; the row reads **Linked** with **Edit page | View page** and the dropdown selects the new page; **Create page** is gone. At 390 no horizontal scroll and the button tap target >= 40px. A failed create shows "The page could not be created. Check that you have permission to publish pages."
- **Read-back**: ledger id equals the new page id. On fail: `includes/admin/class-pages-settings.php:97` (button), `:195` (`created_page_notice()`), `:218` (`create_page()`).

### 7. The Pages table lists every registered page
- **Action**: look at the Pages table.
- **Expect**: rows in order: Directory, Add Listing, My Dashboard, Browse Categories, Featured Listings, Events Calendar (Free owns these six) then, with Pro, Compare Listings, Buy Credits, Post a Need, Browse Needs. Status badges: `Linked`, `Missing`, `Trashed`, `Orphan detected`, `Feature off`. A page whose feature is off reads **Feature off** with "This page exists but its feature is switched off, so it has nothing to show and the site returns a 404 for it. Turn the feature back on, or delete the page." (see `pro-upgrade-and-downgrade`). Browse Categories and Featured Listings may map to the same page when it carries both blocks: block adoption, not a duplicate.
- **Read-back**: row count equals `count( Page_Registry::all() )`.

### 8. Pro activation / deactivation / reinstall (scratch site only)
- **Action**: (a) buy Pro later; (b) deactivate Pro; (c) reactivate; (d) uninstall then reinstall with the four page-id options deleted and the pages kept.
- **Expect**: (a) Free's pages untouched (including a renamed My Dashboard), Pro's four pages appear on the NEXT page load; (b) Free's three required pages still resolve, Pro's four stay published, only the registry keys go; (c) all four keys resolve to the SAME ids, no `-2` duplicates, no fatal; (d) every key resolves to the same page by block adoption (`heal_mapping()`), no page created.
- **Read-back**: page count identical at every stage. Not run on the shared site.

### 9. Role ladder and large site
- **Action**: open Settings > General > Pages as {editor}; with 5,000+ pages (seed) run step 1.
- **Expect**: {editor} gets "Sorry, you are not allowed to access this page."; `get_id()` resolves without scanning all pages.
- **Read-back**: query count for `get_id()` constant. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {admin} | steps 1-9 |
| {editor} | 403 on the Pages settings |
| {guest} | sees only the result (page or 404); `admin.php` redirects to login |

## Pass criteria
1. `ensure()` returns the same id on repeat; editing or unpublishing never creates a second page.
2. A deleted page is never resurrected; **Create page** recovers it with a notice.
3. The Pages table lists every registered page with a correct status.

## Fail diagnostics
| Symptom | Where |
|---|---|
| Duplicate `-2` page after an edit or draft | `includes/core/class-page-registry.php:101` (`get_id()`), `:465` (`heal_mapping()`), `:585` (`ensure()`) |
| Deleted page came back | `class-page-registry.php:585` ledger check (`OPTION_CREATED` `:564`) |
| Create page does nothing / no notice | `includes/admin/class-pages-settings.php:143-230` |
| Pro key missing from table | Pro registers at plugin load (`wb-listora-pro/includes/features/class-comparison.php:271`, `class-pricing-plans.php:2911`) |

## Not verified
Step 8 (activation, deactivation, uninstall) not run on the shared site; step 1 positive control and step 6 click-through were read from code and state, not clicked; step 9.

## Cleanup
Restore the ledger and mapping options; delete any QA page.
