# Data-Flow Verification Plan — Pre-Smoke Checkpoint

**Date:** 2026-05-09
**Scope:** WB Listora Free + Pro (combo)
**Trigger:** ~80 commits across both repos in the past 2 weeks (INV-12 fixes + INV-3 closure + 100k-readiness sprint + same-family migration + 14 customer-visible bug bundles + cron transport flip + Pro option rename migration). Smoke test alone proves flows work end-to-end; this plan proves the **wires** between layers haven't been silently disconnected by the refactors.

**Why before smoke:** smoke walks customer behavior. Data-flow verification walks the seams between layers — the places refactors are most likely to have severed without surfacing in UI. A green data-flow pass is the gate for trusting the smoke run.

**Output:** structured pass/fail report at `plan/data-flow-verification-results.md` with per-flow status, waypoint evidence, and any breakage found. Anything broken becomes a Basecamp card before smoke runs.

---

## Approach

For every chain on the inventory below, verify the **waypoints** — every layer the data passes through. A chain passes only when every waypoint reports the expected value. A chain fails fast at the first missing waypoint, and the report names which.

**Verification toolkit:**
- WP-CLI `wp eval` + `wp option get/list` + `wp post meta get` + `wp action-scheduler list`
- Direct DB queries via `mysql_query` MCP
- `curl` against REST endpoints (with cookie + nonce)
- Browser DevTools `browser_evaluate` for IAPI store + computed CSS
- `wp-content/debug.log` diff for fatals/warnings

**No Edits, no commits during verification.** This phase is read-only inspection. Any fix lands in a follow-up commit after the report is reviewed.

---

## Inventory — risk-ranked

### Tier A — Cross-plugin coupling (HIGHEST RISK)

These are the chains where Pro listens on Free's hook fires. The 107 documented pairs in `audit/derived/cross-plugin-coupling.json` are the surface; the 10 below are the ones with material code change in the past 2 weeks.

| # | Chain | Free fire site | Pro listener | Recent change | What to verify at each waypoint |
|---|---|---|---|---|---|
| A1 | listing-claimed → search-index update | `class-claims-controller.php:512` fires `wb_listora_listing_claimed` | `class-verification.php::on_listing_claimed` | INV-12.1 (commits f0ca6d9 + bcd8157) — Pro no longer writes `_listora_is_claimed` | (1) Free fire-site code references hook (2) Pro listener registered at boot (3) action fires when claim approved (4) `_listora_is_claimed=1` on listing (5) `wp_listora_search_index.is_claimed=1` for that listing |
| A2 | listing-expiration filter chain | `class-status-manager.php:99,118` + `class-listings-controller.php:1654` fire `wb_listora_listing_expiration_date` filter | `class-pricing-plans.php::filter_listing_expiration_date` | INV-12.2 (commits 8b8bf8a + 6a39d2b) — Pro filters expiry, never writes meta | (1) Free fires filter at all 3 sites (2) Pro listener returns plan-derived expiry (3) `_listora_expiration_date` post-write matches plan (4) Free is sole writer of postmeta |
| A3 | Migrated_From writer | `\WBListora\Migration\Migrated_From_Tracker::set()` (Free) | Pro adapters call the static method | INV-12.3 (commits c127b51 + ea9644d) | (1) Class exists + method signature stable (2) Pro `class-base-migrator.php:297` calls `Tracker::set()` not direct `update_post_meta` (3) `_listora_migrated_from` on imported listings (4) zero direct meta writes from Pro |
| A4 | webhook secret filter | `class-settings-page.php:987` fires `wb_listora_webhook_secret` filter | `class-webhook-receiver.php::get_secret` answers | INV-12.4 (commits 9ab22ee + 1d2bf61) — Pro answers via filter, Free no longer reads `wb_listora_pro_webhook_secret` directly | (1) Free fires filter (2) Pro listener returns shared secret (3) admin-displayed value matches Pro option |
| A5 | Reset Settings → Pro purge | `class-settings-controller.php:371` fires `wb_listora_after_reset_settings` + filter `wb_listora_reset_option_keys` | Pro `class-pro-plugin.php:46-47` listens | 2026-04-30 PM (kept stable; verify symmetric reset) | (1) Reset action fires the action (2) Pro purges its options (3) Pro options absent post-reset OR back to defaults |
| A6 | listing-status-changed → email + webhook | `Status_Manager` fires `wb_listora_listing_status_changed` | Free `Notifications::on_listing_status_changed` (0aa62ca fix) + Pro `Outgoing_Webhooks::on_listing_status_changed` (97810e8 fix) | both rewrites in same window | (1) Hook fires once per actual transition (not on no-op saves) (2) Free Notifications dispatches per template (3) Pro Outgoing_Webhooks queues delivery via Action Scheduler (4) email log row created (5) webhook delivery log row created |
| A7 | services-detail → booking CTA | `templates/blocks/listing-detail/tabs.php` fires `wb_listora_after_service_detail` | Pro `Services_Pro::fire_booking_hook` | 2026-05-08 (commit d0432a4 + 7f336c7) — listener was orphan before | (1) Hook fired inside services-grid foreach (2) Pro listener registered (3) booking CTA renders on service cards |
| A8 | review-author → BP profile URL | `tabs.php:344` + `reviews.php:105` + `class-reviews-controller.php:331` fire `wb_listora_member_profile_url` filter | Pro `class-buddy-press-integration.php::filter_member_profile_url` | 2026-05-08 alignment | (1) Filter fired at all 3 sites (2) Pro listener returns BP profile URL when BP active (3) listing detail review-author renders as link (4) REST review response carries `user_profile_url` field |
| A9 | Page_Registry helper chain | Free `wb_listora_register_pages` action + `wb_listora_register_page()` helper | Pro `class-pro-plugin.php` listener registers `compare` page | INV-3 closure (commit 15621de + dc382be) | (1) Action fires at init priority 5 after Free's pages (2) Pro listener registers compare page via the helper, NOT direct `\WBListora\Core\Page_Registry::register()` (3) Compare page resolves via `wb_listora_get_page('compare')` (4) `bin/architecture-checks.sh` INV-3 passes |
| A10 | Pro option rename migration | `Pro_Migrator::migrate_legacy_pro_options()` runs on upgrade | Old `listora_*` options copied to `wb_listora_pro_*`, then deleted | INV-12.5 (commit 0be6bf1) | (1) Free options not affected (2) `wb_listora_pro_max_badges_per_card` populated, `listora_max_badges_per_card` absent post-migration (3) Pro DB_VERSION ≥ 1.4.0 |

