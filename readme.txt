=== WB Listora ===
Contributors: wbcom
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.0
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

Full historical changelog is maintained in CHANGELOG.md.

== Upgrade Notice ==

= 1.1.0 =
Recommended bug-fix, performance, and reliability release. No breaking changes. Update WB Listora Pro to 1.1.0 at the same time.
