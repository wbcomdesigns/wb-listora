# WB Listora (Free) - Capability Catalog

**Generated:** 2026-09-30, corrected 2026-10-11 · **Version:** 1.9.0 (1.9.1 working branch) · **Branch:** 1.9.1

This is the plugin-owned master list of functionality, per **rule 7 of the `wbcom-mobile-app` skill**. It is the
**spine** a mobile app's coverage matrix maps against: every row here is either claimed by an app module, explicitly
deferred, or explicitly declared out of scope (admin). Nothing member-facing may exist outside this file.

**A capability is a thing a USER can DO.** Classes, controllers, and services are not capabilities - they are the
`REST / entry point` column.

## Method / trust

| Source | Use |
|---|---|
| Code (`includes/`, `blocks/`, `templates/`, `src/`) | **Ground truth.** Every row was checked against source on 2026-09-30. File references omit line numbers on purpose; they drift every release. |
| **Live REST** (`GET /wp-json/listora/v1`) | 130 live route paths on listora.local (Free 1.8.0 + Pro 1.8.0 active, one shared namespace, with some Pro toggles off). **72 of them are Free-owned**, each traced to a `register_rest_route` call in this repo. |
| `audit/manifest.json` | Reconciled against source on 2026-09-30 (hooks, REST, tables, cron, CLI, caps, admin screens all match). Used for blocks, tables, cron, CLI, caps and hooks. See "Manifest vs code" at the end. Where the two disagree, this file follows the code. |
| `CHANGELOG.md` + `readme.txt` | Everything shipped in 1.3.0 through 1.9.0. `readme.txt` carries the fuller 1.8.0 entry. |
| `docs/qa/journeys/` (221) | 20 customer / 18 admin / 180 regression / 3 system. Customer + admin journeys ARE capabilities, expressed as flows. Cited in Notes. |

**REST attribution.** A route is attributed to Free only if its `register_rest_route` call exists in this repo. The
live namespace is Free+Pro combined; routes present live but absent from this repo (`/analytics/*`, `/audit-log/*`,
`/badges/*`, `/compare*`, `/coupons*`, `/credits*`, `/credit-packs`, `/plans`, `/migration/*`, `/moderators/*`,
`/needs*`, `/dashboard/needs`, `/services/{compare,search}`, `/listings/{id}/contact`, `/listings/{id}/activate-plan`,
`/listings/{listing_id}/badges*`, `/webhooks/payment`, and every `/import/*` beyond `csv|json|geojson|progress|queue/csv`)
are **Pro** and are excluded from this catalog.

**Feature flags** are the Free toggles in `includes/class-features.php` -> `wb_listora_features_registry()`, persisted
to the `wb_listora_features` option and rendered on Settings -> Features. Free registers exactly **12**:
`submission`, `reviews`, `claims`, `favorites`, `contact_form`, `renewal`, `owner_name`, `report_listings` (category
`core`) · `schema`, `opengraph`, `breadcrumbs`, `sitemap` (category `seo`). All default **on**.
Rows marked `always-on` have no toggle. Rows naming a `wb_listora_settings` key or a listing-type property are gated
by that **setting**, not a feature flag - noted as such.

**Actors:** `guest` (logged out) · `member` (logged in) · `owner` (listing owner) · `admin` (site staff).
**Rows marked `[admin]` are out of scope for mobile** and are grouped coarsely - but completely enough that nothing
member-facing hides inside them.

**Maturity.** Every row describes behaviour that works in 1.9.0. Where a capability is REST-only (no web UI) or
depends on another plugin, the Notes column says so.

---

