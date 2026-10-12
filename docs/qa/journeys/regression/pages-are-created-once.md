---
journey: pages-are-created-once
plugin: wb-listora
priority: critical
roles: [admin]
covers: [page-registry, ensure-page, create-page, pages-table]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "WB Listora Pro active (the Pro-owned keys compare, buy_credits, post_need, browse_needs are exercised)"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# A Listora page is created once, and editing it does not spawn a second

A mapped page belongs to the owner whatever they put on it. `Page_Registry::get_id()` resolves it
(mapping, then the Settings-stored id, then a live page carrying the registered block), and
`Page_Registry::ensure()` creates at most once per key per site, recorded in the ledger option
`wb_listora_created_pages` (key => page id). A deleted page is never silently re-created; the owner
asks for it back with **Create page** in Settings.

Role ladder: this is a site-owner/system journey (the actor is the administrator; visitors only meet
the result as a 404 or a page). Admin is the only role that can open Settings > General > Pages
(`manage_listora_settings`).

Verified via: wp-cli + curl/DOM against http://wb-listora.local. Steps 8-11 (activate/deactivate/uninstall Pro)
were not run, see Not verified.

## Steps

### 1. First call creates or returns, second call does not create
- **Action**: `wp eval 'echo wb_listora_ensure_page("buy_credits"), " ", wb_listora_ensure_page("buy_credits");'`
- **Expect**: the same non-zero id twice (on this site `10 10`), no new page in Pages.
- **Positive control (a never-created key)**: on a scratch site with the key unmapped and absent from the
  ledger, the first call creates (id > 0, `wb_listora_created_pages` gains the key), the second returns the same id.

### 2. Editing the page does NOT create a second one
- **Action**: replace the mapped page's content with a shortcode or plain copy, call `wb_listora_ensure_page( key )` again.
- **Expect**: the same id; exactly one page for the key. `Page_Registry::status_for( key )` stays `linked`.
- **On fail**: a new id and a `-2` slug page. See `includes/core/class-page-registry.php:101` (`get_id()`) and
  `:585` (`ensure()`); resolution must not test post content or `post_status`.

### 3. Unpublishing does not create a second one
- **Action**: set the page to draft, call `ensure` again.
- **Expect**: same id (a draft is still the owner's page; `get_id()` only drops trashed/missing).

### 4. A deleted page is NOT resurrected
- **Action**: trash or delete the page, call `ensure` again.
- **Expect**: `0`, no new page; the Settings row reads **Missing** (a key whose ledger entry exists but whose page is gone).
- **On fail**: `ensure()` at `class-page-registry.php:585` must return 0 when `$created[ $key ]` is set.

### 5. Enabling a feature creates its pages once
- **Action**: Pro Reverse Listings OFF with neither Post a Need nor Browse Needs present, turn it ON; then OFF and ON again.
- **Expect**: both pages exist and are mapped (Browse Needs on slug `needs`), and no second pair after the toggle cycle.
- **Note**: a site that already had the feature ON before 1.7.0 gets no pages automatically (it sees the admin notice).
  The option `wb_listora_pro_feature_pages_backfilled` does not exist; do not look for it.

### 6. Create page recovers a deleted one
- **Action**: Listora > Settings > General > Pages (`admin.php?page=listora-settings&tab=general`). A deleted page's row shows
  the **Missing** badge and a **Create page** button (`.button.button-secondary`). Click it.
- **Expect**: redirect back to the same tab with a green notice "Page created and mapped." and an **Edit it** link;
  the row now reads **Linked** with **Edit page | View page** links and the dropdown selects the new page; **Create page** is gone.
- **Mobile (390)**: no horizontal page scroll, Create page tap target >= 40px.
- **Not allowed**: a failed create shows "The page could not be created. Check that you have permission to publish pages."
- **On fail**: `includes/admin/class-pages-settings.php:97` (button), `:195` (`created_page_notice()`), `:218` (`create_page()`).

### 7. The Pages table lists every registered page
- **Action**: look at the Pages table.
- **Expect** (this site, rows in order): Directory, Add Listing, My Dashboard, Browse Categories, Featured Listings,
  Events Calendar, Compare Listings, Buy Credits, Post a Need, Browse Needs.
  Free owns the first six; Pro adds the last four (so Free alone shows six rows).
- **Status badges**: `Linked`, `Missing`, `Trashed`, `Orphan detected`, `Feature off`. A page whose feature is off reads
  **Feature off** with the explanation "This page exists but its feature is switched off, so it has nothing to show and the
  site returns a 404 for it. Turn the feature back on, or delete the page." (see `pro-upgrade-and-downgrade.md`).
- **Note**: Browse Categories and Featured Listings both map to the same page on this site (the home page, id 281) because
  it carries both blocks. That is by design (block adoption), not a duplicate.

### 8-11. Pro activation / deactivation / reinstall (NOT RUN)
These steps activate, deactivate and uninstall Pro on a live shared site. Run them only on a scratch site.
- **8 Buying Pro later**: Free's pages untouched (including a renamed My Dashboard), Pro's four pages appear on the NEXT page load.
- **9 Deactivating Pro**: Free's three required pages still resolve; Pro's four pages stay published; only the registry keys go.
- **10 Reactivating**: all four keys resolve to the SAME ids, no `-2` duplicates, no fatal on activation.
- **11 Uninstall then reinstall**: with the four page-id options deleted and the pages kept, every key resolves to the same
  page by block adoption (`heal_mapping()`), and no page is created.

## Fail diagnostics

| Symptom | Where |
|---|---|
| Duplicate `-2` page after an edit or a draft | `includes/core/class-page-registry.php:101` (`get_id()`), `:465` (`heal_mapping()`), `:585` (`ensure()`) |
| Deleted page came back | `class-page-registry.php:585` ledger check (`OPTION_CREATED` at `:564`) |
| Create page does nothing / no notice | `includes/admin/class-pages-settings.php:143-230` |
| Pro key missing from the table | Pro registers at plugin load (`wb_listora_ensure_page` callers: `wb-listora-pro/includes/features/class-comparison.php:271`, `class-pricing-plans.php:2911`) |

## Not verified
- Steps 8-11 (plugin activation, deactivation, uninstall): not run on the shared site.
- Step 1 positive control (first creation) and step 6 click-through: code and state read, not clicked.
