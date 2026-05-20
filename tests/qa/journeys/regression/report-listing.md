---
journey: report-listing
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers: [report-listings-feature, listings-report-rest, report-modal, report-admin-column, report-metabox]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "report_listings feature enabled (default)"
  - "At least 1 published listing NOT owned by the test reporter exists (capture as LISTING_ID)"
  - "Test user 'subscriber2' (subscriber) exists"
estimated_runtime_minutes: 5
covers_card: 9906156994
---

# Report a listing (flag for admin review)

A logged-in visitor flags a listing they don't own; the report is stored, surfaced to admins via a Reports column + edit-screen metabox, and an admin clears it. Verifies the full trust loop the directory previously lacked.

## Setup

- Site: `$SITE_URL`
- Reporter: `subscriber2` (autologin via `?autologin=subscriber2`)
- Fixture: 1 published listing whose `post_author` is NOT subscriber2. Capture `LISTING_ID` + slug.
- DB clean (start state):
  ```sql
  DELETE FROM wp_options WHERE option_name = '_listora_listing_reports_<LISTING_ID>';
  ```

## Steps

### 1. Reporter opens a listing they don't own
- **Action**: navigate `$SITE_URL/?autologin=subscriber2` then `$SITE_URL/listing/<slug>`
- **Expect**: `.listora-detail__report-btn` ("Report") visible in the actions row; `#listora-report-modal` present in DOM with a `select[name="reason"]` carrying 6 options (inaccurate, spam, closed, duplicate, offensive, other).
- **On fail**: `blocks/listing-detail/render.php` report button/modal gate (`$listora_can_report`).

### 2. Owner does NOT see the control
- **Action**: navigate as the listing's owner (autologin) to the same listing.
- **Expect**: `.listora-detail__report-btn` absent (you can't report your own listing).
- **On fail**: `$listora_can_report` owner check in `render.php`.

### 3. Submit a report
- **Action**: click `.listora-detail__report-btn`; modal opens (`#listora-report-modal.is-open`); set reason = `closed`, details = "QA test"; click `#listora-report-modal button[type=submit]`.
- **Expect**: `.listora-detail__report-message--success` visible ("Report submitted. Thank you."); form body hidden; REST `POST /listora/v1/listings/<LISTING_ID>/report` returned 200.
- **Verify DB**:
  ```sql
  SELECT option_value FROM wp_options WHERE option_name='_listora_listing_reports_<LISTING_ID>';
  -- 1 entry: user_id, reason='closed', status='open', date
  ```
- **On fail**: `report_listing()` / `report_listing_permissions()` in `includes/rest/class-listings-controller.php`; `actions.submitReport` in `src/interactivity/store.js`.

### 4. Duplicate report blocked
- **Action**: as subscriber2, report the same listing again.
- **Expect**: REST 409 `listora_already_reported`.
- **On fail**: dedup loop in `report_listing()`.

### 5. Admin sees the flag
- **Action**: autologin=1; open `wp-admin/edit.php?post_type=listora_listing` (find LISTING_ID row) and `wp-admin/post.php?post=<LISTING_ID>&action=edit`.
- **Expect**: "Reports" column header present; the row shows a flag icon + count linking to `#wb_listora_reports`. On the edit screen, `#wb_listora_reports` metabox titled "Report (1)" lists the reason label, details, reporter name, and date, plus a "Clear all reports" checkbox + nonce.
- **On fail**: `case 'listora_reports'` in `includes/admin/class-listing-columns.php`; `Report_Metabox::render()`.

### 6. Admin clears the reports
- **Action**: check "Clear all reports", click Update.
- **Expect**: option `_listora_listing_reports_<LISTING_ID>` deleted; `wb_listora_listing_reports_cleared` action fires.
- **Verify DB**:
  ```sql
  SELECT COUNT(*) FROM wp_options WHERE option_name='_listora_listing_reports_<LISTING_ID>'; -- 0
  ```
- **On fail**: `Report_Metabox::save_post()` clear branch (nonce + cap + checkbox).

### 7. Feature toggle gates everything
- **Action**: disable the "Report Listings" feature (Features tab / `wb_listora_features['report_listings']=false`); reload the listing.
- **Expect**: report button absent; `POST /listings/{id}/report` returns 403 `listora_reports_disabled`.
- **On fail**: `wb_listora_feature_enabled('report_listings')` gate in `render.php` + `report_listing_permissions()`.
