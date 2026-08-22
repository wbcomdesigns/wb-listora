# Changelog

All notable changes to WB Listora will be documented in this file.

## [1.7.0] - Unreleased

Every price, credit figure and map now follows the site's own settings instead of a value baked into the code, and a switched-off feature stops advertising itself.

- Fix      - Credit pack prices now show in your currency like every other price. The credits screen could show one currency while the rest of the site showed another.
- New      - Site Health warns you when WooCommerce is set to a different currency from Listora, since a member would then see one currency and be charged in another. Listora shows one currency everywhere rather than mixing them, so this is the place it can be spotted.
- Fix      - Your currency setting is now used everywhere a price appears. Prices entered before you changed currency kept showing the old symbol, because the code stored on the row outranked your setting - so a site switched to yen still showed dollars on older listings.
- Fix      - Search engines are told the same currency your visitors see. The structured data read WooCommerce's currency instead of yours, so a site set to yen displayed one currency and reported another - and invented one entirely on sites with no WooCommerce.
- Dev      - Guardrail G16 fails the build on a hardcoded map tile URL, G17 on a price rendered with a per-row or hardcoded currency.
- Fix      - The map on a listing now uses the map tile source you set in Settings. It ignored that setting entirely and always drew OpenStreetMap tiles, so a site that had configured its own tile server saw its choice applied to the submission map picker and nowhere else.
- New      - Site Health tells you when your maps have no tile source and will therefore draw on a blank background, with a link to the setting. It stays quiet on a site whose listings have no locations, since there is nothing to draw.
- New      - Listora now tells you when a page it created is not linked from any menu, and offers to add it. A page can be published, mapped and working while no visitor can reach it, and every admin screen reported success - so the one thing missing was the one thing nobody was told about.
- Improve  - It offers only pages people navigate to. Compare Listings and Buy Credits are reached from a button on a listing or a plan, so they are never suggested for a menu.
- Improve  - It adds to the menus you already use for Listora pages, which on themes with separate logged-in and logged-out menus means both - not just the one members see. A page you have deliberately placed in only one menu is left alone.
- Dev      - Documented what contract_version promises: it versions the shape of /settings/app-config and nothing else, not the values a field returns. See docs/REST-API.md.
- Fix      - A page whose feature is switched off no longer serves visitors a blank page. It returns a proper 404 instead, and Settings > Pages marks the row Feature off with what to do about it. Turning the feature back on restores the page exactly as it was.
- Fix      - Admin notices on Listora screens are visible again. The pages had no heading for WordPress to place notices after, so they were being dropped into a hidden tab panel - present on the page, readable by nobody.
- Fix      - The Settings screen no longer draws its header twice.
- Improve  - Listora admin screens now have a proper page heading, so screen readers announce where you are.
- Dev      - Added the is_available page-registration key, wb_listora_hide_unavailable_pages, and Page_Registry::key_for_page().
- Fix      - Links to a Listora page are no longer dropped because the page was edited. Whether a link could be shown was worked out in four different places, and one of them required the page to still contain the original block - so rebuilding your Compare page with your own layout removed the Compare link from the site.
- Fix      - No Listora link now points at a draft page a visitor cannot open.
- Improve  - A Compare page built with the old [listora_compare] shortcode is recognised as your Compare page, so the plugin does not offer to create a second one beside it.
- Dev      - Added wb_listora_get_public_page_url() and the default_shortcode registration key.
- Fix      - Editing one of the plugin's pages no longer creates a duplicate. Replacing the block on the Buy Credits page with your own layout made the plugin decide the page was not its own, create a second one at buy-credits-2, and point every Buy Credits link at the empty new page while the page you wrote sat orphaned.
- Improve  - A Listora page is created once per site and never re-created behind your back, so a page you delete stays deleted.
- New      - Settings > General > Pages offers Create page on any row reading Missing, so a deleted page can be brought back without hand-building one with the right block in it.
- Dev      - Added wb_listora_ensure_page() and wb_listora_create_page() as the single page-creation path, and the wb_listora_page_created action.
- Fix      - Deleting one of the plugin's own pages no longer breaks the links that pointed at it. The page mapping kept the deleted page's identifier, so links to it rendered empty and buttons quietly fell back to whatever other address they knew. The mapping now re-attaches to a page carrying the same block, exactly as it already did when the mapping was missing altogether.
- Fix      - After paying for credits, the order confirmation now links straight back to the listing you were part-way through. The link was lost in the checkout redirect, so members finished paying and were left on a receipt whose only listing link started a new one.
- Fix      - The Add Listing block now has a Form Layout control in the editor. The setting existed and was honoured, but was never shown, so a block saved as a wizard or single form could not be changed back and a site owner who altered Settings > Submissions saw nothing happen with no way to find out why.
- Fix      - A listing type restricted to certain Features & Amenities now shows only those on the Add Listing form. Every feature on the site was offered whatever the type, so the setting had no visible effect.
- Security  - Features outside a listing type's allowlist are refused when a listing is saved. Only the form limited the choice, so a direct API call could attach any feature to any type.
- Improve  - A web address on its own is no longer accepted as a listing title. It became the business name on the card, the page and the permalink, which is what automated spam posting looks like. Names that contain a domain, such as Booking.com, are unaffected.
- Fix      - Validating a Pro licence from the upgrade screen works again. It was checking a licence server on a retired domain, so every key came back as "could not be validated" - which reads as a bad key rather than a broken check. It now asks the Wbcom store, where the licence actually lives.
- Fix      - Plugin links, documentation links and the author link point at wbcomdesigns.com. Several went to a domain that no longer resolves, and a few others to store pages that had moved.
- Security  - Files uploaded to prove a business claim are stored under an unguessable name, and the claimant is no longer handed a direct link to their own upload. The file kept the name it was given, so an address like /uploads/2026/08/drivers-licence-scan.png could simply be guessed.
- Security  - The companion-plugin installer only downloads from the Wbcom store over HTTPS. It previously installed whatever download address the store replied with, so a spoofed or compromised reply could have installed other code.
- Security  - Changing the email address on an account now needs the current password, and the new address has to confirm before anything moves. It used to change immediately, so a stolen session or an application password was enough to take the address and then reset the password.
- Security  - A member without publishing rights can no longer put a listing live by asking for the expired status. It skipped moderation, the terms gate and the duplicate check, and the page was readable by anyone with the link.
- Security  - Photos and files can only be attached to a listing or service by the member who uploaded them. Media IDs were accepted on trust, so any file in the library could be attached to someone else's listing and its address published.
- Security  - Services switched off by their owner are no longer readable by the public. Asking for them by status, or by their own address, returned the title and price of a service the owner had deliberately hidden.
- Security  - Credits are no longer granted for an order nobody has paid for. Cash on Delivery, cheque and bank-transfer orders move to processing while still unpaid, and the mapped credit pack was credited at that moment - a buyer could order, receive credits, spend them and never pay. Credits now wait until the payment is recorded, including when a shop marks a cash order completed later.
- New      - Listings on a plan that renews itself now do so from the member's credit balance instead of expiring. If the balance will not cover it the listing pauses rather than expiring, and comes back on its own the moment they top up.
- Dev      - New `wb_listora_should_expire_listing` filter lets an extension keep a listing alive at the moment the expiry sweep would retire it, and `wb_listora_renew_listing()` runs the ordinary renewal from code that has no REST request to hand.
- New      - Going to buy credits from the listing form no longer loses the listing. It is saved as a draft first, and the credits screen offers a link straight back to it, opened on the plan you were choosing.
- Fix      - Autosave and Save Draft work throughout the form. Both were rejected until the Terms of Service box on the final step was ticked, and the failure was never shown, so nothing was saved while you were still typing.
- Fix      - Every autosave now updates the same draft. Only the first one was stored; later ones were turned away as duplicates while the form still reported "Draft saved".
- Fix      - Editing a listing no longer demands the Terms of Service box be ticked again. A saved draft still requires acceptance before it goes live, and that acceptance is now recorded against the listing.
- Fix      - A draft saved before the Terms of Service step no longer records an acceptance that was never given, which had pre-ticked the box on the way to publishing.
- Improve  - The Buy Credits link on a plan you cannot afford is a full-size tap target on phones, rather than a line of text under half the usual size.
- Improve  - Service prices follow the site currency everywhere they appear, so a directory trading in yen or euro no longer shows dollar amounts on listing pages and the member dashboard.
- Improve  - Zero-decimal currencies such as JPY render without decimal places, and whole amounts drop the trailing zeros.
- Improve  - The submission map picker draws the tile source configured in Settings > Maps, matching the directory map instead of always loading OpenStreetMap.
- Improve  - A directory with no tile source configured shows an unstyled picker rather than quietly loading a third party's tiles; panning, zooming and dropping a pin still work.
- Fix      - Members who buy credits through a mapped WooCommerce, MemberPress or Paid Memberships Pro product can see their Credits tab and balance again.
- Fix      - A directory selling credits only through a mapped product no longer tells members that credits are not on sale.
- Fix      - The Credit Transactions screen shows credit counts, so a 50-credit purchase reads as 50 rather than 5000.
- Fix      - The Credits Issued and Credits Used totals report credits instead of raw ledger values.
- Fix      - The Item ID column shows a dash on top-up rows instead of a meaningless zero; the purchase reference remains in the Note column.
- Fix      - The refund dialog lays out as a centred panel with its fields stacked and aligned.
- Fix      - Total Revenue follows the site currency instead of always showing a dollar sign.
- Fix      - Turning the Listing Submission feature off hides the dashboard Add Listing button, and the Add Listing page explains that new listings are closed rather than rendering an empty screen.
- Dev      - New `wb_listora_credit_purchase_paths()` reports which credit purchase routes are live, replacing three separate answers that could disagree with each other.
- Dev      - New `wb_listora_credit_purchase_paths` filter lets an extension declare its own purchase route.
- Compat   - The bundled Wbcom Credits SDK is updated to 1.6.0, bringing gateway and adapter fixes that a forked copy had been holding back.

