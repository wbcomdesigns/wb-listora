---
journey: admin-health-check
plugin: wb-listora
priority: normal
roles: [administrator, qa_editor]
covers: [health-check-cards, cron-health, db-tables-check, search-index-usable, site-health-tests, 2.0.0-site-health]
prerequisites:
  - "Site reachable at $SITE_URL; admin ?autologin=1"
  - "Destructive steps (4, 5) on a SCRATCH site only: they rename a table and unschedule a cron"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
---

# Health Check (Settings > Advanced) and WordPress Site Health surface real, fixable problems

Two surfaces: the Listora **Health Check** block on Settings > Advanced (`admin.php?page=listora-settings&tab=advanced`; the old `admin.php?page=listora-health` URL just redirects there) with eight live cards, and three Listora tests inside **Tools > Site Health** (Status tab): "Claim proof documents are private", "Maps have a tile source", "One currency across the site".

## Steps

### 1. The Health Check block (administrator, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-health&autologin=1` (redirects) then read the Health Check section.
- **Expect**: URL ends `page=listora-settings&tab=advanced`; a section "Health Check" ("Verify activation, cron, pages, and the server environment. Each card runs a live check on page load.") with a summary ("8 passing") and cards: Database tables, Listing expiration cron, Unverified listings cron, Search index, Essential pages, Permalinks, PHP version, Memory limit. A passing card shows a check and its description (e.g. "All 11 Listora tables are present.", "Pretty permalinks are enabled (...)", "<n> listings indexed, with the FULLTEXT index in place.").
- **On fail**: `Health_Check::render_section` (`includes/admin/class-health-check.php` ~147), `Advanced_Settings_Tab` (~316).

### 2. Honest table coverage (known gap, assert the contract)
- **Expect**: the table check covers every table Listora creates. At e4f9f093 `REQUIRED_TABLES` has 11 names and omits `email_log`, `leads` and `space_listings` (14 exist: see `uninstall.php`), so a missing `leads` table (the 2.0.0 data store) would NOT be reported. Record PASS only when the card says "All 14 Listora tables are present." (or lists them by name).
- **Proof step (scratch)**: `wp eval 'global $wpdb; $wpdb->query("RENAME TABLE {$wpdb->prefix}listora_leads TO {$wpdb->prefix}listora_leads_j20");'`, reload; expect the Database tables card FAIL naming `leads` with "Open Plugins page →". Rename back.

### 3. Cron cards
- **Action**: `wp eval 'echo \WBListora\Workflow\Cron_Scheduler::has_scheduled("wb_listora_check_expirations") ? "scheduled":"missing";'`.
- **Expect**: `scheduled`; the card passes. The card checks `wb_listora_check_expirations` and `wb_listora_cleanup_unverified_listings` through the Action Scheduler-aware helper (a bare `wp_next_scheduled()` would false-alarm).

### 4. Break and repair the expiration cron (scratch)
- **Action**: `wp eval 'as_unschedule_all_actions("wb_listora_check_expirations"); wp_clear_scheduled_hook("wb_listora_check_expirations");'`, reload the section.
- **Expect**: the Listing expiration cron card FAILS: "Cron hook wb_listora_check_expirations is not scheduled. Deactivate and reactivate WB Listora to re-register it." with the "Open Plugins page →" fix; the summary counts 1 failing.
- **Action**: reactivate (`wp plugin deactivate wb-listora && wp plugin activate wb-listora`) or reload any front page (the scheduler self-heals on load).
- **Expect**: card back to pass.

### 5. Search index card (scratch)
- **Action**: `wp eval 'global $wpdb; $wpdb->query("DELETE FROM {$wpdb->prefix}listora_search_index LIMIT 5");'`, reload.
- **Expect**: a warning/fail card "Only N of M published listings are indexed, so search results are incomplete." with "Rebuild Search Index →" (a nonce link `admin.php?page=listora-settings&action=reindex`); clicking it schedules the rebuild (see `regression/admin-reindex-button-schedules.md`) and a reload after the job shows pass.
- **Optional**: drop the FULLTEXT index (`ALTER TABLE ... DROP INDEX ...`): the card says "The FULLTEXT index is missing, so keyword search falls back to slower, less accurate matching." and the same Rebuild control recreates it.

### 6. WordPress Site Health (administrator)
- **Action**: `$SITE_URL/wp-admin/site-health.php` > Status.
- **Expect**: under Passed/Recommended/Critical improvements the three Listora tests appear by label: "Claim proof documents are private", "Maps have a tile source", "One currency across the site". Each opens a description and, when failing, an actionable fix.
- **Currency case (WooCommerce active)**: set WooCommerce currency to EUR while Listora is USD: "WooCommerce charges in a different currency from the one your prices show" appears as Recommended with both currency codes named; setting both the same makes it pass.
- **Maps case**: with OpenStreetMap as provider, no Tile URL set and listings that have locations, the test is "recommended" and reads "Your maps have no background and will look empty" with a "Set a map tile source" link; with Google as provider it reads "Maps use Google, which supplies its own map background.
- **White label (Pro)**: with Pro White Label on, the Health Check description and Site Health text use the custom brand name instead of "WB Listora".
- **On fail**: `Site_Health::checks` (`includes/core/class-site-health.php` ~34), `Claim_Proofs::run_health_test`, `Map_Health::run`.

### 7. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-6 | pass |
| qa_editor | `admin.php?page=listora-health` | "You do not have permission to access this page." / not allowed (needs `manage_listora_settings`) |
| qa_editor | `site-health.php` | WordPress gate (needs `view_site_health_checks`): not allowed |
| member | any of the URLs | not allowed |

### 8. 390px
- **Expect**: health cards form a single column, fix links are >= 40px tall, no horizontal scroll.

## Pass criteria

1. The Health Check block renders eight live cards with real states.
2. Each failing card names the problem and offers a working fix control; repair returns it to pass.
3. Table coverage matches the tables the plugin creates (currently a known gap, step 2).
4. The three Listora Site Health tests are listed and accurate.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Section missing on Advanced | `render_section` not called | `class-advanced-settings-tab.php` ~316 |
| Cron card false alarm | using `wp_next_scheduled` only | `Cron_Scheduler::has_scheduled` |
| Missing leads table unreported | `REQUIRED_TABLES` stale | `class-health-check.php` ~31 |
| Site Health test absent | `site_status_tests` not registered | `Site_Health::init` (via plugin boot) |