### Tier B — REST/JS contract chains (HIGH RISK)

| # | Chain | What to verify |
|---|---|---|
| B1 | listings list prefetch (P1-2) | `Listings_Controller::get_items` calls `update_meta_cache('post', $ids)` + `update_object_term_cache($ids, $taxonomies)` BEFORE the prepare loop. Verify: enable Query Monitor, hit `/wp-json/listora/v1/listings?per_page=20`, confirm meta query count is constant (not 20+ N) |
| B2 | apiFetch AbortController (P1-3) | 43 Free + 19 Pro sites wrapped. Verify: pick 5 of each, force a network throttle to 100ms, confirm 10s timeout fires AbortError + UI shows "Network is slow" toast (NOT permanent loading) |
| B3 | search list envelope contract | `GET /listora/v1/search` returns `{ items, total, pages, has_more }` with `has_more = (offset+count) < total`. Verify: hit page 1 of 50 listings (per_page=20) — `has_more=true`. Hit page 3 (the empty page) — `has_more=false`. |
| B4 | review status enum | `PUT /reviews/{id}` accepts `{status: pending|approved|rejected}` (commit 36033b0). Verify: subscriber → 403 `listora_forbidden_status`, moderator → 200, invalid enum → 400 `rest_invalid_param` |
| B5 | submission/resend-verification cooldown | `POST /submission/resend-verification` enforces 5-min per-listing cooldown (F-02). Verify: spam 6 requests, 6th returns 429 |
| B6 | claim REST flow | `POST /claims` writes row, fires no premature `wb_listora_listing_claimed` (only on approve via `Claims_Controller::approve_claim`) |
| B7 | favorites toggle round-trip | `POST /favorites` (200) → DB row → dashboard tab refresh. `DELETE /favorites/{id}` removes |
| B8 | lead-form per-listing nonce (P-01 sentinel) | `POST /listora/v1/lead-form` requires per-listing nonce. No nonce → 403. Cross-listing nonce → 403. Per-listing daily cap of 20 enforced at 21st |
| B9 | analytics track per-listing nonce (P-02 sentinel) | `POST /analytics/track` requires per-listing nonce |
| B10 | webhook strict HMAC (P-03 sentinel) | `POST /webhooks/payment` enforces signature + timestamp + replay defence |

### Tier C — Settings + option write chains