## 1. Discovery / Search

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Browse a paginated listing grid with filters/facets | guest, member | block `listora/listing-grid` | `GET /listings` | always-on | `blocks/listing-grid/render.php`. Envelope `{listings,total,pages,has_more,cursor,next_cursor}` + `X-WP-Total`. Two grids on one page page and count independently. Grid can be pinned to a listing type picked from a list; an empty pinned type says which type is empty. Journeys `integrations-api/listing-rest-contract.md`, `browse-blocks/load-more-counters-and-two-grids.md`, `directory-search/directory-empty-states.md`. |
| Search listings with faceted / geo / fulltext / tag filters | guest, member | block `listora/listing-search` | `GET /search` | always-on | `includes/rest/class-search-controller.php`. Public by design. Tags filter search and come back as a `tag` facet. Changing the listing type narrows the Features checkboxes to that type's allowlist. Enter in the keyword box filters the results. Journeys `directory-search/search-with-filters.md`, `directory-search/search-keyboard-and-suggestions.md`, `directory-search/search-with-filters.md`. |
| Get autocomplete suggestions while typing | guest, member | block `listora/listing-search` | `GET /search/suggest` | always-on | Journey `directory-search/search-keyboard-and-suggestions.md`, `directory-search/search-keyboard-and-suggestions.md`. |
| View listings on a map (Leaflet default) | guest, member | block `listora/listing-map` | `GET /settings/maps`, `GET /search/map-clusters` | always-on | Tile source comes from Settings -> Maps (`tile_url`, `tile_attribution`); a fresh install ships no default tile server and the block says so. Server-side clusters via `/search/map-clusters`. Clustering per block: Use site setting / On / Off. Provider via `wb_listora_map_provider` (Pro swaps to Google, and then Google draws alone). Journeys `map/map-provider-single-engine-and-defaults.md`, `map/map-clustering-site-default.md`, `map/map-tiles-survive-upgrade.md`, `map/map-provider-single-engine-and-defaults.md`. |
| Re-search within current map bounds ("search this area") | guest, member | block `listora/listing-map` | `GET /search` (bbox args) | always-on | Journey `map/map-renders-and-agrees-with-grid.md`, `map/map-renders-and-agrees-with-grid.md`. |
| Browse categories as a grid with counts | guest, member | block `listora/listing-categories` | taxonomy `listora_listing_cat` | always-on | The setup wizard now offers a page for this block. Journey `browse-blocks/categories-block.md`, `settings-setup/setup-pages-created-adopted-once.md`. |
| Browse featured listings carousel | guest, member | block `listora/listing-featured` | `GET /listings` (featured args) | always-on | The setup wizard offers a page for this block. Journey `directory-search/featured-listings.md`, `directory-search/featured-listings.md`. |
| Browse an event calendar (recurring + virtual occurrences) | guest, member | block `listora/listing-calendar` | `wb_listora_calendar_events` filter | always-on | The setup wizard offers a page for this block. Journey `browse-blocks/calendar-block.md`. |
| See related listings on a detail page | guest, member | block `listora/listing-detail` | `GET /listings/{id}/related` | always-on | Public by design. `wb_listora_before_related_listings` / `_after_related_listings` wrap the render. Journey `integrations-api/listing-rest-contract.md`. |
| Batch-fetch listings by ID | guest, member | REST (app/client) | `POST /listings/bulk` | always-on | Public read-only. Journey `integrations-api/listing-rest-contract.md`. |
| Bootstrap frontend/app config (maps, currency, auth doors, terms URL, toggles) | guest, member | REST | `GET /settings/app-config` | always-on | `class-settings-controller.php`. Public by design. **Mobile entry point.** Carries `currency_symbol` / `currency_position` / `decimals`, the `auth` block listing which sign-in doors the site offers, and the terms URL. `contract_version` versions the shape only. Free reports `app_enabled: false`; Pro supplies the real value. Journeys `integrations-api/app-config-contract-shape.md`, `integrations-api/app-config-contract-shape.md`. |
| Browse the public listing-type catalog + its fields/categories | guest, member | block, REST | `GET /listing-types`, `/listing-types/{slug}`, `/{slug}/fields`, `/{slug}/categories` | always-on | Public by design. Responses carry both `is_builtin` and the deprecated `is_default`. Journey `submission/submission-field-values-round-trip.md`. |
| Follow breadcrumb navigation on a listing page | guest, member | block `listora/listing-detail` | `blocks/listing-detail/render.php` + BreadcrumbList in `includes/schema/class-schema-generator.php` | `breadcrumbs` | Journey `seo-schema/breadcrumb-trail-matches-json-ld.md`. |

