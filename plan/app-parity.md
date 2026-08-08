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
| **Plugin — Free findings still open** | **8 of 19** (+3 partial) |
| **Plugin — Pro findings still open** | **~16 of 30** (+1 partial) |

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

**The remaining work is plugin work.** Eight Free findings and roughly sixteen Pro findings are open;
four of them reach the app.

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
| F-06 | Dashboard caps 4 of 6 tabs at 20 rows with no pager, while the stat tile shows the true total | ❌ Open | A member with 61 favourites sees a tile reading 61 and a list of 20. A vendor with 50 listings can manage only 20 from the frontend. **The paginated REST endpoints already exist** — the block never calls them. | ◆ still `LIMIT 20` at `blocks/user-dashboard/render.php:180,205,218,229` |
| F-08 | Featured-image upload zone is not keyboard-reachable | ❌ Open | A `<div>` with a click handler, no `tabindex`, no `role`, no key handler. The featured image is **required on create**, so submission is blocked outright for keyboard and AT users. | ◆ `templates/blocks/listing-submission/step-media.php:35` |
| F-10 | No ban / suspend concept — capability-stripped members can still write | ❌ Open | Matters more for mobile than web: Application Passwords are minted by core and bypass plugin login gates, so a banned member holding one keeps writing. Skill rule 2 wants 403 on every write. | ◆ zero matches in `includes/` |
| F-11 | `listora_payments` fails all three entry points | ❌ Open | No frontend, no admin list, no REST read on the Free side. A table carrying money with no way to look at it. | ◆ no payments admin page in `includes/admin/` |
| F-15 | Dead code — 3 unregistered admin renderers, 8 uncalled public helpers | ❌ Open | Not re-counted; no commit addresses it. | ◇ not re-checked |
| F-16 | Byte-identical `set_geo_data()` duplicated across two importers | ❌ Open | Exactly the pair that drifts once one importer gets a fix. `Term_Helper` already exists as the place for it. | ◆ still in both `class-{geojson,json}-importer.php` |
| F-17 | `$wpdb` outside models: reported 40 files, never re-measured | ❌ Open | **Now 49.** It has grown since the audit, not shrunk. CLAUDE.md calls this rule non-negotiable. | ◆ 49 files under `includes/ blocks/ templates/` |
| F-09 | Admin Reviews and Claims moderation are N+1 at 50 rows/page | ⚠️ Partial | Marked FIXED in the audit file on the strength of `idx_status_created`. **An index does not remove a per-row query.** Reviews were genuinely batched (`95900f9`); the claims path still loops per proof file. | ◆ `class-claims-controller.php:381` |
| F-12 | `facet_cache_ttl` wired to dead code; two facet implementations | ⚠️ Partial | The setting *is* consumed now and only one `Facets` class remains — but no commit claims this, so it needs a confirming read. | ◆ consumed at `includes/search/class-facets.php:39` |
| F-18 | Changelog and readme hygiene | ⚠️ Partial | Marked FIXED for the changelog entries, which did land. The other half of the finding did not: `readme.txt:94` still uses `* Change`, which is not in the allowed set (New / Improve / Fix / Security / Dev / Compat), in a 3-sentence bullet where the rule allows two. | ◆ `readme.txt:94` |
| F-02 | `1.4.2` branch unversioned, no changelog | ✅ Fixed | Now 1.5.0; header and `Stable tag` agree. | ◆ re-read |
| F-03 | `/app/config` never emitted currency formatting — every price in the app was wrong | ✅ Fixed | One `wb_listora_get_currency_format()` owns symbol, position and decimals; web and native read the same helper. | ◇ `8792196` |
| F-04 | Search hard-capped at 5000; rows past the cap unreachable and `total` lied | ✅ Fixed | Pages resolve in SQL, so the count is honest and every row is reachable. | ◇ `362dd89` |
| F-05 | Services REST leaked unpublished listings to anonymous callers | ✅ Fixed | Public service reads inherit listing visibility. | ◇ `0d31643` |
| F-07 | Orphan purge wired to nothing; deleted listings inflated search counts | ✅ Fixed | Runs on the daily cleanup, not only via WP-CLI. | ◇ `fd4fce8` |
| F-13 | `reviews`/`claims` lacked the composite the moderation query needs | ✅ Fixed | `idx_status_created`, DB 1.5.0. | ◇ `b5e2579` |
| F-14 | Sorting the admin listings table by Views materialised the whole analytics table | ✅ Fixed | Sort moved into `posts_clauses`; counts batch-load in one grouped query. | ◆ `class-listing-columns.php:59,146` |
| F-19 | i18n branch packaging | ✅ Fixed | 10 bundled locales, script-module translations, SDK catalogue. | ◇ 5 i18n commits |
| F-20 | Abbreviated prices round wrong — 1,500 displays as "2K" and 1,499 as "1K" | ❌ Open | `d49b7c1` reads like a fix in the log but is **card only, no code change** — thousands abbreviate at 0 decimals while millions use 1, so every price between 1,000 and 999,999 rounds to the nearest whole thousand. Two listings one unit apart appear a thousand apart. | ◆ commit is docs-only |

---

## Plugin — Pro, still half cooked

Thirty findings; roughly half have landed. All three P0s are closed. What remains is mostly scale and
hygiene — but P-11 and P-12 are the same three-entry-point failure the CLAUDE.md rule exists to
prevent. Pro's audit file has **not** had its fix status synced; this table is the current view.