## [1.6.0] - 2026-08-18

Makes the interactions the interface already advertised actually work, gives automation and webhooks a published contract, and enforces Terms of Service acceptance on the submission API. Ships in lockstep with WB Listora Pro 1.6.0.

`WB_LISTORA_DB_VERSION` moves to 1.6.0 and the migration runs on activation.

### Added
- `wb_listora_onboarding_checklist` filter opens the dashboard setup checklist to extensions, with the returned items shape-guarded so a third-party entry missing a key cannot fatal the dashboard. Pro uses it to add the monetization path.
- `wb_listora_directory_is_operational()` reports whether an install is a working directory regardless of whether the setup wizard was walked. Free's onboarding notice used this judgement privately; Pro's setup banner needed the same answer and had no way to ask.
- Listing photos render as a carousel with arrows, dots and a thumbnail strip, all driven by one handler so they cannot disagree.
- The featured-image zone accepts a dragged file. Uploads go through `POST /wp/v2/media` and share the media modal's commit step, so a dropped image and a picked image land in identical state and obey the same size cap.
- Members can assign amenities from the frontend submission and dashboard edit forms; the write path preserves amenities an administrator set from wp-admin.
- Tags filter search, are returned as a facet, and render as chips linking to a filtered directory.
- A listing's video is embedded on the listing page through `wp_oembed_get()`, so every provider WordPress supports works.
- Service create, edit and delete from the member dashboard.
- A published automation trigger registry with a versioned JSON schema per event, so subscribers can discover what exists instead of reading source.
- `categories` on `POST /submit` for clients that own the complete category set.
- `wb_listora_before_related_listings` and `wb_listora_after_related_listings`, both receiving the related query so a child theme can inspect what is about to render.
- `wb_listora_decode_text()`, the single decoding rule for human-facing API strings.
- `wb_listora_sanitize_tile_url()`, the single sanitizer for a map tile template, shared by the settings screen and the settings-import endpoint.
- Per-listing-type feature allowlists. Features were global, so a site running Jobs and Classifieds on separate pages offered classified amenities on the Jobs filter and vice versa (BC 10213603029). A type now carries `_listora_allowed_features` alongside its existing category allowlist, editable in the Type Editor. Empty means no restriction, so every existing type keeps offering every feature until an owner narrows it.
- `wb_listora_get_terms_for_listing_type( $taxonomy, $type_slug, $args )`, the one place that resolves which category or feature terms a listing type offers. The search filters and the submission form both read it, so the two cannot drift (BC 10213705391).
- `\WBListora\Core\Image_Schema`, the single builder for every `featured_image` payload.
- `\WBListora\Core\Cache::ttl()`, which resolves a cache-lifetime setting and returns 0 for "disabled".

