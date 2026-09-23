---
journey: space-showcase-published-listings-only
plugin: wb-listora
priority: normal
roles: [space-team, anonymous]
covers: [space-listings, showcase]
prerequisites:
  - "BuddyNext active, or the space permission filters answered true for the test"
estimated_runtime_minutes: 4
covers_card: null
---

# Only published listings can be approved into a space showcase, and the total counts only those

Regression sentinel for `Space_Listings_Controller::approve()` and `Space_Listings_Model::approved_count()`.

## Background

A space curator could "approve" any post id - another member's pending listing, a private attachment, a page. Nothing was displayed, but the row was stored, the approval hook fired, and the showcase `X-WP-Total` counted it. Found in the 2026-09-23 release security review.

## Steps

### 1. As the space team, approve a published listing → 200.
### 2. Approve a pending listing and a page id → 404 `listora_not_found`, no row stored.
### 3. With a leftover non-listing row in `listora_space_listings`, GET the showcase
- **Expect**: `X-WP-Total` equals the number of listings rendered.
### 4. Delete the test rows.
