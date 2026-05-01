# WB Listora (Free) — Issues + Flow Verification

**Generated**: 2026-04-29 from `audit/manifest.json`
**Source of truth**: this file is the work plan. Tick items as you wrap them.
**Verification**: every fix needs a browser flow test before close. **No "fixed in code" without a flow check.**

---

## 🔴 P0 — Security / DoS (block release)

- [x] **F-01** `/listings/bulk` rate limit — **shipped @ `145cfd4` (PR #36, 2026-05-01)**. 30/min IP, 120/min user via existing `WBListora\Rate_Limiter`. Verified: 35-burst → 30 × 200, 5 × 429.

- [x] **F-02** `/submission/resend-verification` throttle — **shipped @ `f30c493` (PR #37, 2026-05-01)**. Per-listing 5-min cooldown was already there inside `Email_Verification::resend_verification` (RESEND_COOLDOWN); added IP cap 30/hour as defence-in-depth against ID-probing scrapers. Plan's "1/min, 5/day per email" was already covered by the existing cooldown (effectively ≤12/hour per listing).

- [x] **F-03** `/search` + `/search/suggest` rate limit — **shipped @ `a7596b7` (PR #38, 2026-05-01)**. 60/min IP for search, 30/min IP for suggest. Verified bursts on both. Frontend's existing 250ms debounce keeps legitimate typing well below the cap.

- [x] **F-04** `/listings` per_page cap — **already enforced by `WP_REST_Posts_Controller`** (verified 2026-05-01). per_page=200 → 400 `must be between 1 (inclusive) and 100 (inclusive)`. The plan's stated "20 default" is a UX preference (WP core defaults to 10) and not a security gap. No code change.

- [x] **F-05** ~~No moderator role registered~~ — **MISCLASSIFIED, not a Free bug.** Code-verified 2026-04-30 PM:
  - `wp role list` shows `listora_moderator` already registered.
  - Pro registers it on activation (`wb-listora-pro/includes/class-activator.php:51` calls `Features\Moderator::register_role()` which adds the role with all moderator caps and grants admin extra caps).
  - The `manage_listora_moderators` cap is a Pro-side admin extra cap — it doesn't exist in Free's `Capabilities::get_caps_map()` and shouldn't, because moderation is a Pro feature.
  - Free's role-cap registration is correct: admin/editor get `moderate_listora_reviews` for basic review moderation; the full moderator role is layered on by Pro per the Free→Pro upscale model.
  - **No code change needed.** Marking complete to unblock P-06..P-09 in `wb-listora-pro/plan/release-issues-and-flow-tests.md`.

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

- [x] **F-30** $wpdb audit catalogue — **shipped @ `f4d0ab9` (PR #43, 2026-05-01)**.
  - Plan estimated "3 tables violating" — reality is **31 files**. Plan's framing was wrong.
  - **0 release-blocking misuses.** All access is gated by cap/nonce at the right boundary. Most direct `$wpdb` use is legitimate: REST controllers (8) own their resource access, search engine (4) needs FULLTEXT/Haversine, importers (7) are bulk legacy-schema tools, cron+workflow (2) are read-only stat aggregations.
  - Cosmetic cleanup deferred to 1.0.x: move `core/class-services.php`, `class-listing-data.php`, `class-listing-limits.php` into `includes/services/` (no behaviour change, just folder).
  - P3 post-1.0.0: extract `Reviews_Service` + `Claims_Service` from `admin/class-admin.php`.
  - Full per-file catalogue with role + operations: [`audit/WPDB_AUDIT_2026-05-01.md`](../audit/WPDB_AUDIT_2026-05-01.md).

- [x] **F-31** consumed_by audit — **shipped @ `fe26601` (PR #44, 2026-05-01)**.
  - Cross-referenced every Pro `add_filter`/`add_action` call site against Free's `hooks_fired[]`. 47 entries Pro listens to but were undocumented: now marked `consumed_by: ["wb-listora-pro"]`.
  - Orphan count: 153 → 120. Total fired hooks: 184 (plan said 162; manifest grew with later commits).
  - The 120 remaining nulls are **intentional public extension surface** (REST response filters, before_/after_ write hooks Pro doesn't extend, cancellable pre-action filters). Not dead code.
  - Full methodology + reproducer: [`audit/HOOKS_CONSUMED_BY_AUDIT_2026-05-01.md`](../audit/HOOKS_CONSUMED_BY_AUDIT_2026-05-01.md).

- [x] **F-32** Tables-vs-services ratio — **resolved by F-30 audit (no separate code change)**, 2026-05-01.
  - Plan flagged `hours`, `analytics`, `payments` as needing services. Code-verification:
    - **`hours`**: Free defines the schema only — it has zero `$wpdb` calls against this table (`grep -rln 'listora_hours' includes/` returns no Free file). Pro's `class-field-auto-detector.php` is the only consumer.
    - **`analytics`**: One Free read in `workflow/class-expiration-cron.php` (single SELECT for retention checks). Pro's `Analytics` and `Moderator` classes are the primary writers.
    - **`payments`**: Zero Free `$wpdb` calls. Pro's `Webhook_Receiver` and pricing-plan flow own all writes.
  - These are **intentionally schema-on-Free / logic-on-Pro tables**. Adding a Free-side service for tables Free barely touches would be indirection without payoff. F-30's verdict (no release-blocking misuses) covers this.
  - Service-shaped read aggregators that should move to `services/` post-1.0.0 (cosmetic): `core/class-listing-data.php`, `core/class-listing-limits.php` — both are read-only stat aggregators. Tracked in F-30 audit.

- [x] **F-33** Free REST audit — **shipped @ `781c930` (PR #42, 2026-05-01)**.
  - AST-walk: 49 `register_rest_route` invocations + 1 inherited from `WP_REST_Posts_Controller::register_routes()` (via `parent::register_routes()` in `Listings_Controller`) = **50 live routes, 50 manifest entries** post-fix. Plan's "51 actual" was a raw-grep overshoot.
  - Manifest was missing 2 real routes — added: `POST /listings/{id}/deactivate` and `GET /listing-types/{slug}/categories`.
  - Full audit + reproducer: [`audit/REST_AUDIT_2026-05-01.md`](../audit/REST_AUDIT_2026-05-01.md) (returns `missing=[] stale=[]`).

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