### Changed
- Terms of Service is mapped ONCE. Settings gains a page picker (choose the terms page the site already has - no page is ever created, and no ID to look up), with an external URL field for sites whose terms live elsewhere. The submission block's `Terms Page ID` control is gone and its `termsPageId` attribute is deprecated but still honoured. Previously the link was configured in two unconnected places, so mapping one left the other surface - form or mobile app - with no link, and mapping both meant doing the same job twice in two formats. New `wb_listora_get_terms_url()` resolves it for every surface.
- Health Check verifies the search index is USABLE, not merely that its table exists. An empty-but-present index is reachable in normal operation and makes search return nothing while every card stays green; a missing FULLTEXT index degrades keyword search the same silent way. Empty-with-listings fails, missing FULLTEXT and a large shortfall warn, and both offer the Rebuild Search Index control. Zero published listings with an empty index still passes.
- The admin icon picker offers exactly the icons the front-end renderer can draw. It previously enumerated the full Lucide set while the renderer knew a fraction of it, so most selectable icons rendered as nothing.
- `featured_image` is one shape on `/search`, `/detail` and `/related`. The published set is the union of what the three previously returned, so no client loses a field; a missing size is always a string and a listing with no image is always `null`.
- Every human-facing string leaves REST decoded. Whether a value arrived decoded previously depended on which line of PHP built it.
- Review criteria saved against a listing type are read by the review form, the detail tabs and the averages.

