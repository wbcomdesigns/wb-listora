---
journey: dashboard-active-filter-status
plugin: wb-listora
priority: high
roles: [member, administrator]
covers: ["#9962484094", "My Listings Active filter", "filter state derivation"]
prerequisites:
  - "Renewal feature toggle ON (the filter dropdown only renders then)"
  - "A user owning listings with mixed statuses: publish + listora_deactivated (or draft/pending)"
estimated_runtime_minutes: 2
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# My Listings "Active" filter excludes non-published listings

The per-row state in `templates/blocks/user-dashboard/tab-listings.php` is derived as: `expired` (post status `listora_expired`), else `expiring` (published and inside the renewal window), else `active` ONLY when `post_status === 'publish'`, else `inactive` (deactivated, draft, pending, rejected, awaiting credits, pending verification). `inactive` rows match no Expiry option except "Any expiry". The filter itself is server-side (`?listings_filter=`), see `dashboard-renewal-filter-server-side`; this journey locks the row-state derivation and the Active result.

Roles: the listing owner (actor, member-level); another member sees only their own rows; logged out gets the login prompt; admin as control (their own rows; at 2000+ rows).

## Setup

- Renewal feature ON (`wb_listora_feature_enabled( 'renewal' )`; the Expiry dropdown only renders then; on a free directory with expiry off it is hidden).
- A member owning listings with mixed statuses. QA site: `qa_vendor_01` (user 6) owns 7: published x2 (7379, 845), deactivated (7381), expired (7383), rejected (7380), awaiting credits (7382), pending review (7384).
- Dashboard: `$SITE_URL/my-listings/?tab=listings` (`wb_listora_get_dashboard_url()`).

## Steps

### 1. Row states are honest
- **Action**: as the owner open the My Listings tab and read each `.listora-dashboard__listing-row`'s `data-listora-state`.
- **Expect** (QA member): 7379 `active` (pill "Published"), 845 `active`, 7381 `inactive` ("Deactivated"), 7380 `inactive` ("Rejected"), 7382 `inactive` ("Awaiting Credits"), 7384 `inactive` ("Pending Review"), 7383 `expired` ("Expired"). A published listing inside the renewal window reads `expiring`.

### 2. The Active filter hides inactive rows
- **Action**: choose **Active** in the Expiry select (`#listora-renewal-filter`, options "Any expiry", "Active", "Expiring soon", "Expired") and click **Apply** (the view script also submits on change); URL gains `listings_filter=active`.
- **Expect**: only rows with `data-listora-state="active"` remain; the deactivated / rejected / awaiting-credits / pending rows are gone; the Status select (`#listora-listings-status`) is untouched.

### 3. Any expiry shows everything
- **Action**: choose **Any expiry**.
- **Expect**: `listings_filter` is removed and every row is back, including the `inactive` ones.

### 4. Big site
- **Action**: as the administrator (2,632 listings on the QA site: 2,533 draft, 78 published, 21 expired) open `?tab=listings&listings_filter=active`.
- **Expect**: HTTP 200 in well under a second, 20 rows all `active`, pager "Page 1 of 4 (78 items)"; draft listings (2,533 of them) never appear under Active. `?listings_filter=expired` shows "Page 1 of 2 (21 items)".

### 5. Positive control, role ladder
- **Control**: step 1's `active` rows prove the Active filter has something to show; without published rows step 2 would pass trivially.
- **Another member**: sees only their own rows (no other member's listings under any filter).
- **Logged out**: login prompt, no rows.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Draft / deactivated rows state `active` | `publish` check dropped | `templates/blocks/user-dashboard/tab-listings.php:165-174` |
| Active filter shows non-published | query arg | `blocks/user-dashboard/render.php:160-231` |
| Filter select missing | renewal off | `templates/blocks/user-dashboard/tab-listings.php:26` (`$listora_renewal_enabled`), `:136` (the select is wrapped in it) |

## Verified

Steps 1 (states and pills for all 7 rows) and 4 (rows, states, pager text, ~0.2 s) were read live with curl as `qa_vendor_01` and `varundubey`. Steps 2 and 3 as a click-through were NOT run; the URL forms were fetched directly.
