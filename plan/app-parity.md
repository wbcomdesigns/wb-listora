# Plugin ↔ app functionality catalogue

**Living document.** Update a row in the PR that moves it. The HTML visualisation beside this file
(`app-parity.html`) mirrors these tables — regenerate it when the numbers move.

| | |
|---|---|
| **Plugins** | Free + Pro **1.5.0** |
| **App** | `~/apps/listora-app` 1.0.0 · `main` |
| **Last verified** | 2026-08-08 |
| **Live routes** | 117 (`GET /wp-json/listora/v1`) |
| **Called by the app** | 36 (was 20 at the first audit) |
| **App — Free member-facing gaps** | **0 of 9 — all closed** |
| **Plugin — Free findings still open** | **7 of 19** · 3 partial · 1 unproven · 8 fixed |
| **Plugin — Pro findings still open** | **12 of 30** · 2 partial · 1 unverified · 15 fixed |

## Why this file lives here

The capability catalogue is **plugin-owned** (`CAPABILITIES.md` in both repos, per rule 7 of the
`wbcom-mobile-app` skill). The app never re-enumerates features — it maps coverage against this
spine. Keeping the parity view beside the catalogue is what stops the two lists drifting.

The companion release gate lives in the app repo at `docs/FEATURE-COVERAGE.md` and blocks release on
any remaining ❌ row.

**This file now covers both directions.** It used to answer only "what does the app not have yet". It
also has to answer "what does the plugin still owe", because several app rows sit at Deferred for
plugin reasons rather than app ones.

## Method

| Source | Used for |
|---|---|
| `wb-listora/CAPABILITIES.md` (113 rows) + `wb-listora-pro/CAPABILITIES.md` (167 caps) | The capability spine |
| Live `GET /wp-json/listora/v1` | Ground truth for routes. The plugin's own catalogue warns the manifest is unreliable here, so the index is probed directly. |
| `audit/GAP_AUDIT_2026-08-06.md` in both repos | The plugin-side finding list |
| App `api/`, `hooks/`, `app/` | Which endpoints the app actually calls, plus each screen read by hand |

**Confidence is stated per row.** ◆ means re-read in code on 2026-08-08. ◇ means mapped to a commit
message and not re-verified — probable, not proven. That distinction earned its keep immediately:
`d49b7c1` reads like a fix in the log and is card-only, so F-20 was briefly scored Fixed here before
the commit was opened. The install is now seeded to **5,515 listings**, so scale findings can be
observed rather than read from code.

**Status vocabulary:** ✅ Done · ⚠️ Partial · ⚠️ Deferred (reason + target required) ·
🚫 Web-only (by design) · ❌ Missing / Open.

---

## Headline

The app is a **participation** client, not just a discovery one. All nine Free member-facing gaps from
the first audit are closed, and every one was app-side — the endpoints already existed, so no plugin
change was needed to close any of them.

**The remaining work is plugin work, and it is now Free-only.** **All 31 Pro findings have landed.**
Seven Free findings are still open (F-10, F-11, F-15, F-16, F-17, F-19, F-20) plus two partial
(F-12, F-18) and one deliberately declined (F-21). Three reach the app.

**Every plugin finding was re-read in code, not mapped from commit messages** — on 2026-08-08 and
again on the 1.5.0 close-out. That discipline keeps paying: the first pass found F-19 and F-20
scored Fixed while Open and P-19 scored Open while Fixed, a 3-in-22 error rate; the close-out pass
found four more rows where a grep would have lied — P-10, P-15 and P-25 look unfixed because the old
value survives in a **comment**, and P-27's `LIMIT` is a bound placeholder rather than a literal.
Read the code, not the string.

---

## App — Free member-facing

