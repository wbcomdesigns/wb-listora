# Agent Smoke Runbook - WB Listora

**Audience:** a browser-capable agent (Claude Sonnet or equivalent) with Playwright MCP + WP-CLI Bash access, OR a human QA person with the same access. Both should be able to execute every step.

## How to read this runbook

Each C and E step describes a **customer contract**: what the feature promises, why it matters, the surfaces it touches, and what "working" looks like. It does NOT prescribe the exact Playwright calls, selectors, REST paths, or DB queries. Read the relevant plugin code, pick the right mechanism, and verify the contract.

D rows stay specific - those are repros of past incidents; the exact fixture IS the contract.

### Walk the CORE set first, always

This runbook is larger than one agent session. The 2026-08-11 combo walk finished
14 passed / 3 failed / **133 skipped** - roughly 10% coverage - because it started
at A1 and ran out of room. Sections B and F executed zero rows.

A partial walk is legitimate, but only if it spends its budget on the rows most
likely to be broken. So each step is tagged:

- **`[CORE]`** - must run in every walk, in this order, before anything else.
  These are the rows where a regression is customer-visible and likely: money,
  data that renders, permissions, and the cross-cutting checks above.
- untagged - run when budget remains, in section order.

If the walk cannot finish, that is fine and expected: report the honest
`skipped` counts. What is NOT acceptable is skipping a `[CORE]` row while
running untagged ones, or reporting a partial walk as a green gate.

**A partial walk is never a release gate.** `bin/build-release.sh` checks
failures and debug-log entries, not coverage, so a thin walk that happens to
find nothing will open the gate. Before tagging, confirm every `[CORE]` row
actually ran.

### What the release gate checks (emit these in the report)

`bin/build-release.sh` runs `bin/smoke-coverage-gate.py` against the report, and
it fails CLOSED - a report missing these fields cannot open the gate, because a
missing field is indistinguishable from a walk that skipped the work.

```jsonc
"core_rows":      { "total": 16, "executed": 16, "not_executed": [] },
"release_d_rows": { "version": "1.5.0", "executed": [...], "not_executed": [] }
```

The gate refuses to package when:

1. **any section executed zero rows** - the original failure mode (a walk ran
   A-through-C and never touched upgrade or cross-browser, and the gate opened);
2. **any `[CORE]` row never ran** - the must-run set, checked by name rather
   than by count, so "15 of 16" cannot pass;
3. **any D row added for THIS release never ran** - these are the guards written
   for the bugs this release fixed, so skipping them skips the only evidence
   that the build is better than the last one;
4. **fewer than 15% of rows ran** - a floor for the degenerate case.

Note what is NOT checked: the ratio of executed to skipped. That was the v1 rule
and it was wrong in a way worth remembering - section D grows by a row on every
bug fix, forever (1.5.0 added 11), so a ratio gets harder to satisfy each
release purely because the regression suite got better. The pressure that
creates is toward `--skip-browser-smoke`, and a gate people route around
protects nothing.

### The CORE set

| Order | Rows | Why it leads |
|---|---|---|
| 1 | Cross-cutting checks 1-10 | They apply to every page and cost nothing extra. Checks 7-10 exist because each caught a shipped bug. |
| 2 | C.member.* credit flows + D credit rows | Money. Wrong numbers here are the most expensive failure in the product, and the minor-units class hid behind screens that looked fine. |
| 3 | Any screen with a counter next to a list | Cross-cutting check 8. Cheap, and it has caught two separate shipped bugs. |
| 4 | Admin notices on Settings screens | Cross-cutting check 9. Invisible-but-present shipped three times. |
| 5 | C.anon.* directory + listing detail | The most-visited surfaces on a live site. |
| 6 | S1 version lockstep + S REST permission rows | A lockstep or auth break affects every install at once. |

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
- Front-end base slugs: `/listings/`, `/add-listing/`, `/compare-listings/` (Pro)
- **Resolve the dashboard URL, never assume it.** Use
  `wp eval 'echo wb_listora_get_dashboard_url();'` - the page is registry-resolved
  and site-specific (`/my-listings/` on the dev site, `/my-dashboard/` by default).
  Row `D.submission-dashboard-url` exists precisely because hardcoding
  `/dashboard/` 404s on any site that re-slugged it, and this runbook used to
  hardcode it in nine places. On the dev site `/dashboard/` is a leftover
  Directorist migration fixture holding an unrendered shortcode; a walk that
  assumes the slug reports that page as a plugin failure.
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

> Two of these statements named columns that do not exist (`comment_content` on
> `reviews`, `message` on `claims`; the real columns are `content` and
> `proof_text`). They failed silently as `WordPress database error` - neither a
> fatal nor a warning - so **cleanup has never actually removed smoke reviews or
> claims**, and every walk has been leaving fixtures behind for the next one.
> Corrected 2026-08-12. If you add a statement here, check the column against
> `SHOW COLUMNS` first: this is the same defect class as the Favorites
> `ORDER BY id` and the Pro webhook `rating`, and it is invisible without
> cross-cutting check 7.

