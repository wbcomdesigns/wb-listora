---
group: listing-detail
plugin: wb-listora
scope: The single listing page - owner bar and status notices, breadcrumb, gallery and lightbox, header (type, verified, open-now, rating, title, address), action row (Save, Share, Directions, Claim, Report, Feature), tabs and field rendering, sidebar cards, flag/report modal, and the mobile action bar
journeys: 10
site_profile: required
---
# Listing detail: what an agent checks here

## Scope
Covers the page rendered by the Listing Detail block (`blocks/listing-detail/render.php`, `templates/blocks/listing-detail/gallery.php`, `tabs.php`, `sidebar.php`) on `$SITE_URL/listing/{listing}/`, its IAPI actions in `src/interactivity/store.js` (`switchTab`, `toggleFavorite`, `shareDialog`, `openLightbox`, `openReportModal`, `submitReport`, `showClaimModal`, `submitClaim`, `featureListing`, `toggleServiceDesc`) and the `/listings/{id}/detail` REST payload that carries the same field values.

Does NOT cover (neighbouring owner in brackets):
- The Reviews tab content, the write-review form, replies and review flags (reviews). Only the tab button, its count badge and the header rating chip are checked here.
- The Services tab content (services); only that the tab exists with the right count.
- Claim approval, verification badge issuing, the claim modal's back-end processing (claims-verification); the Claim button and modal presence are checked here.
- SEO head, JSON-LD and breadcrumb schema (seo-schema), except where a journey here compares schema values with what the page shows.
- Cards and grids that show the same data (browse-blocks, directory-search); a journey here may compare one card value.
- wp-admin editing of the listing (admin-listings) and the owner edit form (submission).
- Dashboard Favourites tab (dashboard-account).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| S1 | Published listing, guest | `$SITE_URL/listing/{listing}/` | guest | `blocks/listing-detail/render.php` + templates |
| S2 | Published listing, another member | same | {other} (logged in, not owner) | same; Save, Report, Claim states differ |
| S3 | Own listing with owner bar (any status, including published) | same | {owner} | owner bar `.listora-detail__owner-bar`, Feature button |
| S4 | Staff view of a member's listing | same | {editor}, {admin} (any user with `edit_others_posts`) | same owner bar, shown with the owner wording |
| S5 | Non-public listing opened by its owner | same URL for draft / pending / rejected / expired / deactivated / awaiting credits / awaiting email verification | {owner} (others get a 404) | status chip in owner bar |
| S6 | Expired listing for a visitor | same URL, status expired | guest, {other} | `.listora-detail__expired-notice` |
| S7 | Overview tab | default tab `#tab-overview` | all | `templates/blocks/listing-detail/tabs.php` |
| S8 | Field-group tab (one per field group of the type) | `#tab-{group_key}` | all | same |
| S9 | Services tab | `#tab-services` (only when services exist) | all | same |
| S10 | Reviews tab | `#tab-reviews` (when reviews feature on) | all | same (content owned by reviews) |
| S11 | Gallery + lightbox | click the main image or "View all photos (N)" | all | `gallery.php` |
| S12 | Share action | click Share | all | `store.js` `shareDialog` |
| S13 | Report modal | click Report | logged-in; guest gets the login modal | `#listora-report-modal` |
| S14 | Claim modal | click Claim | logged-in; guest gets the login modal | `#listora-claim-modal` |
| S15 | Login modal | Save/Report/Claim as guest | guest | `#listora-login-modal` |
| S16 | Mobile action bar | 390 px | all | `.listora-detail__mobile-bar` |
| S17 | Related Listings | bottom of page | all | `.listora-detail__related` |
| S18 | REST detail | `GET /wp-json/listora/v1/listings/{id}/detail` | public read | `includes/rest/class-listings-controller.php` |
| S19 | wp-admin Report box | `$SITE_URL/wp-admin/post.php?post={listing_id}&action=edit` > reports box | {admin}, {moderator} | `includes/admin/class-report-metabox.php` |
| S20 | Block editor: Listing Detail inspector | page editor | {editor}, {admin} | `src/blocks/listing-detail/index.js` |

