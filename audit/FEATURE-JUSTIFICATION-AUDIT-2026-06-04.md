# Master Justification Audit: WB Listora (Free + Pro)

**Products:** wb-listora (Free) + wb-listora-pro (Pro)
**Audited:** 2026-06-04
**Auditor:** AutoVAP lead auditor — master justification pass
**Scope:** 29 features across both tiers. Per-feature wiring, code-quality consistency, duplication, and "nothing half-cooked or over-cooked" verdict.
**Verification policy applied:** false-positive findings DROPPED; `adjusted_severity` applied where present.

---

## 1. Verdict

**Can we justify that all 29 features are 100% usable and properly wired?** — **NO.**

The plugin pair is *substantially* complete: every feature has a working happy path on a single-admin site with default settings, and the architecture is sound (INV-3 boundary check is clean at the namespace level, the before_/after_ write-hook contract and REST envelope are uniform, security posture is strong with zero critical XSS/SQLi/auth-bypass). But **zero features rate fully-wired** once each is held to the bar of "100% usable + properly wired, every advertised sub-flow reachable, no orphaned code." Every feature carries at least one real gap — a broken sub-flow, a missing render path, a duplicated function, or an untranslatable/inaccessible surface.

### Health counts (29 features)

| Health | Count | Features |
|--------|-------|----------|
| **fully-wired** | **0** | — |
| **partially-wired** | **27** | submission, reviews, claims, favorites, renewal, report_listings, schema, opengraph, breadcrumbs, sitemap, comparison, quick_view, lead_form, verification, badges, audit_log, google_maps, multi_criteria_reviews, photo_reviews, advanced_search, seo_pages, infinite_scroll, outgoing_webhooks, analytics, buddy_press_integration, reverse_listings, white_label, coming_soon |
| **half-cooked** | **2** | notification_digest, white_label* |
| **over-cooked** | **0** (no feature is *primarily* bloat; over-cooked findings exist inside otherwise-wired features) | — |

\* `white_label` is scored partially-wired on its implemented scope but is **functionally half-cooked against its own marketing**: logo/email/dashboard branding is advertised everywhere and delivers none of it. `notification_digest` is the only feature whose *core purpose* is broken in production.

### The headline — features that are NOT fully-wired and why

**Critical / customer-blocking:**

1. **notification_digest** — Owner-facing digest emails are **100% silently discarded** (`send_digest()` reads `$item['vars']['user_email']`, but the recipient `$to` is never stored in `$vars` at intercept time). Two stale event keys (`review_submitted`, `review_published`) orphan all review notifications. Plan lifecycle emails bypass digest mode entirely. Only admin-to-admin `listing_submitted`/`claim_submitted` actually batch. **Core purpose broken.**
2. **coming_soon** — **Private mode 403s every logged-in non-admin** because it checks `current_user_can('read_listora_listings')`, a capability never defined or granted by either plugin. A members-only directory cannot be built. (critical)
3. **seo_pages** — The headline **XML sitemap provider is never registered** (wrong WP API: hooks `wp_sitemaps_add_provider` but nobody calls `wp_register_sitemap_provider('listora-seo', …)`). Generates zero sitemap entries. (critical)
4. **sitemap** — Same broken Pro sitemap-provider registration; the Free sitemap toggle also only gates the listings CPT, leaving five taxonomy sitemaps active when "off." (critical Pro path)
5. **multi_criteria_reviews / photo_reviews** — Frontend CSS lives in `pro-admin.css` (admin-only). The criteria-averages "Ratings Breakdown" sidebar and the photo upload dropzone + gallery render **completely unstyled on every public listing page**. (critical UX)

**High — broken sub-flows / missing render paths:**

