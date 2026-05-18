---
journey: claim-a-business
plugin: wb-listora
priority: critical
roles: [anonymous, subscriber]
covers: [claim-modal, claims-rest, modal-getter-pattern, listing-claimed-hook]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 1 published unclaimed listing exists"
  - "Test user 'tester' (subscriber) exists"
estimated_runtime_minutes: 4
---

# Claim a business

An anonymous visitor lands on a listing detail page, taps "Claim this business", is prompted to login (gating works), logs in as tester, fills the claim form, submits. Verifies the claim modal opens via the getter pattern (commit 63411c8 regression sentinel), the REST round-trip writes a `listora_claims` row, and the `wb_listora_listing_claimed` action fires (Pro listener test in combo mode).

## Setup

- Site: `$SITE_URL`
- Test user: `tester` (autologin via `?autologin=tester`)
- Fixture: 1 published listing without `_listora_is_claimed = 1` postmeta. Capture as `LISTING_ID`.
- DB clean (start state):
  ```sql
  DELETE FROM wp_listora_claims WHERE user_id=(SELECT ID FROM wp_users WHERE user_login='tester');
  ```

## Steps

### 1. Anonymous visitor opens the listing detail page
- **Action**: `playwright_navigate $SITE_URL/listing/<slug>` (NO autologin)
- **Expect**: DOM shows `.wp-block-listora-listing-detail`. "Claim this business" button visible.
- **On fail**: `blocks/listing-detail/render.php`, claim CTA conditional

### 2. Click "Claim this business" while logged out
- **Action**: `playwright_click 'button.listora-detail__claim-cta'`
- **Expect**:
  - Login modal opens (NOT claim modal — anonymous users hit login gate first)
  - `state.activeModal === 'login'` AND DOM shows `.listora-modal[data-state="open"]` with `[data-modal="login"]`
  - Login modal has `role="dialog"` + `aria-modal="true"`
- **On fail**: `src/interactivity/store.js` `openClaimModal` action, login-gate logic. Modal-getter regression — see commit 63411c8 (`isLoginModalOpen` derived getter).

### 3. Login from the modal
- **Action**: navigate to `$SITE_URL/?autologin=tester` then back to `$SITE_URL/listing/<slug>`
- **Expect**: page renders with admin bar; "Claim this business" CTA still visible.

### 4. Open claim modal as logged-in user
- **Action**: `playwright_click 'button.listora-detail__claim-cta'`
- **Expect**:
  - Claim modal opens — `state.activeModal === 'claim'` AND DOM shows `[data-modal="claim"][data-state="open"]`
  - Modal has form fields: Message textarea, Proof file input, Submit button
  - Tab key cycles focus inside modal; Esc closes
- **On fail**: `isClaimModalOpen` derived getter (commit 63411c8 pattern), `templates/blocks/listing-detail/modals/claim.php`

### 5. Fill claim form + submit
- **Action**: type "I am the owner. Smoke claim." into message textarea, click Submit
- **Expect**:
  - `POST /wp-json/listora/v1/claims` returns 200 with `{ id, status: 'pending' }`
  - Modal closes, success toast appears
- **On fail**: `Claims_Controller::create_claim`, validation, nonce

### 6. Verify DB write
- **Action**:
  ```sql
  SELECT id, listing_id, user_id, status, message FROM wp_listora_claims
  WHERE user_id=(SELECT ID FROM wp_users WHERE user_login='tester')
  AND listing_id=$LISTING_ID;
  ```
- **Expect**: 1 row with `status='pending'`, message starts with "I am the owner"

### 7. Verify `wb_listora_listing_claimed` does NOT fire yet (claim is pending, not approved)
- **Action**: check `_listora_is_claimed` postmeta on `$LISTING_ID`
- **Expect**: NOT set to `1` (Free's `Claims_Controller::approve_claim` is the trigger; pending claim shouldn't flip the flag)
- **On fail**: state-machine bug — claim creation should not auto-approve

### 8. Verify dashboard Claims tab shows pending claim
- **Action**: `playwright_navigate $SITE_URL/dashboard/#claims`
- **Expect**: DOM shows row for the new claim with status badge "Pending"
- **On fail**: `templates/blocks/user-dashboard/tab-claims.php`, `Dashboard_Controller::get_claims`

## Pass criteria

ALL of the following hold:
1. Anonymous click on "Claim this business" surfaces login modal (not claim modal directly)
2. After login, click on CTA opens claim modal via the derived getter pattern
3. Submit creates `listora_claims` row with `status='pending'`
4. `_listora_is_claimed` postmeta is NOT prematurely flipped
5. Dashboard Claims tab lists the pending claim

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Anonymous click does nothing | login-gate logic missing | `src/interactivity/store.js` `requireAuth` |
| Anonymous click opens claim modal directly | gate bypass | `openClaimModal` action |
| Claim modal stuck closed after click | modal-getter regression #63411c8 | `isClaimModalOpen` derived getter must be a property, NOT inline `state.activeModal === 'claim'` |
| 401 on POST /claims | nonce expired or REST cookie auth | `Claims_Controller::permissions_check` |
| 500 on POST /claims | validation crash or DB constraint | `wp-content/debug.log`, `Claims::insert` |
| Dashboard Claims tab empty | dashboard query stale | `Dashboard_Controller::get_claims` cache transient |
