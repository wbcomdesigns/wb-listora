---
journey: health-check-and-reindex
plugin: wb-listora
priority: normal
roles: [administrator, editor, member-other, anonymous]
covers: [health-check-cards, cron-health, db-tables-check, search-index-usable, site-health-tests, 2.0.0-site-health, BC-10203331648, Search_Indexer, admin_init-handlers, nonce-verification, listora_reindex]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} session; WP-Cron reachable"
  - "Destructive steps (4, 5, 6) on a SCRATCH site only: they rename a table, unschedule a cron and delete index rows"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
supersedes:
  - ora:admin/09-health-check.md
  - ora:regression/admin-reindex-button-schedules.md
fails_on: step 7 fails when the reindex action is unhandled (pre-fix sha for BC-10203331648 not recorded); step 3 is expected-fail until REQUIRED_TABLES is complete
site_profile: required
---

# Health Check and Site Health report real, fixable problems; the Rebuild Search Index button actually schedules the rebuild

## Why
Two surfaces: the Listora **Health Check** block on Settings > Advanced (`admin.php?page=listora-settings&tab=advanced`; the old `admin.php?page=listora-health` URL redirects there) with live cards, and three Listora tests in **Tools > Site Health**. Its main fix control is "Rebuild Search Index": the button once pointed at `?page=listora-settings&action=reindex` with a `listora_reindex` nonce that nothing consumed, so owners believed a stale index was rebuilt when it was not.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Reindex handler: `Admin::handle_search_reindex()` on `admin_init` calls `Search_Indexer::schedule_full_reindex()`, which clears `wb_listora_reindex_offset` and schedules the single event `Search_Indexer::REINDEX_CRON_HOOK` 30 s out; each tick processes a batch and reschedules itself 60 s out. `wp listora reindex` is the synchronous equivalent. The search reindex job is on-demand, not a recurring AS job (`system/cron-schedule-and-maintenance.md`).

## Steps

### 1. The Health Check block
- **Action**: open `admin.php?page=listora-health` as {admin}, then read the section.
- **Expect**: URL ends `page=listora-settings&tab=advanced`; section "Health Check" ("Verify activation, cron, pages, and the server environment. Each card runs a live check on page load.") with a summary ("8 passing") and cards: Database tables, Listing expiration cron, Unverified listings cron, Search index, Essential pages, Permalinks, PHP version, Memory limit; a passing card shows a check and its description (e.g. "All N Listora tables are present.", "<n> listings indexed, with the FULLTEXT index in place.").

### 2. Cron cards use the Action Scheduler-aware helper
- **Action**: `wp eval 'echo \WBListora\Workflow\Cron_Scheduler::has_scheduled("wb_listora_check_expirations") ? "scheduled":"missing";'`.
- **Expect**: `scheduled` and the card passes (a bare `wp_next_scheduled()` would false-alarm).

### 3. Table coverage matches what the plugin creates
- **Expect**: the Database tables card lists every table Listora creates (compare with `uninstall.php` and `installation-and-activation.md` step 3). `expected-fail: REQUIRED_TABLES omits email_log, leads and space_listings`, so a missing `leads` table would not be reported; PASS only when the card says all tables are present by the full count.
- **Proof (scratch)**: `wp eval 'global $wpdb; $wpdb->query("RENAME TABLE {$wpdb->prefix}listora_leads TO {$wpdb->prefix}listora_leads_qa");'`, reload: the card FAILS naming `leads` with "Open Plugins page →". Rename back.

### 4. Break and repair the expiration cron (scratch)
- **Action**: `wp eval 'as_unschedule_all_actions("wb_listora_check_expirations"); wp_clear_scheduled_hook("wb_listora_check_expirations");'`, reload.
- **Expect**: card FAILS "Cron hook wb_listora_check_expirations is not scheduled. Deactivate and reactivate WB Listora to re-register it." with "Open Plugins page →"; summary counts 1 failing. Reactivate (or load any scheduling request: wp-admin, CLI) and the card returns to pass.

### 5. Search index card (scratch)
- **Action**: see `admin/health-check-search-index-usable.md`, which owns the whole FAIL / WARN / PASS contract of the "Search index" card (empty, partial under 90%, FULLTEXT missing, Rebuild link). This journey only proves the Rebuild link reaches the reindex action (steps 7-9 below).
- **Expect**: the card is present on Settings > Advanced next to the other health cards.

