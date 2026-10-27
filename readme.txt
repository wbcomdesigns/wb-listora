=== WB Listora ===
Contributors: wbcom
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.1.0
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

Requirements: WordPress 6.9+, PHP 8.1+.

== Changelog ==

= 2.1.0 - October 2026 =

Every search filter reaches every listing, owner and moderator fixes across the dashboard, and a phone layout pass.

* New      - Owners can delete a listing from the More menu in My Dashboard; it goes to the trash, where an administrator can restore it.
* New      - The listing author, administrators and review moderators can reply to a review from the listing page's Reviews tab.
* New      - Listings > Add New opens with a listing type's fields straight away, from the link, the default type or the only type.
* New      - Settings tabs say "Unsaved changes" beside Save Changes until the tab is saved.
* Improve  - A category with no colour of its own uses its listing type's colour on category tiles, in the REST API and on the Listing Calendar, and its form says "Not set".
* Improve  - "Listed by" links to the owner's BuddyPress or BuddyBoss profile, or to their author archive only when they have published posts.
* Improve  - The address search on Map Location fields picks nothing until the member chooses a match, and leaves out businesses that share a street address when the address itself matches.
* Improve  - The listing page Save button reads Saved with a filled heart once the listing is saved.
* Improve  - On phones, dashboard tabs stay on one line, List view stays a list, listing action labels stay on one line and the sticky Call / Visit / Save bar has room below it.
* Improve  - On phones, Edit Listing lines up with the dashboard tabs and the Edit Listing services rows become labelled cards instead of a sideways-scrolling table.
* Improve  - The map's Near me and Search this area buttons stay readable over any map tile, and the no-photo placeholder follows dark themes.
* Improve  - Only one of Filters and Save This Search is open at a time, and the map has the same gap below it as the search bar.
* Improve  - A Listora block at the end of a page has room above the theme footer.
* Improve  - Credit history names the listing for a featured upgrade and labels refunds, removals and hold releases as what they are.
* Improve  - The Leads drawer shows the sender's name, email, phone, listing and time with the message.
* Improve  - Each settings tab's Documentation button opens that tab's own page in the docs.
* Improve  - Admin counts match what members see, and save notices name what was saved.
* Improve  - Every label, tooltip and notice in the Listing Types editor can be translated.
* Improve  - A listing with no description or fields says so on its Overview, with an Add a description link for anyone who can edit it.
* Improve  - The One review per listing setting is removed: the reviews table already allowed only one review per member per listing.
* Improve  - With a Featured upgrade cost of 0, owners no longer get a free Feature button on their own listing; administrators still feature listings from wp-admin.
* Improve  - A map with no tile source set shows nothing to visitors; administrators see the notice with a link to Map settings.
* Improve  - The Helpful button stays marked after a reload, and the repeated "people found this helpful" line under each review is removed.
* Improve  - The contact owner form fills in a logged-in member's name and email.
* Improve  - The renewal confirmation shows the member's credit balance only when renewing costs credits.
* Improve  - Update and licence requests go to updates.wbcomdesigns.com, and fall back to wbcomdesigns.com on their own if it cannot be reached.
* Fix      - Category, location, feature, tag, custom-field, event-date and Open now filters find and count every matching listing on directories larger than 5,000 listings.
* Fix      - Stripe and PayPal webhooks confirm purchases and take credits back on refunds again.
* Fix      - The log-in dialog opens for a signed-out visitor from a favourite heart on any page, not only on listing pages.
* Fix      - Business hours on the submission form load on the right days and keep every range of a split shift.
* Fix      - The business hours builder on the wp-admin Edit Listing screen adds times and sets Open 24 hours and Closed.
* Fix      - A listing saved in wp-admin with No type goes to draft with a notice instead of being published without a type.
* Fix      - The site owner is no longer emailed that a listing awaits review while the member is still filling in the form.
* Fix      - The setup wizard is marked complete only after its steps are submitted, offers Google Maps when Pro is active, and Re-run setup opens the first step.
* Fix      - A category image shows on its Browse Categories tile.
* Fix      - The Description field on the frontend edit form shows text instead of block markup for listings written in the block editor.
* Fix      - The submission summary shows the coupon-adjusted cost, and the listings pager shows only under the list.
* Fix      - The More menu in My Dashboard can be clicked on every row.
* Fix      - Pro's listing slots notice shows only on the Credits tab, and Report Review reads "Submitting" while it sends.
* Fix      - Health Check counts only published listings, and new auto-drafts no longer enter the search index.
* Fix      - A claim or review delete that fails to save stops there instead of transferring ownership or removing photos.
* Fix      - A database upgrade that could not create every table is retried instead of being marked done.
* Fix      - The Upgrade to Pro page stays light when the computer uses dark mode.
* Fix      - In List view on phones the Featured badge sits as a ribbon under the photo instead of over the heart and rating.
* Fix      - On phones the sticky Call / Visit / Save bar no longer covers the end of the theme footer.
* Fix      - Edit Listing on phones drops the extra card border so fields use the full width.
* Fix      - Demo content and setup wizard imports index every listing with its type, so type tabs and type searches find them straight away.
* Fix      - Sites that loaded demo content or ran the wizard import before this version have those listings re-indexed with their type in the background after updating.
* Fix      - A favourite that fails to save shows an error instead of quietly switching back.
* Fix      - A listing whose business hours are all blank no longer shows an empty hours card.
* Fix      - Email subjects show "&" in the site name or listing title instead of "&amp;".
* Fix      - Email From names show "&" in the site name instead of "&amp;".
* Fix      - The Credits balance card on phones no longer leaves an empty gap under the balance.
* Fix      - Star ratings on directory cards and the listing header are read out to screen readers.
* Fix      - The CSV export no longer has two Location columns, so re-importing it cannot put an address into the location.
* Fix      - The Services Details toggle tells screen readers whether the description is open.
* Fix      - The heart count on a directory card updates as soon as a member saves or unsaves a listing.
* Fix      - The filter badge counts a chosen category or location once instead of twice.
* Fix      - A favourite that fails to save says so in plain words instead of a technical message.
* Fix      - A listing page no longer shows an empty grey map box when the map has no tile source; the address and Get directions remain.
* Fix      - Card details, search bar and dashboard rows read right to left on RTL sites again.
* Fix      - wp listora demo remove refuses --pack, since it always removes every demo pack.
* Security - Listing exports stream to the browser instead of being written to a public file in uploads.
* Security - Member names, review replies and field options containing an apostrophe can no longer add HTML attributes to review and filter buttons.
* Security - Listora Moderators can manage any listing, and WooCommerce Shop Managers without the listing capability can no longer trash or renew other members' listings.
* Security - The payment webhook signing secret is masked, with Show and Copy buttons.
* Security - Review and listing reports are deleted with the review or listing, on uninstall and by the Erase Personal Data tool.
* Dev      - New helpers wb_listora_category_color(), wb_listora_field_value_label(), wb_listora_community_profile_url(), wb_listora_user_can_moderate_listings(), wb_listora_user_can_reply_to_review() and wb_listora_render_login_modal().
* Dev      - New filter wb_listora_submission_title_placeholder; wb_listora_one_review_per_listing() is deprecated, for removal not before 2.3.0.
* Dev      - The search reindex and importers no longer flush the persistent object cache, and the view counter writes no option rows.
* Dev      - Bundles Wbcom Credits SDK 1.10.3; the payments table gains a claimed_at column.
* Dev      - Clean on PHP 8.1 through 8.5.
* Compat   - Aligned with WB Listora Pro 2.1.0. Install both updates together.

= 2.0.0 - October 2026 =

Directory, per-type moderation and reviews, term pages, Near me radius, field visibility and a large reliability pass.

