# WB Listora (Free) — Issues + Flow Verification

**Generated**: 2026-04-29 from `audit/manifest.json`
**Source of truth**: this file is the work plan. Tick items as you wrap them.
**Verification**: every fix needs a browser flow test before close. **No "fixed in code" without a flow check.**

---

## 🔴 P0 — Security / DoS (block release)

- [ ] **F-01** `POST /listora/v1/listings/bulk` — `__return_true` allows anyone to fetch 50 listings/req. Add per-IP rate limit (5 req/min unauth) or require nonce + logged-in user.
  - File: `includes/rest/class-listings-controller.php`
  - Verify: `curl -X POST .../listings/bulk -d '{"ids":[1,2,3]}'` → expect 429 after burst

- [ ] **F-02** `POST /listora/v1/submission/resend-verification` — `__return_true`, can spam verification emails. Add per-email throttle (1/min, 5/day).
  - File: `includes/rest/class-submission-controller.php`
  - Verify: trigger 6 times in a minute → expect block + audit-log entry

- [ ] **F-03** `GET /listora/v1/search` + `/search/suggest` — public, no rate limit, hits DB on every keystroke. Add per-IP token bucket (60/min, search) and (30/min, suggest).
  - Verify: hammer with `ab -n 200 -c 10 .../search?q=test` → expect 429s without server collapse

- [ ] **F-04** `GET /listora/v1/listings` — inherits `WP_REST_Posts_Controller`; verify `per_page` cap is enforced (20 default, 100 max).
  - File: `includes/rest/class-listings-controller.php`
  - Verify: `curl .../listings?per_page=500` → expect 100 max

- [ ] **F-05** No moderator role registered. `manage_listora_moderators` cap exists but no `add_role('listora_moderator', ...)` call. Admins must hand-assign caps.
  - File: `includes/class-activator.php` (add role on activate)
  - Verify: deactivate → reactivate → `wp role list` shows `listora_moderator`

---

## 🟠 P1 — Core flows (must work before any release)

### Site owner flows

- [ ] **F-06** Setup wizard end-to-end on a fresh install
  - Path: `/wp-admin/admin.php?page=listora-setup`
  - Steps: choose listing type → seed demo → save → confirms wizard complete
  - Verify: `wb_listora_setup_complete = true`, demo listings created, redirect to dashboard

- [ ] **F-07** Listing types CRUD
  - Path: `/wp-admin/admin.php?page=listora-listing-types`
  - Steps: create custom type, edit fields, delete
  - Verify: type appears in submission flow, fields render in detail page

- [ ] **F-08** Categories / Locations / Features taxonomy management
  - Path: `edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing`
  - Steps: add hierarchical categories, assign to listings
  - Verify: filter by category on `/directory/` page returns correct listings

- [ ] **F-09** Reviews moderation page
  - Path: `/wp-admin/admin.php?page=listora-reviews`
  - Steps: list reviews, approve/reject, delete
  - Verify: review status changes reflected in frontend; deleted reviews removed from `wp_listora_reviews`

- [ ] **F-10** Claims approval flow
  - Path: `/wp-admin/admin.php?page=listora-claims`
  - Steps: open pending claim → approve → owner email sent + `post_author` transferred
  - Verify: `wp_listora_claims.status = approved`, listing post_author = claimant

- [ ] **F-11** Settings save + reset for each tab (General, Submissions, Maps, Reviews, Credits, Features, Import/Export)
  - Path: `/wp-admin/admin.php?page=listora-settings`
  - Steps: change setting in each tab → save → reload → value persists
  - Verify: `wp_options.wb_listora_settings` updated; reset returns to defaults

- [ ] **F-12** Health check page surfaces real warnings
  - Path: `/wp-admin/admin.php?page=listora-health`
  - Steps: deactivate cron → reload → cron warning appears
  - Verify: each warning has actionable next step

