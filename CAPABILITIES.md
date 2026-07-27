# WB Listora (Free) — Capability Catalog

**Generated:** 2026-07-15 · **Plugin version:** 1.2.2 (Free) · **Branch:** 1.2.3

This is the plugin-owned master list of functionality, per **rule 7 of the `wbcom-mobile-app` skill**. It is the
**spine** a mobile app's coverage matrix maps against: every row here is either claimed by an app module, explicitly
deferred, or explicitly declared out of scope (admin). Nothing member-facing may exist outside this file.

**A capability is a thing a USER can DO.** Classes, controllers, and services are not capabilities — they are the
`REST / entry point` column.

## Method / trust

| Source | Use |
|---|---|
| `audit/FEATURE_AUDIT.md` | Richest existing catalog — blocks, tables, cron, emails, admin pages. Primary. |
| `docs/qa/journeys/` (103) | 17 customer / 18 admin / 63 regression / 3 system. Customer + admin journeys ARE capabilities, expressed as flows. Cited in Notes. |
| `CLAUDE.md` | Product surface (CPT, taxonomies, blocks, dashboard tabs, search, reviews, favorites, claims, services, submission). |
| **Live REST** (`GET /wp-json/listora/v1`) | **Ground truth.** 98 live routes on listora.local (Free 1.2.2 + Pro 1.2.2 active, one shared namespace). |
| `audit/manifest.json` | **NOT trusted for routes.** Its generator stores unevaluated PHP concatenation literally (`/badges./{id}`, `/import./fields`) — 128 manifest endpoints vs 98 live. Used only for cross-checking hooks/tables. |

**REST attribution was verified live, then each route traced back to a `register_rest_route` call in *this* repo.**
A route is attributed to Free only if that call exists here. The live namespace is Free+Pro combined; routes present
live but absent from this repo (`/analytics/*`, `/audit-log/*`, `/badges/*`, `/compare*`, `/migration/*`,
`/moderators/*`, `/services/{compare,search}`, `/listings/{id}/contact`, and all `/import/*` beyond
`csv|json|geojson|progress|queue/csv`) are **Pro** and are excluded from this catalog.

**Feature flags** are the Free toggles in `includes/class-features.php` → `wb_listora_features_registry()`, persisted
to the `wb_listora_features` option and rendered on Settings → Features. Free registers exactly 10:
`submission`, `reviews`, `claims`, `favorites`, `renewal`, `report_listings` (category `core`) ·
`schema`, `opengraph`, `breadcrumbs`, `sitemap` (category `seo`). All default **on**.
Rows marked `always-on` have no toggle. Rows naming a `wb_listora_settings` key are gated by a **setting**, not a
feature flag — noted as such.

**Actors:** `guest` (logged out) · `member` (logged in) · `owner` (listing owner) · `admin` (site staff).
**🔒 ADMIN-ONLY rows are out of scope for mobile** and are grouped coarsely — but completely enough that nothing
member-facing hides inside them.

---

