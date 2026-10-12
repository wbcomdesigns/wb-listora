---
journey: listing-services-crud-and-photos
group: services
actor: customer
mode: mixed
plugin: wb-listora
priority: critical
roles: [subscriber, other-member, guest, editor, admin]
covers: [services-category-checkboxes, dashboard-services-panel, services-rest-create, services-rest-update, services-rest-delete, optional-numeric-fields, create-after-edit, service-categories-kept, services-metabox, photo-column, media-library-picker, service-photo-kept-on-member-edit, attachment-ownership, services-box-follows-type-flag, bc-10354998955, bc-10393550227, card-10199116630, card-10331867610, card-10331936497, card-10354973676]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "{owner} owns {listing} of a type with services enabled; start from a listing with ZERO services so counts are unambiguous"
  - "At least three terms in `listora_service_cat`; at least one image in the media library; {other}, {editor}, {admin}"
estimated_runtime_minutes: 16
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes:
  - "ora:services/services-box-category-checkboxes.md"
  - "ora:regression/directory-search-and-small-fixes.md (the Services-box category checkbox step)"
  - "ora:regression/dashboard-service-crud.md"
  - "ora:regression/services-photo-upload.md"
  - "ora:regression/surfaces-location-and-service-photos.md (steps 3-4 services half; steps 1-2 moved to admin/listing-location-terms-follow-address.md)"
fails_on: [9deb1456, 771baeeb]
---

# Services are created, edited, photographed and deleted from the dashboard and wp-admin without losing data

## Why
A service form that cannot save with a blank price, an "Add Service" that overwrites the service just edited, an edit that drops categories or an admin-added photo, or a foreign file bound to a service loses the owner's data or leaks someone else's media. Every step below reaches the network (a save on an empty form returns at the title guard and proves nothing).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Dashboard `$DASH` = `wb_listora_get_dashboard_url()`, tab `listings`. Service delete is a SOFT delete: the row stays with `status='deleted'`; count with `status <> 'deleted'` or `GET /listings/<id>/services` `total`.
- Capture `$LISTING_ID`, `$SERVICE_*` ids and `$N_ACTIVE` from the run, never fixed ids.
- Cleanup: `DELETE FROM ${PREFIX}listora_services WHERE listing_id = $LISTING_ID AND title LIKE 'QA-J1-%'` (soft-deleted rows included); restore the type's "Services enabled" flag.

## Steps

### 1. Create with Price and Duration left blank (the reported failure)
- **Action**: as {owner} click **Services** (`.listora-dashboard__services-toggle`) on the {listing} row, **Add Service**, fill ONLY Service Name `QA-J1- Blank Numerics`, leave Price, Duration, Categories, **Save Service**.
- **Expect**: `POST /listora/v1/listings/<id>/services` returns **201** (`price: null`, `duration_minutes: null`, `price_type: "fixed"`), the page reloads showing the row, no toast.
- **Read-back**: the request JSON has NO `price` and NO `duration_minutes` key; exactly 1 row with status `active`. Negative control: the same body with `"price":"","duration_minutes":""` is `400 rest_invalid_param` ("price is not of type number.", "duration_minutes is not of type integer.").
- **On fail**: `src/interactivity/store.js:3256-3264` (`saveService` must omit blank numerics), `includes/rest/class-services-controller.php`.

### 2. Create with every field filled
- **Action**: Add Service again: Name `QA-J1- Full`, Price `49.50`, Price Type `Hourly`, Duration `45`, Categories = first real checkbox.
- **Expect**: 201; the row shows the formatted price and duration; `price` sent as a JSON number, `categories` as integers.
- **Read-back**: DB `49.50 / hourly / 45`.

### 3. Boundary input
- **Action**: Service Name empty, whitespace only, 300 characters, `<script>x</script>`, emoji; Price `-1`, `0`, `1e9`, `abc`; Duration `0`, `-5`, `1.5`.
- **Expect**: empty/whitespace name refused at the title guard; long name refused or truncated with a message (a named outcome decided by the schema max, record it); markup stored escaped, never executed; negative/non-numeric price and non-integer duration refused `400 rest_invalid_param`; `0` price accepted as free. Not verified live (added in consolidation).

### 4. Edit updates the row it was opened for; it does not duplicate
- **Action**: click **Edit** (`aria-label="Edit"`, `actions.editService`) on `QA-J1- Full`, set Price `60`, **Save Service**.
- **Expect**: `POST /listora/v1/services/<SERVICE_ID>` (not the collection route) returns 200 `price: 60`; active count unchanged.
- **On fail**: a third row means `data-editing-service-id` was never set (`store.js:3383`).

