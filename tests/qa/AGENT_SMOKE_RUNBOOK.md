# Agent Smoke Runbook - WB Listora

**Audience:** a browser-capable agent (Claude Sonnet or equivalent) with Playwright MCP + WP-CLI Bash access, OR a human QA person with the same access. Both should be able to execute every step.

## How to read this runbook

Each C and E step describes a **customer contract**: what the feature promises, why it matters, the surfaces it touches, and what "working" looks like. It does NOT prescribe the exact Playwright calls, selectors, REST paths, or DB queries. Read the relevant plugin code, pick the right mechanism, and verify the contract.

D rows stay specific - those are repros of past incidents; the exact fixture IS the contract.

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

Emit a Basecamp draft per failure - project `47045113`, Bugs column.

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

These run silently alongside every C/D/E step - log to `failures[]` if any tripped:

1. **DevTools console errors.** Zero red errors on every page (warnings allowed but flagged in `manual_required[]` if numerous). Check via `browser_console_messages`.
2. **No `admin-ajax.php`.** WB Listora is 100% REST as of 1.0.0 (max 2 documented AJAX exceptions in code). DevTools Network must show ZERO `admin-ajax.php` requests during front-end flows. Admin form submissions allowed via `admin-post.php` for the legacy 2 cases only - record any unexpected hit.
3. **No native dialogs.** `window.alert` / `window.confirm` / `window.prompt` must NOT fire anywhere. All confirmations route through `window.listoraConfirm()` modal helper.
4. **REST 4xx/5xx.** `/wp-json/listora/v1/*` calls return 2xx for happy paths; 401/403 only on permission-blocked actions; 404 only on missing IDs. Anything else is a failure.
5. **No raw IDs leaking to UI.** Field renderers must show resolved values - never `Company Logo: 818`, never `Category: 42`.
6. **CSS tokens.** Rendered stylesheet should resolve through `--listora-*` custom properties. Hex literals appearing in computed styles indicate token gaps.

---

## A - Fresh install

### A1 - Activation and first-request routing
**What to verify:** after `wp plugin deactivate wb-listora && wp plugin activate wb-listora`, the primary front-end routes respond on the FIRST request without re-saving Permalinks. Activator's `flush_rewrite_rules()` must defer to `init` priority 99 (per the 2026-05-07 fix that resolved the textdomain cascade).
**Why it matters:** rewrite-flush-on-activation regressions break first impressions. We've shipped this fix once already (commit `5b4840f`); regressions here are real.
**Acceptance:** `/listings/`, `/add-listing/`, `/dashboard/` all return HTTP 200; `wp rewrite list | grep listora` shows the listing CPT permalink rules.

### A2 - Database schema is in place
**What to verify:** all 11 listora_-prefixed tables exist (`listora_geo`, `listora_search_index`, `listora_field_index`, `listora_reviews`, `listora_review_votes`, `listora_favorites`, `listora_claims`, `listora_hours`, `listora_analytics`, `listora_payments`, `listora_services`). The `wb_listora_db_version` option matches `WB_LISTORA_VERSION`. Engine on every table is `InnoDB`.

### A3 - Pro pairs cleanly (combo mode only)
**What to verify:** activating `wb-listora-pro` on top of `wb-listora` does not fatal; Pro-only tables (`listora_credit_log`, `listora_audit_log`, `listora_saved_searches`) are created; both version constants agree (lockstep). All 12 architecture invariants pass via `bin/architecture-checks.sh`.

### A4 - Setup wizard auto-redirects on first activation
**What to verify:** the `wb_listora_show_wizard_redirect` transient sets at activation; first admin pageload as a `manage_options` user redirects to `admin.php?page=listora-setup` and the transient clears.

### A5 - Essential pages auto-create
**What to verify:** activator creates Directory (`/listings/`), Add Listing (`/add-listing/`), and My Dashboard (`/dashboard/`) pages - idempotent, won't duplicate if they already exist with matching block content.

### A6 - Default capabilities + roles
**What to verify:** `wp role list` shows `administrator` granted Listora caps (`manage_listora_settings`, `moderate_listora_reviews`, `manage_listora_claims`, `manage_listora_types`). `editor` granted `moderate_listora_reviews`. Subscriber unchanged. The `listora_moderator` role is registered (Pro adds the seat-grants on combo activation).

### A7 - Default listing types seeded
**What to verify:** activator registers default listing types (Business / Restaurant / Hotel / Real Estate / Job / Event / Place / Marketplace / Medical / Course). `wp listora stats` (or browser at `admin.php?page=listora-listing-types`) lists each one with its field-group count.

### A8 - Setup wizard demo seed (optional path)
**What to verify:** at the wizard's "Seed demo content" step, picking a demo pack creates ~5 listings of the chosen type with images, hours, services, and a review or two. `wp_listora_geo` rows present for each. Skip-without-seed also completes the wizard cleanly.

---

## B - Upgrade from previous version

### B1 - Migration is silent, data is intact
**What to verify:** upgrading from the last released stable to current build completes with zero new debug.log entries during the activation request. Pre-existing listings still render. Search index counters stay accurate (`SELECT COUNT(*) FROM wp_listora_search_index` matches the published-listing count from `wp_posts`).

### B2 - Settings format migration
**What to verify:** options under `wb_listora_settings` are merged not replaced when new keys are added. Editing one tab on Settings page does not drop values from a different tab.

### B3 - Capabilities re-registered
**What to verify:** an upgrade from 1.0.0-alpha (or wherever Capabilities::get_caps_map changed last) re-grants any newly-added cap to administrator without manual `wp role add-cap`.

### B4 - Cron transport flip (Action Scheduler)
**What to verify:** legacy WP-Cron entries for `wb_listora_*` hooks are unscheduled on upgrade and replaced with Action Scheduler entries. `wp action-scheduler list --status=pending --group=wb-listora` returns 6 pending recurring jobs (see C.cron for the canonical hook-name table). `wp cron event list | grep wb_listora` returns nothing.

---

## C - Core customer flows

Persona ladder: Anonymous → Subscriber/Customer → Contributor (submitter) → Editor → Admin. Test desktop 1280px and mobile 390px where the UI differs.

### C.anon.directory-home
**What to verify:** `/listings/` (and the homepage if listora-grid is the front block) renders for a logged-out visitor with real listing cards, working type tabs, and a search bar that returns results. Pagination renders when results exceed per_page.

### C.anon.listing-detail
**What to verify:** every public template (single listing detail) renders without fatal for an anonymous visitor. Auth-gated actions (Save / Claim / Compare) cleanly prompt login rather than failing 403. URLs to test: at least 5 distinct listing slugs across different listing types (Business, Restaurant, Hotel, Real Estate, Event).

### C.anon.search-facets
**What to verify:** search filters all narrow the result set:
- Keyword (FULLTEXT match in title + content + services + meta)
- Location (geocoded radius - pick a city and 5km radius)
- Type tab switching (Business → Restaurant → Hotel)
- Date filter (events only - preset "This week", "This month", custom range)
- Per-type checkboxes (e.g. "Free WiFi" for Restaurant)
- Per-type dropdowns (e.g. Cuisine for Restaurant)

The active-filter-count badge matches the user's perceived count of selected filters (post-2026-05-08 fix).

### C.anon.search-suggest
**What to verify:** typing 2+ characters into the search box surfaces a debounced (250ms+) suggestions dropdown - populated from `/wp-json/listora/v1/search/suggest`. Click a suggestion → navigates to filtered results page. Network tab shows ≤1 request per ~250ms of typing, NOT one per keystroke.

### C.anon.geo-distance
**What to verify:** results page for a geo-filtered search shows distance-from-origin per listing card (e.g. "1.2 km away"). Distance computation matches Haversine within ±50m.