### Fixed
- A review awaiting approval keeps its confirmation. The form reported success and then reloaded 2s later; a pending review is not rendered in the list, so the reload destroyed the only signal and showed nothing in its place. Approved reviews still reload, where the list is the confirmation.
- Owner and member screens describe the same site identically. Every credits surface used to answer "can a member buy?" from different inputs: the dashboard tested for a direct payment gateway, the Buy Credits block tested whether packs resolve to a checkout URL. A pack sold as an external product satisfies the second and not the first, so the dashboard told members to contact the administrator on sites that were ready to take their money. New `wb_listora_get_monetization_status()` publishes one question (disabled / no packs / needs gateway / ready) that Pro answers; every surface reads it, and owner and member wording are separated so a member is never asked to do the owner's job.
- Brand-coloured text meets the 4.5:1 AA floor whatever accent the site uses. A brand colour is picked to stand out on a surface, not to be read as 11px text on one, and BuddyX's default #ee4036 measured 3.62:1 on white and worse on tinted washes — so secondary buttons, type badges, active tabs and counts failed on every site that never changed the theme accent. Small brand text now resolves through the new `--listora-primary-text`; backgrounds and borders keep the true brand. `--listora-fg-muted` is also no longer bridged to a theme's decorative muted colour. White text on a brand BACKGROUND is deliberately unchanged so Listora's buttons keep matching the theme's; see docs/architecture/CSS-ARCHITECTURE.md for the one-line opt-in.
- The listing header address read "247 West Broadway, Manhattan, NY 10013, Manhattan, NY" — the stored `address` is often already a formatted line containing the city and state, which were then appended again. `wb_listora_format_address_line()` now skips components already present in the street line, and appends `postal_code`, which it never considered: a site storing a bare street plus separate parts had the code stored and never rendered on any listing header.
- Every member-dashboard tab titles the page after itself. The dashboard is one page — usually called "My Listings" — so Credits, Profile, Reviews and Claims all announced themselves as My Listings in the browser tab, the history entry, the bookmark and to a screen reader. Fixed server-side via `document_title_parts`, because the tabs navigate rather than toggle; a JS-only fix passes a click-through and fails every direct load. Labels come from the new `wb_listora_get_dashboard_tab_labels()` map that the sidebar renders from, so the two cannot drift.
- Upgrading writes the previously-implicit OpenStreetMap tile source into `map_tile_url`. Removing the hardcoded fallback was right — a product should not silently lean on a third party's infrastructure at volumes their usage policy forbids — but removing it alone would have blanked every existing map on upgrade. The migration preserves the behaviour and makes it visible and editable instead; fresh installs still start blank.
- A tile URL keeps its `{z}`/`{x}`/`{y}` placeholders when saved. The settings screen sanitized the field with `esc_url_raw()`, and the comment above it asserted that this preserved the placeholders; it does not. Curly braces are not legal URL characters, so every save rewrote `https://tiles.example.com/{z}/{x}/{y}.png` to `https://tiles.example.com/z/x/y.png` and Leaflet then requested that literal path for every tile. The value looked saved, the map looked blank, and nothing reported an error. The new `wb_listora_sanitize_tile_url()` percent-encodes the braces, lets `esc_url_raw()` reject anything that is not http(s), and restores them — so a hostile scheme is still refused. `POST /settings/import` writes the same key and now shares the helper rather than keeping its own rule.
- The setup wizard collects a map tile server. It offered "OpenStreetMap (Free) - works immediately", wrote only `map_provider`, and left `map_tile_url` empty, so a fresh install finished setup and met "The map is not available" on the directory page. The step now asks for the tile URL and attribution, and says plainly why no default ships. Leaving it blank is still a valid answer and still writes nothing: a fresh install must not serve OpenStreetMap's public tiles by default, which their usage policy forbids at product scale and which the mobile app would ship as its tile source.
- A site running Google Maps renders its map again. 1.6.0 added a guard that shows "The map is not available" when no raster tile URL is set, but Google is not a raster-tile provider: it draws its own basemap and Pro's Google_Maps sets `tileUrl` to `''` on purpose. Every Pro site on Google Maps therefore lost its directory map and was told to configure a tile source Google would never request. The guard now reads the provider from the filtered map config -- which Pro sets only once Google is genuinely live -- so Google renders, and a Free site that picks Google without the add-on still gets the notice.
- The two affordances that offer to fix an unconfigured map now arrive somewhere useful. Both the block's "Open Map settings" link and the dashboard checklist's map item pointed at `tab=map`, but the registered slug is `maps`, so the settings screen silently fell back to General and the owner never saw a map setting.
- Editing a service loads the category it is filed under. The category `<select>` carries no `data-wp-bind--value`, so it is an uncontrolled element that only `editService` can populate, and it set the other five fields but not this one -- the member edited blind, with the dropdown reading "Select a category" however the service was actually filed. The stored category was never at risk: `saveService` omits `categories` when the select is empty and the route writes only the fields it was sent, so saving from that state preserved the assignment (verified against the live route). A display defect, not the data loss it looked like.
- Preview Your Listing names the features you ticked. Feature checkboxes carry the term id as their `value` and the feature name only as adjacent text, and the preview read `value`, so a member reviewing their listing saw "Features & Amenities: 193" and could not tell what they had selected. A checkbox in an ARRAY field now previews its own visible label, which covers custom checkbox-group fields too; a standalone yes/no checkbox keeps its tick, so a boolean does not render as "Closed: Closed".
- Service Categories is manageable from wp-admin. The `listora_service_cat` taxonomy was registered and the member dashboard's service form rendered a select from it, but no screen was ever registered to create the terms -- so the dropdown was permanently empty, the documentation pointed at a Listora -> Service Categories menu that did not exist, and an owner had no way to fill it without WP-CLI. Registered alongside the other content taxonomies, in the menu, the submenu-highlight map and the menu order (BC 10217677159).
- Preview Your Listing groups an array field into a single row. Each ticked feature emitted its own `<dt>`, so choosing four features repeated the "Features & Amenities" heading four times. An array field is one answer with several parts, so it now reads "Features & Amenities: WiFi, Parking" on one row. Keyed by field name, so two array fields that share a heading cannot merge into each other (BC 10217547658).
- Saving a NEW listing type, and Reset Settings, both toasted on success and then destroyed the toast by navigating away — the confirmation now travels through the redirect as a URL flag and renders server-side on arrival. Reset is destructive and irreversible, so silence there was indistinguishable from failure.
- The submission form no longer deletes the categories it cannot display. `category` now speaks for the one slot the form renders and preserves the rest.
- A listing holding a category outside its type's allowed list is editable again; the allowlist governs what may be newly picked, not whether an existing listing can be saved.
- A cache lifetime of 0 disables caching instead of writing a permanent entry, on both the search and facet caches, on read as well as write. The migration clears entries the previous behaviour wrote.
- The Rebuild Search Index button schedules a rebuild; it previously had no handler at all.
- The map picker renders, drags and writes back coordinates on the wp-admin listing editor.
- The dashboard favourite control sits over the card image and receives its own clicks.
- Claim approvals and rejections made from wp-admin fire the same action the REST path fires, so the audit log, webhooks and notifications see them.
- Category names containing an ampersand display correctly wherever they surface.
- Page creation is deferred until WordPress has a rewrite object, so a site missing a canonical page no longer white-screens on the front end after an update.

