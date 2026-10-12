---
group: admin-listings
plugin: wb-listora
scope: wp-admin listings table, listing editor boxes, moderation row and bulk actions, Reviews and Claims tables, admin shell header, hub tabs and notices
journeys: 10
site_profile: required
---
# Admin listings: what an agent checks here

## Scope
Covers everything an administrator or editor sees in wp-admin around listings: the Listings list table (columns, filters, status views, row and bulk actions), the listing edit screen and its meta boxes, the shared admin shell (page header, hub tab row, notices), and the shared admin table used by Reviews and Claims.

Does NOT cover: what Reviews or Claims do to a listing's rating or ownership (`reviews`, `claims-verification`); the Add Listing wizard and member edit on the frontend (`submission`); settings tabs, wizard and listing-type editor screens (`settings-setup`); import and export screens (`import-migration`); the email log page and email content (`emails-alerts`); Leads (`contact-leads`); Analytics and Audit Log screens (`analytics-audit`); the map on the public listing page (`map`). Pro-owned screens (Moderators, moderator queue block, Pro hidden submenus) are in `wb-listora-pro/docs/qa/journeys/admin-listings/_group.md`.

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Listings list table | `$SITE_URL/wp-admin/edit.php?post_type=listora_listing` (also `&post_status=pending|publish|listora_rejected|listora_expired|listora_deactivated|pending_verification`, `&s=`, `&listora_type_filter=`, `&listora_duplicate_filter=confirmed`, `&paged=`, `&orderby=`) | {admin}, {editor} | `includes/admin/class-listing-columns.php`, `class-listing-bulk-actions.php`, `class-admin-hubs.php` |
| 2 | Listings list: Quick Edit and Bulk Edit panels | surface 1, row "Quick Edit" or tick rows then Bulk actions > Edit > Apply | {admin} (type control), {editor} (no type control) | `class-listing-bulk-actions.php`, `assets/js/admin/bulk-edit.js` |
| 3 | Listing edit screen (block editor + meta box drawer) | `$SITE_URL/wp-admin/post.php?post={listing id}&action=edit` | {admin}, {editor}, author (own), contributor (draft) | `class-listing-type-metabox.php`, `class-listing-fields-metabox.php`, `class-featured-metabox.php`, `class-services-metabox.php`, `class-report-metabox.php` |
| 4 | New listing screen | `$SITE_URL/wp-admin/post-new.php?post_type=listora_listing` | {admin}, {editor}, author, contributor; subscriber/{owner} denied | same as 3 |
| 5 | Reviews table | `$SITE_URL/wp-admin/admin.php?page=listora-reviews` (`&status=`, `&rating=`, `&listing_type=`, `&period=`, `&reply=`, `&reported=1`, `&s=`, `&paged=`, `&per_page=`) | {admin}, {editor} | `includes/admin/class-admin.php` `render_reviews_page()`, `class-admin-table.php` |
| 6 | Claims table | `$SITE_URL/wp-admin/admin.php?page=listora-claims` (`&status=`, `&s=`) | {admin}, {editor} | `class-admin.php` `render_claims_page()`, `class-admin-table.php` |
| 7 | Plugin page with shared header (Dashboard, Listing Types, Leads, Email Log, Settings) | `$SITE_URL/wp-admin/admin.php?page=listora`, `listora-listing-types`, `listora-leads`, `listora-email-log`, `listora-settings` | {admin} (editor: Reviews and Claims only) | `includes/class-render-helpers.php` `wb_listora_render_admin_header()`, `class-admin-hubs.php` |
| 8 | Core taxonomy screens with hub tabs | `$SITE_URL/wp-admin/edit-tags.php?taxonomy=listora_listing_cat|listora_listing_location|listora_listing_feature&post_type=listora_listing` and `taxonomy=listora_service_cat` | {admin} | `class-admin-hubs.php` `render_tabs()` |
| 9 | Setup wizard (notice-free check only) | `$SITE_URL/wp-admin/admin.php?page=listora-setup&rerun=1&step=type|demo|done` | {admin} | `includes/admin/class-setup-wizard.php` (owned by `settings-setup`) |
| 10 | Settings > Maps notice | `$SITE_URL/wp-admin/admin.php?page=listora-settings&tab=maps` | {admin} | `class-map-settings-tab.php` (+ Pro `class-google-maps.php`) |
| 11 | Plugins screen and WP Dashboard (SDK notices) | `$SITE_URL/wp-admin/plugins.php`, `$SITE_URL/wp-admin/index.php` | {admin} | `wb-listora.php` `wb_listora_render_credits_sdk_outdated_notice()` |
| 12 | Hard-delete path (no UI) | `wp post delete {id} --force`, `wp listora cleanup` | CLI | `includes/core/class-listing-data-eraser.php` |

