---
journey: who-can-add-listings
group: settings-setup
actor: admin
mode: mixed
plugin: wb-listora
priority: high
roles: [admin, customer, subscriber]
covers: [card-10536484346, submission-roles, submit_listora_listing]
prerequisites:
  - "WooCommerce active (adds the customer role), or any plugin role with no Listora caps"
  - "A customer member and a subscriber member"
estimated_runtime_minutes: 8
last_verified: unrecorded
verified_against: free 2.1.2
viewports: [1440, 390]
themes: [reign, buddyx]
fails_on: unrecorded
site_profile: required
---

# The owner picks which roles can add listings

## Why
`submit_listora_listing` was only ever granted to the five core roles, so a WooCommerce Customer or a Dokan Vendor got "You cannot add listings" with no setting to change it (Zoho #42840).

## Steps

### 1. The setting lists every role
As admin open Listora > Settings > Submissions. "Who can add listings" lists every registered role; Administrator is ticked and cannot be unticked; the five core roles are ticked on a fresh site.

### 2. Tick a plugin role
Tick Customer, Save. Log in as the customer member, open Add Listing: the wizard opens (no "You cannot add listings"), and a photo uploads on the Media step. Credits > Limits now has a Customer row.

### 3. Untick a role
Untick Subscriber, Save. As the subscriber, Add Listing shows "You cannot add listings".

### 4. The choice sticks
Save the Reviews tab, then deactivate and re-activate WB Listora. Settings > Submissions still shows Customer ticked and Subscriber unticked.

### 5. API
`POST /wp-json/listora/v1/settings` as admin with `submission_roles: ["subscriber"]` returns 200; the Customer member loses Add Listing, the subscriber gets it back.

## Pass
All five steps hold. Restore the five core roles afterwards.