## 1. Discovery / Search

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Browse a paginated listing grid with filters/facets | guest, member | block `listora/listing-grid` | `GET /listings` | always-on | `blocks/listing-grid/render.php`. Envelope `{listings,total,pages,has_more,cursor,next_cursor}` + `X-WP-Total`. Journey `regression/rest-listings-envelope.md`, `regression/pagination-active-page-contrast.md`. |
| Search listings with faceted / geo / fulltext filters | guest, member | block `listora/listing-search` | `GET /search` | always-on | `includes/rest/class-search-controller.php:39`. Public by design (Rule 2 allowlist). Journey `customer/04-search-with-filters.md`, `regression/filter-count-dropdowns.md`, `regression/search-rating-average-nonzero.md`. |
| Get autocomplete suggestions while typing | guest, member | block `listora/listing-search` | `GET /search/suggest` | always-on | `class-search-controller.php:52`. Journey `regression/search-suggest-envelope-unwrap.md`, `regression/search-single-clear-icon.md`. |
| View listings on a map (Leaflet default) | guest, member | block `listora/listing-map` | `GET /settings/maps` | always-on | Provider via `wb_listora_map_provider` filter (Pro swaps to Google). Journey `regression/map-provider-honored.md`, `regression/map-fatal.md`. |
| Re-search within current map bounds ("search this area") | guest, member | block `listora/listing-map` | `GET /search` (bbox args) | always-on | Journey `regression/map-search-this-area-bounds.md`. |
| Browse categories as a grid with counts | guest, member | block `listora/listing-categories` | taxonomy `listora_listing_cat` | always-on | `blocks/listing-categories/render.php`. Journey `customer/09-categories-block.md`. |
| Browse featured listings carousel | guest, member | block `listora/listing-featured` | `GET /listings` (featured args) | always-on | Journey `customer/10-featured-listings.md`, `regression/featured-block-empty-state.md`, `regression/featured-columns-zero-fatal.md`. |
| Browse an event calendar (recurring + virtual occurrences) | guest, member | block `listora/listing-calendar` | `wb_listora_calendar_events` filter | always-on | Journey `customer/08-calendar-block.md`. |
| See related listings on a detail page | guest, member | block `listora/listing-detail` | `GET /listings/{id}/related` | always-on | `class-listings-controller.php:362`. Public by design. Journey `regression/listing-extra-rest.md` §1. |
| Batch-fetch listings by ID | guest, member | REST (app/client) | `POST /listings/bulk` | always-on | `class-listings-controller.php:427`. Public read-only. Journey `regression/listing-extra-rest.md` §2. |
| Bootstrap frontend/app config (maps, i18n, toggles) | guest, member | REST | `GET /settings/app-config` | always-on | `class-settings-controller.php:71`. Public by design. **Mobile entry point.** Journey `regression/listing-extra-rest.md` §5. |
| Browse the public listing-type catalog + its fields/categories | guest, member | block, REST | `GET /listing-types`, `/listing-types/{slug}`, `/{slug}/fields`, `/{slug}/categories` | always-on | `class-listing-types-controller.php:34,55,93,113`. Public by design. Journey `regression/type-contact-fields.md`. |
| Follow breadcrumb navigation on a listing page | guest, member | template | `breadcrumbs` | Journey `regression/breadcrumb-trail-parity.md`. |

## 2. Listings

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| View a single listing (gallery, sidebar, tabs) | guest, member | block `listora/listing-detail` | `GET /listings/{id}` | always-on | `blocks/listing-detail/render.php`. Public by design. |
| Fetch an enriched single listing (mobile/app shape) | guest, member | REST | `GET /listings/{id}/detail` | always-on | `class-listings-controller.php:226`. Includes `social_links` (flat `{slug:url}`), RFC-3339 `created_at`/`updated_at`. **Primary mobile detail route.** Journey `regression/rest-listing-timestamps.md`. |
| View a listing's social links ("Follow" card) | guest, member | template `listing-detail/sidebar.php` | `_listora_social_links` meta via `/listings/{id}/detail` | always-on | `sidebar.php:56-76`, ordered by `Field::social_link_platforms()`. |
| View a listing's business hours / open-now state | guest, member | template `listing-detail/sidebar.php` | `wp_listora_hours` table | always-on | Journey `regression/business-hours-firefox.md`. |
| Share a listing (share modal) | guest, member | block `listora/listing-detail` | IAPI `activeModal='share'` | always-on | Boolean getter `isShareModalOpen`, `src/interactivity/store.js:89-98`. Touched by `customer/01-browse-and-favourite-a-listing.md:46`. |
| Contact a listing via its contact form | guest, member | template + REST | `POST /listings/{id}/contact-form` | always-on | `includes/class-contact-form.php:75`. Anonymous-allowed: nonce + honeypot + `Anti_Spam` + per-IP-per-listing 3/hr + per-listing 20/day caps. Journey `customer/16-listing-contact-form.md`, `system/spam-protection-layers.md`. |
| Report an inaccurate / spam / closed listing | guest, member | block `listora/listing-detail` | `POST /listings/{id}/report` | `report_listings` | `class-listings-controller.php:332`. Journey `regression/report-listing.md`. |
| Deactivate my own listing | owner | block `listora/user-dashboard` | `POST /listings/{id}/deactivate` | always-on | `class-listings-controller.php:294`. Confirms via `listoraConfirm` promise-modal (`store.js:820-833`). Journey `customer/14-dashboard-listings-tab-manage.md`. |
| Reactivate my own listing | owner | block `listora/user-dashboard` | `POST /listings/{id}/reactivate` | always-on | `class-listings-controller.php:313`. Fires `wb_listora_after_reactivate_listing`. Journey `customer/14-dashboard-listings-tab-manage.md`. |
| Preview a renewal quote before renewing | owner | dashboard modal | `GET /listings/{id}/renewal-quote` | `renewal` | `class-listings-controller.php:387`. Journey `regression/listing-extra-rest.md` §3. |
| Renew an expired listing | owner | dashboard modal | `POST /listings/{id}/renew` | `renewal` | `class-listings-controller.php:406`. Journey `customer/06-listing-renewal.md`, `regression/renewal-modal-error-aria-live.md`. |
| Delete my own listing | owner | dashboard | `DELETE /listings/{id}` | always-on | `class-listings-controller.php:256`. |
| Feature a listing (upgrade) | owner | dashboard | `POST /listings/{id}/feature` | always-on | `class-listings-controller.php:275`. Free wires the service; credit-gated rotation is Pro. Also admin-side via `Admin\Featured_Metabox`. |
| See a listing's view count | owner | dashboard | `wp_listora_analytics` table | always-on | Journey `admin/owner-sees-view-count.md`. |

