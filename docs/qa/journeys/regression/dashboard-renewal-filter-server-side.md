---
journey: dashboard-renewal-filter-server-side
plugin: wb-listora
priority: high
roles: [subscriber, administrator]
covers: [user-dashboard, renewal, card-10294421959]
prerequisites:
  - "Renewal feature enabled"
  - "A member with more than one page (>20) of listings, some expired and some expiring inside renewal_window_days - user 1 on the QA seed"
  - "A member with one active listing and nothing expiring - journey_owner"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The My Listings filter searches every page, not the 20 rows on screen

The Expiry filter once hid rows client-side: on page 1 of a member with many pages "Expired" matched nothing on screen, blanked the panel and said "None of your listings are in this state right now" while expired listings sat on page 2. The filter is now a GET form the server applies to the whole list: `listings_filter` (`active` | `expiring` | `expired`), `listings_status` (any member status), `listings_search`, and `listings_page`, applied in `blocks/user-dashboard/render.php:145-281` with the same rules the rows use: expired = `listora_expired`; expiring = published with `_listora_expiration_date` inside `renewal_window_days` (QA: 7); active = published and not expiring. The pager keeps the args. The select handler is `src/blocks/user-dashboard/view.js:535` (submits the form on change); the form works without script.

Roles: a member with more than one page of listings (actor; QA admin `varundubey` owns 2,632); a member with one active listing and nothing expiring (`qa_vendor_01` has 7, none expiring); another member / logged out as the negative controls.

## Setup

- Renewal feature ON (`wb_listora_feature_enabled( 'renewal' )`), dashboard `$SITE_URL/my-listings/?tab=listings`.
- Counts to compare against (author 1):
  ```bash
  wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"listora_listing\" AND post_author=1 AND post_status=\"listora_expired\"");'
  ```
  QA site: 21 expired, 78 published, 2,533 draft. If a member has no expired listing beyond page 1, make one: pick their OLDEST published listing, `wp post update <id> --post_status=listora_expired`, restore to `publish` afterwards.

## Steps

### 1. Expired finds matches on any page
- **Action**: as the multi-page member choose Expiry -> **Expired** (`#listora-renewal-filter`).
- **Expect**: URL gains `listings_filter=expired`; every row has `data-listora-state="expired"`; the pager reads "Page 1 of 2 (21 items)" (ceil(21 / 20)); NO "Nothing matches this filter" box (verified).

### 2. The pager keeps the filter
- **Action**: choose **Active**, click **Next** on the "Listings pagination" nav.
- **Expect**: `listings_filter=active&listings_page=2`; rows still all `active` ("Page 2 of 4 (78 items)"); the select still shows Active.

### 3. Any expiry restores the full list
- **Action**: choose **Any expiry**.
- **Expect**: `listings_filter` removed; the pager shows every page again ("Page 1 of 132 (2,632 items)" for the QA admin).

### 4. A filter that truly matches nothing
- **Action**: as the member with nothing expiring open `$SITE_URL/my-listings/?tab=listings&listings_filter=expiring`.
- **Expect** (verified for the admin; same template for any member): the "Nothing matches this filter" card (`[data-listora-filter-empty]`, `role=status`, text "None of your listings are in this state right now. Choose another filter to see the rest."), the select shows "Expiring soon", no pager, and NOT the "No listings yet" first-run state (the member does have listings). A Clear link resets the form.

### 5. Status and search compose
- **Action**: `?tab=listings&listings_status=listora_payment` and `&listings_search=<title fragment>`.
- **Expect**: only that status / title across all pages; counts match the pager.

### 6. 390px
- **Expect**: step 4 renders the same, `document.documentElement.scrollWidth <= 390`, selects and Apply >= 40px tall.

### 7. Edge data: one listing, one state
- **Action**: give the member four published listings: expiry in 3 days; expiry `garbage`; two expiry rows (3 days and 60 days); expiry in 60 days.
- **Expect**: Expiring soon = the 3-day and the two-row listing; Active = the garbage-date and 60-day listings plus any with no date. No listing appears under both, none under neither, and Active + Expiring soon = All published.

### 8. Positive control, role ladder
- **Control**: step 1 (21 matches on pages 1 and 2) vs step 4 (0 matches) shows the filter distinguishes, and `listings_page=9999` clamps to the last real page (verified "Page 132 of 132", 12 rows) instead of rendering the empty state.
- **Another member**: only their own listings under every filter. **Logged out**: login prompt.
- **Big site**: all of the above ran against 2,632 rows at ~0.15-0.25 s per page (verified); each page runs a `COUNT` plus a LIMIT/OFFSET query.

## Teardown

Restore any fixture listing to `publish`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Filter blanks page 1 | client-side filter back | `src/blocks/user-dashboard/view.js:535-545` |
| Wrong rows / pager | arg handling | `blocks/user-dashboard/render.php:145-281` |
| Pager drops the filter | pager args | `blocks/user-dashboard/render.php:1041` (`listings_filter` passed into the view data), `wb_listora_render_pagination()` |
| "No listings yet" on a filtered empty result | empty-state branch | `templates/blocks/user-dashboard/tab-listings.php:526-545` |

## Not verified

Steps 1 to 4 and the clamp were fetched as URLs live (as `varundubey`, 2,632 rows; empty `expiring` result; `qa_vendor_01`'s 7 rows). The select-change submit, click on Next, step 5 (status / search compose), step 6 (390) and step 7 (edge-date fixtures) were NOT run.
