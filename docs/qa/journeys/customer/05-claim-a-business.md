---
journey: claim-a-business
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber, listing-owner, other-member, moderator, admin]
covers: [claim-modal, claims-rest, login-gate, modal-getter-pattern, claim-approval-transfer, claimed-flag]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "Dev auto-login mu-plugin installed"
  - "Features > Claims ON (wb_listora_features.claims)"
  - "One published listing without `_listora_is_claimed` owned by OWNER (fixture QA-J1 Zephyrquill Cafe). CLAIMANT = QA-J1-other, OWNER = QA-J1-owner, THIRD = QA-J1-member"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Claim a business

A visitor opens a listing, taps "Claim", is sent to log in (gate), then as a member fills the claim form with proof, submits, and sees it Under review in My Dashboard. Nothing flips to claimed until a moderator approves; approval transfers ownership.

Verified via: Playwright (isolated context per role) and curl REST, WP-CLI for DB.

## Setup

- Listing: `$SITE_URL/listing/qa-j1-zephyrquill-cafe/`; capture `LISTING_ID`. DB clean: `DELETE FROM {prefix}listora_claims WHERE listing_id=LISTING_ID`, remove `_listora_is_claimed`.
- Dashboard: `$SITE_URL/my-listings/?tab=claims` (the old `/dashboard/#claims` is not the plugin dashboard on this site).

## Steps

### 1. Logged out sees the Claim button
- **Action**: open the listing with no session.
- **Expect**: `.listora-detail__actions` contains a button "Claim" (`data-wp-on--click="actions.showClaimModal"`). There is no `#listora-claim-modal` in the DOM (it is only rendered for a logged-in non-owner).
- **On fail**: `blocks/listing-detail/render.php:559-570`

### 2. NEGATIVE: logged-out click opens the LOGIN modal, not the claim form
- **Action**: click "Claim".
- **Expect**: `.listora-detail__modal#listora-login-modal` gets `is-open`; `<h3 id="listora-login-modal-title">` reads "Log in to claim this listing"; `.listora-detail__modal-desc` reads "Sign in to request ownership of this listing."; links "Log in" and "Create account"; the dialog has `role="dialog"` `aria-modal="true"`; focus moves inside it; Esc closes it. `POST /wp-json/listora/v1/claims` without a session returns `401` `listora_unauthorized`.
- **On fail**: `src/interactivity/store.js:1836` (`showClaimModal`), `:512-520` (modal getters)

### 3. Log in as CLAIMANT; the claim form opens
- **Action**: `$SITE_URL/?autologin=QA-J1-other`, open the listing, click "Claim".
- **Expect**: `#listora-claim-modal` gets `is-open`; heading "Claim This Business"; text "Prove you own or manage this business. Once approved, you can edit the listing and reply to its reviews."; fields `textarea[name="proof_text"]#listora-claim-proof` (required, label "Proof of Ownership *") and `input[type=file][name="proof_file"]#listora-claim-proof-file` (accept JPEG/PNG/GIF/WebP/PDF, hint "... max 5 MB"); buttons "Submit Claim" and "Cancel"; focus is inside `.listora-detail__modal-content`; Esc closes (no `.is-open` left).
- **On fail**: `blocks/listing-detail/render.php:951-980`

### 4. NEGATIVE: empty proof
- **Action**: click "Submit Claim" with `proof_text` empty.
- **Expect**: browser validation "Please fill in this field."; no request.

### 5. Submit the claim
- **Action**: `#listora-claim-proof` = `I am the owner. QA-J1 smoke claim.`; click "Submit Claim".
- **Expect**: `POST /wp-json/listora/v1/claims` (multipart: `listing_id`, `proof_text`, optional `proof_file`) returns `201` `{"id": N, "status": "pending", "message": "Your claim has been submitted and is under review."}`. The form is replaced by `.listora-detail__claim-message--success`: "Claim submitted - we'll email you when it's reviewed." and a link "View my claims" (`/my-listings/#claims`).
- **On fail**: `src/interactivity/store.js:2454` (`submitClaim`), `includes/rest/class-claims-controller.php:154`