## 3. Reviews

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Read reviews on a listing | guest, member | block `listora/listing-reviews` | `GET /listings/{listing_id}/reviews` | `reviews` | `class-reviews-controller.php:33`. Public by design. When flag off, surface hides — journey `regression/reviews-feature-disabled.md`. |
| Write a star-rated review | member | block `listora/listing-reviews` | `POST /listings/{listing_id}/reviews` | `reviews` | Criteria via `wb_listora_review_criteria` (Pro adds multi-criteria). Journey `customer/03-write-and-reply-to-a-review.md`, `regression/review-minlength-reads-setting.md`. |
| Edit / delete my own review | member | block, dashboard | `PUT|DELETE /reviews/{id}` | `reviews` | `class-reviews-controller.php:112`. |
| Vote a review "helpful" | member | block `listora/listing-reviews` | `POST /reviews/{id}/helpful` | `reviews` | `class-reviews-controller.php:159`. Dedup via `wp_listora_review_votes`. Hooked up in `templates/blocks/listing-detail/tabs.php` (commit 253cef9). |
| Reply to a review on my listing | owner | block + dashboard `tab-reviews.php` | `POST /reviews/{id}/reply` | `reviews` | `class-reviews-controller.php:172`. Inline form, not a modal (commit e01486b). Journey `customer/03-write-and-reply-to-a-review.md`. |
| Report an abusive review | member | review report modal | `POST /reviews/{id}/report` | `reviews` | `class-reviews-controller.php:192`. Journey `regression/review-report-modal.md`, `regression/review-report-reason-enum.md`. |

## 4. Favorites

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Save a listing to favorites | member | card + detail heart | `POST /favorites` | `favorites` | `includes/rest/class-favorites-controller.php:33`. Journey `customer/01-browse-and-favourite-a-listing.md`. |
| List my favorites | member | dashboard `tab-favorites.php` | `GET /favorites` | `favorites` | Journey `regression/dashboard-favorites-template-override.md`. |
| Remove a listing from favorites | member | card + dashboard | `DELETE /favorites/{listing_id}` | `favorites` | `class-favorites-controller.php:76`. |
| Be prompted to log in when favouriting as a guest | guest | login modal | IAPI `activeModal='login'` | `favorites` | Register CTA suppressible via `wb_listora_login_modal_register_url`. Journey `regression/anon-login-modal-register-cta.md`. |

## 5. Claims

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Claim ownership of an unverified listing (with proof) | member | detail claim modal | `POST /claims` | `claims` | `includes/rest/class-claims-controller.php:42`. Proof text + files. Journey `customer/05-claim-a-business.md`. |
| Track the status of my claims | member | dashboard `tab-claims.php` | `GET /dashboard/claims` | `claims` | User-scoped list lives on the dashboard route, not `/claims`. Journey `customer/13-dashboard-claims-tab.md`, `regression/claims-tab-pagination.md`, `regression/claims-model-list-count-parity.md`. |
| View / withdraw a single claim | member | dashboard | `GET|PUT|DELETE /claims/{id}` | `claims` | `class-claims-controller.php:95`. |

## 6. Services

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Browse a listing's services (price, duration, photo) | guest, member | detail services tab | `GET /listings/{listing_id}/services` | always-on | `includes/rest/class-services-controller.php:38`. Public by design. Journey `regression/service-details-toggle.md`. |
| Add / edit / delete a service on my listing | owner | dashboard services modal | `POST /listings/{listing_id}/services`, `PUT|DELETE /services/{id}` | always-on | `class-services-controller.php:38,107`. Journey `regression/dashboard-services-modal.md`. |
| Upload a photo / gallery for a service | owner | dashboard services modal | `POST /listings/{listing_id}/services` | always-on | Journey `regression/services-photo-upload.md`. |
| Reorder my services | owner | dashboard services modal | `POST /listings/{listing_id}/services/reorder` | always-on | `class-services-controller.php:180`. Journey `regression/listing-extra-rest.md` §4. |