| Capability | Route | Status | Evidence |
|---|---|---|---|
| Browse, search, autocomplete | `/search`, `/search/suggest` | ✅ Done | ◇ pre-existing |
| Map with tiles and markers | `/settings/maps` | ✅ Done | ◇ pre-existing |
| Listing detail, related, services | `/listings/{id}/detail`, `/related`, `/services` | ✅ Done | ◇ pre-existing |
| Favourites | `/favorites` | ✅ Done | ◇ pre-existing |
| Write and rate a review | `POST /listings/{id}/reviews` | ✅ Done | ◇ pre-existing |
| Mark a review helpful | `POST /reviews/{id}/helpful` | ✅ Done | ◇ pre-existing |
| Report a listing or review | `/listings/{id}/report`, `/reviews/{id}/report` | ✅ Done | ◆ enum round-trips |
| Contact a listing owner | `POST /listings/{id}/contact-form` | ✅ Done | ◇ pre-existing |
| Sign in with Application Passwords | `/auth/app-password` | ✅ Done | ◇ pre-existing |
| Bootstrap config | `/settings/app-config` | ✅ Done | ◇ pre-existing |
| Submit a listing | `POST /submit`, `/submit/check-duplicate` | ✅ Done — app `9d37cc9` | ◆ UI — listing created, right type/category/address |
| Edit own listing | `POST /submit/{id}` | ✅ Done — app `684a81c` | ◆ UI — cleared one field, preserved unrendered |
| Renew a listing | `/renewal-quote`, `POST …/renew` | ✅ Done — app `0a840c8` | ◆ UI — all 3 expiry states |
| Claim a business | `POST /claims`, `GET /dashboard/claims` | ✅ Done — app `c8dbcce` | ◆ UI — flipped to "under review" |
| Notifications | `/dashboard/notifications`, `…/read` | ✅ Done — app `b1c2f2f` | ◆ UI — mark-all-read cleared server-side |
| Edit profile | `GET/POST /dashboard/profile` | ✅ Done — app `a70b775` | ◆ UI — survives the bio/description rename |
| My reviews | `GET /dashboard/reviews` | ✅ Done — app `a70b775` | ◆ UI — both tabs, server totals |
| Reply to a review as owner | `POST /reviews/{id}/reply` | ✅ Done — app `a70b775` | ◆ UI — control flipped without reload |
| Delete account | `DELETE /me?confirm=DELETE` | ✅ Done — app `9d37cc9` | ◆ UI + DB — `wp_users` row destroyed |
| Deactivate **and reactivate** | `POST /me/{de,re}activate` | ✅ Done — app `5c6f884` | ◆ was a one-way door; see below |

**Submission was the structural one** — without it the app could never grow the directory it displays.
Photos, opening hours and social links are deliberately post-submit enrichment, not preconditions; see
the app's `docs/UX-SUBMISSION.md`.

### The reactivation trap — why an unused function was not dead code

`reactivateAccount` sat with no caller and read as something to delete. Checking the server first is
what saved it: a deactivated member **still authenticates** (200 on `/settings/app-config` and
`/dashboard/profile`), no payload exposes the deactivated state, and nothing auto-reactivates on next
sign-in. The app was signing them out right after deactivating — so they could log back in, still
deactivated, with no way to find out and no control to undo it. The session is now kept and a
Reactivate control shows while the flag is set.

---

## App — Pro member-facing

Each gates on its own `/app/config` flag; a module never assumes Pro is active. Pro's catalogue marks
**69 capabilities "dark"** — that is the *toggle state of a given site*, not absent product. Saved
searches and the Needs marketplace are switched off on the QA site, so there is nothing to surface.

