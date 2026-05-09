# Changelog

All notable changes to WB Listora will be documented in this file.

## [1.0.4] - 2026-05-09

### Added

- **Same-family primitive vocabulary** (Part 7.6.1 / F9): canonical `.listora-page--{single,list,dashboard,booking}` shells, `.listora-ui-card__head/body/foot` triplet, `.listora-card--empty` + `.listora-empty/__icon/__title/__desc/__actions`, semantic badge variants, numeric spacing/type tokens. All 11 Free blocks migrated to canonical shells.
- **Public Page Registry helper**: `wb_listora_register_page( $key, $config )` + `wb_listora_register_pages` action. Pro and themes consume the helper instead of the internal `Page_Registry` class (closes architecture invariant INV-3 on Pro's side).
- **`wb_listora_after_service_detail` action** fired inside services-grid foreach (`templates/blocks/listing-detail/tabs.php:300`). Pro `Services_Pro::fire_booking_hook` listener no longer orphan.
- **`wb_listora_member_profile_url` filter** at three sites (review tab, reviews block card, REST). Pro BuddyPress integration replaces empty default with `bp_core_get_user_domain( $user_id )`. Reviews REST response now carries `user_profile_url` field.
- **Action Scheduler migration** for all 6 cron jobs (P1-1.B): expire-listings, cleanup-drafts, send-expiry-reminders, rotate-featured-listings, cleanup-email-verification, cleanup-notification-log. WP-Cron drops jobs at scale; AS retries.
- **REST list prefetch** (P1-2): `Listings_Controller::get_items` now calls `update_post_caches` + `update_object_term_cache` before the prepare loop. Saves ~100 queries per request at 100K listings.
- **AbortController + 10s timeout** wrapper for 43 apiFetch sites (P1-3). Translatable "Network is slow — please try again" toast replaces permanent loading on slow networks.
- **4 new block render hooks** (P1-5): `wb_listora_before/after_listing_card`, `wb_listora_search_before/after_form`. Pro and themes can extend without forking.
- **Capabilities helper class** (P2-3): `Capabilities::can_*()` query helpers + 5 cap constants for cleaner cap checks across REST controllers.
- **Comprehensive QA pipeline**: `docs/qa/AGENT_SMOKE_RUNBOOK.md` (536-line A-G runbook covering all 13 admin pages + every customer flow + 29 Pro toggle contracts), 29 executable journeys (10 customer + 10 admin + 9 regression sentinels), `audit/qa-index.json` machine-readable discovery, `bin/build-release.sh` smoke gate.

### Fixed

- **Setup wizard headers regression** (#9867159785, round 2): POST handler moved to `admin_init` priority 1 via new `Setup_Wizard::init()` + `handle_post_submission()` static pair. "Go to Dashboard" button no longer renders a blank page when user holds `manage_listora_settings` but not `edit_listora_listings`.
- **Empty Media fieldset** (#9867347053): suppress `<fieldset>` when every field in the group is renderer-skipped. Affects 10 listing types' Details step.
- **Raw attachment ID on Overview tab** (#9867775853): skip `file`-type fields on tabs.php Overview loop. Logos render as image on their own field-group tab; Overview tab no longer prints `Company Logo: 818`.
- **Map popup featured image** (#9867372176): markers JSON now carries `image` field via `update_meta_cache` prefetch; popup template injects `imageHtml` snippet.
- **Map block fatal** (#9871222447): replaced non-existent `update_post_meta_cache()` call with `update_meta_cache( 'post', $listing_ids )`.
- **Business Hours flatpickr round 2** (#9856828615): vendored 4.6.13 + idempotent attach via `data-listora-flatpickr-attached` flag. Firefox now shows time picker (was native numeric spinner).
- **Service description toggle silent fail** (#9872013428): `toggleServiceDesc` action now scopes to the clicked card's description.
- **Filter-count badge ignored dropdowns** (#9871208081): badge calculation now sums every active filter type including dropdown facets.
- **Services Photo upload missing** (#9872014083): new Photo column in Services meta box + delegated media-library handler. `image_id` persists across reload.
- **Notification emails for status transitions** (commit `0aa62ca`): replaced typo'd hook listeners (`wb_listora_listing_publish`, `wb_listora_listing_listora_rejected`, `wb_listora_listing_listora_expired`) with single canonical listener on `wb_listora_listing_status_changed`. Approve/reject/expire emails now reach owners.
- **Map provider filter never fired** (commit `847dcc8`): `wb_listora_map_provider` filter now actually applies in `wb_listora_get_setting('map_provider')`. Pro Google_Maps listener finally takes effect when API key is configured.
- **Helpful vote button** restored on detail Reviews tab (commit `253cef9`).
- **Listing-owner reply form** restored as inline form (was opening non-existent modal — commit `e01486b`).
- **FULLTEXT index** split out of `dbDelta()` to avoid SQL syntax error on activation (commit `7606f8c`).
- **IAPI modal getter pattern** (commit `63411c8`): `data-wp-class--*` directives now bind to derived getter properties (e.g. `state.isClaimModalOpen`) instead of inline literal-comparison expressions. IAPI's reactivity tracks property reads, not equality re-evaluations.
- **Dashboard 2-column layout regression** (today): reverted `.listora-page--dashboard` shell on dashboard outer wrapper after it overrode `.listora-dashboard`'s grid.
- **Empty state hidden on 0-result archives** (today): `state.showEmptyState` getter now returns true when `state.totalResults === 0` regardless of `hasSearched`.
- **Action Scheduler init-timing notices** (smoke-prep finding): `Cron_Scheduler::has_action_scheduler()` now checks `did_action('action_scheduler_init') > 0` so AS calls before the data store is initialized fall through to WP-Cron temporarily and migrate cleanly on the next request. Eliminates `_doing_it_wrong` notice spam on every WP-CLI invocation and admin bootstrap. Same guard added to Pro's three `maybe_schedule_*` methods (Analytics, Advanced_Search, Audit_Log).

### Changed

- **Architecture invariants** (12/12 pass per `bin/architecture-checks.sh`):
  - INV-3 — no Pro→Free internal-namespace coupling (Pro now uses `wb_listora_register_page()` helper).
  - INV-12.1 — Free is sole writer of `_listora_is_claimed`. New `wb_listora_listing_claimed` action fired from `Claims_Controller::approve_claim`. Pro Verification listens.
  - INV-12.2 — Free is sole writer of `_listora_expiration_date`. New `wb_listora_listing_expiration_date` filter fired before every meta write. Pro Pricing_Plans answers via filter, not direct write.
  - INV-12.3 — New `\WBListora\Migration\Migrated_From_Tracker` class is the sole writer of `_listora_migrated_from`. Pro competitor migrators call `Tracker::set()`.
  - INV-12.4 — Free reads webhook secret via `wb_listora_webhook_secret` filter. Pro `Webhook_Receiver::get_secret` answers. Free no longer reads `wb_listora_pro_webhook_secret` directly.
- **WPCS + PHPStan baselines restored** to green. Pre-push hook runs cleanly without `SKIP_LOCAL_CI=1`.
- **REST contract**: list endpoints return `{ items, total, pages, has_more }` with `has_more = (offset + count) < total` (never `count === limit`).
- **Capability helper migration**: 5 read-only `get_option('wb_listora_settings')` sites routed through `wb_listora_get_setting()` helper for cache hits + per-key extension filters.

### Documentation

- **CLAUDE.md QA Pipeline section**: release gate checklist, self-growth contract, discovery order for new sessions.
- **Audit baselines refreshed** to 2026-05-08 (manifest schema v2.1, wppqa **0 release blockers**, cross-plugin coupling **29 pairs**).

### Compatibility

- **Pro version lockstep**: WB Listora Pro must be at the same `x.y.z` (`1.0.4`). Sites with mismatched versions get an admin notice; build-release.sh refuses to package on drift.
- **No breaking changes** for end users. Theme overrides for templates and email templates remain compatible.

## [1.0.0] - 2026-04-05

### Added
- Complete WordPress directory plugin with 11 Gutenberg blocks
- Listing Grid, Card, Detail, Search, Map, Reviews, Submission, Categories, Featured, Calendar, User Dashboard blocks
- Interactivity API for all frontend interactions (no jQuery)
- 10 custom database tables for high-performance queries at scale
- Full-text search with faceted filtering and geo-distance queries
- 22 custom field types with dynamic listing type system
- Frontend listing submission with multi-step form and guest registration
- Review system with star ratings, helpful votes, owner replies, and reporting
- Business claims with admin approval workflow
- Favorite/save listings with user dashboard management
- Business hours with day-of-week display
- Services system with CRUD, categories, and Schema.org markup
- Event support with recurring events and calendar view
- Import/Export: JSON, GeoJSON, and 4 competitor migration tools
- 14 email notification templates (submission, approval, expiry, claims, reviews)
- reCAPTCHA v3 + Cloudflare Turnstile spam protection
- Schema.org structured data (LocalBusiness, Restaurant, Hotel, Event)
- 98 action/filter hooks for Pro and third-party extensibility
- REST API with 41+ endpoints and response filters on all resources
- WP-CLI commands for index rebuilding, data seeding, and maintenance
- WPCS, PHPStan Level 5, PHPUnit, and Plugin Check CI pipeline
- 5 demo data packs (restaurants, hotels, healthcare, real estate, events)