## 7. Submission

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Submit a listing via the multi-step wizard | member | block `listora/listing-submission` | `POST /submit` | `submission` | Cap `submit_listora_listing` (incl. subscriber). Journey `customer/02-submit-a-listing-wizard-end-to-end.md`, `regression/submission-rest-feature-gate.md`. |
| Submit as a guest (auto-registration) | guest | block `listora/listing-submission` | `POST /submit` | `submission` + setting `enable_guest_submission` | Setting read at `class-settings-controller.php:452`; default **off**. Journey `customer/07-guest-submission-with-email-verify.md`. |
| Verify my email to publish a guest submission | guest | emailed link | `GET /submission/verify` | `submission` + setting `guest_email_verification` (default true) | `class-submission-controller.php:125`, gate at `:341`. Token IS the credential — public by design. Unverified listings pruned at 14d by cron. |
| Resend the verification email | guest | submission success screen | `POST /submission/resend-verification` | `submission` | `class-submission-controller.php:100`. |
| Be warned my listing looks like a duplicate | member, guest | wizard | `POST /submit/check-duplicate` | `submission` | `class-submission-controller.php:58`. |
| Edit my listing (re-enter the wizard) | owner | block `listora/listing-submission` | `PUT /submit/{id}` | `submission` | `class-submission-controller.php:150`. Journey `customer/17-edit-my-listing.md`. |
| Pick a location by dragging a map pin | member, guest | wizard map step | Geocode → `wp_listora_geo` | `submission` | Journey `regression/submission-map-picker-stacking.md`, `regression/location-terms-from-address.md`. |
| Fill conditional / custom fields per listing type | member, guest | wizard details step | `GET /listing-types/{slug}/fields` | `submission` | `includes/submission-field-renderer.php`. |
| Enter social links per platform | member, guest | wizard details step | `Field::social_link_platforms()` | `submission` | Renderer `submission-field-renderer.php:310-320`; sanitizer `includes/core/class-field.php:423`. |
| Upload media / gallery for my listing | member, guest | wizard media step | `POST /submit` | `submission` | Journey `regression/media-step-field-prompt.md`, `regression/empty-media-fieldset.md`. |
| Be blocked when my submission is spam | guest | wizard | `WBListora\Anti_Spam` | `submission` | Akismet + URL-density + blacklist; fails open on Akismet outage. Journey `system/spam-protection-layers.md`. |

## 8. User Dashboard

Block `listora/user-dashboard`, tabs registered at `blocks/user-dashboard/render.php:65`:
`overview, listings, reviews, favorites, claims, credits, profile` (+ Pro `needs`, `analytics` via `wb_listora_dashboard_sections`).

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| See my overview stats (listings, views, reviews, favorites) | member | dashboard overview tab | `GET /dashboard/stats` | always-on | `class-dashboard-controller.php:53`. Journey `customer/11-dashboard-overview-tab.md`, `regression/dashboard-stats-transient-bust.md`, `regression/overview-company-logo-id.md`. |
| Manage my listings (filter by status, act per row) | owner | dashboard listings tab | `GET /dashboard/listings` | always-on | `class-dashboard-controller.php:66`. Journey `customer/14-dashboard-listings-tab-manage.md`, `regression/dashboard-active-filter-status.md`. |
| See reviews I wrote / received | member, owner | dashboard reviews tab | `GET /dashboard/reviews` | `reviews` | `class-dashboard-controller.php:106`. |
| Edit my public profile | member | dashboard profile tab | `GET|POST /dashboard/profile` | always-on | `class-dashboard-controller.php:169`. Journey `customer/12-dashboard-profile-tab.md`. |
| Read my notifications + unread badge | member | dashboard notifications tab | `GET /dashboard/notifications` | always-on | `class-dashboard-controller.php:197`. Journey `customer/15-dashboard-notifications-tab.md`. |
| Mark notifications read | member | dashboard notifications tab | `POST /dashboard/notifications/read` | always-on | `class-dashboard-controller.php:219`. |
| See my credit balance / packs | member | dashboard credits tab | `libs/wbcom-credits-sdk` | always-on | `templates/blocks/user-dashboard/tab-credits.php`. Free renders the tab + `limit_behavior='credits'` overflow CTA (`render.php:160`); purchase/gateways are Pro/SDK. |
| Use the dashboard on mobile (2-col → stacked) | member | dashboard | CSS | always-on | Journey `regression/dashboard-2-col-layout.md`, `regression/sm-button-tap-target.md`. |