| Capability | Route | Status | Note |
|---|---|---|---|
| Verification badges | embedded in `/listings/{id}/detail` | ✅ Done | `BadgeRow`, gated on `features.badges`. The matrix once scored this Missing *and* had the endpoint wrong — `/badges` is POST/DELETE only (admin assignment). |
| Owner analytics | `/analytics/listing/{id}` | ✅ Done — app `aa43bf4` | Exercised with the flag on *and* off. Owner-only; 403 otherwise. |
| Credit balance & plans | `/credits`, `/credit-packs` | ✅ Done — app `aa43bf4` | Read-only. No purchase flow — Apple 3.1.1 puts buying on the web. |
| Compare listings | `/compare` | ⚠️ Deferred | Needs multi-select across the grid plus a comparison screen; two listings rarely fit side by side on a phone. Target: after the enrichment wave. |
| Lead form | `/listings/{id}/contact` | ⚠️ Deferred | **Not broken** — Free's `contact-form` still returns 200 and delivers while `lead_form` is ON (verified). The gap is lead *attribution*, which would undercount the `leads` metric. Blocked on plugin card **#10183618407**. |

---

## App — deferred and skipped

Shipping *less* than the plugin is legitimate when it is declared. Shipping something *different* is
not — that is what the faithfulness section exists for.

### Deferred, with a reason and a target

| Item | Reason | Target |
|---|---|---|
| Native push | Needs the plugin's Expo fan-out, and can only be proven on a real build — a dev client cannot show it working | after the Pro wave |
| `business_hours` field | 7 × (open/close/24h/closed) is a screen of its own; optional on every type, so it never blocks a submission | enrichment wave |
| `social_links` field | Repeater UI; optional everywhere | enrichment wave |
| Photo upload on submit | Gallery is optional and listings are useful without it. Note the plugin makes the *featured* image required on the web — see F-08 | enrichment wave |
| GPS on the address field | Written and wired but **unverified** — headless Chrome cannot supply a location, so only the typed fallback is exercised | next simulator run |

**Deferred fields are skipped on create and PRESERVED on edit** — `serialiseMeta` omits keys the form
never rendered, so the app can never erase a value it cannot draw.

### Skipped by design — stays on the web

| Area | Why | Status |
|---|---|---|
| Admin and moderation | 85 capabilities (56 Pro, 29 Free). Site owners administer on the web by design. | 🚫 Web-only |
| Buying credits | Apple in-app-purchase rules make this display-only on mobile. | 🚫 Web-only |
| Imports and competitor migration | Bulk operations belong to the site owner, not the member. | 🚫 Web-only |
| Settings of every kind | The app configures nothing — every setting lives on the website and the app reflects it. | 🚫 Web-only |

---

## Faithfulness — divergence, not absence

This matrix catches **absence**. It does not catch **divergence**: a screen that exists, works, and
shows something the site does not actually say still scores ✅.

**Audited 2026-08-07 — one real bug found and fixed (app `e0b52c4`).**

`api/dashboard.ts` carried `expired` in its status filter. This plugin registers `listora_expired`;
`expired` is not a status it has. The `status` arg has no enum, so the server **ignored** the unknown
value and returned everything — tapping "Expired" showed the member all their listings, published ones
included, with no hint the filter had done nothing. Verified live: `status=expired` returned 4 rows
where the unfiltered list had 3. The same map rendered `listora_payment` as "Payment" where
`Status_Manager` says **"Awaiting Credits"**.

Post statuses are code-registered, not admin-editable, so this is not quite the Career Board
pipeline-stage shape — but the outcome was identical: a screen that existed, worked, and disagreed
with the site.

Clean on re-check: report reasons round-trip against the server enum, review tabs render server totals
rather than `rows.length`, and renewal renders the server's quote rather than recomputing
`can_renew_now`.

---

## Plugin — Free, still half cooked

From `audit/GAP_AUDIT_2026-08-06.md`, re-checked against 1.5.0 on 2026-08-08. Nine of nineteen findings
have landed. `848f2f7` marked most of them in the audit file; **this table differs from it on two rows
(F-09, F-18) where the marker is more generous than the code** — reasons in the note column.

