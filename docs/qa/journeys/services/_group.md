---
group: services
plugin: wb-listora
scope: Services a listing offers - the member dashboard Services modal (create, edit, photo, delete), the wp-admin Services box, the public Services tab on a listing, and the per-listing-type switch that turns services off
journeys: 4
site_profile: required
---
# Services: what an agent checks here

## Scope
Covers the `listora_services` rows of a listing (title, description, price, price type, duration, status, categories, photo) through every entry point: dashboard modal (`templates/blocks/user-dashboard/tab-listings.php`, actions in `src/interactivity/store.js`), wp-admin Services box (`includes/admin/class-services-metabox.php`), REST (`includes/rest/class-services-controller.php`, `POST|PUT|DELETE /listora/v1/listings/{id}/services...`), and the public Services tab and card (`templates/blocks/listing-detail/tabs.php`). Also the Type Editor "Services" checkbox (`includes/admin/class-type-editor.php`) and the "N services" tag on dashboard rows.

Does NOT cover (neighbouring owner in brackets):
- The rest of the dashboard (tabs, listing rows, renew modal, menus) (dashboard-account). Only the Services button, count tag and the Services modal are checked here.
- The rest of the listing page (header, gallery, other tabs) (listing-detail); the Services tab is checked here.
- Cross-listing service search and compare, the `booking_url` field and the `wb_listora_after_service_detail` hook output (Pro: `wb-listora-pro/docs/qa/journeys/services/_group.md`).
- Service categories as a taxonomy admin screen (admin-listings).

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| S1 | Dashboard > My Listings, row with Services button and count tag | `$SITE_URL{dashboard}` (tab Listings) | {owner} | `templates/blocks/user-dashboard/tab-listings.php` |
| S2 | Services modal, list state | S1, click "Services" (`.listora-dashboard__services-toggle`) | {owner} (own listings only) | `tab-listings.php` (`#services-panel-{id}`) |
| S3 | Services modal, Add Service form open | S2, click "Add Service" | {owner} | `tab-listings.php` |
| S4 | Services modal, Edit form open | S2, click a row's Edit icon | {owner} | `tab-listings.php`, `store.js` `editService` |
| S5 | Delete confirmation | S2, click a row's Delete icon | {owner} | `store.js` `deleteService` + shared confirm (`assets/js/shared/confirm.js`) |
| S6 | Listing page, Services tab | `$SITE_URL/listing/{listing}/` > tab "Services" (`#tab-services`) | all | `templates/blocks/listing-detail/tabs.php` |
| S7 | Listing page, a service card with a long description | S6 with a description over 120 characters | all | same, `store.js` `toggleServiceDesc` |
| S8 | wp-admin listing edit: Services box | `$SITE_URL/wp-admin/post.php?post={listing_id}&action=edit` > "Services" box (`#wb_listora_services`) | {admin}, {editor} | `includes/admin/class-services-metabox.php` |
| S9 | wp-admin Type Editor | `$SITE_URL/wp-admin/admin.php?page=listora-listing-types` (edit a type) > "Services" checkbox `#listora-type-services` | {admin} | `includes/admin/class-type-editor.php` |
| S10 | Listing of a type with services off | S1/S6/S8 for a listing of that type | all | same files; services hidden, rows kept |
| S11 | REST | `GET|POST /wp-json/listora/v1/listings/{id}/services`, `PUT|DELETE .../services/{service_id}`, `POST .../services/reorder` | public read; write for owner/staff | `includes/rest/class-services-controller.php` |

## Components on each surface
**S1 row** `.listora-dashboard__row` (inside the Listings tab): tag `span.listora-dashboard__services-count` "N service|services" (absent for a type with services off), actions cluster with `button.listora-dashboard__services-toggle.listora-btn--sm` labelled "Services" (`data-wp-context` carries `servicesListingId`).

**S2 modal** `div.listora-dashboard__services-panel#services-panel-{id}[role=dialog][aria-modal=true][hidden]`:
1. Backdrop `.listora-dashboard__services-backdrop` (click closes).
2. Dialog `.listora-dashboard__services-dialog` with close button `.listora-dashboard__services-close` (aria-label "Close").
3. Header `.listora-dashboard__services-header`: title "Services for \"{listing title}\"" (the `aria-labelledby` target) and `button.listora-dashboard__add-service-btn` "Add Service".
4. Form region `.listora-dashboard__service-form[hidden]` (S3/S4): photo picker `.listora-dashboard__service-photo` (preview `img[data-listora-service-photo-preview]`, empty label "Choose photo", hidden `input[type=file][name=service_photo_file]`, hidden `service_image_id`, remove button `[data-listora-service-photo-remove]`), `input[name=service_title]` (required, placeholder "e.g., Teeth Cleaning"), `textarea[name=service_description]`, `input[name=service_price][type=number][step=0.01][min=0]`, `select[name=service_price_type]`, `input[name=service_duration][type=number][min=0]`, categories `fieldset.listora-dashboard__service-cats` with `input[name="service_category[]"]` per `listora_service_cat` term (or "No service categories yet."), actions `.listora-dashboard__service-form-actions`: "Save Service" and "Cancel".
5. List `.listora-dashboard__services-list`: per row `.listora-dashboard__service-row[data-service-id]` = drag glyph `.listora-dashboard__service-drag` (aria-hidden), thumb `img.listora-dashboard__service-thumb` (40x40, alt = title), title, price (site currency), duration, actions: Edit icon button (aria-label "Edit"), Delete icon button (aria-label "Delete").

