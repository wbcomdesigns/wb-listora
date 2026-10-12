---
journey: dashboard-service-crud
plugin: wb-listora
priority: critical
roles: [subscriber]
covers: [dashboard-services-panel, services-rest-create, services-rest-update, services-rest-delete, optional-numeric-fields, create-after-edit]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member who owns at least 1 listing (capture OWNER_LOGIN + LISTING_ID)"
  - "At least one term in the listora_service_cat taxonomy"
estimated_runtime_minutes: 6
covers_card: 10199116630
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Dashboard service CRUD actually writes, including with the optional fields left blank

Regression sentinel for two defects that shipped with the dashboard wiring of `saveService` / `editService` / `deleteService` to `Services_Controller`.

**Why this journey exists separately from `dashboard-services-modal.md`:** that journey's save step on an empty form returns at the title guard before any request is made, so it passed while saving was completely broken. A step that cannot reach the code it claims to cover is not coverage. Every step below reaches the network.

The two defects:

1. **Blank Price or Duration made saving impossible.** Both are optional inputs but declared numeric on the route (`price` number, `duration_minutes` integer); the handler once sent `''`, which satisfies neither type. The keys must be OMITTED when blank.
2. **"Add Service" after an "Edit" overwrote the edited service.** `editService` marks the form with `data-editing-service-id`; nothing cleared it, and one handler serves both "Add Service" and "Cancel", so the next Add saved over the earlier service.

Roles: the listing owner (actor, member-level); another member and logged out are refused (403 / 401); admin as control.

## Setup

- Site `$SITE_URL`; owner = `OWNER_LOGIN` owning `LISTING_ID` of a type with services enabled. QA site: `qa_vendor_01` owns 7379 (a restaurant; `GET /listings/7379/services` returns 0 services). Start from a listing with **zero** services so counts are unambiguous.
- Dashboard: `$SITE_URL/my-listings/?tab=listings` (`wb_listora_get_dashboard_url()`).
- DB note: delete is a SOFT delete: the row stays with `status = 'deleted'` and is excluded from the REST list and the panel. Count with `status <> 'deleted'` (or via `GET /listings/<id>/services` `total`), not bare `COUNT(*)`.

## Steps

### 1. Create with Price and Duration left blank (the reported failure)
- **Action**: as the owner open the dashboard, click **Services** on the `LISTING_ID` row (the button `.listora-dashboard__services-toggle`, a wrench icon + the text "Services"), click **Add Service**. Fill ONLY Service Name = `QA-J1- Blank Numerics`. Leave Price, Duration and Categories untouched. Click **Save Service**.
- **Expect**: `POST /listora/v1/listings/<LISTING_ID>/services` returns **201** (`price: null`, `duration_minutes: null`, `price_type: "fixed"`), then the page reloads showing the new row. **No** toast.
- **Verify**:
  - network: the request JSON has NO `price` and NO `duration_minutes` key;
  - `SELECT title, price, duration_minutes, status FROM wp_listora_services WHERE listing_id = <LISTING_ID> AND title = 'QA-J1- Blank Numerics';` exactly 1 row, status `active`.
- **Negative control**: the same body with `"price":"","duration_minutes":""` is rejected 400 `rest_invalid_param` ("price is not of type number.", "duration_minutes is not of type integer."), proving the route validates and that omitting the keys is what makes step 1 work.
- **On fail**: `src/interactivity/store.js:3256-3264` (`saveService` must omit blank numerics), `includes/rest/class-services-controller.php`.

### 2. Create with every field filled
- **Action**: Add Service again. Name = `QA-J1- Full`, Price = `49.50`, Price Type = `Hourly`, Duration = `45`, Categories = the first real checkbox (if any exist; else none).
- **Expect**: 201; the row shows the formatted price and duration.
- **Verify**: `SELECT price, price_type, duration_minutes FROM wp_listora_services WHERE title = 'QA-J1- Full';` is `49.50 / hourly / 45`. `price` is sent as a JSON number, `categories` as an array of integers.