| ID | Finding | Status | Where it stands | Evidence |
|---|---|---|---|---|
| F-06 | Dashboard caps 4 of 6 tabs at 20 rows with no pager, while the stat tile shows the true total | ✅ **Fixed** | All five tabs paginate server-side through one shared `wb_listora_render_pagination()` helper; Claims was refactored onto it. Page size filterable via `wb_listora_dashboard_per_page`. Verified on 5,508 listings / 602 reviews: totals agree with reachable rows, out-of-range clamps to the last populated page. | ◆ browser, commit `63b9c7c` |
| F-08 | Featured-image upload zone is not keyboard-reachable | ✅ **Fixed** | Both the featured-image trigger and the generic renderer's `file` field are real `<button type="button">` now — the second was the same defect on *every* file custom field and was not in the card. Verified with the keyboard: first Tab, 2px `:focus-visible` ring, Enter **and** Space both open the picker, ring survives dark mode. | ◆ browser, commit `0e56462` |
| F-10 | No ban / suspend concept — capability-stripped members can still write | ❌ Open | Application Passwords are minted by core and bypass plugin login gates, so this matters more on mobile than web. | ◆ zero matches in `includes/` |
| F-11 | `listora_payments` fails all three entry points | ❌ Open | No frontend, no admin list, no REST read. | ◆ no payments admin page |
| F-15 | Dead code — 3 unregistered admin renderers, 5 uncalled cap helpers, 4 orphan helpers | ❌ Open | Each renderer resolves to its own definition and nothing else; the five `can_*()` helpers from the 2026-05-07 additive commit still have zero callers. | ◆ ref counts across Free + Pro |
| F-16 | Byte-identical `set_geo_data()` duplicated across two importers | ❌ Open | `Term_Helper` already exists as the place for it. | ◆ both `class-{geojson,json}-importer.php` |
| F-17 | `$wpdb` outside models: reported 40 files, never re-measured | ❌ Open | **Now 49** — it has grown since the audit, not shrunk. | ◆ 49 files |
| F-19 | i18n packaging: `.po`/`.pot` and `.wbcom-i18n.json` ship in the dist | ❌ Open | **Previously scored Fixed here — wrong.** The five i18n commits shipped the *catalogues*; the *packaging* finding is untouched. `.distignore` names none of them, so `.po` sources (10 files, 12,416 lines in tr_TR alone) still ship, and `.wbcom-i18n.json` is stripped only by `build-release.sh:66` — any path reading `.distignore` still ships it. | ◆ `.distignore` read in full |
| F-20 | Abbreviated prices round wrong — 1,500 shows as "2K", 1,499 as "1K" | ❌ Open | `d49b7c1` reads like a fix in the log and is **card-only, no code change**. | ◆ commit is docs-only |
| F-09 | Admin Reviews runs an N+1 over users and posts | ✅ Fixed | Primes both caches for the page: 46 queries → 4 on 50 rows. Note `cache_users()`, **not** `get_users( fields => … )`, which returns trimmed objects and does not populate the cache `get_user_by()` reads — the first attempt at this fix was wrong for exactly that reason. | ◆ `class-admin.php:1368-1375` |
| F-12 | `facet_cache_ttl` wired to dead code; two facet implementations | ⚠️ Partial | The setting *is* consumed and only one `Facets` class remains, but no commit claims it — so this may have been fixed incidentally, or the finding may have described a path I am not reading. | ◆ `includes/search/class-facets.php:39` |
| F-18 | Changelog and readme hygiene | ⚠️ Partial | The changelog half landed. `readme.txt:94` still uses `* Change`, outside the allowed set, in a 3-sentence bullet where the rule allows two. | ◆ `readme.txt:94` |
| F-04 | Search caps candidates at 5,000, so deep pages are unreachable | ✅ Fixed | A page now resolves in SQL when nothing needs the full candidate array: dedicated `COUNT(*)` plus database-side `ORDER BY … LIMIT/OFFSET`. `build_candidate_query()` is shared by both paths so they cannot drift; every sort carries a `listing_id` tiebreak, without which LIMIT/OFFSET repeats and skips rows on the low-cardinality columns. Distance and FULLTEXT relevance both still sort. | ◆ `class-search-engine.php:419,758,792` |
| F-02 | `1.4.2` branch unversioned, no changelog | ✅ Fixed | Now 1.5.0; header and `Stable tag` agree. | ◆ re-read |
| F-03 | `/app/config` never emitted currency formatting | ✅ Fixed | Live response carries all four keys: `currency`, `currency_symbol`, `currency_position`, `decimals`. | ◆ live `GET /settings/app-config` |
| F-05 | Services REST leaked unpublished listings to anonymous callers | ✅ Fixed | `listing_is_viewable()` guards **both** read paths. | ◆ `class-services-controller.php:217,264` |
| F-07 | Orphan purge wired to nothing | ✅ Fixed | Both `Listing_Data_Eraser` and `Search_Indexer` hook `wb_listora_daily_cleanup`. | ◆ `class-listing-data-eraser.php:69`, `class-search-indexer.php:51` |
| F-13 | `reviews`/`claims` lacked the moderation composite | ✅ Fixed | `KEY idx_status_created (status, created_at)` on both tables. | ◆ `class-activator.php:243,290` |
| F-14 | Admin sort by Views materialised the analytics table | ✅ Fixed | Sort moved into `posts_clauses`; counts batch-load in one grouped query. | ◆ `class-listing-columns.php:59,146` |