## Components on each surface
Check every item on every surface where it appears; this is the MICRO_CHECKS sweep list.

**Owner bar (S3, S5)** `aside.listora-detail__owner-bar[aria-label="Manage this listing"]`: label "You're viewing this as the owner.", status chip `.listora-detail__owner-bar-status` (Published, Pending review, Draft, Awaiting credits, Expired, Rejected, Deactivated, Awaiting email verification), actions `.listora-detail__owner-bar-actions` ("Edit listing", "My dashboard").

**Expired notice (S6)** `p.listora-detail__expired-notice[role=note]`, copy filterable by `wb_listora_expired_listing_notice`.

**Breadcrumb** `nav.listora-detail__breadcrumb[aria-label=Breadcrumb]` > `ol/li` chain, last item `aria-current="page"` and not a link; absent when the Breadcrumbs feature is off.

**Gallery (S11)** `.listora-detail__gallery` > main `.listora-detail__gallery-main` with `img.listora-detail__gallery-image`, prev/next `.listora-detail__gallery-nav--prev|--next`, dots `.listora-detail__gallery-dots[role=group][aria-label="Listing photos"]` > `.listora-detail__gallery-dot` (current `.is-active`), thumbs `.listora-detail__gallery-thumbs` > `.listora-detail__gallery-thumb`, button `.listora-detail__gallery-viewall` "View all photos (N)", lightbox `dialog.listora-detail__lightbox[aria-label="Photo viewer"]` with close, prev, next, image, counter `.listora-detail__lightbox-counter[aria-live=polite]`.

**Header** `header.listora-detail__header`: top row `.listora-detail__header-top` = type badge `.listora-badge--type`, "Verified" `.listora-badge--open` (once), "Open now" `.listora-badge--open[role=status]` or "Closed" `.listora-badge--closed` (only when hours exist), Pro title badges (`wb_listora_listing_title_badges`), rating `.listora-rating` (star, value with one decimal, `.listora-rating__count` "(N)"); `h1.listora-detail__title`; `address.listora-detail__address` (pin icon + one complete address line); action row `.listora-detail__actions`: Save button (`aria-pressed`, `.listora-detail__favorite-count`), Share, Directions (needs lat/lng, opens new tab), Claim (when claiming allowed), Report (`.listora-detail__report-btn`, when Report Listings feature on), Feature button `.listora-detail__feature-btn` or status `.listora-detail__feature-status` ("Featured (permanent)" / "Featured until {date}") for the owner.

**Tabs** `[role=tablist]` buttons `.listora-detail__tab`: Overview, one per field group, Services (+ `.listora-detail__tab-count`), Reviews (+ count only when > 0). Panels `.listora-detail__panel[role=tabpanel]`.

**Overview panel** quick info `.listora-detail__quick-info` (`.listora-detail__info-item`), description `.listora-detail__description.listora-prose`, field items `.listora-detail__field-list` > `.listora-detail__field-item` (file fields `--file` with `img.listora-detail__field-image`, hours `--hours`), Features & Amenities `.listora-detail__features` > `.listora-feature-badge`, Tags `.listora-detail__tags` > `.listora-tag-badge`, video `.listora-detail__video` with embed.

**Sidebar `aside.listora-detail__sidebar`** cards in order: "Listed by" `.listora-detail__owner-card` (only when the Show Who Listed It feature is on and a name exists), "Contact" `.listora-detail__contact-card` (contact name, phone `tel:`, website, email `mailto:`), "Location" map card `.listora-detail__map-card` (map owned by the map group, address `.listora-detail__map-address`, "Get directions"), "Follow" social list, "Business Hours" `.listora-detail__hours-card`, "Claimed & Verified Business" `.listora-detail__claimed-badge`.