6. **submission** — `PUT /submit/{id}` only checks `post_author`; admins/editors with `edit_others_listora_listings` get 403. Unbounded geo-proximity query (`HAVING distance < 100`, no bounding box) on every geo submission.
7. **reviews** — Review reports written to an option no admin UI reads (silently discarded); admin moderation skips search-index + cache sync (stale star averages).
8. **claims** — Admin notification-email CTA points to a non-existent page (`page=listora&tab=claims`); dashboard Claims tab LIMIT 20 with no pagination; two Pro hook listeners get the wrong arg type.
9. **badges** — Pro CPT badge pills **never render on listing cards** in grid/featured/related (card template consumes the legacy `{featured,verified,claimed}` boolean map); Quick View ignores the `badges[]` array.
10. **lead_form** — Free's contact-form fallback fires `actions.submitContactForm`, an IAPI action that **does not exist** (Free contact form non-functional when Pro toggle is off).
11. **verification** — `verified_only` filter has no frontend UI; three render paths bypass the `wb_listora_is_verified()` resolver (re-opens the PR #71 badge-leak); no DB index on `is_verified`.
12. **google_maps** — Listing-map block double-mounts Leaflet + Google (memory leak); marker popups never render the image; IAPI bridge uses undocumented private WP API.
13. **outgoing_webhooks** — No Delete button and no Test-fire button in admin UI (both REST-only); `payment_received` double-fires.
14. **audit_log** — Moderator-assignment events fire a hook nobody listens to (silent gap); 5 action-key mismatches between logged keys and the filter dropdown.
15. **favorites / advanced_search / reverse_listings** — N+1 queries, unbounded bootstrap, alert-toggle uses wrong HTTP method (corrupts saved-search list), needs blocks register regardless of toggle.

**Medium/SEO-correctness:**

16. **schema** — `isAccessibleForFree` emits integer `0` (invalid Schema.org Boolean); BreadcrumbList type URL is a live 404.
17. **opengraph / breadcrumbs** — Duplicate canonical tag; visual breadcrumb vs JSON-LD built from divergent data → mismatched crumbs on every listing.
18. **comparison / quick_view / infinite_scroll** — Toggle-default mismatches (registry says `true`, activation says `false`), invalid `data-wp-disabled` directive, RTL stylesheet handle-prefix mismatch.

---

## 2. Per-feature justification table

| Feature | Tier | usable_wired | health | One-line evidence / gap |
|---------|------|--------------|--------|--------------------------|
| Listing Submission | free | partially-wired | needs-work | Happy path solid; PUT route blocks admins, unbounded geo query, ~15 untranslatable JS strings. |
| Reviews & Ratings | free | partially-wired | needs-work | Review-report sub-flow writes to an option no admin reads; admin moderation skips index/cache sync. |
| Business Claims | free | partially-wired | needs-work | Admin email CTA → non-existent page; dashboard tab LIMIT 20; Pro listeners get wrong arg type. |
| Favorites | free | partially-wired | needs-work | Unbounded bootstrap query every page; N+1 in dashboard panel; dead `wb_listora_favorite_removed` hook. |
| Listing Renewal | free | partially-wired | needs-work | No cache invalidation on renewal (stale stats); `purchase_url` absent from quote → buy-credits button never shows. |
| Report Listings | free | partially-wired | needs-work | `wb_listora_listing_reported` fires into a void (no admin email); report options not purged on uninstall; REST route missing from manifest. |
| Schema.org JSON-LD | free | partially-wired | needs-work | `isAccessibleForFree`=`0` (invalid Boolean); BreadcrumbList type URL 404s; REST leaks schema when toggle off. |
| Open Graph + Twitter | free | partially-wired | needs-work | Duplicate canonical tag; raw `$wpdb` in Schema_Generator; Pro virtual pages have no OG tags. |
| Breadcrumbs | free | partially-wired | needs-work | Visual breadcrumb + JSON-LD built independently → mismatched crumbs/URLs; broken cache-bust. |
| Sitemap (XML) | free | partially-wired | needs-work | Toggle gates only listings CPT (5 taxonomies leak); Pro sitemap provider is dead code; cache-bust broken. |
| Listing Comparison | pro | partially-wired | needs-work | Toggle default mismatch (ships disabled); dead `render_card_verified_badge`; stale journey localStorage key. |
| Quick View | pro | partially-wired | needs-work | RTL stylesheet never loaded (handle prefix mismatch); gallery arrows 30px (< 40px); toggle-default mismatch. |
| Lead Capture Form | pro | partially-wired | needs-work | Dead post-submit hook; bypasses digest + anti-spam; Free contact-form fallback action missing. |
| Verification System | pro | partially-wired | needs-work | `verified_only` has no UI; 3 paths bypass resolver (badge-leak); no index on `is_verified`. |
| Custom Badges | pro | partially-wired | needs-work | CPT badges never render on cards/grid/quick-view; condition engine bypasses verified resolver. |
| Audit Log | pro | partially-wired | needs-work | Moderator-assign hook unconsumed; 5 action-key dropdown mismatches; spoofable IP (no proxy gate). |
| Google Maps Provider | pro | partially-wired | needs-work | listing-map double-mounts Leaflet; marker image never rendered; private IAPI API bridge. |
| Multi-Criteria Reviews | pro | partially-wired | needs-work | Averages CSS in admin-only sheet (unstyled on frontend); search-only REST hook; N+1 per review. |
| Photo Reviews | pro | partially-wired | needs-work | All CSS admin-only (unstyled frontend); detail-block tab never fires render hook; REST has no `photos` field. |
| Saved Searches & Alerts | pro | partially-wired | needs-work | Alert-toggle uses DELETE+POST not PATCH (corrupts list); DB table is inert (storage is user-meta); user-meta full-scan. |
| Programmatic SEO Pages | pro | partially-wired | needs-work | Sitemap provider never registered (0 entries); 3 documented filters never fired; no OG tags; bypasses theme. |
| Infinite Scroll | pro | partially-wired | needs-work | Invalid `data-wp-disabled` directive; counter undercounts (SSR page-1 not in state); SEO rel links ignore filters. |
| Outgoing Webhooks | pro | partially-wired | needs-work | No Delete/Test button in admin (REST-only); `payment_received` double-fires; N+1 in list prepare. |
| Analytics | pro | partially-wired | needs-work | `share` event never recorded (tile always 0); Free 90-day cron overrides Pro 365-day setting; stale journey. |
| BuddyPress Integration | pro | partially-wired | needs-work | Profile tabs render unstyled (zero CSS for `listora-bp-*`); advertised Favorites tab missing entirely. |
| Reverse Listings | pro | partially-wired | needs-work | Needs blocks register regardless of toggle (404 on submit); N+1 dashboard tab; Need_Matcher bypasses geo service. |
| White Label | pro | partially-wired | half-cooked | Implemented = plugin-row rename only; logo/email/dashboard branding advertised but absent; journey tests non-existent impl. |
| Coming Soon / Visibility | pro | partially-wired | needs-work | **Private mode 403s all non-admins** (undefined capability); visibility tab not gated by toggle; no dark mode. |
| Notification Digest | pro | **half-cooked** | **poor** | **Owner digest emails 100% discarded** (recipient never captured); 2 stale event keys; plan emails bypass digest. |

---

## 3. Half-cooked findings (incomplete / orphaned wiring)

Per feature, with file:line and the missing link.

### notification_digest (poor — core broken)
- **Owner delivery 100% broken** — `class-notification-digest.php:328-333` reads `$item['vars']['user_email']`, but `Notifications::send()` fires `wb_listora_send_notification` at `class-notifications.php:1031` with `$to` as a *positional* param, never inside `$vars`. The `if ($user_email)` guard is always false → every owner event silently dropped. **Missing link:** add 4th arg `$to` to the filter (Free PR first), store `recipient_email` in queued item, read it in `send_digest()`.
- **Stale event keys** — `admin_events` includes `review_submitted` (line 311); Free fires `review_received` (`class-notifications.php:473`). `owner_events` includes `review_published` (line 313); Free fires no such event. Both orphan all review notifications.
- **Plan_Notifier bypass** — `class-plan-notifier.php:44-173` calls `wp_mail()` directly; never routes through the digest intercept. Plan emails always instant.

### white_label (half-cooked vs marketing)
- **Logo/email/dashboard branding advertised, none implemented** — `functions.php:156`, `class-pro-promotion.php:823`, `FEATURE_AUDIT.md:185` all promise logo + email + dashboard branding. Actual feature = Plugins-screen + admin-menu rename only. `wb_listora_email_logo_url` / `wb_listora_email_footer_text` filters fired by `class-email-helpers.php:75-76` are **never answered**. **Missing link:** either build the promised scope (hook the email filters, add logo upload, admin-header override) or correct the three description strings.
- **QA journey tests a non-existent implementation** — `admin/12-white-label-branding.md` references REST endpoint `/settings/white-label`, option keys `plugin_name/menu_label/footer_text`, and a `wb_listora_pro_white_label` filter — none exist. Every step fails.

### report_listings
- **`wb_listora_listing_reported` fires into a void** — `class-listings-controller.php:1387`; `consumed_by: []`. No admin email, no moderator queue. **Missing link:** add a `Notifications` consumer.
- **Report options not purged on uninstall** — `uninstall.php:46` deletes `wb_listora_%` but report data is `_listora_listing_reports_{id}`. **Missing link:** add the `_listora_listing_reports_%` DELETE (Pro already does this for review reports).
- **REST route missing from manifest** — `POST /listings/{id}/report` absent from `audit/manifest.json` (count says 55, should be 56).

### reviews
- **Review-report sub-flow half-built** — `class-reviews-controller.php:922` writes `_listora_review_reports_{id}`; **no admin UI reads it.** Report_Metabox reads a different key (`_listora_listing_reports_{id}`). Every review flag silently discarded.
- **Admin moderation skips index/cache sync** — `class-admin.php:1115` direct `$wpdb->update/delete` without `update_listing_rating()` or firing `wb_listora_after_update_review` → stale star average + uninvalidated `listora_review_stats_{id}` cache.
- **Load More reloads same first page** — `view.js:274` only sets hash + reloads, no offset/cursor; `get_reviews()` has no offset param.

### claims
- **Admin email CTA → non-existent page** — `class-notifications.php:567` uses `admin.php?page=listora&tab=claims`; real slug is `listora-claims`. Every claim email "Review Claim" button 404s into the dashboard.
- **Pro listeners get wrong arg type** — Free fires `wb_listora_after_submit_claim($claim_id, $listing_id, $request)` (3 args); Pro Audit_Log + Moderator hook with `accepts_args=2` and treat arg 2 as `$data` array → `listing_id 0` in audit, silent moderator-assign failure (`class-audit-log.php:201`).

### favorites
- **`wb_listora_favorite_removed` fires with zero consumers** — `class-favorites-controller.php:337`. Dead fire alongside the canonical `wb_listora_after_remove_favorite`.
- **IAPI state seed not gated on toggle** — `class-assets.php:317` runs the DB query + injects `state.favorites` even when Favorites is disabled.

### renewal
- **No cache invalidation on renewal** — `class-cache.php:73-93` has no `wb_listora_after_renew_listing` listener; the controller comment at `class-listings-controller.php:2163` falsely claims it does. Dashboard stats stale until TTL.
- **`autoSaveDraft` action implemented but never triggered** (submission) — analogous dead-store pattern.

### badges
- **CPT badges never render on cards** — `class-template-helpers.php:595-599` builds `{featured,verified,claimed}` bools; Pro never hooks `wb_listora_card_view_data`. All 6 default + custom badges absent from grid/featured/related.
- **Quick View ignores `badges[]`** — `quick-view.js:66-75` `hasBadges` reads only `isFeatured||isVerified`.
- **Journey references non-existent cron** — `wb_listora_pro_badge_auto_assign` AS hook (`admin/06-create-badge.md:51`) is registered nowhere.

### audit_log
- **Moderator-assign fires unconsumed hook** — `class-moderator.php:278` fires `wb_listora_audit_log` with `moderator_assigned`; no `add_action('wb_listora_audit_log', …)` exists anywhere. Silent audit gap.
- **5 action-key mismatches** — `credits_refunded`/`listing_paused`/`listing_resumed` logged but absent from `get_action_labels()` (`class-audit-log.php:1482`); `settings_changed`/`badge_assigned`/`badge_removed` in the dropdown but never logged.

### seo_pages
- **Sitemap provider never registered** — `class-seo-pages.php:98,746` hooks `wp_sitemaps_add_provider` (fires only when WP adds an existing provider); the required `wp_register_sitemap_provider('listora-seo', …)` is never called. Zero entries.
- **3 documented filters never fired** — `wb_listora_pro_seo_pages_title/description/schema` documented at `seo-pages.md:57-59`; zero `apply_filters()` in the class.
- **No OG tags** — `render_meta_tags():569-601` emits only title/description/canonical/robots.

### sitemap
- **Pro provider dead code** — same `wp_register_sitemap_provider` gap; the "Include in XML sitemap" checkbox does nothing.
- **Free toggle gates only the CPT** — `class-plugin.php:470`; five Listora taxonomies remain in the index when "off."

### advanced_search
- **DB table inert** — `listora_saved_searches` created by migrator but production reads/writes only `_listora_saved_searches` user-meta. Journeys, runbook, CLI teardown all target the dead table → false QA signal.
- **Alert-toggle uses DELETE+POST** — `pro-frontend.js:130-153` deletes old ID and POSTs new; DOM keeps stale `data-search-id` → orphaned entry + 404 on next delete. The correct PATCH endpoint exists but is unused.

### reverse_listings
- **Needs blocks register regardless of toggle** — `class-pro-plugin.php:601-607` glob-registers all blocks; when toggle off the `post-need` form submits to a 404 endpoint.

### google_maps
- **listing-map double-mounts Leaflet** — `blocks/listing-map/render.php:16-20` unconditionally enqueues Leaflet (no provider gate like listing-detail has) → Free Leaflet map + Pro Google map on the same container, memory leak.
- **Marker image never rendered** — `google-maps-init.js:345-390` `buildInfoWindowContent()` never reads `data.image` though render.php supplies it.

### lead_form
- **Free contact-form fallback action missing** — `class-contact-form.php:295` emits `data-wp-on--submit="actions.submitContactForm"`; that IAPI action does not exist in the store. Free contact form is non-functional when Pro `lead_form` toggle is off.
- **`wb_listora_pro_after_submit_lead` has zero consumers** — `class-lead-form.php:313`.

---

## 4. Over-cooked findings (dead code / bloat / over-engineering)

No feature is *primarily* bloat, but these dead/over-engineered surfaces exist inside otherwise-wired features:

| Feature | Over-cooked surface | File:line |
|---------|---------------------|-----------|
| claims | `role` REST param registered + sanitized but **no DB column, never stored** — dead API surface. | `class-claims-controller.php:58` |
| comparison | `render_card_verified_badge()` — defined, never registered (dead method, also has INV-12 meta bypass). | `class-comparison.php:155-173` |
| comparison | `Comparison::enqueue_assets()` loads full `pro-admin.css` (79 KB) on every frontend page when enabled; only ~340 lines are needed. | `class-comparison.php:73-80` |
| google_maps | `enqueue_places()` is an empty stub adding a no-op `wp_enqueue_scripts` hook on every page. | `class-google-maps.php:191-193` |
| google_maps | `wb_listora_pro_map_styles` option stored + read but **no UI, no REST, no setter** — phantom feature. | `class-google-maps.php:212-224` |
| sitemap | Pro `is_sitemap_enabled()` sub-toggle renders a UI control for a provider that is never registered — dead UI. | `class-seo-pages.php:144` |
| audit_log | `CRON_HOOK` deprecated alias — never referenced anywhere (dead constant). | `class-audit-log.php:41` |
| renewal | `$new_expiry` computed but never used (leftover from earlier impl). | `class-listings-controller.php:2114` |
| opengraph | `wb_listora_set_feature()` cache-bust writes `$GLOBALS['…cache_bust']` that `get_features()` never reads — dead write (latent bug). | `class-features.php:264-271` |
| infinite_scroll / quick_view / comparison / verification / badges / multi_criteria_reviews / photo_reviews / buddy_press_integration / analytics / reverse_listings | **Redundant double feature-gate** in `init()` — Feature_Manager already skips instantiation when the toggle is off; the in-class `if (!feature_enabled) return;` is unreachable dead code. Systemic across ~10 Pro feature classes. | (each class `init()`) |
| submission | Transaction wraps `wp_insert_post` hooks that fire AS jobs / search-index side-effects that ROLLBACK cannot undo — transaction boundary is over-scoped vs what it can actually protect. | `class-submission-controller.php:449-524` |

---

## 5. Duplication report

From the cross-cutting manual pass + `cleanup-duplicate-detect.php` (4 cross-plugin, 32 within-plugin token matches) + `cleanup-boundary-check.sh` (0 namespace-import violations). The token detector misses semantic duplication; the items below were grep+read verified across both repos.

### HIGH severity — fix first

| # | Duplicated logic | ALL locations | Canonical home |
|---|------------------|---------------|----------------|
| D1 | **Claims list query + COUNT twin** (verbatim) | `class-admin.php:1572-1583`, `class-claims-controller.php:384-396` | New `\WBListora\Core\Claims_Model::get_list()` + `get_list_count()` — both admin + REST consume. Also the big-site index/count fix. |
| D2 | **Pro reads raw `_listora_is_verified` meta**, bypassing `wb_listora_is_verified()` resolver (re-opens PR #71 badge-leak) | `class-verification.php:189,209,231`, `class-badges.php:437-438` → resolver at `class-features.php:290` | All 4 sites call `wb_listora_is_verified($id)`. `add_to_rest_response` is worst — overwrites Free's already-correct resolver value with a raw read. |
| D3 | **Need_Matcher re-implements Geo_Query haversine** | `need-matcher.php:210-223` → canonical `class-geo-query.php:37-58` via `wb_listora_service('geo_query')` | Delete Pro copy; call the service. Add `haversine_distance()` to Free's interface FIRST if missing (5-step order). |
| D4 | **Audit_Log::get_ip() duplicates Public_Rate_Limiter IP resolver — unsafely** (no proxy-trust filter, no `FILTER_VALIDATE_IP`) | `class-audit-log.php:170-180` vs `class-public-rate-limiter.php:175-192` | Shared `resolve_client_ip()` with proxy gate + validation. Duplication AND spoofable-audit-trail security bug. |

### MEDIUM severity

| # | Duplicated logic | ALL locations | Canonical home |
|---|------------------|---------------|----------------|
| D5 | Rating-summary SQL (`SELECT avg_rating, review_count FROM search_index`) | `class-schema-generator.php:161-169` → `Listing_Data::get_rating_summary()` `class-listing-data.php:59-75` | Schema_Generator calls `Listing_Data::get_rating_summary()`. |
| D6 | Review-stats aggregate (AVG+COUNT+5×CASE) — **cached in controller, uncached in Listing_Data** (drift) | `class-listing-data.php:101-130`, `class-reviews-controller.php:303-322` | Make `get_review_distribution()` the cached owner; controller delegates. |
| D7 | Inline favorites COUNT in template (raw `$wpdb` in render — rule violation) | `blocks/listing-card/render.php:113-119` → `Listing_Data::get_favorite_count()` | Call the model method. |
| D8 | Inline favorites fetch in template (no model equivalent) | `blocks/user-dashboard/render.php:210-214` | New `Listing_Data::get_user_favorite_ids()`. |
| D9 | Review-form criteria-collection JS (verbatim regex loop) | `store.js:1747-1768`, `view.js:53-98` | Shared `extractCriteriaRatings(form)` util. |
| D10 | Option-backed report storage/dedup pattern (diverged: listing adds `status:'open'`, review omits) | `class-listings-controller.php:1342+`, `class-reviews-controller.php:910+` | `wb_listora_record_report()` helper. |
| D11 | Report-reason vocabulary (REST enum vs metabox label map — 2 sources of truth) | `class-listings-controller.php:338`, `class-report-metabox.php:45-51` | Single `wb_listora_report_reasons()` map; enum derives keys. |
| D12 | Submission proximity haversine SQL re-implements Geo_Query | `class-submission-controller.php:865-883` → `class-geo-query.php:61-158` | `check_duplicates()` calls `Geo_Query::find_nearby()`. |
| D13 | Analytics INSERT…ON DUPLICATE (verbatim) | `class-analytics.php:497-511` (private), `class-lead-form.php:277-290` | Make `record_event()` public static; Lead_Form calls it. |
| D14 | Multi-criteria SELECT+aggregate (twice in same class) | `class-multi-criteria-reviews.php:222-261`, `:271-300` | Private `get_criteria_averages()`. |
| D15 | `abortableApiFetch` (4 copies) | `wb-listora/src/utils/abortable-fetch.js` (canonical), `wb-listora-pro/src/utils/abortable-fetch.js`, `wb-listora-pro/assets/js/abortable-fetch.js`, inline in `quick-view.js:15-22` + `infinite-scroll.js:19-26` | Consolidate to TWO: one ES module + one registered classic-script global. |
| D16 | SEO type×location matrix loop (4 methods) | `class-seo-pages.php:760,798,885,930,999` | Private `get_valid_combos()`; source counts from `search_index` service. |
| D17 | BreadcrumbList construction (3 copies, divergent URLs) | `class-schema-generator.php:472-535`, `blocks/listing-detail/render.php:248-278`, `class-seo-pages.php:705-732` | Free `wb_listora_get_breadcrumb_items()` + `build_breadcrumb_json_ld()`; both HTML + JSON-LD delegate. |
| D18 | **Importer/migrator method family** (`set_geo_data`/`create_listing`/`sanitize_address`/`normalize_term_list`/`sideload_image`/`map_taxonomies`/`migrate_listing`) — KNOWN BACKLOG | `class-geojson-importer.php`, `class-json-importer.php`, `class-csv-importer.php:250`, `class-directorist-migrator.php`, `class-listingpro-migrator.php` (+ GeoDirectory/BDP) | Execute CLAUDE.md backlog: `\WBListora\Import\Term_Helper::set_taxonomy_terms()` + `Importer_Base` owning the shared geo/term/image methods. |

### LOW severity

| # | Duplicated logic | Locations | Canonical home |
|---|------------------|-----------|----------------|
| D19 | Dual favorites hooks for one event (`wb_listora_favorite_added` 2-arg + `wb_listora_after_add_favorite` 3-arg) | `class-favorites-controller.php:271,280` | Deprecate the 2-arg legacy; migrate Pro Analytics to canonical 3-arg; remove after 2 minors. |
| D20 | `Credits::get_balance()` fetched twice per renewal | `class-listings-controller.php:1936,2066` | `renew_listing()` reuses `build_renewal_quote()` result. |
| D21 | `days_until_expiry` expression in 2 layers | `class-listings-controller.php:1914`, `tab-listings.php:107` | `wb_listora_listing_days_until_expiry()` helper. |
| D22 | Claims COUNT run twice per admin session | `class-admin.php:902-903`, `:1572` | `Claims_Model::get_counts()` (single GROUP BY). |
| D23 | Lead_Form rate-limit pattern duplicates Contact_Form | `class-lead-form.php:166-199`, `class-contact-form.php:177-205` | Shared `wb_listora_rate_limit_guard()`. |
| D24 | `Comparison::render_card_verified_badge()` dead duplicate of `Verification::render_card_badge()` | `class-comparison.php:155`, `class-verification.php:188` | Remove the dead Comparison copy. |

> **Not a duplication:** `Email_Helpers::html_to_text()` is a correctly-marked `@deprecated` delegation shim (deletes in 1.2.0). The 4 cross-plugin + several within-plugin token-detector hits are the **correct, intentionally-uniform permission-callback idiom** — desirable consistency, not drift. Only refinement: hoist the repeated logged-in/admin guards (Pro Needs_Controller repeats the author-or-`edit_others_listora_needs` check 5×) into trait methods.

---

## 6. Code-quality consistency (divergences from the house pattern)

| Area | House pattern | Outlier(s) | Files |
|------|---------------|-----------|-------|
| **Upscale contract (consume Free surface)** | Pro consumes Free via `wb_listora_service()` / hooks / resolvers; boundary-check = 0. | Pro reads raw post-meta / re-queries Free tables: Verification + Badges read `_listora_is_verified`; Need_Matcher re-implements haversine; Multi_Criteria_Reviews, Outgoing_Webhooks (`on_review_updated`/`on_claim_updated`), BuddyPress raw `$wpdb->get_row` on `listora_reviews`/`listora_claims`. Pass namespace-check but break extension-order step 2. | `class-verification.php:189,209,231`; `class-badges.php:437`; `need-matcher.php:210`; `class-multi-criteria-reviews.php:222,271`; `class-outgoing-webhooks.php:411,452` |
| **IP / client-identity resolution (security)** | Rate-limiter gates XFF behind `wb_listora_pro_trust_proxy_headers` + `FILTER_VALIDATE_IP`. | Audit_Log trusts XFF unconditionally, no validation — spoofable audit trail. | `class-audit-log.php:170` vs `class-public-rate-limiter.php:175` |
| **Cache discipline (same query)** | Identical aggregates wrapped in `wp_cache` group `listora`. | Reviews_Controller caches; `Listing_Data::get_review_distribution` does not. Schema_Generator runs rating query inline uncached. Badges caches in group `wb_listora_pro` with no shared invalidation vs Free's `listora` bump. | `class-listing-data.php:59,101`; `class-reviews-controller.php:300`; `class-schema-generator.php:161`; `class-badges.php:542-581` |
| **No raw `$wpdb` in render templates** | Reads go through `Listing_Data`. | listing-card favorites count + user-dashboard favorites fetch run raw `$wpdb` in the template. | `blocks/listing-card/render.php:113`; `blocks/user-dashboard/render.php:210` |
| **Single source of truth for enums** | `Field::social_link_platforms` single-source. | Report-reason vocabulary defined twice; SEO sitemap sub-toggle orthogonal to `feature_enabled('sitemap')` (and dead). | `class-listings-controller.php:338`; `class-report-metabox.php:45`; `class-seo-pages.php:144` |
| **Write-event hook naming** | `before_/after_` pair, one hook per event. | Favorites fires BOTH `wb_listora_after_add_favorite` AND legacy `wb_listora_favorite_added` for one write — listeners split across two names. | `class-favorites-controller.php:271,280` |
| **CSS tokens (no raw hex; Pro consumes Free tokens)** | Token-driven `var(--listora-*)`. | SEO_Pages breadcrumb uses raw inline `#666`; SEO_Pages full page render uses 28 inline hex styles (dark mode + RTL broken); quick-view.css / comparison style.css / needs-grid / verification have raw hex fallbacks (INV-14). | `class-seo-pages.php:705-732,400-554`; `comparison/style.css:196,222,223`; `needs-grid/style.css:382,390` |
| **Frontend CSS location** | Frontend styles in `pro-frontend.css` (loaded on all pages). | Multi-criteria averages + photo-review upload/gallery CSS placed in `pro-admin.css` (admin-only) → unstyled on frontend. | `pro-admin.css:404-462`; `pro-admin.css:259-403` |
| **`wp-element-button` ban** | CSS Rule 5 forbids `wp-element-button` in markup. | Systemic across Pro: lead_form, infinite_scroll, badges, advanced_search, pricing-plans, webhooks, etc. carry it on customer-facing buttons. | `class-lead-form.php:371`; `class-infinite-scroll.php:229`; + ~7 more |
| **Feature toggle = single gate (Feature_Manager)** | Feature_Manager skips instantiation when off. | ~10 Pro classes add a redundant in-`init()` toggle re-check (dead code). | (each Pro feature `init()`) |
| **Permission-callback idiom (CONSISTENT — good)** | Identical logged-in/admin WP_Error guard shape across Free + Pro. | None — desirable consistency. Refinement only: hoist repeated guards to traits. | `needs-controller.php:318,517,642,704` |

---

## 7. Ranked correctness findings (confirmed, post-verification)

Severities reflect `adjusted_severity` where present; false-positive entry dropped (the quick_view RTL-handle finding was marked false-positive → DROPPED).

### CRITICAL

| # | Feature | Finding | File:line | Fix |
|---|---------|---------|-----------|-----|
| C1 | notification_digest | Owner digest emails 100% discarded — recipient `$to` never in `$vars`. | `class-notification-digest.php:328-333` | Pass `$to` as 4th filter arg; store `recipient_email`; read it in send_digest. |
| C2 | notification_digest | Stale event keys `review_submitted`/`review_published` orphan all review notifications. | `class-notification-digest.php:311-313` | Replace with `review_received`; audit full key list. |
| C3 | coming_soon | Private mode 403s every non-admin — `read_listora_listings` capability undefined/ungranted. | `class-coming-soon.php:90` | Check `current_user_can('read')`, or define+grant the cap in Free Capabilities. |
| C4 | seo_pages | XML sitemap provider never registered (zero entries). | `class-seo-pages.php:98,746` | `wp_register_sitemap_provider('listora-seo', new SEO_Pages_Sitemap_Provider($this))` on init. |
| C5 | multi_criteria_reviews | Criteria-averages CSS in admin-only sheet → "Ratings Breakdown" unstyled on frontend. | `pro-admin.css:404-462` | Move rules to `pro-frontend.css` + RTL twin. |
| C6 | photo_reviews | All photo-review CSS in `pro-admin.css` → upload dropzone + gallery unstyled on frontend. | `pro-admin.css:259-403` | Move to `pro-frontend.css`; enqueue from `enqueue_assets()`. |

### HIGH

| # | Feature | Finding | File:line | Fix |
|---|---------|---------|-----------|-----|
| H1 | submission | `PUT /submit/{id}` only checks `post_author` — admins/editors 403'd. | `class-submission-controller.php:155-172` | Add `current_user_can('edit_others_listora_listings')` escape hatch; name the callback. |
| H2 | submission | `check_duplicates()` geo query: `HAVING distance<100` no bounding-box WHERE — full scan per geo submit. | `class-submission-controller.php:865-883` | Bounding-box pre-filter using `idx_lat_lng`. |
| H3 | reviews | Admin moderation skips index/cache sync → stale star avg + count. | `class-admin.php:1115` | Call `update_listing_rating()` + fire after_update/delete hooks + cache delete. |
| H4 | reviews | Review reports written to option no admin UI reads. | `class-reviews-controller.php:922` | Add Reports column/panel or consolidate storage. |
| H5 | claims | Admin email CTA → non-existent page slug. | `class-notifications.php:567` | `admin.php?page=listora-claims`. |
| H6 | claims | Pro listeners receive wrong arg type from `wb_listora_after_submit_claim`/`_update_claim`. | `class-audit-log.php:201` (+Moderator) | Change Pro `accepts_args` to 3. |
| H7 | favorites | Unbounded favorites bootstrap query every page load. | `class-assets.php:321` | Add `LIMIT 500` + gate on `feature_enabled('favorites')`. |
| H8 | favorites | N+1 search_index queries in dashboard favorites panel. | `blocks/user-dashboard/render.php:793` | `wb_listora_prime_card_index_rows($ids)` before loop. |
| H9 | renewal | No cache invalidation on renewal → stale dashboard stats. | `class-cache.php:73-93` | `add_action('wb_listora_after_renew_listing', [__CLASS__,'bump_listings'])`. |
| H10 | renewal | `purchase_url` absent from quote → buy-credits button never shows on pre-flight. | `class-listings-controller.php:1939-1952` | Add `purchase_url` to `build_renewal_quote()`. |
| H11 | report_listings | `wb_listora_listing_reported` fires with no consumer → no admin alert. | `class-listings-controller.php:1387` | Add Notifications consumer (adjusted med). |
| H12 | report_listings | Report options not purged on uninstall. | `uninstall.php:46` | Add `_listora_listing_reports_%` DELETE. |
| H13 | schema | `isAccessibleForFree` emits int `0` (invalid Boolean) → fails Rich Results. | `class-listing-type-defaults.php:1447`; `class-schema-generator.php:333` | Cast `amount==0 → true`. |
| H14 | schema | BreadcrumbList type URL `/place/` is a live 404. | `class-schema-generator.php:495` | `get_term_link()` + `is_wp_error()` guard. |
| H15 | badges | CPT badges never render on cards. | `class-template-helpers.php:595` | Pro hooks `wb_listora_card_view_data`; card template renders `pro_badges[]`. |
| H16 | badges | Quick View ignores `badges[]`. | `quick-view.js:66-75` | Read `data.badges` getter; render dynamically. |
| H17 | verification | `verified_only` filter has no frontend UI. | `class-verification.php:176` | Add InspectorControl checkbox + IAPI state. |
| H18 | lead_form | Free contact-form fires non-existent `actions.submitContactForm`. | `class-contact-form.php:295` | Add the IAPI action to Free store. |
| H19 | lead_form | QA journey wrong route/response/CPT/table. | `06-lead-form.md:40` | Rewrite journey to actual `/listings/{id}/contact`. |
| H20 | lead_form | `wb_listora_pro_after_submit_lead` unconsumed. | `class-lead-form.php:313` | Wire Audit_Log or remove. |
| H21 | google_maps | listing-map double-mounts Leaflet (memory leak). | `blocks/listing-map/render.php:16-20` | Gate Leaflet enqueue on `provider==='osm'`. |
| H22 | google_maps | Marker popup never renders image. | `google-maps-init.js:345-390` | Insert `data.image` `<img>`. |
| H23 | google_maps | IAPI bridge uses private WP API with dead fallback. | `google-maps-init.js:401-459` | Use public `wp.interactivity.store('listora/directory')`. |
| H24 | multi_criteria_reviews | `add_criteria_to_response` hooks search-only filter → averages absent from single-listing REST. | `class-multi-criteria-reviews.php:35` | Also hook `wb_listora_rest_prepare_listing`. |
| H25 | multi_criteria_reviews | N+1: one SELECT per review. | `class-multi-criteria-reviews.php:53-84` | Batch-load before array_map. |
| H26 | multi_criteria_reviews | Per-review criteria scores never displayed. | `review-card.php` | Render `criteria_ratings` in card. |
| H27 | photo_reviews | listing-detail tab never fires `wb_listora_review_after_content`. | `tabs.php:487-558` | Add the `do_action`. |
| H28 | photo_reviews | No delete-photo endpoint; no cumulative cap (>5 via repeated POST). | `class-photo-reviews.php:46-87,118` | Add DELETE route + cumulative cap. |
| H29 | advanced_search | Alert-toggle DELETE+POST corrupts saved-search list. | `pro-frontend.js:130-153` | Use PATCH `/saved-searches/{id}`. |
| H30 | advanced_search | `listora_saved_searches` table inert (prod uses user-meta). | `class-advanced-search.php:184-486` | Decide table-vs-meta; align journeys/runbook/CLI. |
| H31 | outgoing_webhooks | No Delete button in admin UI (REST-only). | `class-outgoing-webhooks.php:1612` | Add delete action to list row. |
| H32 | outgoing_webhooks | No Test-fire button (REST-only). | `class-outgoing-webhooks.php:1511` | Add Send-Test button calling REST. |
| H33 | outgoing_webhooks | `payment_received` double-fires (two listeners, same path). | `class-outgoing-webhooks.php:210-216` | Remove one listener. |
| H34 | audit_log | Moderator-assign hook unconsumed. | `class-moderator.php:278` | Register `add_action('wb_listora_audit_log', …)`. |
| H35 | audit_log | 3 logged keys absent from labels; 3 ghost dropdown keys never logged. | `class-audit-log.php:1482` | Add missing labels; wire/remove ghosts. |
| H36 | reverse_listings | Needs blocks register regardless of toggle → 404 on submit. | `class-pro-plugin.php:601-607` | Gate block registration on toggle. |
| H37 | reverse_listings | N+1 dashboard tab (up to 100 queries). | `class-needs-dashboard-tab.php:101`; `tab-needs.php:53` | Batch-fetch responses (mirror REST). |
| H38 | analytics | `share` event never recorded (tile always 0). | `analytics-tracker` / `shareDialog()` | Emit `event_type:'share'` on share. |
| H39 | analytics | Free 90-day cron overrides Pro 365-day retention. | `class-expiration-cron.php:218` | Pro hooks `wb_listora_analytics_retention_days`. |
| H40 | analytics | QA journey stale (wrong namespace/route/param/selectors/table). | `admin/11-analytics-dashboard.md` | Rewrite against actual impl. |
| H41 | buddy_press_integration | Profile tabs render unstyled (zero CSS for `listora-bp-*`). | templates/bp/*.php | Add `bp.css` token-driven + RTL twin. |
| H42 | buddy_press_integration | Advertised Favorites tab missing entirely. | `functions.php:138` | Implement tab or correct description. |
| H43 | white_label | Logo/email/dashboard branding advertised, none implemented. | `functions.php:156` | Build scope or correct copy. |
| H44 | notification_digest | Plan_Notifier bypasses digest (always instant) — adjusted med. | `class-plan-notifier.php:44-173` | Route through digest intercept. |
| H45 | (cross) | Pro reads raw `_listora_is_verified` in 4 places (re-opens PR #71 leak). | D2 locations | Use `wb_listora_is_verified()`. |
| H46 | (cross) | Audit_Log spoofable IP (no proxy gate / validation). | `class-audit-log.php:170` | Shared validated resolver. |
| H47 | (cross) | Claims list query duplicated verbatim admin↔REST. | D1 locations | Extract `Claims_Model`. |
| H48 | (cross) | Need_Matcher re-implements geo haversine. | `need-matcher.php:210` | Use `wb_listora_service('geo_query')`. |

### MEDIUM (selected — full set in per-feature JSON)

| # | Feature | Finding | File:line |
|---|---------|---------|-----------|
| M1 | submission | ~15 hardcoded untranslatable JS strings (verify/dup cards). | `view.js:2655…` |
| M2 | submission | Success card hardcodes `home_url('/dashboard/')`. | `submission.php:131,142` |
| M3 | reviews | Detail-tab review form hardcodes `minlength=20`. | `tabs.php:619` |
| M4 | reviews | Report modal uses `window.prompt()` (inaccessible). | `view.js:182` |
| M5 | reviews | `report_review` reason has no enum. | `class-reviews-controller.php:199` |
| M6 | claims | Dashboard Claims tab LIMIT 20, no pagination. | `user-dashboard/render.php:222` |
| M7 | claims | `GET /dashboard/claims` registers no args. | `class-dashboard-controller.php:138` |
| M8 | schema | REST detail emits `schema` even when toggle off. | `class-listings-controller.php:1066` |
| M9 | schema | `output_schema()` missing Yoast/RankMath guard (sibling methods have it). | `class-plugin.php:483` |
| M10 | opengraph | Duplicate `<link rel=canonical>`. | `class-schema-generator.php:541-551` |
| M11 | opengraph | Pro virtual pages emit no OG/Twitter tags. | `class-seo-pages.php:569` |
| M12 | breadcrumbs | Visual vs JSON-LD breadcrumb built from divergent data. | `render.php:248`/`schema-generator.php:485` |
| M13 | sitemap | Toggle leaves 5 taxonomy sitemaps active when off. | `class-plugin.php:470` |
| M14 | comparison | Toggle default mismatch (registry true vs activation false). | `functions.php:32` vs `:202` |
| M15 | quick_view | Gallery nav arrows 30px (< 40px floor). | `quick-view.css:226` |
| M16 | infinite_scroll | Invalid `data-wp-disabled` directive (adjusted med). | `class-infinite-scroll.php:231` |
| M17 | infinite_scroll | Counter undercounts (SSR page-1 not in `state.results`). | `infinite-scroll.js:47-55` |
| M18 | infinite_scroll | SEO rel-links ignore filter context. | `class-infinite-scroll.php:315` |
| M19 | seo_pages | OPT_MORE_SECTION saved but never read. | `class-seo-pages.php:162,531` |
| M20 | seo_pages | Render bypasses theme (no header/footer/nav). | `class-seo-pages.php:389-558` |
| M21 | reverse_listings | Admin budget hardcodes `$` (ignores currency meta). | `class-reverse-listings.php:795` |
| M22 | coming_soon | Visibility tab not gated by toggle. | `class-pro-plugin.php:272` |

---

## 8. UX-gap matrix

| Feature | Empty/Error/Loading | A11y | Mobile/RTL | Dark mode | Concurrency | Big-site filter/sort/pagination |
|---------|:---:|:---:|:---:|:---:|:---:|:---:|
| submission | OK | ⚠ ~15 untranslatable strings | ⚠ untranslatable | OK | ⚠ username collision race | ⚠ unbounded geo query |
| reviews | ⚠ Load More no-op | ✗ star inputs no focus ring; prompt() modal | OK | OK | ⚠ rejected-user lockout | ✗ Load More fake pagination |
| claims | ⚠ no re-submit flow | OK | OK | OK | OK | ✗ dashboard LIMIT 20 |
| favorites | ⚠ no empty state when all non-publish | OK | OK | OK | OK | ⚠ unbounded bootstrap, N+1 |
| renewal | ⚠ buy-credits CTA missing | ✗ modal error no aria-live | OK | ⚠ raw rgba (not token) | OK | OK |
| report_listings | ⚠ success modal no close CTA | ✗ review report prompt() | OK | OK | OK | ✗ no filter by report count |
| schema | n/a | ✗ star unicode no aria | OK | OK | OK | OK |
| opengraph | n/a | ⚠ no og:locale | OK | OK | OK | OK |
| breadcrumbs | OK | ⚠ no component focus ring | OK | OK | OK | OK |
| sitemap | n/a | n/a | n/a | n/a | OK | OK |
| comparison | ⚠ slot flash before hydrate | ✗ client re-render 4 untranslated strings | OK | ⚠ raw hex fallbacks | ⚠ no dedupe | OK |
| quick_view | OK | ✗ nav arrows 30px | ✗ RTL twin (false-positive — dropped) | ⚠ hex fallbacks | OK | OK |
| lead_form | ⚠ no spinner | ✗ status div no aria-live, no aria-required | OK | OK | OK | OK |
| verification | n/a | OK | OK | OK | OK | ✗ verified_only no UI; no index |
| badges | ✗ badges absent on cards | OK | OK | OK | ⚠ no cache-bust on data change | ⚠ admin list cap 100 |
| audit_log | OK | OK | OK | OK | OK | ⚠ no retention UI; ghost filters |
| google_maps | ⚠ no marker fetch error state | OK | ✗ RTL never loaded | OK | ⚠ uncleared intervals | OK |
| multi_criteria | ✗ unstyled frontend | OK | ✗ admin-only CSS | ✗ admin tokens on frontend | ⚠ no cache invalidation | ⚠ N+1 |
| photo_reviews | ✗ unstyled frontend; no lightbox | OK | ✗ admin-only CSS | ✗ | OK | OK |
| advanced_search | ⚠ no loading on delete/toggle | OK | OK | OK | ⚠ no per-user cap | ✗ no pagination envelope; meta full-scan |
| seo_pages | ⚠ no loading | ⚠ star no aria | ✗ inline styles, no logical props | ✗ raw hex inline | OK | ⚠ count loop O(types×locs) |
| infinite_scroll | OK | ✗ button not disabled (invalid directive) | OK | OK | ⚠ no dedupe on append | ⚠ rel-links wrong total |
| outgoing_webhooks | ⚠ silent save; no payload view | OK | OK | OK | OK | ✗ list cap 50, no pagination |
| analytics | OK | OK | OK | OK | OK | ⚠ dashboard cap 50, no pagination |
| buddy_press | ✗ tabs unstyled | OK | ✗ no CSS = no RTL | ✗ no CSS = no dark | ⚠ count cache no invalidation | ✗ tabs cap 20, no pagination |
| reverse_listings | ⚠ stale panel after accept | OK | ⚠ needs-grid hex fallbacks | ⚠ hex fallbacks | OK | ⚠ responses unbounded; needs cap 50 |
| white_label | n/a | OK | OK | OK | OK | OK |
| coming_soon | n/a | OK | OK | ✗ splash no dark mode | OK | n/a |
| notification_digest | ✗ owner emails never arrive | OK | OK | OK | ✗ no queue locking (lost writes) | n/a |

Legend: OK = handled · ⚠ = partial/risk · ✗ = missing/broken.

---

## 9. Prioritized remediation plan

Sized S (≤2h) / M (½–1 day) / L (multi-day). Grouped: half-cooked → duplication → correctness → UX polish. File any missing Free-side surface in Free FIRST per the non-negotiable 5-step extension order.

### Phase 1 — Half-cooked (broken core / unreachable sub-flows) — DO FIRST

| Size | Item |
|------|------|
| M | **C1/C2/H44 notification_digest**: pass `$to` as 4th filter arg (Free PR first), store `recipient_email`, fix `review_received` key, route Plan_Notifier through digest. *Owner emails currently 100% lost.* |
| S | **C3 coming_soon**: replace `read_listora_listings` with `current_user_can('read')` (or define+grant the cap). *Private mode currently admits nobody.* |
| S | **C4/sitemap seo_pages**: call `wp_register_sitemap_provider('listora-seo', …)` on init; remove the dead `wp_sitemaps_add_provider` filter. *Sitemap currently empty.* |
| S | **C5/C6 multi_criteria + photo_reviews CSS**: move frontend rules from `pro-admin.css` → `pro-frontend.css` + RTL twins. *Both unstyled on frontend.* |
| M | **H15/H16 badges**: hook `wb_listora_card_view_data`; render `pro_badges[]` in card + Quick View. *Custom badges invisible on cards.* |
| S | **H18 lead_form**: add `submitContactForm` IAPI action to Free store. *Free contact form non-functional.* |
| S | **H5 claims**: fix email CTA page slug. |
| S | **H11/H12 report_listings**: add Notifications consumer + uninstall purge. |
| S | **H4/H3 reviews**: surface review reports in admin; sync index/cache on admin moderation. |
| S | **H34/H35 audit_log**: wire moderator-assign listener; fix the 5 action-key mismatches. |
| S | **H31/H32/H33 outgoing_webhooks**: add Delete + Test buttons; remove the double `payment_received` listener. |
| S | **H36 reverse_listings**: gate needs-block registration on the toggle. |
| S | **H29 advanced_search**: switch alert-toggle to PATCH. |
| S | **H21/H22 google_maps**: gate Leaflet enqueue on provider; render marker image. |
| S | **H27 photo_reviews**: add `wb_listora_review_after_content` to the detail tab. |
| M | **H41/H42 buddy_press**: add `listora-bp-*` CSS + RTL; implement or de-advertise Favorites tab. |
| S | **H43 white_label**: correct the three description strings (or schedule the full branding build as L). |

### Phase 2 — Duplication (single canonical home)

| Size | Item |
|------|------|
| M | **D1/H47 Claims_Model** extract (admin + REST + counts). |
| S | **D2/H45 verification resolver**: 4 call-sites → `wb_listora_is_verified()`. *Security: re-opens PR #71 leak.* |
| S | **D4/H46 IP resolver**: shared validated `resolve_client_ip()`. *Security: spoofable audit trail.* |
| S | **D3/H48 + D12 geo haversine**: Need_Matcher + submission check_duplicates → `wb_listora_service('geo_query')` (add to Free interface first if needed). |
| S | **D5/D6/D7/D8 Listing_Data canonical reads** (rating-summary, review-distribution caching, favorites count + ids). |
| S | **D13 analytics record_event** public static; Lead_Form delegates. |
| S | **D11/D10 report vocabulary + storage** helpers. |
| M | **D17 breadcrumb** shared item builder + JSON-LD shape. |
| M | **D15 abortableApiFetch** consolidate 4→2. |
| S | **D14 multi-criteria** private `get_criteria_averages()`. |
| S | **D9 criteria-collection JS** shared util. |
| L | **D18 importer/migrator base** (CLAUDE.md backlog: `Term_Helper` + `Importer_Base`). |
| S | **D19/D20/D21/D22/D24** low-severity dedup (favorites hook deprecation, double balance fetch, days-until-expiry helper, claims counts, dead Comparison badge). |

### Phase 3 — Correctness (remaining HIGH/MEDIUM)

| Size | Item |
|------|------|
| S | **H1 submission PUT** capability escape hatch. |
| M | **H2 submission geo** bounding-box pre-filter. |
| S | **H9/H10 renewal** cache invalidation + `purchase_url` in quote. |
| S | **H7/H8 favorites** bootstrap LIMIT + dashboard prime. |
| S | **H13/H14 schema** Boolean cast + breadcrumb term-link. |
| M | **H17 verification** `verified_only` UI control + REST arg + `is_verified` index. |
| M | **H24/H25/H26 multi_criteria** single-listing REST hook + batch + per-review display. |
| S | **H28 photo_reviews** delete endpoint + cumulative cap + MIME restriction. |
| M | **H30 advanced_search** table-vs-meta decision + align QA artifacts. |
| S | **H38/H39 analytics** emit share event + Pro retention filter bridge. |
| M | **H37 reverse_listings** batch dashboard responses. |
| S | **H6 claims** Pro `accepts_args` to 3. |
| S | **H19/H40 + journeys** rewrite stale QA journeys (lead_form, analytics, white_label, badges cron, multi-criteria SQL, advanced_search field names). |

### Phase 4 — UX polish

| Size | Item |
|------|------|
| S | i18n sweep: submission verify/dup cards, comparison client re-render, reviews JS, lead_form labels. |
| S | a11y: star focus rings (reviews), replace `prompt()` modals (reviews/report), `aria-live` on lead_form + renewal status. |
| S | tap targets: quick_view gallery arrows 30→44px; `--sm` button floor. |
| S | dark mode: coming_soon splash; renewal raw rgba → tokens; comparison/quick_view/needs-grid/verification hex fallbacks (INV-14). |
| M | SEO_Pages: extract render to template + `seo-pages.css` (tokens, logical props, dark mode), use theme header/footer. |
| S | pagination: outgoing_webhooks list, analytics dashboard, buddy_press tabs, claims dashboard tab. |
| S | remove `wp-element-button` from all Pro customer-facing buttons (systemic). |
| S | remove ~10 redundant in-`init()` toggle re-checks; delete dead surfaces (role param, map_styles option, CRON_HOOK alias, `$new_expiry`, enqueue_places stub). |

---

### Reaching "100% justified"

The product is **not** 100%-justified today. The fastest path to a *defensible* "every feature usable + wired" claim is **Phase 1** (mostly S-sized) — it converts the 2 half-cooked features and the 6 critical broken sub-flows into working surfaces. **Phase 2 D2/D4/D3** must ride alongside Phase 1 because they are also security/contract regressions, not mere tidiness. After Phases 1–2, every feature would be honestly partially-wired-trending-fully-wired; Phases 3–4 close the remaining correctness + polish gaps to reach fully-wired.

*Conventions: follows `audit/AUDIT_VERDICT_*.md` severity ladder (Critical→High→Medium→Low) and the `audit/derived/` boundary/duplicate tooling outputs. File missing Free surfaces in Free first (CLAUDE.md 5-step extension order).*