## Components on each surface
**1 Listings list table** (page order): hub tab row `nav.listora-hub-tabs[aria-label="Listings"]` (All Listings, Leads); core `h1` + Add New; status views line (All, Mine, Published, Pending, Draft, Trash, plus Expired / Rejected / Deactivated / Pending Email Verification only when count > 0, each `a` with `span.count`); search box; bulk action select (core Edit, Move to Trash, plus "Send renewal reminder") + Apply; filters `select#listora-type-filter` ("All Types" + registered types), `select#listora-duplicate-filter` ("All Listings" / "Only duplicate-confirmed"), core date and Filter button; "N items" count and pagination. Columns (id after `column-`): `cb`, `listora_thumb` (40px thumb `.listora-listing-col__thumb` or type icon `.listora-listing-col__thumb-empty`), `title` (row actions Edit, Quick Edit, Trash, Preview, plus Approve `a` green and Reject `a.listora-reject-listing` red on pending rows, "Mark verified" on pending_verification rows; status pill "Pending Email Verification" via `display_post_states`), `listora_type` (`.listora-listing-col__type` coloured chip), `listora_location` (two most specific of city/state/country, else address, else `.listora-listing-col__placeholder` dash), `listora_rating` (`.listora-listing-col__star` + average + `.listora-listing-col__count`), `listora_views` (hidden by default), `listora_featured` (`.listora-listing-col__featured` star with screen-reader text), `listora_renewals` (hidden), `listora_reports` (`a.listora-listing-col__reports` flag + count, hidden unless any open report), `listora_duplicate` (`.listora-duplicate-flag`, hidden), `author`, two taxonomy columns (hidden), `listora_moderator` (Pro), `date`. Sortable: rating, views, featured, title, date. Screen Options checkboxes `input.hide-column-tog` for every column.
**2 Quick Edit / Bulk Edit**: core Quick Edit fields (title, slug, date, status); Bulk Edit panel `#bulk-edit` with `select[name="listora_bulk_listing_type"]` ("- No Change -" then each registered type), nonce `_wb_listora_bulk_type_nonce`, Update button; notice after save (`updated=N&skipped=M`).
**3 Edit screen**: block editor title and content; side panels Categories, Locations, Tags (listing taxonomies), Locations help text (`location-panel-help.js`); meta box drawer: `#wb_listora_listing_type` (Listing Type, `select#wb-listora-listing-type`), `#wb_listora_fields_{group}` one box per field group of the type (inputs rendered by the frontend field renderer: text, url `video`, checkbox toggle, `file` upload `.listora-submission__upload-trigger`, gallery `[data-listora-admin-gallery]` with `[data-listora-gallery-add]`, thumbs `.listora-admin-gallery__thumb`, remove `[data-listora-gallery-remove]`, map picker `.listora-submission__map-picker` with Leaflet `.leaflet-container`, hidden `[name$="[lat]"]`/`[lng]`), `#wb_listora_features` (Features & Amenities), `#wb_listora_featured` (Featured: intro `.wb-listora-featured-metabox__intro`, days input `#wb-listora-featured-days` "0 = permanent", until line `.wb-listora-featured-metabox__until`), `#wb_listora_services` (Services: `.wb-listora-services-metabox` count `__count`, table `__table`, row `__row`, "+ Add Another Service" `[data-listora-svc-add-row]`, remove `[data-listora-svc-remove-row]`, photo `__photo` with choose/remove, category checkbox fieldset `__cats`, empty `__empty`), `#wb_listora_reports` ("Report (n)" list `.wb-listora-reports-metabox__list`, item `__item`), Pro adds `#listora-manual-badges` and `#listora-verification` side boxes. Publish panel, expiry meta only when expiry setting is on.
**5 and 6 Reviews / Claims**: shared header (Moderation hub tab row `nav.listora-hub-tabs[aria-label="Moderation"]`), notice `.notice.listora-notice` from `listora_notice`, filter tabs `nav.listora-filter-tabs` (All, Pending, Approved, Rejected, each with `.listora-filter-tab__count`), filter bar `form.listora-filter-bar[role=search]` (search `input.listora-search-input` placeholder "Search listing or review text" / "Search by listing, name or email"; Reviews selects: Any rating, Any listing type, Any time, Owner reply: any, Reported or not; button "Search" or "Apply"; "Clear" link), table `table.listora-table` with `caption.screen-reader-text` count, select-all `.listora-table__select-all`, sortable header links `a.listora-table__sort`, row checkbox `input[name="ids[]"]`, per-row actions `.listora-row-actions` and `details.listora-row-menu` overflow, detail drawer `dialog.listora-drawer` (Close button), footer `form.listora-admin-table-form[method=post]` bulk bar `.listora-bulk-actions` (`select[name=bulk_action]` Approve / Reject / Delete, Apply), pagination bar `.listora-pagination-bar` (`.listora-pagination__count`, `nav.listora-pagination`, `.listora-per-page`), empty state `.listora-empty-state` ("No reviews waiting" / "No reviews yet" / "No claims waiting" / "No claims yet").
**7 Shared header screens**: `.listora-page-header` > `.listora-page-header__left` (`h1.listora-page-header__title` with decorative Lucide icon, `p.listora-page-header__desc`), optional `.listora-page-header__actions`, one `hr.wp-header-end`, notices below, hub tab row for multi-tab hubs (Listing Types: Listing Types, Badges; Moderation: Reviews, Claims, Needs, Moderators; Tools: Audit Log, Email Log, Webhooks); single-tab hubs (Dashboard, Settings, Analytics) show no tab row; sidebar shows one item per hub (nine).
**8 Taxonomy screens**: core `h1`, hub tab row (Categories, Locations, Features, Service Categories), core table; notices once.
**10, 11 Notices**: `.notice.listora-notice.notice-info.inline` (maps), `.notice-warning` licence and SDK notices with action links and Dismiss.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | empty list | filter to a status with no rows, or `s=zzzznope` (core "No listings found") |
| 1 | one row / many rows | fixtures script `seed`; 2000+ rows via fixtures for scale |
| 1 | pending / rejected / expired / deactivated / pending_verification rows | `wp eval-file docs/qa/fixtures/2.0-fixtures.php seed` (prints pending and rejected ids); set `post_status` by `wp post update` |
| 1 | row with no geo city / no address / long markup city | edit `${PREFIX}listora_geo` (see `admin-location-column-no-city` Setup) |
| 1 | row with open visitor report (Reports column default-visible) | file a report on the listing as {guest} |
| 1 | all columns on / default columns | `wp user meta update {admin id} manageedit-listora_listingcolumnshidden '[]' --format=json` / `wp user meta delete ...` |
| 1 | Pro moderator column visible | Pro active and one active moderator |
| 3 | expiry off / on | Settings > General "Enable automatic listing expiration" |
| 3 | type without services / with services; type with map_location field | pick {type} / {type_b} |
| 3 | gallery with deleted attachments | delete attachments (see `listing-media-fields-and-retention` step 9) |
| 5, 6 | pending / approved / rejected rows, reported review, owner reply | create `QA-J1-` review via REST as {owner}; claim via the frontend |
| 5, 6 | N=0 | status view with no rows |
| 7, 8 | wizard quiet / foreign notice | mu-plugin emitting `admin_notices` (see `admin-shell-header-and-notices` step 4) |
| 10 | Google provider without key | `wb_listora_settings` `map_provider=google`, `google_maps_key=''` (restore after) |
| 11 | SDK folder missing | throwaway site only: rename `libs/wbcom-credits-sdk/src` |
| all | no-permission | {owner} subscriber, {guest}; editor on Settings |
| all | RTL | switch site language to an RTL locale (admin RTL CSS: `assets/css/admin-rtl.css`) |
| all | 390 px | core stacked list layout; Playwright viewport 390x844 |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| Settings > General "Enable automatic listing expiration" and Default expiration | off / on, days | surface 3/4: `_listora_expiration_date` written only when on; surface 1 Approve sets expiry only when on; Renewals column and "Send renewal reminder" relevance |
| Screen Options column preference (user meta `manageedit-listora_listingcolumnshidden`) | unset / `[]` / list | surface 1: which `th.column-*` are visible; an empty saved array suppresses defaults |
| `wb_listora_settings.map_provider`, `google_maps_key` | osm / google, empty / key | surface 10 notice text; surface 3 map picker (Pro provider owns the map when `data-provider-map-init`) |
| Listing type: services enabled, field groups | per type | surface 3: Services box present; field boxes per group |
| Listing type colour and icon | per type | surface 1 type chip and thumb placeholder icon |
| Pro `outgoing_webhooks`, `reverse_listings` toggles | off / on | Pro tabs in Tools and Moderation hubs appear or vanish (hub tab row, sidebar) |
| Pro active/inactive | - | Hub tab sets, Moderator column, Badges and Verification boxes |
| User meta `wb_listora_menu_prompt_dismissed` | unset / 1 | unlinked-pages notice on Settings only |
| White label (Pro) | brand name | licence notice brand name (surface 7/11) |

