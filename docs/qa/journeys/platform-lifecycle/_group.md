---
group: platform-lifecycle
plugin: wb-listora
scope: Install and activation, Health Check and Site Health, search reindex, cron and Action Scheduler jobs, Pro activation side effects, translations, uninstall
journeys: 10
site_profile: required
---
# Platform lifecycle: what an agent checks here

## Scope
Covers how the plugin comes to life, stays healthy and goes away: activation (schema, caps, options, wizard redirect), the Health Check block and Site Health tests, the Rebuild Search Index action, recurring jobs on Action Scheduler, the pages that disappear when a Pro feature is off, translations that render, and "delete data on uninstall".

Most journeys are WP-CLI or database journeys. Only a handful of surfaces exist in a browser, so the surface sweep is short but each surface is dense. Run the CLI journeys for correctness; use the browser only on the surfaces below.

Does NOT cover: the settings tabs themselves and the setup wizard steps (`settings-setup`), the admin shell header and dependency notices (`admin-listings`), CSV import and demo import (`import-migration`), role and capability matrix (`access-security`), email templates and delivery (`emails-alerts`), outgoing webhooks (`integrations-api`), licence screens (`settings-setup`, Pro).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Settings > Advanced tab (Health Check block, Maintenance, Data Management) | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=advanced` | {admin}; user with `manage_listora_settings`. {editor}, {other}, {guest}: refused | `includes/admin/class-advanced-settings-tab.php`, `includes/admin/class-health-check.php` (`render_section()`) |
| 2 | Legacy Health URL | `$SITE_URL/wp-admin/admin.php?page=listora-health` (302 to surface 1) | same as 1 | `includes/admin/class-admin.php` (redirect near line 745); full-page `Health_Check::render()` kept for the legacy route |
| 3 | Reindex action result | `$SITE_URL/wp-admin/admin.php?page=listora-settings&action=reindex&_wpnonce=...` then `&listora_reindexed=1` | {admin}; bogus nonce gets 403 | `includes/admin/class-admin.php` (`handle_search_reindex()`), `includes/search/class-search-indexer.php` |
| 4 | WordPress Site Health > Status | `$SITE_URL/wp-admin/site-health.php` | {admin} (`view_site_health_checks`); {editor} blocked by core | `includes/core/class-site-health.php` |
| 5 | Plugins screen (activate, deactivate, delete) | `$SITE_URL/wp-admin/plugins.php` | {admin}; {editor} has no Delete link | WordPress core; `uninstall.php` |
| 6 | Setup wizard redirect target | first `wp-admin/index.php` load after activation lands on `$SITE_URL/wp-admin/admin.php?page=listora-setup` once | {admin}; {editor} refused | `includes/admin/class-activation-redirect.php`, `includes/admin/class-setup-wizard.php` |
| 7 | Settings > General > Pages (status badges) | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=general` | {admin} | `includes/admin/class-pages-settings.php` |
| 8 | Public Listora page whose feature is off (404 or owner content) | `$SITE_URL{directory}` and the Pro pages (Browse Needs, Post a Need, Compare, Buy Credits) | {guest} sees 404; {editor}, {admin} see the page | `includes/core/class-page-availability.php` |
| 9 | Translated surfaces | Directory `$SITE_URL{directory}`, Add Listing `$SITE_URL{add_listing}`, `{dashboard}` success card, one wp-admin settings screen, with site language set to the reference locale | {guest}, {owner}, {admin} | block `render.php` files, templates, `window.listoraI18n` from `includes/class-assets.php` |
| 10 | Cron and uninstall state | CLI and DB only (`wp action-scheduler action list`, `wp listora cleanup`, snapshot script); no browser surface | system | `includes/workflow/class-cron-scheduler.php`, `uninstall.php` |