## 2. Listings

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| View a single listing (gallery carousel, sidebar, tabs) | guest, member | block `listora/listing-detail` | `GET /listings/{id}` | always-on | `blocks/listing-detail/render.php`. Public by design. Photos render as a carousel with arrows, dots and a thumbnail strip. Tags render as chips linking to a filtered directory. |
| Fetch an enriched single listing (mobile/app shape) | guest, member | REST | `GET /listings/{id}/detail` | always-on | **Primary mobile detail route.** Includes `social_links`, RFC-3339 `created_at`/`updated_at`, one `featured_image` shape shared with `/search` and `/related`, decoded strings, and `owner: {name, url}` when Show Who Listed It is on. Journeys `integrations-api/listing-rest-contract.md`, `directory-search/search-rest-listing-row-fields.md`, `integrations-api/listing-rest-contract.md`. |
| Watch a listing's video | guest, member | detail tabs | `wp_oembed_get()` in `templates/blocks/listing-detail/tabs.php` | always-on | Any oEmbed provider WordPress supports. Video URL is a field on every listing type since 1.8.0. |
| See who listed the business | guest, member | detail header | `wb_listora_get_listing_owner_name()` in `includes/helpers.php` | `owner_name` | Uses the listing's Contact Name, else the account display name; never a login or email. Filters `wb_listora_listing_owner_name` / `_owner_url`. Journey `listing-detail/listing-owner-name.md`. |
| View a listing's social links ("Follow" card) | guest, member | template `listing-detail/sidebar.php` | `_listora_social_links` meta via `/listings/{id}/detail` | always-on | Platforms from `Field::social_link_platforms()`, filterable via `wb_listora_social_platforms`. |
| View a listing's business hours / open-now state, including split shifts | guest, member | template `listing-detail/sidebar.php` | `wp_listora_hours` table | always-on | Up to three ranges per day. `wb_listora_normalize_hours()` is the single reader. Journeys `submission/business-hours-multiple-ranges.md`, `submission/business-hours-multiple-ranges.md`. |
| Share a listing (share modal) | guest, member | block `listora/listing-detail` | IAPI `activeModal='share'` | always-on | Touched by `browse-blocks/browse-and-favourite-a-listing.md`. |
| Contact a listing via its contact form | guest, member | template + REST | `POST /listings/{id}/contact-form` | `contact_form` | `includes/class-contact-form.php`. Anonymous-allowed: nonce + honeypot + `Anti_Spam` + per-IP-per-listing 3/hr + per-listing 20/day caps. Blocked members cannot use it. Each enquiry is counted as a lead whether or not Pro Analytics is on. When Pro `lead_form` is on, Pro renders the one form on the same submit path. Journeys `contact-leads/listing-contact-form.md`, `contact-leads/lead-counted-without-analytics.md`, `contact-leads/blocked-member-cannot-contact-owner.md`. |
| Report an inaccurate / spam / closed listing | guest, member | block `listora/listing-detail` | `POST /listings/{id}/report` | `report_listings` | Logged-out visitors get a log-in prompt worded for reporting. Administrators and moderators are emailed (throttled per listing; setting Notifications -> Listing reported); the owner is not told. Journeys `listing-detail/flag-a-listing.md`, `listing-detail/flag-a-listing.md`. |
| Deactivate my own listing | owner | block `listora/user-dashboard` | `POST /listings/{id}/deactivate` | always-on | Confirms via the `listoraConfirm` promise-modal. Journey `dashboard-account/dashboard-listings-tab-manage.md`. |
| Reactivate my own listing | owner | block `listora/user-dashboard` | `POST /listings/{id}/reactivate` | always-on | Fires `wb_listora_after_reactivate_listing`. Journey `dashboard-account/dashboard-listings-tab-manage.md`. |
| Preview a renewal quote before renewing | owner | dashboard modal | `GET /listings/{id}/renewal-quote` | `renewal` | Journey `integrations-api/listing-rest-contract.md`. |
| Renew an expired listing | owner | dashboard modal | `POST /listings/{id}/renew` | `renewal` | `wb_listora_renew_listing()` runs the same renewal from code; `wb_listora_should_expire_listing` lets an extension keep a listing alive (Pro uses it for plans that renew from credits). Journey `plans-expiry-renewal/listing-renewal.md`, `plans-expiry-renewal/listing-renewal.md`. |
| Delete my own listing | owner | dashboard | `DELETE /listings/{id}` | always-on | Owners can delete again since 1.4.0. Permanent delete cascades to reviews, votes, favorites, claims, services, analytics and the listing's own images. Journey `admin-listings/listing-delete-cascade.md`. |
| Feature a listing (upgrade) | owner | dashboard | `POST /listings/{id}/feature` | always-on | Free wires the service. When `featured_credit_cost` is above 0 the upgrade spends that many credits (SDK hold through `wb_listora_before_feature_listing`) and lasts until `_listora_featured_until`, when the expiry cron unfeatures it. There is no rotation. Also admin-side via `Admin\Featured_Metabox`. |
| See a listing's view and lead counts | owner | dashboard | `wp_listora_analytics` table | always-on | `includes/features/class-analytics-lite.php`; Pro Analytics supersedes it when on. Journey `analytics-audit/owner-sees-view-count.md`. |

## 3. Reviews

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Read reviews on a listing, with per-criterion stars | guest, member | block `listora/listing-reviews` | `GET /listings/{listing_id}/reviews` | `reviews` | Public by design. Each review shows the stars given per criterion (criteria come from the listing type). Reviews by members the viewer blocked are hidden, and the headline count agrees. Reviews from deleted accounts read "Former member". Journeys `reviews/reviews-and-moderation-switches-take-effect.md`, `reviews/blocking-a-reviewer-hides-review-not-total.md`. |
| Write a star-rated review | member | block `listora/listing-reviews` | `POST /listings/{listing_id}/reviews` | `reviews` | Criteria via listing type + `wb_listora_review_criteria` (Pro adds preset sets). A pending review keeps its confirmation instead of reloading it away. Journey `reviews/write-and-reply-to-a-review.md`, `reviews/review-form-settings-take-effect.md`. |
| Edit / delete my own review | member | block, dashboard | `PUT|DELETE /reviews/{id}` | `reviews` | |
| Vote a review "helpful" | member | block `listora/listing-reviews` | `POST /reviews/{id}/helpful` | `reviews` | Dedup via `wp_listora_review_votes`. |
| Reply to a review on my listing | owner | block + dashboard `tab-reviews.php` | `POST /reviews/{id}/reply` | `reviews` | Approved reviews only; a pending or rejected review answers 403 `listora_review_not_approved`. Journey `reviews/write-and-reply-to-a-review.md`, `reviews/moderate-review.md`. |
| Report an abusive review | member | review report modal | `POST /reviews/{id}/report` | `reviews` | Journey `reviews/flag-a-review-dialog-and-reasons.md`, `reviews/flag-a-review-dialog-and-reasons.md`. |
| Block a member from their review card | member | review card | `POST /me/blocks` | always-on | Hides that member's reviews for the viewer and stops them using the viewer's contact forms. Unblock from the dashboard Profile tab (section 8). |