**Report modal (S13)** `#listora-report-modal.listora-detail__modal[role=dialog][aria-modal=true]`: title "Report This Listing", `select#listora-report-reason` (Inaccurate information, Spam or misleading, Permanently closed, Duplicate listing, Offensive or inappropriate, Something else), `textarea#listora-report-details` ("Details (optional)"), "Submit Report", "Cancel", message `.listora-detail__report-message` (success/error variants), close `x`.

**Claim modal (S14)** `#listora-claim-modal`: "Claim This Business", `textarea#listora-claim-proof` (required), `input#listora-claim-proof-file` (JPEG/PNG/GIF/WebP/PDF, 5 MB), "Submit Claim", "Cancel", `.listora-detail__claim-message`.

**Login modal (S15)** `#listora-login-modal`: title and description set by `state.loginModalTitle|Desc` per triggering action, "Log in", "Create account" (filterable URL).

**Mobile bar (S16)** `.listora-detail__mobile-bar`: "Call" (`tel:`, only with phone), "Visit" (only with website), Save.

**Related (S17)** `section.listora-detail__related` > "Related Listings" `.listora-detail__related-grid[role=list]` of cards.

## States to force
| Surface | State | How |
|---|---|---|
| S1-S3 | Role ladder | guest, {other}, {owner}, {editor}, {admin} on the same listing |
| S5 | Each owner-visible status | `wp post update {id} --post_status=draft|pending|listora_rejected|listora_expired|listora_deactivated|pending_verification` and the awaiting-credits state; non-owners must get the 404 |
| S6 | Expired for visitors | status `listora_expired`, as guest |
| S11 | 0 / 1 / 2 / many photos | listings with no featured image, one, two, twelve |
| header | Verified on/off, Open now / Closed / no hours | Pro verification meta; business hours fixtures including overnight and split shifts |
| header | N reviews = 0, 1, many | review fixtures; rating chip and Reviews tab count |
| S7 | Long content | 120-character title, long unbroken word, 4-digit favourites count, address with all parts, no street, empty address |
| S8 | A tab whose fields are all hidden | field group with all fields hidden for the page: tab must not be an empty panel |
| S9 | Services 0 / many | Services group |
| S13 | Duplicate report, invalid reason, throttled | report twice; REST with a bad reason; repeat past the throttle |
| S12 | Web Share available / not / rejected | Playwright with and without `navigator.share`; reject with `AbortError`; deny clipboard |
| S16 | 390 px with the theme's back-to-top button | scroll to the end on a theme that adds one |
| any | Feature toggles off | Settings > Features: Report Listings, Business Claims, Favorites, Reviews & Ratings, Show Who Listed It |
| any | Dark, unbridged theme, RTL, 200% zoom | per matrix |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| Settings > Features > Report Listings (`report_listings`) | on / off | Report button, modal and REST route exist / absent and refused |
| Settings > Features > Business Claims (`claims`) | on / off | Claim button and modal; REST refuses when off |
| Settings > Features > Favorites (`favorites`) | on / off | Save button, count, mobile-bar Save |
| Settings > Features > Reviews & Ratings (`reviews`) | on / off | rating chip, Reviews tab, Reviews panel |
| Settings > Features > Show Who Listed It (`owner_name`) | on / off | "Listed by" card on S1 and the name line on cards, both together |
| Settings > Features > Breadcrumbs / Schema.org JSON-LD | on / off | breadcrumb nav / JSON-LD |
| Settings > General > Currency (`currency`) | symbol/code | money and number-range fields on the Overview, zero-decimal currencies show no decimals |
| Type Editor > field "Show on listing page" and "Show in API" (per field) | on / off | field appears on S7/S8 and cards, API, and schema as specified in `type-field-visibility-page-api-schema` |
| Type Editor > "Services" | on / off | S9 tab |
| Block attributes `showGallery`, `showMap`, `showReviews`, `showRelated`, `showShare`, `showClaim`, `relatedCount` (S20) | per block | gallery, map card, reviews tab, related section, Share button, Claim button, number of related cards |
| Site timezone (`timezone_string`) | any | Open now / Closed badge follows it |
| Filters `wb_listora_expired_listing_notice`, `wb_listora_listing_title_badges`, `wb_listora_login_modal_register_url`, `wb_listora_detail_reviews_limit` | code | notice text, Pro badges, Create account URL, reviews shown |