### 3. Edit updates the row it was opened for; it does not duplicate
- **Action**: click **Edit** (`aria-label="Edit"`, `actions.editService`) on `QA-J1- Full`, confirm the form populates, set Price = `60`, **Save Service**.
- **Expect**: `POST /listora/v1/services/<SERVICE_ID>` (NOT the collection route) returns 200 with `price: 60`. Still two active services on this listing.
- **Verify**: active count 2; `price` 60.00.
- **On fail**: a third row means `data-editing-service-id` was never set by `editService` (`store.js:3383`).

### 4. Add Service straight after an Edit creates; it does not overwrite (defect 2)
- **Action**: WITHOUT reloading, click **Edit** on `QA-J1- Full` again, then **Add Service**.
- **Expect**: the form stays OPEN and EMPTY (every input cleared, selects back to their first option) and `data-editing-service-id` is gone.
- **Verify**:
  ```js
  const f = document.querySelector('#services-panel-<LISTING_ID> .listora-dashboard__service-form');
  ({ hidden: f.hidden, editing: f.dataset.editingServiceId,
     title: f.querySelector('[name="service_title"]').value, price: f.querySelector('[name="service_price"]').value })
  // expect { hidden: false, editing: undefined, title: "", price: "" }
  ```
- **Action**: Name = `QA-J1- Third`, **Save Service**.
- **Expect**: `POST /listora/v1/listings/<LISTING_ID>/services` (the COLLECTION route), 201; active count 3; `QA-J1- Full` still `60.00`, unchanged.
- **On fail**: if `Full` now reads `Third`, or the count is still 2, `toggleServiceForm` is not resetting on Add (`store.js:2994-3040`).

### 5. Delete removes the row and requires confirmation
- **Action**: click **Delete** (`aria-label="Delete"`, `actions.deleteService`) on `QA-J1- Third`.
- **Expect**: the design-system confirm modal ("Delete this service? This cannot be undone.") appears (never a native `confirm()`). Dismiss it: the row stays, no request sent. Click Delete again and confirm: `DELETE /listora/v1/services/<ID>` returns 200 `{"deleted":true,"id":<ID>}` and the row disappears without a reload.
- **Verify**: active count 2 (the row remains with `status = 'deleted'`).
- **On fail**: a deletion with no modal means `listoraConfirm` fails open (`store.js:3416-3430` must abort when it is unavailable).

### 6. Role ladder: nobody else can write
- **Another member** (`author1`): `POST /listora/v1/listings/<LISTING_ID>/services` returns 403 `listora_forbidden` ("You do not have permission to manage services for this listing."); `DELETE /listora/v1/services/<ID>` returns 403 ("You do not have permission to manage this service.") (both verified).
- **Logged out**: `POST` returns 401 `listora_unauthorized` (verified); `GET /listings/<id>/services` is public (the services show on the listing page).
- **Admin / editor**: allowed (control).

### 7. 390px viewport
- **Action**: resize 390x844, reload, open the panel, run step 1 again with a different title.
- **Expect**: form usable, Save button >= 40px tall, no horizontal page scroll:
  ```js
  document.documentElement.scrollWidth <= window.innerWidth;
  document.querySelector('#services-panel-<LISTING_ID> .listora-dashboard__service-form-actions .listora-btn--primary').getBoundingClientRect().height >= 40;
  ```

## Cleanup

`DELETE FROM wp_listora_services WHERE listing_id = <LISTING_ID> AND title LIKE 'QA-J1-%';` (soft-deleted rows included).

## Not verified

The REST contract in steps 1 to 3, 5 and 6 (201 with null numerics, 400 for blank strings, 201 full, `POST /services/<id>` 200, soft delete `{"deleted":true}`, 403 / 401) was run live as `qa_vendor_01`, `author1` and anonymous on listing 7379 and cleaned up. The in-page behaviour (form, Edit then Add reset in step 4, confirm modal, 390 layout) was NOT run in a browser; it is verified against `src/interactivity/store.js` only.