### 6. DB check and no premature flip
- **Action**: `SELECT id, listing_id, user_id, status, proof_text FROM {prefix}listora_claims WHERE listing_id=LISTING_ID`; `get_post_meta(LISTING_ID, '_listora_is_claimed', true)`.
- **Expect**: one row, `status='pending'`, `proof_text` starts "I am the owner"; `_listora_is_claimed` is empty; `post_author` is still OWNER.

### 7. Dashboard shows it
- **Action**: as CLAIMANT open `$SITE_URL/my-listings/?tab=claims`.
- **Expect**: `#dash-tab-claims` "My Claims" with a count badge `1`; `#dash-panel-claims` lists the listing with `.listora-dashboard__status--pending` "Under review", "Submitted <date>", "We will email you as soon as the review is complete." and a "View Listing" link.
- **On fail**: `templates/blocks/user-dashboard/tab-claims.php`

### 8. ROLE LADDER and gates
| Actor | Action | Expect |
|---|---|---|
| Logged out | click Claim / `POST /claims` | login modal / `401` |
| CLAIMANT (member) | second `POST /claims` while pending | `409 listora_claim_pending` ("You already have a pending claim for this listing.") |
| OWNER (post author) | open listing; `POST /claims` | no "Claim" button in the DOM; `409 listora_own_listing` ("You already own this listing.") |
| Any member, missing proof | `POST /claims {"listing_id":..}` | `400 rest_missing_callback_param` (proof_text) |
| Member (non-admin) | `GET /claims` | `403 listora_forbidden` (list is admin/moderator only) |
| Admin / moderator (control) | `GET /claims` | `200`, row for the claim incl. `user_email`, `proof_text`, `status` |

### 9. POSITIVE CONTROL: approval flips the flag (proves step 6 is not just "never flips")
- **Action**: admin `PUT /claims/CLAIM_ID {"status":"approved"}` (or Moderation > Claims > Approve).
- **Expect**: `200` `{"id": N, "status": "approved", "message": "Claim approved. Listing ownership transferred."}`; `_listora_is_claimed = 1`; `post_author` = CLAIMANT; the CLAIMANT's Claims tab reads "Approved" and "You now own this listing. Open My Listings to edit it."; a third member no longer sees the "Claim" button and `POST /claims` returns `409 listora_already_claimed`.
- **Restore**: set `post_author` back to OWNER and delete `_listora_is_claimed`, `_listora_pre_claim_author`, and the claim row before re-running.

### 10. Layout
- **Action**: repeat steps 3 and 5 at 390x844.
- **Expect**: `scrollWidth <= 390`; the modal content is about 351px wide inside the viewport; `body` gets `overflow: hidden` while open (background does not scroll); Submit Claim 46px, Cancel 46px and close button 44px high (all >= 40px).

## Pass criteria

1. Logged-out click opens the login modal titled "Log in to claim this listing".
2. Logged-in click opens `#listora-claim-modal` with the proof field.
3. Submit returns 201 `pending`; one `listora_claims` row; `_listora_is_claimed` not set.
4. Dashboard Claims tab shows "Under review".
5. Duplicate, own-listing and missing-proof requests are refused with the codes above; list is admin-only.
6. Approval sets `_listora_is_claimed`, transfers `post_author`, hides the button.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Logged-out click opens nothing | getter regression | `src/interactivity/store.js:512-520`, `blocks/listing-detail/render.php:1045` |
| Logged-out click opens the claim form | gate bypass | `src/interactivity/store.js:1836` |
| No Claim button for visitors | condition tightened | `blocks/listing-detail/render.php:559` (`$listora_can_claim`) |
| 401 on POST /claims when logged in | nonce / cookie | `includes/rest/class-claims-controller.php:640` |
| 403 `listora_claims_disabled` | feature off | `includes/rest/class-claims-controller.php:166` |
| Row missing / wrong status | insert path | `includes/rest/class-claims-controller.php:154-260` |
| Flag set while pending | state machine | `includes/rest/class-claims-controller.php:660` (`change_status`) |
| Dashboard Claims empty | tab query | `templates/blocks/user-dashboard/tab-claims.php`, `includes/rest/class-dashboard-controller.php` |