**S6 tab** `button#tab-services[role=tab]` "Services" with `span.listora-detail__tab-count` (number of active services; the tab is absent when 0). Panel `#panel-services[role=tabpanel]` > `.listora-detail__services-grid` > one `.listora-detail__service-card` each: image `.listora-detail__service-image`, title `.listora-detail__service-title`, meta `.listora-detail__service-meta` (price `.listora-detail__service-price` in site currency, duration `.listora-detail__service-duration` as "N min" or "Nh Nmin"/"N hours"), description `p.listora-detail__service-desc` (modifier `--collapsed` and a `button.listora-detail__service-toggle` "Details" when the plain text is over 120 characters), and the extension point `wb_listora_after_service_detail`.

**S8 box** `.wb-listora-services-metabox`: intro text, count line `.wb-listora-services-metabox__count`, scrolling table with columns Photo, Title, Price, Type, Duration (min), Status (Active/Inactive), Category (checkbox list `fieldset.wb-listora-services-metabox__cats`), Delete ("Remove on save"); photo cell with Choose / Remove (`[data-listora-svc-choose]`, `[data-listora-svc-remove]`); "Add new services" table with "+ Add Another Service" (`[data-listora-svc-add-row]`) and per-row "Remove row"; footer hint "Click Update / Publish above to save changes to services. Leave a new-service title blank to skip that row."

**S9** checkbox "Services" with description "Off for types like Jobs. Saved services are hidden, not deleted."

## States to force
| Surface | State | How |
|---|---|---|
| S2 | Zero / one / many services | listing with 0, 1, 12 `QA-svc` rows; empty list shows no stray header |
| S3 | Blank price and duration | submit with only a title (the reported failure) |
| S3 | Boundary input | 120-char title, markup in description, emoji, negative price, price 0, price with 3 decimals, duration 0 and 99999 |
| S2 | Photo present / absent | service with image, without |
| S2 | No categories exist | delete all `listora_service_cat` terms: "No service categories yet." |
| S5 | Delete confirm cancelled / accepted | Cancel then Delete |
| S6 | Services tab absent | listing with zero active services; also inactive-only |
| S7 | Long description | description of 121+ characters vs exactly 120 |
| S6 | Zero-decimal currency | Settings > General currency JPY, then restore |
| S10 | Type with services off | Type Editor uncheck "Services" for one type, keep another on |
| S2 | Other member's listing | open the dashboard as {other}: no Services button for a listing they do not own; REST write by {other} refused |
| S2 | 820 and 390 viewports, dark, unbridged theme | resize; toggle dark mode; `theme.unbridged` |
| S2 | Slow or failed save | throttle then block `POST .../services`: message "Could not save the service. Please try again.", button re-enabled |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| Type Editor > "Services" (`services_enabled` on the type) | on / off | off: no "N services" tag and no Services button on S1, no S2 panel rendered, no Services tab on S6, Services box hidden on S8, REST write refused; on: all return with the rows intact |
| Settings > General > Currency (`currency`) | symbol/code | price on S2 rows, S6 cards and S8 follows it (zero-decimal currencies show no decimals) |
| Service category terms (Services Categories taxonomy `listora_service_cat`) | terms | S3 checkboxes and S8 checkbox list list the same terms |
| Filter `wb_listora_after_service_detail` | code | Pro and themes add output after each card on S6 |

## Fixtures and setup
- Personas: `bash bin/qa-fixtures.sh`; placeholders from `docs/qa/sites/<site-host>.json` ({owner}, {other}, {editor}, {admin}, {listing}).
- {owner} owns {listing} of a type with services on, starting with ZERO services. A second listing of a type with services off. At least three `listora_service_cat` terms (`wp term create listora_service_cat QA-svc-cat-A`), one image in the media library.
- Seed rows prefixed `QA-J1-` (journey cleanup deletes by that prefix): `DELETE FROM ${PREFIX}listora_services WHERE listing_id = $LISTING_ID AND title LIKE 'QA-J1-%'`. Delete is a soft delete (`status = 'deleted'`), so count with `status != 'deleted'`.
- Record before/after: `wb_listora_settings.currency`, the type's `services_enabled`, the three QA terms.
- Restore afterwards: currency back to the site profile value, type flag back to on, QA services, terms and test images removed.