### 5. Add after Edit creates; it does not overwrite
- **Action**: WITHOUT reloading, **Edit** `QA-J1- Full` again, then **Add Service**; evaluate
  `f = document.querySelector('#services-panel-<id> .listora-dashboard__service-form'); ({hidden:f.hidden, editing:f.dataset.editingServiceId, title:f.querySelector('[name="service_title"]').value, price:f.querySelector('[name="service_price"]').value})`.
- **Expect**: `{hidden:false, editing:undefined, title:"", price:""}` (form open and empty, selects on first option). Then Name `QA-J1- Third`, **Save**: `POST` to the COLLECTION route, 201; `$N_ACTIVE` +1; `QA-J1- Full` still `60.00`.
- **On fail**: `toggleServiceForm` not resetting on Add (`store.js:2994-3040`).

### 6. Delete removes the row and requires confirmation
- **Action**: **Delete** (`aria-label="Delete"`) on `QA-J1- Third`.
- **Expect**: the design-system confirm modal ("Delete this service? This cannot be undone."), never a native `confirm()`; dismiss: row stays, no request. Confirm: `DELETE /listora/v1/services/<id>` returns 200 `{"deleted":true,"id":<id>}`, the row disappears without reload.
- **Read-back**: active count back to previous (row remains `status='deleted'`).
- **On fail**: `listoraConfirm` failing open (`store.js:3416-3430`).

### 7. Editing keeps categories the form cannot show (card 10354998955)
- **Action**: as {admin} in wp-admin assign THREE `listora_service_cat` terms (CAT_A, CAT_B, CAT_C) to `QA-J1- Full`; record the term ids. As {owner} Edit it, change only the title to `QA-J1- Full renamed`, Save.
- **Expect**: `POST /services/<id>` 200; response `categories` and DB still list all three ids.
- **Read-back**: `SELECT term_taxonomy_id FROM wp_term_relationships WHERE object_id=<id>` returns the same 3 rows; negative control: re-save with only CAT_A ticked and the set becomes exactly `[CAT_A]`.
- **fails_on**: 9deb1456 (parent of 038a6017). Not verified live.

### 8. Selected categories are visibly marked (card 10393550227)
- **Action**: give `QA-J1- Full` CAT_A and CAT_B, open its Edit (1440).
- **Expect**: CAT_A and CAT_B checkboxes `checked` AND render a visible selected state (computed appearance not `none` without a custom indicator, or the label has the selected class); unselected ones do not.
- **Read-back**: `GET /services/<id>` `categories` lists exactly CAT_A and CAT_B.
- **fails_on**: 771baeeb. Not verified live.

### 9. The wp-admin Services box and its Photo column
- **Action**: as {admin} open `post.php?post=$LISTING_ID&action=edit`; find `#wb_listora_services` (Screen Options if hidden).
- **Expect**: a table with headers Photo, Title, Price, Type, Duration (min), Status, Category, Delete, and an "Add new services" table with **+ Add Another Service** (`data-listora-svc-add-row`). Categories are checkboxes (a "Category" column).
- **Category checkboxes** (merged from services-box-category-checkboxes): the Category cell is a checkbox list (not a `<select>`) inside a short scrolling container (`fieldset.wb-listora-services-metabox__cats`, `Services_Metabox::render_category_select()`). Tick a category, Update, reload: the choice is kept; untick, Update, reload: cleared. Read-back: the term relationship on the service row. With 30+ service categories the box scrolls and does not push the Update button off screen (not verified live).

### 10. Choose, pick, save, persist, remove a service photo
- **Action**: fill title `QA-J1-service`, price, duration; click **Choose** (`[data-listora-svc-choose]`); in the WP media frame (title "Choose service photo", images only) select a thumbnail, click "Use this photo"; **Update**, reload; later **Remove**, Update.
- **Expect**: frame closes; 48x48 preview `[data-listora-svc-preview]` shows the image, the button reads "Change", **Remove** appears, hidden `[data-listora-svc-input]` (`wb_listora_services[new][N][image_id]`) holds the attachment id; after reload the thumbnail persists; after Remove `image_id` is NULL and the card has no image.
- **Read-back**: `${PREFIX}listora_services.image_id` equals the attachment id (`save_post()` `:452`, branch `:578-583`).
- **On fail**: `assets/js/admin/services-metabox.js` (enqueued only on the `listora_listing` screen, `enqueue_assets` `:56`).