## 4. Favorites

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Save a listing to favorites | member | card + detail heart | `POST /favorites` | `favorites` | Journey `browse-blocks/browse-and-favourite-a-listing.md`, `browse-blocks/favorite-controls-on-card-and-detail.md`. |
| List my favorites | member | dashboard `tab-favorites.php` | `GET /favorites` | `favorites` | Journey `browse-blocks/browse-and-favourite-a-listing.md`. |
| Remove a listing from favorites | member | card + dashboard | `DELETE /favorites/{listing_id}` | `favorites` | |
| Be prompted to log in when favouriting as a guest | guest | login modal | IAPI `activeModal='login'` | `favorites` | Register CTA suppressible via `wb_listora_login_modal_register_url`. Journey `listing-detail/anon-login-modal-register-cta.md`. |

## 5. Claims

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Claim ownership of an unverified listing (with proof) | member | detail claim modal | `POST /claims` | `claims` | Proof text + files. Proof files are stored under an unguessable name and never linked back to the claimant. Logged-out visitors see the Claim button and are prompted to log in. Journeys `claims-verification/claim-a-business.md`, `claims-verification/claim-a-business.md`, `claims-verification/claim-a-business.md`. |
| Track the status of my claims | member | dashboard `tab-claims.php` | `GET /dashboard/claims` | `claims` | Journey `claims-verification/dashboard-claims-tab.md`, `dashboard-account/dashboard-tabs-paginate-every-row.md`. |
| [admin] List all claims / update one | admin | Listora -> Claims | `GET /claims`, `PUT /claims/{id}` | `claims` | Both are admin-only; members track their own claims through `/dashboard/claims`. `PUT` is the approve/reject path (section 11). |

## 6. Services

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Browse a listing's services (price, duration, photo) | guest, member | detail services tab | `GET /listings/{listing_id}/services` | listing-type setting `services_enabled` | Public by design, but inherits the parent listing's visibility, and services the owner switched off are not readable. Prices follow the site currency. Journey `services/service-details-toggle.md`, `services/services-per-listing-type.md`. |
| Add / edit / delete a service on my listing | owner | dashboard listings tab, services modal | `POST /listings/{listing_id}/services`, `PUT|DELETE /services/{id}` | listing-type setting `services_enabled` | Pick one or more service categories, add several service rows at once, and edit with the stored categories preloaded. Journeys `services/listing-services-crud-and-photos.md`, `services/dashboard-services-modal.md`. |
| Upload a photo / gallery for a service | owner | dashboard services modal | `POST /listings/{listing_id}/services` | listing-type setting `services_enabled` | Only media the member uploaded can be attached. Journey `services/listing-services-crud-and-photos.md`. |
| Reorder my services | owner | dashboard services modal | `POST /listings/{listing_id}/services/reorder` | listing-type setting `services_enabled` | |

Services can be switched off per listing type (Type Editor); existing services are hidden, not deleted. Filter `wb_listora_services_enabled`.

## 7. Submission

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Submit a listing via the multi-step wizard or single-page form | member | block `listora/listing-submission` | `POST /submit` | `submission` | Account required (cap `submit_listora_listing`, incl. subscriber); guest submission was removed in 1.3.0. Form Layout control on the block. Turning the flag off hides every submit invitation. Journeys `submission/submit-a-listing-wizard-end-to-end.md`, `submission/submission-rest-feature-gate.md`, `submission/submission-form-style-setting.md`. |
| Accept the Terms of Service | member | wizard final step | `agree_terms` on `POST /submit` | `submission` | Enforced server-side; terms page mapped once in Settings. Opt out with `wb_listora_require_terms_acceptance`. Journey `submission/submission-terms-acceptance-enforced.md`. |
| Autosave / save a draft while filling the form | member | wizard | `POST /submit`, `PUT /submit/{id}` | `submission` | Every autosave updates the same draft; drafts do not need the terms box. A page refresh resumes the wizard where the member left off. |
| Be warned my listing looks like a duplicate | member | wizard | `POST /submit/check-duplicate` | `submission` | |
| Edit my listing (re-enter the form) | owner | block `listora/listing-submission` | `PUT /submit/{id}` | always-on for existing listings | Editing works even with new submissions switched off. Journey `submission/edit-my-listing.md`. |
| Pick a location by address lookup or by dragging a map pin | member | wizard map step | Geocode -> `wp_listora_geo`; `window.wbListoraGeocoder` | `submission` | Type an address and press Enter to choose from matches; fills town, region, country, postcode and places the pin. Map picker uses the configured tile source. Journey `submission/submission-map-picker.md`, `submission/submission-map-picker.md`. |
| Fill conditional / custom fields per listing type | member | wizard details step | `GET /listing-types/{slug}/fields` | `submission` | `includes/submission-field-renderer.php`. |
| Choose features / amenities allowed for the type | member | wizard details step | `features` on `POST /submit` | `submission` | Only the type's allowlist is offered; a disallowed feature is refused with a named error (`wb_listora_refuse_disallowed_features` restores drop-and-accept). |
| Enter social links per platform | member | wizard details step | `Field::social_link_platforms()` | `submission` | |
| Upload a featured image (pick or drag-drop) and gallery | member | wizard media step | `POST /wp/v2/media` then `POST /submit` | `submission` | Photos attach to the listing in the Media Library; works on WooCommerce sites. Only the uploader's media can be attached. Journeys `submission/submission-featured-image-drag-drop.md`, `submission/submit-a-listing-wizard-end-to-end.md`. |
| Add a video URL | member | wizard media step | `POST /submit` | `submission` | Video URL is a field on every listing type. |
| Pick a plan / pay with credits when the site charges | member | wizard plan step | `wb_listora_submission_plan_step` | `submission` | Plans and packs are Pro. Free supplies the step, the "Buy credits" path that saves the draft first, and refuses publishing a plan-less listing when the site charges credits. Journey `submission/submission-save-draft-and-resume.md`. |
| Be blocked when my submission is spam | member | wizard | `WBListora\Anti_Spam` | `submission` | Akismet + URL-density + blacklist; fails open on Akismet outage. A bare web address is refused as a title. Journey `access-security/spam-protection-layers.md`, `submission/title-is-not-a-url.md`. |
| Verify email for a pre-1.3.0 guest submission | guest | emailed link | `GET /submission/verify`, `POST /submission/resend-verification` | `submission` | Legacy only. No new guest submissions can be created, but both routes still answer for listings left in `pending_verification`, and the unverified cleanup cron still prunes them. |