### Security
- Terms of Service acceptance is enforced on `POST /submit`. A submission with `agree_terms` absent previously returned 201 with no consent recorded anywhere. This is a breaking change for integrators posting from their own client, and for sites that set the submission block's `showTerms` to false: both opt out with `add_filter( 'wb_listora_require_terms_acceptance', '__return_false' )`. The default fails closed because it is a legal gate.

## [1.5.0] - 2026-08-12

Adds split-shift business hours and member blocking, corrects a credit unit mismatch that showed the wrong balance, closes a data-exposure gap on listing services, and makes the plugin fully translatable. Ships in lockstep with WB Listora Pro 1.5.0.

Minor rather than patch: this wave adds database indexes, and production rule 4 reserves schema changes for a minor release at minimum. WB_LISTORA_DB_VERSION moves to 1.5.0 and the migration runs on activation.

### Added
- `wb_listora_get_currency_format()` resolves symbol, position and decimal precision for a currency code, with a `wb_listora_currency_format` filter. `/settings/app-config` publishes `currency_symbol`, `currency_position` and `decimals`; without them native clients rendered prices through `Intl.NumberFormat` as "US$35.00" rather than the site's "$35.00".
- `wb_listora_get_map_tiles()` resolves raster tile URL and attribution, with a `wb_listora_map_tiles` filter. `/settings/maps` publishes `tile_url` and `tile_attribution`; the tile source was previously a literal inside the listing-map block, so native clients had none and Android rendered a blank map.
- `Search_Indexer::purge_orphans()` sweeps `search_index`, `field_index`, `geo` and `hours` for rows whose listing no longer exists.

