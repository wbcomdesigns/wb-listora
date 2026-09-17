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
---

# The My Listings renewal filter searches every page, not the 20 rows on screen

The filter hid rows client-side. On page 1 of a member with 7 pages, "Expired" matched nothing on
screen, first blanked the panel, and after the first fix said "None of your listings are in this
state right now" and hid the pager - while expired listings sat on page 2 (card 10294421959).

The filter is now a query arg, `listings_filter` (`active` | `expiring` | `expired`), applied in
`blocks/user-dashboard/render.php` with the same rules the rows use: expired = `listora_expired`;
expiring = published with `_listora_expiration_date` inside the renewal window; active = published
and not expiring. The pager keeps the arg. **Build note:** the select handler lives in
`src/blocks/user-dashboard/view.js` → `build/blocks/user-dashboard/view.js`.

## Setup

Counts to compare against (author 1; adjust the id):
```bash
wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type=\"listora_listing\" AND post_author=1 AND post_status=\"listora_expired\"");'
```
If the member has no expired listing beyond page 1, make one: pick their OLDEST published listing,
`wp post update <id> --post_status=listora_expired`, restore to `publish` afterwards.

## Steps

### 1. Expired finds matches on any page
- **Action**: as the multi-page member open `/my-listings/?tab=listings`, choose Filter → Expired
- **Expect**: URL gains `listings_filter=expired`; every row has `data-listora-state="expired"`; pager reads "Page 1 of ceil(expired/20)"; NO "Nothing matches" box (the bounce showed that box here)

### 2. The pager keeps the filter
- **Action**: choose Active, click Next
- **Expect**: `listings_filter=active&listings_page=2`; rows still all `active`; select still shows Active

### 3. All restores the full list
- **Action**: choose All listings
- **Expect**: `listings_filter` removed from the URL; pager shows every page again

### 4. A filter that truly matches nothing
- **Action**: as journey_owner open `/my-listings/?tab=listings&listings_filter=expiring`
- **Expect**: "Nothing matches this filter" computed-visible, the select shows "Expiring soon", no pager, and NOT the "No listings yet" first-run state (the member does have a listing)

### 5. 390px
- **Expect**: same as step 4, `document.documentElement.scrollWidth <= 390`

## Teardown
Restore any fixture listing to `publish`.