## 8. User Dashboard

Block `listora/user-dashboard`, tabs defined by `wb_listora_get_dashboard_tab_labels()`:
`overview, listings, reviews, favorites, claims, credits, profile` (+ Pro `needs`, `analytics`). Each tab sets the
page title. A dashboard page can be scoped to one listing type (block attribute `listingType`).

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| See my overview stats (listings, views, reviews, favorites) | member | dashboard overview tab | `GET /dashboard/stats` | always-on | `wb_listora_rest_prepare_dashboard_stats` runs on cached responses too. Journey `dashboard-account/dashboard-overview-tab.md`, `dashboard-account/dashboard-overview-tab.md`. |
| Manage my listings (filter by status and type, act per row) | owner | dashboard listings tab | `GET /dashboard/listings` | always-on | Accepts `listing_type`. Statuses shown are shared by web and app (`wb_listora_member_listing_statuses`), including listings paused awaiting credits. Journeys `dashboard-account/dashboard-listings-tab-manage.md`, `dashboard-account/dashboard-listing-type.md`, `dashboard-account/dashboard-my-listings-statuses-counts-filters.md`. |
| See reviews I wrote / received | member, owner | dashboard reviews tab | `GET /dashboard/reviews` | `reviews` | |
| Edit my public profile and email preferences | member | dashboard profile tab | `GET|PUT /dashboard/profile` | always-on | Changing the account email needs the current password and a confirmation from the new address. Journey `dashboard-account/dashboard-profile-tab.md`. |
| See and unblock members I blocked | member | dashboard profile tab | `GET /me/blocks`, `DELETE /me/blocks/{user_id}` | always-on | Journey `dashboard-account/dashboard-profile-tab.md`. |
| Read my notifications + unread count | member | REST only (app) | `GET /dashboard/notifications` | always-on | No web tab renders this feed today; the web dashboard exposes email preferences on the Profile tab instead. |
| Mark notifications read | member | REST only (app) | `PUT /dashboard/notifications/read` | always-on | |
| See my credit balance | member | dashboard credits tab | `libs/wbcom-credits-sdk` | always-on when a purchase path exists | Shown only when the site has a configured purchase path (`wb_listora_should_show_member_credits()`). Balance is in credits, not ledger units. Gateway and ledger-reason labels are translatable and filterable (`wb_listora_credit_gateway_labels`, `wb_listora_credit_reason_label`). Selling packs is Pro. |
| Use the dashboard on mobile (2-col -> stacked) | member | dashboard | CSS | always-on | Journey `dashboard-account/dashboard-layout-and-navigation.md`, `theme-presentation/sm-button-tap-target.md`. |

## 9. Notifications / Email

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Receive lifecycle emails (submitted, approved, rejected, expiring, expired, renewed, claim, review, reply, helpful, reminders, reported) | member, owner, admin | email | `WBListora\Workflow\Notifications` | always-on | 17 templates in `templates/emails/`, editable under Settings -> Notifications. Canonical listener on `wb_listora_listing_status_changed`. Rejection emails carry the moderator's reason; the owner is told about a review only once it is approved (filter `wb_listora_notify_owner_on_pending_review`). Admins can preview any email with sample values. Journey `emails-alerts/listing-status-emails.md`. |
| One-click unsubscribe from a notification type | member | emailed link | `GET /unsubscribe` | always-on | HMAC token over uid+event IS the credential; renders a standalone confirmation page. No journey covers this. |
| Receive expiry / draft / review reminders | owner, member | email (cron) | `wb_listora_check_expirations`, `wb_listora_draft_reminder_cron`, `wb_listora_review_reminder_cron` | always-on | Journey `platform-lifecycle/cron-schedule-and-maintenance.md`. |