## Fixtures and setup
- Resolve `$SITE_URL`, `$WP_PATH`, personas and content from `docs/qa/sites/<site-host>.json`.
- Seed: `wp --path=$WP_PATH eval-file docs/qa/fixtures/2.0-fixtures.php seed` (prints pending and rejected listing ids). For scale: seed 2000+ listings per the same script, then delete them.
- QA-prefixed rows only (`QA-J1-`, `QA-loc-`, `QA admin listing`); mu-plugin for foreign notice goes in `wp-content/mu-plugins/` and is removed after.
- Restore afterwards: `manageedit-listora_listingcolumnshidden` user meta, `wb_listora_settings` map keys, expiration setting, listing types changed by bulk edit, review/claim statuses, `_listora_assigned_moderator` meta, the renamed SDK folder (throwaway site only), the mu-plugin.

## Run order
1. `admin-shell-header-and-notices` - browser, critical: one header, hub tabs, notices once and visible, unlinked-pages notice.
2. `admin-listings-table-layout` - browser, high: default columns, widths, 390 stacked layout.
3. `admin-location-column-no-city` - mixed, high: Location cell for resolved, region-level, country-only, composite, empty.
4. `listing-approve-reject-and-bulk-actions` - mixed, critical: Approve/Reject row actions, rejected guard, bulk type edit.
5. `add-listing-from-wp-admin` - mixed, high: new listing screen, services with categories, expiry, roles.
6. `listing-media-fields-and-retention` - mixed, critical: Media box, gallery, moderator keeps media, deleted attachments.
7. `admin-map-picker-renders` - browser, normal: Leaflet picker mounts, marker drives fields.
8. `admin-bulk-actions-submit` - browser, critical: Reviews and Claims Apply / Search submit once.
9. `missing-sdk-notices-agree` - mixed, normal: throwaway site only, last of the browser set.
10. `listing-delete-cascade` - cli, high: hard delete cascades every listing table.
Then run: `wb-listora-pro/docs/qa/journeys/admin-listings/_group.md`.