| # | Chain | What to verify |
|---|---|---|
| C1 | Settings tab merge | Save tab A → switch to tab B → tab B values intact. (D.metabox-fields-merged sentinel — `class-settings-page.php::save` must `array_merge`, not replace.) |
| C2 | get_setting() helper | `wb_listora_get_setting('expiration_days')` returns last saved value. 5 read sites routed through helper (P2-8 commit 42511ef) |
| C3 | map_provider filter | `wb_listora_get_setting('map_provider')` fires `wb_listora_map_provider` filter (commit 847dcc8). Pro Google_Maps listener returns 'google' when API key set |
| C4 | feature_toggles option | `wb_listora_pro_features_enabled` array — 29 keys. Toggling one key doesn't drop others |
| C5 | Pro->Free Settings_Helper (INV-4) | `WBListoraPro\Settings_Helper::get_free()` is the only Pro path to Free settings. Direct `get_option('wb_listora_settings')` calls in Pro = 0 (commit 4f1f5f9) |
| C6 | Reset → settings + Pro symmetric purge | covered as A5 above (cross-plugin); add per-key verification here |

### Tier D — Cron + Action Scheduler chains

| # | Chain | What to verify |
|---|---|---|
| D1 | Free cron migration to AS (P1-1.B) | All 6 Free crons under AS group `wb_listora`. Zero `wp_cron event list | grep wb_listora_` entries. Hooks: expire, cleanup-drafts, send-expiry-reminders, rotate-featured, cleanup-email-verification, cleanup-notification-log |
| D2 | Pro cron migration | All 7 Pro jobs under AS group `wb_listora_pro`. Hooks: audit_cleanup, saved_search_alerts, analytics_cleanup, webhook_log_cleanup, deliver_webhook (retry), expire_needs, license_check (P1-1.C commit c977ab7) |
| D3 | Cron-fired hooks | Each cron's handler executes without fatal. Run each manually: `wp action-scheduler run --hooks=<hook>` |
| D4 | Webhook retry backoff | Failed delivery in `wb_listora_pro_deliver_webhook` reschedules with backoff. Verify: configure invalid URL, trigger event, observe rescheduled rows |
| D5 | License check cron | Weekly cron updates `wb_listora_pro_license` option's `last_checked` timestamp. Manually fire → option's `last_checked` advances |

### Tier E — IAPI + frontend hydration chains

