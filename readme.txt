=== WB Listora ===
Contributors: wbcom
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The complete WordPress directory plugin - listings, faceted search, maps, frontend submission, reviews, claims, and a full REST API.

== Description ==

WB Listora is a modern, block-based WordPress directory plugin. Build any kind of listing directory - business, restaurant, hotel, real estate, jobs, events - with faceted search, interactive maps, frontend submission, reviews, claims, services, and a built-in WP-CLI + REST API.

Key features:

* Custom listing types with a flexible custom-field system and services.
* Faceted search with geo/radius, full-text, and map "search this area".
* Interactive maps (Leaflet built in; Google Maps via Pro) with clustering.
* Multi-step frontend submission wizard with media uploads (account required to submit).
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

= 1.6.0 - Unreleased =

Makes the interactions the interface already advertised actually work, gives automation and webhooks a published contract, and enforces Terms of Service acceptance on the submission API.

* New      - Listing photos display as a carousel, so every image stays reachable without scrolling back to the thumbnails.
* New      - The featured image box accepts a dragged file, which its label has always offered.
* New      - Members can set amenities when submitting or editing a listing, instead of only administrators from wp-admin.
* New      - Tags are a discovery dimension: they filter search, appear as a facet, and render as clickable chips on cards and listing pages.
* New      - A listing's video is rendered on the listing page rather than only stored.
* New      - Services can be added, edited and deleted from the member dashboard.
* New      - Automation and webhook subscribers can discover every available trigger from a published registry, with a JSON schema per event.
* New      - The submission API accepts a complete `categories` list for clients that manage the full set.
* Improve  - An icon chosen in the admin picker is always one the front end can draw, so a selected icon can no longer vanish.
* Improve  - Every endpoint returns the same `featured_image` shape, so a client no longer has to handle three variants.
* Improve  - Every human-facing string leaves the API decoded, so ampersands and apostrophes render as typed.
* Improve  - Review criteria saved against a listing type are the criteria the review form and averages use.
* Improve  - Related Listings can be extended from a child theme through dedicated hooks.
* Fix      - Editing a listing no longer deletes the categories the single-select form cannot display.
* Fix      - Saving a new listing type, and resetting settings, now confirm they worked instead of reloading silently.
* Fix      - A listing whose category sits outside its type's allowed list can be edited and saved again.
* Fix      - A search cache lifetime of 0 disables caching, rather than caching permanently.
* Fix      - Rebuild Search Index now rebuilds the index instead of reloading the page.
* Fix      - The map picker renders and accepts a dragged marker on the wp-admin listing editor.
* Fix      - The favourite heart on the dashboard sits over the card image and responds to clicks.
* Fix      - Approving or rejecting a claim from wp-admin now notifies the same listeners the API path does.
* Fix      - Category names containing an ampersand display correctly in the submission form.
* Fix      - A site missing a canonical page no longer white-screens after an update.
* Dev      - New helper `wb_listora_directory_is_operational()` reports whether an install is a working directory, wizard walked or not.
* Security - Terms of Service acceptance is enforced on `POST /submit`, which previously accepted a submission with no consent recorded. Clients that cannot send `agree_terms`, including sites that hide the checkbox, opt out with `add_filter( 'wb_listora_require_terms_acceptance', '__return_false' )`.
* Dev      - New public helpers: `wb_listora_render_icon()`, `wb_listora_get_icon_choices()`, `wb_listora_get_review_criteria()` and `wb_listora_decode_text()`.
* Dev      - New hooks: `wb_listora_before_related_listings`, `wb_listora_after_related_listings` and `wb_listora_require_terms_acceptance`.
* Dev      - `/search` accepts a `tags` argument; `/settings/app-config` publishes `contact_path`.
* Dev      - Database version moves to 1.6.0; the migration clears cache entries written without an expiry and runs on activation.

= 1.5.0 - August 2026 =

Adds split-shift business hours and member blocking, corrects a credit unit mismatch that showed the wrong balance, and closes a data-exposure gap on listing services.