## Fixtures and setup
- Personas: `bash bin/qa-fixtures.sh`; placeholders from `docs/qa/sites/<site-host>.json` ({listing} owned by {owner}, {other}, {editor}, {admin}, {moderator} with Pro).
- Listings (QA-prefixed) covering: with 0/1/12 photos, with and without hours (one split shift, one overnight), with a file-type field, with a number min/max pair, with services, with 0 and many reviews, claimed and unclaimed, featured and not, with and without lat/lng, a 120-character title.
- Record settings before: `wp option get wb_listora_settings --format=json > $SCRATCH/settings-before.json`; feature toggles live in the same option.
- Restore: write the saved JSON back, delete QA posts, reset statuses changed during S5, clear the report option rows created by the flag journey (`wp eval` with the QA listing id), reset timezone to UTC.

## Run order
Browser journeys first, in page order; mixed after.
1. `share-copies-link-no-scroll-lock.md` - mode browser, high: Share works once and never locks scrolling.
2. `back-to-top-clears-action-bar.md` - mode browser, high: theme back-to-top does not cover the mobile action bar.
3. `listing-header-chips-and-address.md` - mode mixed, normal: one Verified chip, one complete address line.
4. `open-now-badge-matches-search.md` - mode mixed, high: header Open now equals the search Open now filter.
5. `listing-owner-name.md` - mode mixed, normal: "Listed by" shows a name, never a login, and obeys the toggle everywhere.
6. `number-fields-money-and-range.md` - mode mixed, high: money and range display on page, cards, schema.
7. `listing-detail-file-field-renders-as-image.md` - mode mixed, normal: no raw attachment id; logo renders on its tab (expected-fail).
8. `flag-a-listing.md` - mode mixed, high: report modal, throttle, staff email, admin sees and clears flags.
9. `type-field-visibility-page-api-schema.md` - mode mixed, critical: a field hidden for the page disappears from page, cards, API and schema.
10. `anon-login-modal-register-cta.md` - mode browser, high. The anonymous login modal always renders a Create account CTA