* New      - Every message sent through a listing's contact form is saved as a lead: owners read theirs under My Dashboard > Leads, site owners under Listora > Listings > Leads, and apps through the listora/v1/leads REST routes.
* New      - The contact form asks for an optional phone number, and the owner's email is branded, editable under Email Templates and replies go straight to the sender.
* New      - Leads are kept 12 months by default (Listings > Leads sets it) and are included in WordPress personal data export and erase requests.
* New      - Listing types have their own Moderation (use the site setting, manual or auto-approve) and Reviews (use the site setting, on or off) choices, and both now take effect; existing sites behave as before until a type is changed.
* New      - Category, location, tag and feature pages show the Directory filtered to that term, with the term title, breadcrumb and description, and the plain listings archive redirects to the Directory.
* New      - Near me carries the visitor position through the search and filters results by a radius of 1, 5, 10, 25 or 50, with the start value set in Settings > General.
* New      - Listing Types editor fields have "Show on listing page" and "Show in API" switches, and hidden fields stay out of listing pages, cards, the gallery, the public API and structured data.
* New      - Events demo pack adds eight events dated relative to the import day, so the Events Calendar and event pages are no longer empty.
* New      - Number fields can be shown as money in the site currency, and a min / max pair shows as one range: job cards, the listing page and Compare read "$140K - $185K", announced as "Salary $140K to $185K".
* New      - The Directory can be filtered to featured listings, and the Featured block "See all" lists every featured listing.
* New      - Settings > General has Country and City for where the directory is based; the setup wizard fills them in and the app configuration returns them.
* New      - Listing tags have their own screen under Listora > Categories, like categories, locations and features.
* Improve  - Browse by category hides empty categories by default, names the listing type on every unpinned tile (the type that uses the category most when no type lists it), keeps two columns on phones and spaces the "View all" link from the next block.
* Improve  - The Directory Features filter lists every feature in use, most used first, with the rest under "Show all features (N)", and a chosen type lists only the features its listings use (or its "Selected only" list).
* Improve  - Photo uploads follow the server limit, refuse oversized files by name before uploading, keep the photos that fit and show progress.
* Improve  - Add Listing shows "This field is required." under empty required fields, clears the featured-photo error once a photo is set, and gives a submitted draft the right confirmation.
* Improve  - The Education type has Phone, Email and Website fields for the provider on new installs.
* Improve  - Demo import spreads publish dates over 60 days, offers each type its own features, and no longer emails an approval for every sample listing; the setup wizard hides other plugins' notices and Skip on the last step.
* Improve  - Settings > Re-run Demo Import offers every demo pack, matching the setup wizard and WP-CLI.
* Improve  - Listora stylesheets load as linked layered files instead of an inline @import, so cache plugins can combine and defer them.
* Improve  - Listora follows the brand colour of Astra, Kadence, Blocksy, BuddyBoss and Elementor kits, and button hover darkens that colour.
* Improve  - Every admin screen shares one page header with notices below it, and dashboard empty states and star ratings share one component, with stars announced as "Rated N out of 5".
* Improve  - Listing expiry is off on a free directory and on while a paid add-on sells listing time, with a note on the setting saying why; events and job deadlines end on their own date either way, and new types default to no expiry in the editor and the REST API.
* Improve  - Structured data uses option labels for choice fields and image URLs for file and gallery fields.
* Improve  - Dashboard filter, services, blocked members, credits and calendar empty states use the shared empty-state card, so they match in spacing, icon and dark mode.
* Improve  - Credit checkout shows Listora's own messages for every payment error, and the billing country list is in the site's language, sorted by name.
* Improve  - Listing page tabs wrap onto a second row when they do not fit, so every tab stays visible at any screen width.
* Improve  - On phones the listing page keeps its content clear of the bottom action bar, and the sidebar scrolls with the page.
* Improve  - Prices members pay, such as credit packs and services, print in full instead of "1.2K", and three-decimal currencies such as KWD show all three decimals.
* Improve  - Directory search ignores common words, so "the pizza" finds what "pizza" finds, and a cleared filter no longer returns no results.
* Improve  - A review can be a star rating alone when the minimum review length is 0.
* Improve  - File and image fields, such as Company Logo, show on the listing page and as a file name on cards.
* Improve  - Accessibility: the Filters badge hides at 0, phone tap targets are 44px, card ratings read "Rated N out of 5", Reply moves focus to the reply box and the report dialog keeps keyboard focus inside.
* Improve  - Scheduled jobs are checked once a day in wp-admin, or right after an update or feature change, instead of on every admin page: about 45 fewer database queries per admin page.
* Fix      - Site Additional CSS and child-theme CSS apply to Listora blocks again; child themes wrap Listora rules in `@layer listora-overrides`.
* Fix      - Add Listing shows a reason and a link to My Dashboard for a member whose role cannot submit, instead of a blank page, and the dashboard stops offering Add Listing to them.
* Fix      - Directory search runs on Enter or the Search button, so the grid no longer stays unfiltered while the count changes, and Enter works in the Location field.
* Fix      - "Listed by" no longer shows a private first and last name when the member has no public display name.
* Fix      - Review criteria with the same key no longer share one star group, and picking an address without a street keeps the place name and adds the country.
* Fix      - Services get their categories in the demo import, and the Services box lists categories as checkboxes.
* Fix      - An admin saving a member's listing from the frontend edits it instead of hitting a duplicate warning, and a listing the user cannot edit returns 403.
* Fix      - A rejected listing can no longer be published straight from Quick Edit, Bulk Edit, the block editor, the API or WP-CLI without an expiry; every path asks for Pending Review first and says so, and approving a pending listing sets its expiry and sends the approval email.
* Fix      - Updating one setting of a listing type through the API no longer resets its other settings, and saving a built-in type in the editor keeps it built in (an update restores types already affected).
* Fix      - The listing page "Open now" badge agrees with the Open now search for split hours, closing time and overnight spans.
* Fix      - Competitor migrations started from WB Listora Pro run again, in the background one batch at a time.
* Fix      - Owners can open their own rejected, deactivated and awaiting-payment listings from the dashboard, and visitors to an expired listing see a short notice.
* Fix      - Event and listing dates and times show as entered in every timezone instead of shifting by the site offset, and event structured data carries the site's time zone offset for Google.
* Fix      - A review posted a minute ago no longer reads "4 hours ago", and the aspect stars in the review form are thumb-sized.
* Fix      - A Directory page past the last page shows the last page instead of "Showing 1,961-127 of 127".
* Fix      - The map popup works on phones: it pans clear of the controls, shows the type name, uses translated labels and has a 40px close button.
* Fix      - The map says "zoom in to see more" only when the marker limit hides results, and the dashboard no longer opens tabs for features that are off.
* Fix      - JobPosting structured data includes the required title and datePosted, puts the location in jobLocation, gives the salary its currency and unit, and uses Google's employment type and remote values.
* Fix      - Saving the Job, Classified, Education, Healthcare or Real Estate type no longer changes its Schema.org type to LocalBusiness, and an update restores types this already changed.
* Fix      - A listing's rating is one public average and count, rounded to one decimal, on the page, cards, REST API and structured data; blocking a reviewer hides their review from you without changing the total.
* Fix      - Demo jobs store their company and salary period in the Job type's own fields, and an update repairs demo content imported earlier: company, salary period, spread publish dates and image authors.
* Fix      - The Listing Types editor no longer resets field settings it has no control for on save, and "Show in API" now saves.
* Fix      - The listing form accepts a pin dropped on the map without a street address, and filling city and region from the pin no longer overwrites what the owner typed.
* Fix      - Editing a listing as an admin or editor no longer asks to accept the terms again.
* Fix      - Owners with the Subscriber role can open their own pending, rejected, paused or unpaid listings from the dashboard; everyone else still gets a 404.
* Fix      - Submitting a saved draft from the dashboard says "Submit Listing" and shows "Listing Submitted!" with the real outcome (live, waiting for review or waiting for credits) instead of "Listing Updated!".
* Fix      - Sites that imported the demo before 2.0.0 offer each listing type its own features instead of every demo feature.
* Fix      - Updating no longer logs a "translation loading was triggered too early" notice: database upgrades now run at the start of init.
* Fix      - Near me results show how far each listing is on its card, in the site's distance unit.
* Fix      - A customised email body keeps the branded header, the "sent by" footer and the preferences link, and the editor lists every variable each email can use.
* Fix      - With Pro's White Label on, admin notices, the setup wizard, settings, Site Health checks, uninstall and privacy texts use the custom brand name instead of "WB Listora".
* Fix      - Every stylesheet reads design tokens Listora declares, so text sizes, spacing, soft status colours and the My Responses line height follow the theme and dark mode instead of fixed fallbacks.
* Fix      - Category, location, tag and type pages use the term name as the page heading and breadcrumb on themes that read the title outside the loop, such as BuddyX Pro.
* Fix      - Service categories in the dashboard are a checkbox list, so several can be chosen without Ctrl or Cmd.
* Fix      - The Claims tile on the admin dashboard gets its icon tint like the other tiles.
* Fix      - The dashboard Buy Credits link opens in the same tab.
* Fix      - Notification emails: demo import stays quiet, "1 helpful vote" reads correctly, and the plain-text part no longer shows &quot; or &amp;.
* Fix      - The Business Claims modal no longer promises a Verified badge, and its upload hint lists every accepted file type.
* Fix      - "Delete data on uninstall" removes every listing with its images, every category, location, tag and type, and the Credits SDK data, whichever of WB Listora or Pro is deleted first, and touches no other plugin's rows.
* Fix      - Undeclared v1 design tokens in empty states and labels now point at current tokens, so their spacing and colours apply.
* Fix      - Editing a review checks the same minimum length as writing one, counted in characters.
* Fix      - Editing a listing writes only its own type's fields, even when another type is submitted.
* Fix      - A rate-limited request says when to retry (Retry-After), and the wait no longer grows while a client keeps trying.
* Fix      - Services added in the wp-admin Services box are no longer saved a second time by the next Save, and a refused service says why.
* Fix      - Phone and email fields with their own key, such as a seller email, show on the listing page instead of being hidden as part of the Contact card.
* Fix      - Renewal is offered only for listings that expire after a set time, so free directories, events and jobs are not renewable, and a reinstated listing gets a fresh expiry date.
* Fix      - Expiry emails show no Renew button for a listing that cannot be renewed, and the one-day reminder says "expires tomorrow".
* Fix      - Members can change their email in the Profile tab with their current password and see the confirmation notice, and a display name can no longer be cleared.
* Fix      - Opening the edit link of a listing you cannot edit shows a notice with a link to My Listings instead of an empty Add Listing form.
* Fix      - Saving a listing with an empty title, or a contact message with no name or message, is refused with a clear message.
* Fix      - Featuring or unfeaturing a listing updates the Featured block and filter at once, and a deleted featured listing leaves search immediately.
* Fix      - Imported listings and their images belong to the member who ran the import, including batches after the first.
* Fix      - Every admin list and the Leads tab show the last page for an out-of-range page number instead of an empty list.
* Fix      - The Leads tab stays available to owners whose listings are all paused, expired or drafts.
* Fix      - Credit history shows a translated line for every entry, including purchases and gateway refunds.
* Fix      - Direct credit pack prices use each currency's own decimals, so a JPY pack of 2,500 no longer shows as 25.
* Fix      - Remote job structured data names the country applicants must live in, as Google requires.
* Fix      - Space submissions, migrated reviews and renewal times are stored in UTC like the rest of Listora's data.
* Fix      - Demo content imported by older versions gets future job deadlines and event dates, and leftover demo images get an owner.
* Fix      - The unsubscribe page shows as a page instead of plain text, and Health Check lists every Listora table.
* Fix      - Grid, Categories and Featured blocks accept up to 12 columns, and an unknown card layout falls back to Standard.
* Fix      - On multisite, uninstall erases each site with its own listing types, and keeps member data shared across the network unless every site chose to delete it.
* Fix      - CSV exports and imports no longer log deprecation notices on PHP 8.4 and later.
* Fix      - Search suggestions show "&" instead of "&amp;" in listing, category and location names.
* Fix      - Listing pages no longer scroll sideways on phones in right-to-left languages.
* Fix      - On phones, "Search this area" steps aside while a map popup is open, so it never covers the popup.
* Fix      - Previewing or test-sending the email verification template no longer logs PHP warnings.
* Fix      - The CSV export writes "&" in titles, descriptions and category names instead of "&amp;".
* Fix      - Currencies shown by their code get a space, such as "CHF 12.50" and "KWD 1.234".
* Fix      - A listing with one review reads "based on 1 review".
* Fix      - Reinstalling after an older uninstall that left the credits version marker behind rebuilds the credit tables instead of leaving credits broken.
* Fix      - Deactivating WB Listora clears every scheduled job, including review reminders, the email log prune, the search reindex and Pro's jobs, so none is left to fail.
* Fix      - A search reindex after an update also finishes on hosts with WP-Cron turned off.
* Fix      - Search suggestions on the Directory page open above the listing type tabs instead of behind them.
* Fix      - The verify-email status on the dashboard and Add Listing, the low credit balance card and the map border follow dark mode.
* Security - Leads follow a listing to its new owner after a claim or author change, and a personal data export or erase also covers messages a member sent under another email address.
* Security - The listing API no longer shows an owner's login as their name to visitors, leaves internal fields (plan, expiry, demo and consent state) out of public responses, and limits logged-out reads to 120 a minute per IP.
* Security - Reviewers who never chose a display name show as "Member" instead of their login.
* Security - Demo accounts get random passwords, and a migration locks existing demo accounts still using the old shared password.
* Security - Block spacing, radius and shadow settings accept only numbers, known units and colours, so a block attribute cannot add CSS rules or load outside URLs.
* Security - Members who never chose a display name get no profile link (profile addresses use the login), and listing embeds and feeds name the listing's public owner instead of the account.
* Security - Helpful votes, reports and owner replies are refused on a review that is not published yet, and on a review that no longer exists.
* Security - A submitted listing type must be a real type slug, and a per-type "reviews off" setting also blocks review edits, owner replies, helpful votes and reports.
* Security - Block style output strips `<` so block attributes cannot close the style tag and inject markup.
* Security - A listing title could carry script into the map popup that ran for anyone who opened its marker; popup text and links are now fully escaped.
* Security - Unpublished listings stay hidden from visitors and other members in the listing and related-listings API, the duplicate check and the contact form.
* Security - Reviews, review edits, owner replies and claims run the same spam checks as submissions: the keyword list, the link limit and Akismet.
* Security - Claim proof documents are deleted with their claim, their listing, a privacy erase and on uninstall, and review photos are deleted with their review, listing or member data.
* Security - Account deletion also erases the contact messages a member sent, and deleting a listing deletes the messages sent to it.
* Security - The Listing Map height accepts a CSS length only, so the block cannot add other styles.
* Security - Listing excerpts in the API are plain text, so encoded markup cannot become a tag in an app.
* Security - The Email Log CSV export neutralises spreadsheet formulas.
* Security - Business hours are cleaned of markup when saved, and listing structured data can no longer close its script tag.
* Dev      - The code-standard ratchet counts hand-built empty states in templates and blocks; use wb_listora_render_empty_state().
* Dev      - bin/token-parity-check.py fails the build when a stylesheet reads an undeclared --listora-* token; the optional knobs are listed in the styling guide.
* Dev      - The dead-end crawl accepts empty states that are correct on real data: a member with no listings, the compare page before a pick, and a month with no events.
* Dev      - wb_listora_before_contact_form_submit lets a site or add-on refuse a contact message; wb_listora_after_contact_form_submit now carries the phone number and lead ID.
* Dev      - Uses only the Credits SDK 1.10 headless API (Billing::schema, Countries::display_name); nothing on screen comes from the SDK's text domain.
* Dev      - Removed deprecated code: Listing_Type::is_default() and its interface method (use is_builtin()), the WBListora\Search\Facets class, the importers' set_taxonomy_terms() shims (use Term_Helper::set_terms()), Notifications::LOG_MAX_ENTRIES and its html_to_text() shim, and the submission block's termsPageId attribute.
* Dev      - Removed unused settings default_type, max_upload_size, facet_cache_ttl, enable_guest_submission, guest_email_verification and reviews.require_login; an update clears them and moves a submission block's Terms page into Settings when none is set.
* Dev      - wb_listora_most_favorited() returns the listings favourited most since a date, from one grouped count; the favorites table gains a date index.
* Dev      - wb_listora_review_gate() and wb_listora_rate_limit() let add-ons apply the review rules and rate limits Free uses.
* Dev      - New filters `wb_listora_theme_isolation`, `wb_listora_css_variables` and `wb_listora_owner_name_use_real_name`, and the JS hook `wbListora.formatAddress`.
* Dev      - New filters `wb_listora_type_moderation` and `wb_listora_type_reviews_enabled`; listing-types REST accepts `moderation` (global, manual, auto) and `reviews` (global, on, off), and `wb_listora_user_can_edit_listing()` is the one edit-permission check.
* Dev      - New filters `wb_listora_term_archive_filters`, `wb_listora_term_archive_intro`, `wb_listora_categories_show_type_label`, `wb_listora_categories_hide_empty` and `wb_listora_search_features_visible`.
* Dev      - New filters `wb_listora_near_me_radius_options`, `wb_listora_visible_meta`, `wb_listora_submission_no_permission_message` and `wb_listora_expired_listing_notice`, plus `wb_listora_can_offer_submission()`.
* Dev      - New `wb_listora_brand_name()` (filter `wb_listora_brand_name`) gives notices one product name for white label, and `wb_listora_get_dashboard_tab_labels()` lists dashboard tabs.
* Dev      - `Status_Manager::transition_allowed()` is the one status-move rule (filter `wb_listora_allow_status_transition`), applied to `wp_insert_post` saves and the core REST route as well as `change_status()`.
* Dev      - Field props `range_end`, `range_start` and `range_label`, and `wb_listora_field_display()`, which every field list uses to show a range once.
* Dev      - New `wb_listora_term_type_usage( $taxonomy )` (cached listing counts per term and type) and `wb_listora_get_feature_filter_map()`.
* Dev      - New `wb_listora_site_date( $days_ago )` returns the site-calendar date that analytics day buckets are cut with; no query uses the database server's clock (SQL NOW()) any more.
* Dev      - New `wb_listora_user_public_name()` (filter `wb_listora_user_public_name`), filter `wb_listora_public_meta_keys`, and the `public_read` rate-limit action tunable through `wb_listora_rate_limit_config`.
* Dev      - New `wb_listora_display_amount` filter in `wb_listora_format_currency()` lets a currency converter change what is shown, and the new `$exact` argument leaves charged amounts as recorded.
* Dev      - New filter `wb_listora_should_send_notification` (a skipped email fires `wb_listora_notification_skipped`), `wb_listora_log()` for debug logging, and `wb_listora_queue_migration_import()`.
* Dev      - New `DELETE /claims/{id}` and `Claims_Model::delete()` with `wb_listora_before_delete_claim` and `wb_listora_after_delete_claim`, used by wp-admin and REST.
* Dev      - Shared UI helpers: `wb_listora_render_admin_header()` (prints the wp-header-end marker), `wb_listora_render_stars()`, `wb_listora_render_empty_state()`, `wb_listora_render_admin_empty_state()`, a `wb_listora_render_pagination()` that takes a base URL, and one `review-form.php` for the Reviews block and listing page.
* Dev      - New `wb_listora_get_demo_packs()`, a public `Rate_Limiter::client_ip()`, `Dashboard_Data` for the member dashboard block and REST, and `wb_listora_local_timestamp()` for stored wall-time dates.
* Dev      - The `directory_slug`, `submission_slug` and `dashboard_slug` settings are no longer read; URL helpers use `wb_listora_default_page_url()` and the `wb_listora_*_url` filters still override.
* Dev      - New wb_listora_csv_cell() for formula-safe CSV text, and the meta service's get_visible_values() applies the page and API field visibility rule.
* Dev      - New wb_listora_clamp_paged(), wb_listora_query_total() and wb_listora_window_counter(); author is a search filter.
* Dev      - The hooks reference is generated from the source with a Deprecated table and removal versions, and local CI fails when it drifts.
* Dev      - Recurring jobs are declared in one list, Cron_Scheduler::jobs(), extended with the wb_listora_cron_jobs filter, and one-off jobs use Cron_Scheduler::schedule_single(); the wb_listora_dedupe_recurring_hooks filter is removed.
* Compat   - Requires WB Listora Pro 2.0.0 or later when Pro is active; install both updates together.
* Compat   - Requires PHP 8.1 or later, the oldest version the code runs on.