## Group-specific micro checks
- G-01 The "Pending (n)" status-view count on surface 1 equals the number of rows shown on `post_status=pending` and the "N items" line; and the Dashboard "pending review" stat card link count (`class-admin.php` ~1176) agrees with the Reviews Pending tab count `.listora-filter-tab__count`.
- G-02 Custom status chips (Expired, Rejected, Deactivated, Pending Email Verification) appear only when their count > 0, and each chip count equals the rows on its view; "All" excludes none of them (`filter_query` lists all eight statuses).
- G-03 Default visible `th` set on a fresh admin is exactly nine (checkbox, thumb, title, type, location, rating, featured, author, date); `th.column-title` width >= 240 px at 1440; `.check-column` about 35 px.
- G-04 Location cell shows at most two parts for a fully resolved row (city, state) and never appends country; no cell is empty (placeholder dash `.listora-listing-col__placeholder` is used).
- G-05 Rating cell reads star + average + `(count)`; the count equals the review count on the public listing page and the Reviews table "approved" rows for that listing; a listing with no reviews shows the dash, not "0 (0)".
- G-06 The Reports column count (`a.listora-listing-col__reports`) equals the "Report (n)" title of the `#wb_listora_reports` box on that listing's edit screen; the column is hidden by default when no listing has an open report and shows when one does.
- G-07 Approve (green) and Reject (red) exist only on `pending` rows; Reject link carries class `.listora-reject-listing`; both links are >= 40 px tall at 390 and are distinguishable without colour (text label).
- G-08 After Approve the notice "Listing approved and published." appears once under the title and the Pending count drops by exactly 1 on reload; after Reject the count drops by 1 and the Rejected chip appears or increments by 1.
- G-09 Bulk Edit type select first option is "- No Change -" (value empty); the control exists once in `#bulk-edit` regardless of rows ticked, and is absent for {editor}.
- G-10 "Send renewal reminder" bulk action notice count equals the number of ticked listings that are listing posts; ticking 0 rows shows no success notice.
- G-11 Exactly one `h1` and one `hr.wp-header-end` per screen on surfaces 5-7; on core list screens (1, 8) exactly one `h1` and `document.querySelectorAll('#message').length` is 1 after load.
- G-12 Hub tab row: `aria-label` equals the hub name, exactly one tab has `.is-active` and `aria-current="page"`, the sidebar shows one highlighted item per hub (nine) and the highlighted one matches the open tab.
- G-13 Every `.notice` inside `.wb-listora-admin` has class `listora-notice` and computed `display` is not `none`; a third-party notice injected by mu-plugin computes `display: none` there and `block` on core screens.
- G-14 The table caption (`caption.screen-reader-text`) text, the `.listora-pagination__count` text and the active filter tab count agree on surfaces 5 and 6; singular/plural reads "1 review" / "2 reviews", "1 claim" / "2 claims".
- G-15 Select-all checkbox `.listora-table__select-all` toggles every `input[name="ids[]"]` on the page only and un-ticks when one row is un-ticked; Apply with none ticked or no action chosen posts nothing.
- G-16 Services box `__count` text equals the number of `.wb-listora-services-metabox__row` rows; after saving a service with two categories both category checkboxes are ticked on reload.
- G-17 Featured box "0 = permanent": value 0 shows no "until" line; value N shows a date N days ahead in the site timezone; the Featured column star on surface 1 matches after save.
- G-18 Map picker: after dragging the marker, hidden lat and lng change and equal the values read from `_listora_address` after save; picker height is not 0 (box is visible when measured).
- G-19 Gallery: thumbs count equals the comma list length in `[data-listora-gallery-input]`; removing one thumb removes exactly one id; the remove control sits on the inline-end edge (right in LTR, left in RTL) and is >= 28 px.
- G-20 Type chip colour (`--listora-type-color`) equals the type's colour in Listing Types, and chip text has contrast >= 4.5:1 on its background in light and dark.
- G-21 Thumb column: listings with a featured image show a 40x40 `img` with non-empty `alt` or decorative handling; listings without show the type icon, never a broken image or an empty cell.
- G-22 Hidden-by-default columns (Views, Renewals, Duplicate confirmed, both taxonomy columns) remain listed under Screen Options and turning one on does not push any column under 20 px or create page-level horizontal scroll.

## Known defects (expected-fail)
- `admin-shell-header-and-notices` step 10: Pro `class-outgoing-webhooks.php` `registry_unavailable` notice omits `listora-notice` and is hidden.
- `listing-delete-cascade` step 4: `wp listora cleanup` prints `Orphaned listing rows purged: 0` although rows were purged.

## Not covered yet
- Listing-table filters: the type filter (`#listora-type-filter`) and duplicate filter (`#listora-duplicate-filter`) results, and sort by Rating, Views and Featured (`sortable_columns`, `sort_by_views`) have no step.
- Row action "Mark verified" and the "Pending Email Verification" pill for `pending_verification` listings.
- Bulk action "Send renewal reminder" (`handle_bulk_actions`, `listora_renewal_reminders` notice).
- Featured metabox, Features & Amenities box and Report metabox (read-only report list) on the edit screen.
- Reviews and Claims filters beyond search (rating, listing type, period, owner reply, reported), sorting, row actions, detail drawer, and per-page switch; only bulk Apply and Search are exercised.