## Group-specific micro checks
| id | Check |
|---|---|
| G-01 | The rating chip `(N)` equals the Reviews tab badge number and the "N reviews" in the aria-label; when N is 0 the chip is absent and the tab has no badge (never a "0" badge). |
| G-02 | The Services tab badge equals the number of `.listora-detail__service-card` in `#panel-services`; Reviews and Services tabs follow Overview and the field-group tabs in that order. |
| G-03 | "View all photos (N)": N equals the number of images in the lightbox (featured plus gallery), the lightbox counter reads "1 / N" on open and wraps from N to 1; with a single photo the button, dots, nav arrows and thumbs are all absent. |
| G-04 | The lightbox is a native `dialog`: Escape closes it, focus returns to the control that opened it, the page behind does not scroll while open (`overflow` restored after close), and arrow keys move one image. |
| G-05 | Exactly one "Verified" chip exists in the header (not also in the sidebar/title area), the Open now / Closed chip appears only when business hours exist and uses `role=status`, and none of them wrap onto a second row at 390 without a gap. |
| G-06 | The address line prints each part once (street, city, state, postal code) with no duplicated city/postcode and no trailing comma; with no street it prints the remaining parts; with an empty address the `<address>` element and pin icon are both absent. |
| G-07 | Save button: `aria-pressed` flips on click, the count in `.listora-detail__favorite-count` changes by exactly 1 and agrees after reload and with the dashboard Favourites tab; the mobile-bar Save is in sync with the header Save. |
| G-08 | A guest clicking Save, Report or Claim sees the login modal with an action-specific title and description (not the Save wording for Report), "Log in" returns to the same listing URL, and focus returns to the clicked button on close. |
| G-09 | Directions link has `target=_blank` and `rel=noopener`, its URL contains the listing's saved lat and lng, and it is absent for a listing without coordinates. |
| G-10 | Share with Web Share: `navigator.share` is called once with the listing title and URL; without it the URL is copied once and a single confirmation appears; neither path leaves `overflow:hidden` on `html` or `body`; a rejected share shows no error toast for `AbortError`. |
| G-11 | Tabs are keyboard operable: arrow keys move between `[role=tab]` buttons (or Tab reaches each), `aria-selected` is true on exactly one tab, the matching panel is the only one not `hidden`, and the URL does not change to a dead anchor. |
| G-12 | A field group whose every field is hidden for the page produces no tab and no empty panel; a hidden field's label never appears in `dl.listora-detail__field-list` markup (check page source, not only visible text). |
| G-13 | Money field renders with the site currency symbol and decimals (zero-decimal currency shows none); a min/max pair renders as one range row "min - max" and never two rows; a missing max renders as "from min". |
| G-14 | Hours card: each day prints every stored range in order, a closed day prints "Closed", today's row is visually marked, and times use the site's time format; the header Open now chip agrees with the row for today at the current site time. |
| G-15 | Report modal: reason select defaults to the first option, Submit disables while sending and re-enables with its original label on error, the success message replaces the form body, and a second report by the same member shows "You have already reported this listing." |
| G-16 | Claim button disappears for a claimed listing and for the owner; the sidebar "Claimed & Verified Business" badge appears only when the listing is claimed. |
| G-17 | Related Listings shows exactly `relatedCount` cards (default 3), never the current listing, and the section is absent (heading too) when there are none. |
| G-18 | Mobile bar at 390: "Call" and "Visit" appear only when the listing has a phone or website, each is at least 40 px tall, and the bar does not hide the footer links at the end of the page (page has bottom padding equal to the bar height). |
| G-19 | The owner bar renders for the author and for every user with `edit_others_posts` (editor, admin), on published listings too, with the label "You're viewing this as the owner.": record that staff see "owner" wording on a listing they do not own. Status chip text equals the listing's real status (a published listing shows its "Published"-equivalent chip); a guest and a non-owner member never see the bar; the Feature button shows only to the owner of a non-featured listing, and a featured listing shows "Featured (permanent)" or "Featured until {site-formatted date}". |
| G-20 | The page has exactly one `h1` (the title), tab panels use `h2` or lower in order, and every image has a non-empty alt except decorative icons marked `aria-hidden`. |

## Known defects (expected-fail)
- `listing-detail-file-field-renders-as-image.md` step 3: the file-type field's tab (`#panel-company`) renders an empty `<dl class="listora-detail__field-list">` instead of the image because the loop asks `wb_listora_field_display( $field, $meta, $type, true )` first; Overview correctly hides the raw id (step 2 passes).
- `type-field-visibility-page-api-schema.md` (Not verified list): `detail.schema.telephone` in the embedded JSON-LD still carries the phone when the page shows it; the owner question is whether "API only" should also remove it from the embedded schema.

## Not covered yet
- Gallery and lightbox (G-03, G-04): no journey opens the lightbox, tests arrow keys, Escape, focus return or the "View all photos (N)" count.
- Tab keyboard semantics and deep-linking to a tab (G-11).
- The Claim modal (proof text, file type and 5 MB limit, success and error messages) and the Feature button flow (credits, label, permanent vs until date).
- Hours card rendering per day and today's highlight (only the header badge is covered by open-now-badge).
- Favourites on the listing page: count sync with the dashboard and mobile-bar Save (G-07).
- Related Listings section and `relatedCount` (G-17).
- Owner-bar status chip text per status and the expired notice (owned today by submission/owner-opens-own-unpublished-listing, which tests access rather than the chip wording).
- Breadcrumb trail contents and the feature toggle.
- RTL and 200% zoom for the header action row and the modals.