= 1.9.0 - September 2026 =

* New      - The admin menu is nine hub items (Dashboard, Listings, Listing Types, Categories, Moderation, Monetization, Analytics, Tools, Settings), each with a tab row; old links keep working and moderators see only their tabs.
* New      - One shared admin table for Claims, Reviews, Email Log and every Pro list: status views, search, filters, a member search instead of raw user IDs, sortable columns, bulk actions, a detail drawer, numbered pages, and rows that stack on small screens.
* New      - Listing types can be Draft (hidden from members) or Active, and deleting a type moves its listings to another type first.
* New      - Listing Types editor: field actions always visible, one searchable picker for categories and features, and a page a third of its former height.
* New      - Categories and Features show which listing types use each term, with a filter by type.
* New      - Email Log has its own table with the full email, Sent and Failed views, filters, search, Resend, and a retention setting (90 days by default).
* New      - Settings > Notifications lists every email, Free and Pro, as one row with an on/off switch, recipients, Edit template and Preview.
* New      - Settings > Credits is split into Pricing, Limits and Payments sub-tabs, and Listing Limits per Role lists only roles that can submit.
* New      - Settings > Maps offers one-click tile sources (OpenStreetMap, MapTiler, Stadia) and a map picker with place search for where maps start.
* New      - Settings > Integrations shows the plugins Listora is connected to right now.
* New      - Admin Dashboard leads with Needs attention (every queue), links each card to what it counts, and shows the last 30 days and a real activity feed.
* New      - Directory: the type chips are the type picker and scroll with arrows; the map fits the listing cluster and says "Showing 500 of N on the map, zoom in to see more" when the marker cap hides results.
* New      - Single listing: a Location card in the sidebar with the map, address and Get directions, and a gallery lightbox with View all photos.
* New      - Browse Categories has "View all N categories" grouped by type; the Featured carousel has prev/next arrows and moves by whole pages.
* New      - Member dashboard: linked and grouped navigation, Next steps on Overview, search, status filter and numbered pages on My Listings, a profile photo control, and grouped notification toggles.
* New      - Add Listing: tags are chips, Business Hours has "Copy Monday to all days / weekdays", the featured image has Replace and Remove, gallery photos reorder, and the preview shows the listing card and every section with Edit links.
* New      - Events calendar shows multi-day events on every day they cover, links each event to its listing, and lists a month agenda on phones.
* New      - Reviewers are told when their review is awaiting moderation.
* New      - Demo content ships curated photos per listing, category icons and colours, 30 days of views and clicks, and demo remove also removes the terms, users and analytics it added.
* Improve  - One title per admin screen, and admin buttons keep their 13px size; the member button styles no longer override them.
* Improve  - Destructive admin actions sit in a row menu behind a confirm instead of a red button on every row; one stat-card style and one Export CSV style across screens.
* Improve  - Settings nav is grouped by task with a top tab bar below 1200px, one switch per behaviour, and "Reset this tab" instead of a red Reset to Defaults.
* Improve  - Edit Listing opens with the fields drawer open and Listing Type first; All Listings fits an iPad, hides Views by default and shows Reports only while something is reported.
* Improve  - Locations use one term per country, and the 1.9.0 update merges duplicate country trees.
* Improve  - Only the chosen listing type's fields are on the Add Listing page, checkbox fields show one label, and addresses are stored as a street address instead of the geocoder's full line.
* Improve  - Listing pages stack the sidebar below the content at 900px or less, use one heading scale, and show the phone once.
* Improve  - Grid cards read Free for a price of 0, keep the favourite count in its pill, align their footers, and leave tags to the single listing.
* Improve  - Credit history reads On hold, Hold released and Spent instead of Hold, Refund and Deduction.
* Improve  - Listing Details shows free-text fields in full.
* Improve  - Demo listings use photos chosen for each listing, with no photo repeated within a listing type; demo reviews are written by demo member accounts with different names.
* Improve  - The Restaurant type offers Vietnamese, Korean, Seafood, Latin American and Cafe & Coffee cuisines.
* Improve  - The Credits tab on the member dashboard pages through the full transaction history instead of stopping at the latest 20.
* Improve  - Each settings tab has one Save Changes that saves everything on the tab, including email templates, and warns before you leave with unsaved changes.
* Fix      - Update Listing on a draft with no plan chosen did nothing; the form now says which choice is missing.
* Fix      - The Reviews tab on a listing could not be reached on an iPad; the tab bar now scrolls inside its column.
* Fix      - Related listings showed Featured twice and left an empty column.
* Fix      - The admin Dashboard's "Unique Users" and "Pending Items" counted the wrong things.
* Fix      - A claim or review already decided by another moderator is reported, not applied twice, and a page refresh never repeats an action.
* Fix      - The Events calendar showed the previous month's name for sites west of UTC.
* Fix      - The Featured block with Sort by Featured showed non-featured listings.
* Fix      - The Reply button and Write a Review follow the Reviews settings, and both review forms send the CAPTCHA token.
* Fix      - Demo content created duplicate categories, left expired demo listings behind on remove, stored empty galleries when seeded from WP-CLI, and wrote reviews under authors that never existed.
* Fix      - A listing type re-created without fields (the "business" and empty Job types) is repaired with its default setup on update and when demo content is added.
* Fix      - Healthcare Specialty showed a raw slug on cards.
* Fix      - Directory search filters no longer lead to "page not found" on sites where another plugin or the theme uses the same address words; filter links now use listora_ names such as ?listora_category=, and older links keep working.
* Fix      - The Reviews tab on the member dashboard now counts the reviews it lists, labels the ones awaiting approval or not published, and no longer lists reviews marked as spam. The website and the app show the same number.
* Fix      - Two paid actions by the same member at the same moment, such as a Featured upgrade or a renewal started in two tabs, can no longer both go through and push their credits below zero.
* Fix      - A Featured upgrade or renewal that fails no longer gives back an earlier charge on the same listing, such as its plan.
* Fix      - Refunding or cancelling a WooCommerce order for credits now removes the credits it added, including partial refunds.
* Fix      - Stripe refunds now remove the refunded credits; they could not be matched to their purchase. A second partial Stripe refund no longer removes too many.
* Fix      - A paid Stripe or PayPal credit purchase can no longer be lost when two members check out at the same moment, or when the first attempt to credit it fails.
* Fix      - After buying credits with Stripe or PayPal from the Add Listing form, the Credits page again offers the way back to the saved listing.
* Fix      - Stripe and PayPal credit packs keep their own id in the purchasable pack list; every one was reported as pack 0.
* Fix      - The member dashboard shows credit balances as 10 or 12.5 instead of 10.00, and a member with a negative balance sees how many credits they owe.
* Fix      - With credit sales switched off, the Buy Credits page says purchases are paused instead of showing a page-not-found error, and a link to the dashboard Credits tab opens Overview.
* Fix      - On the BuddyX theme, Listora buttons that are links (such as Go to your dashboard) keep their button colours; BuddyX 5.1 had turned them into underlined link text.
* Fix      - A renewal lasts your standard expiration period when Renewal duration is 0, instead of a full year; a listing never expires when that period is 0 too.
* Fix      - New listings follow Settings > Default expiration; it was only used for renewals.
* Fix      - A renewal that fails no longer counts as a renewal, keeps the old expiry date, and tells the member plainly that they weren't charged.
* Fix      - Renewed listings expire at the right time; the new date was stored in site time and expired hours late.
* Fix      - The listing submission cost is charged: members without enough credits are asked to buy credits instead of listing for free, and auto-approved listings are charged too.
* Fix      - Saving a draft no longer holds the submission cost; it is charged when the draft is submitted.
* Fix      - Deactivating a live listing no longer refunds its submission cost.
* Fix      - Two paid submissions sent at the same moment can no longer both be charged against credits for one.
* Fix      - A settings save that a section refuses now says so instead of reporting Settings saved.
* Fix      - Claiming or reporting a listing confirms once, in the dialog, instead of also popping up a second notice.
* Fix      - Long notices wrap inside their box instead of running past it.
* Fix      - Editing a listing from the frontend no longer strips images, video and formatting from its description, and the CSV, JSON and GeoJSON importers keep description HTML.
* Fix      - A listing on a paid plan is charged once; the plan was taken at publish, the listing was then parked awaiting credits and the next top-up charged the plan again.
* Fix      - Two listings submitted at the same moment no longer leave a response pointing at a listing that was never saved; the member is asked to submit again.
* Fix      - Text typed into frontend forms keeps its quotes and dashes on save.
* Fix      - Search suggestions wait for a pause in typing before asking the server.
* Fix      - Unsaving a listing on the dashboard Favorites tab removes its card and updates the count.
* Fix      - Accepting the Terms of Service on a frontend edit is remembered; owners of listings made in wp-admin or imported were asked again on every edit.
* Fix      - The Add Listing success message says the listing is live when it published at once, not that it is pending review.
* Fix      - Per-criterion review ratings are saved on every site; without Pro's multi-criteria reviews they were collected and then dropped.
* Fix      - Ticking Selected only in the Listing Types Features or Categories picker no longer makes Save fail.
* Fix      - Buying credits by Stripe or PayPal works for members with no billing details on file; the first Buy click asks for name and country once, then checkout opens.
* Fix      - Form rows built on the shared form-field styles stack label above field again; those styles had been switched off by mistake.
* Fix      - The dashboard services window no longer slides under the WordPress admin bar.
* Fix      - Block styles are served under the plugin version, so style changes reach returning visitors after an update instead of waiting on the browser cache.
* Fix      - Share on a listing no longer locks page scroll on browsers without a share sheet; it copies the link and says so.
* Fix      - On phones the theme's back-to-top button no longer covers the Save button in the listing action bar (Reign, BuddyX).
* Security - Submitting a saved draft always charges the listing submission cost; a made-up plan id no longer lets a member list for free.
* Dev      - New hooks: wb_listora_dashboard_nav_group, wb_listora_member_notification_events, wb_listora_dashboard_attention, wb_listora_dashboard_activity, wb_listora_dashboard_period_cards, wb_listora_active_integrations, wb_listora_credit_entry_types, wb_listora_event_labels, wb_listora_notification_events, wb_listora_notification_preview, wb_listora_credits_subtabs, wb_listora_settings_general_rows, wb_listora_reset_field_aliases, wb_listora_show_basic_csv_import, wb_listora_featured_backfill, wb_listora_import_location_as_place, wb_listora_layered_style_bases, wb_listora_show_credit_record and wb_listora_demo_gallery_max. See the hooks reference.
* Dev      - Admin_Table service (wb_listora_service( 'admin_table' ), contract Admin_Table_Interface) and Admin_Table::stat_cards(); Listing_Type_Registry gains get_active(), reassign_listings() and install_default(); listing type REST routes take status and reassign_to and answer 409 listora_type_has_listings.
* Dev      - New helpers wb_listora_directions_url(), wb_listora_event_label(), wb_listora_notification_enabled(), wb_listora_credit_entry_types(); GET /settings/notifications/preview; wb_listora_log_email() takes the body and headers.
* Dev      - DB 1.9.0: email_log table (the email log option is imported and deleted), location repair, hold-release ledger retype. New meta _listora_pre_claim_author, _listora_status on types, term meta _listora_country_code; admin URL params renamed claim_action and review_action.
* Dev      - Submission templates: step-details.php renders each type inside a <template> that view.js mounts; the form is novalidate; a required radio group may carry data-listora-required-message. Frontend CSS loads in @layer listora with an isolation reset.
* Dev      - Removed the wb_listora_skip_admin_header filter with the injected title bar.
* Dev      - Directory filter URLs are now listora_-prefixed (listora_keyword, listora_type, listora_category, listora_location, listora_page and every field filter). Read them with wb_listora_url_arg() and build links with wb_listora_url_args(); REST parameters are unchanged.
* Dev      - New wb_listora_with_credits_lock( $user_id, $callback ) runs a credit spend so no other spend by the same member can run at the same time. Wrap any balance check and hold in it.
* Dev      - Bundled Wbcom Credits SDK updated to 1.7.2. New wbcom_credits_checkout_enabled filter decides whether a credit checkout may start.
* Dev      - New wb_listora_format_credits() formats a credit amount as 10 or 12.5, never 10.00.
* Dev      - wb_listora_log_email() records an email in Listora > Email Log, for add-ons that send their own mail.
* Dev      - wb_listora_dashboard_tab_available() says whether a dashboard tab is switched on for the site; wb_listora_hide_unavailable_pages now also passes the page and its registry key.
* Dev      - wb_listora_listing_duration_days() returns how long a listing runs: plan, then listing type, then Default expiration.
* Dev      - wb_listora_listing_submission_cost() and the wb_listora_listing_submission_charge action; the credits SDK's listing_submission consumer holds on that action.