- [ ] **F-13** Import / Export CSV / JSON / GeoJSON
  - Path: `/wp-admin/admin.php?page=listora-settings&tab=import`
  - Steps: export → modify → import → verify roundtrip
  - Verify: counts match, no data loss, geo coords preserved

- [ ] **F-14** WP-CLI commands (`wp listora stats|reindex|test-email|cleanup`)
  - Verify each subcommand returns sensible output without fatal

### End user flows

- [ ] **F-15** Listing submission (frontend, multi-step)
  - Path: `/submit/`
  - Steps as **logged-out user**: fill form → captcha → email verification → first-time login → listing pending
  - Verify: `listora_listing` post created with `post_status = pending`, `_listora_email_verified = 1`

- [ ] **F-16** Listing submission as **logged-in subscriber**
  - Steps: skip registration, submit → pending or auto-publish based on settings
  - Verify: respects `wb_listora_settings.auto_approve_subscribers`

- [ ] **F-17** Conditional fields work per listing type (Restaurant has cuisine, Hotel has stars, Real Estate has bedrooms)
  - Steps: change type dropdown mid-submission → fields update without page reload
  - Verify: only relevant fields appear; submitted data has only selected-type meta keys

- [ ] **F-18** Draggable map pin during submission
  - Steps: drag pin → lat/lng input updates → save → detail page renders pin at saved coords
  - Verify: `wp_listora_geo` row has correct lat/lng

- [ ] **F-19** Listing detail page renders all tabs
  - Path: `/listing/{slug}/`
  - Tabs: Overview · Reviews · Hours · Services · Location
  - Verify: each tab loads without JS error, services render with images, hours show "open now" when applicable

- [ ] **F-20** Review submission + helpful vote + owner reply
  - Steps: submit review (star + text + photo) → vote helpful (logged-in) → listing owner replies
  - Verify: `wp_listora_reviews.helpful_count` increments, `owner_reply` stored, milestone fires at 10/50/100

- [ ] **F-21** Favorites toggle + collections
  - Steps: heart icon on card → favorite saved → dashboard shows it → remove
  - Verify: `wp_listora_favorites` row created/deleted, in-place toggle without page reload

- [ ] **F-22** Search faceted + geo + fulltext
  - Steps: search "pizza" → filter by category Italian → set radius 5km → sort by rating
  - Verify: results match all filters, facet counts update, geo distances visible

- [ ] **F-23** Search autocomplete suggestions
  - Steps: type 2+ chars → see suggestions dropdown → click → navigates to filtered results
  - Verify: `/search/suggest` debounced (no req per keystroke, 250ms+)

- [ ] **F-24** User dashboard — all tabs (Listings, Reviews, Claims, Credits, Profile, Notifications)
  - Path: `/dashboard/` (or auto-created page)
  - Verify each tab loads, pagination works, edit/delete actions confirmed before destructive op

- [ ] **F-25** Listing renewal flow
  - Steps: trigger expiration cron manually → listing → renewal-quote → renew (uses Pro credits if active)
  - Verify: `_listora_expires_at` updated, status returns to publish, audit entry

- [ ] **F-26** Email verification link from spam folder works (test against expired token)
  - Verify: expired link shows clear "request new link" UX, not generic 404

- [ ] **F-27** Business claim submission
  - Steps: detail page → "Claim" button → upload proof PDF + text → submit
  - Verify: `wp_listora_claims` row, admin notified, claimant sees pending status in dashboard

### Moderator flow (currently broken — no role registered)

- [ ] **F-28** **BLOCKED by F-05.** After moderator role added: assign user → moderator dashboard → review queue → approve/reject
  - Verify: moderator sees only review/claim queue, not full admin

- [ ] **F-29** Moderator sees their own audit trail
  - Verify: `/audit-log` REST endpoint allows `moderate_listora_reviews` cap (currently `manage_listora_settings` only)

---

## 🟡 P2 — Architecture / completeness

