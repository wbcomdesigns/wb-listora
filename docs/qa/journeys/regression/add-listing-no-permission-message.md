---
journey: add-listing-no-permission-message
plugin: wb-listora
priority: high
roles: [member-without-cap, subscriber, anon]
covers: [10380188010]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 5
---

# A member who cannot submit gets a reason, not a blank page

A signed-in member whose role lacks `submit_listora_listing` (a WooCommerce `customer`, an LMS student) opened Add Listing and got an empty page, and My Dashboard still offered them Add Listing.

## Steps

### 1. Customer opens Add Listing
- **Action**: user with role `customer`; `?autologin=<login>` then `/add-listing/` at 1440 and 390.
- **Expect**: `.listora-submission--no-permission` with "You cannot add listings", the message, and Go to My Dashboard. No horizontal scroll.

### 2. Customer's dashboard
- **Action**: `/my-dashboard/`.
- **Expect**: no Add Listing / Add Your First Listing button; empty state reads "Your account cannot add listings on this site...".

### 3. Controls
- **Action**: a subscriber on both pages; a guest on Add Listing.
- **Expect**: subscriber gets the wizard and both buttons; guest gets the log-in prompt.

## Pass criteria

ALL of the following hold:
1. Member without the cap sees the message on Add Listing.
2. No Add Listing CTA for them on the dashboard or the grid empty state (`wb_listora_can_offer_submission()`).
3. Subscribers and guests unchanged.