* New      - A day in Business Hours can now hold up to three time ranges, so a split shift such as 08:00-12:00 and 17:00-22:00 is finally expressible.
* New      - Members can block a reviewer from the review card and unblock from their dashboard, so blocking is no longer one-way.
* New      - Bulk Edit and Quick Edit can set the listing type, so retyping a batch of listings no longer means opening each one.
* New      - Administrators can change a listing's type directly from the listing screen.
* New      - Site owners choose which platforms the Social Links field offers, in Settings, rather than taking the full built-in list.
* New      - Site currency symbol, position, and decimal precision are now published to connected apps, so prices display the way the site formats them instead of falling back to a generic currency code.
* New      - Map tile source and attribution are published to connected apps, so native maps render the same tiles as the website.
* New      - Two repair commands, wp listora repair-locations and wp listora repair-credit-ledger, report what they would change and only act when asked; the credit repair adds a correcting entry and never removes credits already granted.
* Improve  - The plugin is fully translatable for the first time: 284 previously untranslatable strings were exposed, the JavaScript layer is translated, and ten locales ship complete.
* Improve  - The setup checklist no longer asks owners to configure settings that do not exist, so it can actually reach complete.
* Improve  - Owners are warned when a listing type is open for submission but has no categories, instead of members meeting an unusable form.
* Improve  - A review left by a since-deleted account reads as Former member, and one anonymized under a privacy request reads as Anonymous, rather than showing nothing.
* Improve  - Abbreviated and zero-decimal currencies render correctly, and a new filter allows a custom symbol, suffix position, or precision.
* Improve  - Records left behind by listings deleted on earlier versions are now cleared automatically by the daily cleanup, instead of only when the cleanup command was run by hand.
* Improve  - Filtering reviews or claims by status is substantially faster on large directories, and sorting listings by Views no longer scans the whole analytics history.
* Improve  - The admin Reviews screen loads author names and listing links in one batch instead of one lookup per row.
* Fix      - Right-to-left sites no longer request a stylesheet that does not exist on every page load.
* Fix      - A listing paused for credits can now be activated by a member who already has enough, instead of being told to buy credits they do not need.
* Fix      - A listing whose plan was deleted now says so, rather than promising it will activate on its own and sending the member to an empty credits store.
* Fix      - The listing header no longer shows Verified twice, and no longer repeats the city and state that are already part of the address.
* Fix      - The Reviews screen in wp-admin now distinguishes a review left by a deleted account from one anonymized under a privacy request, matching what the listing page shows.
* Fix      - Photos attached to a review now appear on the listing page; they uploaded correctly and were never shown back.
* Fix      - The My Listings counter now includes listings awaiting credits, and every other status the list shows; it could read zero above a listing you could see.
* Fix      - Keyboard focus stays inside an open dialog instead of moving to the page behind it.
* Fix      - The map now plots the search that is actually running: filtering the directory left the map showing pins for listings the results had excluded.
* Fix      - The saved-count beside a listing's Save button updates when you save, instead of waiting for a page reload.
* Fix      - Credits are awarded and spent as credits rather than the ledger's internal units, so buying a 50-credit pack adds 50 and the transaction history reads in credits.
* Fix      - The dashboard Favorites tab listed nothing for every member while the tab counter still showed the real number.
* Fix      - Opening hours were missing from the search-engine markup of every member-submitted listing, and a split shift showed as closed on the listing page.
* Fix      - Saving a listing in wp-admin no longer erases its location.
* Fix      - A directory with a single listing type has a working Add Listing form again.
* Fix      - The plugin's own admin notices no longer hide themselves.
* Fix      - Turning the sitemap setting off now drops every Listora post type rather than only listings.
* Fix      - A review count shown above a list now matches the reviews the reader can actually see, once blocking is taken into account.
* Fix      - The plain-text part of a notification email no longer carries the previous email's listing.
* Fix      - Business hours a competitor importer cannot interpret are preserved and reported instead of being discarded while the import reports success.
* Fix      - Deleted listings no longer count toward search result totals or page counts.
* Fix      - Translations are no longer shadowed by a stale compiled catalogue, which could serve older wording while every completeness check reported the language as fully translated.
* Security - Service names, descriptions, and prices belonging to a draft, pending, or rejected listing are no longer readable by visitors; they now follow the listing's own visibility.
* Dev      - New wb_listora_search_args_from_url() builds search arguments from the URL for every surface that renders a filtered set, and a new wb_listora_map_query_args filter matches the grid's. Search accepts has_geo to page over mappable listings only.
* Dev      - New wb_listora_normalize_hours() and wb_listora_max_hours_slots() helpers: any reader of business hours must normalize through the former, and any writer must cap through the latter.
* Dev      - The credit balance REST response now states its units and adds the human-readable figure and currency alongside the existing field, which keeps its previous meaning.
* Dev      - New wb_listora_get_currency_format() and wb_listora_get_map_tiles() helpers, with wb_listora_currency_format and wb_listora_map_tiles filters, are shared by the website and the app payloads so the two cannot drift.
* Dev      - New wb_listora_social_platforms and wb_listora_credit_pack_sizes filters, and a new wb_listora_migrated_hours_unreadable action fired when an import cannot read a source's hours.
* Dev      - Database indexes added to reviews, claims, and analytics. The upgrade runs automatically on activation.
* Compat   - Aligned with WB Listora Pro 1.5.0. Install both updates together.

= 1.4.1 - August 2026 =

Hardens the plugin against corrupted or unexpected stored data and completes cleanup when a listing is permanently deleted.