---

## Plugin — Pro, still half cooked

Thirty-one findings; **all of them have landed** as of 2026-08-08. All three P0s are closed, and the
four that needed a product call (P-11, P-12, P-21, P-22) were decided and implemented — see
`wb-listora-pro/plan/OPEN-DECISIONS-1.5.0.md` for the reasoning, kept because the trade-offs outlive
the decisions.

Two things to carry forward rather than treat as closed:

- **P-28 has an adjacent residual** — the fan-out is fixed, but `rest_list()` still caps at 50 with no
  pager. Same class as the coupons list, smaller blast radius.
- **P-12 was overstated in the audit.** The payments read path was never dead: `Webhook_Receiver`
  uses the table for gateway idempotency. What was genuinely unreachable was refund reconciliation.
  A Payments admin screen is still not built, and nobody has asked for one.

| ID | Finding | Status | Note | Evidence |
|---|---|---|---|---|
| P-05 | Transactions CSV loads the entire credit ledger into memory | ✅ Fixed | Streams 2,000 rows at a time after the headers are sent; it materialised 40,071 rows at 48 MB before writing a byte. | ◆ `class-pro-plugin.php` |
| P-06 | Multi-criteria reviews runs an unbounded query per listing on Free's search hot path | ✅ Fixed | Cached on Free's reviews incrementor via the new `cache` service. Measured 24 queries per search page → **0 warm**, 1 cold. | ◆ `class-multi-criteria-reviews.php:243` |
| P-11 | `saved_searches` table has 0 of 3 entry points | ✅ Fixed | Table retired at schema 1.13.0; rows folded into the `_listora_saved_searches` user meta the feature actually reads. All 5 migrated rows now return from `GET /saved-searches` — which the table never did. The privacy eraser now sweeps that meta too, closing a live GDPR gap on the erasure path. | ◆ `class-pro-migrator.php:294`, `class-personal-data-tools.php:244` |
| P-12 | `payments` table has no admin list, no REST read; the refund path cannot reach it | ✅ Fixed | New `payments.ledger_id` joins a ledger row back to its payment, so the refund modal sends a real id. Verified: a refund wrote `status=refunded` + the three refund columns that had never been reachable; a repeat returned 409. **The read path was never dead** — `Webhook_Receiver` uses the table for gateway idempotency; the audit overstated that. A Payments admin screen is still not built. | ◆ `class-pro-plugin.php` `attach_payment_ids()` |
| P-15 | Bulk moderator reassign calls `wp_cache_flush()` | ✅ Fixed | Invalidates only the affected posts. The sole remaining mention is a comment explaining the old behaviour. | ◆ `class-moderator.php:1261` |
| P-21 | Setup Wizard is the only Pro admin surface on `manage_options` | ✅ Fixed | Moved to `manage_listora_settings` — **6** checks, not the 5 first counted; the activation redirect had one. Verified an editor holding only that capability now reaches it. | ◆ `class-setup-wizard.php`, `class-activation-redirect.php` |
| P-22 | License enforcement does not exist | ✅ By design, documented | ADR-004 position now written into Pro's CLAUDE.md rather than left to be refiled. `License::is_valid()` is the canonical name — it was a **rename**, not new logic: `is_active()` already checked key + status + expiry. Verified across five licence states that `is_valid()`, `is_active()` and `app_enabled` agree. | ◆ `class-license.php:318` |
| P-25 | Invisible focus indicator on the compare-bar remove button | ✅ Fixed | Draws a real ring via `--listora-focus-ring`. The `outline: 2px solid transparent` that remains is the deliberate forced-colors/HCM pattern, not the bug. | ◆ `pro-frontend.css:2560` |
| P-26 | `uninstall.php` leaves the feature-toggle option and others behind | ✅ Fixed | Sweeps the whole `wb_listora_pro_` namespace instead of a hand-list that had drifted by 11 options. | ◆ `uninstall.php:78` |
| P-27 | Rate-limit GC deletes without a `LIMIT` | ✅ Fixed | `DELETE … LIMIT %d` bound to 500 per sweep. | ◆ `class-public-rate-limiter.php:218` |
| P-28 | Outgoing webhooks silently cap at 50 subscribers per event | ✅ Fixed | Fan-out is unbounded and subscribers resolve once per request; subscriber 51+ never fired before. **Residual, adjacent:** `rest_list()` still returns 50 with `no_found_rows` and no pager, so an owner with more than 50 webhooks cannot page past the first 50 over REST. | ◆ `class-outgoing-webhooks.php:686` fixed / `:1170` residual |
| P-31 | Manifest and CLAUDE.md are materially stale | ✅ Fixed | Both refreshed to 1.5.0, and re-synced after the schema-1.13.0 wave dropped `saved_searches`. | ◆ `audit/manifest.json` |
| P-10 | Audit-log CSV silently truncated to 100 rows | ✅ Fixed | Pages through the log instead of asking past `query_log()`'s clamp. The `5000` still in the file is a comment describing the old behaviour. | ◆ `class-audit-log.php:912` |
| P-23 | Six JS fetch sites with no AbortController/timeout | ✅ Fixed | Down to one deliberate exception: `analytics-tracker.js` uses `fetch` + `keepalive` fire-and-forget so the beacon survives page unload — a timeout would defeat it. | ◆ per-file scan |
| P-24 | Need-respond marks `message` required but nothing enforces it — and charges credits anyway | ✅ Fixed | Empty and whitespace-only messages are rejected before the credit hold. Both `"   "` and `""` were accepted before the guard. | ◆ `class-need-response-manager.php:92` |
| P-02 | Any Author could mint a free pricing plan; any Editor could delete all paid plans | ✅ Fixed | Plan CPT moved onto primitive Listora caps, with a documented escape hatch. | ◆ `class-pricing-plans.php:966+` |
| P-03, P-04 | Toggled-off features still registered blocks and rendered admin surfaces | ✅ Fixed | The feature manager's toggle gate skips loading entirely; monetization surfaces gate on `monetization_enabled()`. | ◆ `class-feature-manager.php:101`, `class-pro-plugin.php:132,247` |
| P-07 | Three needs routes compared against `post_author` without asserting login | ✅ Fixed | `is_user_logged_in` now guards the controller in four places. | ◆ `class-needs-controller.php` |
| P-08 | Stale Free-version guard | ✅ Fixed | `WB_LISTORA_PRO_MIN_FREE_VERSION` is 1.5.0, enforced by `version_compare`. | ◆ `wb-listora-pro.php:40,130` |
| P-09 | Pro's proxy-header filter named differently from Free's, so CDN sites mass-429 | ✅ Fixed | Pro now defaults to whatever Free's `wb_listora_trust_proxy_headers` resolved. | ◆ `class-public-rate-limiter.php:288,302` |
| P-13 | Needs dashboards run unbounded `SELECT *` inside foreach loops | ✅ Fixed | Counts batch through `Need_Response_Manager::get_counts_for_needs()` on a plucked ID list. | ◆ `class-needs-dashboard-tab.php:105` |
| P-14 | Missing indexes on the queries that actually run | ✅ Fixed | 34 `KEY idx_*` definitions in the migrator. | ◆ `class-pro-migrator.php` |
| P-16 | Moderators page: unbounded `get_users()` ×2 and a queue count that lies above 500 | ✅ Fixed | Queue totals are `COUNT(*)`, batched. | ◆ `class-moderator.php:835,866` |
| P-17 | Transactions admin page: 4 uncached full-table aggregates per view | ✅ Fixed | Summary totals cached, with invalidation on every canonical credit event. | ◆ `class-pro-plugin.php:60,508,1277` |
| P-18 | Coupons admin list capped at 50, no pagination, count always lies | ✅ Fixed | `get_usage_log_paginated()` with page/per_page and a separate `COUNT(*)`. | ◆ `class-coupons.php:510,553` |
| P-19 | Receipt HMAC tokens never expire and are not revocable | ✅ Fixed | **Previously scored Open here — wrong.** `issued_at` is signed into the payload so it cannot be edited to extend a link, and expiry is enforced on verify. | ◆ `class-receipt.php:175-233,262` |
| P-20 | `wb_listora_pro_view_analytics` was a phantom capability | ✅ Fixed | No longer referenced anywhere. | ◆ zero matches |
| P-29 | `1.4.2` branch unversioned | ✅ Fixed | Now 1.5.0. | ◆ re-read |
| P-30 | `CHANGELOG.md` has no 1.4.1 entry | ✅ Fixed | Both `[1.5.0]` and `[1.4.1]` present. | ◆ `CHANGELOG.md:5,31` |