### 6. WordPress Site Health
- **Action**: `$SITE_URL/wp-admin/site-health.php` > Status.
- **Expect**: three Listora tests by label: "Claim proof documents are private", "Maps have a tile source", "One currency across the site", each with a description and, when failing, an actionable fix.
- **Currency case (WooCommerce active)**: WooCommerce EUR while Listora is USD: "WooCommerce charges in a different currency from the one your prices show" as Recommended naming both codes; same currency passes.
- **Maps case**: OpenStreetMap with no Tile URL and located listings: "recommended", "Your maps have no background and will look empty" with "Set a map tile source"; Google as provider: "Maps use Google, which supplies its own map background."
- **White label (Pro)**: the Health Check description and Site Health text use the custom brand name.

### 7. A bogus nonce on the reindex action is rejected
- **Action**: as {admin} open `admin.php?page=listora-settings&action=reindex&_wpnonce=bogus`.
- **Expect**: HTTP 403 ("link expired"). HTTP 200 with the settings page rendered is the original regression (an unhandled action looks exactly like that).

### 8. The real button schedules the job, it does not run inline
- **Action**: Settings > Advanced > "Maintenance" > "Search index" > "Rebuild Search Index" (an `a.button` carrying the `listora_reindex` nonce). Before the click set `wb_listora_reindex_offset` to `500`.
- **Expect**: 302 to `?page=listora-settings&listora_reindexed=1` and `.notice.listora-notice.notice-success` "Search index rebuild scheduled. It runs in the background and may take a few minutes on large directories."; `wp eval '$h=\WBListora\Search\Search_Indexer::REINDEX_CRON_HOOK; var_export((bool)wp_next_scheduled($h)); var_export(get_option("wb_listora_reindex_offset","unset"));'` prints `true` and `'unset'` (the click cleared the cursor). The click returns immediately; a click that hangs for seconds means the work was inlined.
- **Other entry point**: the Health Check card's "Rebuild Search Index →" uses the same action and nonce and schedules the same job.

### 9. The index actually repopulates
- **Action**: let cron run (or `wp listora reindex`), compare `${PREFIX}listora_search_index` count with published listings.
- **Expect**: repopulated and equal; at 2000+ listings the rebuild takes several chunks, not one request. Scheduling without rebuilding is a different failure wearing the same success notice.

### 10. 390px
- **Expect**: health cards form a single column, fix links >= 40 px tall, no horizontal scroll.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-10 | pass; valid nonce schedules |
| user with `manage_listora_settings` only | valid-nonce reindex URL | schedules |
| {editor} | `admin.php?page=listora-health`; Settings; reindex URL with a valid nonce | "You do not have permission to access this page." / 403; nothing scheduled; `site-health.php` blocked by WordPress (`view_site_health_checks`) |
| {other} (Subscriber) | any URL; bogus nonce | 403; nothing scheduled |
| {guest} | any | redirect to wp-login.php |

## Pass criteria
1. The Health Check block renders its cards with real states.
2. Each failing card names the problem and offers a working fix; repair returns it to pass.
3. Table coverage matches the tables the plugin creates (currently a known gap, step 3).
4. The three Site Health tests are listed and accurate.
5. The reindex button is nonce- and capability-gated, schedules (not runs) the rebuild, and the index repopulates.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Section missing on Advanced | `render_section` not called | `includes/admin/class-advanced-settings-tab.php`, `class-health-check.php` |
| Cron card false alarm | `wp_next_scheduled` only | `Cron_Scheduler::has_scheduled` |
| Missing leads table unreported | `REQUIRED_TABLES` stale | `includes/admin/class-health-check.php` |
| Site Health test absent | not registered | `includes/core/class-site-health.php` `Site_Health::init` |
| 200 instead of 403 / no schedule | handler not on `admin_init` | `includes/admin/class-admin.php` `handle_search_reindex` |
| Button or notice missing | tab / notice map | `includes/admin/class-advanced-settings-tab.php`, `class-settings-page.php` |

## Not verified
Steps 3-6 and 9 mutate state and were not run on the shared site; step 8 timing, the `manage_listora_settings`-only rung and the editor rung for the reindex URL.

## Cleanup
Rename tables back; reactivate; `wp eval 'wp_unschedule_hook( \WBListora\Search\Search_Indexer::REINDEX_CRON_HOOK );'` on a large site; rebuild the index.
