=== WB Listora ===
Contributors: wbcom
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The complete WordPress directory plugin - listings, faceted search, maps, frontend submission, reviews, claims, and a full REST API.

== Description ==

WB Listora is a modern, block-based WordPress directory plugin. Build any kind of listing directory - business, restaurant, hotel, real estate, jobs, events - with faceted search, interactive maps, frontend submission, reviews, claims, services, and a built-in WP-CLI + REST API.

Key features:

* Custom listing types with a flexible custom-field system and services.
* Faceted search with geo/radius, full-text, and map "search this area".
* Interactive maps (Leaflet built in; Google Maps via Pro) with clustering.
* Multi-step frontend submission wizard with guest registration and media.
* Reviews and ratings with helpful votes and owner replies.
* Business claims with admin approval and ownership transfer.
* Frontend user dashboard to manage listings, reviews, claims, and profile.
* CSV / JSON / GeoJSON import and export, plus competitor migrators.
* Schema.org markup, anti-spam (reCAPTCHA v3, Turnstile, Akismet), and RTL.
* Built for scale (Action Scheduler, denormalized indexes) and headless use.

WB Listora Pro adds Google Maps, a credit-based payment economy, pricing plans, coupons, analytics, multi-criteria and photo reviews, lead forms, verification badges, white-label, and more.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/wb-listora` or install via the Plugins screen.
2. Activate the plugin through the Plugins screen.
3. Go to WordPress Admin -> Listora and run the setup wizard.

Requirements: WordPress 6.9+, PHP 7.4+.

== Changelog ==

= 1.2.1 - June 2026 =

Adds the Wbcom family Integrations page, a notification action for suite aggregators, and a wave of onboarding and QA fixes.

* New      - Integrations page showcasing the Wbcom plugin family with product logos, store links, and one-click companion install.
* New      - wb_listora_notification_created action so suite-wide tools can aggregate Listora notifications.
* Improve  - Color and elevation tokens aligned to the Wbcom Community-OS design system.
* Improve  - Demo seeder is now autoloadable everywhere, with an autoload-resolution gate to prevent activation errors.
* Fix      - Activation redirect to the setup wizard no longer gets lost, and the setup notice is scoped and persists correctly.
* Fix      - Suppressed the duplicate setup wizard so Free and Pro no longer launch two wizards.
* Fix      - Analytics, media privacy, submission preview, and carousel QA fixes.
* Fix      - Card and comparison field values truncate with a real ellipsis instead of a literal HTML entity.

= 1.2.0 - June 2026 =

Feature release: background imports, analytics-lite, email tooling, privacy compliance, and a 13-card QA fix wave.

* New      - Demo packs and large CSV imports run as resumable background jobs with a live progress widget and a per-column field-mapping UI.
* New      - Analytics-lite view tracking: per-listing view counts on the dashboard, an admin Views column, and a REST field. Defers to Pro analytics when active.
* New      - Email template editor under Settings - Notifications: override any notification subject and body per event, honored on every send path.
* New      - Bulk actions on the listings table: approve, reject, feature, unfeature, and assign category. Invalid status transitions are reported, never silently applied.
* New      - One-click unsubscribe links (RFC 8058 signed tokens) in marketing email footers, plus a review-reminder opt-out on the profile tab and admin toggles.
* New      - WordPress privacy-tools integration: personal-data exporter and eraser cover claims, reviews, and favorites.
* New      - Review-reminder notification event, and an Open now / Closed badge on listing detail computed from business hours.
* New      - HivePress migrator joins the competitor migration set.
* New      - Inline add and edit listing forms inside the dashboard, so owners never leave My Listings.
* New      - Submission form style setting: choose the step-by-step wizard or a single page form site-wide; a block whose author picked a layout in the editor keeps that choice.
* Improve  - The Media step now says "Add a featured photo to continue" instead of a generic required-field error, and validation messages are translatable and filterable per field.
* Improve  - Dashboard sidebar restyled as an elevated card panel; tab navigation gains a scroll affordance on small screens.
* Improve  - Hours builder gives live Open-24h and Closed feedback; small buttons meet the 40px tap-target floor; featured images get an alt-text fallback.
* Improve  - Database migrations run automatically after a plugin update, no manual trigger needed.
* Fix      - Background import no longer sticks at Running after an error: failed chunks retry up to 3 times from the saved cursor, then the run is marked Failed with a clear message.
* Fix      - Featured Listings block renders a proper empty state instead of silently vanishing when its listing type has no listings.
* Fix      - Manage Services on My Listings opens in a modal dialog next to the listing instead of a panel at the bottom of the page.
* Fix      - My Listings Active filter no longer shows deactivated, draft, pending, or rejected listings.
* Fix      - Favorites dashboard tab moved to an overridable template file (tab-favorites.php), matching every other tab.
* Fix      - Settings Documentation buttons deep-link to the live docs site sections instead of 404 pages.
* Fix      - Submission map picker keeps Leaflet controls below fixed theme headers while scrolling.
* Fix      - Keyword search shows a single clear icon: the native browser cancel control no longer stacks under the plugin's clear button.
* Fix      - Post-submission success message is centered as a unit on every theme width, with stacked full-width buttons on mobile.
* Fix      - Map picker now loads on the dashboard inline Add Listing form (single-form layout); it previously initialized only when stepping through the wizard.
* Fix      - Single-form submission shows the Submit button directly and hides the redundant Back/Continue navigation, with full field validation run on submit.
* Fix      - Onboarding dismissal is stored under the wb_listora_onboarding_dismissed key (auto-migrated from the legacy unprefixed key).
* Fix      - Removed a "translation loading triggered too early" notice (WordPress 6.7+) by deferring email-template filter registration to the init hook.
* Fix      - Featured Listings block no longer crashes the page (division by zero) when its columns value reaches the server as 0 via the editor preview API or saved content; columns now floor at 1.
* Fix      - Listing Grid and Categories blocks also clamp a 0 columns value, which previously collapsed the layout to zero columns.
* Fix      - Featured count and Grid per-page values of 0 no longer crash search pagination; the search engine floors page and per_page at 1.
* Dev      - New hooks: wb_listora_search_resolved, wb_listora_dashboard_credit_row_actions, wb_listora_before/after_dashboard_favorites, wb_listora_show_credits (lets Pro's new Monetization toggle hide credit surfaces), and a reusable rating-recompute entry point. Removed the dead listing-detail view.js.
* Dev      - Relocated QA artifacts from tests/qa to docs/qa and adopted the portfolio-standard pre-release smoke model.
* Dev      - New release gates against the fatal class above: PHPStan now analyzes blocks/, a block-attribute guard detector runs in coding-rules (Rule 7), and an adversarial block-attribute journey fuzzes every block via the preview REST API.
* Compat   - Aligned with WB Listora Pro 1.2.0. Install both updates together.

= 1.1.0 - June 2026 =

Bug-fix, performance, and reliability release.

* New      - Pro feature toggles now register into the Features screen, so all features are managed in one place when WB Listora Pro is active.
* New      - WP-CLI gains wp listora test-email and cleanup subcommands.
* Improve  - Demo import is faster and more resilient: per-image timeout reduced from 30s to 10s, gallery images capped, repeated image URLs downloaded once, and a failed image is skipped instead of stalling.
* Improve  - CSV import and export now support the Location taxonomy and offer a per-column field-mapping UI, so listings round-trip without data loss.
* Improve  - Listing-grid ratings are batch-prefetched, the admin Reviews and Claims tables and the dashboard Claims tab are paginated, and the calendar recurring-events query is bounded.
* Improve  - The settings option no longer autoloads on every request, and a composite index speeds per-user review pagination.
* Improve  - The Write a Review form is fully styled and responsive (criteria stack on mobile, full-width submit, clearer spacing and star states).
* Improve  - Reporting a review now uses an accessible modal instead of the native browser prompt.
* Improve  - Submission preview and the location map are hardened so a single failing section cannot blank the preview and the map recalculates as soon as it is visible.
* Fix      - A build missing a bundled SDK no longer white-screens the site; the license and credits SDKs ship composer-free and load defensively.
* Fix      - Credit purchases are idempotent: webhook replays cannot double-credit, refunds carry the real amount, and PayPal refunds link back to the original transaction.
* Fix      - Schema.org JSON-LD and canonical tags no longer duplicate when Yoast SEO or Rank Math is active, and og:locale is emitted in Open Graph output.
* Fix      - Breadcrumbs and BreadcrumbList schema now come from one canonical trail, and the listing-detail REST schema field respects the Schema.org toggle.
* Fix      - Admin script no longer 404s during demo import; it now loads from the packaged build path.
* Fix      - Documentation buttons now open the correct docs host instead of an unreachable domain.
* Fix      - Search field clear and near-me icons no longer touch the field edges.
* Fix      - The /search REST endpoint no longer returns a zero rating average for rated listings.
* Fix      - Business hours now appear in the listing submission preview, the Business Hours builder is aligned, and the submission map stays contained in its step.
* Fix      - Selecting a listing-type tab no longer increments the filter count, and the active type tab is now highlighted.
* Fix      - Listing dashboard service and more-actions controls now open as expected, and the submission success card resolves the Dashboard link reliably.
* Fix      - Map no longer errors when marker clustering is disabled.
* Fix      - The Listings per page setting is now respected by the grid on first load.
* Fix      - The Required Skills field on Job listings is now usable instead of showing only a label.
* Fix      - The review form respects the configured minimum review length, and report reasons validate against a fixed vocabulary.
* Fix      - Pagination active-page text stays visible under aggressive theme link styles, and dark-mode contrast is corrected on BuddyX.
* Fix      - Renewal modal errors are announced to screen readers, and small buttons meet the tap-target floor.
* Fix      - Settings REST endpoints return structured listora_unauthorized (401) / listora_forbidden (403) codes on denial.
* Dev      - Wbcom Credits SDK re-homed from a git submodule to a committed composer-free libs/ copy that loads defensively when absent.
* Dev      - CSV importer and migrator base consolidated onto the shared Term_Helper; claims queries consolidated into a canonical Claims_Model.
* Dev      - wb_listora_send_notification filter now receives the recipient address as its 4th argument.
* Dev      - Off-canon CSS breakpoints consolidated, and the WP-CLI table-status query is prepared.
* Compat   - Aligned with WB Listora Pro 1.1.0. Install both updates together.

= 1.0.4 - May 2026 =

* New      - Public Page Registry helper and new block render hooks for theme and Pro extensions.
* New      - All 6 scheduled jobs migrated to Action Scheduler for reliable background processing at scale.
* New      - Network timeout handling with a clear retry message on slow connections.
* Improve  - Consistent page shells, cards, badges, and empty states across all 11 blocks.
* Improve  - REST listing queries prefetch caches, saving about 100 queries per request on large directories.
* Fix      - Setup wizard no longer renders a blank page after completing on restricted accounts.
* Fix      - Approve, reject, and expiry notification emails reach listing owners again.
* Fix      - Map popups show the listing featured image, and a map fatal on activation is resolved.
* Fix      - Business Hours time picker works in Firefox.
* Fix      - Services photo upload, helpful votes, owner reply form, and filter-count badge restored.
* Dev      - Architecture invariants enforced between Free and Pro; WPCS and PHPStan baselines green.
* Compat   - Aligned with WB Listora Pro 1.0.4. Install both updates together.

= 1.0.0 - April 2026 =

Initial release.

* New      - 11 Gutenberg blocks: grid, search, map, detail, reviews, submission, categories, featured, calendar, card, and user dashboard.
* New      - Dynamic listing types with 22 custom field types.
* New      - Frontend submission wizard with guest registration and spam protection.
* New      - Review system with star ratings, helpful votes, owner replies, and reporting.
* New      - Business claims, favorites, business hours, services, and recurring events.
* New      - Import and export for JSON, GeoJSON, and CSV plus 4 competitor migration tools.
* New      - Full-text search with facets and geo-distance queries on dedicated database tables.
* New      - REST API, extensive hooks, WP-CLI commands, and 14 email notification templates.

Full historical changelog is maintained in CHANGELOG.md.

== Upgrade Notice ==

= 1.1.0 =
Recommended bug-fix, performance, and reliability release. No breaking changes. Update WB Listora Pro to 1.1.0 at the same time.