## 10. Import / Export

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| [admin] Export listings to CSV | admin | Settings -> Import / Export | `GET /export/csv` | always-on | Journey `import-migration/import-export-roundtrip.md`. |
| [admin] Import listings from CSV / JSON / GeoJSON | admin | Settings -> Import / Export | `POST /import/csv`, `/import/json`, `/import/geojson` | always-on | |
| [admin] Queue a large CSV import in the background + watch progress | admin | Settings -> Import / Export | `POST /import/queue/csv`, `GET /import/progress/{run_id}` | always-on | `includes/import-export/class-background-import.php`. Journey `import-migration/background-import-lifecycle.md`, `import-migration/background-import-lifecycle.md`. |
| [admin] Migrate from Directorist / GeoDirectory / ListingPro / HivePress / Business Directory Plugin | admin | Settings -> Migration tab, WP-CLI `wp listora migrate` | AJAX `listora_run_migration` | always-on | Source business hours are mapped; unreadable values are kept in `_listora_migrated_hours_raw` and reported. The `/migration/*` REST routes are **Pro**. Journeys `admin/migrate-from-{directorist,geodirectory,listingpro}.md`, `import-migration/competitor-hours-are-mapped-on-import.md`. |

## 11. Admin / Settings (out of scope for mobile)

14 admin screens under the `listora` menu (`includes/admin/class-admin.php`, `class-pro-promotion.php`): Dashboard,
Listing Types, Categories, Locations, Features, Service Categories, Reviews, Claims, Settings, Email Log,
Integrations, Health Check (hidden), Setup Wizard, Upgrade to Pro. Caps: virtual `view_listora_dashboard`,
`manage_listora_types`, `manage_listora_settings`. Frontend uses REST exclusively; the 6 AJAX actions are admin-only.

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| [admin] Approve / reject a pending listing | admin | Listings list-table row actions | `POST /listings/bulk-moderate` | always-on | Cap `edit_others_listora_listings` + per-ID `edit_post`; up to 100 IDs/call. **Emits the member-facing approval/rejection email.** Journey `admin-listings/listing-approve-reject-and-bulk-actions.md`. |
| [admin] Moderate a review (read in full, approve, reject, delete, reply) | admin | Listora -> Reviews | `PUT /reviews/{id}` | `reviews` | Read full review shows the whole text and per-criterion stars. Moderation updates the listing's rating and count and fires the same hooks as REST. Bulk Apply works. Journey `reviews/moderate-review.md`, `reviews/moderate-review.md`. |
| [admin] Approve / reject a claim | admin | Listora -> Claims | `PUT /claims/{id}` | `claims` | **Grants listing ownership** to the member. wp-admin and REST fire the same action. Journey `claims-verification/approve-claim.md`. |
| [admin] Add / edit a listing from wp-admin | admin | CPT `listora_listing` edit screen | WP core + metaboxes | always-on | Media box has a working gallery; upload buttons open the media library; video URL field; toggle fields render as checkboxes; map picker renders. `Field::show_in_admin` keeps a field off the editor. Journey `admin-listings/add-listing-from-wp-admin.md`, `admin-listings/listing-media-fields-and-retention.md`. |
| [admin] Bulk / Quick Edit listing type; see Reports column | admin | Listings list table | `includes/admin/class-listing-bulk-actions.php` | always-on | Reports column visible by default. Journey `admin-listings/listing-approve-reject-and-bulk-actions.md`. |
| [admin] Manage listing types, their fields, feature allowlist, review criteria and services switch | admin | `listora-listing-types` | `POST|PUT|DELETE /listing-types*` | always-on | Journey `settings-setup/listing-types-crud.md`, `services/services-per-listing-type.md`. |
| [admin] Manage taxonomies (categories, locations, features, tags, service categories) | admin | edit-tags screens | `includes/core/class-taxonomies.php` | always-on | 6 taxonomies. Service Categories got its own menu item in 1.6.0. Journey `settings-setup/taxonomy-crud.md`. |
| [admin] Configure settings | admin | `listora-settings` | `GET|PUT|DELETE /settings`, `/settings/maps`, `GET /settings/notifications/preview` | always-on | 10 tabs: General, Features, Maps, Submissions, Reviews, Credits, Notifications, Advanced, Import / Export, Migration. Extendable via `wb_listora_settings_tabs`. Listing limits per role live on Submissions. Journey `settings-setup/settings-tabs-save-validate-reset.md`. |
| [admin] Map and create plugin pages | admin | Settings -> General -> Pages | `wb_listora_ensure_page()` | always-on | Create page on any Missing row; pages are created once and never re-created; a page whose feature is off 404s and is marked Feature off; a notice on Settings offers to add unlinked pages to menus. Journeys `settings-setup/pages-are-created-once.md`, `settings-setup/page-registry-heals-stale-mapping.md`, `admin-listings/admin-shell-header-and-notices.md`. |
| [admin] Map the Terms of Service page once | admin | Settings -> General | `wb_listora_get_terms_url()` | always-on | Page picker or external URL; used by the form and the app. |
| [admin] Toggle any Free feature on/off | admin | Settings -> Features | `wb_listora_features` option | n/a (the gate itself) | `includes/class-features.php`. **Every flag in this catalog is set here.** |
| [admin] Export / import / reset plugin settings | admin | Settings -> Advanced | `GET /settings/export`, `POST /settings/import` | always-on | Reset fires `wb_listora_after_reset_settings` + `wb_listora_reset_option_keys` (both Pro-consumed). |
| [admin] Turn app password sign-in on or off | admin | Settings -> Advanced | option `wb_listora_app_password_login` | always-on | Default on. Off stops new exchanges without signing out existing app users. |
| [admin] Send a test email, edit templates, read/export/prune the email log | admin | `listora-email-log`, Settings -> Notifications | `POST /settings/notifications/test`, `GET|DELETE /settings/notifications/log`, `/log/export`, `POST /log/retention` | always-on | Journey `emails-alerts/email-log-and-test.md`. |
| [admin] Suspend / reinstate a member | admin | WordPress Users screens | `includes/admin/class-user-moderation.php` | always-on | A suspended account cannot write through Listora. Journey `access-security/member-write-gate.md`. |
| [admin] Run first-run setup wizard | admin | `listora-setup` | `Admin\Setup_Wizard` | always-on | Collects a map tile server; also offers pages for the Categories, Featured Listings and Events Calendar blocks, adopting existing ones rather than duplicating. Journey `settings-setup/setup-wizard-first-run.md`. |
| [admin] Run a health check and Site Health tests | admin | `listora-health` (hidden -> Settings -> Advanced), Tools -> Site Health | `Admin\Health_Check`, `includes/core/class-site-health.php` | always-on | Verifies the search index is usable; Site Health warns on a WooCommerce currency mismatch and on maps with no tile source. Journey `platform-lifecycle/health-check-and-reindex.md`, `platform-lifecycle/health-check-search-index-usable.md`. |
| [admin] Install companion plugins | admin | `listora-integrations` | `includes/integrations/class-companion-installer.php` | always-on | Downloads only from the Wbcom store over HTTPS. |
| [admin] See the setup checklist | admin | Listora -> Dashboard | `wb_listora_onboarding_checklist` | always-on | Pro adds the monetization path. |
| [admin] See Pro promotion / validate a license | admin | Upgrade to Pro | AJAX `wb_listora_validate_license`, `wb_listora_dismiss_promo` | always-on | Only when Pro is inactive. |
| [admin] Verify role -> capability grants | admin | - | `includes/core/class-capabilities.php` | always-on | 15 stored custom caps + 1 virtual (`view_listora_dashboard`). Journey `access-security/role-cap-matrix.md`. |