```bash
wp --path="/Users/varundubey/Local Sites/directory/app/public" eval '
global $wpdb;
$prefix = $wpdb->prefix . "listora_";
$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type = \"listora_listing\" AND post_title LIKE \"Smoke %\"" );
$wpdb->query( "DELETE FROM {$prefix}reviews WHERE content LIKE \"Smoke %\"" );
$wpdb->query( "DELETE FROM {$prefix}claims WHERE proof_text LIKE \"Smoke %\"" );
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
7. **No database errors.** `grep "WordPress database error" wp-content/debug.log` must return nothing new after every page. This is called out separately from the debug-log protocol because a DB error is neither a "fatal" nor a "warning" - it is logged at neither level, so a walk that only classifies fatals and warnings sails past it. The 1.5.0 Favorites bug was exactly this: `ORDER BY id` on a table with no `id` column, query silently returning zero rows, error visible only here.
8. **Counters must agree with what they count.** Wherever a number and the thing it counts appear on one screen - a nav badge beside a list, a review headline above its reviews, a pager beside its rows, a balance beside its transactions - assert they agree. Disagreement is the single highest-yield signal in this product: the Favorites tab showed "32" in the badge and "No saved listings" in the panel, and the review headline said "5 reviews" above a list of 4. Both shipped because each half was checked alone.
9. **Visible means computed-visible.** For anything a customer is supposed to SEE, assert `getComputedStyle(el).display !== 'none'` and `visibility !== 'hidden'` **on the element itself**. Presence in the DOM, a passing grep of the HTML, or `innerText` on an ancestor all return success for an element sitting at `display: none`. Three admin-notice cards were signed off "browser-verified" this way and every one shipped invisible.
10. **Translated means rendered-translated.** When checking i18n, read what the BROWSER received, not the catalogue. A correct `.po` and `.mo` can still render English because WordPress 6.5+ prefers `.l10n.php`, and a stale one silently shadows both. Switch the site locale and assert on the rendered string (matching IDs/classes, never visible text).

---

## A - Fresh install

### A1 - Activation and first-request routing  `[CORE]`
**What to verify:** after `wp plugin deactivate wb-listora && wp plugin activate wb-listora`, the primary front-end routes respond on the FIRST request without re-saving Permalinks. Activator's `flush_rewrite_rules()` must defer to `init` priority 99 (per the 2026-05-07 fix that resolved the textdomain cascade).
**Why it matters:** rewrite-flush-on-activation regressions break first impressions. We've shipped this fix once already (commit `5b4840f`); regressions here are real.
**Acceptance:** `/listings/`, `/add-listing/` and the resolved dashboard URL all return HTTP 200; `wp rewrite list | grep listora` shows the listing CPT permalink rules.

### A2 - Database schema is in place  `[CORE]`
**What to verify:** all 11 listora_-prefixed tables exist (`listora_geo`, `listora_search_index`, `listora_field_index`, `listora_reviews`, `listora_review_votes`, `listora_favorites`, `listora_claims`, `listora_hours`, `listora_analytics`, `listora_payments`, `listora_services`). The `wb_listora_db_version` option matches `WB_LISTORA_VERSION`. Engine on every table is `InnoDB`.

### A3 - Pro pairs cleanly (combo mode only)
**What to verify:** activating `wb-listora-pro` on top of `wb-listora` does not fatal; Pro-only tables (`listora_credit_log`, `listora_audit_log`, `listora_saved_searches`) are created; both version constants agree (lockstep). All 12 architecture invariants pass via `bin/architecture-checks.sh`.

### A4 - Setup wizard auto-redirects on first activation
**What to verify:** the `wb_listora_show_wizard_redirect` transient sets at activation; first admin pageload as a `manage_options` user redirects to `admin.php?page=listora-setup` and the transient clears.

### A5 - Essential pages auto-create  `[CORE]`
**What to verify:** activator creates Directory (`/listings/`), Add Listing (`/add-listing/`), and My Dashboard (resolved via `wb_listora_get_dashboard_url()`) pages - idempotent, won't duplicate if they already exist with matching block content.

### A6 - Default capabilities + roles  `[CORE]`
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

### C.anon.directory-home  `[CORE]`
**What to verify:** `/listings/` (and the homepage if listora-grid is the front block) renders for a logged-out visitor with real listing cards, working type tabs, and a search bar that returns results. Pagination renders when results exceed per_page.

### C.anon.listing-detail  `[CORE]`
**What to verify:** every public template (single listing detail) renders without fatal for an anonymous visitor. Auth-gated actions (Save / Claim / Compare) cleanly prompt login rather than failing 403. URLs to test: at least 5 distinct listing slugs across different listing types (Business, Restaurant, Hotel, Real Estate, Event).

### C.anon.search-facets  `[CORE]`
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

### C.member.submit-listing  `[CORE]`
**What to verify:** a Contributor can complete the 6-step submission wizard end-to-end (Type → Basic Info → Details → Media → Plan → Preview): listing persists with `pending_verification` or `pending` status, fires `wb_listora_listing_submitted` action, and lands in the user's dashboard "My Listings" tab. Featured image upload + gallery picker open the WP media frame for both logged-in members and (where guest-submission is enabled) anonymous visitors.

### C.member.submit-conditional-fields
**What to verify:** changing the listing type dropdown mid-submission re-renders the Details step's field set without a page reload. Restaurant shows Cuisine + Dietary; Hotel shows Star Rating + Amenities; Real Estate shows Bedrooms + Price + Sqft. Submitted listings carry only the selected-type meta keys.

### C.member.submit-map-pin
**What to verify:** in the Details step, the location field exposes a draggable map pin. Dragging updates lat/lng inputs (visible or hidden). Saving persists `wp_listora_geo` row with correct coords. Loading the listing detail page re-renders the pin at saved coords.

### C.member.business-hours-picker
**What to verify:** Business / Hotel / Restaurant types show the Business Hours field in Details step. Clicking any time input opens a flatpickr time-picker (24h, 15-min increments) - works identically on Chrome AND Firefox (the round-2 flatpickr fix from 2026-05-08 covers Firefox's native-spinner gap).

### C.member.dashboard  `[CORE]`
**What to verify:** the resolved dashboard URL shows a 2-column sidebar+main layout (260px sidebar at 1280px). Tabs (My Listings, Reviews, Favorites, Claims, Credits, Profile, My Needs, Analytics) navigate via URL hash. Stats cards render real counts. Edit-listing from a row deep-links into the submission wizard with prefilled data.

### C.member.dashboard-pagination
**What to verify:** with 30+ items on any tab (Listings / Reviews / Favorites / Claims), pagination renders. Cursor or page navigation reaches the last page without 500. "Load more" or page-N click does NOT duplicate items.

### C.member.review-create  `[CORE]`
**What to verify:** a logged-in member writes a review on a listing they don't own, submits, and the review appears immediately in the Reviews tab. Helpful-vote button increments. Listing owner can reply via dashboard inline-reply form (post-2026-04-30 fix `e01486b`).

### C.member.review-toggle-services
**What to verify:** services-tab description toggle (Details button) expands/collapses the description without a page reload (post-2026-05-09 fix `c382a86`). Tested on the listing detail Services tab.

### C.member.favorites  `[CORE]`
**What to verify:** save/unsave a listing from the card AND from the detail page. Favorites count on the heart button updates client-side. The favorite appears in dashboard Favorites tab. Unsave from the dashboard removes it everywhere.

### C.member.claim
**What to verify:** anonymous viewer on a listing's detail page sees "Claim this business" CTA → clicking prompts login. As a logged-in member, the claim modal accepts a message + optional proof-document upload. Submitting writes a `listora_claims` row, fires `wb_listora_claim_submitted`, queues admin email. Member dashboard Claims tab shows pending status.

### C.member.renewal
**What to verify:** an owner whose listing is approaching expiry sees a "Renew" affordance in the dashboard My Listings row. Triggering renewal extends `_listora_expiration_date` by the configured period and fires `wb_listora_listing_renewed`. With Pro active and Credits enabled, renewal deducts the configured cost.

### C.member.compare (combo)
**What to verify:** "Add to Compare" on 2-4 listings navigates to `/compare-listings/?compare=ID,ID,ID` with a populated side-by-side table. Empty state shows with 0-1 listings selected.

### C.member.app-password-sign-in (1.3.1)
**What to verify:** `POST /listora/v1/auth/app-password` with a member's real WordPress username + password returns **200** carrying `{user_login, password, app_id}`, the returned password authenticates a Basic-auth call to `/wp/v2/users/me`, and the response carries `Cache-Control: no-store`. The account password appears nowhere in the response body and nowhere in `debug.log`.

Reconnect semantics: repeating the call with the **same** `app_id` leaves the member with exactly ONE credential row (older rows for that `app_id` are pruned); a **different** `app_id` adds a second row, so a phone never signs out a tablet.

**Security corners - each is a release blocker if it regresses:**
- **No enumeration.** A wrong password and a nonexistent username return byte-identical 401 bodies (`wb_listora_login_failed`, same message). The route must never answer "does this account exist?".
- **Rate limited before any credential is read.** 5 failures inside 900s → **429**, enforced on TWO independent buckets (`ip:` and `user:`), so neither one host grinding passwords nor a distributed run at one account gets through. Only rejected CREDENTIALS count - a 403 from the owner switch or a 409 hand-off must NOT increment the counter, or honest members get locked out. A correct sign-in clears both buckets.
- **No 2FA bypass.** With any plugin hooking `authenticate` to refuse, correct credentials must NOT return 200 - the route answers **409** and sends the member to the interactive browser flow. `wp_authenticate()` does the authentication, so every site auth rule still gets its say.

**Owner switch:** Listora → Settings → Advanced → **App sign-in**. Unchecking + saving makes the route return **403** `wb_listora_app_passwords_off`; the checkbox must render UNCHECKED on reload (**test the OFF direction explicitly - it is the one that regresses**, because an unchecked box posts nothing and the control relies on a paired hidden `0`). Existing credentials must KEEP working while the switch is off - turning it off stops new sign-ins, it does not sign out the install base. Filter escape hatch `wb_listora_app_password_login_enabled` overrides the setting either way.

Covered by `customer/13-app-password-sign-in.md`.

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

### C.admin.settings-each-tab  `[CORE]`
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

### C.rest.contract  `[CORE]`
**What to verify:** spot-check the REST contract - at least one endpoint per controller - confirms shape per `docs/REST-API.md`:
- List endpoint envelope `{ items_key, total, pages, has_more }`
- Single resource includes `id`, `created_at`, `updated_at`
- 401 on unauth (not `false`, not 200-with-empty)
- `has_more` formula `(offset+count)<total` (never `count===limit`)

Use `curl -i http://directory.local/wp-json/listora/v1/listings` and a paginated request to verify.

New 1.2.0 route - `GET /listora/v1/import/progress/(?P<run_id>[A-Za-z0-9]+)` (`Background_Import::register_rest_routes` / `rest_progress`): admin-only read (`progress_permissions` = logged-in + `manage_options`; anon → 401 `listora_unauthorized`, non-admin → 403 `listora_forbidden`) returning the live import-progress shape `{ run_id, kind, status, total, processed, imported, skipped, errors, percent, messages[], done }`; unknown run_id → 404 `listora_import_run_not_found`. The listing detail/list responses also gained an owner/admin-gated `views` field (`class-listings-controller.php`) - see C.analytics-lite.

### C.rest.app-config

**What to verify:** `GET /listora/v1/settings/app-config` (`Settings_Controller::get_app_config`, `permission_callback => __return_true`) is the PUBLIC bootstrap payload a native app reads before it can render anything or sign anyone in. Anonymous → **200** with all 25 contract keys present on every response (an app has no fallback at cold start): `contract_version` (int, mirrors `Settings_Controller::APP_CONTRACT_VERSION` = 1), `plugin_version`, `rest_namespace`, `directory_url`, `submit_url`, `dashboard_url`, `per_page`, `distance_unit`, `currency`, `default_country`, `moderation`, the pre-1.2.3 back-compat quartet `enable_claiming` / `enable_guest_submission` / `enable_reviews` / `enable_favorites` (+ `enable_captcha`, `captcha_provider`, `is_pro_active`), `app_enabled`, `min_app_version` (`'0.0.0'` default), `branding{accent_color,logo_url,login_bg_url}`, `legal{privacy_policy_url,terms_url,community_guidelines_url,abuse_contact_email}`, `features{}`, `languages{}`, `timezone{}`.

**`features{}` — the store split (1.2.3):** Free resolves ONLY registry rows tagged `store => 'free'` (**10** keys) via `wb_listora_features_registry()`. Pro registers its **20** rows into the SAME registry tagged `store => 'wb_listora_pro_features'` but stores their values in its OWN option — Free's reader would report the registry default, so Free skips them and Pro merges its real values through `wb_listora_app_config` (combo total **30**). Free-only sites serve exactly the 10. See D.app-config-pro-flag-leak.

**`app_enabled` — Pro-only gate, fails closed:** Free hardcodes `false`; ONLY Pro flips it true, and only under a valid license (Pro supplement S25). It gates the CLIENT, not the DATA — it is public and trivially spoofable, so it is a licensing gate, NEVER an authorization gate; per-user authorization stays in the REST permission callbacks. Never "harden" it by weakening a permission callback.

**Security:** the payload is public, unauthenticated and proxy-cacheable. It must NEVER carry the license key, customer email, or expiry (Pro `regression/app-config-no-license-leak.md`). The `wb_listora_app_config` filter passes `$request` as a 2nd arg since 1.2.3; anything hooking it must never add secrets.

Source: `includes/rest/class-settings-controller.php` (`get_app_config` + `free_feature_flags`). Covered by `regression/app-config-contract-shape.md` + `regression/app-config-app-enabled-free-false.md`; Pro side by `admin/18-app-config-license-gate.md` + `regression/app-config-pro-flags-branding-merge.md` + `regression/app-config-no-license-leak.md`.

---

## D - Known-regression guards

Each row is a repro of a past bug. Fixture IS the contract.