### Fixed
- Service reads inherit the parent listing's visibility. `GET /listings/{id}/services` and `GET /services/{id}` are public by design but had no `post_status` check, so a draft, pending, rejected or awaiting-credits listing served its service titles, descriptions and prices to anonymous callers.
- Orphaned rows are purged by the daily cleanup rather than only by `wp listora cleanup`. The 1.4.1 backfill also never covered the four index tables: `Listing_Data_Eraser::purge_orphans()` deliberately skips them because `Search_Indexer` owns them, and `Search_Indexer` had no backfill. A stale `search_index` row keeps its old status, and the search engine selects candidates from that table with no join to `wp_posts`, so orphans inflated result totals and page counts.
- `wb_listora_format_currency()` honours the resolved position, and zero-decimal currencies such as JPY no longer render two decimal places.

### Performance
- `KEY idx_status_created (status, created_at)` on `reviews` and `claims`. The moderation screens filter by status and sort by date, and no composite satisfied both, so MySQL walked `idx_created` and discarded non-matching rows. Measured on 9,127 reviews: `EXPLAIN` `filtered` moves 10% to 100%, and the query at offset 1500 goes from 5.74ms to 1.59ms. Claims additionally loses `Using filesort`.
- `KEY idx_event_listing (event_type, listing_id, count)` on `analytics`. Sorting the admin listings table by Views builds a derived table over the whole analytics history; the existing key leads with `listing_id` so a `WHERE event_type=… GROUP BY listing_id` could not use it. Measured on 28,014 rows: 21.2ms to 5.5ms, and the derived table becomes a covering index scan.
- Admin Reviews batches its per-row `get_user_by()` and `get_permalink()` calls. Measured on a 50-row page: 46 queries to 4.

### Changed
- `wp listora cleanup` reports index-table orphans in its purge count; it previously counted only the data tables.

### Added (2026-08-08 to 08-12 wave)
- Business hours hold up to three ranges per day (`slot` column). `wb_listora_normalize_hours()` is the single interpretation every reader must use; `wb_listora_max_hours_slots()` caps every writer. Five readers each had their own interpretation and four were wrong, all failing silently.
- Member blocking: block from the review card, unblock from the dashboard. `wb_listora_hidden_review_authors()` resolves the viewer's block list.
- `wp listora repair-locations` and `wp listora repair-credit-ledger`, both dry-run by default. The credit repair appends a correcting adjustment, records the ledger IDs it settled so a re-run cannot double-pay, and never claws back.
- `Credits::award()` is the single money-mode-aware top-up seam; callers reaching `topup()` directly bypass the conversion.
- Bulk Edit / Quick Edit for listing type, bound to the plugin-owned `listora_type` column (`bulk_edit_custom_box` never fires for core columns).
- Filters `wb_listora_social_platforms`, `wb_listora_credit_pack_sizes`; action `wb_listora_migrated_hours_unreadable`; meta `_listora_migrated_hours_raw`.

### Fixed (2026-08-08 to 08-12 wave)
- Dashboard Favorites listed nothing for every member: `ORDER BY id` on a table whose primary key is the composite `(user_id, listing_id)` and which has no `id` column. Logged as `WordPress database error`, which is neither a fatal nor a warning.
- Credits were awarded and spent in the ledger's minor units, so a 50-credit purchase credited 0.50. The REST balance now declares `balance_units` and adds `balance_money` + `currency`; `balance` keeps its previous meaning for shipped clients.
- `format_hours_schema()` skipped entries with no `day` key, so every member-submitted listing published an empty `openingHoursSpecification`; the page rendered hours correctly throughout.
- Saving a listing in wp-admin erased its location.
- A single-type directory rendered an unusable Add Listing form.
- Admin notices carried a class excluded by the notice renderer and shipped invisible; `bin/coding-rules-check.sh` Rule 10 now fails the build on a notice without `listora-notice`.
- Review headline counted authors the viewer had blocked, so a listing showed "5 reviews" above 4. `get_rating_summary()` and `get_review_distribution()` are both block-aware.
- Sitemap toggle dropped only `listora_listing`, leaking every other Listora post type.
- Notification email plain-text part carried the previous email's listing.
- Competitor migrators passed source business hours through unmapped and reported success; unreadable values are now preserved under `_listora_migrated_hours_raw` and reported.
- `setup_complete` was read from two sources that could disagree.
- Reviews from deleted accounts render "Former member"; privacy-anonymized rows (user_id 0) render "Anonymous".