= 1.8.0 - September 2026 =

The listing editor in wp-admin can finally manage photos and video, and a configured payment gateway reaches the Buy Credits page.

* New      - The setup wizard offers pages for the Categories, Featured Listings and Events Calendar blocks, which previously had nowhere to live. Existing pages built around those blocks are adopted rather than duplicated.
* New      - Turn Services off for a listing type that does not need them, such as Jobs or Classifieds. Existing services are hidden rather than deleted, so switching it back on restores them.
* New      - Features > Contact Owner Form turns the listing contact form off, on the website and in the app.
* New      - Type an address on the Add Listing form and press Enter to pick it from a list of matches, which fills the town, region, country and postcode and places the map pin. It used to guess from what you had typed so far and silently take the first result, so the pin often landed on the wrong street.
* New      - Reporting a listing now emails administrators and moderators, so a report reaches a person instead of sitting unread. Repeat reports on the same listing are throttled. The listing owner is deliberately not told. Switch it off in Settings > Notifications > Listing reported.
* New      - Listing pages show who listed the business, the way Google Maps and Yelp do. Uses the listing's Contact Name, or the account display name when there is none, and never a login or an email address. Turn it off in Features > Show Who Listed It.
* New      - A dashboard page can belong to one listing type, so a site running Jobs, Classifieds and Real Estate on separate pages gives each page its own dashboard. The listing counts on that page follow the same type.
* New      - Video URL is now a field on every listing type, so the listing editor in wp-admin can show and change a video a member submitted. The submission form has always asked for one, but nothing in the admin could display it.
* New      - The Media box in the listing editor now has a working photo gallery - add images, remove them, and see what is already attached. It previously rendered with no gallery control at all.
* Improve  - The plan picker no longer offers plans restricted to other listing types.
* Improve  - The Reports column on the listings screen is visible by default, so a report is visible without opening Screen Options.
* Improve  - Listing type is now picked from a list in the block editor instead of typed as a slug, on every block that filters by type. A grid pinned to a type with no listings now says which type is empty instead of asking the visitor to adjust filters they never set.
* Improve  - Stripe and PayPal settings are now shown on the Credits tab by default. Setting up direct credit purchases previously required hand-written PHP, so the Credits tab offered to add packs while giving you nowhere to enter the keys they need.
* Improve  - Settings > Submissions now explains how listing limits work for a member with more than one role: the most generous limit wins, and 0 does not block someone who also holds another listed role. The Administrator row shows as always unlimited instead of looking editable.
* Fix      - The message shown after paying for credits can now be closed. It had no close button and came back on every page reload.
* Fix      - Choosing a plan that is not available for the listing type you are submitting is now refused with a clear message, instead of saving the listing as "Awaiting credits" for a plan no top-up could ever activate. Listings already stuck that way are released for review when you update.
* Fix      - The Credit Balance on your dashboard no longer jumps to a figure around a hundred times too high after paying with Stripe or PayPal. It was showing the stored value rather than the amount, and reloading did not clear it.
* Fix      - The "nothing links to these pages" notice now appears only on Listora Settings, where you can act on it. It was repeating on the listings screen and every listing taxonomy screen.
* Fix      - The Blocked Members section on your dashboard Profile tab is styled to match the rest of the tab. It was rendering as unstyled text with list bullets.
* Fix      - A directory whose only credit pack is a Direct (Stripe or PayPal) pack no longer says "No credit packs yet" on the Credits tab and the setup checklist.
* Fix      - Credit receipts now open from the link in your dashboard or confirmation screen even if you are signed out or open them in a new window. They used to show a technical error instead of the receipt.
* Fix      - Two listing grids on one page now page independently. Clicking Load More under one grid appended the other grid's listings, so a "Top restaurants" section filled up with hotels.
* Fix      - Enquiries sent through a listing contact form are now counted whether or not the Analytics feature is switched on, and on sites running the free plugin alone. The Leads figure stayed at zero while the messages arrived.
* Fix      - Deactivating your account twice now answers normally instead of an error saying you cannot post. A double tap, a stale tab or a retry no longer looks like a failure.
* Fix      - A listing paused awaiting credits now shows in the app's dashboard, not just on the website. The app also reported a listing count that agreed with the omission.
* Fix      - Credits no longer take the site down when another Wbcom plugin loads an older copy of the shared credits library. Every credits check now confirms the loaded library can actually service a balance, hold or purchase, so a mismatched site shows credits as unavailable with a notice naming the plugin to update.
* Fix      - Logged-out visitors can see the "Claim" button on a listing again, and clicking it prompts them to log in. It was only shown to signed-in users, so the owners it exists for never saw it.
* Fix      - Pressing Enter in the directory search box now filters the listings. It updated the address bar but left the results untouched, so the keyword had to be submitted again with the Search button.
* Fix      - Upload buttons in the listing editor now open the media library. Any file field, such as a Job listing's Company Logo, printed an upload area that did nothing when clicked.
* Fix      - Selecting gallery images in the listing editor now survives saving. The choice appeared to work and was silently discarded, including when only one image was chosen.
* Fix      - A toggle field, such as a Job listing's Position Filled, now renders as a checkbox instead of a text box.
* Fix      - Job listings now have a Media section. They were the only listing type without one, even though the submission form offers photos and video for every type.
* Fix      - A configured payment gateway now reaches the Buy Credits page. With Stripe enabled and keyed, every pack still showed "Checkout unavailable" and no buy button was ever drawn.
* Fix      - The Marker clustering setting now applies to map blocks that have not set their own clustering. It only reached the mobile app, so turning it off changed nothing on the website.
* Fix      - Settings > General shows "Page selected but not published" for a draft privacy policy or terms page instead of "Not set", and saving Settings no longer drops a selected draft terms page.
* Fix      - A business claim with a proof file attached is saved again. Attaching a file made the request run out of memory, so the claim was never recorded.
* Fix      - With Google Maps as the provider, the directory map block now draws with Google alone. Leaflet also started on the same map, which left it broken.
* Fix      - Reviews in wp-admin have a Read full review link showing the whole text and the stars given per criterion, so a review is no longer approved from a 15-word excerpt.
* Fix      - Reply is offered only on approved reviews, in wp-admin and through the REST API. A reply to a pending or rejected review answered something visitors could not see.
* Fix      - Each review on a listing now shows the stars the reviewer gave per criterion, such as Food or Service, not only the overall rating.
* Fix      - With two listing grids on one page, each "Showing X-Y of Z" now counts its own grid. Both showed the last grid's numbers and neither moved with Load More.
* Fix      - Approving, rejecting or deleting a review in Listora > Reviews now updates the listing's star rating and review count. Existing counts are corrected by Rebuild Search Index in Listora settings.
* Fix      - Bulk Apply on the Reviews and Claims screens and the Claims Filter button work again. They showed Processing... and did nothing.
* Fix      - A logged-out visitor who clicks Claim or Report now sees a log-in prompt that says so. It always read "Log in to save listings", the favorites wording.
* Fix      - Unticking a toggle such as Position Filled on the frontend edit form now saves; it came back ticked after every update.
* Fix      - Listing Types: switching Services off now saves, and the member dashboard hides the services count and Manage Services button for that type.
* Fix      - A Listora Moderator saving a listing in wp-admin no longer empties its photo gallery and file fields, and a member editing their listing no longer loses photos an admin added.
* Fix      - A listing submitted from the website now gets its Country, State and City locations, so the Locations panel in wp-admin and the location filter show it.
* Fix      - "Listed by" no longer shows an account's login name; it uses the real name, or is hidden when there is none.
* Fix      - Save Draft no longer runs into the new-listing limit; only creating a listing counts, and a draft that cannot be saved now says why.
* Fix      - Save Draft is no longer shown on the Type step of Add Listing, where there is nothing to save yet.
* Fix      - Pending listings on the dashboard no longer show an empty More menu.
* Fix      - Admin notices no longer appear twice on the Listings and Categories screens.
* Fix      - The Business Hours time picker no longer shows a second box inside the field on some themes, and matches dark themes.
* Fix      - Members behind one shared network (an office, a campus) no longer run into each other's limits; each account has its own.
* Fix      - A listing's Country, State and City now follow its address when it is changed in wp-admin or the block editor, not only on the website.
* Fix      - A member editing a service no longer loses a photo an admin added to it.
* Fix      - The Services box in the wp-admin listing editor is hidden for listing types with services switched off.
* Fix      - When the bundled Credits SDK is missing, the admin no longer also claims another plugin is loading an older copy of it; one notice explains the fix.
* Fix      - The directory search bar no longer makes the page scroll sideways on phones with themes such as Twenty Twenty-Five; the Location field now fits its column.
* Fix      - The Add Listing step bar now shows every step on phones. The first and last steps were cut off at 390px on every theme.
* Security - On a directory that charges credits, a listing can no longer be published without a plan. Submitting without choosing one, or publishing a plan-less draft, is now refused instead of going live free of charge.
* Security - A space curator can no longer add a non-listing or unpublished post to a BuddyNext space showcase, and the showcase total no longer counts rows it does not show.
* Dev      - wb_listora_pro_show_gateway_settings still hides those settings for a site that wants them hidden; pass __return_false.
* Dev      - Field::show_in_admin is now honoured, on render and on save, so a field can be kept off the listing editor. The property existed and defaulted to true but was never read.
* Dev      - wb_listora_get_template() now defines $view_data in template scope, so a template can read either $view_data['key'] or the extracted variable.
* Dev      - window.wbListoraGeocoder replaces the address lookup on the location picker. Return a promise for an array of { lat, lon, display_name, address }. Pro registers a Google-backed one when Google is the live map provider.
* Dev      - The search engine has extension seams: wb_listora_search_parse_args (covers search, the map clusters and the grid, map and featured block renders in one listener, and runs before the cache key is built), wb_listora_search_where_clauses and wb_listora_search_where_params, wb_listora_search_orderby, and wb_listora_search_result. The existing wb_listora_search_args stays REST-only.
* Dev      - wb_listora_listing_removed_from_space now carries the prior status and a context of reject, takedown or withdraw, and wb_listora_listing_rejected_in_space fires for a declined submission. The first three arguments are unchanged.
* Dev      - GET /spaces/{id}/listings/pending takes page and per_page and sends X-WP-Total and X-WP-TotalPages.
* Dev      - wb_listora_listing_report_recipients filters who is emailed about a reported listing, wb_listora_listing_report_notify_interval how often repeat reports notify, and wb_listora_max_stored_listing_reports caps what is stored per listing.
* Dev      - wb_listora_listing_owner_name and wb_listora_listing_owner_url filter the public owner name and the page it links to; return an empty URL to render the name unlinked, or a member-profile URL to point it at a community profile.
* Dev      - wb_listora_member_listing_statuses filters the post statuses a member sees on their own dashboard, on the website and in the app together.
* Dev      - GET /listings/{id}/detail carries owner: { name, url }; the key is absent when Show Who Listed It is off. GET /dashboard/listings accepts listing_type.
* Dev      - Map blocks gain a Use site setting / On / Off clustering choice. Return true from wb_listora_map_block_clustering to cluster every map that has not chosen, as before.
* Dev      - wb_listora_rest_prepare_dashboard_stats now also runs on cached responses, so fields a listener adds are not lost on repeat requests. Moderating a review in wp-admin now fires the same review hooks as the REST API.
* Dev      - wb_listora_submission_steps and wb_listora_submission_plan_step now receive the ID of the listing being edited (0 for a new one).
* Dev      - wb_listora_rate_limit_ip_counts_members restores per-IP limits for signed-in members; return true.
* Dev      - Any form with data-listora-confirm-form and a data-confirm-message now asks for confirmation with the Listora modal before it submits, when the listora-confirm script is loaded.
* Compat   - Aligned with WB Listora Pro 1.8.0. Install both updates together.