## 9. Notifications / Email

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Receive lifecycle emails (approved, rejected, expired, claim, review, reply, reminders) | member, owner | email | `WBListora\Workflow\Notifications` | always-on | 14 events, `templates/emails/`. Canonical listener on `wb_listora_listing_status_changed` (`class-notifications.php:45`). Journey `regression/email-approval-send.md`. |
| One-click unsubscribe from a notification type | member, guest | emailed link | `GET /unsubscribe` | always-on | `includes/rest/class-unsubscribe-controller.php:139`. HMAC token over uid+event (`wp_salt`) IS the credential; handler renders a standalone confirmation page and `exit`s. **⚠ No journey covers this — see QA gap.** |
| Receive expiry / draft reminders | owner | email (cron) | `wb_listora_check_expirations`, `wb_listora_draft_reminder_cron` (twicedaily) | always-on | `includes/workflow/class-expiration-cron.php`. Journey `system/cron-maintenance.md`. |

## 10. Import / Export

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| 🔒 Export listings to CSV | admin | Settings → Import/Export | `GET /export/csv` | always-on | `class-import-export-controller.php:44`. Journey `admin/10-import-export.md`. |
| 🔒 Import listings from CSV / JSON / GeoJSON | admin | Settings → Import/Export | `POST /import/csv`, `/import/json`, `/import/geojson` | always-on | `class-import-export-controller.php:94,129,155`. |
| 🔒 Queue a large CSV import in the background + watch progress | admin | Settings → Import/Export | `POST /import/queue/csv`, `GET /import/progress/{run_id}` | always-on | `includes/import-export/class-background-import.php:725,750`. Journey `admin/demo-import-background-progress.md`, `regression/bg-import-failed-rollback.md`. |
| 🔒 Migrate from Directorist / GeoDirectory / ListingPro | admin | WP-CLI `wp listora migrate` | `class-cli-commands.php:528` | always-on | CLI only in Free — the `/migration/*` REST routes are **Pro**. Journeys `admin/migrate-from-{directorist,geodirectory,listingpro}.md`, `regression/migrator-context-arg.md`. |

## 11. Admin / Settings 🔒 (out of scope for mobile)