### Changed (2026-08-08 to 08-12 wave)
- Full i18n: 284 previously untranslatable strings exposed, JS layer translated via `src/utils/i18n.js`, ten locales complete. WordPress 6.5+ prefers `.l10n.php` over `.mo`, so a stale one silently shadows correct translations while every catalogue check reports 100%; `bin/coding-rules-check.sh` Rule 11 fails the build on a `.l10n.php` older than its `.po`, and `bin/build-release.sh` regenerates them at package time.
- `bin/coding-rules-check.sh` Rule 12: every `data-wp-interactive` namespace must have a registered store. A namespace with no matching `store()` call resolves to nothing, silently, with a clean console.
- `bin/build-release.sh` gains a coverage gate: it previously asked only whether the smoke walk found anything, never whether it looked, so a 10%-coverage walk would open the gate once its failures were fixed.
- Smoke runbook gains a `[CORE]` must-run set and cross-cutting checks 7-10 (no database errors, counters agree with what they count, visible means computed-visible, translated means rendered-translated). Each traces to a bug that shipped past the previous checks.

## [1.4.1] - 2026-08-04

Hardens the plugin against corrupted or unexpected stored data, and completes cleanup when a listing is permanently deleted. Ships in lockstep with WB Listora Pro 1.4.1.

### Fixed
- Select, radio and multiselect options added through the listing type editor no longer produce a fatal on the submission form. Options are normalized to a canonical `{ value, label }` shape both on read and on save, so already-corrupted sites heal without a migration.
- Permanently deleting a listing now removes its reviews, review votes, favorites, claims, services and analytics rows. Trashing keeps everything, so restore stays lossless.
- Corrupted or legacy-shaped stored data renders safely instead of fataling: dashboard statistics cache, gallery, social links, review reports, and values supplied by extensions to the calendar, categories and review-criteria hooks.

### Added
- `wb_listora_listing_data_deleted` and `wb_listora_purge_orphaned_listing_data` actions let extensions clean their own listing-scoped data alongside the core cleanup.

### Changed
- `wp listora cleanup` removes records orphaned by listings deleted on earlier versions.

## [1.4.0] - 2026-08-02

Mobile-app password sign-in, with the owner switch and brute-force defences that route requires. Ships in lockstep with WB Listora Pro 1.4.0.

Minor rather than patch: this wave adds a feature and a setting, and production rule 7 reserves patch releases for bug fixes only.

### Added
- `POST /listora/v1/auth/app-password` trades a member's WordPress password for a core Application Password, so the app can offer the password members already have instead of only the browser approval screen. `wp_authenticate()` does the authentication, so every `authenticate` filter on the site still runs.
- Settings > Advanced carries an App sign-in switch (`wb_listora_app_password_login`, default on). Turning it off stops new exchanges without signing out members who already have the app.
- Reconnecting from the same install prunes older credentials carrying that `app_id`, so a member's application-password list stops growing on every reconnect. A different `app_id` is left alone, so a phone never evicts a tablet.
- The app-config `auth` block advertises which sign-in doors this site offers, so the client never renders a path the site will refuse.
- New filters `wb_listora_app_scheme`, `wb_listora_app_connect_schemes`, `wb_listora_app_connect_bridge`, `wb_listora_app_password_login_enabled`; new action `wb_listora_app_credential_issued` (deliberately not passed the credential).
- New filter `wb_listora_dedupe_recurring_hooks` — a hook => Action Scheduler group map, so a plugin can register its own recurring jobs for the duplicate-pending sweep.

### Fixed
- Members can delete their own listings again; the permission check refused the owner and admitted only administrators.
- The cron dedupe sweep covered 4 of Free's 7 recurring jobs and none of Pro's. Its list named `wb_listora_email_log_prune`, a transposed name matching nothing, and `dedupe_pending_batch()` takes one Action Scheduler group for the whole batch, so Pro's hooks were swept against Free's group and silently found nothing.
- `$view_data` is a hand-built whitelist and dropped anything `wb_listora_card_view_data` added, so an extension's card additions never reached the templates.
- The single-form submission layout no longer emits `hidden` on its own steps, and `wb_listora_submission_plan_step` now passes `$is_single_form` so an extension's step can do the same rather than depending on a non-`!important` CSS override.
- Status colours meet contrast requirements in dark mode.
- The owner contact form and the Pro lead form share one submit path, so a listing renders one working form instead of two competing ones.

### Security
- Sign-in failures answer identically for a wrong password and a nonexistent username, so the endpoint cannot enumerate accounts.
- Failed exchanges are throttled on two independent buckets (per address and per account) before any credential is read; only wrong passwords count, so a disabled switch or a 2FA hand-off cannot lock out an honest member.
- A site running two-factor authentication is never bypassed — the exchange answers 409 and returns the member to the interactive flow.
- The account password is never stored, logged or echoed, and the response carries `Cache-Control: no-store`.