## 12. Privacy / GDPR

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Export my personal data (listings, reviews, favorites, claims) | member | WP core Tools -> Export Personal Data | `includes/privacy/class-privacy-exporter.php` | always-on | No journey covers this. |
| Erase my personal data | member | WP core Tools -> Erase Personal Data | `includes/privacy/class-privacy-eraser.php` | always-on | Anonymized reviews render "Anonymous". No journey covers this. |
| Delete my account from inside the app or site | member | REST | `DELETE /me` | always-on | Ships in Free because app-store rules require in-app account deletion. Journey `dashboard-account/delete-account.md`. |
| [admin] Remove all plugin data on uninstall | admin | `uninstall.php` | - | always-on | Includes listing images. |

## 13. Infrastructure

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| [admin] Run maintenance jobs | admin (system) | Action Scheduler / cron | 10 hooks: `wb_listora_check_expirations`, `_draft_reminder_cron`, `_review_reminder_cron`, `_daily_cleanup`, `_expire_featured`, `_cleanup_unverified_listings`, `_search_reindex`, `_prune_email_log`, `_bg_import_batch`, `_bg_import_finalize` | always-on | Daily cleanup also purges orphaned index rows. Journey `platform-lifecycle/cron-schedule-and-maintenance.md`, `platform-lifecycle/cron-recurring-dedupe.md`. |
| [admin] Rebuild the search index | admin | Settings button, `wp listora reindex` | `wb_listora_search_reindex` | always-on | The button schedules a rebuild. Journey `platform-lifecycle/health-check-and-reindex.md`. |
| [admin] Run CLI ops | admin | WP-CLI `wp listora <cmd>` | `includes/class-cli-commands.php` | always-on | 13 subcommands: stats, reindex, test-email, cleanup, audit-prices, listing-types, import, export, repair, migrate, demo, repair-locations, repair-credit-ledger. The two repair-* commands are dry-run by default. Journey `map/repair-locations-command.md`, `credits-checkout/credit-ledger-repair-idempotent.md`. |
| [admin] Publish automation triggers with versioned schemas | admin (dev) | `includes/automation/` | service `triggers`, action `wb_listora_register_triggers` | always-on | 25 Free triggers in 6 groups, each with a `*.v1.json` schema. Pro's outgoing webhooks consume it. Journey `integrations-api/webhook-subscriber-ui-matches-registry.md`. |
| [admin] Emit SEO metadata (Schema.org JSON-LD, Open Graph / Twitter cards, sitemap) | (system) | `wp_head`, sitemap | `includes/schema/class-schema-generator.php` | `schema`, `opengraph`, `sitemap` | Yoast/RankMath dedupe guard. Structured data uses the site currency. Journeys `seo-schema/listing-seo-head-output.md`, `seo-schema/listing-seo-head-output.md`. |
| [admin] Import demo content | admin | Setup wizard, `wp listora demo` | AJAX `listora_run_demo_import`, `listora_delete_demo` | always-on | Journey `import-migration/background-import-lifecycle.md`. |
| [admin] Override templates from a theme | admin (dev) | `{theme}/wb-listora/...` | `wb_listora_locate_template` | always-on | Templates can read `$view_data['key']` or the extracted variable. Journey `browse-blocks/browse-and-favourite-a-listing.md`. |