---

## What actually blocks the app

Of everything above, only these three reach the app. P-22 used to sit here; it does not any more — `License::is_valid()` now exists (as a rename, not new logic) and `app_enabled` has tracked licence validity since 1.2.3, so the premise was wrong.

| Plugin gap | What it costs the app | Card |
|---|---|---|
| Statuses and labels are not published in `/settings/app-config` | Every client carries its own copy of the status map — exactly how the "Expired" filter bug happened. Publishing them from the same `Status_Manager::custom_statuses()` map that already drives `register_post_status()` is the only fix that prevents recurrence. | #10182473304 |
| No answer on which route the app posts to when Pro `lead_form` is ON | Holds the lead-form row at Deferred. Messages deliver either way; the risk is silent under-attribution of the `leads` metric. | #10183618407 |
| Add Listing is unsubmittable for a type with no allowed categories | A hard block on both surfaces. QA filed it as a data note; it is not one. | #10180373117 |

---

## Catalogue drift to fix

| File | Says | Reality |
|---|---|---|
| `wb-listora/CAPABILITIES.md` | Generated 2026-07-15, plugin 1.2.2, 98 live routes | Plugin is **1.5.0**, **117** live routes |
| `wb-listora-pro/audit/manifest.json` | ~~`1.4.1`~~ | **Current** — 1.5.0, re-synced after the schema-1.13.0 wave |
| `wb-listora-pro/audit/GAP_AUDIT_2026-08-06.md` | Fix markers lag | **All 31** Pro findings have landed; this board is the current view |