## Components on each surface
Surface 1 (page order, `.wb-listora-admin` shell): settings tab bar with Advanced current; sections as `section.listora-settings-block` each with `h3.listora-settings-block__title` and `p.listora-settings-block__desc`: Setup wizard, App sign-in (checkbox `wb_listora_app_password_login`), Mobile app legal links (read-only privacy URL, terms, community guidelines, abuse email), Cache (`search_cache_ttl` number), Maintenance (table rows "Search index" with `a.button` "Rebuild Search Index", "Setup wizard" with `a.button` "Run Setup Wizard", then demo-import rows), Debug (`debug_logging` checkbox), Data Management (row "Uninstall": `delete_on_uninstall` checkbox plus `p.listora-description--danger`; row "Privacy": `a.button` Export Personal Data and Erase Personal Data), then the Health Check block `section.listora-health-block` with `div.listora-health-grid` of eight `div.listora-health-card` (icon `.listora-health-card__icon--pass|warn|fail`, `p.listora-health-card__title`, `p.listora-health-card__desc`, optional `a.listora-health-card__fix`) in this order: Database tables, Listing expiration cron, Unverified listings cron, Search index, Essential pages, Permalinks, PHP version, Memory limit; save button for the tab.

Surface 2: a 302, no markup. Surface 3: `.notice.listora-notice.notice-success` "Search index rebuild scheduled..." on the settings page, or the "link expired" 403 screen.

Surface 4: three Listora tests in the Status list, each with label, badge (Good / Recommended / Critical), description and optional action link: "Claim proof documents are private", "Maps have a tile source", "One currency across the site".

Surface 5: plugin rows for WB Listora and WB Listora Pro with Activate, Deactivate, Delete links; the activation notice area.

Surface 6: `div.wrap.listora-wizard` with `.listora-wizard__progress` step pills, `.listora-wizard__card`, `.listora-wizard__nav` (Back link, primary Continue button), `.listora-wizard__skip`; complete screen with `.listora-wizard__actions` (primary "Go to" link and a re-run link).

Surface 7: `section.listora-settings-block` "Pages" with one table row per registered page: label, status badge `span.listora-page-status--linked|missing|trashed|orphan|inactive` (text Linked, Missing, Trashed, Orphan detected, Feature off), `select#page-{key}`, description, and a "Create page" `a.button-secondary` when missing; `.notice.listora-notice` success or error after create.

Surface 8: 404 template, or the page content with the owner's own paragraph plus the blocks; admin-bar Edit link for editors.