= 1.7.0 - September 2026 =

Every price, credit figure and map now follows the site's own settings instead of a value baked into the code, and a switched-off feature stops advertising itself.

* Fix      - The Upgrade and account links in the admin now point at pages that exist. Three of them led to 404s on the store.
* Improve  - The Submission form style setting now says that adding or editing from inside the member dashboard always uses the single page form. The setting only governs the standalone Add Listing page, and nothing said so - so it looked broken to anyone testing it from the dashboard.
* Fix      - Changing the listing type in search now narrows the Features checkboxes to the ones that type allows, instead of leaving the previous type's list on screen. Any feature that no longer applies is unticked rather than left filtering results down to nothing.
* Dev      - Added wb_listora_get_feature_allowlist_map(), used by both the submission form and the search filters.
* Fix      - Submitting a listing with a feature its listing type does not allow is now refused with a clear error, naming the field. It used to return success while quietly discarding those features, so a member could not tell that part of what they chose had been thrown away.
* Dev      - wb_listora_refuse_disallowed_features restores the previous drop-and-accept behaviour for a site that prefers it.
* Fix      - Turning off Listing Submission now hides every invitation to submit, not just the dashboard button. The empty state still offered Add Your First Listing, and the form it led to then explained submissions were closed.
* Fix      - The dashboard header no longer leaves an empty bar when there is nothing to put in it.
* Dev      - Guardrail G18 fails the build on a frontend submission CTA that does not check the feature toggle.
* Fix      - Credit pack prices now show in your currency like every other price. The credits screen could show one currency while the rest of the site showed another.
* New      - Site Health warns you when WooCommerce is set to a different currency from Listora, since a member would then see one currency and be charged in another. Listora shows one currency everywhere rather than mixing them, so this is the place it can be spotted.
* Fix      - Your currency setting is now used everywhere a price appears. Prices entered before you changed currency kept showing the old symbol, because the code stored on the row outranked your setting - so a site switched to yen still showed dollars on older listings.
* Fix      - Search engines are told the same currency your visitors see. The structured data read WooCommerce's currency instead of yours, so a site set to yen displayed one currency and reported another - and invented one entirely on sites with no WooCommerce.
* Dev      - Guardrail G16 fails the build on a hardcoded map tile URL, G17 on a price rendered with a per-row or hardcoded currency.
* Fix      - The map on a listing now uses the map tile source you set in Settings. It ignored that setting entirely and always drew OpenStreetMap tiles, so a site that had configured its own tile server saw its choice applied to the submission map picker and nowhere else.
* New      - Site Health tells you when your maps have no tile source and will therefore draw on a blank background, with a link to the setting. It stays quiet on a site whose listings have no locations, since there is nothing to draw.
* New      - Listora now tells you when a page it created is not linked from any menu, and offers to add it. A page can be published, mapped and working while no visitor can reach it, and every admin screen reported success - so the one thing missing was the one thing nobody was told about.
* Improve  - It offers only pages people navigate to. Compare Listings and Buy Credits are reached from a button on a listing or a plan, so they are never suggested for a menu.
* Improve  - It adds to the menus you already use for Listora pages, which on themes with separate logged-in and logged-out menus means both - not just the one members see. A page you have deliberately placed in only one menu is left alone.
* Dev      - Documented what contract_version promises: it versions the shape of /settings/app-config and nothing else, not the values a field returns. See docs/REST-API.md.
* Fix      - A page whose feature is switched off no longer serves visitors a blank page. It returns a proper 404 instead, and Settings > Pages marks the row Feature off with what to do about it. Turning the feature back on restores the page exactly as it was.
* Fix      - Admin notices on Listora screens are visible again. The pages had no heading for WordPress to place notices after, so they were being dropped into a hidden tab panel - present on the page, readable by nobody.
* Fix      - The Settings screen no longer draws its header twice.
* Improve  - Listora admin screens now have a proper page heading, so screen readers announce where you are.
* Dev      - Added the is_available page-registration key, wb_listora_hide_unavailable_pages, and Page_Registry::key_for_page().
* Fix      - Links to a Listora page are no longer dropped because the page was edited. Whether a link could be shown was worked out in four different places, and one of them required the page to still contain the original block - so rebuilding your Compare page with your own layout removed the Compare link from the site.
* Fix      - No Listora link now points at a draft page a visitor cannot open.
* Improve  - A Compare page built with the old [listora_compare] shortcode is recognised as your Compare page, so the plugin does not offer to create a second one beside it.
* Dev      - Added wb_listora_get_public_page_url() and the default_shortcode registration key.
* Fix      - Editing one of the plugin's pages no longer creates a duplicate. Replacing the block on the Buy Credits page with your own layout made the plugin decide the page was not its own, create a second one at buy-credits-2, and point every Buy Credits link at the empty new page while the page you wrote sat orphaned.
* Improve  - A Listora page is created once per site and never re-created behind your back, so a page you delete stays deleted.
* New      - Settings > General > Pages offers Create page on any row reading Missing, so a deleted page can be brought back without hand-building one with the right block in it.
* Dev      - Added wb_listora_ensure_page() and wb_listora_create_page() as the single page-creation path, and the wb_listora_page_created action.
* Fix      - Deleting one of the plugin's own pages no longer breaks the links that pointed at it. The page mapping kept the deleted page's identifier, so links to it rendered empty and buttons quietly fell back to whatever other address they knew. The mapping now re-attaches to a page carrying the same block, exactly as it already did when the mapping was missing altogether.
* Fix      - After paying for credits, the order confirmation now links straight back to the listing you were part-way through. The link was lost in the checkout redirect, so members finished paying and were left on a receipt whose only listing link started a new one.
* Fix      - The Add Listing block now has a Form Layout control in the editor. The setting existed and was honoured, but was never shown, so a block saved as a wizard or single form could not be changed back and a site owner who altered Settings > Submissions saw nothing happen with no way to find out why.
* Fix      - A listing type restricted to certain Features & Amenities now shows only those on the Add Listing form. Every feature on the site was offered whatever the type, so the setting had no visible effect.
* Security - Features outside a listing type's allowlist are refused when a listing is saved. Only the form limited the choice, so a direct API call could attach any feature to any type.
* Improve  - A web address on its own is no longer accepted as a listing title. It became the business name on the card, the page and the permalink, which is what automated spam posting looks like. Names that contain a domain, such as Booking.com, are unaffected.
* Fix      - Validating a Pro licence from the upgrade screen works again. It was checking a licence server on a retired domain, so every key came back as "could not be validated" - which reads as a bad key rather than a broken check. It now asks the Wbcom store, where the licence actually lives.
* Fix      - Plugin links, documentation links and the author link point at wbcomdesigns.com. Several went to a domain that no longer resolves, and a few others to store pages that had moved.
* Security - Files uploaded to prove a business claim are stored under an unguessable name, and the claimant is no longer handed a direct link to their own upload. The file kept the name it was given, so an address like /uploads/2026/08/drivers-licence-scan.png could simply be guessed.
* Security - The companion-plugin installer only downloads from the Wbcom store over HTTPS. It previously installed whatever download address the store replied with, so a spoofed or compromised reply could have installed other code.
* Security - Changing the email address on an account now needs the current password, and the new address has to confirm before anything moves. It used to change immediately, so a stolen session or an application password was enough to take the address and then reset the password.
* Security - A member without publishing rights can no longer put a listing live by asking for the expired status. It skipped moderation, the terms gate and the duplicate check, and the page was readable by anyone with the link.
* Security - Photos and files can only be attached to a listing or service by the member who uploaded them. Media IDs were accepted on trust, so any file in the library could be attached to someone else's listing and its address published.
* Security - Services switched off by their owner are no longer readable by the public. Asking for them by status, or by their own address, returned the title and price of a service the owner had deliberately hidden.
* Security - Credits are no longer granted for an order nobody has paid for. Cash on Delivery, cheque and bank-transfer orders move to processing while still unpaid, and the mapped credit pack was credited at that moment - a buyer could order, receive credits, spend them and never pay. Credits now wait until the payment is recorded, including when a shop marks a cash order completed later.
* New      - Listings on a plan that renews itself now do so from the member's credit balance instead of expiring. If the balance will not cover it the listing pauses rather than expiring, and comes back on its own the moment they top up.
* Dev      - New `wb_listora_should_expire_listing` filter lets an extension keep a listing alive at the moment the expiry sweep would retire it, and `wb_listora_renew_listing()` runs the ordinary renewal from code that has no REST request to hand.
* New      - Going to buy credits from the listing form no longer loses the listing. It is saved as a draft first, and the credits screen offers a link straight back to it, opened on the plan you were choosing.
* Fix      - Autosave and Save Draft work throughout the form. Both were rejected until the Terms of Service box on the final step was ticked, and the failure was never shown, so nothing was saved while you were still typing.
* Fix      - Every autosave now updates the same draft. Only the first one was stored; later ones were turned away as duplicates while the form still reported "Draft saved".
* Fix      - Editing a listing no longer demands the Terms of Service box be ticked again. A saved draft still requires acceptance before it goes live, and that acceptance is now recorded against the listing.
* Fix      - A draft saved before the Terms of Service step no longer records an acceptance that was never given, which had pre-ticked the box on the way to publishing.
* Improve  - The Buy Credits link on a plan you cannot afford is a full-size tap target on phones, rather than a line of text under half the usual size.
* Improve  - Service prices follow the site currency everywhere they appear, so a directory trading in yen or euro no longer shows dollar amounts on listing pages and the member dashboard.
* Improve  - Zero-decimal currencies such as JPY render without decimal places, and whole amounts drop the trailing zeros.
* Improve  - The submission map picker draws the tile source configured in Settings > Maps, matching the directory map instead of always loading OpenStreetMap.
* Improve  - A directory with no tile source configured shows an unstyled picker rather than quietly loading a third party's tiles; panning, zooming and dropping a pin still work.
* Fix      - Members who buy credits through a mapped WooCommerce, MemberPress or Paid Memberships Pro product can see their Credits tab and balance again.
* Fix      - A directory selling credits only through a mapped product no longer tells members that credits are not on sale.
* Fix      - The Credit Transactions screen shows credit counts, so a 50-credit purchase reads as 50 rather than 5000.
* Fix      - The Credits Issued and Credits Used totals report credits instead of raw ledger values.
* Fix      - The Item ID column shows a dash on top-up rows instead of a meaningless zero; the purchase reference remains in the Note column.
* Fix      - The refund dialog lays out as a centred panel with its fields stacked and aligned.
* Fix      - Total Revenue follows the site currency instead of always showing a dollar sign.
* Fix      - Turning the Listing Submission feature off hides the dashboard Add Listing button, and the Add Listing page explains that new listings are closed rather than rendering an empty screen.
* Dev      - New `wb_listora_credit_purchase_paths()` reports which credit purchase routes are live, replacing three separate answers that could disagree with each other.
* Dev      - New `wb_listora_credit_purchase_paths` filter lets an extension declare its own purchase route.
* Compat   - The bundled Wbcom Credits SDK is updated to 1.6.0, bringing gateway and adapter fixes that a forked copy had been holding back.
* Fix      - The directory's no-results empty state could still offer an Add a listing button after submissions were turned off, leading to a page that said new listings were closed. It is now hidden along with every other submission invitation.
* Fix      - Turning off new submissions no longer blocks editing an existing listing. The page used to show "New listings are closed" before checking whether you were editing, so an owner following an Edit link on their own listing saw that message instead of their listing.
* Fix      - Uploading photos in the submission form works again on WooCommerce sites. WooCommerce redirects members without publishing rights away from wp-admin, and the wizard's photo picker posted through wp-admin, so gallery and custom-image uploads had no working path for exactly the members submitting listings.
* Improve  - Photos uploaded through the submission form are now attached to the listing they belong to, so they appear under it in the Media Library instead of the Unattached pile.
* Fix      - Permanently deleting a listing now deletes its featured image and gallery photos with it, instead of leaving every uploaded file behind on disk. An image still used by another listing is left alone.
* Fix      - Uninstalling the plugin now removes listing images along with everything else, instead of leaving gallery photos behind on any listing created before 1.7.0.
* Dev      - New `wb_listora_delete_listing_media` filter turns off the image cleanup above for an owner who wants images kept; `wb_listora_listing_media_deleted` action fires with what was removed.
* Fix      - Buying credits directly with Stripe or PayPal (outside a WooCommerce, MemberPress, or Paid Memberships Pro product) now completes. Direct checkout was missing a required pricing configuration, so every attempt failed before reaching the payment gateway.
* Fix      - If the payment confirmation from the gateway is slow or does not arrive, credits now still land when you return to the site after paying, instead of never being added.
* Fix      - The credits screen no longer tells you credits have been added before they actually have. It now says payment was received while crediting is in progress, and confirms once your balance updates.
* Fix      - Settings and Credits screens no longer show misaligned field columns, labels sitting flush against the card edge, or fields spilling outside the card padding, wherever in the page a section renders.
* Fix      - Currency and product dropdowns in the admin no longer clip the bottom of their text.
* Fix      - The Save/Reset bar on long settings screens, such as Credits, now stays visible as you scroll instead of being left far up the page.
* Fix      - Admin buttons and links share one consistent style across the whole plugin section, instead of some picking up the active theme's button styling.
* Dev      - Admin RTL stylesheets are now generated at build time from their LTR source instead of hand-maintained, so they can no longer drift out of sync.
* Dev      - Deleting one of the plugin's pages now clears its page-mapping option immediately rather than leaving it pointing at a deleted page until next read. New `wb_listora_page_mapping_forgotten` action.