Both `CAPABILITIES.md` files need a `/wp-plugin-onboard --refresh` pass; until then this file is the
current view.

---

## App flow run — 2026-08-08

Every member flow exercised against the live API with a real Application Password, which is the
layer the app itself calls. Test data removed afterwards (listing 6761, review 14548, claim 49, the
profile edit) and both app passwords revoked.

| Flow | Result |
|---|---|
| Bootstrap — `/settings/app-config`, `/settings/maps` | ✅ 200, currency quartet + `tile_url` present |
| Discover — `/search`, `/search/suggest`, `/listing-types` | ✅ 200 |
| Submit — check-duplicate → `POST /submit` | ✅ 201, `status=pending`, correct type/category/address |
| Edit own listing — `POST /submit/{id}` | ✅ 200, title round-trips |
| My listings — `/dashboard/{stats,listings}` | ✅ 200, totals agree with the filters |
| Favourites — POST / GET / DELETE | ✅ 201 → total 1 → 200 → total 0 |
| Renewal — `/listings/{id}/renewal-quote` | ✅ 200 |
| Claim — `POST /claims` + `/dashboard/claims` | ✅ 201 "under review", appears in the list |
| Review — `POST /listings/{id}/reviews`, report | ✅ 201 `status=pending`; report 200 |
| Notifications — GET + mark-all-read | ✅ 200 |
| Profile — GET/POST, bio round-trip | ✅ 200, value returned verbatim |
| Deactivate → still authenticates → reactivate | ✅ 200 / 200 / 200 — the one-way door is closed |

