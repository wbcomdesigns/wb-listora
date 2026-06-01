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

Bug-fix and reliability release.

* Improve  - Demo import is faster and more resilient: per-image timeout reduced from 30s to 10s, gallery images capped, repeated image URLs downloaded once, and a failed image is skipped instead of stalling.
* Improve  - CSV import and export now support the Location taxonomy and offer a per-column field-mapping UI, so listings round-trip without data loss.
* Improve  - The Write a Review form is fully styled and responsive (criteria stack on mobile, full-width submit, clearer spacing and star states).
* Improve  - Submission preview and the location map are hardened so a single failing section cannot blank the preview and the map recalculates as soon as it is visible.
* Fix      - Admin script no longer 404s during demo import; it now loads from the packaged build path.
* Fix      - Documentation buttons now open the correct docs host instead of an unreachable domain.
* Fix      - Search field clear and near-me icons no longer touch the field edges.
* Fix      - Business hours now appear in the listing submission preview.
* Fix      - Selecting a listing-type tab no longer increments the filter count, and the active type tab is now highlighted.
* Fix      - Listing dashboard service and more-actions controls now open as expected.
* Fix      - Map no longer errors when marker clustering is disabled.
* Fix      - The Listings per page setting is now respected by the grid on first load.
* Fix      - The Required Skills field on Job listings is now usable instead of showing only a label.
* Fix      - The Business Hours builder is aligned and no longer congested.
* Fix      - Settings REST endpoints return structured listora_unauthorized (401) / listora_forbidden (403) codes on denial.
* Dev      - CSV importer and migrator base consolidated onto the shared Term_Helper for consistent term handling.
* Dev      - Wbcom Credits SDK loads defensively; a build missing the SDK source degrades gracefully instead of causing a fatal.

Full historical changelog is maintained in CHANGELOG.md.

== Upgrade Notice ==

= 1.1.0 =
Recommended bug-fix and reliability release. No database or breaking changes.