| ID | Finding | Status | Note | Evidence |
|---|---|---|---|---|
| P-11 | `saved_searches` table has 0 of 3 entry points — the live feature uses user meta instead | ❌ Open | The table carries demo-seeder rows only; `Advanced_Search` stores real data in `_listora_saved_searches` user meta. Two stores for one feature, and the privacy eraser has to know about both. | ◆ re-read both paths |
| P-12 | `payments` table has no admin list, no REST read, and the refund path can never reach it | ❌ Open | Pairs with Free's F-11 — the money tables are the least observable thing in the product. | ◆ re-read |
| P-15 | Bulk moderator reassign calls `wp_cache_flush()` | ❌ Open | Blows the entire object cache for every site on the install, to invalidate a handful of keys. | ◆ `class-moderator.php:1270` |
| P-22 | License enforcement does not exist | ❌ Open | Blocks skill rule 9: `app_enabled` is supposed to be `pro_active && License::is_valid() && owner_toggle`. Without it the app's connect-time gate cannot be honest. | ◆ `class-license.php` exists; no `is_valid()` |
| P-26 | `uninstall.php` leaves the feature-toggle option and several others behind | ❌ Open | One `delete_option` call for a plugin with 30 toggles. | ◆ re-read |
| P-31 | Manifest and CLAUDE.md are materially stale | ❌ Open | Manifest still says `1.4.1` while Pro ships 1.5.0 — and CLAUDE.md tells the next session to read the manifest first. | ◆ `audit/manifest.json` |
| P-05, P-10 | Transactions CSV loads the whole ledger into memory; audit-log CSV silently truncates to 100 rows | ❌ Open | A silent truncation reads as "you exported everything". | ◇ not re-checked |
| P-06 | Multi-criteria reviews runs an unbounded query per listing on Free's search hot path | ❌ Open | Pro slowing Free's most-used surface. | ◇ not re-checked |
| P-19, P-21, P-23, P-24, P-25, P-27, P-28, P-30 | Receipt HMACs never expire · Setup Wizard is the lone `manage_options` surface · six fetch sites with no abort/timeout · need-respond charges credits for an unenforced required field · invisible focus ring on compare-bar remove · rate-limit GC deletes without a `LIMIT` · webhooks silently cap at 50 subscribers · changelog missing 1.4.1 | ❌ Open | Debt and hygiene. None blocks the app; several would surface as customer bugs. | ◇ not re-checked |
| P-13 | Needs dashboards run unbounded `SELECT *` inside foreach loops, twice each | ⚠️ Partial | Response counting moved to `GROUP BY`; the second loop is unconfirmed. | ◇ `4fd8430` |
| P-02 | Any Author could mint a free pricing plan; any Editor could delete all paid plans | ✅ Fixed | Plan CPT restricted to settings managers, with the required escape hatch. | ◇ `323fba3`, `43c61c7` |
| P-03, P-04 | Toggled-off features still registered blocks and rendered admin surfaces | ✅ Fixed | Reverse-listings and monetization gate on their toggles. | ◇ `1308f0f` |
| P-07 | Three needs routes compared against `post_author` without asserting login | ✅ Fixed | | ◇ `05af4a0` |
| P-08, P-09 | Stale Free-version guard · Pro's proxy-header filter named differently from Free's, so CDN sites mass-429 | ✅ Fixed | | ◇ `dc681b9`, `4627201` |
| P-14, P-16, P-17, P-18 | Missing indexes · moderator queue counts · uncached Transactions aggregates · coupons list capped at 50 | ✅ Fixed | The 1.5.0 scale wave. | ◇ `247c085`, `1e5925d`, `994d3c8`, `b6ee61a` |
| P-20 | `wb_listora_pro_view_analytics` was a phantom capability | ✅ Fixed | No longer referenced anywhere. | ◆ zero matches |
| P-29 | `1.4.2` branch unversioned | ✅ Fixed | Now 1.5.0. | ◆ re-read |

---

## What actually blocks the app

Of everything above, only these four reach the app.

| Plugin gap | What it costs the app | Card |
|---|---|---|
| Statuses and labels are not published in `/settings/app-config` | Every client carries its own copy of the status map — exactly how the "Expired" filter bug happened. Publishing them from the same `Status_Manager::custom_statuses()` map that already drives `register_post_status()` is the only fix that prevents recurrence. | #10182473304 |
| No answer on which route the app posts to when Pro `lead_form` is ON | Holds the lead-form row at Deferred. Messages deliver either way; the risk is silent under-attribution of the `leads` metric. | #10183618407 |
| Add Listing is unsubmittable for a type with no allowed categories | A hard block on both surfaces. QA filed it as a data note; it is not one. | #10180373117 |
| P-22 — license enforcement does not exist | Skill rule 9 makes the app a licensed Pro benefit gated at connect. Until `License::is_valid()` exists, `app_enabled` cannot mean what it is supposed to mean. | not yet filed |

---

## Catalogue drift to fix

| File | Says | Reality |
|---|---|---|
| `wb-listora/CAPABILITIES.md` | Generated 2026-07-15, plugin 1.2.2, 98 live routes | Plugin is **1.5.0**, **117** live routes |
| `wb-listora-pro/audit/manifest.json` | `1.4.1` | Pro is **1.5.0** (this is P-31) |
| `wb-listora-pro/audit/GAP_AUDIT_2026-08-06.md` | No fix markers | ~13 findings have landed |

Both `CAPABILITIES.md` files need a `/wp-plugin-onboard --refresh` pass; until then this file is the
current view.
