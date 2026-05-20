# Agent Smoke Runbook — WB Listora

**Audience:** a browser-capable agent (Claude Sonnet or equivalent) with Playwright MCP + WP-CLI Bash access, OR a human QA person with the same access. Both should be able to execute every step.

## How to read this runbook

Each C and E step describes a **customer contract**: what the feature promises, why it matters, the surfaces it touches, and what "working" looks like. It does NOT prescribe the exact Playwright calls, selectors, REST paths, or DB queries. Read the relevant plugin code, pick the right mechanism, and verify the contract.

D rows stay specific — those are repros of past incidents; the exact fixture IS the contract.

## Global preconditions

- Working directory: `/Users/varundubey/Local Sites/directory/app/public/wp-content/plugins/wb-listora`
- Site URL: `http://directory.local`
- WP path: `/Users/varundubey/Local Sites/directory/app/public`
- WP-CLI: `wp --path="/Users/varundubey/Local Sites/directory/app/public" <cmd>`
- Admin auto-login: append `?autologin=1` to any front-end URL (mu-plugin: `wp-content/mu-plugins/dev-auto-login.php`)
- Per-user auto-login: `?autologin=<user_login>`
- Playwright: one Chromium session throughout; restart with `browser_close` + `browser_navigate` if it dies
- Plugin version constant: `WB_LISTORA_VERSION` (`wb-listora.php`)
- Pair plugin: `wb-listora-pro` (combo mode = both active)
- Front-end base slugs: `/listings/`, `/add-listing/`, `/dashboard/`, `/compare-listings/` (Pro)
- Basecamp project: `47045113` (Bugs column id `9827892296`, Ready-for-Testing `9827892302`)

## Output contract

At end of walk, write exactly one JSON file to `docs/qa/.last-smoke-pass.json`:

```json
{
  "mode": "free|combo",
  "release_version": "<from WB_LISTORA_VERSION>",
  "pro_version": "<from WB_LISTORA_PRO_VERSION when combo>",
  "ran_at": "<ISO 8601 UTC>",
  "sections": {
    "A_fresh_install":     { "pass": N, "fail": N, "skipped": N },
    "B_upgrade":           { "pass": N, "fail": N, "skipped": N },
    "C_core_flows":        { "pass": N, "fail": N, "skipped": N },
    "D_regression_guards": { "pass": N, "fail": N, "skipped": N },
    "E_pro_smoke":         { "pass": N, "fail": N, "skipped": N },
    "F_cross_browser":     { "pass": N, "fail": N, "skipped": N }
  },
  "failures": [
    { "id": "...", "origin": "from|for", "triage_note": "...", "expected": "...", "actual": "...", "url": "...", "screenshot": "..." }
  ],
  "debug_log_issues": [
    { "section": "...", "level": "fatal|warning|notice|deprecated", "line": "...", "file": "..." }
  ],
  "manual_required": []
}
```

Emit a Basecamp draft per failure — project `47045113`, Bugs column.

## Fixture cleanup (before every walk)

```bash
wp --path="/Users/varundubey/Local Sites/directory/app/public" eval '
global $wpdb;
$prefix = $wpdb->prefix . "listora_";
$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = \"listora_listing\" AND post_title LIKE \"Smoke %\"" );
$wpdb->query( "DELETE FROM {$prefix}reviews WHERE comment_content LIKE \"Smoke %\"" );
$wpdb->query( "DELETE FROM {$prefix}claims WHERE message LIKE \"Smoke %\"" );
$wpdb->query( "DELETE FROM {$prefix}services WHERE title LIKE \"Smoke %\"" );
echo "fixtures cleaned\n";
'
```

## Debug log protocol

Enable `WP_DEBUG` + `WP_DEBUG_LOG` + `WP_DEBUG_DISPLAY=false` before Section A. Baseline `wp-content/debug.log` byte count. After every section, diff new lines into `debug_log_issues[]` classified by level. Any new fatal or warning is a failure unless explicitly whitelisted.

## Cross-cutting checks (apply to EVERY page visited)

These run silently alongside every C/D/E step — log to `failures[]` if any tripped:

1. **DevTools console errors.** Zero red errors on every page (warnings allowed but flagged in `manual_required[]` if numerous). Check via `browser_console_messages`.
2. **No `admin-ajax.php`.** WB Listora is 100% REST as of 1.0.0 (max 2 documented AJAX exceptions in code). DevTools Network must show ZERO `admin-ajax.php` requests during front-end flows. Admin form submissions allowed via `admin-post.php` for the legacy 2 cases only — record any unexpected hit.
3. **No native dialogs.** `window.alert` / `window.confirm` / `window.prompt` must NOT fire anywhere. All confirmations route through `window.listoraConfirm()` modal helper.
4. **REST 4xx/5xx.** `/wp-json/listora/v1/*` calls return 2xx for happy paths; 401/403 only on permission-blocked actions; 404 only on missing IDs. Anything else is a failure.
5. **No raw IDs leaking to UI.** Field renderers must show resolved values — never `Company Logo: 818`, never `Category: 42`.
6. **CSS tokens.** Rendered stylesheet should resolve through `--listora-*` custom properties. Hex literals appearing in computed styles indicate token gaps.

---

## A — Fresh install

### A1 — Activation and first-request routing
**What to verify:** after `wp plugin deactivate wb-listora && wp plugin activate wb-listora`, the primary front-end routes respond on the FIRST request without re-saving Permalinks. Activator's `flush_rewrite_rules()` must defer to `init` priority 99 (per the 2026-05-07 fix that resolved the textdomain cascade).
**Why it matters:** rewrite-flush-on-activation regressions break first impressions. We've shipped this fix once already (commit `5b4840f`); regressions here are real.
**Acceptance:** `/listings/`, `/add-listing/`, `/dashboard/` all return HTTP 200; `wp rewrite list | grep listora` shows the listing CPT permalink rules.