- [ ] **F-30** Audit raw `$wpdb` usage outside service classes (3 tables likely violating "no raw $wpdb outside models" rule)
  - Run: `grep -rn "global \$wpdb" includes/ --include="*.php" | grep -v "includes/services/" | grep -v "includes/db/"`
  - Action: route through service or document exception

- [ ] **F-31** 162 hooks fired with no documented `consumed_by` — sweep for hooks that should be removed (dead extension surface) or documented
  - Source: `audit/manifest.json` → `hooks_fired` where `consumed_by == null`
  - Action: prune or document; never just leave unused fired hooks

- [ ] **F-32** Tables vs services ratio mismatch (11 tables / 8 services)
  - Missing services for: `hours`, `analytics`, `payments`
  - Action: create wrapper service or confirm direct access is intentional

- [ ] **F-33** REST coverage gap (49 manifest / 51 actual `register_rest_route` calls — within 5% threshold but still 2 routes missing)
  - Action: locate the 2 missing entries; refresh manifest

---

## 🔵 P3 — Polish / customer-facing UX

- [ ] **F-34** Add per-user notification preferences UI in dashboard (currently 14+ email events fire, user can't opt out per-type)

- [ ] **F-35** Cap `WP_REST_Posts_Controller` `per_page` at 100 explicitly (don't rely on WP defaults)

- [ ] **F-36** "Open now" indicator on cards — verify timezone math for `wp_listora_hours` (off-by-one bugs likely on DST boundaries)

- [ ] **F-37** Listing cards on grid — verify lazy-loading images (no LCP regression)

- [ ] **F-38** Email templates — verify all 14 events have a template + are theme-overridable via `{theme}/wb-listora/emails/`
  - Test: `wp listora test-email` for each event → render correct content

---

## Verification matrix (test before close)

| Flow | Roles to test | Browser | Status |
|---|---|---|---|
| Submission (logged-out → register → verify → submit) | guest → subscriber | Chrome, Safari mobile | ⬜ |
| Submission (logged-in subscriber) | subscriber | Chrome | ⬜ |
| Submission (admin direct) | admin | Chrome | ⬜ |
| Search faceted | guest, subscriber | Chrome | ⬜ |
| Detail page all tabs | guest | Chrome, Safari | ⬜ |
| Review create + helpful + reply | subscriber, owner | Chrome | ⬜ |
| Favorites | subscriber | Chrome | ⬜ |
| Claim flow | subscriber | Chrome | ⬜ |
| Dashboard all tabs | subscriber | Chrome | ⬜ |
| Settings save (each tab) | admin | Chrome | ⬜ |
| Listing types CRUD | admin | Chrome | ⬜ |
| Reviews moderation | admin, moderator | Chrome | ⬜ |
| Claims approval | admin | Chrome | ⬜ |
| Import/Export | admin | Chrome | ⬜ |
| Setup wizard | admin (fresh install) | Chrome | ⬜ |
| Health check | admin | Chrome | ⬜ |
| WP-CLI commands | shell | terminal | ⬜ |
| Email verification | guest | inbox | ⬜ |
| Renewal | owner | Chrome | ⬜ |
| Cron jobs (expiration, draft reminder, cleanup) | shell | wp cron | ⬜ |

---

## Test environment

- **Site**: http://directory.local
- **Auto-login**: append `?autologin=1` (admin), `?autologin=<username>` (any user)
- **WP-CLI**: `wp --path="/Users/varundubey/Local Sites/directory/app/public" listora ...`
- **Reset DB**: `wp db reset --yes && wp core install ...`

## Rules during sweep

1. **No "fixed in code" closes**. Every checkbox needs a browser flow ✓.
2. **PHPStan L7 + WPCS clean** before commit (project rule).
3. **No new fired hooks without a documented consumer** in the manifest.
4. After each P0/P1 batch: refresh `audit/manifest.json` via `/wp-plugin-onboard --refresh`.
