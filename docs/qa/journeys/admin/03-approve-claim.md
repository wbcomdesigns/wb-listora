---
journey: admin-approve-claim
plugin: wb-listora
priority: critical
roles: [administrator, qa_editor, member-owner, member-other, anonymous]
covers: [claims-approval, post-author-transfer, listing-claimed-hook, claim-approved-email, leads-follow-owner, claim-reversal, 2.0.0-claims]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1); qa_editor (Editor has manage_listora_claims)"
  - "A pending claim: as dana_whitfield claim 'J20 Owner Published' (customer journey 05: Claim this business, proof text >= 20 characters), or POST /listora/v1/claims {listing_id, proof_text}"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light for the claimant dashboard]
---

# Admin approves a business claim and the listing changes hands

Moderation > Claims (`admin.php?page=listora-claims`). Approving transfers `post_author` to the claimant (remembering the previous author so the approval can be reversed), sets `_listora_is_claimed`, fires `wb_listora_listing_claimed` and `wb_listora_claim_approved`, updates the search index (`is_claimed`, `author_id`), emails the claimant, and since 2.0.0 the listing's leads follow the new owner.

## Setup

```bash
wp eval 'global $wpdb; print_r($wpdb->get_row("SELECT id,listing_id,user_id,status FROM {$wpdb->prefix}listora_claims WHERE status=\"pending\" ORDER BY id DESC LIMIT 1", ARRAY_A));'
wp eval 'echo get_post_field("post_author", LISTING_ID);'   # ORIG_AUTHOR_ID
wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}listora_email_log");'   # MAIL_BASE
```
Capture `CLAIM_ID`, `LISTING_ID`, `CLAIMANT_ID`. Optional: a lead on the listing (see `customer/2.0-leads-guest-to-owner-inbox.md`) to prove step 7.

## Steps

### 1. Open Claims (administrator, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-claims&autologin=1`.
- **Expect**: shared header "Claims" + "Business owners asking to take over a listing. Approving gives them the listing."; views All / Pending / Approved / Rejected; search "Search by listing, name or email"; columns Listing, Claimant, Proof, Status, Submitted; the pending claim with the proof text, "Pending" badge and actions Approve / Reject / Delete. The Moderation hub tab row shows Reviews | Claims.
- **On fail**: `Admin::render_claims_page` (`includes/admin/class-admin.php` ~2053).

### 2. Approve
- **Action**: "Approve" on the row.
- **Expect**: notice "1 claim approved. The listing now belongs to the claimant."; the row is under Approved; the row's actions now include "Reverse approval".

### 3. Ownership and flags
- **Action**:
  ```bash
  wp post get LISTING_ID --field=post_author         # CLAIMANT_ID, not ORIG_AUTHOR_ID
  wp post meta get LISTING_ID _listora_is_claimed    # 1
  wp post meta get LISTING_ID _listora_pre_claim_author   # ORIG_AUTHOR_ID (kept for reversal)
  wp eval 'global $wpdb; print_r($wpdb->get_row($wpdb->prepare("SELECT is_claimed,author_id FROM {$wpdb->prefix}listora_search_index WHERE listing_id=%d", LISTING_ID), ARRAY_A));'
  wp eval 'global $wpdb; echo $wpdb->get_var($wpdb->prepare("SELECT status FROM {$wpdb->prefix}listora_claims WHERE id=%d", CLAIM_ID));'
  ```
- **Expect**: author = claimant; `_listora_is_claimed` = 1; search index `is_claimed`=1 and `author_id`=claimant; claim status `approved`. (Free is the only writer of `_listora_is_claimed`.)
- **On fail**: `Claims_Controller::apply_approval_side_effects` (`includes/rest/class-claims-controller.php` ~799), `PRE_CLAIM_AUTHOR_META`.

### 4. Hooks and Pro listener (combo mode)
- **Expect**: `wb_listora_listing_claimed` fired with context `admin_claim`; with Pro active `Verification::on_listing_claimed` ran (a verified badge/state per Pro settings). Free alone: the action simply has no listener.

### 5. The claimant is emailed
- **Action**:
  ```bash
  wp eval 'global $wpdb; print_r($wpdb->get_results($wpdb->prepare("SELECT event_key,recipient,subject,success FROM {$wpdb->prefix}listora_email_log WHERE id > %d", MAIL_BASE), ARRAY_A));'
  ```
- **Expect**: one row `event_key=claim_approved`, recipient = the claimant's email, success 1. The email does not promise a "Verified" badge (2.0.0 wording fix).

### 6. Claimant sees it (member-owner, 1440)
- **Action**: `$SITE_URL/my-listings/?autologin=<claimant login>&tab=claims` then `tab=listings`.
- **Expect**: "My Claims" shows the claim as Approved; "My Listings" now lists the listing with edit actions; its page shows the owner bar (they own it).

### 7. Leads follow the listing
- **Action**: before/after step 2 compare `SELECT DISTINCT owner_id FROM wp_listora_leads WHERE listing_id=LISTING_ID`.
- **Expect**: owner_id changed to CLAIMANT_ID; the previous owner's Leads tab no longer lists them (`customer/2.0-leads-guest-to-owner-inbox.md`).

### 8. Reverse approval
- **Action**: "Reverse approval" (confirm text: `"<listing>" will be taken back from <claimant> and returned to <previous owner>. <claimant> is told the claim was rejected.`).
- **Expect**: author back to ORIG_AUTHOR_ID, `_listora_is_claimed` removed, claim `rejected`, leads' owner reverted; claimant gets a "Claim rejected" email.

### 9. Guard rails
- **Expect**: approving a second claim on a listing that already has an approved claim is skipped with the warning "1 claim was skipped: that listing already has an approved claim. Reject or reverse it first."; approving an already-decided claim from a stale tab shows the info notice "1 claim was already decided (perhaps in another tab or by another moderator) and was left as it was.".

### 10. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-9 | pass |
| qa_editor | open Claims, Approve | allowed (`manage_listora_claims`) |
| claimant (dana) | `PUT /listora/v1/claims/<id> {"status":"approved"}` | 403 (cannot approve own claim) |
| member-other | same | 403 |
| anonymous | `GET /listora/v1/claims` | 401 |
| subscriber | `admin.php?page=listora-claims` | not allowed |

## Pass criteria

1. Pending claim becomes approved via the screen with the documented notice.
2. `post_author`, `_listora_is_claimed`, search-index `is_claimed`/`author_id` all reflect the claimant.
3. `claim_approved` email logged and sent; claimant dashboard shows Approved and the listing.
4. Leads follow; reversal restores everything; duplicate/stale approvals are refused gracefully.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Author not transferred | `wp_update_post` skipped | `Claims_Controller::apply_approval_side_effects` |
| Index `author_id` stale | search index update missing | same, step 4 of that method |
| Pro badge missing (combo) | Pro listener not on `wb_listora_listing_claimed` | `wb-listora-pro/includes/features/class-verification.php` ~45 |
| Leads still with old owner | `Leads::follow_owner` not registered | `class-leads.php::init` |
