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

---

## A — Fresh install

### A1 — Activation and first-request routing
**What to verify:** after `wp plugin deactivate wb-listora && wp plugin activate wb-listora`, the primary front-end routes respond on the FIRST request without re-saving Permalinks. Activator's `flush_rewrite_rules()` must defer to `init` priority 99 (per the 2026-05-07 fix that resolved the textdomain cascade).
**Why it matters:** rewrite-flush-on-activation regressions break first impressions. We've shipped this fix once already (commit `5b4840f`); regressions here are real.
**Acceptance:** `/listings/`, `/add-listing/`, `/dashboard/` all return HTTP 200; `wp rewrite list | grep listora` shows the listing CPT permalink rules.

### A2 — Database schema is in place
**What to verify:** all 11 listora_-prefixed tables exist (`listora_geo`, `listora_search_index`, `listora_field_index`, `listora_reviews`, `listora_review_votes`, `listora_favorites`, `listora_claims`, `listora_hours`, `listora_analytics`, `listora_payments`, `listora_services`). The `wb_listora_db_version` option matches `WB_LISTORA_VERSION`.

### A3 — Pro pairs cleanly (combo mode only)
**What to verify:** activating `wb-listora-pro` on top of `wb-listora` does not fatal; Pro-only tables (`listora_credit_log`, `listora_audit_log`, `listora_saved_searches`) are created; both version constants agree (lockstep). All 12 architecture invariants pass via `bin/architecture-checks.sh`.

### A4 — Setup wizard auto-redirects on first activation
**What to verify:** the `wb_listora_show_wizard_redirect` transient sets at activation; first admin pageload as a `manage_options` user redirects to `admin.php?page=listora-setup` and the transient clears.

### A5 — Essential pages auto-create
**What to verify:** activator creates Directory (`/listings/`), Add Listing (`/add-listing/`), and My Dashboard (`/dashboard/`) pages — idempotent, won't duplicate if they already exist with matching block content.

---

## B — Upgrade from previous version

### B1 — Migration is silent, data is intact
**What to verify:** upgrading from the last released stable to current build completes with zero new debug.log entries during the activation request. Pre-existing listings still render. Search index counters stay accurate (`SELECT COUNT(*) FROM wp_listora_search_index` matches the published-listing count from `wp_posts`).

### B2 — Settings format migration
**What to verify:** options under `wb_listora_settings` are merged not replaced when new keys are added. Editing one tab on Settings page does not drop values from a different tab.

---

## C — Core customer flows

Persona ladder: Anonymous → Subscriber/Customer → Contributor (submitter) → Editor → Admin. Test desktop 1280px and mobile 390px where the UI differs.

### C.anon.directory-home
**What to verify:** `/listings/` (and the homepage if listora-grid is the front block) renders for a logged-out visitor with real listing cards, working type tabs, and a search bar that returns results.

### C.anon.listing-detail
**What to verify:** every public template (single listing detail) renders without fatal for an anonymous visitor. Auth-gated actions (Save / Claim / Compare) cleanly prompt login rather than failing 403. URLs to test: at least 5 distinct listing slugs.

### C.anon.search
**What to verify:** search filters (keyword + location + type tabs + date filter + per-type checkboxes + dropdowns) all narrow the result set; the active-filter-count badge matches the user's perceived count of selected filters (post-2026-05-08 fix).

### C.anon.empty-state
**What to verify:** a category page with zero listings (e.g. `/business/` if no Business listings exist) shows the canonical `.listora-card--empty` card with icon + "No listings found" + "Clear All Filters" CTA — not a blank space.

### C.member.submit-listing
**What to verify:** a Contributor can complete the 6-step submission wizard end-to-end (Type → Basic Info → Details → Media → Plan → Preview): listing persists with `pending_verification` or `pending` status, fires `wb_listora_listing_submitted` action, and lands in the user's dashboard "My Listings" tab. Featured image upload + gallery picker open the WP media frame for both logged-in members and (where guest-submission is enabled) anonymous visitors.