| # | Chain | What to verify |
|---|---|---|
| E1 | Single-namespace IAPI | All blocks use `listora/directory` namespace. No per-block stores. Verify: `Object.keys(window.wp.interactivity.state)` returns exactly 1 namespace per page (Pro extends, doesn't replace) |
| E2 | Modal getter pattern (#63411c8) | `data-wp-class--is-open` binds to derived getter (e.g. `state.isClaimModalOpen`), NOT inline `===`. Verify: open claim modal, check `state.activeModal === 'claim'` AND modal has `[data-state="open"]` |
| E3 | Empty state hydration (today's fix) | Server-rendered `.listora-card--empty` stays visible after IAPI hydration on 0-result pages. `state.showEmptyState` returns true on `state.totalResults === 0` |
| E4 | Search filter count getter | Active-filter-count badge sums keyword + location + type + checkbox + dropdown + date (D.filter-count-dropdowns sentinel) |
| E5 | Service description toggle | `toggleServiceDesc` action targets the clicked card's desc only (D.service-details-toggle sentinel) |
| E6 | Server state init | Every layout-owning block calls `wp_interactivity_state()` with defaults. Verify: SSR HTML includes `data-wp-context` JSON; JS hydrates without "missing key" warnings |
| E7 | Search abortable fetch | Search action wraps fetch with AbortController + 20s timeout (commit 50dc326) |
| E8 | view.js dependency chain | All view.js imports the shared store (per-block dependency). Verify: load order — store loads before any block view.js |

### Tier F — Page Registry + admin redirect chains

| # | Chain | What to verify |
|---|---|---|
| F1 | Free Page_Registry resolves canonical pages | `wb_listora_get_page('listings')` returns existing page ID. Settings Pages tab admin UI shows the 3 canonical pages |
| F2 | Pro adds compare via helper | combo mode: `wb_listora_get_page('compare')` returns existing page ID. Comparison block renders on it |
| F3 | Activator idempotency | Re-running `wb_listora_activator()` doesn't duplicate canonical pages. Verify: count pages with each canonical slug = 1 |
| F4 | Setup wizard headers fix (#9867159785) | wizard POST handler runs at `admin_init` priority 1. Verify: walk wizard, `Cannot modify header information` count in debug.log = 0 |

### Tier G — Asset + namespace chains

| # | Chain | What to verify |
|---|---|---|
| G1 | flatpickr round-2 (#9856828615) | `flatpickr` 4.6.13 enqueued on submission block. `data-listora-flatpickr-attached` flag prevents double-attach. Firefox shows picker, not native spinner |
| G2 | Services photo metabox (#9872014083) | `services-metabox.js` enqueued on listing edit screen. WP media frame opens on Choose. `image_id` persists |
| G3 | Pro inline cap pairs | `class-pro-plugin.php:1700,1742` show `current_user_can(...)` adjacent to `wp_verify_nonce(...)` (PR-review wppqa Rule S1 sentinel) |
| G4 | wbcom-credits-sdk consumer | Pro reads from Free's `vendor/wbcom-credits-sdk` (Pro doesn't bundle its own copy). Verify: `wp eval 'echo realpath(WB_LISTORA_CREDITS_SDK_PATH);'` resolves into Free's path |
| G5 | Lucide icons inline rendering | `Lucide_Icons::render('star')` outputs proper SVG (21 icons available). No broken dashicons |

---

## Per-flow waypoint template

Each flow gets reported in this shape:

```
## A1 — listing-claimed → search-index update
- Waypoint 1 (Free fire site present): PASS — `class-claims-controller.php:512` matches grep
- Waypoint 2 (Pro listener registered at boot): PASS — `class-verification.php` `add_action('wb_listora_listing_claimed', ...)`
- Waypoint 3 (Action fires on approve): PASS — temp listener captured the fire
- Waypoint 4 (`_listora_is_claimed=1` postmeta): PASS — postmeta query returns 1
- Waypoint 5 (search-index `is_claimed=1`): PASS — DB query confirms

OUTCOME: PASS
```

Failures spell out the failed waypoint + likely cause + suggested fix file:line. The report is then reviewed before smoke runs.

---

## Recommended execution sequence

1. **Phase 1: Static checks** (~10 min). Grep for the canonical hook names, helper function calls, and required class methods. Catches the easy regressions where a refactor renamed a callsite. Tools: `grep -rn`, `wp eval` for class_exists / function_exists.
2. **Phase 2: Live waypoint walks** (~25 min). For each chain, set up the fixture, fire the trigger, observe waypoints in order. Tools: WP-CLI eval + DB query + REST curl + browser_evaluate.
3. **Phase 3: Static analysis re-baseline** (~5 min). Re-run wppqa baseline. Compare against `audit/wppqa-baseline-2026-05-08/SUMMARY.md`. Any new findings beyond the baseline are regressions.
4. **Phase 4: Architecture invariants** (~2 min). `bash bin/architecture-checks.sh` — all 12 must pass.
5. **Phase 5: Report** (~5 min). Write `plan/data-flow-verification-results.md` with per-flow status. If any chain fails: file Basecamp card + halt before smoke. If all green: green-light the smoke run.

Total: ~45-50 min. Significantly faster than smoke (~25 min on Sonnet plus Opus re-verification time) and more diagnostic — failures point at the exact waypoint and file.

---

## Pre-flight (run once before Phase 1)

```bash
# Working directory
cd /Users/varundubey/Local\ Sites/directory/app/public

# Confirm both plugins active
wp plugin list --status=active | grep -E "wb-listora$|wb-listora-pro$"
# Expect: 2 lines

# Snapshot debug.log baseline
wc -c wp-content/debug.log > /tmp/data-flow-debug-baseline.txt

# Confirm pre-push gate is currently green (no SKIP_LOCAL_CI=1 needed in last push)
# This is informational — verifies repos are in clean releaseable shape
cd wp-content/plugins/wb-listora && bash bin/architecture-checks.sh 2>&1 | tail -3
cd ../wb-listora-pro && bash bin/architecture-checks.sh 2>&1 | tail -3
```

---

## What this plan does NOT cover (smoke handles these)

- End-to-end UX (smoke runbook + journeys cover this)
- Frontend visual correctness (smoke + UX walk)
- Cross-browser (Firefox-specific items in smoke F.firefox)
- Accessibility (smoke F.a11y)
- Performance budgets (separate `plan/PERF-BUDGETS.md`)

This plan covers ONLY the data-flow seams between layers.

---

## Decision needed

User reviewing this plan should confirm:

1. **Scope** — is the inventory above complete? Anything I missed?
2. **Priority** — should we run all 5 tiers, or prioritize Tier A + B + F as the highest-risk and defer C/D/E/G to smoke?
3. **Execution path** — direct execution by Opus (read-only, ~45 min) or dispatch Sonnet sub-agent for the live waypoints (cheaper but lower diagnostic depth)?

Once approved, execution writes the report and either green-lights smoke or halts with a Basecamp card per failure.