### A2 — Database schema is in place
**What to verify:** all 11 listora_-prefixed tables exist (`listora_geo`, `listora_search_index`, `listora_field_index`, `listora_reviews`, `listora_review_votes`, `listora_favorites`, `listora_claims`, `listora_hours`, `listora_analytics`, `listora_payments`, `listora_services`). The `wb_listora_db_version` option matches `WB_LISTORA_VERSION`. Engine on every table is `InnoDB`.

### A3 — Pro pairs cleanly (combo mode only)
**What to verify:** activating `wb-listora-pro` on top of `wb-listora` does not fatal; Pro-only tables (`listora_credit_log`, `listora_audit_log`, `listora_saved_searches`) are created; both version constants agree (lockstep). All 12 architecture invariants pass via `bin/architecture-checks.sh`.

### A4 — Setup wizard auto-redirects on first activation
**What to verify:** the `wb_listora_show_wizard_redirect` transient sets at activation; first admin pageload as a `manage_options` user redirects to `admin.php?page=listora-setup` and the transient clears.

### A5 — Essential pages auto-create
**What to verify:** activator creates Directory (`/listings/`), Add Listing (`/add-listing/`), and My Dashboard (`/dashboard/`) pages — idempotent, won't duplicate if they already exist with matching block content.

### A6 — Default capabilities + roles
**What to verify:** `wp role list` shows `administrator` granted Listora caps (`manage_listora_settings`, `moderate_listora_reviews`, `manage_listora_claims`, `manage_listora_types`). `editor` granted `moderate_listora_reviews`. Subscriber unchanged. The `listora_moderator` role is registered (Pro adds the seat-grants on combo activation).

### A7 — Default listing types seeded
**What to verify:** activator registers default listing types (Business / Restaurant / Hotel / Real Estate / Job / Event / Place / Marketplace / Medical / Course). `wp listora stats` (or browser at `admin.php?page=listora-listing-types`) lists each one with its field-group count.

### A8 — Setup wizard demo seed (optional path)
**What to verify:** at the wizard's "Seed demo content" step, picking a demo pack creates ~5 listings of the chosen type with images, hours, services, and a review or two. `wp_listora_geo` rows present for each. Skip-without-seed also completes the wizard cleanly.

---

## B — Upgrade from previous version

### B1 — Migration is silent, data is intact
**What to verify:** upgrading from the last released stable to current build completes with zero new debug.log entries during the activation request. Pre-existing listings still render. Search index counters stay accurate (`SELECT COUNT(*) FROM wp_listora_search_index` matches the published-listing count from `wp_posts`).

### B2 — Settings format migration
**What to verify:** options under `wb_listora_settings` are merged not replaced when new keys are added. Editing one tab on Settings page does not drop values from a different tab.

### B3 — Capabilities re-registered
**What to verify:** an upgrade from 1.0.0-alpha (or wherever Capabilities::get_caps_map changed last) re-grants any newly-added cap to administrator without manual `wp role add-cap`.

### B4 — Cron transport flip (Action Scheduler)
**What to verify:** legacy WP-Cron entries for `wb_listora_*` hooks are unscheduled on upgrade and replaced with Action Scheduler entries. `wp action-scheduler list --status=pending --group=wb-listora` returns 6 pending recurring jobs (see C.cron for the canonical hook-name table). `wp cron event list | grep wb_listora` returns nothing.

---

## C — Core customer flows

Persona ladder: Anonymous → Subscriber/Customer → Contributor (submitter) → Editor → Admin. Test desktop 1280px and mobile 390px where the UI differs.

### C.anon.directory-home
**What to verify:** `/listings/` (and the homepage if listora-grid is the front block) renders for a logged-out visitor with real listing cards, working type tabs, and a search bar that returns results. Pagination renders when results exceed per_page.

### C.anon.listing-detail
**What to verify:** every public template (single listing detail) renders without fatal for an anonymous visitor. Auth-gated actions (Save / Claim / Compare) cleanly prompt login rather than failing 403. URLs to test: at least 5 distinct listing slugs across different listing types (Business, Restaurant, Hotel, Real Estate, Event).

### C.anon.search-facets
**What to verify:** search filters all narrow the result set:
- Keyword (FULLTEXT match in title + content + services + meta)
- Location (geocoded radius — pick a city and 5km radius)
- Type tab switching (Business → Restaurant → Hotel)
- Date filter (events only — preset "This week", "This month", custom range)
- Per-type checkboxes (e.g. "Free WiFi" for Restaurant)
- Per-type dropdowns (e.g. Cuisine for Restaurant)

The active-filter-count badge matches the user's perceived count of selected filters (post-2026-05-08 fix).

### C.anon.search-suggest
**What to verify:** typing 2+ characters into the search box surfaces a debounced (250ms+) suggestions dropdown — populated from `/wp-json/listora/v1/search/suggest`. Click a suggestion → navigates to filtered results page. Network tab shows ≤1 request per ~250ms of typing, NOT one per keystroke.

### C.anon.geo-distance
**What to verify:** results page for a geo-filtered search shows distance-from-origin per listing card (e.g. "1.2 km away"). Distance computation matches Haversine within ±50m.

### C.anon.empty-state
**What to verify:** a category page with zero listings (e.g. `/business/` if no Business listings exist) shows the canonical `.listora-card--empty` card with icon + "No listings found" + "Clear All Filters" CTA — not a blank space.

### C.anon.submission-gated
**What to verify:** `/add-listing/` for a logged-out visitor either (a) shows the registration step inline (when "Allow guest submissions" setting is on) OR (b) redirects to wp-login.php with a clear message. The choice respects the Submission settings tab. Anonymous users seeing the form must clear reCAPTCHA / Turnstile if configured.

### C.guest.submission-with-email-verify (gated by setting)
**What to verify:** with guest submissions enabled, an anonymous visitor walks the wizard, submits, receives the email-verify message, clicks the link from the inbox → verification stamp updates `_listora_email_verified=1`, listing transitions to `pending`. Expired link path: tampering with the verification token's timestamp portion produces a "request new link" UX, not a generic 404.