### C.member.business-hours-picker
**What to verify:** Business / Hotel / Restaurant types show the Business Hours field in Details step. Clicking any time input opens a flatpickr time-picker (24h, 15-min increments) — works identically on Chrome AND Firefox (the round-2 flatpickr fix from 2026-05-08 covers Firefox's native-spinner gap).

### C.member.dashboard
**What to verify:** `/dashboard/` shows a 2-column sidebar+main layout (260px sidebar at 1280px). Tabs (My Listings, Reviews, Favorites, Claims, Credits, Profile, My Needs, Analytics) navigate via URL hash. Stats cards render real counts. Edit-listing from a row deep-links into the submission wizard with prefilled data.

### C.member.review-create
**What to verify:** a logged-in member writes a review on a listing they don't own, submits, and the review appears immediately in the Reviews tab. Helpful-vote button increments. Listing owner can reply via dashboard inline-reply form (post-2026-04-30 fix `e01486b`).

### C.member.review-toggle-services
**What to verify:** services-tab description toggle (Details button) expands/collapses the description without a page reload (post-2026-05-09 fix `c382a86`). Tested on the listing detail Services tab.

### C.member.favorites
**What to verify:** save/unsave a listing from the card AND from the detail page. Favorites count on the heart button updates client-side. The favorite appears in dashboard Favorites tab. Unsave from the dashboard removes it everywhere.

### C.member.compare (combo)
**What to verify:** "Add to Compare" on 2-4 listings navigates to `/compare-listings/?compare=ID,ID,ID` with a populated side-by-side table. Empty state shows with 0-1 listings selected.

### C.admin.plugin-pages
**What to verify:** every plugin admin page renders without PHP Notice/Warning/Fatal:
- Listora (dashboard widget on /wp-admin/)
- Listora → Listings (CPT list table)
- Listora → Email Log
- Listora → Settings (every tab — General / Submission / Search / Maps / Notifications / Advanced / Features)
- Listora → Setup Wizard (revisit URL)
- Listora → Health Check redirect (loads Settings → Advanced)

### C.admin.crud
**What to verify:** Listing CPT can be created via `/wp-admin/post-new.php?post_type=listora_listing` — even when "Days before a new listing expires" setting is non-zero (post-2026-05-08 verification: this was the cannot-reproduce repro for card #9857011539). Services meta box supports Photo upload (post-2026-05-09 fix `5eb3b33`).

### C.admin.settings-merge
**What to verify:** editing one Settings tab and saving does NOT drop values from another tab. Specifically: change Submission tab's "Days before a new listing expires" → save → reload Search tab — Search tab values intact.

### C.notifications
**What to verify:** `wb_listora_listing_status_changed` action fires once per actual transition (per the 2026-04-30 fix `0aa62ca`). Approve / reject / expire emails reach the listing author. Email Log admin page records each send.

### C.cron
**What to verify:** all 6 cron jobs are scheduled after activation per Action Scheduler (NOT WP-Cron):
- `wb_listora_expire_listings`
- `wb_listora_cleanup_drafts`
- `wb_listora_send_expiry_reminders`
- `wb_listora_rotate_featured_listings`
- `wb_listora_cleanup_email_verification`
- `wb_listora_cleanup_notification_log`

`wp action-scheduler list` (or equivalent) shows all 6 in the `wb_listora` group.

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
| D.metabox-fields-mergedupdate | n/a — long-standing | Settings tabs reset on save | Settings → Submission → change "Days before a new listing expires" → Save. Switch to Search tab. Assert: Search tab values still set. |

Rule: every customer-visible fix adds a D row in the same PR. After 2 clean releases, a D row graduates into C/E.

---

## E — Pro-only flows (combo mode)

Each Pro extension gets a customer contract. Run only when `wb-listora-pro` is active.

### E.compare
**What to verify:** Pro's comparison block on `/compare-listings/?compare=ID,ID` renders a side-by-side table for 2-4 listings. Empty state with 0-1 selected. "Remove" button on each column updates URL + table. Floating compare bar persists via localStorage across page navigations.

### E.credits
**What to verify:** with Credits feature enabled, a member visiting `/dashboard/#credits` sees their balance, a transaction history table, and (where direct-pack purchase is configured) a Buy Credits button. Buying via Stripe / PayPal / WooCommerce flow correctly adds credits and writes a `listora_credit_log` row.

### E.pricing-plans
**What to verify:** Listora → Pricing Plans CPT admin page lists plans. Submission wizard's Plan step shows enabled plans with correct credit costs. Selecting a paid plan and submitting deducts credits at the documented rate.

### E.coupons
**What to verify:** admin can Create Coupon at `admin.php?page=listora-coupons&coupon_action=add` — page renders form, NOT blank (per 2026-05-09 fix `de4b79b`). Coupon redeems on a paid plan and reduces the credit deduction.

### E.outgoing-webhooks
**What to verify:** a configured webhook URL receives POST on listing approve / reject / expire / claim. Body matches the documented schema.

### E.lead-form
**What to verify:** sidebar lead-form on a Business / Real Estate listing accepts name+email+message, fires reCAPTCHA v3 if configured, sends notification email to the listing author.

### E.google-maps
**What to verify:** with Google Maps key configured, the listing-map block uses Google tiles instead of OSM. Submission wizard's address autocomplete uses Google Places.

### E.analytics
**What to verify:** anonymous + authenticated visitors trigger view + click events. Owner dashboard Analytics tab shows totals, CTR, favorites count.

### E.multi-criteria-reviews
**What to verify:** for review-criteria-configured listing types, the review form shows multiple star inputs (e.g. "Food", "Service", "Ambiance" for Restaurant) — submitting persists per-criterion ratings AND computes a correct overall.

### E.photo-reviews
**What to verify:** the review form accepts up to N images. Submission writes `listora_review_votes` photo refs. Reviews tab on the detail page renders thumbs that lightbox.

### E.coming-soon
**What to verify:** with Visibility = "Coming Soon", non-admin visitors are redirected to a coming-soon template; admins bypass via `manage_listora_settings`. With Visibility = "Private", non-admins redirect to login.

### E.needs (combo)
**What to verify:** Pro's Needs CPT — `/post-need/` form submits a need; `/browse-needs/` lists them; vendors respond via Need Response REST endpoint; need-creator accepts/rejects responses.

---

## F — Cross-browser, RTL, accessibility

### F.chromium
Already covered by Sections A-E.

### F.firefox-desktop
Chromium-only Playwright cannot walk these. Populate `manual_required[]`:
- Submission wizard Business Hours flatpickr opens on click (D.business-hours-firefox repro).
- listing-map popups feature image renders.
- Dashboard responsive breakpoint at 768px.

### F.safari-ios
- Sticky bottom CTA bar on listing detail (Call / Visit / Save) doesn't overlap WP admin bar.
- Submission wizard step transitions don't trigger viewport jumps.

### F.rtl
**What to verify:** with `wp option set WPLANG ar` (or equivalent RTL locale), every primary template renders right-to-left. Search bar location field flips. Card meta rows (rating ★ → on the right). Calendar block week starts from right.

### F.a11y
**What to verify:** keyboard tab order through directory grid → card → save button → next card. Visible focus rings. Icon-only buttons have `aria-label`. Submission wizard step indicator uses `aria-current="step"`.

---

## G — Post-release monitoring (first 24h)

- Watch `wp-content/debug.log` for new fatals/warnings.
- `wp action-scheduler list --status=failed --group=wb_listora` — should be empty.
- Support tickets / Slack #listora-support channel for breakage reports.
- Activity-signal drops: listing-create, review-submit, favorite-add daily counts vs prior week.

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