| ID | Card | Bug | Fixture + assertion |
|----|------|-----|---------------------|
| D.app-config-pro-flag-leak | 1.2.3 wave-1 | `/settings/app-config` served `analytics: false` while Pro's option said `true` - Free's resolver read EVERY registry row with Free's reader, but Pro's rows store their values in Pro's option, so a Pro flag came back as the registry default. An app gating on it hid a feature the site had switched ON (worse for Pro flags than Free ones: a disabled Free feature 403s, a disabled Pro feature is skipped by `Feature_Manager` so its route **404s**). | Combo. `wp eval 'remove_filter("wb_listora_app_config", array("WBListoraPro\Pro_Plugin","filter_app_config"), 10); $r=rest_do_request(new WP_REST_Request("GET","/listora/v1/settings/app-config")); $f=$r->get_data()["features"]; echo count($f);'` Assert: **exactly 10** keys, all `store => 'free'`, and ZERO Pro keys (`analytics`, `comparison`, …) - while `wb_listora_features_registry()` still holds 20 Pro rows (if it holds 0, Pro is inactive → **inconclusive, not PASS**). Then with the filter attached: `curl` → `features` has **30** keys and `analytics` matches `wb_listora_pro_feature_enabled('analytics')`, flipping in BOTH directions when Pro's option flips. Guard: `free_feature_flags()` must keep `if ( 'free' !== $store ) { continue; }`. |
| D.app-config-app-enabled-fail-closed | 1.2.3 wave-1 | `app_enabled` must fail CLOSED - the mobile app is a Pro benefit, so a free-only/unlicensed site serving `true` lets users into an unlicensed product | `grep -n "'app_enabled'" includes/rest/class-settings-controller.php` → exactly one hit assigning the literal `false` (never derived from `is_pro_active` / a toggle / an option - Free cannot know license state, INV-1). With Pro's filter removed: payload is **strictly** `false` even though `is_pro_active` is `true`. JSON type is a real boolean, never `"0"`/`0`/`null`. Licensed true-path + the 4 license states: Pro supplement S25. |
| D.app-nonce-exempt-writes | 1.2.3 wave-2 | Three app-facing writes ran an **unconditional** `wp_verify_nonce` against a token printed into page HTML (`POST /listings/{id}/contact-form` Free; `POST /listings/{id}/contact` + `POST /analytics/track` Pro). A native client never renders that page and a nonce is session-bound, so all three were a hard **403** for the mobile app. 1.2.3 routes them through `wb_listora_verify_rest_nonce()`: a sent nonce is still verified, an authenticated caller needs none, **anonymous + no nonce is still rejected**. | Journey: `regression/app-nonce-exempt-authenticated-writes.md` (Free) + `wb-listora-pro/docs/qa/journeys/regression/app-nonce-exempt-pro-writes.md` (Pro). Mint an App Password (`wp user application-password create 1 qa --porcelain`; use the **real `user_login`**, not "admin"). Assert all four corners on each of the 3 routes: authed+no-nonce → **200**; authed+bad-nonce → **403**; **anon+no-nonce → 403 (release blocker if 200 — open spam relay)**; `add_filter('wb_listora_require_rest_nonce','__return_true')` → **403** again (escape hatch, production rule 3). Rate limits, honeypot + captcha must still fire (4th contact in an hour → **429**). `/analytics/track` gotchas: `event_type` is an enum (`phone_click`…) and args validate **before** `permission_callback`, so a bad value is a `400` that never reaches the gate; a default curl UA is bot-filtered to `{"tracked":false}` — send a browser UA. Revoke: `wp user application-password delete 1 --all`. |
| D.contact-rate-limit-per-user | 1.2.3 wave-2 | Contact/lead rate limit (3/hr per listing) was keyed on **IP alone**. Behind carrier-grade NAT — every mobile network — unrelated app users share one public IP and throttle each other; the cap is meant to stop one *person*, not one *network*. 1.2.3 buckets authenticated senders per-user via `wb_listora_contact_rate_limit_identity()`; guests keep the per-IP key unchanged. Cap + window unchanged. | Clear counters (`DELETE FROM wp_options WHERE option_name LIKE '%transient%wb_listora_contact%'`). Send 4 authed contact-form messages to one listing → `200,200,200,429`. Assert a `_transient_wb_listora_contact_user_<md5>` key exists and its hash equals `md5("<user_id>|<listing_id>")` — **NOT** `_transient_wb_listora_contact_ip_*`. Assert guest path unchanged: `wp eval '$_SERVER["REMOTE_ADDR"]="203.0.113.9"; wp_set_current_user(0); echo json_encode(wb_listora_contact_rate_limit_identity(335));'` → `{"scope":"ip",...}`; `wp_set_current_user(1)` → `{"scope":"user","id":"1"}`. Same contract on Pro's lead form (`_transient_listora_lead_user_<md5>`). Escape hatch: `wb_listora_contact_rate_limit_identity` filter restores always-per-IP. |
| D.is-favorited-batched | 1.2.3 wave-2 | `prepare_item_for_response()` ran one `SELECT COUNT(*) FROM {prefix}favorites` **per item** (20 queries at `per_page=20`), and `/search` — the app's home screen — carried **no `is_favorited` at all**. 1.2.3 adds `\WBListora\Core\Favorites_Cache` (prime-once/read-many, the pattern `Analytics_Lite::prepare_views()` already used). | Journey: `regression/is-favorited-batched-and-on-search.md`. Count favorites queries by hooking the `query` filter around one `rest_do_request` (no `SAVEQUERIES` needed). Assert `per_page=20` authenticated → **exactly 1** favorites query on BOTH `/search` and `/listings`, with values diffed against `{prefix}listora_favorites` truth (**mismatches=0** — cheap AND correct). **Measure the OFFSET path**, not just the cursor path: `get_items()` delegates the default path to `WP_REST_Posts_Controller` and primes via a scoped `the_posts` filter — a fix that only primes the cursor branch leaves the app's actual path at 20 queries. Anonymous → field **present** and `false` with **0** favorites queries. `/listings/bulk` (10 ids) → ≤2. `/detail` `favorite_count` == DB count. Contract: `is_favorited` always present on `/search`, `/listings`, `/detail`, `/bulk`. |
| D.search-hours-additive | 1.2.3 wave-2 | `business_hours` had two incompatible shapes from two producers: `/search`→`meta.business_hours` (post meta, `{day:1, open:"06:00", close:"01:00"}`) vs `/detail`→`business_hours` (`hours` table, `{day:0, day_name, open_time:"06:00:00", …, is_closed, is_24h, timezone}`). Only `/detail` carried timezone/is_closed/is_24h, so **"Open now" was not honestly computable from a search card** (seeded data has overnight 06:00→01:00 spans needing midnight-wrap in the *listing's* tz). 1.2.3 **adds** `hours` + `timezone` to `/search` from the shared `\WBListora\Core\Business_Hours` normaliser `/detail` now also uses. | Journey: `regression/search-hours-normalised-additive.md`. **Back-compat is the blocker:** `meta.business_hours` must stay byte-identical — keys exactly `{day,open,close}` at `HH:MM`. Assert `/search` `hours` **==** `/detail` `business_hours` (one client parser; both must come from `Business_Hours::get()`). Assert `timezone` is a non-empty IANA id and rows expose `is_closed`/`is_24h`. Batching: `per_page=20` → **1** hours query (not 20). No-hours listing → `hours: []`, `timezone: ""` (never invent a tz — a wrong "Open now" is worse than none). |
| D.listing-delete-cascade | BC 10156782139 | Hard-deleting a listing cleaned only the 4 index tables; reviews/review_votes/favorites/claims/services/analytics (Free) + need_responses/coupon_usage (Pro) orphaned forever. 1.4.1: `Listing_Data_Eraser` on `before_delete_post` + `wb_listora_listing_data_deleted` action (Pro cascades its own tables, INV-6) + orphan backfill in `wp listora cleanup` via `wb_listora_purge_orphaned_listing_data`. `payments`/`audit_log` intentionally retained. | Journey: `regression/listing-delete-cascade.md`. Seed one row per table on a probe listing → trash keeps data (only search_index drops) → hard delete leaves ZERO rows everywhere → orphan rows purged by `wp listora cleanup` (incl. the Pro table). |
| D.string-shaped-field-options | 1.4.1 (live-site Wordfence fatal) | Owner-added select/radio/multiselect options were saved as plain strings by the Type Editor JS; PHP 8 readers (`submission-field-renderer.php`, search filters) did `$opt['value']` on a string → `TypeError: Cannot access offset of type string on string` → public submission page 500. Fixed 3-layer: `Field::normalize_options()` in constructor (read-side healing, no migration), save-path normalization in `Listing_Type_Registry::create_type_from_data()`, Type Editor JS always writes `{value,label}`. | Journey: `regression/string-shaped-field-options-fatal.md`. Inject string options directly into `_listora_field_groups` term meta (wp eval in journey) → load `/add-listing/?autologin=1` → HTTP 200, checkboxes render with slugged values + original labels, zero `Cannot access offset` in debug.log. Then Type Editor: Add Option → type → Save → raw term meta contains ONLY `{value,label}` arrays, no plain strings. |
| D.price-scalar-sanitize | BC 10171941201 | `price` was mapped to `Field::sanitize_json()`, but the submission form and the wp-admin fields metabox both post a bare scalar. `json_decode("275")` returned `int 275`, failed `is_array()`, and the sanitizer returned `array()` — **every price save silently discarded the amount**; the edit form then rendered `value="Array"` with a PHP `Array to string conversion` warning. Fixed with a dedicated `Field::sanitize_price()` promoting scalars to the documented `{amount,currency}` shape, plus an `is_scalar()` guard in the renderer. Amounts lost by pre-1.4.2 saves are unrecoverable. | Journey: `regression/price-scalar-sanitize-dataloss.md`. Sanitize `'275'` → `{amount:275.0,currency:<site>}` (pre-fix `array()`) → submit a price through `/add-listing/` → `_listora_price` holds `amount`, NOT `a:0:{}` → reopen edit form, input prefills `value="275"` → force `array()` into the meta, edit form renders empty with zero `Array to string conversion` in debug.log → same assertion via the wp-admin metabox. |
| D.location-column-no-city | BC 10172069880 | Admin Location column blank for region-level addresses. `render_column()` gated the cell on `! empty( $geo['city'] )` (the indexer stores `city = ''` when reverse geocoding resolves no city), and its `_listora_address` fallback rendered only `if is_string()` — but that meta is a composite array, so the fallback was dead code for every listing. Fixed by rendering the two most specific non-empty parts of `city/state/country`, falling back to the composite address meta, then an em-dash placeholder. | Journey: `regression/admin-location-column-no-city.md`. Fully resolved rows still read `"City, ST"` (guards over-reach) → blank the geo `city` → cell reads `"Northwest Territories, Canada"` (pre-fix: empty) → country-only row reads `"Canada"` → delete the geo row, cell falls back to composite address components → clear everything, cell shows `—`, never an empty cell. |
| D.listings-column-defaults | Reported in-session (QA screenshot) | The listings table shipped 17 columns all visible (9 Free, 1 Pro, 7 core). Under `table-layout: fixed` Title was left unsized and collapsed to ~52px, wrapping titles one character per line into a vertical ribbon ~1000px tall. Fixed with `default_hidden_columns` (curated default set + new `wb_listora_default_hidden_columns` filter) and percentage column widths scoped to `.post-type-listora_listing`. The check column is pinned — unsized it absorbed every leftover percent and ballooned to a third of the table. | Default view shows 11 columns, Title ≥ 240px at 1440px, no row taller than ~80px. Re-enable ALL columns in Screen Options: zero columns collapse below 20px, no horizontal page scroll, rows stay under ~140px. Confirm `edit.php` (core Posts) Title width is UNCHANGED — the CSS must not leak past `.post-type-listora_listing`. At 390px core's stacked layout applies and the page must not scroll sideways. |
| D.pages-review-notice-dismiss | Reported in-session | The "WB Listora is set up — N pages are mapped" notice reappeared on every admin screen. It renders `is-dismissible`, so core paints an X, but core's X is client-side only and persisted nothing; only the small "Dismiss" link wrote the user-meta flag. Every plugin reactivation re-armed the 7-day transient, so it read as "the notice never goes away when Pro is active". Fixed by enqueuing `assets/js/admin/pages-review-notice.js`, which wires the X to the same nonce'd endpoint. | Arm it (`set_transient wb_listora_pages_review_pending`, clear `wb_listora_pages_review_dismissed` user meta) → load any admin screen → click the **X** (not the link) → assert the transient is deleted AND the user meta is `1` → reload and assert the notice does not return. |
| D.setup-wizard-headers | #9867159785 | Setup wizard "Go to Dashboard" → blank page | Create user with `manage_listora_settings` cap but NOT `edit_listora_listings`. Walk wizard to "Done" step. Click "Go to Dashboard". Assert: lands on `admin.php?page=listora` (not blank). Assert: `wp-content/debug.log` has zero `Cannot modify header information - headers already sent` entries. |
| D.empty-media-fieldset | #9867347053 | Details step rendered empty `<fieldset><legend>Media</legend></fieldset>` | Login as Contributor → Add Listing → pick Business → Continue to Details. Assert: NO empty fieldset whose only child is a `<legend>Media</legend>`. Repeat for Restaurant, Hotel, Place, Marketplace, Real Estate, Event, Medical, Course, Job Board. |
| D.overview-company-logo-id | #9867775853 | Overview tab printed `Company Logo: 818` (raw attachment ID) | Visit any Job listing detail page. Assert: Overview tab DOES NOT contain a `<dt>Company Logo</dt><dd>{integer}</dd>` block. Assert: Company tab still renders the logo as `<img>`. |
| D.map-popup-image | #9867372176 | Map popups missing featured image | Visit any page with the listing-map block, with at least one listing that has a featured image. Click the marker. Assert popup contains `<img class="listora-map__popup-image">` for listings with thumbnails (and gracefully omits it when no thumbnail). |
| D.migrated-hours-not-dropped | #10184420962 | The four competitor migrators pass the source plugin's own hours structure straight into `_listora_business_hours`; none of those is a shape Listora reads, so imported hours indexed to zero rows, rendered nothing and matched no "Open now" search — while the import reported a clean success | Run `wp eval-file docs/qa/fixtures/migrated-hours-probe.php`. Assert: the three competitor shapes report `stored_normally=NO` + `raw_preserved=yes`; the three readable Listora shapes report `stored_normally=yes` + `raw_preserved=no`; dropped count is 3. Then run a real migration with hours and assert `migrate_all()` stats carry `unreadable_hours` and a message naming the source. NOTE: this tests that the failure is REPORTED, not that a mapping exists — per-source mappings are still open on #10184420962. Full flow: `docs/qa/journeys/regression/migrated-hours-not-silently-dropped.md` |
| D.business-hours-multi-range | #10180685898 | A day could hold only one time range, so a split shift (08:00-12:00, 17:00-22:00) was unrecordable; and once the form could post the `ranges` shape, the detail template's own inline grouping did not understand it — the index table held both ranges while the listing page rendered Monday as `–` | Add Listing → Business Hours → Monday **+ Add another time** ×3. Assert: third add hides the control (`hidden`, absent from the a11y tree), input names stay `ranges[0..2]` contiguous. Remove the MIDDLE range. Assert: survivors keep their own times, renumber to 0/1, aria-labels re-derive, add control returns. Then save a split shift and open the listing. Assert: `Monday 8:00 am – 12:00 pm, 5:00 pm – 10:00 pm` AND two `slot` rows in `{prefix}listora_hours`. Assert via `wp eval-file bin/hours-grouping-diff.php`: every non-`ranges` listing renders byte-identically. Full flow: `docs/qa/journeys/regression/business-hours-multi-range.md` |
| D.claim-audit-trail-both-paths | #10199419982 | Two defects in one surface. Pro's `Audit_Log` consumed `( $claim_id, $data )` while Free fires scalars, so every `claim_submitted` row logged `listing_id: 0` with an empty title, and `on_claim_updated` read `$data['status']` off a string so an approval logged as `claim_updated`. Separately the wp-admin Claims page called `apply_approval_side_effects()` directly and never fired `wb_listora_after_update_claim`, so an admin approval produced NO audit row at all - and Pro's Outgoing_Webhooks missed the same event. The claim-approved email still sent (it hangs off `wb_listora_claim_approved`), which is what hid it | Member submits a claim, then approve it from wp-admin (single row AND bulk). Assert an audit `claim_submitted` row carries the real `listing_id` + `listing_title` (0 / empty is the regression), and a `claim_approved` row exists after the admin approval with `"status":"approved"` (no row, or `claim_updated`, is the regression). Repeat for reject on both paths. With an outgoing webhook on `claim_approved`, assert admin approval delivers too. Full flow: `docs/qa/journeys/regression/claim-audit-trail-both-paths.md` |
| D.blocking-enforced-on-live-contact-route | #10184284933 | Two contact routes exist and only one renders: Free's `/contact-form` when `lead_form` is OFF, Pro's `/contact` when it is ON (the paid default, where Free's `Contact_Form::should_render()` suppresses itself). Free's route enforced member blocking; Pro's had zero block checks, so on the paid configuration the enforced route was the dead one and the live route was unenforced - a blocked member could still message the person who blocked them. Guideline 1.2 severity: the app posts to Pro's route | Block member A<->B. POST as A to Pro's `/listings/{B listing}/contact` -> assert `403` `listora_contact_blocked` (`200 {sent:true}` is the regression). Same assertion on Free's `/contact-form`. Unblock -> assert `200 sent:true` (a guard that blocks everyone is the opposite failure). Logged out -> assert unaffected (viewer 0 is never a blocked pair). Finally `curl /settings/app-config \| jq .contact_route` -> `"contact"` with `lead_form` ON, `"contact-form"` OFF. Full flow: `docs/qa/journeys/regression/blocking-enforced-on-live-contact-route.md` |
| D.admin-writes-run-before-output | #10199668750 | A Pro admin save that redirects cannot run inside a render callback - by then admin body output has begun, `wp_safe_redirect()` warns "headers already sent" and the `exit` ends the request where it stands. The record IS written; only the navigation fails, so the user sits on the form they just submitted. The card was scoped to the Badges Save redirect; a sweep found **four** pages in the same shape (Badges save/delete/max-badges, Moderator promote/demote, Outgoing Webhooks save, Needs approve/reject). Coupons had been fixed for this already (9927893041) and was the reference | For each page: note the debug.log line count, perform the write, assert the URL lands on the LIST (not the form), the success notice is **computed-visible** (`getComputedStyle().display !== 'none'` - Listora's own notices were being hidden by a `:not(.listora-notice)` rule, so presence proves nothing), and **zero** new `headers already sent` lines. Webhooks + Needs need their feature toggles ON; restore them after. Then re-run the greppable detector (now **G7** in `bin/audit-guardrails.sh`) - any `add_submenu_page` + redirect with no `load-{$hook}` is the regression. Full flow: `docs/qa/journeys/regression/admin-writes-run-before-output.md` |
| D.admin-lists-paginate-and-count-truthfully | #10199612602 | The Moderators screen fetched every moderator with no `number` and rendered them in ONE unpaginated table - 60 rows live on the repro install, against a card that recorded "not at scale today (6 users)". Eligible Users was hard-capped at `number => 50` with no pager, so users 51+ were unpromotable from the UI and the copy told the admin to go edit roles by hand. No search on either table, and no bulk actions at all - while `POST /moderators/reassign` had been implemented and journey-verified with ZERO UI consumers, and CAPABILITIES.md:282 documented that surface as existing. Badges had the same shape at a 100 cap | Moderators: assert both tables paginate (20/page), the header count reports the TOTAL not the page, search narrows both (exact + partial + no-match), and the stats grid counts ALL moderators - it read "0 Active" of 60 when it was handed the rendered page instead of the full ID set. Bulk: select-all toggles every row and clears when one row is unchecked; Deactivate then Activate round-trips; **bulk must NOT bypass a per-row guard** - bulk-demoting yourself must report 0 applied of 1. Badges: assert pagination and that "N badges configured" reports the total. Zero `Undefined variable` lines in debug.log on every page/search variant. Full flow: `docs/qa/journeys/regression/admin-lists-paginate-and-count-truthfully.md` |
| D.favorite-heart-overlays-card-image | #10195604615 | On the dashboard Favorites tab the heart sat BELOW the card image in normal flow, made the card taller, and could not be clicked - the click landed on the card's own anchor and navigated to the listing. The same card on the directory was fine. It was never a missing stylesheet: the button carries both `listora-favorite-btn` and `listora-card__favorite`, and two rules of EQUAL specificity fight over `position` - `all: unset` in listora-base.css vs `position: absolute` in the listing-card block style. At equal specificity the last sheet loaded wins, and that differs per page: the directory LINKS the block style after base, the dashboard INLINES it before. Fixed by scoping the positioning rule to `.listora-card__media` so specificity decides, not load order | On `/listings/` AND the dashboard Favorites tab: assert `getComputedStyle(heart).position === 'absolute'` (the rule being present in a sheet proves nothing - that was true while the bug was live), the heart's rect sits inside `.listora-card__media`, and `document.elementFromPoint` at its centre resolves to the button (scroll into view first). Click it: `is-favorited` flips and the URL does NOT change. Repeat at 390px. Re-favorite anything toggled and check the row count against baseline. Full flow: `docs/qa/journeys/regression/favorite-heart-overlays-card-image.md` |
| D.admin-map-picker-renders | #10198832114 | The wp-admin listing editor reuses the frontend submission field renderer, so a `map_location` field prints the same `.listora-submission__map-picker` div the Add Listing wizard uses - but that div only becomes a map when Leaflet AND the picker initialiser are both present, and neither was ever enqueued in wp-admin. Editors got an empty box: no map, no marker, nothing to drag. Saving still worked via the hidden coordinate inputs, which is why it read as a UX gap rather than data loss. CORRECTION to the card: the sizing CSS is NOT part of this gap - the block editor enqueues every registered block stylesheet, so `height: 250px` already applied | Open a listing whose type has a `map_location` field at `post.php?post=<id>&action=edit`. Assert the page carries `assets/vendor/leaflet.css`, `assets/vendor/leaflet.js` and `build/admin/listing-map-picker.js`, with leaflet BEFORE the picker script. Then assert a Leaflet map actually renders inside `.listora-submission__map-picker` (`.leaflet-container` present, `window.L` defined, the div has children), the marker drags, and dragging writes the hidden lat/lng. The picker is the SAME `initMapPickers()` the wizard uses, extracted to `src/utils/map-picker.js` - a second map implementation appearing anywhere is the regression. Full flow: `docs/qa/journeys/regression/admin-map-picker-renders.md` |
| D.related-listings-hooks | #10194553271 | The Related Listings section is rendered by the block, not by a template, so overriding `blocks/listing-detail/render.php` from a child theme does not reach it - the guidance previously given to the customer was wrong. There was no `do_action` anywhere near it; the last one on the page sat ~160 lines earlier | On a listing WITH related results, hook `wb_listora_before_related_listings` and `wb_listora_after_related_listings` from an mu-plugin and echo a marker. Assert the before-marker renders immediately ahead of `<section class="listora-detail__related">` and the after-marker follows the section, and that both receive `$post_id` plus a primed `WP_Query` (`found_posts` > 0). On a listing with NO related results assert NEITHER fires - a hook firing around an absent section would place content where there is nothing to relate it to. |
| D.review-criteria-configurable | #10199712310 | `review_criteria` could be written over REST and genuinely persisted to `_listora_review_criteria` term meta, and NOTHING read it. All three consumers called `apply_filters( 'wb_listora_review_criteria', array(), $slug )` with an EMPTY base, and Pro's resolver discarded its first argument entirely and returned one of its hardcoded per-type sets. So the save returned 200, the meta was really there, and the front end never changed - a setting that is a false positive by construction | Configure criteria on a listing type (REST PUT or term meta), then render the review form for a listing of that type **as a non-owner** (owners cannot review their own listing, so the form is absent for them - that is not the bug). Assert the form shows the CONFIGURED criteria and none of Pro's hardcoded set. Clear the criteria -> Pro's per-type default returns. Store a junk scalar row alongside a valid one -> the junk row is dropped and nothing fatals (same PHP 8 offset class as the field-options fatal). Also assert Pro's review label map resolves the same criteria the form collected. NOTE when testing in one process: `Listing_Type_Registry` caches the type, so writing meta and reading it back in the same request returns the pre-write value. |
| D.plans-restricted-by-listing-type | #10195190213 | Pricing plans were global: a plan set up in wp-admin showed on the Plan step for EVERY listing type, so a multi-type directory offered a 30-day "Job Posting" plan next to a lifetime "Restaurant" one. `_listora_plan_listing_types` was specified in the design doc and had ZERO occurrences in the codebase; `get_all()` took no arguments; and `render_plan_step()` received the selected type from Free but its own docblock called the argument "unused; kept for hook signature compatibility". There was also no server-side check, so a forged `plan_id` in the POST body activated any plan for any listing type - a payment-integrity gap, not a presentation one | Create two listing types and two plans. Restrict plan A to type X in the plan metabox; leave plan B unrestricted. Frontend Add Listing -> pick type Y -> assert plan A is ABSENT and plan B is present; pick type X -> both present. Then call `activate_plan_for_listing()` (or POST the plan_id directly) with plan A against a type-Y listing -> assert `WP_Error` `listora_plan_type_mismatch`, NOT an activation. Assert an unrestricted plan still activates for every type - over-blocking is the opposite failure. An empty restriction means "all types", which is what every pre-1.6.0 plan carries, so existing sites must see no change until an owner opts in. |
| D.terms-acceptance-enforced | #10195308842 | A listing could be created without accepting the Terms of Service. `handleSubmission` validated only behind `if ( single-form )`, and add mode renders the wizard — where the per-step validation runs on the step being LEFT, so Preview (the one step carrying the checkbox) was never validated. `submit_listing()` never read `agree_terms` either, so a direct REST POST created a listing with no consent and nothing recorded it | POST `/listora/v1/submit` with no `agree_terms` → assert `400` + code `listora_terms_required`; repeat with `false` → `400`; with `true` → `201`. Then walk the Add Listing wizard to Preview filling only **active** required fields (inactive type blocks are `disabled` and carry duplicate names), leave the box unticked, Submit. Assert: no request sent, form still visible, and `.listora-submission__field-error--agree-terms` is **computed-visible** (`getComputedStyle().display !== 'none'` — presence proves nothing) reading "Please accept the Terms of Service to continue.". Repeat at 390px. Tick the box → submits, and `_listora_terms_accepted` meta is non-empty on the new listing. Finally assert `add_filter( 'wb_listora_require_terms_acceptance', '__return_false' )` restores the `201`. Full flow: `docs/qa/journeys/regression/terms-acceptance-enforced.md` |
| D.business-hours-firefox | #9856828615 | Firefox showed numeric spinner instead of time picker | (Manual - Firefox Desktop) Login as Contributor → Add Listing → Business → Details → click Monday opening time input. Assert: flatpickr dropdown opens (not native spinner). |
| D.map-fatal | #9871222447 | `Call to undefined function update_post_meta_cache()` | Visit any page rendering the listing-map block. Assert: HTTP 200, no fatal. Tail debug.log - no `Call to undefined function` entries. |
| D.service-details-toggle | #9872013428 | "Details" toggle on service descriptions did nothing | Visit listing detail with services. Click "Details" on a service card. Assert: `.listora-detail__service-desc--collapsed` class flips to expanded. Click again - re-collapses. Chevron rotates. |
| D.filter-count-dropdowns | #9871208081 | Filter count badge ignored dropdown filters | Open listings page → Filters panel → select a category from dropdown. Assert: badge shows `1` (was `0` before fix). Add a location selection - badge becomes `2`. Add a date preset - badge becomes `3` (date counts as one regardless of from/to/preset). |
| D.services-photo-upload | #9872014083 | Services Meta Box had no Photo upload field | `/wp-admin/post.php?post={listing_id}&action=edit` → scroll to Services meta box. Assert: Photo column visible. Click "Choose" → WP media library opens (filtered to images). Pick image → preview appears + hidden `image_id` populated. Save listing → reload - preview persists. |
| D.dashboard-2-col-layout | (today) | Dashboard sidebar+main collapsed to single column | Visit the resolved dashboard URL with `?autologin=1` at 1280px+. Assert: `getComputedStyle(.listora-dashboard).display === 'grid'` AND `gridTemplateColumns` starts with `260px` (sidebar width). |
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
| D.single-form-steps-no-hidden-attr | #10153910549 | Dashboard "Update Listing" silently did nothing — single-form emitted `hidden` on every step and relied on a CSS override winning the cascade; any theme shipping `[hidden]{display:none!important}` collapsed the steps, made the `agree_terms` checkbox unreachable, and native `required` then blocked submit with no message, no console entry, and no request | Open `/my-listings/?tab=listings&action=edit&id=<owned id>`. Assert **every** `.listora-submission__step` reports `hasAttribute('hidden') === false` and computes `display:block`. Inject `[hidden]{display:none!important}` → all steps still `block`, checkbox `offsetParent` non-null, `form.checkValidity()` true. Assert `input[name="agree_terms"]`: `.required === false` and `.dataset.listoraRequired === 'agree_terms'`. Submit without ticking → NO request, `.listora-submission__field-error--agree-terms` visible in `--listora-danger` + wrapper `.is-invalid` + 2px danger outline on the checkbox. Tick → `POST /listora/v1/submit` **200** and `post_modified` advances. Wizard (`/add-listing/`) unchanged: only `type` visible, other 4 still `hidden`/`display:none`, stepper visible. Covered by `regression/single-form-steps-no-hidden-attr.md`. |
| D.cli-test-email-cleanup | #(43ded68) | `wp listora test-email` + `wp listora cleanup` documented in C.cli but never registered | `wp listora test-email listing_approved --to=<email>` exits 0 (Success "Sent" OR a non-fatal delivery WARNING). `wp listora cleanup` prints "Cleanup complete." with no fatal, exit 0. `wp listora test-email` (no arg) lists all 15 templates. Unknown template → clean validation `Error:`, not a fatal. New CLI in `includes/class-cli-commands.php` (NEW feature - also +2 subcommands in `audit/manifest.json` wp-cli list + `audit/manifest.summary.json` count). Covered by `regression/cli-test-email-cleanup.md`. |
| D.coming-soon-private-cap | #(ce3f9f6, Pro) | Private visibility 403'd every logged-in non-admin - gated on the phantom cap `read_listora_listings` (never defined/granted) | (Combo.) `wb_listora_pro_visibility=private`, coming_soon ON. Logged-out → gated (login/403). A subscriber → HTTP **200** on a listing (not 403); `user_can(subscriber,'read')` true, `'read_listora_listings'` absent. Admin always 200. Gate reads `current_user_can('read')` at `wb-listora-pro/includes/features/class-coming-soon.php:99`. Covered by Pro `regression/coming-soon-private-cap.md`. |
| D.seo-sitemap-provider-registered | #(d3de2f2, Pro) | Programmatic-SEO sitemap never registered (dead `wp_sitemaps_add_provider` filter branch) | (Combo.) seo_pages ON. `wp-sitemap.xml` lists a `listora-seo` sub-sitemap; that sub-sitemap has **> 0** `<url>` entries; the provider name `listora-seo` is in `wp_sitemaps_get_server()->registry`. Registered via `wp_register_sitemap_provider('listora-seo', ...)` on init@11. Toggle OFF → provider absent. Covered by Pro `regression/seo-sitemap-provider-registered.md`. |
| D.comparison-label-wrap | #9962541597 (Pro) | (Combo.) Compare-view label text painted over the adjacent value column (`white-space: nowrap` in a `table-layout: fixed` 150px column — Healthcare labels like "Insurance Accepted" measure wider) | `/compare-listings/?compare=<3 healthcare ids>` → every `.listora-comparison-table__label-col` has `scrollWidth <= clientWidth` (labels wrap to two lines). Same at 390px; table scrolls inside its wrapper, no body overflow. Covered by Pro `regression/comparison-label-wrap.md`. |
| D.dashboard-stats-transient-bust | #9982046916 | Dashboard stat counts stayed stale up to 60s after favorite/review writes — the stats cache is a TRANSIENT but the busts used `wp_cache_delete` (no-op on every setup) | Seed `listora_dashboard_stats_<uid>` transient with a sentinel, POST/DELETE `/listora/v1/favorites` (and review create/delete) via real REST → transient is GONE after each write; dashboard stat shows the real count immediately. `listora_review_stats_` correctly keeps `wp_cache_delete` (paired with `wp_cache_get`); dead `dashboard_reviews_` busts removed. Covered by `regression/dashboard-stats-transient-bust.md`. |
| D.submission-form-style-setting | (1.2.0 feature) | Submission layout is now a site setting; also the single-form CSS hid the wrong class (`__stepper` vs the template's real `__progress`) so the wizard progress bar leaked into single-form contexts | Settings > Submissions > "Submission form style" = Single page form → submission page renders `--single-form` (all sections stacked, `__progress` computes display:none). Back to wizard → stepper returns. Explicit block `layoutMode` wins over the setting. Covered by `regression/submission-form-style-setting.md`. |
| D.media-step-field-prompt | (1.2.0 UX) | Media step showed generic "This field is required." on the missing featured image — the top abandonment point | Walk the wizard to Media, click Continue with no featured image → error reads "Add a featured photo to continue."; setting an image clears it and Continue advances. `wb_listora_required_field_messages` filter overrides the copy. Covered by `regression/media-step-field-prompt.md`. |
| D.submission-success-centered | #9962418696 | Post-submission success message read as misaligned (no width cap — spanned the theme's full content column; buttons edge-to-edge on mobile) | Submit a listing end-to-end: `.listora-submission__success` computes ≤520px wide, centered in its container, buttons centered (desktop) / stacked full-width (≤640px). LTR + RTL twins carry the same rules. Covered by `regression/submission-success-centered.md`. |
| D.search-single-clear-icon | #9962442616 | Two stacked × icons on the keyword search input (`type="search"` → native WebKit cancel button painted under the block's own clear button) | Type text in the Directory keyword input (focused): exactly ONE × control renders (`.listora-search__clear`); `::-webkit-search-cancel-button` suppressed in block CSS (LTR + RTL). Clear button still empties the input. Check desktop + 390px. Covered by `regression/search-single-clear-icon.md`. |
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
| D.dashboard-services-modal | #9976599203 | "Manage services" gear on a My Listings row revealed the services panel FAR below all listing rows — `tab-listings.php` renders every `services-panel-{ID}` in a sibling foreach AFTER the rows (panel N below row 20), and `toggleDashServices` just unhid the distant sibling | Login as a listing owner → `/my-listings/` → click the gear (`aria-label="Manage services"`) on any row. Assert: `#services-panel-{ID}` opens as a fixed overlay (`position: fixed`, `role="dialog"`, `aria-modal="true"`, `aria-labelledby` resolving to the panel title) with its `.listora-dashboard__services-dialog` fully inside the viewport — NO scroll needed. Esc, the X button, AND a backdrop click each close it; focus returns to the gear. Opening a second row's gear closes the first (single-open invariant). Inner functionality unchanged: "Add Service" toggles the inline form; Save/Edit/Delete still surface the documented "coming in a future update" info toasts (Free stubs — by design, not errors). At 390px: dialog fits, form grid collapses to 1 column, close button ≥40px, zero horizontal overflow, zero console errors. `templates/blocks/user-dashboard/tab-listings.php` (modal wrapper) + `src/interactivity/store.js` (`toggleDashServices`/`closeDashServices` + modal helpers) + `blocks/user-dashboard/style{,-rtl}.css`. Covered by `regression/dashboard-services-modal.md`. |
| D.bp-profile-listings-styled | #9976250827 (Pro) | (Combo.) BP profile "My Listings"/"My Reviews" rendered unstyled stacked text — the templates emitted the full `.listora-bp-*` BEM tree but Pro shipped ZERO CSS for those classes and `BuddyPress_Integration` had no `wp_enqueue_style()` call | On `/members/{member}/listings/` (member with > per_page listings): `link[href*="pro-bp.css"]` present (handle `wb-listora-pro-bp`, dep `listora-components`) and ABSENT on home + the member's activity screen; `.listora-bp-listings-grid` computes `display:grid` (multi-col at 1280px; 1 col + no overflow at 390px); pagination controls ≥40x40, `?lpage=2` changes the card set with `span.current` on the `--listora-primary`/`--listora-primary-fg` pair (same as D.pagination-active-page-contrast); listings default page size == Free's Directory `per_page` setting (filter `wb_listora_pro_bp_listings_per_page` still wins); `my-reviews` styled (amber `--listora-rating` stars, ≥40px prev/next, `?rpage=2`, `»` renders as glyph not literal `&raquo;`); zero-listing member renders the canonical `listora-card listora-card--empty listora-bp-empty` state; `.listora-bp-count` shows the full localized total ("2,631 listings", not `%d`-truncated "2"). Pagination itself PRE-EXISTED (see D.buddypress-tabs-pagination) — this row guards the styling + enqueue + per-page wiring. Covered by Pro `regression/bp-profile-listings-styled.md`. |

| D.featured-columns-zero | #9989784605 | `columns: 0` on the Featured Listings block threw an uncaught `DivisionByZeroError` (render.php dot-count division) — a 500 in the editor preview REST and a fatally truncated page for visitors when saved; Grid/Categories (+ Pro needs-grid) emitted a zero-track `--*-columns: 0` CSS property | Create a published page containing `<!-- wp:listora/listing-featured {"columns":0} /-->` + the same for `listora/listing-grid` + `listora/listing-categories`. Anonymous fetch → HTTP 200, featured block renders, every inline `--listora-*-columns:` value ≥ 1, debug.log has ZERO `DivisionByZeroError`/fatal entries. Both defense layers present in source: `max( 1, (int) (...) )` clamp in each render.php AND `"minimum": 1` on the `columns` attribute in each block.json (combo: Pro `blocks/needs-grid/` too). Delete the fixture page. Covered by `regression/featured-columns-zero-fatal.md`. |

| D.adversarial-block-attributes | #9989784605 (class guard) | Server trusted editor-JS attribute constraints — block-renderer REST + saved content deliver raw attributes; out-of-range numerics fataled (DivisionByZeroError) or rendered zero-track grids | Run `system/adversarial-block-attributes.md`: every registered `listora/*` (combo: `listora-pro/*`) block × every number-typed attribute × {0, -1, -999999, 0.4, 999999, "abc"} via the block-renderer REST → ONLY 200 or 400, never 500. Saved-content fixture with all layout numerics at 0 → HTTP 200, clean debug.log, every `--listora-*-columns:` ≥ 1. Static twin: `bin/check-block-attr-guards.py` (coding-rules Rule 7) exits 0 in both repos. |

| D.submission-fieldset-min-width | #10163072337 | Details-step field groups burst out of the form column — the UA stylesheet gives `<fieldset>` `min-width: min-content`, so `.listora-submission__fieldset` refused to shrink below its widest child regardless of container width (measured at 1440px: three Event fieldsets at **720px inside a 582px parent**). Overflow pushed Continue off-screen on narrow viewports, blocking submission | `/add-listing/?autologin=1` → Event → Basic Info → **Details**. Every visible `.listora-submission__fieldset` computes `min-width: 0px` (NOT `min-content`/`auto`) and renders **≤ its parent's width** at 1440px (578 ≤ 582) AND at 390px (286 ≤ 290); `document.documentElement.scrollWidth === clientWidth` at both — no horizontal page scroll. Identical widths in theme-dark (containment must not depend on the palette). Declaration present in BOTH `blocks/listing-submission/style.css` and the hand-maintained twin `style-rtl.css` (`bin/build-css.mjs` does NOT generate this one — it owns only the variables/components twins). Covered by `regression/submission-fieldset-min-width.md`. |
| D.native-controls-color-scheme | #9895778531 (+ #9919496983 guard) | Native date/time pickers ignored the page and followed the **OS**: `color-scheme` was never declared, while the plugin's dark mode is deliberately gated on `[data-bx-mode]` and never on a bare `prefers-color-scheme` (BC 9919496983). A tester on OS-dark saw a light page with a **black** calendar popup. No plugin CSS can restyle a UA-owned popup — only `color-scheme` can | `/add-listing/?autologin=1` → Event → **Details** (7 date/datetime/time inputs). **Light:** root `color-scheme` is `light` (NOT `normal` — that is the undeclared bug state) and every `input[type=date|datetime-local|time]` computes `light`. **Theme-dark** (toggle): `[data-bx-mode="dark"]`, root `dark`, all 7 inputs `dark`. **OS-dark alone** (`emulateMedia({colorScheme:'dark'})` with the theme on light): everything stays `light` — the OS preference must change nothing, which is the BC 9919496983 guard; every `prefers-color-scheme` occurrence stays gated on `[data-bx-mode="auto"]`. Declarations present in BOTH `assets/css/listora-base.css` and the hand-maintained twin `listora-base-rtl.css`. Covered by `regression/native-controls-color-scheme.md`. |

| D.submission-category-optional | #10180373117 | A listing type whose `allowed_categories` is empty (`business` ships that way; the other nine carry 8-15) dead-ended the wizard. `step-basic.php:43` suppresses the Category field only when the type is known at render time — in the wizard it never is, so the select printed unconditionally with `required`, and `view.js` was the only thing that ever filled it. For a type with no categories it stayed at the bare placeholder while still `required`: a control with nothing to pick that refused to let the member past Basic Info, with no message saying why. **The server was never the blocker** — `POST /submit` accepts a listing with no category and returns 201 | `/add-listing/?autologin=1` at 1440x900. **Business** (0 categories): `[name="category"]` has `options.length === 1`, `required === false`, wrapper `hidden`; Continue from Basic Info reaches step `details` — staying on `basic` is the regression. **Restaurant** (15): `options.length > 1`, `required === true`, wrapper shown; Continue with Category empty is still BLOCKED on `basic`; picking one passes to `details` — the fix must not weaken validation where categories exist. **Switch Restaurant -> Business**: re-hides and un-requires, no stale `required`. 390px: no horizontal scroll. Invariant is `required` IFF `options.length > 1`, applied on the reset, success AND failure paths in `syncCategoryApplicability()` so an unreachable REST call cannot strand the member either. `src/blocks/listing-submission/view.js`. Covered by `regression/submission-category-optional-when-none.md`. |

| D.submission-upload-keyboard | LST-F-08 | The featured-image upload control was a `<div>` with `data-wp-on--click` - no tab stop, no Enter/Space, no focus ring, no announced role. The featured image is **required** on a new submission, so this did not make one control awkward: it made the whole form impossible to complete without a mouse. The same pattern was in the generic field renderer, so it also affected **every `file` custom field on every listing type** | Walk to the Media step (Restaurant -> Continue -> Title/Description/Category -> Continue -> Address -> Continue). **Test with the keyboard - clicking proves nothing, it worked with a mouse before too.** Trigger is `<button type="button">` with an accessible name from its own text; `document.querySelectorAll('div[class*="upload-zone"][data-wp-on--click]').length === 0` (catches the renderer copy returning); reachable by **Tab** alone (reference run: first Tab); focus ring `2px solid` at `2px` offset with `:focus-visible` matching - measure AFTER a real Tab, since `.focus()` does not set `:focus-visible`; **Enter** opens the media frame; **Space** opens it too (a hand-rolled keydown typically implements only one); ring survives `data-bx-mode="dark"` resolved from `--listora-primary`; Gallery button unchanged; >=40px tall and no horizontal scroll at 390px; in edit mode the remove (X) is a **sibling** of the trigger, never nested - a button inside a button is invalid and nesting lets `zone.textContent = ''` wipe it. `templates/blocks/listing-submission/step-media.php` + `includes/submission-field-renderer.php` + `blocks/listing-submission/style{,-rtl}.css` + `src/blocks/listing-submission/view.js`. Covered by `regression/submission-upload-keyboard-access.md`. |

| D.dashboard-tab-pagination | LST-F-06 | Claims was the only dashboard tab that paginated. Listings, reviews written, reviews received and favourites each took a flat `LIMIT 20` with no way forward **while the stat tile above them rendered the real `COUNT(*)`** - so the numbers visibly disagreed with the list underneath, and a vendor with 50 listings could manage 20 of them from the frontend. The paginated REST endpoints already existed; the block never called them | **NEEDS SEEDED DATA** - on a small dataset every pager correctly hides and this proves nothing. On a member with >20 of each: five `nav.listora-pagination` render (Listings / Reviews written / Reviews received / Favorites / Claims), each "Page 1 of N" with N>1; tile totals and reachable rows agree; `?tab=listings&listings_page=2` changes the first row and Previous becomes a real `<a>`; page 1 disables Previous and the last page disables Next, both as `<span aria-disabled="true">`, never a dead link; **`?…_page=99999` clamps to the last POPULATED page** (reference: "Page 276 of 276", 8 rows, Next disabled) - an empty state here is the regression, and it is what the first cut of this fix shipped, because the clamp was built on `WP_Query::found_posts` which returns 0 when `paged` is past the end, so the total now comes from a dedicated `COUNT(*)` taken before the slice query; the two Reviews pagers move independently (`reviews_page` vs `received_page`); `wb_listora_dashboard_per_page` ($per_page, $context, $user_id) changes the size; no pager renders below 2 pages; no overflow at 390px. Markup is `wb_listora_render_pagination()` in `includes/class-render-helpers.php`, shared by all five - Claims was refactored onto it rather than its 45 lines being copied four times. Server-rendered, so it works with JS off and survives the back button. Covered by `regression/dashboard-tab-pagination.md`. |
| D.dashboard-tabs-no-db-errors | (1.5.0 smoke) | Favorites tab ordered by `id DESC` on a table with no `id` column — query returned nothing, panel showed "No saved listings", nav badge next to it still said **32**. HTTP 200, correct empty-state markup, no PHP warning, no fatal: every assertion the walk made passed | Walk EVERY dashboard tab as a member with rows behind each. `grep -i "WordPress database error"` on the debug.log diff returns nothing (this string is neither a fatal nor a warning — a fatal/warning-only check misses it). On each tab the nav badge agrees with what the panel lists; badge > 0 beside an empty state is a FAIL. Every `ORDER BY` column exists in `SHOW KEYS`/columns for its table. Covered by `regression/dashboard-tabs-emit-no-db-errors.md`. |
| D.translations-render | (1.5.0 i18n) | `.po` + `.mo` verified complete and correct, plugin declared translation-ready, site still rendered English — WP 6.5+ prefers `.l10n.php`, and a stale one silently shadowed both | Set `WPLANG` to a locale with a complete `.po`. Read a translated string from the RENDERED page **by selector** (never by matching expected text) on all four paths: PHP template, block `render.php`, IAPI state string, JS `src/utils/i18n.js`. No `.l10n.php` older than its `.po` (Rule 11). `wp i18n make-php languages` must not change what renders. Covered by `regression/translations-must-render-not-just-compile.md`. |
| D.credit-ledger-repair-idempotent | #10190573574 | The repair that compensates minor-unit top-ups paid twice on re-run: the settled-ID parse sat AFTER the query meant to use it. Detection also only matched hardcoded pack sizes, silently leaving 1 of 3 members short | Seed broken top-ups at small, large, and filter-added pack sizes — dry run must report ALL of them. `--execute`, assert balances correct. `--execute` AGAIN: zero balance change, zero new adjustment rows, reports nothing to repair. Original ledger rows unmodified (additive only, never clawed back). Fixture must go through the real top-up path so the adjustment note carries the settled IDs. Covered by `regression/credit-ledger-repair-is-idempotent.md`. |
| D.bulk-edit-listing-type | #10190576873 | Bulk-edit control rendered nothing — `bulk_edit_custom_box` was bound to `title`, and WP skips core columns (`cb`, `title`, `author`, `date`, `comments`). Hook registered, callback correct, panel rendered, feature dead, no error anywhere | Bulk Edit on 2+ MIXED-type listings: the type `<select>` exists AND computes visible. The registered column is not a core column. Applying changes the term for every selected row (verify in DB, not just the list table). Leaving it at "— No change —" reassigns nothing. Quick Edit shares the behaviour. Covered by `regression/bulk-edit-listing-type-renders.md`. |
| D.credit-balance-rest-units | #10186487388 | REST returned a bare `balance` naming neither minor nor major units, so consumers disagreed and a 50-credit purchase displayed as 0.50. An untyped number is the bug: `100` is valid in both readings, so no consumer can detect it | `GET /wbcom-credits/v1/balance` carries `balance_units` (`minor` in money mode, `credits` otherwise), plus `balance_money` + `currency` in money mode; `balance_money * exponent === balance` exactly. Run at TWO exponents - USD (100) AND JPY (1), where major and minor are IDENTICAL and a units bug is invisible. Buy a 50-credit pack through the dummy gateway: dashboard reads 50. `balance` keeps its old meaning (additive only - older app builds read it). Credits awarded via `Credits::award()`, not `topup()` direct. Seed a non-zero, non-round balance; zero passes every assertion. Covered by `regression/credit-balance-rest-declares-its-units.md`. |
| D.map-grid-agree | (1.5.0 smoke) | The grid parsed eleven URL filters through Search_Engine; the map parsed one (`bounds`) and hand-rolled its own SQL. Side by side on the Directory page, `?keyword=cafe` gave a grid reading "No listings found" next to a map still showing pins for non-matching listings. Found by cross-cutting check 8 on its first run | `?type=<real>&keyword=zzzznomatch` -> grid empty state computed-visible AND `mapConfig.markers.length === 0`. `?type=hotel` -> every marker `type==='hotel'`, count equals `COUNT(*) WHERE status='publish' AND listing_type='hotel' AND lat!=0`. Unfiltered -> count equals the geo-join total (capped at `map_max_markers`); it was 73 vs 99 before `has_geo` existed, because filtering for coordinates AFTER paging caps candidates rather than markers. Block-pinned type still beats `?type=`. Map may be a subset of the grid, never a superset. Covered by `regression/map-and-grid-agree-on-the-search.md`. |
| D.favorite-count-live | (1.5.0 smoke) | Save flipped `aria-pressed` and persisted the row while the count beside it kept its server value until reload - the span had no binding. It was also only rendered above zero, so on a 0-favourite listing there was no node to update and the first favourite could never show a count | `.listora-detail__favorite-count` carries `data-wp-text="state.favoriteCountDisplay"`. On a non-zero listing: toggle -> +1 with no reload, toggle back -> restored. On a ZERO listing: node EXISTS but hidden, toggle -> computed-visible reading 1, toggle back -> hidden. Displayed figure equals `SELECT COUNT(*) FROM favorites WHERE listing_id=%d` after each toggle. Test BOTH arrival states (already-favourited and not) - base and delta cancel if you only test one. Covered by `regression/favorite-count-updates-without-reload.md`. |
| D.webhook-review-rating | (1.5.0 smoke) | Pro's review webhook read `$review['rating']` off a raw row; the COLUMN is `overall_rating` and `rating` is the REST field name, so `(float) null` shipped `rating: 0` on EVERY review webhook ever delivered. The `$review ?` guard checks the row exists, not the key | (Combo.) outgoing_webhooks ON. `build_review_payload()` returns `rating` equal to the row's `overall_rating`; submitting a review logs no `Undefined array key` from that file; the delivered JSON matches. Use a non-zero rating - a 0-rated fixture makes broken and fixed identical. Covered by Pro `regression/webhook-review-payload-carries-real-rating.md`. |
| D.search-empty-candidate-set | (1.5.0 smoke) | `array_fill(0,0,'%d')` renders `IN ()` - invalid SQL, logged as `WordPress database error` (neither fatal nor warning). Fired live twice from Pro's saved-search alert cron. Empty-in-empty-out is the CORRECT answer, so it produced a logged error and a wasted query, not wrong data - which is exactly why it survived | Invoke `filter_by_taxonomy([], tax, term)`, `add_taxonomy_facets($f, [], [])`, `Facets::taxonomy_facets([])` - each returns the empty/unchanged result and adds ZERO `WordPress database error` lines. An empty multi-value `field_filters` entry behaves as NO filter (not match-nothing) - a cleared checkbox group must not blank the page. `grep array_fill includes/search/*.php`: every site has an `empty()` guard or builds from a hardcoded array. Non-empty paths unchanged. Covered by `regression/search-empty-candidate-set-no-sql-error.md`. |
| D.modal-focus-trap | (1.5.0 smoke) | Tab walked out of an open modal into the page behind it while it stayed open and blocking - 7 Tabs from the Claim modal landed on the site footer. Escape, backdrop-click and focus-return all worked, so the modals read as complete; `assets/js/shared/confirm.js` had the trap all along and the detail family never adopted it | Open Claim/Report/Login. Press REAL browser Tab more times than the modal has focusables: `dialog.contains(document.activeElement)` stays true throughout. Shift+Tab from first wraps to last. Escape closes, focus returns to the trigger (focus the trigger BEFORE clicking - a programmatic `.click()` never focuses it and focus-return then looks broken), `body.style.overflow` restored. Open/close 3x then Tab on the page - the keydown listener must be gone. A dispatched `KeyboardEvent` does NOT move focus, so a synthetic test passes against broken code. Covered by `regression/modal-traps-focus.md`. |
| D.detail-tab-review-hooks | (1.5.0 smoke) | The detail Reviews tab renders its own markup and fired only the review-FORM hook, never `wb_listora_review_after_content` - so on the most-visited review surface, photo uploads worked and the photos were never shown back. Second bug from that split, after the 'Former member' author fix | (Combo, photo_reviews ON.) `grep` shows tabs.php fires BOTH review hooks. A listing whose approved reviews carry photos renders one `.listora-review-photos__gallery` per such review, each holding computed-visible `<img>`s. Toggle OFF removes them from BOTH surfaces. TRAPS: seeded demo reviews store image URLs, but the upload path stores attachment IDs and `absint('https://...')` is 0, so the gallery renders empty on unrepresentative data; and the review list is cached, so flush after editing `photos` directly. Covered by `regression/detail-tab-fires-review-hooks.md`. |
| D.dashboard-badge-all-statuses | (1.5.0 smoke) | The My Listings badge summed 4 statuses while the rows query used 8, so a member whose only listing was `listora_payment` ("Awaiting Credits") saw "My Listings 0" above that listing. Fourth counter-vs-list instance this release | Sign in as a member whose ONLY listing is in a non-headline status: badge equals rendered rows. Seed one listing per status - badge equals 8, panel renders 8. `grep 'listings_statuses = array'` returns exactly ONE definition. A stats transient written by an older build lacks `total` and must fall back to the four-status sum rather than rendering 0. TRAP: an all-`publish` member passes while the bug is present. Covered by `regression/dashboard-badge-counts-every-status.md`. |
| D.paused-listing-route-forward | #10194590910 | A member with 115 credits saw "Paused - credits needed", no plan, no cost, and a Buy credits CTA opening an empty store. Three defects: the UI read only `_listora_pending_plan_id`, the resume sweep matched only that key too (so NO top-up could ever resume it), and resuming was only ever triggered BY a top-up (so a member who already had enough had no trigger at all) | Enough credits -> plan + cost named, CTA is **Activate now**; clicking publishes the listing, deducts EXACTLY the plan cost, clears the pending meta, and logs ZERO console errors (the first build activated server-side then threw on a missing toast helper, so it looked inert). Short -> "Short by N" + buy CTA + top-up auto-resumes. Plan DELETED -> says so, tells them to contact the owner, and shows NO buy CTA. `resolve_paused_plan_id()` falls back to `_listora_plan_id`. `POST /listings/{id}/activate-plan` returns 401/403/409 for logged-out / not-owner / not-paused / no-plan, and never deducts on failure. Covered by `regression/paused-listing-always-has-a-route-forward.md`. |
| D.detail-header-duplicates | #10194590939, #10194590988 | The detail header said things twice: Free's Verified chip plus Pro's Verified pill, and an address line that appended city/state already inside the stored `address` ("…, NY 10013, Manhattan, NY") | Exactly ONE chip reading "Verified"; Featured / Top Rated still render (Free does not draw those on detail, so suppressing them would hide a real badge); toggling verification off clears both. Address renders once for a Places-style full line, still gains city/state for a bare street, and "NY" is not suppressed inside "Nyack Road" (word boundaries). Note the native badge set differs per surface - Free draws `featured` on CARDS but `verified` on DETAIL - so the card suppression list could not be reused. Covered by `regression/detail-header-no-duplicate-chips.md`. |
| D.rtl-twin-exists | (1.5.0 smoke) | `mark_styles_rtl()` marked EVERY Listora handle for RTL replacement without checking the twin existed, so on an RTL locale WordPress requested a `-rtl.css` for the three theme bridges (which have none) and the browser took a 404 on every pageview. Invisible to a markup or visual check - the base RTL stylesheets carry the mirroring, so the page looked right | Set locale to `ar`: `documentElement.dir === 'rtl'` and EVERY Listora stylesheet returns 200, read from the Network panel or a HEAD fetch (never inferred from the console, never from the page looking correct). All genuine twins still load - suppressing the 404 by dropping RTL would be a worse fix than the bug. The bridge loads its LTR file unswapped. Adding a twin opts that file in; removing it opts back out. A src outside `content_url()` (CDN) is still marked, because skipping on "cannot prove it exists" would silently drop RTL. Covered by `regression/rtl-twin-only-claimed-when-it-exists.md`. |

Rule: every customer-visible fix adds a D row in the same PR. After 2 clean releases, a D row graduates into C/E.

---

## E - Pro-only flows (combo mode)

Each Pro extension gets a customer contract. Run only when `wb-listora-pro` is active. Pro has **30 feature toggles** (`wb_listora_pro_features.*`; `monetization` added in 1.2.0, default OFF on new installs). Section E walks every customer-facing toggle plus the always-on infrastructure ones.

For toggle-able features, every E row has TWO assertions:
- **Toggle ON** - feature renders / works as documented.
- **Toggle OFF** - feature absent (no PHP fatal, no JS error, no orphan UI element, no leftover REST route).

Set toggles via `Settings → Pro Features` admin page OR `wp option patch update wb_listora_pro_features <key> 1|0`.

### E.compare (toggle: comparison)
**What to verify:** Pro's comparison block on `/compare-listings/?compare=ID,ID` renders a side-by-side table for 2-4 listings. Empty state with 0-1 selected. "Remove" button on each column updates URL + table. Floating compare bar persists via localStorage across page navigations. Toggle off → block server-renders nothing; the auto-created Compare Listings page shows the empty Gutenberg fallback.

### E.monetization (toggle: monetization, default OFF on new installs — 1.2.0 owner decision)  `[CORE]`
**What to verify:** (Combo.) One toggle gates the whole monetization unit: `credit_system` + `pricing_plans` + `coupons` + `webhook_receiver` feature classes, the `/credits/*` + `/coupons/*` + `/webhooks/payment` REST routes, Receipt/Credits_Admin/Business_Details self-boots, Credit_Notifier, and the credit-purchase block. **Toggle OFF (fresh-install default):** dashboard has NO Credits tab and NO Buy Credits CTAs (Free's `wb_listora_show_credits` filter answered false at `wb-listora-pro/includes/class-pro-plugin.php:68`); Add Listing wizard has NO Plan step and a logged-in member completes a submission end-to-end free; `GET /wp-json/listora/v1/credits/balance` and `POST /webhooks/payment` → 404; the Buy Credits page is NOT auto-created on activation; credit-purchase block renders nothing. **Toggle ON:** Credits tab + Plan step return, routes register, and the Buy Credits page is auto-created on the explicit OFF→ON flip (`wb_listora_pro_ensure_monetization_pages`). **Upgrade preservation:** an option saved WITHOUT the `monetization` key (pre-1.2.0 install) resolves to ON and the init@1 bootstrap persists `monetization=true` — existing installs completely unaffected. Covered by Pro `regression/monetization-default-off.md`.

### E.credit-system (toggle: monetization — gated with the monetization unit since 1.2.0)  `[CORE]`
**What to verify:** with monetization enabled, a member visiting the resolved dashboard URL at `#credits` sees their balance, a transaction history table, and (where direct-pack purchase is configured) a Buy Credits button. Buying via Stripe / PayPal / WooCommerce flow correctly adds credits and writes a `listora_credit_log` row. Admin can manually add credits via Pro admin → Credit Transactions.

### E.pricing-plans (toggle: monetization — gated with the monetization unit since 1.2.0)
**What to verify:** Listora → Pricing Plans CPT admin page lists plans. Submission wizard's Plan step shows enabled plans with correct credit costs. Selecting a paid plan and submitting deducts credits at the documented rate. `wb_listora_listing_expiration_date` filter sets expiry per plan (Pro listener overrides Free's default).

### E.coupons (toggle: monetization — gated with the monetization unit since 1.2.0)
**What to verify:** admin can Create Coupon at `admin.php?page=listora-coupons&coupon_action=add` - page renders form, NOT blank (per 2026-05-09 fix `de4b79b`). Coupon redeems on a paid plan and reduces the credit deduction. Edit and Delete also work. Generate Code utility produces unique uppercase codes.

### E.outgoing-webhooks (toggle: outgoing_webhooks)
**What to verify:** admin → Webhooks page - admin adds a webhook URL with selected events (`listing.approved`, `listing.rejected`, `listing.expired`, `claim.submitted`, etc.). Triggering an event delivers a POST to the URL with the documented payload (signature header included). Delivery log shows status code per attempt. Failed deliveries retry per Action Scheduler.

### E.webhook-receiver (toggle: monetization — gated with the monetization unit since 1.2.0; inbound payments)
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
**What to verify:** admin promotes subscriber → moderator via Pro admin → Moderator. New `listora_moderator` role granted moderation caps. Moderator visiting the resolved dashboard URL at `#moderator-queue` (or the moderator-queue block on a public page) sees ONLY items assigned to them. Bulk reassign from admin → receiving moderator gets email. Moderator-only audit log endpoint clamps `user_id` filter to the requesting moderator.

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

### E.pro-version-lockstep  `[CORE]`
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