13 admin pages under the `listora` menu (`includes/admin/class-admin.php:325-476`). Caps: `view_listora_dashboard`,
`manage_listora_types`, `manage_listora_settings`. Frontend uses REST exclusively; the only 4 AJAX actions are
admin-only and `manage_listora_settings`-gated.

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| 🔒 Approve / reject a pending listing | admin | Listings list-table row actions | `POST /listings/bulk-moderate` | always-on | Cap `edit_others_listora_listings` + per-ID `edit_post`; ≤100 IDs/call. **Emits the member-facing approval/rejection email.** Journey `admin/01-approve-pending-listing.md`, `regression/listing-approve-reject-row-actions.md`. |
| 🔒 Moderate a review | admin | Listora → Reviews (`listora-reviews`) | `PUT /reviews/{id}` | `reviews` | Fires `wb_listora_review_status_changed`. Journey `admin/02-moderate-review.md`, `regression/admin-caps-reviews-claims.md`. |
| 🔒 Approve / reject a claim | admin | Listora → Claims (`listora-claims`) | `PUT /claims/{id}` | `claims` | **Grants listing ownership** to the member. Journey `admin/03-approve-claim.md`. |
| 🔒 Add / edit a listing from wp-admin | admin | CPT `listora_listing` edit screen | WP core + `Admin\Featured_Metabox` | always-on | Custom statuses `listora_rejected`, `listora_expired`, `listora_deactivated`, `listora_payment`. Journey `admin/05-add-listing-from-wp-admin.md`. |
| 🔒 Manage listing types + their fields | admin | `listora-listing-types` | `POST|PUT|DELETE /listing-types*` | always-on | Journey `admin/06-listing-types-crud.md`. |
| 🔒 Manage taxonomies (categories, locations, features, tags, service cats) | admin | edit-tags screens | `includes/core/class-taxonomies.php` | always-on | 6 taxonomies. Journey `admin/07-taxonomy-crud.md`, `regression/term-helper-consolidation.md`. |
| 🔒 Configure settings (General, Submissions, Maps, Reviews, Credits, Features, Import/Export) | admin | `listora-settings` | `GET|POST /settings`, `/settings/maps` | always-on | Tabs extendable via `wb_listora_settings_tabs`. Journey `admin/08-settings-merge.md`, `regression/settings-docs-links-live.md`. |
| 🔒 Toggle any Free feature on/off | admin | Settings → Features | `wb_listora_features` option | n/a (the gate itself) | `includes/class-features.php`. **Every flag in this catalog is set here.** |
| 🔒 Export / import / reset plugin settings | admin | Settings → Advanced | `GET /settings/export`, `POST /settings/import` | always-on | Reset fires `wb_listora_after_reset_settings` + `wb_listora_reset_option_keys` (both Pro-consumed). |
| 🔒 Send a test email + read/export/prune the email log | admin | `listora-email-log` | `POST /settings/notifications/test`, `GET /settings/notifications/log`, `/log/export`, `POST /log/retention` | always-on | Journey `admin/12-email-log-and-test.md`, `regression/cli-test-email-cleanup.md`. |
| 🔒 Run first-run setup wizard | admin | `listora-setup` | `Admin\Setup_Wizard` | always-on | Journey `admin/04-setup-wizard-first-run.md`, `regression/setup-wizard-unknown-step.md`. |
| 🔒 Run a health check | admin | `listora-health` (hidden → Settings → Advanced) | `Admin\Health_Check` | always-on | Journey `admin/09-health-check.md`. |
| 🔒 Manage integrations | admin | `listora-integrations` | — | always-on | |
| 🔒 See Pro promotion / validate a license | admin | `listora-pro-promotion` | AJAX `wb_listora_validate_license`, `wb_listora_dismiss_promo` | always-on | Only when Pro inactive. |
| 🔒 Verify role → capability grants | admin | — | `includes/core/class-capabilities.php` | always-on | 15 custom caps. Journey `admin/11-role-cap-matrix.md`. |

## 12. Privacy / GDPR

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Export my personal data (listings, reviews, favorites, claims) | member | WP core Tools → Export Personal Data | `wp_privacy_personal_data_exporters` → `includes/privacy/class-privacy-exporter.php` | always-on | Registered `class-plugin.php:309`. **⚠ No journey covers this — see QA gap.** |
| Erase my personal data | member | WP core Tools → Erase Personal Data | `wp_privacy_personal_data_erasers` → `includes/privacy/class-privacy-eraser.php` | always-on | Registered `class-plugin.php:310`. **⚠ No journey covers this — see QA gap.** |
| 🔒 Remove all plugin data on uninstall | admin | `uninstall.php` | — | always-on | |

## 13. Infrastructure 🔒

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| 🔒 Run maintenance crons (expirations, draft reminders, analytics prune, featured expiry, unverified cleanup) | admin (system) | cron | 6 hooks — see `FEATURE_AUDIT.md` §10 | always-on | Journey `system/cron-maintenance.md`, `regression/cron-recurring-dedupe.md`, `regression/cron-scheduler-deferred-init.md`. |
| 🔒 Rebuild the search index | admin | `wp listora reindex` / `wb_listora_search_reindex` | `class-cli-commands.php:132` | always-on | Chunked 200/tick. |
| 🔒 Run CLI ops (stats, reindex, test-email, cleanup, listing-types, import, export, db-repair, migrate, demo) | admin | WP-CLI `wp listora <cmd>` | `includes/class-cli-commands.php:854` | always-on | 10 subcommands. |
| 🔒 Emit SEO metadata (Schema.org JSON-LD, Open Graph / Twitter cards, sitemap) | (system) | `wp_head`, sitemap | `class-schema-generator.php` | `schema`, `opengraph`, `sitemap` | Member-visible *output*, admin-configured. Yoast/RankMath dedupe guard. Journeys `regression/seo-meta-output.md`, `regression/schema-duplicate-canonical.md`, `regression/schema-yoast-rankmath-guard.md`, `regression/schema-rest-toggle-gate.md`, `regression/og-locale-native-output.md`. |
| 🔒 Import demo content | admin | `wp listora demo` | `class-cli-commands.php:682` | always-on | Journey `admin/demo-import-background-progress.md`. |
| 🔒 Override templates from a theme | admin (dev) | `{theme}/wb-listora/…` | `wb_listora_locate_template` | always-on | Journey `regression/dashboard-favorites-template-override.md`. |