## 14. Account / App access

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Sign in to the mobile app with my site password | member | app | `POST /auth/app-password` | setting `wb_listora_app_password_login` (default on) | Trades the password for a core Application Password. Identical answer for wrong password and unknown user; throttled per address and per account; a 2FA site answers 409 and the app falls back to browser approval; the password is never stored or logged. Reconnecting prunes older credentials for the same `app_id`. Journey `integrations-api/app-password-sign-in.md`. |
| Deactivate / reactivate my account | member | REST (app) | `POST /me/deactivate`, `POST /me/reactivate` | always-on | A repeated deactivate answers normally. Journey `dashboard-account/deactivate-and-reactivate-account.md`. |
| Delete my account | member | REST (app) | `DELETE /me` | always-on | See section 12. |
| Block / unblock a member | member | review card, dashboard profile | `GET|POST /me/blocks`, `DELETE /me/blocks/{user_id}` | always-on | See sections 3 and 8. |

## 15. BuddyNext space showcase

REST-only partner API; the UI lives in BuddyNext Pro. Authorization is answered by BuddyNext through
`wb_listora_user_can_moderate_space` and `wb_listora_user_can_view_space`, both default **false**, so every route
refuses on a site without BuddyNext. Links stored in the `listora_space_listings` table.

| Capability | Actor | Surface(s) | REST / entry point | Feature flag | Notes |
|---|---|---|---|---|---|
| Submit my listing to a community space | owner | BuddyNext space UI | `POST /listings/{id}/spaces` | always-on (needs BuddyNext) | The target space is validated; pending submissions per member are capped. |
| See the businesses showcased in a space | member | BuddyNext space UI | `GET /spaces/{space_id}/listings` | always-on (needs BuddyNext) | |
| Work a space's pending-listing queue | member (space team) | BuddyNext space UI | `GET /spaces/{space_id}/listings/pending` | always-on (needs BuddyNext) | Paged with `page` / `per_page`, `X-WP-Total`, `X-WP-TotalPages`. Journey `integrations-api/space-moderation-queue-is-bounded.md`. |
| Approve a listing into a space | member (space team) | BuddyNext space UI | `POST /spaces/{space_id}/listings/{id}/approve` | always-on (needs BuddyNext) | Fires `wb_listora_listing_approved_in_space`. |
| Reject, take down or withdraw a listing from a space | member (space team or owner) | BuddyNext space UI | `DELETE /spaces/{space_id}/listings/{id}` | always-on (needs BuddyNext) | `wb_listora_listing_removed_from_space` carries a context of reject, takedown or withdraw. Journey `integrations-api/space-listings-rest.md`. |

---

## Manifest vs code (1.9.0)

Reconciled on 2026-09-30 against source at the 1.9.0 branch head. Both directions were checked: hook literals with
comments stripped, the live Free REST registry, and the schema, cron, CLI, capability and admin-screen lists.

| Category | Manifest | Code | Note |
|---|---|---|---|
| REST route paths | 72 | 72 | Includes `GET /settings/notifications/preview`. |
| Tables | 13 | 13 | |
| Admin screens | 14 | 14 | |
| WP-CLI subcommands | 13 | 13 | |
| Cron / Action Scheduler hooks | 10 | 10 | Includes the two background-import jobs. |
| Fired hooks | 396 | 396 | Bundled SDK hooks other than the two `*_sdk_registry` hooks are documented by the SDKs, not listed. Variable-fired hooks (the four `*_max_per_run` ceilings, the unverified-listing cleanup, the import jobs) are listed and verified. |
| Feature flags | not tracked | 12 | `contact_form` and `owner_name` were added in 1.8.0. |

Also noted: `docs/qa/journeys/dashboard-account/dashboard-notifications-feed.md` walks a web Notifications tab that the
dashboard does not render; the notifications feed is REST-only today.