### C.member.submit-listing
**What to verify:** a Contributor can complete the 6-step submission wizard end-to-end (Type → Basic Info → Details → Media → Plan → Preview): listing persists with `pending_verification` or `pending` status, fires `wb_listora_listing_submitted` action, and lands in the user's dashboard "My Listings" tab. Featured image upload + gallery picker open the WP media frame for both logged-in members and (where guest-submission is enabled) anonymous visitors.

### C.member.submit-conditional-fields
**What to verify:** changing the listing type dropdown mid-submission re-renders the Details step's field set without a page reload. Restaurant shows Cuisine + Dietary; Hotel shows Star Rating + Amenities; Real Estate shows Bedrooms + Price + Sqft. Submitted listings carry only the selected-type meta keys.

### C.member.submit-map-pin
**What to verify:** in the Details step, the location field exposes a draggable map pin. Dragging updates lat/lng inputs (visible or hidden). Saving persists `wp_listora_geo` row with correct coords. Loading the listing detail page re-renders the pin at saved coords.

### C.member.business-hours-picker
**What to verify:** Business / Hotel / Restaurant types show the Business Hours field in Details step. Clicking any time input opens a flatpickr time-picker (24h, 15-min increments) — works identically on Chrome AND Firefox (the round-2 flatpickr fix from 2026-05-08 covers Firefox's native-spinner gap).

### C.member.dashboard
**What to verify:** `/dashboard/` shows a 2-column sidebar+main layout (260px sidebar at 1280px). Tabs (My Listings, Reviews, Favorites, Claims, Credits, Profile, My Needs, Analytics) navigate via URL hash. Stats cards render real counts. Edit-listing from a row deep-links into the submission wizard with prefilled data.

### C.member.dashboard-pagination
**What to verify:** with 30+ items on any tab (Listings / Reviews / Favorites / Claims), pagination renders. Cursor or page navigation reaches the last page without 500. "Load more" or page-N click does NOT duplicate items.

### C.member.review-create
**What to verify:** a logged-in member writes a review on a listing they don't own, submits, and the review appears immediately in the Reviews tab. Helpful-vote button increments. Listing owner can reply via dashboard inline-reply form (post-2026-04-30 fix `e01486b`).

### C.member.review-toggle-services
**What to verify:** services-tab description toggle (Details button) expands/collapses the description without a page reload (post-2026-05-09 fix `c382a86`). Tested on the listing detail Services tab.

### C.member.favorites
**What to verify:** save/unsave a listing from the card AND from the detail page. Favorites count on the heart button updates client-side. The favorite appears in dashboard Favorites tab. Unsave from the dashboard removes it everywhere.

### C.member.claim
**What to verify:** anonymous viewer on a listing's detail page sees "Claim this business" CTA → clicking prompts login. As a logged-in member, the claim modal accepts a message + optional proof-document upload. Submitting writes a `listora_claims` row, fires `wb_listora_claim_submitted`, queues admin email. Member dashboard Claims tab shows pending status.

### C.member.renewal
**What to verify:** an owner whose listing is approaching expiry sees a "Renew" affordance in the dashboard My Listings row. Triggering renewal extends `_listora_expiration_date` by the configured period and fires `wb_listora_listing_renewed`. With Pro active and Credits enabled, renewal deducts the configured cost.

### C.member.compare (combo)
**What to verify:** "Add to Compare" on 2-4 listings navigates to `/compare-listings/?compare=ID,ID,ID` with a populated side-by-side table. Empty state shows with 0-1 listings selected.

### C.admin.dashboard-widget
**What to verify:** `/wp-admin/` (default landing) shows the Listora dashboard widget with totals (listings / reviews / claims / pending). Widget data sourced from cached transient — no slow query on dashboard load.

### C.admin.cpt-list
**What to verify:** `/wp-admin/edit.php?post_type=listora_listing` lists listings with custom columns (Type, Category, Location, Status, Featured, Date). Bulk actions (Approve, Reject, Feature, Trash) function. Filters in the table top bar narrow correctly.

### C.admin.cpt-edit
**What to verify:** Listing CPT can be created via `/wp-admin/post-new.php?post_type=listora_listing` — even when "Days before a new listing expires" setting is non-zero (post-2026-05-08 verification: this was the cannot-reproduce repro for card #9857011539). Services meta box supports Photo upload (post-2026-05-09 fix `5eb3b33`).

### C.admin.types-crud
**What to verify:** `/wp-admin/admin.php?page=listora-listing-types` — admin can add a custom type (slug, label, icon, field groups), edit it, and delete it. New type appears as a tab on `/listings/` and as an option in the submission wizard's Type step.

### C.admin.taxonomies
**What to verify:** the three taxonomy admin pages render and accept new term creation:
- Categories (`edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing`) — hierarchical
- Locations (`edit-tags.php?taxonomy=listora_listing_location&post_type=listora_listing`) — hierarchical
- Features (`edit-tags.php?taxonomy=listora_listing_feature&post_type=listora_listing`) — flat

Adding a category → assigning to a listing → filtering `/listings/?listora_listing_cat={slug}` returns just that listing.

### C.admin.reviews-mod
**What to verify:** `admin.php?page=listora-reviews` lists reviews with status filters (All, Pending, Approved, Rejected). Approve / Reject inline actions transition status, fire `wb_listora_review_status_changed`, update the public detail page. Bulk delete removes rows from `listora_reviews`.

### C.admin.claims-approval
**What to verify:** `admin.php?page=listora-claims` lists claims with status badges. Approve sets `wp_listora_claims.status=approved`, transfers `wp_posts.post_author` to the claimant, sends approval email. Reject keeps the listing's original author and emails the claimant the rejection reason.

### C.admin.email-log
**What to verify:** `admin.php?page=listora-email-log` lists every email sent (recipient, template, status, timestamp). Resend action delivers a copy. Filter dropdowns narrow by template / recipient / date.

### C.admin.health-check
**What to verify:** `admin.php?page=listora-health` redirects to (or renders inside) Settings → Advanced. Surface lists actionable warnings — deactivate cron locally → reload → Cron Schedules warning appears with a "Run now" button.

### C.admin.setup-wizard-revisit
**What to verify:** revisiting `admin.php?page=listora-setup` after first-run completion still renders cleanly. Each step is editable; saving updates only that step's data without resetting completed steps.

### C.admin.import-export
**What to verify:** Settings → Import/Export tab — exports CSV / JSON / GeoJSON of all listings. Re-importing the JSON onto a clean install round-trips every field including geo coords, hours, services, and meta. Counts match.

### C.admin.settings-each-tab
**What to verify:** every Settings tab renders without PHP Notice/Warning/Fatal AND saves correctly:
- General (site identity, default listing type, currency, lang)
- Submission (guest submissions, captcha, expiration period, auto-publish)
- Search (default radius, distance unit, fulltext stop-words)
- Maps (provider, default center, zoom)
- Notifications (per-event toggles, sender name/email, BCC)
- Advanced (cache, health check link, debug)
- Features (per-feature toggles)
- Import/Export (covered above)

### C.admin.settings-merge
**What to verify:** editing one Settings tab and saving does NOT drop values from another tab. Specifically: change Submission tab's "Days before a new listing expires" → save → reload Search tab — Search tab values intact.

### C.admin.settings-reset
**What to verify:** Reset Settings affordance restores defaults. Pro options (Pro_Plugin's listener on `wb_listora_after_reset_settings` + `wb_listora_reset_option_keys`) ALSO purge — license, white-label, visibility values cleared.

### C.notifications
**What to verify:** `wb_listora_listing_status_changed` action fires once per actual transition (per the 2026-04-30 fix `0aa62ca`). Approve / reject / expire emails reach the listing author. Email Log admin page records each send. 15 templates exist under `templates/emails/`; each renders via `wp listora test-email <template>` without fatal.

### C.cron
**What to verify:** all 6 recurring cron jobs are scheduled after activation per Action Scheduler (NOT WP-Cron). These are the actual hook names registered in source — verified 2026-05-18:

| Hook | Schedule | Handler | Source |
|---|---|---|---|
| `wb_listora_check_expirations` | twicedaily | `Expiration_Cron::check_expirations` (marks expired + sends 7d/1d reminders) | `includes/workflow/class-expiration-cron.php:30` |
| `wb_listora_draft_reminder_cron` | twicedaily | `Expiration_Cron::send_draft_reminders` (nudges stale drafts ≥48h) | `includes/workflow/class-expiration-cron.php:31` |
| `wb_listora_daily_cleanup` | daily | `Expiration_Cron::prune_analytics` (90d analytics retention) | `includes/workflow/class-expiration-cron.php:32` |
| `wb_listora_expire_featured` | daily | `Featured::expire_featured` (expires featured upgrades) | `includes/core/class-featured.php:27` |
| `wb_listora_cleanup_unverified_listings` | daily | `Email_Verification::cleanup_unverified` (deletes unverified ≥14d) | `includes/workflow/class-email-verification.php:64` |
| `wb_listora_prune_email_log` | daily | `Notifications::prune_email_log` (notification-log retention) | `includes/workflow/class-notifications.php:84` |

`wp action-scheduler list --status=pending --group=wb-listora` shows all 6. Manually running each via `wp action-scheduler run --hooks=<hook>` completes without fatal.

Plus 1 single-event hook for chunked reindex: `wb_listora_search_reindex` (handler `Search_Indexer::process_scheduled_reindex`, 200 listings per tick, re-schedules until done). Only present after a schema bump triggers a background reindex.

### C.cli
**What to verify:** WP-CLI namespace works:
- `wp listora stats` — counts of listings / reviews / claims / favorites
- `wp listora reindex` — rebuilds search_index without fatal
- `wp listora test-email <template> --to=<email>` — sends a sample
- `wp listora cleanup` — purges expired drafts and orphan rows

Each subcommand returns sensible output and 0 exit code on the happy path.

### C.rest.contract
**What to verify:** spot-check the REST contract — at least one endpoint per controller — confirms shape per `docs/REST-API.md`:
- List endpoint envelope `{ items_key, total, pages, has_more }`
- Single resource includes `id`, `created_at`, `updated_at`
- 401 on unauth (not `false`, not 200-with-empty)
- `has_more` formula `(offset+count)<total` (never `count===limit`)

Use `curl -i http://directory.local/wp-json/listora/v1/listings` and a paginated request to verify.

---

## D — Known-regression guards

Each row is a repro of a past bug. Fixture IS the contract.

| ID | Card | Bug | Fixture + assertion |
|----|------|-----|---------------------|
| D.setup-wizard-headers | #9867159785 | Setup wizard "Go to Dashboard" → blank page | Create user with `manage_listora_settings` cap but NOT `edit_listora_listings`. Walk wizard to "Done" step. Click "Go to Dashboard". Assert: lands on `admin.php?page=listora` (not blank). Assert: `wp-content/debug.log` has zero `Cannot modify header information - headers already sent` entries. |
| D.empty-media-fieldset | #9867347053 | Details step rendered empty `<fieldset><legend>Media</legend></fieldset>` | Login as Contributor → Add Listing → pick Business → Continue to Details. Assert: NO empty fieldset whose only child is a `<legend>Media</legend>`. Repeat for Restaurant, Hotel, Place, Marketplace, Real Estate, Event, Medical, Course, Job Board. |
| D.overview-company-logo-id | #9867775853 | Overview tab printed `Company Logo: 818` (raw attachment ID) | Visit any Job listing detail page. Assert: Overview tab DOES NOT contain a `<dt>Company Logo</dt><dd>{integer}</dd>` block. Assert: Company tab still renders the logo as `<img>`. |
| D.map-popup-image | #9867372176 | Map popups missing featured image | Visit any page with the listing-map block, with at least one listing that has a featured image. Click the marker. Assert popup contains `<img class="listora-map__popup-image">` for listings with thumbnails (and gracefully omits it when no thumbnail). |
| D.business-hours-firefox | #9856828615 | Firefox showed numeric spinner instead of time picker | (Manual — Firefox Desktop) Login as Contributor → Add Listing → Business → Details → click Monday opening time input. Assert: flatpickr dropdown opens (not native spinner). |
| D.map-fatal | #9871222447 | `Call to undefined function update_post_meta_cache()` | Visit any page rendering the listing-map block. Assert: HTTP 200, no fatal. Tail debug.log — no `Call to undefined function` entries. |
| D.service-details-toggle | #9872013428 | "Details" toggle on service descriptions did nothing | Visit listing detail with services. Click "Details" on a service card. Assert: `.listora-detail__service-desc--collapsed` class flips to expanded. Click again — re-collapses. Chevron rotates. |
| D.filter-count-dropdowns | #9871208081 | Filter count badge ignored dropdown filters | Open listings page → Filters panel → select a category from dropdown. Assert: badge shows `1` (was `0` before fix). Add a location selection — badge becomes `2`. Add a date preset — badge becomes `3` (date counts as one regardless of from/to/preset). |
| D.services-photo-upload | #9872014083 | Services Meta Box had no Photo upload field | `/wp-admin/post.php?post={listing_id}&action=edit` → scroll to Services meta box. Assert: Photo column visible. Click "Choose" → WP media library opens (filtered to images). Pick image → preview appears + hidden `image_id` populated. Save listing → reload — preview persists. |
| D.dashboard-2-col-layout | (today) | Dashboard sidebar+main collapsed to single column | Visit `/dashboard/?autologin=1` at 1280px+. Assert: `getComputedStyle(.listora-dashboard).display === 'grid'` AND `gridTemplateColumns` starts with `260px` (sidebar width). |
| D.empty-state-server-rendered | (today) | 0-result archive showed "0 results" but empty card was hidden | Visit `/business/?autologin=1` (or any 0-result archive). Assert: `.listora-grid__empty.listora-card--empty` is visible (not display:none / is-hidden). Empty card shows icon + "No listings found" + "Clear All Filters" CTA. |
| D.metabox-fields-merged | n/a — long-standing | Settings tabs reset on save | Settings → Submission → change "Days before a new listing expires" → Save. Switch to Search tab. Assert: Search tab values still set. |
| D.cron-as-init-timing | (2026-05-09 smoke-prep) | Action Scheduler `as_*()` called before data store init → `_doing_it_wrong` notice spam in debug.log on every CLI / admin pageload | Truncate debug.log → `wp plugin list` → `grep -c "as_next_scheduled_action\|as_schedule_recurring_action" wp-content/debug.log` returns `0`. Fix added `did_action('action_scheduler_init') > 0` guard to `Cron_Scheduler::has_action_scheduler()` + 3 Pro `maybe_schedule_*` methods. |
| D.modal-getter-pattern | #(63411c8) | Claim/Share/Login modals stuck closed | Click "Claim this business" on any detail page. Assert: modal opens (`.listora-modal[data-state="open"]`). Test for Share + Login modals as well. Pattern: `data-wp-class--is-open` MUST bind to a derived getter (e.g. `state.isClaimModalOpen`), not an inline `===` literal. |
| D.notifications-typo-hooks | #(0aa62ca) | Approve/reject/expire emails never sent | Approve a pending listing via wp-admin Listings list. Assert: Email Log gains 1 row for `listing-approved`, owner inbox gets the email. Pre-fix bug: Notifications class hooked typo'd hook names that nothing fired. |
| D.map-provider-filter | #(847dcc8) | Pro Google_Maps listener never fired | (Combo only.) With Google Maps API key configured + Maps tab provider set to Google. Visit any listing-map block. Assert: tiles come from googleapis.com (not openstreetmap.org). Pre-fix bug: Free's `wb_listora_get_setting('map_provider')` didn't fire `wb_listora_map_provider` filter. |
| D.helpful-vote-button | #(253cef9) | Helpful button missing on detail Reviews tab | Visit any listing detail → Reviews tab. Assert: each review row has a "Helpful (N)" button that increments N on click for logged-in users. |
| D.reply-form-inline | #(e01486b) | Listing-owner reply opened a non-existent modal | As listing owner, dashboard → Reviews tab. Click Reply on a review. Assert: an inline form opens below the review (not a modal). Submit → reply persists, refreshes the row. |
| D.fulltext-index-split | #(7606f8c) | Activator threw SQL syntax error on FULLTEXT clause | Fresh activate → check debug.log. Assert: zero `SQL syntax` errors during activation. `wp_listora_search_index` table has FULLTEXT index on the searchable columns. |
| D.verified-flag-feature-gate | #9911539296 | Verified badge/flag kept showing after the Pro verification feature was disabled | (Combo only.) Set `_listora_is_verified=1` on a listing with verification ON → assert `is_verified:true` on `/listings/{id}/detail`. Disable the Pro verification feature → assert meta is UNCHANGED (`1`) but `is_verified:false` on detail, list, and `/search`, and no verified badge on card/detail. Re-enable → flag + badge return. All 5 Free read sites must call `wb_listora_is_verified()`, never read the meta directly. |
| D.approve-reject-row-actions | #9910737903 | Pending listings had no one-click Approve/Reject in the admin list | On `edit.php?post_type=listora_listing&post_status=pending`, hover a pending row. Assert "Approve" + "Reject" row actions render (not on publish/rejected rows). Click Approve → status becomes `publish` + notice "Listing approved and published." Click Reject on another → status `listora_rejected` + notice "Listing rejected." Bad/missing nonce → `wp_die`. |
| D.wizard-unknown-step | #9910738227 | Setup wizard blank on `step=finish` (unknown step fell through the render switch) | Visit `admin.php?page=listora-setup&step=finish`. Assert `.listora-wizard__success` renders (heading "Your directory is ready!" + the 3 CTAs + "Go to Dashboard"), NOT a blank card with a stray Continue button. `step=done` renders identically; real steps (`type`/`pages`) still render their own fields + nav. |
| D.map-search-this-area-bounds | #9909608502 | "Search this area" dropped the drawn viewport (bounds not serialized; full reload reset the map) | Load the Directory page with `?bounds[ne_lat]=..&[ne_lng]=..&[sw_lat]=..&[sw_lng]=..` covering region A. Assert the map's `mapConfig.markers` includes an in-box listing and excludes an out-of-box one (both appear without bounds). Grid honors the same bounds. Built `store.js` `searchImmediate()` serializes `state.mapBounds`. `map_max_markers` LIMIT still applies. |

Rule: every customer-visible fix adds a D row in the same PR. After 2 clean releases, a D row graduates into C/E.

---

## E — Pro-only flows (combo mode)

Each Pro extension gets a customer contract. Run only when `wb-listora-pro` is active. Pro has **29 feature toggles** (`wb_listora_pro_features_enabled.*`). Section E walks every customer-facing toggle plus the always-on infrastructure ones.

For toggle-able features, every E row has TWO assertions:
- **Toggle ON** — feature renders / works as documented.
- **Toggle OFF** — feature absent (no PHP fatal, no JS error, no orphan UI element, no leftover REST route).

Set toggles via `Settings → Pro Features` admin page OR `wp option patch update wb_listora_pro_features_enabled <key> 1|0`.

### E.compare (toggle: comparison)
**What to verify:** Pro's comparison block on `/compare-listings/?compare=ID,ID` renders a side-by-side table for 2-4 listings. Empty state with 0-1 selected. "Remove" button on each column updates URL + table. Floating compare bar persists via localStorage across page navigations. Toggle off → block server-renders nothing; the auto-created Compare Listings page shows the empty Gutenberg fallback.

### E.credit-system (toggle: credit_system, always-on infra)
**What to verify:** with Credits feature enabled, a member visiting `/dashboard/#credits` sees their balance, a transaction history table, and (where direct-pack purchase is configured) a Buy Credits button. Buying via Stripe / PayPal / WooCommerce flow correctly adds credits and writes a `listora_credit_log` row. Admin can manually add credits via Pro admin → Credit Transactions.

### E.pricing-plans (toggle: pricing_plans, always-on infra)
**What to verify:** Listora → Pricing Plans CPT admin page lists plans. Submission wizard's Plan step shows enabled plans with correct credit costs. Selecting a paid plan and submitting deducts credits at the documented rate. `wb_listora_listing_expiration_date` filter sets expiry per plan (Pro listener overrides Free's default).

### E.coupons (toggle: coupons)
**What to verify:** admin can Create Coupon at `admin.php?page=listora-coupons&coupon_action=add` — page renders form, NOT blank (per 2026-05-09 fix `de4b79b`). Coupon redeems on a paid plan and reduces the credit deduction. Edit and Delete also work. Generate Code utility produces unique uppercase codes.

### E.outgoing-webhooks (toggle: outgoing_webhooks)
**What to verify:** admin → Webhooks page — admin adds a webhook URL with selected events (`listing.approved`, `listing.rejected`, `listing.expired`, `claim.submitted`, etc.). Triggering an event delivers a POST to the URL with the documented payload (signature header included). Delivery log shows status code per attempt. Failed deliveries retry per Action Scheduler.

### E.webhook-receiver (toggle: webhook_receiver, inbound payments)
**What to verify:** with strict HMAC mode (default), POST to `/wp-json/listora/v1/webhooks/payment` requires `X-Listora-Signature` + `X-Listora-Timestamp` headers — missing or invalid → 401 + audit-log row. Replay of a valid payload → 401 `replay_detected`. Valid Stripe-style delivery → 200 + credits credited + `wp_listora_payments` row. Legacy mode (option=0) accepts shared-secret header path.

### E.lead-form (toggle: lead_form)
**What to verify:** sidebar lead-form on a Business / Real Estate listing accepts name+email+message, fires reCAPTCHA v3 if configured, sends notification email to the listing author. Per-listing nonce required (no nonce = 403). Per-listing-per-day cap (20/day default) returns 429 on the 21st. Toggle off → form absent from sidebar.

### E.google-maps (toggle: google_maps, auto when API key set)
**What to verify:** with Google Maps API key configured (Settings → Maps), the listing-map block uses Google tiles instead of OSM. Listing detail's location section uses Google Maps. Toggle off (or no key) → falls back to Leaflet/OSM cleanly.

### E.google-places (toggle: google_places, always-on infra)
**What to verify:** submission wizard's address field surfaces Google Places autocomplete suggestions as the user types. Selecting one populates address + lat/lng + city + country. Reads same `google_maps_key` as E.google-maps.

### E.advanced-search (toggle: advanced_search)
**What to verify:** member dashboard surfaces "Saved searches" (or similar) UI. Saving a search persists `listora_saved_searches` row. Daily Action Scheduler job (`saved_search_alerts`) emails matches when listings appear. Toggle off → saved-search UI absent + cron unscheduled.

### E.analytics (toggle: analytics)
**What to verify:** anonymous + authenticated visitors trigger view + click events (track REST endpoint requires per-listing nonce). Owner dashboard Analytics tab shows totals, CTR, favorites count. Daily `analytics_cleanup` cron prunes old rows per retention setting. Toggle off → no track requests fired, dashboard tab hidden.

### E.multi-criteria-reviews (toggle: multi_criteria_reviews)
**What to verify:** for review-criteria-configured listing types, the review form shows multiple star inputs (e.g. "Food", "Service", "Ambiance" for Restaurant) — submitting persists per-criterion ratings AND computes a correct overall.

### E.photo-reviews (toggle: photo_reviews)
**What to verify:** the review form accepts up to N images. Submission writes `listora_review_votes` photo refs. Reviews tab on the detail page renders thumbs that lightbox.

### E.coming-soon (toggle: coming_soon)
**What to verify:** with Visibility = "Coming Soon", non-admin visitors are redirected to a coming-soon template; admins bypass via `manage_listora_settings`. With Visibility = "Private", non-admins redirect to login. Toggle off → site fully public regardless of Visibility setting.

### E.audit-log (toggle: audit_log)
**What to verify:** Pro admin → Audit Log page lists admin/REST mutations with filterable columns (user, action, target, time). CSV export works. Daily `audit_cleanup` cron prunes per retention. Toggle off → admin page hidden, no new rows written.

### E.badges (toggle: badges)
**What to verify:** admin → Badges page — admin creates a badge (label, icon, criteria). Auto-assignment fires when a listing meets the criteria (e.g. "Verified" after claim approval, "Top-rated" on rating ≥4.5). Listing card + detail render the badge pill. Toggle off → no badges render frontend-side.

### E.verification (toggle: verification)
**What to verify:** Verification meta-box on Listing edit screen surfaces a status display. Owner can request verification from dashboard; admin reviews + approves → "Verified" badge auto-assigned. Listener on `wb_listora_listing_claimed` updates search-index `is_claimed` column.

### E.moderator (toggle: moderator)
**What to verify:** admin promotes subscriber → moderator via Pro admin → Moderator. New `listora_moderator` role granted moderation caps. Moderator visiting `/dashboard/#moderator-queue` (or the moderator-queue block on a public page) sees ONLY items assigned to them. Bulk reassign from admin → receiving moderator gets email. Moderator-only audit log endpoint clamps `user_id` filter to the requesting moderator.

### E.needs (toggle: needs_dashboard_tab + reverse_listings)
**What to verify:** Pro's Needs CPT — `/post-need/` form submits a need; `/browse-needs/` lists them; vendors respond via Need Response REST endpoint; need-creator accepts/rejects responses. Member dashboard "My Needs" tab visible.

### E.reverse-listings (toggle: reverse_listings)
**What to verify:** admin → Reverse Listings page lists need posts. Admin creates a listing in response to a need. Need-creator notified.

### E.notification-digest (toggle: notification_digest)
**What to verify:** member can opt into digest mode (instant / digest / digest_urgent) in dashboard preferences (or via Pro Settings). With digest mode on, non-urgent notifications batch into a daily email at 9am instead of firing instant. `wb_listora_send_notification` filter intercepts and queues. Toggle off → all notifications instant.

### E.seo-pages (toggle: seo_pages)
**What to verify:** Pro Settings → SEO Pages tab visible (only when toggle on). Admin enables landing-page generators (e.g. "Restaurants in {city}"). Generated pages render with `<title>`, meta description, schema.org markup, og: tags. Sitemap entries include the generated URLs.

### E.white-label (toggle: white_label)
**What to verify:** Pro Settings → White Label tab — admin sets custom plugin name + hide-author-info. wp-admin → Plugins page shows the custom name (rather than "WB Listora Pro"). Toggle off → reverts to default "WB Listora Pro" branding.

### E.infinite-scroll (toggle: infinite_scroll)
**What to verify:** with toggle on, listing-grid block on `/listings/` auto-loads next page on scroll-near-bottom (no pagination clicks). Toggle off → standard pagination renders.

### E.quick-view (toggle: quick_view)
**What to verify:** with toggle on, listing card surfaces a "Quick View" affordance that opens a modal with summary (image, title, rating, snippet) without leaving the listings page. Toggle off → no quick-view button.

### E.field-mapper (toggle: field_mapper, always-on infra)
**What to verify:** admin → Tools → Field Mapper — when importing CSV, the column-mapping UI auto-detects column meaning (Name, Address, Phone, Hours) and lets admin override. Mapping persists per-import.

### E.migration (toggle: migration, always-on infra)
**What to verify:** admin → Tools → Migrate from competitor — at least one adapter (e.g. GeoDirectory) imports a sample listing batch with all geo + meta + categories preserved. `_listora_migrated_from` postmeta set via `Migrated_From_Tracker`.

### E.services-pro (toggle: services_pro, always-on infra)
**What to verify:** with services_pro on, listing detail Services tab cards expose a "Book" CTA. Booking flow either deep-links to a Pro booking template or surfaces an inline form (per configuration). `wb_listora_after_service_detail` action fires inside the services foreach.

### E.buddy-press-integration (toggle: buddy_press_integration, auto when BP active)
**What to verify:** with BuddyPress active + toggle on, listing-detail review-author links route to BP profile (`bp_core_get_user_domain`) instead of WP author archive. Toggle off → links resolve to default WP author URL.

### E.pro-license
**What to verify:** Pro Settings → License tab — invalid key produces clear error (no fatal). Valid key marks status = active. Deactivate → status = inactive, license-gated features fail-soft (no fatal). Reactivate restores. Expired key disables license-gated features but Free remains fully functional.

### E.pro-setup-wizard
**What to verify:** Pro's setup wizard (`admin.php?page=listora-pro-setup`) runs after Pro activation. Walks through license activation → feature defaults → done. Re-running is idempotent.

### E.pro-admin-pages
**What to verify:** every Pro admin page renders without fatal. There is no standalone Pro "dashboard" slug — Pro UI lives inside the shared `listora` menu via these submenus + Settings tabs:

Always-on submenus (anyone with `manage_listora_settings`):
- `admin.php?page=listora-settings` (Settings, tabs include Pro Features, License, Credits, Import/Export)
- `admin.php?page=listora-transactions` (credit transactions log)
- `admin.php?page=listora-analytics` (analytics dashboard)
- `admin.php?page=listora-pro-setup` (Pro setup wizard, first-run focus)

Toggle-gated submenus — appear ONLY when the matching `wb_listora_pro_features` key is `true`:
- `admin.php?page=listora-webhooks` — requires `outgoing_webhooks` toggle (default OFF)
- `admin.php?page=listora-badges` — requires `badges` toggle (default ON)
- `admin.php?page=listora-coupons` — core feature, always on
- `admin.php?page=listora-moderators` — core feature, always on
- `admin.php?page=listora-audit-log` — requires `audit_log` toggle (default ON)
- `admin.php?page=listora-needs` — requires `reverse_listings` toggle (default OFF)

Legacy URL stubs (redirect to Settings tabs, not standalone pages):
- `admin.php?page=listora-credit-mappings` → `listora-settings&tab=credits`
- `admin.php?page=listora-tools` → `listora-settings&tab=import-export`

A 403 on a toggle-gated slug while the toggle is OFF is correct behavior, NOT a failure.

### E.pro-cron
**What to verify:** all 7 Pro cron jobs scheduled via Action Scheduler (NOT WP-Cron):
- `wb_listora_pro_audit_cleanup`
- `wb_listora_pro_saved_search_alerts`
- `wb_listora_pro_analytics_cleanup`
- `wb_listora_pro_webhook_log_cleanup`
- `wb_listora_pro_deliver_webhook` (retry)
- `wb_listora_pro_expire_needs`
- `wb_listora_pro_license_check`

`wp action-scheduler list --group=wb_listora_pro` shows all 7. None in `wp cron event list`.

### E.pro-features-toggle-page
**What to verify:** `admin.php?page=listora-settings&tab=pro-features` lists all 29 Pro feature toggles with their defaults. There is no standalone `wb-listora-features` page — the toggles live as a tab inside Settings. Toggling ON/OFF persists in the `wb_listora_pro_features` option. Subsequent page-loads honor the toggle (feature class loads/unloads via `Feature_Manager::load_features()`). Notice + cache flush on save.

### E.pro-version-lockstep
**What to verify:** `wp eval 'echo "free:" . WB_LISTORA_VERSION . " pro:" . WB_LISTORA_PRO_VERSION;'` — both constants are identical. Drift = halt + Basecamp card.

### E.pro-dependency-guard
**What to verify:** deactivating Free while Pro stays active — admin notice "WB Listora Pro requires WB Listora" appears. Pro bails out without fataling. Reactivating Free restores Pro automatically.

### E.pro-cross-coupling (29 Free→Pro pairs)
**What to verify:** spot-check at least 4 of the 29 documented coupling pairs from `audit/derived/cross-plugin-coupling.json`:
- `wb_listora_listing_claimed` → Pro's `Verification::on_listing_claimed` updates search-index.
- `wb_listora_listing_expiration_date` filter → Pro's `Pricing_Plans::filter_listing_expiration_date` overrides.
- `wb_listora_register_pages` action → Pro registers `compare` page via the helper.
- `wb_listora_after_reset_settings` + `wb_listora_reset_option_keys` → Pro options purged on Reset Settings.

---

## F — Cross-browser, RTL, accessibility

### F.chromium
Already covered by Sections A-E.

### F.firefox-desktop
Chromium-only Playwright cannot walk these. Populate `manual_required[]`:
- Submission wizard Business Hours flatpickr opens on click (D.business-hours-firefox repro).
- listing-map popups feature image renders.
- Dashboard responsive breakpoint at 768px.
- Search autocomplete dropdown keyboard navigation.

### F.safari-ios
- Sticky bottom CTA bar on listing detail (Call / Visit / Save) doesn't overlap WP admin bar.
- Submission wizard step transitions don't trigger viewport jumps.
- Map-popup tap targets ≥40px.

### F.rtl
**What to verify:** with `wp option set WPLANG ar` (or equivalent RTL locale), every primary template renders right-to-left. Search bar location field flips. Card meta rows (rating ★ → on the right). Calendar block week starts from right. Sidebar nav on dashboard mirrors. Compare-table column order reverses.

### F.a11y
**What to verify:**
- Keyboard tab order through directory grid → card → save button → next card.
- Visible focus rings on every interactive element.
- Icon-only buttons have `aria-label`.
- Submission wizard step indicator uses `aria-current="step"`.
- Modal toolkit (claim/share/login) — DevTools console snippet:
  ```js
  const o = document.querySelector('.listora-modal[data-state="open"]');
  console.log({
    role: o.getAttribute('role'),                     // expect: 'dialog'
    ariaModal: o.getAttribute('aria-modal'),          // expect: 'true'
    bodyOverflow: document.body.style.overflow,       // expect: 'hidden'
    focusInside: o.contains(document.activeElement),  // expect: true
  });
  ```
- Tab/Shift+Tab cycle inside modal; Esc closes; backdrop click closes; focus returns to trigger.

---

## G — Post-release monitoring (first 24h)

- Watch `wp-content/debug.log` for new fatals/warnings.
- `wp action-scheduler list --status=failed --group=wb_listora` — should be empty.
- `wp action-scheduler list --status=failed --group=wb_listora_pro` — should be empty.
- Support tickets / Slack #listora-support channel for breakage reports.
- Activity-signal drops: listing-create, review-submit, favorite-add daily counts vs prior week.
- New `webhook_auth_rejected` audit-log rows beyond baseline noise indicate misconfigured customer integrations or attack traffic.

---

## Failure protocol

1. Screenshot every failure: `browser_take_screenshot({ filename: "fail-<id>.png" })`.
2. Triage — `from` (our bug) vs `for` (theme / other plugin / browser limit / legacy data).
3. Record in `failures[]` with `{ id, origin, triage_note, expected, actual, url, screenshot }`.
4. Never halt — collect ALL failures in one pass.
5. Emit Basecamp draft per failure with origin populated.

Triage is Sonnet's job; fix-or-document is the calling session's (Opus's) job.

## Step ID format

`<Section>.<persona>.<feature>` e.g. `C.member.submit-listing`. D rows: `D.<descriptor>`. E rows: `E.<extension>`.

## Maintenance

Every customer-visible bug fix that lands must add a D row in the same PR. After 2 clean releases of a D row, graduate it into the matching C/E flow row (the bug class is unlikely to recur). Every 6 months, compare this runbook against Jetonomy's `docs/qa/AGENT_SMOKE_RUNBOOK.md` to catch drift in the portfolio's QA model.