**Two guards fired correctly and are not bugs:** `helpful` and `reply` both 403 on a
freshly-posted, still-`pending` review by its own author.

### The `status` enum now exists — the faithfulness bug's root cause is closed

When the "Expired" filter bug was found, `status` had **no enum**, so the server silently ignored an
unknown value and returned everything. That is no longer true. `status=expired` and any other unknown
value now return **400 `rest_invalid_param`**, naming the allowed set:

```
'', draft, pending, publish, listora_rejected, listora_expired,
listora_deactivated, listora_payment, pending_verification
```

This matters more than the original fix: a drifting client can no longer fail quietly. All seven
values in `DASHBOARD_STATUS_FILTERS` were re-checked individually against the live enum — **all 200**.

`pending_verification` is valid server-side and is **not** offered as an app filter, though the app's
label map does cover it, so a listing in that state still renders with the right words. Worth a
deliberate decision rather than leaving it implicit.

### Card #10180373117 — found, and my first reading of it was wrong

`GET /listing-types/{slug}/categories` returns **0 for `business`** and 8–15 for all nine other types.

| Type | Categories |
|---|---|
| **business** | **0** |
| restaurant / event / job / real-estate / hotel / place / classified / education / healthcare | 15 / 10 / 12 / 8 / 8 / 11 / 11 / 9 / 10 |

I first reported this as "every `business` submission 400s through the API". **That was wrong** — the
400 came from my own test script sending `"category":none`, which is not valid JSON. Re-tested with a
well-formed body and no `category` key at all: `POST /submit` returns **201**. The server accepts it.

The real fault was client-side and narrower but worse for the member. `step-basic.php:43` suppresses
the Category field only when the type is known at render time; in the wizard it never is, so the
select printed unconditionally with `required`, and `view.js` was the only thing that ever filled it.
For a type with no categories it stayed at the bare placeholder while still `required` — a control
with nothing to pick, refusing to let anyone past Basic Info, with no message explaining why.

**Fixed** — `syncCategoryApplicability()` in `src/blocks/listing-submission/view.js` enforces one
invariant: required if and only if there is something to pick, applied on the reset, success and
failure paths. Browser-verified: Business now reaches step `details`; Restaurant still shows the
field, still blocks on empty, still passes when chosen; switching Restaurant → Business re-hides it
with no stale `required`; no overflow at 390px. Sentinel:
`regression/submission-category-optional-when-none.md`.

**An empty `allowed_categories` is a legitimate configuration, not a data bug** — so seeding business
categories would have masked this rather than fixed it, and left the next such type to dead-end again.

**Not covered by this run:** anything above the API — rendering, navigation, offline behaviour,
gestures, and the GPS control. Those need a simulator; this run proves the contracts behind the
screens, not the screens.