Surface 9: Directory search bar (`.listora-search__toggle-btn span`), Add Listing success actions (`.listora-submission__success-actions .listora-btn--primary`), dashboard strings, the "Buy credits" link on a 402, `window.listoraI18n` keys, and a wp-admin settings screen in the admin user's language.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | Health card pass / warn / fail | healthy site; drop FULLTEXT (warn); delete index rows (fail); unschedule `wb_listora_check_expirations` (fail); rename a table (fail); set Plain permalinks (warn or fail) - scratch site only |
| 1 | Index below 90% | delete part of `${PREFIX}listora_search_index` (scratch) |
| 1 | Zero published listings | new install or all listings trashed: index card passes |
| 1 | Long permalink or page names | rename a page to 120 characters, reload the Essential pages card |
| 1 | No permission | {editor}, {other}, {guest} request the URL |
| 3 | Bogus nonce, valid nonce, no nonce | edit `_wpnonce` |
| 3 | Large directory | 2000+ listings: rebuild is chunked and the notice still shows at once |
| 4 | Currency mismatch / maps without tile source | WooCommerce in a different currency; OpenStreetMap with empty Tile URL and located listings |
| 6 | Wizard once only | delete the transient `wb_listora_show_wizard_redirect`, set it again, load admin twice; also `plugins.php?activate-multi=true` must keep the flag |
| 7 | Feature off / missing / trashed / orphan | switch a Pro feature off; delete or trash a mapped page; map a page that carries another page's meta |
| 8 | Feature off, bare page / page with own paragraph / editor viewing | switch Reverse Listings, Comparison, Monetization off; add a paragraph; request as {editor} |
| 9 | Non-English locale, RTL locale | `wp option update WPLANG de_DE`, then `ar`; restore afterwards |
| all | 390 viewport, dark mode | resize; admin dark scheme or `prefers-color-scheme: dark` |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_settings[delete_on_uninstall]` (Settings > Advanced > Data Management) | 0 / 1 | Off: uninstall leaves every row; on: nothing of Listora remains in either deletion order (journey uninstall) |
| `wb_listora_pro_features` keys `reverse_listings`, `comparison`, `monetization` (Settings > Features) | on / off | Off: bare Pro pages 404 for {guest}; Pages table badge reads "Feature off"; Buy Credits stays 200 |
| `wb_listora_hide_unavailable_pages` filter | true / false | false: pages stay 200 even when their feature is off |
| `WPLANG` (Settings > General > Site Language) | locale | Surfaces in group 9 render translated strings; layout flips for RTL |
| `unverified_listings_max_days`, `unverified_listings_action` | days, trash | stale `pending_verification` listings cleaned by the daily job |
| `wb_listora_draft_reminder_max_per_run` filter | integer | bounds draft reminder emails per run |
| Permalink structure (Settings > Permalinks) | Plain / post name | Permalinks health card state |
| Site timezone | UTC+14, UTC-12 | job schedules unchanged |

## Fixtures and setup
- Resolve placeholders from `docs/qa/sites/<site>.json`. Hosts and paths: `$SITE_URL`, `$WP_PATH`, table prefix `${PREFIX}`.
- Fixtures: `docs/qa/fixtures/2.0-fixtures.php` for listings in every status.
- Before anything mutating: `wp db export $SCRATCH/pre-lifecycle.sql`; note `wc -l` of `wp-content/debug.log` and compare by offset.
- Destructive journeys (uninstall, health-check break steps, draft reminder) run on a scratch site only; say so in the report if skipped.
- Probe mu-plugin `wp-content/mu-plugins/qa-as-probe.php` (cron journeys): remove afterwards.
- Restore afterwards: `WPLANG` to empty, feature toggles to recorded values, renamed tables, unscheduled jobs (load wp-admin once to re-arm), index (`wp listora reindex`), injected duplicate Action Scheduler rows (`dedupe_pending_batch`).

## Run order
1. `installation-and-activation.md` - mixed, critical: schema, caps, options, wizard redirect fires once.
2. `health-check-and-reindex.md` - mixed, normal: Health Check cards, Site Health, reindex button.
3. `health-check-search-index-usable.md` - mixed, high: the Search index card contract.
4. `pro-upgrade-and-downgrade.md` - mixed, critical: Pro activation creates nothing, off features return 404.
5. `translations-render-not-just-compile.md` - mixed, high: rendered strings in the reference locale.
6. `trigger-labels-translate.md` - mixed, high: no early textdomain load, trigger labels translate.
7. `cron-schedule-and-maintenance.md` - cli, high: each recurring job once, no Action Scheduler notices.
8. `cron-recurring-dedupe.md` - cli, high: duplicate sweep on scheduling requests only.
9. `term-helper-consolidation.md` - cli, high: shared term and email formatter, Pro uses functions.
10. `uninstall-deletes-everything-both-orders.md` - cli, high, scratch only, last.
Then run: `wb-listora-pro/docs/qa/journeys/platform-lifecycle/_group.md`

## Group-specific micro checks
- G-01 The Health Check summary text counts equal the cards: "N passing" plus warnings plus failing equals 8, and `.is-warn` and `.is-fail` spans are absent when their count is 0.
- G-02 Each failing or warning card carries a `.listora-health-card__fix` link whose target resolves (no 404, no `#`); each passing card has none.
- G-03 The Database tables card says "All 11 Listora tables are present." but activation creates 14 core tables; `email_log`, `leads`, `space_listings` are missing from `REQUIRED_TABLES` (expected-fail in `health-check-and-reindex.md` step 3). Compare the count in the card text with `SHOW TABLES LIKE '${PREFIX}listora\_%'`.
- G-04 Card icons match state: `--pass` shows a check, `--warn` and `--fail` differ in glyph and in colour, and colour is never the only cue (P-08, F-12).
- G-05 "Rebuild Search Index" exists twice (Maintenance button and Search index card link); both carry a `_wpnonce` for `listora_reindex` and schedule the same single event; clicking either twice quickly leaves one scheduled `Search_Indexer::REINDEX_CRON_HOOK`.
- G-06 After the reindex click the browser URL ends `listora_reindexed=1` and reload does not repeat the action or the notice (F-06).
- G-07 The Search index card count equals `SELECT COUNT(*) FROM ${PREFIX}listora_search_index` and uses a thousands separator at 4 digits (`number_format_i18n`).
- G-08 `?page=listora-health` returns 302 to the Advanced tab for {admin} and a permission message (not a blank page) for {editor}.
- G-09 The Data Management checkbox label reads "Permanently delete all WB Listora data on plugin uninstall"; under white label it uses the brand name (P-18); the danger description keeps the `listora-description--danger` colour in dark mode.
- G-10 Pages table: every row's badge text matches the `select#page-{key}` value (Linked only when a page is selected and published; Missing shows a Create button; Feature off rows still show their description).
- G-11 The Pages "Feature off" badge for Buy Credits appears although the page still returns 200 (Monetization off), and the description text says the page returns 404 only for the others; confirm wording matches the real HTTP status per page.
- G-12 Site Health: the three Listora tests have unique labels, a non-empty description, and the badge colour matches the status (Good green, Recommended orange, Critical red); a passing currency test names a single currency code.
- G-13 Wizard redirect: first admin load after activation lands on `page=listora-setup`, the second load does not; `plugins.php?activate-multi=true` does not redirect and the next normal load does.
- G-14 Translated Directory search toggle `.listora-search__toggle-btn span` differs from its English source in the reference locale, and the Add Listing success primary button reads "Zum Dashboard"; `window.listoraI18n.fileTooLarge` is non-empty in that locale.
- G-15 RTL: in an RTL locale the Health Check grid, status badges and the card icon mirror (icon on the right), and no card text is clipped at 390.
- G-16 A bare Pro page with its feature off returns 404 with the theme 404 template, not a blank 200 with an empty `main`; with the owner's paragraph it returns 200 and that paragraph is visible.
- G-17 After `wp listora cleanup` the output lines are exactly the documented six and the exit code is 0; running it twice prunes 0 rows the second time.
- G-18 `wp action-scheduler action list --status=pending --group=wb-listora` shows 7 distinct hooks, each count 1, and `wb_listora_search_reindex` is absent unless a rebuild is in progress.