= 1.6.0 - August 2026 =

Makes the interactions the interface already advertised actually work, gives automation and webhooks a published contract, and enforces Terms of Service acceptance on the submission API.

* New      - Listing photos display as a carousel, so every image stays reachable without scrolling back to the thumbnails.
* New      - The featured image box accepts a dragged file, which its label has always offered.
* New      - Members can set amenities when submitting or editing a listing, instead of only administrators from wp-admin.
* New      - Tags are a discovery dimension: they filter search, appear as a facet, and render as clickable chips on cards and listing pages.
* New      - A listing's video is rendered on the listing page rather than only stored.
* New      - Services can be added, edited and deleted from the member dashboard.
* New      - Automation and webhook subscribers can discover every available trigger from a published registry, with a JSON schema per event.
* New      - The submission API accepts a complete `categories` list for clients that manage the full set.
* New      - Features can be limited to specific listing types, so a Jobs page stops offering classified-ad amenities.
* Improve  - An icon chosen in the admin picker is always one the front end can draw, so a selected icon can no longer vanish.
* Improve  - Search filters and the submission form offer only the categories and features the selected listing type allows.
* Improve  - Every endpoint returns the same `featured_image` shape, so a client no longer has to handle three variants.
* Improve  - Every human-facing string leaves the API decoded, so ampersands and apostrophes render as typed.
* Improve  - Review criteria saved against a listing type are the criteria the review form and averages use.
* Improve  - Related Listings can be extended from a child theme through dedicated hooks.
* Improve  - Health Check reports whether the search index is actually populated and indexed, not merely that its table exists.
* Improve  - The dashboard checklist now guides owners through monetization setup in the order it has to happen.
* Improve  - Terms of service is mapped once, by choosing the page your site already has, and every surface links to it.
* Fix      - Editing a listing no longer deletes the categories the single-select form cannot display.
* Fix      - Saving a new listing type, and resetting settings, now confirm they worked instead of reloading silently.
* Fix      - Each member dashboard tab now titles the page after itself, instead of every tab reading "My Listings".
* Fix      - Upgrading records the map tile source it was already using, so existing maps keep rendering and the source is now visible and editable.
* Fix      - A map tile URL keeps its {z}/{x}/{y} placeholders when saved, so tiles load instead of every request returning 404.
* Fix      - The setup wizard asks for a map tile server, so a fresh install is not left with a map it cannot draw.
* Fix      - A site using Google Maps shows the map again, instead of an "unavailable" notice asking for a tile source Google never uses.
* Fix      - The "Open Map settings" link, and the dashboard checklist's map item, land on the Map tab instead of falling back to General.
* Fix      - Editing a service shows the category it is filed under, instead of an empty dropdown.
* Fix      - Preview Your Listing lists the features you ticked by name, instead of their numeric term IDs.
* Fix      - Service Categories has an admin screen, so the category dropdown on the member service form can actually be filled.
* Fix      - Preview Your Listing groups the features you ticked into one row, instead of repeating the heading for each one.
* Fix      - The listing header address no longer repeats the city and state, and no longer drops the postal code.
* Fix      - Brand-coloured text meets AA contrast whatever accent colour the site or its theme uses.
* Fix      - Credits status now reads the same on every screen, so members are no longer told to contact an administrator on a site that can take their payment.
* Fix      - A listing whose category sits outside its type's allowed list can be edited and saved again.
* Fix      - A search cache lifetime of 0 disables caching, rather than caching permanently.
* Fix      - Rebuild Search Index now rebuilds the index instead of reloading the page.
* Fix      - The map picker renders and accepts a dragged marker on the wp-admin listing editor.
* Fix      - The favourite heart on the dashboard sits over the card image and responds to clicks.
* Fix      - Approving or rejecting a claim from wp-admin now notifies the same listeners the API path does.
* Fix      - Category names containing an ampersand display correctly in the submission form.
* Fix      - A site missing a canonical page no longer white-screens after an update.
* Fix      - A review held for approval now says so and keeps saying so, instead of the confirmation being wiped by a page reload that had nothing to show.
* Fix      - Automation trigger labels translate again, and no longer fill the debug log with a notice on every page load.
* Security - Submitting a listing now requires a valid listing type, so listings can no longer be created without one.
* Security - Terms of Service acceptance is enforced on `POST /submit`, which previously accepted a submission with no consent recorded. Clients that cannot send `agree_terms`, including sites that hide the checkbox, opt out with `add_filter( 'wb_listora_require_terms_acceptance', '__return_false' )`.
* Dev      - New helper `wb_listora_directory_is_operational()` reports whether an install is a working directory, wizard walked or not.
* Dev      - New public helpers: `wb_listora_render_icon()`, `wb_listora_get_icon_choices()`, `wb_listora_get_review_criteria()` and `wb_listora_decode_text()`.
* Dev      - New public helper `wb_listora_sanitize_tile_url()` sanitizes a tile template without stripping the placeholders Leaflet substitutes.
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

* Fix      - Submitting a listing now requires an account. Anonymous guest submission (and its email-verification step) has been removed: guests could never upload media, so the flow always dead-ended. The former block "Require Login" control was removed to match, and the inert enable_guest_submission flag no longer appears in the app-config REST response.
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