### Dev
- The server advertises its own contact and lead-form routes; clients no longer hardcode them.
- Manifest refreshed for the wave: +2 REST routes, +6 fired hooks, +1 setting.

## [1.3.0] - 2026-07-27

Product-wide money-flow and data-integrity audit. Ships in lockstep with WB Listora Pro 1.3.0.

### Changed
- Listing submission now requires an account; anonymous guest submission and its email-verification step were removed (guests could never upload media, so the flow always dead-ended). The block "Require Login" control and the inert `enable_guest_submission` flag were removed to match.

### Fixed
- Credit surfaces (submission form, dashboard) no longer render on installs with no configured purchase path; both route through the canonical `wb_listora_should_show_member_credits()` gate.
- The SDK credit `Consumer` is money-mode aware, so a per-listing credit fee is charged at full value instead of roughly 1/100th (minor-unit mismatch).
- A site-configured Buy Credits override URL is honored on every credit CTA via a shared `wb_listora_get_credit_purchase_url_override()` helper.
- `pending_verification` is now a single-sourced status with a label and valid transitions; `Status_Manager::custom_statuses()` drives registration, the transition map, and the label list.
- REST `PUT /settings` routes through `Settings_Page::sanitize()`, so array settings and blank list-limit defaults can no longer be corrupted by a headless client.
- Activation mirrors `directory_page` into settings; the dead duplicate `maybe_create_pages()` was removed.

### Dev
- Free no longer reads the Pro `wb_listora_pro_credit_packs` option (INV-12c); Pro answers the `wb_listora_has_credit_purchase_path` filter.
- Free and Pro keep a byte-identical `payments` table definition.
- Custom capabilities and their uninstall removal derive from one map; uninstall never strips WordPress core capabilities.
- SDK `Consumer` and direct gateways are money-mode aware; new `Credits::cancel_hold_by_id()` cancels a specific reservation.
- New `bin/audit-guardrails.sh` (local-CI stage 2.3): rating source, Free-Pro option boundary, payments schema, and credit-surface gating.

## [1.2.2] - 2026-06-26

### Fixed
- Bundled Credits SDK and license/update SDK sources (`libs/wbcom-credits-sdk/src`, `libs/edd-sl-sdk/src`) were stripped from the 1.2.1 package by an unanchored `src` exclude, disabling credit-based features and auto-updates on fresh installs. Distribution excludes are now root-anchored (`/src`, `/bin`, `/tests`) so bundled libraries always ship complete.
- `bin/build-release.sh` now asserts every bundled `libs/*/src` tree actually landed in the finished zip and fails the build if any is missing.

### Changed
- Missing or incomplete bundled libraries now degrade to a soft admin notice (dependent features disabled) via a single `wb_listora_require_bundled_lib()` guard, instead of risking a fatal error.

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

- **New release gates** (BC #9989784605 retro): PHPStan paths now include `blocks/` (28 latent type findings fixed - `strtotime`/`filemtime`/`get_permalink`/`wp_json_encode` false-flows), `bin/check-block-attr-guards.py` enforces the floor-guard rule as coding-rules Rule 7 (block attributes are untrusted server-side input), and `docs/qa/journeys/system/adversarial-block-attributes.md` fuzzes every registered block through the block-renderer REST as part of the smoke.
- Dashboard sidebar restyled as an elevated card panel (`142e256`); mobile tab-rail scroll affordance (`4c95242`); view counts render `0` instead of hiding (`99a5003`).
- Hours builder gives live Open-24h / Closed feedback (`0edbe56`, `1d631e0`); customer-facing `--sm` buttons meet the 40px tap-target floor (`15ecfd8`); featured images get an alt-text fallback (`fe0737c`).
- Database migrations run eagerly on plugins_loaded after an update (`ce83bb6`, BC #9970182629).

### Fixed

- **Search_Engine page/per_page=0 fatal** (BC #9989784605 family): `parse_args()` now floors `page` and `per_page` at 1 - a saved Featured block with `count: 0` or Grid with `perPage: 0` hit the page-count division at `class-search-engine.php:112` uncaught. Found by the new adversarial block-attribute matrix (480 REST calls, every block x numeric attribute x {0, -1, -999999, 0.4, 999999, "abc"}), not by a customer. Blocks also clamp `count`/`perPage` at the assignment + `"minimum": 1` in their schemas.
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
- **Documentation buttons opened a dead domain** (#9919933465): docs links pointed to a domain that no longer resolves; they now point to `store.wbcomdesigns.com/listora/docs/`, with per-tab slugs and the `wb_listora_docs_url` filter intact.
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
