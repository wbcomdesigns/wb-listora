---
journey: admin-approve-claim
plugin: wb-listora
priority: critical
roles: [administrator]
covers: [claims-approval, post-author-transfer, listing-claimed-hook]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1) with manage_listora_claims cap"
  - "Pending claim exists in wp_listora_claims (run journey 05-claim-a-business first)"
estimated_runtime_minutes: 3
---

# Admin approves a business claim and transfers ownership

Admin opens Claims admin page, approves a pending claim. Verifies post_author transfers from the original author to the claimant, `_listora_is_claimed` postmeta flips to `1`, `wb_listora_listing_claimed` fires (Pro listener test in combo mode), and the claimant gets an approval email.

## Setup

- Site: `$SITE_URL`
- Admin: `?autologin=1`
- Fixture (preferred — run customer journey 05 first):
  ```sql
  SELECT id, listing_id, user_id FROM wp_listora_claims WHERE status='pending' LIMIT 1;
  ```
  Capture `CLAIM_ID`, `LISTING_ID`, `CLAIMANT_USER_ID`. Capture `ORIG_AUTHOR_ID = wp_posts.post_author of $LISTING_ID`.

## Steps

### 1. Open Claims admin page
- **Action**: `playwright_navigate $SITE_URL/wp-admin/admin.php?page=listora-claims&autologin=1`
- **Expect**: list table shows the pending claim with claimant + listing + message + Approve/Reject buttons
- **On fail**: `admin/class-claims-page.php` registration

### 2. Approve the claim
- **Action**: click "Approve" on the row
- **Expect**: success notice; row moves to Approved filter

### 3. Verify post_author transferred
- **Action**:
  ```bash
  wp post get $LISTING_ID --field=post_author
  ```
- **Expect**: equals `$CLAIMANT_USER_ID` (NOT `$ORIG_AUTHOR_ID`)
- **On fail**: `Claims_Controller::approve_claim` does not call `wp_update_post` with new author

### 4. Verify `_listora_is_claimed` flag flipped
- **Action**:
  ```bash
  wp post meta get $LISTING_ID _listora_is_claimed
  ```
- **Expect**: `1`
- **On fail**: claim approval doesn't update the postmeta. Pre-Phase-0 bug: Pro's Verification class wrote this; INV-12.1 moved write to Free's `Claims_Controller`.

### 5. Verify `wb_listora_listing_claimed` fired
- **Action**: combo mode only — check that Pro's `Verification::on_listing_claimed` updated the search-index `is_claimed` column:
  ```sql
  SELECT is_claimed FROM wp_listora_search_index WHERE listing_id=$LISTING_ID;
  ```
- **Expect**: `1`
- **On fail**: hook not fired in Free OR Pro listener not registered. See `class-claims-controller.php:512` for fire site.

### 6. Verify claim status updated
- **Action**:
  ```sql
  SELECT status FROM wp_listora_claims WHERE id=$CLAIM_ID;
  ```
- **Expect**: `approved`

### 7. Verify approval email sent
- **Action**:
  ```sql
  SELECT template, recipient FROM wp_listora_email_log
  WHERE listing_id=$LISTING_ID
  ORDER BY created_at DESC LIMIT 1;
  ```
- **Expect**: template = `claim-approved`, recipient = claimant's email
- **On fail**: `Notifications::send_claim_approved`, `templates/emails/claim-approved.php`

### 8. Verify claimant dashboard shows approved status
- **Action**: `playwright_navigate $SITE_URL/dashboard/?autologin=<claimant_login>#claims`
- **Expect**: claim row shows status = "Approved"

## Pass criteria

1. Pending claim → approved transitions via admin action
2. `wp_posts.post_author` transferred from original to claimant
3. `_listora_is_claimed` postmeta = `1`
4. `wb_listora_listing_claimed` action fires (combo mode: Pro's search-index is_claimed = 1)
5. `claim-approved` email logged + sent
6. Claimant dashboard reflects new status

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Approve does nothing | cap missing or admin page not registered | `class-claims-page.php` |
| Post author not transferred | `wp_update_post` call missing | `Claims_Controller::approve_claim` |
| Postmeta not flipped | INV-12.1 regression — write moved away from Free | `Claims_Controller::approve_claim` (Free is sole writer) |
| Search index `is_claimed` stale (combo) | Pro listener not on `wb_listora_listing_claimed` | `wb-listora-pro/includes/features/class-verification.php::on_listing_claimed` |
| Email not sent | typo'd hook regression sibling | `class-notifications.php` claim-approved listener |