* Fix      - The submission form no longer shows an error page when select, radio, or multiselect options were added through the listing type editor; stored options are normalized to a canonical shape on read and on save.
* Fix      - Permanently deleting a listing now also removes its reviews, review votes, favorites, claims, services, and analytics records. Trashing a listing keeps everything so restore is lossless.
* Fix      - Corrupted or legacy-shaped stored data (dashboard statistics cache, gallery, social links, review reports, and data supplied by extensions to the calendar, categories, and review criteria hooks) renders safely instead of producing an error page.
* Improve  - The wp listora cleanup command now removes records orphaned by listings deleted on earlier versions.
* Dev      - New wb_listora_listing_data_deleted and wb_listora_purge_orphaned_listing_data actions let extensions clean their own listing-scoped data alongside the core cleanup.
* Dev      - Field options and review criteria are normalized on save, and render-time hook returns are shape-checked before use.
* Compat   - Aligned with WB Listora Pro 1.4.1. Install both updates together.

= 1.4.0 - August 2026 =

Adds password sign-in for the mobile app, with an owner switch and the brute-force protections that route needs.

* New      - Members can sign in to the mobile app by typing the WordPress password they already have, instead of walking the browser approval screen.
* New      - Settings > Advanced carries an App sign-in switch so the site owner decides whether password sign-in is offered. Turning it off leaves the browser flow and does not sign out members who already use the app.
* New      - Signing in again from the same install replaces that install's credential instead of adding another, so a member's app-password list stops growing on every reconnect.
* Improve  - The app is told which sign-in doors this site offers before it draws the screen, so it never presents a path the site will refuse.
* Improve  - Sign-in failures answer identically whether the username or the password was wrong, so the endpoint cannot be used to discover which accounts exist.
* Improve  - Repeated failed sign-ins are throttled per address and per account, and only wrong passwords count toward the limit.
* Improve  - Sites running two-factor authentication are never bypassed; the app is handed back to the browser flow so the second factor can complete.
* Fix      - Members can delete their own listings again. The permission check refused the owner and allowed only administrators.
* Fix      - Custom badges now appear on directory cards and in Quick View, and a featured listing no longer shows the Featured label twice.
* Fix      - The single-form submission layout no longer hides its own steps.
* Fix      - Status colours now meet contrast requirements in dark mode.
* Fix      - The owner contact form and the Pro lead form share one submit path, so a listing shows one working form rather than two competing ones.
* Dev      - The server advertises its own contact and lead-form routes; clients no longer hardcode them.
* Dev      - Card view data now carries through anything added by the wb_listora_card_view_data filter, so an extension's additions reach the card templates.
* Dev      - New filters: wb_listora_app_scheme, wb_listora_app_connect_schemes, wb_listora_app_connect_bridge and wb_listora_app_password_login_enabled, plus the wb_listora_app_credential_issued action.
* Compat   - Ships in lockstep with WB Listora Pro 1.4.0. Install both updates together.

= 1.3.0 - July 2026 =

Includes a product-wide money-flow and data-integrity audit: every credit charge, refund, and credit-facing surface was verified end to end.

* Change   - Submitting a listing now requires an account. Anonymous guest submission (and its email-verification step) has been removed: guests could never upload media, so the flow always dead-ended. The former block "Require Login" control was removed to match, and the inert enable_guest_submission flag no longer appears in the app-config REST response.
* Fix      - The listing submission form and dashboard no longer show credit pricing or a Buy Credits link on installs that have no configured way to buy credits.
* Fix      - Per-listing credit fees are now charged at the correct amount; a money-unit mismatch could charge a fraction of the configured fee.
* Fix      - A custom Buy Credits URL set by the site owner is now honored on every credit call-to-action, including paused-listing emails.
* Fix      - Listings awaiting email verification now carry a proper status label and valid status transitions instead of appearing unlabeled.
* Fix      - Updating settings through the REST API now uses the same validation as the admin screen, so a headless client can no longer corrupt list-limit or notification settings.
* Fix      - Activation reliably records the Directory page in settings, and a redundant page-creation routine was removed.
* Dev      - The shared payments table definition is kept byte-identical across Free and Pro so a version upgrade never rewrites it.
* Dev      - Custom capabilities and their uninstall cleanup derive from a single list; uninstall removes them without stripping WordPress core capabilities.
* Dev      - Added internal-audit guardrails (rating source, Free-Pro option boundary, payments schema, credit-surface gating) to the local-CI gate.
* Compat   - Ships in lockstep with WB Listora Pro 1.3.0. Install both updates together.

= 1.2.2 - June 2026 =

Packaging fix for the 1.2.1 release, which shipped incomplete bundled SDKs.

* Fix      - The bundled Credits SDK and license/update SDK sources were stripped from the 1.2.1 package, disabling credit-based features and auto-updates on fresh installs. Both now ship complete.
* Fix      - Distribution excludes are now anchored to the plugin root so bundled libraries under libs/ are never stripped, and the release build fails fast if any SDK source is missing from the zip.
* Improve  - A missing or incomplete bundled library now shows a soft admin notice and disables only the dependent features, instead of risking a fatal error.

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