### 11. The photo shows on the public listing
- **Action**: as {guest} open {listing}, Services tab `#tab-services`.
- **Expect**: the service card shows the image (`templates/blocks/listing-detail/tabs.php:355-360`); after step 10 Remove, no image.

### 12. A member edit keeps a photo an admin added; foreign files are refused
- **Action**: {admin} sets a service photo on {owner}'s service (`PUT /listora/v1/services/{id}` with `image_id`); {owner} then changes only the title (`PUT /listora/v1/services/{id}` `{"title":"QA-J1- renamed"}`).
- **Expect**: `image_id` unchanged after the member edit.
- **Action**: {owner} `PUT` with `image_id` of an attachment uploaded by someone else that is not on this service.
- **Expect**: 200 and `image_id` stored as `0`, never the foreign id (`wb_listora_user_can_attach()` returns false and the controller stores `0`, `class-services-controller.php:316,365`). Positive control: {owner}'s own uploaded attachment IS accepted.
- **fails_on**: not captured (cards 10331867610, 10331936497).

### 13. Services box follows the type's flag
- **Action**: none restated here. `services/services-per-listing-type.md` step 2 owns the "Services enabled" switch for the wp-admin Services box (`#wb_listora_services` absent when off), the dashboard **Services** button and the REST 403.
- **Expect**: run that step once with the box from step 9 open; this journey only requires the box to be present when the flag is on.

### 14. 390px
- **Action**: resize 390x844, open the panel, repeat step 1 with a different title.
- **Expect**: `document.documentElement.scrollWidth <= window.innerWidth`; `.listora-dashboard__service-form-actions .listora-btn--primary` height >= 40; the wp-admin Services box stacks without page-level horizontal scroll.

## Role ladder
| Rung | Expect |
|---|---|
| {owner} | steps 1-8, 10-12 on own listing only |
| {other} | `POST /listora/v1/listings/<id>/services` `403 listora_forbidden` ("You do not have permission to manage services for this listing."); `PUT`/`DELETE /services/<id>` `403` ("You do not have permission to manage this service.") |
| {guest} | `POST` `401 listora_unauthorized`; `GET /listings/<id>/services` is public |
| contributor without `edit_others` | cannot open another's `post.php` (403) |
| {editor}, {admin} | allowed (control); wp-admin box edits any listing's services |

## Pass criteria
1. Create works with blank optional numerics (keys omitted) and with every field; edit updates in place; Add after Edit creates a new row.
2. Delete confirms through the design-system modal and soft-deletes.
3. Edits keep categories and admin photos the form cannot show; a foreign attachment is never bound.
4. The wp-admin box offers Photo with a working media frame, persists and removes the image, and disappears when services are off.
5. {other} 403, {guest} 401 on every write.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Save impossible with blank Price | blank numerics sent as `''` | `store.js:3256-3264` |
| Third row after Edit | editing id not set | `store.js:3383` |
| Add overwrites the edited service | form not reset | `store.js:2994-3040` |
| Delete without modal | `listoraConfirm` fails open | `store.js:3416-3430` |
| Member edit drops admin photo | keep-media call | `class-services-controller.php:358-368`, `includes/media-helpers.php:75` |
| Foreign attachment bound | ownership check | `class-services-controller.php:316,365` |
| No Photo column | header / cell | `class-services-metabox.php:138`, `render_photo_cell` `:382` |
| Choose does nothing | enqueue / script | `enqueue_assets` `:56`, `services-metabox.js` |
| Selection not saved | `save_post()` | `:452`, `:578-583` |
| Services box shown for a services-off type | flag check | `class-services-metabox.php:89` |

## Not verified
- The REST contract in steps 1, 2, 4, 6 and the 403/401 ladder was run live as a member, another member and anonymous on a restaurant listing and cleaned up. The in-page behaviour (form, Edit-then-Add reset, confirm modal, 390 layout), the media frame flow (steps 9-11 need a real browser), steps 7, 8, 12 member half and the foreign-attachment 0-store were not run live. Whether a foreign `image_id` on UPDATE also clears an existing admin photo is not verified.

## Cleanup
- `DELETE FROM ${PREFIX}listora_services WHERE listing_id = $LISTING_ID AND title LIKE 'QA-J1-%'` (soft-deleted rows included).
- Delete the attachments picked or uploaded as service photos in steps 10-12 if they were created for this run.
- Re-tick the type's "Services enabled" flag (step 13) and Save Type.