## Run order
Browser first, in the order an owner meets them.
1. `dashboard-services-modal.md` - mode browser, high: the Services panel is a modal overlay in the viewport, closes three ways, focus returns, one panel at a time.
2. `listing-services-crud-and-photos.md` - mode mixed, critical: create with blank price and duration, edit-not-duplicate, delete with confirm, categories kept, wp-admin box and category checkboxes, photos.
3. `service-details-toggle.md` - mode browser, normal: long descriptions collapse and the Details toggle expands them, per card.
4. `services-per-listing-type.md` - mode mixed, normal: switching services off for a type hides every surface and deletes nothing.
Then run: `wb-listora-pro/docs/qa/journeys/services/_group.md`.

## Group-specific micro checks
| id | Check |
|---|---|
| G-01 | The count tag on S1 ("N services") equals the number of rows in the S2 list, the number on the `#tab-services` count badge when all are active, and the "N" counted in the S8 count line; singular "1 service" not "1 services". |
| G-02 | Active vs inactive: an `inactive` service appears in S2 and S8 but not on S6, so the S6 tab count may be smaller than the S2 count; the tab disappears when only inactive services exist. |
| G-03 | The modal title on S2 reads `Services for "{title}"` with the real listing title (HTML-escaped: a title with `&` or a quote shows as typed, not as an entity). |
| G-04 | Opening Services for listing A and then listing B closes A first (one `[role=dialog]` visible); the page behind does not scroll while a modal is open and scrolls again after closing. |
| G-05 | Close affordances: the `x` button, a click on the backdrop and Escape each close the modal and return focus to the "Services" button that opened it (check `document.activeElement`). |
| G-06 | Add Service with an empty title does not fire a request and names the field; the title field has `required` and a visible asterisk. |
| G-07 | Price wording on S6 by Price Type: `free` prints "Free", `contact` prints "Contact for price", `starting_from` prints "From {price}", `hourly` prints "{price}/hr", fixed prints the bare price, all in the site currency; a blank price with a fixed type prints no price element at all (no "0.00", no lone symbol). S2 rows print `wb_listora_format_currency(price)` regardless of type, so compare the two surfaces and record the difference. |
| G-08 | Duration formatting on S6: 45 renders "45 min", 60 renders "1 hour", 90 renders "1h 30min" (or the translated equivalent), 120 renders "2 hours"; 0 or blank renders nothing. |
| G-09 | The drag glyph `.listora-dashboard__service-drag` is shown on each S2 row but no drag-to-reorder script exists in `store.js`; `POST .../services/reorder` exists in REST. Expect the glyph to be inert (record as a dead affordance) and confirm nothing reorders on drag. |
| G-10 | Row thumbnail is 40 x 40, cropped not stretched, alt equals the service title; a service with no photo shows a designed placeholder, not a broken image. |
| G-11 | The Edit form pre-fills every field including the photo preview and the checked categories; Cancel then Add Service shows a clean empty form (no leftovers from the edit). |
| G-12 | Delete icon button has the accessible name "Delete"; clicking it opens the shared design-system confirm dialog (never the browser `confirm()`), Cancel keeps the row, confirm removes it without a page reload and the S1 count tag decrements. |
| G-13 | S6 description toggle: a 120-character description shows no toggle, 121 shows one; the toggle label changes between "Details" and its collapsed counterpart and `aria-expanded` flips; each card toggles independently. |
| G-14 | S8 Photo column is the first column (8% width) and the Category cell scrolls inside its own box without pushing the Delete column off screen at 1440; the table scrolls horizontally inside `.wb-listora-services-metabox__scroll` at 820. |
| G-15 | S8 new-service rows with a blank title are skipped silently on Update (no empty service row is created), and the note "Set a title to create." is visible in that row. |
| G-16 | S9: unchecking Services for a type keeps rows (`status != 'deleted'` count unchanged) and rechecking shows the same rows with their photos and categories. |

## Known defects (expected-fail)
- No Free-folder step is marked `expected-fail`. G-09 (reorder handle with no reorder behaviour) is read from source and is not yet tracked.
- `listing-services-crud-and-photos.md` carries the note that a foreign image on update may clear the photo (coverage gap, step 12), listed in Not covered yet below.

## Not covered yet
- Reordering services: the dashboard handle, the REST route `services/reorder` and any admin UI (three entry points rule) have no journey and no UI wiring.
- Service status Active/Inactive toggle from the dashboard modal (the modal form has no status control; wp-admin box has one) and its effect on S6.
- Price type select values (`service_price_type`) in the dashboard form: only the S6 wording is spelled out in G-07, no journey iterates each value end to end.
- Deleted-author listing and trashed listing: services panel and REST for a listing the owner no longer owns.
- RTL for the modal and the card grid.
- Keyboard focus trap inside the modal (Tab from the last control returns to the first, not behind the backdrop).
- Pagination or a cap for a listing with hundreds of services (S2 list and S6 grid render all rows).
