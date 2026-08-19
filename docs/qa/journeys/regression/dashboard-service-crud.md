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
---

# Dashboard service CRUD actually writes, including with the optional fields left blank

Regression sentinel for the two defects that shipped with the 1.6.0 wiring of
`saveService` / `editService` / `deleteService` to `Services_Controller`.

**Why this journey exists separately from `dashboard-services-modal.md`:** that
journey's step 4 clicks "Save Service" on an empty form. The title guard fires
first and returns before any request is made, so it passed while saving was
completely broken. A step that cannot reach the code it claims to cover is not
coverage. Every step below reaches the network.

The two defects:

1. **Blank Price or Duration made saving impossible.** Both are optional inputs
   on the form; both are declared with a numeric type on the route (`price`
   number, `duration_minutes` integer). The handler sent them as `''`
   regardless, and WordPress validates a declared arg the moment it is present
   — `''` satisfies neither type. Every save by a member who did not type a
   price failed with `price is not of type number.`
2. **"Add Service" after an "Edit" overwrote the edited service.** `editService`
   marks the form with `data-editing-service-id` so the save updates that row.
   Nothing cleared the mark, and one handler serves both "Add Service" and
   "Cancel", so the next Add reopened a form still flagged as an edit and
   saving it PUT over the earlier service instead of creating a new one.

## Setup

- Site: `$SITE_URL`; owner = `OWNER_LOGIN` owning `LISTING_ID`.
- Start from a listing with **zero** services so the counts below are unambiguous.

## Steps

### 1. Create with Price and Duration left blank — the reported failure
- **Action**: `playwright_navigate $SITE_URL/my-listings/?autologin=<OWNER_LOGIN>`, click the gear (`aria-label="Manage services"`) on the `LISTING_ID` row, click "Add Service". Fill **only** Service Name = `Blank Numerics Service`. Leave Price, Duration and Category untouched. Click "Save Service".
- **Expect**: a `POST /listora/v1/listings/<LISTING_ID>/services` returning **201**, then a page reload showing the new row. **No** toast.
- **Verify**:
  ```js
  // Before clicking Save, capture the request:
  //   playwright_network_requests → find the POST to /services
  //   status === 201
  //   JSON.parse(request.postData) has NO 'price' key and NO 'duration_minutes' key
  ```
  ```sql
  SELECT title, price, duration_minutes FROM wp_listora_services
   WHERE listing_id = <LISTING_ID> AND title = 'Blank Numerics Service';
  -- expect exactly 1 row
  ```
- **On fail**: if the response is 400 with `price is not of type number.` or
  `duration_minutes is not of type integer.`, `saveService` is sending the
  empty strings again — the keys must be OMITTED when blank, not sent empty.
- **likely_files**: `src/interactivity/store.js` (`saveService`), `includes/rest/class-services-controller.php`

### 2. Create with every field filled
- **Action**: "Add Service" again. Name = `Full Service`, Price = `49.50`, Price Type = `Hourly`, Duration = `45`, Category = the first real option.
- **Expect**: 201; the row shows the formatted price and duration.
- **Verify**:
  ```sql
  SELECT price, price_type, duration_minutes FROM wp_listora_services
   WHERE listing_id = <LISTING_ID> AND title = 'Full Service';
  -- expect 49.50 / hourly / 45
  ```
- **On fail**: check the payload coerces — `price` must be a JSON number, not a
  string, and `categories` must be an array of integers.

### 3. Edit updates the row it was opened for — it does not duplicate
- **Action**: click Edit on the `Full Service` row. Confirm the form is populated. Change Price to `60`. Click "Save Service".
- **Expect**: a `POST /listora/v1/services/<SERVICE_ID>` (not the collection route) returning 200. Still **two** services on this listing.
- **Verify**:
  ```sql
  SELECT COUNT(*) FROM wp_listora_services WHERE listing_id = <LISTING_ID>;  -- expect 2
  SELECT price FROM wp_listora_services WHERE title = 'Full Service';        -- expect 60.00
  ```
- **On fail**: a third row means `data-editing-service-id` was never set by `editService`.

### 4. Add Service straight after an Edit creates — it does not overwrite (defect 2)
- **Action**: **Without reloading**, click Edit on `Full Service` again (form populates, flagged as an edit). Now click "Add Service".
- **Expect**: the form stays OPEN and is **empty** — every input cleared, every select back to its first option — and `data-editing-service-id` is gone.
- **Verify**:
  ```js
  const f = document.querySelector('#services-panel-<LISTING_ID> .listora-dashboard__service-form');
  f.hidden;                                          // expect false — Add opens, never toggles shut
  f.dataset.editingServiceId;                        // expect undefined
  f.querySelector('[name="service_title"]').value;   // expect ""
  f.querySelector('[name="service_price"]').value;   // expect ""
  ```
- **Action**: fill Name = `Third Service`, click "Save Service".
- **Expect**: `POST /listora/v1/listings/<LISTING_ID>/services` (the COLLECTION route), 201.
- **Verify**:
  ```sql
  SELECT COUNT(*) FROM wp_listora_services WHERE listing_id = <LISTING_ID>;  -- expect 3
  SELECT price FROM wp_listora_services WHERE title = 'Full Service';        -- expect 60.00, UNCHANGED
  ```
- **On fail**: if `Full Service` now reads `Third Service`, or the count is
  still 2, the edit flag survived into the create — `toggleServiceForm` is not
  resetting on Add.
- **likely_files**: `src/interactivity/store.js` (`toggleServiceForm`, `resetServiceForm`, `editService`)

### 5. Delete removes the row and requires confirmation
- **Action**: click Delete on `Third Service`.
- **Expect**: the design-system confirm modal appears (never a native `confirm()`). Dismiss it — the row stays and no request is sent. Click Delete again and confirm.
- **Expect**: `DELETE /listora/v1/services/<ID>` 200, the row disappears without a page reload.
- **Verify**:
  ```sql
  SELECT COUNT(*) FROM wp_listora_services WHERE listing_id = <LISTING_ID>;  -- expect 2
  ```
- **On fail**: a deletion happening with no modal at all means the
  `listoraConfirm` guard is failing open — a destructive action must abort when
  the modal is unavailable, not proceed.

### 6. Another member cannot write to this listing's services
- **Action**: as a different logged-in member, `POST /listora/v1/listings/<LISTING_ID>/services` with a valid body.
- **Expect**: 403.
- **On fail**: `create_service_permissions` is not checking listing ownership.

### 7. 390px viewport
- **Action**: `playwright_resize 390 844`, reload, open the panel, run step 1 again with a different title.
- **Expect**: the form is usable, the Save button meets the 40px tap target, and the page does not scroll horizontally.
- **Verify**:
  ```js
  document.documentElement.scrollWidth <= window.innerWidth;   // expect true
  const b = document.querySelector('#services-panel-<LISTING_ID> .listora-dashboard__service-form-actions .listora-btn--primary');
  b.getBoundingClientRect().height >= 40;                      // expect true
  ```

## Cleanup

```sql
DELETE FROM wp_listora_services WHERE listing_id = <LISTING_ID>;
```
