# Changelog

All notable changes to WB Listora will be documented in this file.

## [1.2.0] - 2026-06-10

### Added

- **Background imports** (`bcf7aeb`, `0b84d0b`): demo packs and large CSV imports run on Action Scheduler batches - resumable, idempotent (row fingerprinting), with a REST progress endpoint, live progress widget, and per-column field-mapping UI.
- **Analytics-lite view tracking** (`81e709f`): per-listing view counts on the dashboard, an admin Views column, and a REST field; supersedes itself when Pro analytics is enabled (`2114d0a`).
- **Email template editor MVP** (`cc58535`): per-event subject/body overrides under Settings > Notifications, honored on REST and cron send paths (`aefd54d`).
- **Bulk-edit listing actions** (`b74e91f`): approve/reject/feature/unfeature/assign-category via core bulk_actions, guarded by `Status_Manager::is_valid_transition`.
- **One-click unsubscribe** (`13a90e9`): RFC 8058 signed-token unsubscribe controller (`/listora/v1/unsubscribe`) wired into marketing email footers; review_reminder opt-out on profile + admin toggles.
- **WP privacy tools** (`e4ede4d`, `29c523b`, `5de3bbc`): personal-data exporter + eraser for claims, reviews, favorites; reusable rating-recompute entry point (`3cd9d74`).
- **Review-reminder event** (`bed3f7c`) and **Open now / Closed badge** on listing detail from `listora_hours`, timezone + overnight aware (`400e747`).
- **HivePress migrator** (`2f32196`) - listings, categories, images, reviews via Migration_Base.
- **Inline dashboard add/edit forms** (flow-closure wave): the submission block renders inline in My Listings via `?action=add|edit`.
- **Submission form style setting**: Settings > Submissions chooses wizard vs single page form site-wide; the block's `layoutMode` default became `default` (defers to the setting, explicit editor choices win) + `wb_listora_submission_layout_mode` filter. Side fix: the single-form CSS hid `__stepper` but the template renders `__progress` — the wizard progress bar leaked into every single-form context.
- **Field-aware validation copy**: `requiredFieldMessages` map in `listoraI18n` (filter `wb_listora_required_field_messages`) — the Media step now prompts "Add a featured photo to continue."; `requiredFieldError` is finally localized.
- **New hooks**: `wb_listora_search_resolved` (`b6dabd1`), `wb_listora_dashboard_credit_row_actions` (`fa16309`), `wb_listora_{before,after}_dashboard_favorites` (`13ddecb`), `wb_listora_show_credits` (credits surfaces become gateable - Pro's new Monetization toggle answers it).

### Changed

- Dashboard sidebar restyled as an elevated card panel (`142e256`); mobile tab-rail scroll affordance (`4c95242`); view counts render `0` instead of hiding (`99a5003`).
- Hours builder gives live Open-24h / Closed feedback (`0edbe56`, `1d631e0`); customer-facing `--sm` buttons meet the 40px tap-target floor (`15ecfd8`); featured images get an alt-text fallback (`fe0737c`).
- Database migrations run eagerly on plugins_loaded after an update (`ce83bb6`, BC #9970182629).

### Fixed

- **Featured block columns=0 fatal** (BC #9989784605): the block-renderer REST API and saved content bypass the editor's JS-only `min: 1`, so `columns: 0` hit the carousel dot-count division uncaught (`DivisionByZeroError` - editor preview 500, fatally truncated page for visitors). `render.php` now floors columns at 1; Grid + Categories get the same clamp (their `--*-columns: 0` collapsed the layout); all three block.json schemas declare `"minimum": 1` so attribute validation rejects 0 outright. Regression journey: `regression/featured-columns-zero-fatal.md`.
- **Background import stuck at RUNNING** (`3b439b8`, BC #9977212594): AS does not retry failed actions - chunks now self-requeue up to 3 consecutive failures then mark the run failed; FAILED is terminal (no finalize/DONE overwrite, no resurrect).
- **Featured block silent disappearance** (`14bcc37`, BC #9977213192): canonical empty state instead of a bare return.
- **Services panel far below listings** (`8026b89`, BC #9976599203): Manage Services opens as a modal dialog with Esc/backdrop/X close and focus return.
- **Active filter showed non-published listings** (`fd52cf5`, BC #9962484094): `active` now requires `publish` status.
- **Favorites tab not theme-overridable** (`13ddecb`, BC #9977212895): extracted to `templates/blocks/user-dashboard/tab-favorites.php`.
- **Docs buttons 404** (`8197d4c`, BC #9919933465): store docs are one page with `#{slug}-ls` anchors; all sections mapped, Pro sections included.
- **Submission map picker stacking** (`cd268b3`, BC #9976402618): `isolation: isolate` confines Leaflet below fixed theme headers.
- **Double clear icons in search** (`4ee2e2b`, BC #9962442616): native `::-webkit-search-cancel-button` suppressed.
- **Post-submission message alignment** (`8f0c101`, BC #9962418696): success card capped at 520px and centered; buttons stack on mobile.
- Import pipeline hardening: pending-only enqueue idempotency unblocks multi-chunk runs (`b359c1e`); CSV upload no longer aborted mid-flight (`59cfb19`).

### Removed

- Dead `src/blocks/listing-detail/view.js` + build artifacts (`b19eb28`, BC #9977213076) - the shared IAPI store loads globally via the render_block filter.

### Documentation

- Typography-attributes deferral recorded at both code sites (`293d2f8`, BC #9977214822).

## [1.1.0] - 2026-06-06

### Added

- **Pro feature toggles on the Features screen** (`332eb56`, BG-4 / #9952700845): when WB Listora Pro is active its toggles register into Free's Features screen, replacing Pro's parallel tab.
- **WP-CLI subcommands** (`43ded68`): `wp listora test-email` + `wp listora cleanup`.

### Security

- **Prepared the WP-CLI `SHOW TABLE STATUS` query** (`7984a51`, AUD-F5).

### Performance

- **Listing-grid ratings batch-prefetched** (`5b07701`, AUD-F2): kills the per-card N+1 rating query on every grid render.
- **Admin Reviews & Claims tables paginated** (`db3ab0c`, AUD-F3) and **dashboard Claims tab paginated** (`fcbf0d1`, M6-M7) on a canonical `Claims_Model` (`db8e2cd`, DUP-1).
- **Calendar recurring-events query bounded** (`8b64b5d`, AUD-F4).
- **`wb_listora_settings` option no longer autoloads** (`357fb26`, AUD-F6).
- **Composite index for per-user review pagination** (`10677e7`).

### Fixed

- **Admin script 404 on demo import** (#9941185510): the admin script was enqueued from the `src/` source path, which is stripped from release builds and returns 404. It now resolves to the packaged `build/admin/admin.js` (with its asset.php dependencies/version).
- **Documentation buttons opened a dead domain** (#9919933465): docs links pointed to the unreachable `wblistora.com`; they now point to `store.wbcomdesigns.com/listora/docs/`, with per-tab slugs and the `wb_listora_docs_url` filter intact.
- **Search clear and near-me icons flush against field edges** (#9932168698): the visible icon circle (32px) now sits centred inside the tap target with vertical spacing; the 40px+ hit area is preserved. RTL synced.
- **Business Hours missing from submission preview** (#9928220940): every listing type renders identical `meta_business_hours[]` field names, so inactive (hidden/disabled) type blocks overwrote the active values, and the time picker had not flushed its value at preview time. The preview now skips disabled/hidden blocks and flushes the picker.
- **Listing-type tab counted as a filter** (#9932186473): selecting a type tab no longer increments the Filters badge (a type pivot is navigation, not an applied filter), and the active type tab now receives the highlight and `aria-pressed`.
- **Dashboard service and more-actions controls unresponsive** (#9932329169): the "Manage services" toggle targeted the wrong element/subtree; it now resolves the services panel by listing id, and the more-actions dropdown opens.
- **Map error when clustering disabled** (#9909608577): with clustering off the map used `L.layerGroup()` (no `getBounds()`), throwing in `fitMarkersInView`. It now uses `L.featureGroup()` plus a guard. Verified on a clustering-off page.
- **Write a Review form unstyled and misaligned** (#9927635866): the review-form styles were defined in a stylesheet not enqueued on the listing page. Full responsive styling now lives in the listing-detail block (criteria stack at <=640px, full-width submit on mobile, star select/hover states, clearer section spacing, token-based borders). RTL synced.
- **Import/Export integrity** (#9927642448): the CSV importer and migrator base now route taxonomy terms through the shared `Term_Helper` (consistent with JSON/GeoJSON, gaining entity-decode normalization); CSV import and export now support the `listora_listing_location` taxonomy so location round-trips without data loss; added a real per-column CSV field-mapping UI (also fixing a pre-existing fully-broken CSV import); docs aligned to the implemented behavior.
- **Listings-per-page setting ignored in grid server render** (#9872099543): the grid block declared a `perPage` default that always overrode the saved "Listings per page" setting on first paint. The default is removed so the site-wide setting applies; the editor control falls back to the setting when left empty.
- **Required Skills field invisible on Job listings** (#9900622602): a multiselect field with no predefined options rendered only its label. It now falls back to a comma-separated text input so the field is usable.
- **Business Hours builder congested/misaligned** (#9895239227): the hours markup emitted class names the layout CSS did not target, so the alignment styling never applied. The markup now matches the CSS contract; rows align on desktop and stack cleanly on mobile.
- **REST permission callbacks returned a bare boolean** (#9910209003): the settings endpoints (and sibling notification-log endpoints) now return the structured `listora_unauthorized` (401) / `listora_forbidden` (403) codes on denial instead of the generic `rest_forbidden`. Capability logic unchanged.
- **EDD SL SDK could WSOD the whole site when vendor missing** (`1780539` + `519ef2b`, AUD-F11): the bundled license SDK is now composer-free and mandatory, with a defensive load guard.
- **Credits SDK payment hardening** (`5c20d70`, `9ee59cc`): adapter idempotency, PayPal refund linkage, refund events carry real amount/context, and the v2 `payment_intent` column lands on existing installs.
- **Duplicate SEO output with Yoast / Rank Math** (`d412d58` M9, `9d4a956`, `8ecd82e` M10): one canonical SEO-plugin detector guards `output_schema()` against duplicate JSON-LD, and WP-core `rel_canonical` is suppressed on listing singulars.
- **`og:locale` missing from Open Graph output** (`deb68b6`, M11).
- **Breadcrumb and BreadcrumbList drift** (`b348c4e`, M12): both render from one canonical trail helper.
- **Listing-detail REST schema field ignored the Schema.org toggle** (`c0995ab`, M8).
- **`/search` REST always returned `rating.average` 0** (`5106ee4`).
- **Review report used native `prompt()`** (`ea6e027`, M4): replaced with an accessible IAPI modal; report reasons validate against a canonical vocabulary (`1d87a15`, M5).
- **Detail-page review form hardcoded a 20-char minimum** (`236dc03`): now reads `reviews.min_length`.
- **Submission success card resolved the Dashboard URL unreliably** (`f623a39`): now resolved via the Page Registry.
- **Pagination active-page text invisible under aggressive theme link rules** (`b299fd6`).
- **Dark-mode contrast on BuddyX 5.1.0** (`e83e4b8`, BG-3 / #9953255233).
- **Submission map overflow + Business Hours misalignment** (`d0ae001`, BG-2 / #9952543239).
- **Renewal modal error not announced to screen readers** (`1535f00`): `aria-live` added.
- **Customer-facing `--sm` buttons under tap-target floor** (`86dd941`, AUD-F7): raised to 36px.
- **`wb_listora_send_notification` filter missing the recipient** (`0673644`): `$to` now passed as the 4th arg.

### Changed

- **Demo import is faster and more resilient** (#9941186252): per-image download timeout reduced from 30s to 10s, gallery images capped per listing, repeated image URLs downloaded only once (in-run + media-library de-duplication), and a failed image is skipped instead of stalling the request. The import remains synchronous; a background-job rearchitecture is tracked separately.
- **Credits SDK loads defensively** (#9927933886): a build packaged without the SDK submodule source now degrades gracefully with an admin notice instead of causing a fatal on admin pages. Note: the SDK source must still be shipped in release builds for credit features to function.
- **Submission-wizard location map hardened** (#9932290292): the single fixed-delay resize was replaced with a `requestAnimationFrame` recalc plus a `ResizeObserver` gated on container height, so the map recalculates as soon as it is visible. Hardening only - the original intermittent blank-map report could not be reproduced on this build and is pending QA confirmation.
- **Submission preview hardened** (#9927816084): a single failing preview section can no longer blank the whole preview, and field-visibility detection is more robust. Hardening only - the original blank-preview report could not be reproduced on this build and is pending QA confirmation.
- **Wbcom Credits SDK re-homed** (`bf889d9`): moved from a gitignored git submodule at `vendor/wbcom-credits-sdk` to a committed composer-free copy at `libs/wbcom-credits-sdk/` with a self-registered PSR-4 autoloader. Plugin zip and fresh clone both work with zero `composer install` / `git submodule init`.
- **Off-canon CSS breakpoints consolidated** (`c8c60cc`, AUD-F8).

### Compatibility

- **Pro version lockstep**: WB Listora Pro must be at the same `x.y.z` (`1.1.0`). `bin/build-release.sh` refuses to package on drift.
- **No breaking changes** for end users. No database schema changes beyond the SDK's additive `payment_intent` column.

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