### C.anon.empty-state
**What to verify:** a category page with zero listings (e.g. `/business/` if no Business listings exist) shows the canonical `.listora-card--empty` card with icon + "No listings found" + "Clear All Filters" CTA - not a blank space.

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
**What to verify:** Business / Hotel / Restaurant types show the Business Hours field in Details step. Clicking any time input opens a flatpickr time-picker (24h, 15-min increments) - works identically on Chrome AND Firefox (the round-2 flatpickr fix from 2026-05-08 covers Firefox's native-spinner gap).

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
**What to verify:** `/wp-admin/` (default landing) shows the Listora dashboard widget with totals (listings / reviews / claims / pending). Widget data sourced from cached transient - no slow query on dashboard load.

### C.admin.cpt-list
**What to verify:** `/wp-admin/edit.php?post_type=listora_listing` lists listings with custom columns (Type, Category, Location, Status, Featured, Date). Bulk actions (Approve, Reject, Feature, Trash) function. Filters in the table top bar narrow correctly.

### C.admin.cpt-edit
**What to verify:** Listing CPT can be created via `/wp-admin/post-new.php?post_type=listora_listing` - even when "Days before a new listing expires" setting is non-zero (post-2026-05-08 verification: this was the cannot-reproduce repro for card #9857011539). Services meta box supports Photo upload (post-2026-05-09 fix `5eb3b33`).

### C.admin.types-crud
**What to verify:** `/wp-admin/admin.php?page=listora-listing-types` - admin can add a custom type (slug, label, icon, field groups), edit it, and delete it. New type appears as a tab on `/listings/` and as an option in the submission wizard's Type step.

### C.admin.taxonomies
**What to verify:** the three taxonomy admin pages render and accept new term creation:
- Categories (`edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing`) - hierarchical
- Locations (`edit-tags.php?taxonomy=listora_listing_location&post_type=listora_listing`) - hierarchical
- Features (`edit-tags.php?taxonomy=listora_listing_feature&post_type=listora_listing`) - flat

Adding a category → assigning to a listing → filtering `/listings/?listora_listing_cat={slug}` returns just that listing.

### C.admin.reviews-mod
**What to verify:** `admin.php?page=listora-reviews` lists reviews with status filters (All, Pending, Approved, Rejected). Approve / Reject inline actions transition status, fire `wb_listora_review_status_changed`, update the public detail page. Bulk delete removes rows from `listora_reviews`.

### C.admin.claims-approval
**What to verify:** `admin.php?page=listora-claims` lists claims with status badges. Approve sets `wp_listora_claims.status=approved`, transfers `wp_posts.post_author` to the claimant, sends approval email. Reject keeps the listing's original author and emails the claimant the rejection reason.

### C.admin.email-log
**What to verify:** `admin.php?page=listora-email-log` lists every email sent (recipient, template, status, timestamp). Resend action delivers a copy. Filter dropdowns narrow by template / recipient / date.

### C.admin.health-check
**What to verify:** `admin.php?page=listora-health` redirects to (or renders inside) Settings → Advanced. Surface lists actionable warnings - deactivate cron locally → reload → Cron Schedules warning appears with a "Run now" button.

### C.admin.setup-wizard-revisit
**What to verify:** revisiting `admin.php?page=listora-setup` after first-run completion still renders cleanly. Each step is editable; saving updates only that step's data without resetting completed steps.

### C.admin.import-export
**What to verify:** Settings → Import/Export tab - exports CSV / JSON / GeoJSON of all listings. Re-importing the JSON onto a clean install round-trips every field including geo coords, hours, services, and meta. Counts match.

### C.analytics-lite
**What to verify:** Free's `Analytics_Lite` records a per-listing view exactly once and surfaces it on three entry points (owner dashboard, admin listings table, REST), gated to owner + admin. The `listora_analytics` table is an UPSERT-per-day aggregate keyed `(listing_id, event_type, event_date)` - assert on the `count` COLUMN delta, never row count. A real anonymous browser view (normal UA, not curl/headless - `is_bot()` rejects those) increments `count` by +1; a same-visitor refresh within the hour does not (per-IP dedupe transient). Anonymous `GET /listora/v1/listings/{id}/detail` carries NO `views` field; owner (`post_author`) and admin (`edit_others_posts`) DO. Owner dashboard Listings tab shows an "N views" tag; `edit.php?post_type=listora_listing` has a sortable "Views" column (`orderby=listora_views`). Pro supersession (combo): with the Pro analytics toggle ON, `apply_filters('wb_listora_pro_owns_analytics', false)` is `true`, Free stands down from writing (Pro records), and the read surfaces still resolve - full single-vs-double contract in Pro `regression/analytics-no-double-count.md`. Source: `includes/features/class-analytics-lite.php`, `includes/admin/class-listing-columns.php`, `includes/rest/class-listings-controller.php`. Covered by `admin/owner-sees-view-count.md`.

### C.admin.background-import
**What to verify:** demo-pack and file imports run on Action Scheduler off the wizard request, polled live over REST. The wizard start returns a `run_id` and stays responsive (no request >10s - chunks run async). `GET /listora/v1/import/progress/{run_id}` returns `{ run_id, kind, status, total, processed, imported, skipped, errors, percent, messages[], done }` and is admin-only (anon → 401 `listora_unauthorized`, subscriber → 403 `listora_forbidden`). The run is resumable (cursor persisted per chunk) and idempotent (a row whose hash is already mapped this run is skipped) - killing and restarting the AS runner mid-import resumes from the cursor and creates NO duplicate listings/images. On exhaustion the finalize job rebuilds the search index, so imported listings are searchable; final state is `done` / `percent=100`. debug.log clean (no AS-before-init `_doing_it_wrong` spam). Source: `includes/import-export/class-background-import.php`. Covered by `admin/demo-import-background-progress.md`.

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
**What to verify:** editing one Settings tab and saving does NOT drop values from another tab. Specifically: change Submission tab's "Days before a new listing expires" → save → reload Search tab - Search tab values intact.

### C.admin.settings-reset
**What to verify:** Reset Settings affordance restores defaults. Pro options (Pro_Plugin's listener on `wb_listora_after_reset_settings` + `wb_listora_reset_option_keys`) ALSO purge - license, white-label, visibility values cleared.

### C.notifications
**What to verify:** `wb_listora_listing_status_changed` action fires once per actual transition (per the 2026-04-30 fix `0aa62ca`). Approve / reject / expire emails reach the listing author. Email Log admin page records each send. 15 templates exist under `templates/emails/`; each renders via `wp listora test-email <template>` without fatal.

### C.cron
**What to verify:** all 6 recurring cron jobs are scheduled after activation per Action Scheduler (NOT WP-Cron). These are the actual hook names registered in source - verified 2026-05-18:

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
- `wp listora stats` - counts of listings / reviews / claims / favorites
- `wp listora reindex` - rebuilds search_index without fatal
- `wp listora test-email <template> --to=<email>` - sends a sample
- `wp listora cleanup` - purges expired drafts and orphan rows

Each subcommand returns sensible output and 0 exit code on the happy path.

### C.rest.contract
**What to verify:** spot-check the REST contract - at least one endpoint per controller - confirms shape per `docs/REST-API.md`:
- List endpoint envelope `{ items_key, total, pages, has_more }`
- Single resource includes `id`, `created_at`, `updated_at`
- 401 on unauth (not `false`, not 200-with-empty)
- `has_more` formula `(offset+count)<total` (never `count===limit`)

Use `curl -i http://directory.local/wp-json/listora/v1/listings` and a paginated request to verify.

New 1.2.0 route - `GET /listora/v1/import/progress/(?P<run_id>[A-Za-z0-9]+)` (`Background_Import::register_rest_routes` / `rest_progress`): admin-only read (`progress_permissions` = logged-in + `manage_options`; anon → 401 `listora_unauthorized`, non-admin → 403 `listora_forbidden`) returning the live import-progress shape `{ run_id, kind, status, total, processed, imported, skipped, errors, percent, messages[], done }`; unknown run_id → 404 `listora_import_run_not_found`. The listing detail/list responses also gained an owner/admin-gated `views` field (`class-listings-controller.php`) - see C.analytics-lite.

---

## D - Known-regression guards

Each row is a repro of a past bug. Fixture IS the contract.

| ID | Card | Bug | Fixture + assertion |
|----|------|-----|---------------------|
| D.setup-wizard-headers | #9867159785 | Setup wizard "Go to Dashboard" → blank page | Create user with `manage_listora_settings` cap but NOT `edit_listora_listings`. Walk wizard to "Done" step. Click "Go to Dashboard". Assert: lands on `admin.php?page=listora` (not blank). Assert: `wp-content/debug.log` has zero `Cannot modify header information - headers already sent` entries. |
| D.empty-media-fieldset | #9867347053 | Details step rendered empty `<fieldset><legend>Media</legend></fieldset>` | Login as Contributor → Add Listing → pick Business → Continue to Details. Assert: NO empty fieldset whose only child is a `<legend>Media</legend>`. Repeat for Restaurant, Hotel, Place, Marketplace, Real Estate, Event, Medical, Course, Job Board. |
| D.overview-company-logo-id | #9867775853 | Overview tab printed `Company Logo: 818` (raw attachment ID) | Visit any Job listing detail page. Assert: Overview tab DOES NOT contain a `<dt>Company Logo</dt><dd>{integer}</dd>` block. Assert: Company tab still renders the logo as `<img>`. |
| D.map-popup-image | #9867372176 | Map popups missing featured image | Visit any page with the listing-map block, with at least one listing that has a featured image. Click the marker. Assert popup contains `<img class="listora-map__popup-image">` for listings with thumbnails (and gracefully omits it when no thumbnail). |
| D.business-hours-firefox | #9856828615 | Firefox showed numeric spinner instead of time picker | (Manual - Firefox Desktop) Login as Contributor → Add Listing → Business → Details → click Monday opening time input. Assert: flatpickr dropdown opens (not native spinner). |
| D.map-fatal | #9871222447 | `Call to undefined function update_post_meta_cache()` | Visit any page rendering the listing-map block. Assert: HTTP 200, no fatal. Tail debug.log - no `Call to undefined function` entries. |
| D.service-details-toggle | #9872013428 | "Details" toggle on service descriptions did nothing | Visit listing detail with services. Click "Details" on a service card. Assert: `.listora-detail__service-desc--collapsed` class flips to expanded. Click again - re-collapses. Chevron rotates. |
| D.filter-count-dropdowns | #9871208081 | Filter count badge ignored dropdown filters | Open listings page → Filters panel → select a category from dropdown. Assert: badge shows `1` (was `0` before fix). Add a location selection - badge becomes `2`. Add a date preset - badge becomes `3` (date counts as one regardless of from/to/preset). |
| D.services-photo-upload | #9872014083 | Services Meta Box had no Photo upload field | `/wp-admin/post.php?post={listing_id}&action=edit` → scroll to Services meta box. Assert: Photo column visible. Click "Choose" → WP media library opens (filtered to images). Pick image → preview appears + hidden `image_id` populated. Save listing → reload - preview persists. |
| D.dashboard-2-col-layout | (today) | Dashboard sidebar+main collapsed to single column | Visit `/dashboard/?autologin=1` at 1280px+. Assert: `getComputedStyle(.listora-dashboard).display === 'grid'` AND `gridTemplateColumns` starts with `260px` (sidebar width). |
| D.empty-state-server-rendered | (today) | 0-result archive showed "0 results" but empty card was hidden | Visit `/business/?autologin=1` (or any 0-result archive). Assert: `.listora-grid__empty.listora-card--empty` is visible (not display:none / is-hidden). Empty card shows icon + "No listings found" + "Clear All Filters" CTA. |
| D.metabox-fields-merged | n/a - long-standing | Settings tabs reset on save | Settings → Submission → change "Days before a new listing expires" → Save. Switch to Search tab. Assert: Search tab values still set. |
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
| D.pagination-active-page-contrast | #(b299fd6) | Pagination active-page text invisible under aggressive theme link rules (BuddyX recolored `.entry-content a:not()×3` at 0,4,1 → blue-on-blue) | On `/listings/` with >1 page of listings (BuddyX active), find the active page number (`a.listora-grid__page-num.is-active[aria-current="page"]`). Assert its computed text/background contrast ≥ 4.5:1 (NOT blue-on-blue) in BOTH light AND `prefers-color-scheme: dark`. Fix re-asserts pagination colour at specificity 0,5,1 (doubled class/state, NO `!important`) in `src/components/theme-hardening.css` (compiled to `assets/css/listora-components.css`). Tokens only - `var(--listora-primary)` / `--listora-primary-fg`. Covered by `regression/pagination-active-page-contrast.md`. |
| D.search-rating-average-nonzero | #(5106ee4) | `/search` always returned `rating.average: 0` (int `0 ===` guard vs float `0.0`, so the search-index fallback never fired) | For a published listing WITH approved reviews, `GET /wp-json/listora/v1/search?q=<title>`. Assert that listing's `rating.average` is the real non-zero average (matches the search-index `avg_rating` within rounding) and `rating.count` > 0. Assert a genuinely-unreviewed listing still reports `0`. Fix changes the fallback guard to `0.0 === $listing['rating']['average']` in `includes/rest/class-search-controller.php`. Covered by `regression/search-rating-average-nonzero.md`. |
| D.cli-test-email-cleanup | #(43ded68) | `wp listora test-email` + `wp listora cleanup` documented in C.cli but never registered | `wp listora test-email listing_approved --to=<email>` exits 0 (Success "Sent" OR a non-fatal delivery WARNING). `wp listora cleanup` prints "Cleanup complete." with no fatal, exit 0. `wp listora test-email` (no arg) lists all 15 templates. Unknown template → clean validation `Error:`, not a fatal. New CLI in `includes/class-cli-commands.php` (NEW feature - also +2 subcommands in `audit/manifest.json` wp-cli list + `audit/manifest.summary.json` count). Covered by `regression/cli-test-email-cleanup.md`. |
| D.coming-soon-private-cap | #(ce3f9f6, Pro) | Private visibility 403'd every logged-in non-admin - gated on the phantom cap `read_listora_listings` (never defined/granted) | (Combo.) `wb_listora_pro_visibility=private`, coming_soon ON. Logged-out → gated (login/403). A subscriber → HTTP **200** on a listing (not 403); `user_can(subscriber,'read')` true, `'read_listora_listings'` absent. Admin always 200. Gate reads `current_user_can('read')` at `wb-listora-pro/includes/features/class-coming-soon.php:99`. Covered by Pro `regression/coming-soon-private-cap.md`. |
| D.seo-sitemap-provider-registered | #(d3de2f2, Pro) | Programmatic-SEO sitemap never registered (dead `wp_sitemaps_add_provider` filter branch) | (Combo.) seo_pages ON. `wp-sitemap.xml` lists a `listora-seo` sub-sitemap; that sub-sitemap has **> 0** `<url>` entries; the provider name `listora-seo` is in `wp_sitemaps_get_server()->registry`. Registered via `wp_register_sitemap_provider('listora-seo', ...)` on init@11. Toggle OFF → provider absent. Covered by Pro `regression/seo-sitemap-provider-registered.md`. |
| D.dashboard-active-filter-status | #9962484094 | My Listings "Active" filter also showed deactivated/draft/pending rows (state fell through to `active` for every non-expired status) | Owner with mixed statuses → rows carry honest `data-listora-state` (`publish`+non-expiring=`active`, other statuses=`inactive`). Selecting "Active" hides the deactivated row; "All listings" shows everything. Covered by `regression/dashboard-active-filter-status.md`. |
| D.submission-map-picker-stacking | #9976402618 | Submission map picker's Leaflet controls (z-index 1000, root context) painted above fixed theme headers on scroll (Reign: header context trapped at z-index 100) | On the Add Listing step rendering `.listora-submission__map-picker`, `getComputedStyle(picker).isolation === 'isolate'` (LTR + RTL twins). With Leaflet mounted, scrolling the map under a fixed header keeps the header on top. Covered by `regression/submission-map-picker-stacking.md`. |
| D.settings-docs-links-live | #9919933465 | Settings Documentation buttons 404'd (per-section path `/listora/docs/{section}/`; store docs are ONE page with `#{slug}-ls` anchors) | On `admin.php?page=listora-settings`, every `.listora-docs-link` href matches `https://store.wbcomdesigns.com/listora/docs/#<slug>-ls`; each slug exists as an `<article id>` on the live docs page (HTTP 200). Pro sections map: pagination→infinite-scroll, seo→seo-pages, visibility→coming-soon, white-label→white-label, credits→credits-and-plans. Covered by `regression/settings-docs-links-live.md`. |
| D.dashboard-favorites-template-override | #9977212895 | Favorites dashboard tab was hardcoded inline in render.php — theme overrides at `{theme}/wb-listora/blocks/user-dashboard/tab-favorites.php` silently ignored | Place a marker override at that theme path → dashboard Favorites panel renders the marker; remove it → default `templates/blocks/user-dashboard/tab-favorites.php` returns. `wb_listora_{before,after}_dashboard_favorites` hooks fire with `$view_data`. Covered by `regression/dashboard-favorites-template-override.md`. |
| D.featured-block-empty-state | #9977213192 | Featured Listings block silently vanished (bare `return;`) when its type had no listings | Page with `listora/listing-featured` pointed at a listing type with 0 published listings → visible `.listora-featured--empty.listora-card--empty` card (`role="status"`, "No featured listings yet"). Populated type still renders the carousel. Covered by `regression/featured-block-empty-state.md`. |
| D.bg-import-failed-rollback | #9977212594 | Background import stuck at `running` forever after a chunk threw (AS does not retry failed actions; also `mark_failed` was clobbered by finalize → `done`) | Queue an async CSV run (>10 rows), delete the stashed source, drain → `get_progress()` reports `status:failed, done:true` (never `done`/`finalizing`). Force a throw during row processing → run self-requeues with `chunk_retries` 1→2, then `failed` + "Import failed after 3 attempts" message at the cap. A clean chunk resets `chunk_retries` to 0. Covered by `regression/bg-import-failed-rollback.md`. |
| D.digest-owner-event-delivery | #(775ecf7+a3f65bf, Pro; 0673644, Free) | Owner digest emails 100% dropped - recipient never stored + stale review event keys | (Combo.) notification mode=digest, notification_digest ON. Submit a review → queued item has `event:review_received` + `recipient_email:<owner>`. Run `send_digest()` → owner receives the digest (review event NOT dropped); queue cleared only after send. `intercept()` accepts the 4th `$to` (Free `0673644`) and stores `recipient_email`; `$owner_events` includes `review_received`. Covered by Pro `regression/digest-owner-event-delivery.md` + Free `regression/send-notification-to-arg.md`. |
| D.review-css-frontend-enqueue | #(6c8518b, Pro) | Multi-criteria + photo-review CSS lived in pro-admin.css → unstyled on public listing pages | (Combo.) multi_criteria_reviews + photo_reviews ON. On a public listing detail, the `pro-frontend.css` (`wb-listora-pro-frontend`) stylesheet is present and the review-criteria/photo rules apply (computed style ≠ UA default); `pro-admin.css` is NOT on the frontend. Rules moved to `pro-frontend.css` + RTL twin. Covered by Pro `regression/review-css-frontend-enqueue.md`. |
| D.gateway-refund-audit-amount | #(Wave-1 P4/D-H1/P-H3, Pro) | Gateway/hold refunds recorded `amount: 0` everywhere - bridge read the amount from a hint gateway refunds never set | (Combo.) Fire `do_action('wbcom_credits_refunded','wb-listora',<uid>,7,['reason'=>'gateway_refund','provider_ref'=>'pi_x','item_id'=>0])`. Assert a `wb_listora_pro_credits_refunded` listener sees `amount===7.0` (not 0) and the newest `{prefix}listora_audit_log` `credits_refunded` row has `details.amount===7`. Hold path (`item_id=<listing>`, `reason=hold_refund`) carries `amount` + `item_id`. Other-slug events are ignored. Fix: `Credit_System::on_sdk_refunded()` registered `,10,4`, reads arg3 directly. Covered by Pro `regression/gateway-refund-audit-amount.md`. |
| D.refund-after-activation-rollback | #(Wave-1 P1/M-H6, Pro) | A refund of the paying credits left the activated listing live (`publish` + `_listora_plan_id` intact) | (Combo.) Seed a paid+activated listing (publish, `_listora_plan_id` set) + a `{prefix}listora_payments` row (`gateway='stripe'`, `gateway_payment_id='pi_x'`, `listing_id=<id>`). Fire the matching gateway refund → assert `wb_listora_pro_listing_paused` (`context.cause==='refund'`), status `listora_payment`, `_listora_plan_id` deleted, `_listora_pending_plan_id=<plan>`, featured perk reversed. Hold path resolves via `item_id`. `add_filter('wb_listora_pro_refund_repauses_listing','__return_false')` → NO repause (escape hatch). Duplicate refund → no double-rollback, fires `wb_listora_pro_refund_needs_admin_attention` instead of silent no-op / wrong listing. Covered by Pro `regression/refund-after-activation-rollback.md`. |
| D.webhook-missing-txn-id | #(Wave-1 P2/I-H2, Pro) | No-transaction-id payment webhooks credited on EVERY retry (NULL `gateway_payment_id` is non-dedupable) | (Combo.) POST `/listora/v1/webhooks/payment` with no `transaction_id` (`amount:10,credits:10,gateway:'gw',timestamp:now`) twice in the same 5-min window → credited ONCE (2nd returns `duplicate:true`); payments row carries a `syn_…` `gateway_payment_id`. A distinct amount/credits → different `syn_` key → credits independently. Real `transaction_id` behaviour unchanged (stored verbatim, no `syn_` prefix). Fix: `Webhook_Receiver::process()` synthesizes `'syn_'.hash('sha256', gateway|email|amount|credits|event|floor(ts/300))` when txn_id empty, used for both the dedup check and the UNIQUE-indexed payments row. Covered by Pro `regression/webhook-missing-txn-id.md`. |
| D.verified-badge-resolver | #(8e401ef, Pro) | Pro badge-condition + comparison card read `_listora_is_verified` raw → badge leaked after verification disabled | (Combo.) Set `_listora_is_verified=1`. With verification ON badge shows on detail + comparison. Disable verification → meta UNCHANGED (`1`) but `wb_listora_is_verified()` false; no badge on detail, in the Badges engine, or in the comparison table. Both Pro reads (`class-badges.php`, `class-comparison.php`) route through `wb_listora_is_verified()`. Covered by Pro `regression/verified-badge-resolver-pro-reads.md` (pairs with Free `regression/verification-feature-disabled.md`). |
| D.audit-log-ip | #(a12bb68, Pro) | Audit-log IP trusted a spoofable X-Forwarded-For without proxy gate or IP validation | (Combo.) audit_log ON, `wb_listora_pro_trust_proxy_headers` OFF. Trigger an audited mutation with `X-Forwarded-For: 1.2.3.4` → newest `listora_audit_log.ip_address` is the real `REMOTE_ADDR` (not 1.2.3.4) and is a valid IP. With proxy-trust ON, a valid XFF (`8.8.8.8`) is honoured; garbage falls back. `Audit_Log::get_ip()` delegates to `Public_Rate_Limiter::resolve_client_ip()`. Covered by Pro `regression/audit-log-ip-spoof-guard.md`. |
| D.need-matcher-geo | #(2245fe1, Pro) | Need_Matcher re-implemented Haversine inline instead of consuming Free's geo_query service (INV-3) | (Combo.) reverse_listings ON. No private `haversine_distance()` in `class-need-matcher.php`; `find_matching_needs()` calls `wb_listora_service('geo_query')->haversine_distance()`. A near listing returns its matching need; a far one does not. Fails soft when the service is null. Covered by Pro `regression/need-matcher-geo-query-service.md`. Coupling pair count unchanged (service-locator consumption, not a hook). |
| D.claims-model | #(db8e2cd, Free) | Admin claims list + REST claims list duplicated their query+COUNT → list/total could disagree | Both `class-admin.php` (Claims page) and `class-claims-controller.php` route through `Claims_Model::get_list()` + `get_list_count()` (shared `build_where()`). For `status=pending,per_page=10,page=1`, model `total` == admin page total == REST envelope `total`; page-1 rows identical; pagination bounded (LIMIT/OFFSET); the lat/lng bounding-box arg uses the geo INNER JOIN (idx_lat_lng). Covered by Free `regression/claims-model-list-count-parity.md`. |
| D.notification-to-arg | #(0673644, Free) | `wb_listora_send_notification` didn't expose the recipient → digest consumers couldn't read `$to` | A 4-arg `add_filter('wb_listora_send_notification', fn($send,$event,$vars,$to))` receives `$to` = the real recipient email when a notification fires. Fire-site `class-notifications.php:1032` passes `$to` as the 4th arg; manifest `args_count` = **4**. Covered by Free `regression/send-notification-to-arg.md` (consumed by Pro digest - see D.digest-owner-event-delivery). |
| D.review-minlength-reads-setting | #(236dc03, Free) | Detail-page review form hardcoded minlength=20 + required, ignoring Settings → Reviews → Minimum length | On a listing detail Write-a-Review form: with `reviews.min_length=50` the content `textarea[name="content"]` is `required` + `minlength="50"` (not 20), placeholder "Share your experience (minimum 50 characters)". With `min_length=0` the textarea has NO `required` + NO `minlength`, placeholder "Share your experience (optional)" (rating-only allowed). Matches `listing-reviews/review-form.php` for the same setting. `tabs.php:603-648`. Covered by Free `regression/review-minlength-reads-setting.md`. |
| D.review-report-reason-enum | #(1d87a15, Free) | Review-report reason not enum-validated + drifted from listing-report enum / admin labels | `POST /listora/v1/reviews/{id}/report` with `reason=inaccurate` (or spam\|closed\|duplicate\|offensive\|other) → 200; `reason=banana` / empty / free-form → 400 `rest_invalid_param`. The reviews-controller enum is `array_keys($this->report_reasons())` → `Report_Metabox::reasons()`; the listing-report enum + admin labels resolve from the SAME method. Covered by Free `regression/review-report-reason-enum.md`. |
| D.schema-rest-toggle-gate | #(c0995ab, Free) | REST listing `schema` field emitted unconditionally, ignoring the Schema.org toggle | With schema OFF, `GET /listora/v1/listings/{id}/detail` (and `/{id}`) → `data.schema === null` (no populated object) AND the page emits no JSON-LD; with schema ON → `data.schema` is the generated object and the page has one JSON-LD block. Parity with `output_schema()`. Gate at `class-listings-controller.php:1066-1071`. Covered by Free `regression/schema-rest-toggle-gate.md`. |
| D.schema-yoast-rankmath-guard | #(d412d58, Free) | Listora emitted LocalBusiness/Place JSON-LD even when Yoast/Rank Math owns schema (duplicate) | On a single `listora_listing`: with `WPSEO_VERSION` OR `RANK_MATH_VERSION` defined, `Plugin::output_schema()` emits ZERO `application/ld+json` (early return at `class-plugin.php:489`); with neither defined and schema ON, exactly ONE Listora JSON-LD block. Covered by Free `regression/schema-yoast-rankmath-guard.md`. |
| D.sm-button-tap-target | #(f2-sm-tap-target, Free wave-2) | Customer-facing `--sm` buttons (search filters, submission form, map popups) had a sub-40px hit area | At a 390px viewport on a directory page, every customer-facing `.listora-btn--sm` (NOT inside `.wb-listora-admin`) renders `>= 40px` tall (`min-height: 40px` at `src/components/button.css:158` → compiled `assets/css/listora-components.css:315`); the `.wb-listora-admin .listora-btn--sm { min-height: 34px; }` density exception is intact; no two filter or pagination tap targets overlap. Covered by Free `regression/sm-button-tap-target.md`. |
| D.card-image-alt-fallback | #(f2-image-alt-fallback, Free wave-2) | Missing featured images emitted empty/misleading alt; untitled listings produced empty alt - `visual_required_no_enforcement` detector unenforced | No-featured-image cards render the deterministic `wb_listora_placeholder_url()` SVG with `alt=""` + `aria-hidden="true"` (decorative); real featured images carry a title alt falling back to `Listing #ID` when untitled (never empty); detail hero + thumbnails resolve attachment-alt → title → `Listing #ID`; the map popup marker resolves a deterministic title + explicit `imageAlt`. Sources: `templates/blocks/listing-card/card-image.php`, `templates/blocks/listing-detail/gallery.php`, `blocks/listing-map/render.php`. Covered by Free `regression/card-image-alt-fallback.md`. |
| D.duplicate-canonical | #(8ecd82e, Free) | Listing singular emitted TWO `<link rel=canonical>` (core's rel_canonical + Listora's) | On a published `listora_listing` singular with no SEO plugin active: exactly ONE `<link rel="canonical">`, href == `get_permalink()` (core's `rel_canonical` removed). With `WPSEO_VERSION`/`RANK_MATH_VERSION` defined: Listora emits ZERO canonical AND does NOT `remove_action('wp_head','rel_canonical')`. `class-schema-generator.php:548-558`. Covered by Free `regression/schema-duplicate-canonical.md`. |
| D.og-locale-missing | #(deb68b6, Free) | Native OG output missing `og:locale` | With Listora's native OG active (no Yoast/Rank Math): the listing `<head>` has exactly one `<meta property="og:locale" content="...">` whose content == `get_locale()` with `-`→`_` (e.g. `en_GB`), positioned after `og:site_name`, HTML-attribute-escaped. `class-schema-generator.php:444`. Covered by Free `regression/og-locale-native-output.md`. |
| D.renewal-modal-error-aria-live | #(1535f00, Free) | Renewal-modal error not a pre-existing live region → screen reader silent on failure | When a renewal fails (insufficient credits / network error), the error `<p>` `[data-listora-renew-error]` carries `aria-live="assertive"` + `aria-atomic="true"`, server-rendered while hidden; on failure its `hidden` is removed and text set on the SAME element (announced). `templates/blocks/user-dashboard/tab-listings.php:558`. Covered by Free `regression/renewal-modal-error-aria-live.md`. |
| D.submission-dashboard-url | #(f623a39, Free) | Submission success "Go to Dashboard" hardcoded `home_url('/dashboard/')` → 404 on non-default Dashboard slug | Re-slug the Dashboard page; submit a NEW listing and edit an existing one. Each success card's "Go to Dashboard" href == `wb_listora_get_dashboard_url()` (registry-resolved, default `/my-dashboard/`), never `home_url('/dashboard/')`, and lands 200 (not 404). `submission.php:131,142`. Covered by Free `regression/submission-dashboard-url-registry.md`. |
| D.toggle-default-mismatch | #(050090f, Pro) | Fresh-activation feature defaults drifted across activator / registry / default-map | (Combo.) On fresh activation (no `wb_listora_pro_features` option): `wb_listora_pro_feature_enabled('comparison')` === true AND `('quick_view')` === true. The seeded option agrees with `wb_listora_pro_features_registry()` defaults for every key (no activator-vs-registry-vs-default-map drift); the init bootstrap path (option absent, deactivate→init) yields the same on-by-default set as the activator path. `class-activator.php:153-172` + `functions.php:29`. Covered by Pro `regression/feature-defaults-on-fresh-activation.md`. |
| D.quick-view-arrow-tap-target | #(263bb50, Pro) | Quick View gallery prev/next arrows 30px (below the 40px tap-target floor) | (Combo.) quick_view ON. On the frontend Quick View modal, computed width + height of `.listora-qv-gallery__nav` ≥ 40px (resolves to `var(--listora-tap-target, 44px)`, matching `.listora-qv-modal__close`). Holds in LTR AND RTL stylesheets. Covered by Pro `regression/quick-view-arrow-tap-target.md`. |
| D.invalid-data-wp-disabled | #(d65bc05, Pro) | Load More fired a second REST page fetch on a second click mid-fetch; markup used the no-op `data-wp-disabled` | (Combo.) infinite_scroll ON (Load More mode). While a fetch is in flight (`state.listoraProPagination.isLoadingMore===true`) the Load More button carries the native `disabled` attribute and a second click does NOT trigger a second `actions.listoraProLoadMore`/page fetch. Sentinel: markup uses `data-wp-bind--disabled`, NEVER `data-wp-disabled`. `class-infinite-scroll.php:231`. Covered by Pro `regression/load-more-disabled-while-loading.md`. |
| D.needs-budget-currency-symbol | #(6e723e2, Pro) | Reverse Listings admin budget column hardcoded `$` | (Combo.) reverse_listings ON. A need with `_listora_need_currency=EUR` renders budget `EUR 500 - EUR 1,000` (code prefix on BOTH bounds), not `$500 - $1,000`. Empty currency → `USD`. Zero budget → em-dash. `class-reverse-listings.php:782-799`. Covered by Pro `regression/needs-budget-currency-symbol.md`. |
| D.visibility-tab-not-gated-by-toggle | #(4048299, Pro) | "Visibility" settings tab shown even when coming_soon (the enforcing feature) was OFF | (Combo.) With coming_soon OFF, the "Visibility" tab is absent from the Pro settings sidebar nav-groups; with it ON the tab is present and `render_visibility_settings()` outputs the public/private/coming_soon radio group (`input[name="wb_listora_pro_visibility"]` × 3). `$tab_to_feature['visibility']='coming_soon'` in `class-pro-plugin.php`. Covered by Pro `regression/visibility-tab-gated-by-toggle.md`. |
| D.lead-form-status-aria-live | #(ff45301, Pro) | Lead-form result message not a pre-existing live region | (Combo.) lead_form ON. On a listing with the lead form, `.listora-lead-form__message` carries `aria-live="polite"` + `aria-atomic="true"` in the server-rendered DOM (present while hidden); after a successful AND a failed submission it is the element receiving the result text. `class-lead-form.php:375`. Covered by Pro `regression/lead-form-status-aria-live.md`. |
| D.coming-soon-dark-mode | #(3363bd4, Pro) | Coming Soon splash had no dark-mode rule | (Combo.) `wb_listora_pro_visibility=coming_soon`, coming_soon ON. As a non-admin visitor the splash inline `<style>` includes `@media (prefers-color-scheme: dark)` setting body `#1a1a1a`/`#e0e0e0`, `.coming-soon p` `#aaa`, `.coming-soon a` `#4a9eff`; light values (`#f7f7f7`/`#333`/`#666`/`#0073aa`) unchanged. Rendered HTML source contains `prefers-color-scheme: dark`. `class-coming-soon.php:147-151`. Covered by Pro `regression/coming-soon-dark-mode.md`. |
| D.wp-element-button-customer-facing | #(700843f, Pro) | Customer-facing Pro buttons carried `wp-element-button` (theme/core styling fought design tokens) | (Combo.) After rendering any customer-facing Pro surface (lead form, infinite-scroll Load More, advanced-search saved-search toggle/submit/cancel/delete + Saved Searches dashboard nav, pricing-plans coupon Apply, needs dashboard nav), NO rendered button carries `wp-element-button` while also carrying its canonical class (`listora-btn`/`listora-btn--*`, `listora-dashboard__nav-item`, `listora-plan-step__coupon-apply`). Admin pages (webhooks/badges/audit-log) EXEMPT. Covered by Pro `regression/no-wp-element-button-customer-facing.md`. |
| D.review-report-modal | #(ea6e027, Free) | listing-reviews "Report" used a native `prompt()` - inaccessible + CSP-blocked | On a listing detail with the listing-reviews block, click "Report" on a review. Logged-in: an accessible `role="dialog" aria-modal="true"` modal (`#listora-report-review-dialog[tabindex=-1]`) opens, focus moves INTO it, the Reason `<select>` is populated from `\WBListora\Admin\Report_Metabox::reasons()`; pick a reason + Submit → 200 + success toast + modal closes + focus returns to the Report button. Empty-reason Submit fires NO request. Guest → login modal (not the report dialog). Escape closes. ZERO `prompt(` in `view.js` (src + build); action lives in `src/interactivity/store.js` with the `isReportReviewModalOpen` getter. Covered by Free `regression/review-report-modal.md`. |
| D.claims-tab-pagination | #(fcbf0d1, Free) | Dashboard Claims tab hard-capped at 20 with no nav - claims 21+ unreachable | With > 20 claims for a member, open My Dashboard → Claims tab: a prev / "Page X of Y" / next nav renders below the list (`.listora-pagination`); Next reloads at `?tab=claims&claims_page=2` SSR-rendering claims 21-40; Previous returns to page 1; `?claims_page=999` clamps to the last page (200, not blank). `GET /listora/v1/dashboard/claims?page=2&per_page=20` accepts the args (page>=1; per_page 1-50; absint) and returns `{claims,total,pages,has_more}` with `total` from a dedicated `COUNT(*)`. `class-dashboard-controller.php` + `blocks/user-dashboard/render.php` + `templates/blocks/user-dashboard/tab-claims.php`. Covered by Free `regression/claims-tab-pagination.md`. |
| D.breadcrumb-trail-parity | #(b348c4e, Free) | Visual breadcrumb + JSON-LD BreadcrumbList built from divergent sources (root label/URL + type-URL mismatch) | On a listing detail with breadcrumbs ON (no SEO plugin owning schema): the visible trail (root "Directory", listing-type, primary-category, listing-title leaf) matches the JSON-LD BreadcrumbList name-for-name AND URL-for-URL. Root URL == `wb_listora_get_page_url('directory')` (the mapped Directory page), NOT `home_url('/')`. A listing whose category `get_term_link()` errors renders the category crumb as plain text (no broken `href`) in both surfaces. Both consumers call `Schema_Generator::get_breadcrumb_items()`. Google Rich Results Test: BreadcrumbList root points at the Directory page. `includes/schema/class-schema-generator.php` + `blocks/listing-detail/render.php`. Covered by Free `regression/breadcrumb-trail-parity.md`. |
| D.infinite-scroll-counter | #(51a14738, Pro) | Infinite-scroll counter read `state.results.length` (0 for SSR cards) → "0 of M" on first paint | (Combo.) infinite_scroll ON, a category with > per_page listings, Pagination = "Load More Button" (repeat for "Infinite Scroll"). First paint: the counter reads "N of M" where N == the SSR page-1 card count (`per_page`), NOT "0 of M". Each Load More grows N by the appended page size; the remaining "(X)" badge == `M - loadedCount` and decrements correctly. `loadedCount` getter = `state.pageTo + state.results.length` in `assets/js/infinite-scroll.js`. Covered by Pro `regression/infinite-scroll-counter.md`. |
| D.infinite-scroll-rel-links | #(b0106e19, Pro) | SEO rel-prev/next + noscript pagination advertised the UNFILTERED total + dropped filter params | (Combo.) infinite_scroll ON. On `/directory/?category=<cat>&location=<loc>` (a filtered set spanning > 1 page): every `<link rel="next"/"prev">` in `<head>` carries `category` + `location`; the advertised page count == the FILTERED result set, not the unfiltered directory total; the `<noscript>` pagination links preserve the filters. The unfiltered `/directory/` still advertises the directory total with no stray params. `get_active_filters()` + `get_filtered_total_pages()` in `includes/features/class-infinite-scroll.php`. Covered by Pro `regression/infinite-scroll-rel-links.md`. |
| D.webhooks-list-pagination | #(9f3afd9, Pro) | Outgoing Webhooks admin list had no pagination + a per-row N+1 last-delivery query | (Combo.) outgoing_webhooks ON, > 20 webhooks. `admin.php?page=listora-webhooks` shows 20 rows + a `paginate_links` prev/next nav + item count; `?paged=2` loads webhooks 21+ (no 500, no dup rows). "Last Delivery" column shows the latest delivery per row after the batch refactor (`get_last_deliveries()` - one grouped `SELECT MAX(id) ... GROUP BY webhook_id` on `idx_webhook`, ≤ 2 `webhook_log` queries for the whole list, never one-per-row). `class-outgoing-webhooks.php`. Extends `admin/05-outgoing-webhook.md`; covered by Pro `regression/webhooks-list-pagination.md`. |
| D.buddypress-tabs-pagination | #(d27ed6f, Pro; 10677e7, Free) | BP profile Listings + Reviews tabs hard-capped at 20, no total, no nav | (Combo, BuddyPress + buddy_press_integration ON.) A member with > 20 listings + > 20 reviews: My Listings shows pagination + the TRUE total (`found_posts`), `?lpage=2` shows the next set; My Reviews shows Previous/Next + "Page X of Y" via `?rpage=N`, the status reads the true review total (dedicated `COUNT(*)`, not capped at 20). Reviews query backed by Free's `idx_user_status_created (user_id, status, created_at)` composite index (Free migration 1.3.0). `class-buddy-press-integration.php` + `templates/bp/{listings,reviews}-loop.php` + Free `includes/db/class-migrator.php`. No new Free→Pro coupling pair. Covered by Pro `regression/buddypress-tabs-pagination.md`. |
| D.seo-page-theme-integrated | #(M20, Pro) | Programmatic SEO landing pages emitted a standalone HTML document - the active theme's header/nav/branding/footer were never used | (Combo.) seo_pages ON, a `{type}-in-{location}` combo with ≥ `get_min_listings()` listings (e.g. `healthcare-in-ny`). By DEFAULT the page renders INSIDE the theme: `#masthead`/`.site-header` + `#colophon`/`.site-footer` + `#page.site` present, `.listora-seo-page-wrap` nested inside `#page.site`, `<body>` carries `wp-theme-*` + `listora-seo-page` + `page-template-full-width`. `document.title` == the SEO title (via `pre_get_document_title`/`document_title_parts`). SEO preserved: `meta[name=description]`, `link[rel=canonical]`, exactly 2 JSON-LD (`ItemList` numberOfItems == count + non-empty `itemListElement`, `BreadcrumbList`). H1 + listing cards + breadcrumb + internal-link panels render. CSS is the ENQUEUED `assets/css/seo-pages.css` (handle href contains `seo-pages`) - ZERO inline `style=` attributes in the body markup; dark mode (`<html data-bx-mode="dark">`) flips the card/panel surfaces dark via Free tokens (card bg resolves to `--listora-bg-elevated`). Escape hatch: `add_filter('wb_listora_pro_seo_page_use_theme_template','__return_false')` restores the verbatim standalone document (no `#masthead`/`#colophon`/`#page.site`) while keeping meta + canonical + 2 JSON-LD + H1 + cards + the enqueued stylesheet. No PHP notice/fatal/`Array to string conversion` in debug.log (`_listora_address` array routed through `get_listing_address_line()`). `includes/features/class-seo-pages.php` (`render_page`/`render_theme_template`/`render_standalone`/`render_page_body`) + `assets/css/seo-pages{,-rtl}.css`. Covered by Pro `regression/seo-page-theme-integrated.md`. |
| D.webhook-credit-idempotency | (1.1.0, Pro) | Payment webhook could double-credit on retry/concurrent delivery (credits added BEFORE the payments INSERT, INSERT return ignored, non-atomic pre-check) and credited on ANY event ($event parsed but never branched - a charge.refunded payload still added credits) | (Combo.) Signed `payment.completed` (strict HMAC over `<ts>.<body>`) with a NEW txn → HTTP 200 `{success,credits,balance}`, exactly ONE `{prefix}payments` row + exactly ONE `{prefix}credit_ledger` row, balance +credits. **Duplicate** delivery (same gateway+txn) → 200 `{duplicate:true}`, balance/ledger show the credit **only ONCE** (verify via ledger/balance, not just payments). **Concurrent-race** dup (inject the colliding row via the `wb_listora_pro_before_payment_webhook` filter so the pre-check passes but the INSERT hits the UNIQUE constraint) → 200 `{duplicate:true}`, NO credit, 1 payments row - proves the insert-failure branch, not just the pre-check. **`charge.refunded`** (and `payment.failed` / `*.dispute`) → 200 `{received:true,ignored:true,event}`, NO credit, NO payments row. Record-before-credit: a payments row is written FIRST and is the authoritative guard (UNIQUE `idx_gateway_payment_unique` on `(gateway, gateway_payment_id)`, DB_VERSION 1.6.0); credits/plan-activation run only AFTER the durable row. Allowlist filterable via `wb_listora_pro_webhook_credit_events` (default `['payment.completed']`). `wb-listora-pro/includes/features/class-webhook-receiver.php::process()` + `includes/db/class-pro-migrator.php::ensure_payments_unique_index()`. No coupling-pair change. Covered by Pro `regression/webhook-credit-idempotency.md`. |
| D.seo-plugin-defer | #(Free + Pro) | Listora double-injected meta/canonical/OG/JSON-LD when a popular SEO plugin owns the page (duplicates); the M9 Yoast/Rank-Math guard only covered 2 plugins and was duplicated inline at each emitter; SEO-page grid also dropped unrated listings | ONE canonical detector `wb_listora_seo_plugin_active()` (Free `includes/class-features.php`, filterable via `wb_listora_seo_plugin_active`) detects Yoast (`WPSEO_VERSION`/`WPSEO_Options`), Rank Math (`RANK_MATH_VERSION`/`RankMath`), AIOSEO (`aioseo()`/`AIOSEO_VERSION`), SEOPress (`SEOPRESS_VERSION`/`seopress_get_service`). **With NO SEO plugin active:** a listing-detail singular has exactly ONE Listora JSON-LD + ONE OG/Twitter set + ONE canonical + ONE BreadcrumbList; the `{type}-in-{location}` SEO page (e.g. `healthcare-in-ny`) has ONE Listora meta description + canonical + ItemList + BreadcrumbList. **With ANY of the 4 active** (verify with Yoast - `wp plugin install wordpress-seo --activate`): Listora emits ZERO og/twitter/meta-description/canonical/JSON-LD on BOTH the listing-detail page AND the SEO page (only the SEO plugin's tags remain - no duplicates), while the SEO page CONTENT (theme chrome + H1 + listings grid) still renders. Restore env: deactivate + uninstall the SEO plugin. Routed emitters: Free `Plugin::output_schema()` (`class-plugin.php`), `Schema_Generator::output_og_tags()` + `output_canonical()` + `output_breadcrumbs()` (`class-schema-generator.php`); Pro `SEO_Pages::render_meta_tags()` + `render_schema()` (`class-seo-pages.php`). **Plus FIX 2:** the SEO-page grid (`SEO_Pages::query_listings()`) now LEFT-JOINs the rating meta via an OR meta_query (`rated` EXISTS + `unrated` NOT EXISTS), ordered by the `rated` clause DESC - rated listings sort first, unrated sort last but NEVER vanish (seed an unrated healthcare-in-NY listing → it appears in the grid + the count rises by 1). No coupling-pair change (Pro→Free is a documented function call, not a hook). Covered by Pro `regression/seo-plugin-defer.md`. |
| D.credits-sdk-composer-free | (1.1.0, Free) | A no-composer install (uploaded zip OR fresh `git clone` with no `composer install` / no submodule init) fataled the site - the Credits SDK was a gitignored submodule at `vendor/wbcom-credits-sdk` whose `Wbcom\Credits\` classes resolved only via composer's PSR-4, and `wb-listora.php` hard-loaded `vendor/autoload.php` | Simulate a no-composer zip: rename Free's `vendor/autoload.php` → `vendor/autoload.php.bak`. Load the site front-end + wp-admin (`admin.php?page=listora-settings`) + any credit-feature surface. Assert: HTTP 200, NO WSOD, and `wp-content/debug.log` has ZERO `Class Wbcom\Credits\… not found` / fatal entries. Assert the SDK booted from `libs/`: `WBCOM_CREDITS_SDK_PATH` ends with `libs/wbcom-credits-sdk` and `class_exists('\Wbcom\Credits\Gateways\Pricing')` (PSR-4 closure) + `class_exists('\Wbcom\Credits\Adapters\WooCommerceAdapter')` (eager map, non-PSR-4 filename) are both true. RESTORE `vendor/autoload.php` afterward. The SDK now lives committed + composer-free at `libs/wbcom-credits-sdk/` (mirrors `libs/edd-sl-sdk/`); its `wbcom-credits-sdk.php` self-registers a PSR-4 `spl_autoload_register` for `Wbcom\Credits\`; `wb-listora.php`'s `vendor/autoload.php` require is `file_exists`-guarded and Free's own classes load via `wb_listora_autoload`. No coupling-pair change. |
| D.email-template-palette-keys | (1.1.0, Pro) | `listing-paused.php` (+ latent twin `listing-resumed.php`) referenced palette keys `button_bg`/`button_text`/`link` that `Notifications::get_palette()` never returns → 3 PHP Warnings per send; on the money path since the refund-after-activation rollback fires `wb_listora_pro_listing_paused` | (Combo, WP_DEBUG on.) Render `emails/listing-paused.php` (via `wb_listora_get_template_html` with the `on_paused()` view-data + `merge_shared_envelope(...,'listing_paused','neutral')`) and `emails/listing-resumed.php` (the `on_resumed()` contract incl. `credits_charged`/`new_balance` + `'listing_resumed','success'`): BOTH render with **0** `Undefined array key`/`Undefined variable` warnings. No Pro email template references `button_bg`/`button_text`/`link` (house pattern: buttons use `$colors['primary']` + `$colors['white']`, links `$colors['primary']`). Triggering the paused email via refund rollback adds 0 new template warnings to debug.log. `wb-listora-pro/templates/emails/listing-{paused,resumed}.php`. Covered by Pro `regression/email-template-palette-keys.md`. |

Rule: every customer-visible fix adds a D row in the same PR. After 2 clean releases, a D row graduates into C/E.

---

## E - Pro-only flows (combo mode)

Each Pro extension gets a customer contract. Run only when `wb-listora-pro` is active. Pro has **29 feature toggles** (`wb_listora_pro_features_enabled.*`). Section E walks every customer-facing toggle plus the always-on infrastructure ones.

For toggle-able features, every E row has TWO assertions:
- **Toggle ON** - feature renders / works as documented.
- **Toggle OFF** - feature absent (no PHP fatal, no JS error, no orphan UI element, no leftover REST route).

Set toggles via `Settings → Pro Features` admin page OR `wp option patch update wb_listora_pro_features_enabled <key> 1|0`.

### E.compare (toggle: comparison)
**What to verify:** Pro's comparison block on `/compare-listings/?compare=ID,ID` renders a side-by-side table for 2-4 listings. Empty state with 0-1 selected. "Remove" button on each column updates URL + table. Floating compare bar persists via localStorage across page navigations. Toggle off → block server-renders nothing; the auto-created Compare Listings page shows the empty Gutenberg fallback.

### E.credit-system (toggle: credit_system, always-on infra)
**What to verify:** with Credits feature enabled, a member visiting `/dashboard/#credits` sees their balance, a transaction history table, and (where direct-pack purchase is configured) a Buy Credits button. Buying via Stripe / PayPal / WooCommerce flow correctly adds credits and writes a `listora_credit_log` row. Admin can manually add credits via Pro admin → Credit Transactions.

### E.pricing-plans (toggle: pricing_plans, always-on infra)
**What to verify:** Listora → Pricing Plans CPT admin page lists plans. Submission wizard's Plan step shows enabled plans with correct credit costs. Selecting a paid plan and submitting deducts credits at the documented rate. `wb_listora_listing_expiration_date` filter sets expiry per plan (Pro listener overrides Free's default).

### E.coupons (toggle: coupons)
**What to verify:** admin can Create Coupon at `admin.php?page=listora-coupons&coupon_action=add` - page renders form, NOT blank (per 2026-05-09 fix `de4b79b`). Coupon redeems on a paid plan and reduces the credit deduction. Edit and Delete also work. Generate Code utility produces unique uppercase codes.

### E.outgoing-webhooks (toggle: outgoing_webhooks)
**What to verify:** admin → Webhooks page - admin adds a webhook URL with selected events (`listing.approved`, `listing.rejected`, `listing.expired`, `claim.submitted`, etc.). Triggering an event delivers a POST to the URL with the documented payload (signature header included). Delivery log shows status code per attempt. Failed deliveries retry per Action Scheduler.

### E.webhook-receiver (toggle: webhook_receiver, inbound payments)
**What to verify:** with strict HMAC mode (default), POST to `/wp-json/listora/v1/webhooks/payment` requires `X-Listora-Signature` + `X-Listora-Timestamp` headers - missing or invalid → 401 + audit-log row. Replay of a valid payload → 401 `replay_detected`. Valid Stripe-style delivery → 200 + credits credited + `wp_listora_payments` row. Legacy mode (option=0) accepts shared-secret header path.

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
**What to verify:** for review-criteria-configured listing types, the review form shows multiple star inputs (e.g. "Food", "Service", "Ambiance" for Restaurant) - submitting persists per-criterion ratings AND computes a correct overall.

### E.photo-reviews (toggle: photo_reviews)
**What to verify:** the review form accepts up to N images. Submission writes `listora_review_votes` photo refs. Reviews tab on the detail page renders thumbs that lightbox.

### E.coming-soon (toggle: coming_soon)
**What to verify:** with Visibility = "Coming Soon", non-admin visitors are redirected to a coming-soon template; admins bypass via `manage_listora_settings`. With Visibility = "Private", non-admins redirect to login. Toggle off → site fully public regardless of Visibility setting.

### E.audit-log (toggle: audit_log)
**What to verify:** Pro admin → Audit Log page lists admin/REST mutations with filterable columns (user, action, target, time). CSV export works. Daily `audit_cleanup` cron prunes per retention. Toggle off → admin page hidden, no new rows written.

### E.badges (toggle: badges)
**What to verify:** admin → Badges page - admin creates a badge (label, icon, criteria). Auto-assignment fires when a listing meets the criteria (e.g. "Verified" after claim approval, "Top-rated" on rating ≥4.5). Listing card + detail render the badge pill. Toggle off → no badges render frontend-side.

### E.verification (toggle: verification)
**What to verify:** Verification meta-box on Listing edit screen surfaces a status display. Owner can request verification from dashboard; admin reviews + approves → "Verified" badge auto-assigned. Listener on `wb_listora_listing_claimed` updates search-index `is_claimed` column.

### E.moderator (toggle: moderator)
**What to verify:** admin promotes subscriber → moderator via Pro admin → Moderator. New `listora_moderator` role granted moderation caps. Moderator visiting `/dashboard/#moderator-queue` (or the moderator-queue block on a public page) sees ONLY items assigned to them. Bulk reassign from admin → receiving moderator gets email. Moderator-only audit log endpoint clamps `user_id` filter to the requesting moderator.

### E.needs (toggle: needs_dashboard_tab + reverse_listings)
**What to verify:** Pro's Needs CPT - `/post-need/` form submits a need; `/browse-needs/` lists them; vendors respond via Need Response REST endpoint; need-creator accepts/rejects responses. Member dashboard "My Needs" tab visible.

### E.reverse-listings (toggle: reverse_listings)
**What to verify:** admin → Reverse Listings page lists need posts. Admin creates a listing in response to a need. Need-creator notified.

### E.notification-digest (toggle: notification_digest)
**What to verify:** member can opt into digest mode (instant / digest / digest_urgent) in dashboard preferences (or via Pro Settings). With digest mode on, non-urgent notifications batch into a daily email at 9am instead of firing instant. `wb_listora_send_notification` filter intercepts and queues. Toggle off → all notifications instant.

### E.seo-pages (toggle: seo_pages)
**What to verify:** Pro Settings → SEO Pages tab visible (only when toggle on). Admin enables landing-page generators (e.g. "Restaurants in {city}"). Generated pages render with `<title>`, meta description, schema.org markup, og: tags. Sitemap entries include the generated URLs.

### E.white-label (toggle: white_label)
**What to verify:** Pro Settings → White Label tab - admin sets custom plugin name + hide-author-info. wp-admin → Plugins page shows the custom name (rather than "WB Listora Pro"). Toggle off → reverts to default "WB Listora Pro" branding.

### E.infinite-scroll (toggle: infinite_scroll)
**What to verify:** with toggle on, listing-grid block on `/listings/` auto-loads next page on scroll-near-bottom (no pagination clicks). Toggle off → standard pagination renders.

### E.quick-view (toggle: quick_view)
**What to verify:** with toggle on, listing card surfaces a "Quick View" affordance that opens a modal with summary (image, title, rating, snippet) without leaving the listings page. Toggle off → no quick-view button.

### E.field-mapper (toggle: field_mapper, always-on infra)
**What to verify:** admin → Tools → Field Mapper - when importing CSV, the column-mapping UI auto-detects column meaning (Name, Address, Phone, Hours) and lets admin override. Mapping persists per-import.

### E.migration (toggle: migration, always-on infra)
**What to verify:** admin → Tools → Migrate from competitor - at least one adapter (e.g. GeoDirectory) imports a sample listing batch with all geo + meta + categories preserved. `_listora_migrated_from` postmeta set via `Migrated_From_Tracker`.

### E.services-pro (toggle: services_pro, always-on infra)
**What to verify:** with services_pro on, listing detail Services tab cards expose a "Book" CTA. Booking flow either deep-links to a Pro booking template or surfaces an inline form (per configuration). `wb_listora_after_service_detail` action fires inside the services foreach.

### E.buddy-press-integration (toggle: buddy_press_integration, auto when BP active)
**What to verify:** with BuddyPress active + toggle on, listing-detail review-author links route to BP profile (`bp_core_get_user_domain`) instead of WP author archive. Toggle off → links resolve to default WP author URL.

### E.pro-license
**What to verify:** Pro Settings → License tab - invalid key produces clear error (no fatal). Valid key marks status = active. Deactivate → status = inactive, license-gated features fail-soft (no fatal). Reactivate restores. Expired key disables license-gated features but Free remains fully functional.

### E.pro-setup-wizard
**What to verify:** Pro's setup wizard (`admin.php?page=listora-pro-setup`) runs after Pro activation. Walks through license activation → feature defaults → done. Re-running is idempotent.

### E.pro-admin-pages
**What to verify:** every Pro admin page renders without fatal. There is no standalone Pro "dashboard" slug - Pro UI lives inside the shared `listora` menu via these submenus + Settings tabs:

Always-on submenus (anyone with `manage_listora_settings`):
- `admin.php?page=listora-settings` (Settings, tabs include Pro Features, License, Credits, Import/Export)
- `admin.php?page=listora-transactions` (credit transactions log)
- `admin.php?page=listora-analytics` (analytics dashboard)
- `admin.php?page=listora-pro-setup` (Pro setup wizard, first-run focus)

Toggle-gated submenus - appear ONLY when the matching `wb_listora_pro_features` key is `true`:
- `admin.php?page=listora-webhooks` - requires `outgoing_webhooks` toggle (default OFF)
- `admin.php?page=listora-badges` - requires `badges` toggle (default ON)
- `admin.php?page=listora-coupons` - core feature, always on
- `admin.php?page=listora-moderators` - core feature, always on
- `admin.php?page=listora-audit-log` - requires `audit_log` toggle (default ON)
- `admin.php?page=listora-needs` - requires `reverse_listings` toggle (default OFF)

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
**What to verify:** `admin.php?page=listora-settings&tab=pro-features` lists all 29 Pro feature toggles with their defaults. There is no standalone `wb-listora-features` page - the toggles live as a tab inside Settings. Toggling ON/OFF persists in the `wb_listora_pro_features` option. Subsequent page-loads honor the toggle (feature class loads/unloads via `Feature_Manager::load_features()`). Notice + cache flush on save.

### E.pro-version-lockstep
**What to verify:** `wp eval 'echo "free:" . WB_LISTORA_VERSION . " pro:" . WB_LISTORA_PRO_VERSION;'` - both constants are identical. Drift = halt + Basecamp card.

### E.pro-dependency-guard
**What to verify:** deactivating Free while Pro stays active - admin notice "WB Listora Pro requires WB Listora" appears. Pro bails out without fataling. Reactivating Free restores Pro automatically.

### E.pro-cross-coupling (29 Free→Pro pairs)
**What to verify:** spot-check at least 4 of the 29 documented coupling pairs from `audit/derived/cross-plugin-coupling.json`:
- `wb_listora_listing_claimed` → Pro's `Verification::on_listing_claimed` updates search-index.
- `wb_listora_listing_expiration_date` filter → Pro's `Pricing_Plans::filter_listing_expiration_date` overrides.
- `wb_listora_register_pages` action → Pro registers `compare` page via the helper.
- `wb_listora_after_reset_settings` + `wb_listora_reset_option_keys` → Pro options purged on Reset Settings.

---

## F - Cross-browser, RTL, accessibility

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
- Modal toolkit (claim/share/login) - DevTools console snippet:
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

## G - Post-release monitoring (first 24h)

- Watch `wp-content/debug.log` for new fatals/warnings.
- `wp action-scheduler list --status=failed --group=wb_listora` - should be empty.
- `wp action-scheduler list --status=failed --group=wb_listora_pro` - should be empty.
- Support tickets / Slack #listora-support channel for breakage reports.
- Activity-signal drops: listing-create, review-submit, favorite-add daily counts vs prior week.
- New `webhook_auth_rejected` audit-log rows beyond baseline noise indicate misconfigured customer integrations or attack traffic.

---

## Failure protocol

1. Screenshot every failure: `browser_take_screenshot({ filename: "fail-<id>.png" })`.
2. Triage - `from` (our bug) vs `for` (theme / other plugin / browser limit / legacy data).
3. Record in `failures[]` with `{ id, origin, triage_note, expected, actual, url, screenshot }`.
4. Never halt - collect ALL failures in one pass.
5. Emit Basecamp draft per failure with origin populated.

Triage is Sonnet's job; fix-or-document is the calling session's (Opus's) job.

## Step ID format

`<Section>.<persona>.<feature>` e.g. `C.member.submit-listing`. D rows: `D.<descriptor>`. E rows: `E.<extension>`.

## Maintenance

Every customer-visible bug fix that lands must add a D row in the same PR. After 2 clean releases of a D row, graduate it into the matching C/E flow row (the bug class is unlikely to recur). Every 6 months, compare this runbook against Jetonomy's `docs/qa/AGENT_SMOKE_RUNBOOK.md` to catch drift in the portfolio's QA model.