## Known defects (expected-fail)
- `health-check-and-reindex.md` step 3: `REQUIRED_TABLES` omits `email_log`, `leads` and `space_listings`, so a missing table is not reported.
- `translations-render-not-just-compile.md` step 5: only "This field is required." has a `msgstr` in `wb-listora-de_DE.po`; "Selected only", "Services enabled" and "Show all features (%d)" render English in de_DE.

## Not covered yet
- Upgrade path: `Migrator::maybe_migrate()` with an older `wb_listora_db_version` (installation step 10 is unrun, and no journey checks that a migration keeps data).
- WP-CLI commands with no journey: `wp listora stats`, `repair`, `repair-locations`, `repair-credit-ledger`, `audit-prices`, `test-email`, `listing-types`.
- Deactivation behaviour: what `wp plugin deactivate` clears (scheduled jobs, rewrite rules, transients) and that reactivation restores them.
- Minimum requirements: PHP and WordPress version gate and the admin message when requirements are not met.
- Health Check cards Essential pages, Permalinks, PHP version and Memory limit have no step that forces their warn or fail states and checks their fix link.
- Privacy exporter and eraser (Export Personal Data / Erase Personal Data buttons) are linked from Data Management but no journey runs them.
- Site Health tests "Claim proof documents are private" has no step; only currency and maps are described.
