---
journey: approve-claim
group: claims-verification
actor: admin
mode: mixed
plugin: wb-listora
priority: critical
roles: [administrator, editor, member-owner, member-other, anonymous]
covers: [claims-approval, post-author-transfer, listing-claimed-hook, claim-approved-email, leads-follow-owner, claim-reversal, claim-delete, claim-delete-one-path, 2.0.0-claims, card-10393294523]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} (autologin); {editor} (Editor has `manage_listora_claims`)"
  - "A pending claim: as {other} claim a {listing} (`claims-verification/claim-a-business.md`, proof text >= 20 characters) or `POST /listora/v1/claims {listing_id, proof_text}`"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light for the claimant dashboard]
site_profile: required
supersedes: ["ora:regression/one-path-delete-and-money.md (claim-delete half)"]
---

# Admin approves (or reverses, rejects, deletes) a business claim and the listing changes hands

## Why
Approving a claim transfers `post_author`, sets `_listora_is_claimed`, updates the search index, emails the claimant and moves the listing's leads; getting any one wrong leaves the new owner locked out or the old owner still receiving enquiries. Audit rows for these transitions: `pro:analytics-audit/claim-audit-trail-both-paths.md`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Moderation > Claims = `admin.php?page=listora-claims`. Capture:
  ```bash
  wp eval 'global $wpdb; print_r($wpdb->get_row("SELECT id,listing_id,user_id,status FROM {$wpdb->prefix}listora_claims WHERE status=\"pending\" ORDER BY id DESC LIMIT 1", ARRAY_A));'   # CLAIM_ID, LISTING_ID, CLAIMANT_ID
  wp eval 'echo get_post_field("post_author", LISTING_ID);'   # ORIG_AUTHOR_ID
  wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}listora_email_log");'   # MAIL_BASE
  ```
- Optional: a lead on the listing (`contact-leads/listing-contact-form.md`) to prove step 7.
- Code map: `Claims_Controller::apply_approval_side_effects` (`includes/rest/class-claims-controller.php` ~799), `PRE_CLAIM_AUTHOR_META`, `Claims_Model::delete()` (`includes/core/class-claims-model.php:106`, fires `wb_listora_after_delete_claim`), REST delete `class-claims-controller.php:145`, `Admin::render_claims_page` (~2053).

## Steps

### 1. Open Claims ({admin}, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-claims&autologin=1`.
- **Expect**: header "Claims" + "Business owners asking to take over a listing. Approving gives them the listing."; views All / Pending / Approved / Rejected; search "Search by listing, name or email"; columns Listing, Claimant, Proof, Status, Submitted; the pending claim with proof text, "Pending" badge, actions Approve / Reject / Delete; hub tab row Reviews | Claims. List/REST count parity: `claims-verification/claims-list-and-count-agree.md`.
- **Read-back**: Pending tab count equals `SELECT COUNT(*) ... WHERE status='pending'`.

### 2. Approve
- **Action**: "Approve" on the row.
- **Expect**: notice "1 claim approved. The listing now belongs to the claimant."; row under Approved; row actions now include "Reverse approval".
- **Read-back**: step 3.

### 3. Ownership and flags
- **Action**:
  ```bash
  wp post get LISTING_ID --field=post_author         # CLAIMANT_ID, not ORIG_AUTHOR_ID
  wp post meta get LISTING_ID _listora_is_claimed    # 1
  wp post meta get LISTING_ID _listora_pre_claim_author   # ORIG_AUTHOR_ID (kept for reversal)
  wp eval 'global $wpdb; print_r($wpdb->get_row($wpdb->prepare("SELECT is_claimed,author_id FROM {$wpdb->prefix}listora_search_index WHERE listing_id=%d", LISTING_ID), ARRAY_A));'
  ```
- **Expect**: author = claimant; `_listora_is_claimed`=1; index `is_claimed`=1 and `author_id`=claimant; claim `status='approved'`. (Free is the only writer of `_listora_is_claimed`.)

### 4. Hooks and Pro listener (combo mode)
- **Expect**: `wb_listora_listing_claimed` fired with context `admin_claim`; with Pro `Verification::on_listing_claimed` ran (verified state per Pro settings: `pro:claims-verification/verification-badge.md` step 6). Free alone: the action has no listener.

### 5. The claimant is emailed
- **Action**: `SELECT event_key,recipient,subject,success FROM ${PREFIX}listora_email_log WHERE id > MAIL_BASE`.
- **Expect**: one row `claim_approved`, recipient = the claimant's email, `success=1`; the email does not promise a "Verified" badge (2.0.0 wording fix).

### 6. Claimant sees it (member-owner, 1440 and 390)
- **Action**: `$SITE_URL/my-listings/?autologin={other}&tab=claims`, then `tab=listings`.
- **Expect**: "My Claims" shows Approved; "My Listings" lists the listing with edit actions; its page shows the owner bar. Chip text and layout: `claims-verification/dashboard-claims-tab.md`.

### 7. Leads follow the listing (claim path)
- **Action**: before/after step 2 `SELECT DISTINCT owner_id FROM ${PREFIX}listora_leads WHERE listing_id=LISTING_ID`.
- **Expect**: `owner_id` changes to CLAIMANT_ID; the previous owner's Leads tab no longer lists them. The Quick Edit path: `contact-leads/leads-admin-retention-privacy-ownership.md` step 6.

### 8. Reverse approval
- **Action**: "Reverse approval" (confirm text `"<listing>" will be taken back from <claimant> and returned to <previous owner>. <claimant> is told the claim was rejected.`).
- **Expect**: author back to ORIG_AUTHOR_ID, `_listora_is_claimed` removed, claim `rejected`, leads' owner reverted; claimant gets a "Claim rejected" email.
- **Read-back**: repeat step 3 queries.

### 9. Guard rails
- **Expect**: approving a second claim on a listing that already has an approved claim is skipped: "1 claim was skipped: that listing already has an approved claim. Reject or reverse it first."; approving an already-decided claim from a stale tab: "1 claim was already decided (perhaps in another tab or by another moderator) and was left as it was."
- **Read-back**: `post_author` unchanged by the skipped approval.

### 10. Delete a claim (one path)
- **Action**: insert two QA-J1 claims. `DELETE /listora/v1/claims/<id>` as {guest}, as a subscriber, as {admin}; then delete the second from the Claims screen (row Delete); then a second admin DELETE of the same id.
- **Expect**: guest `401 listora_unauthorized`; subscriber `403 listora_forbidden`; admin `200 {"deleted":true}` and `wb_listora_after_delete_claim` fires once; repeat `404 listora_not_found` ("Claim not found."); screen delete removes the row and fires the same action. An approved claim's ownership (`post_author`) stays after its claim row is deleted.
- **Read-back**: `SELECT COUNT(*)` of the ids; scale: with many rows deleting one does not reload the table (screen pages with LIMIT/OFFSET, `Claims_Model::get_list_count()`).

## Role ladder
| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-10 | pass |
| {editor} | open Claims, Approve | allowed (`manage_listora_claims`); page-access matrix: `access-security/moderation-and-settings-screen-capabilities.md` |
| claimant ({other}) | `PUT /claims/<id> {"status":"approved"}` | `403` (cannot approve own claim) |
| member-other | same; `DELETE /claims/<id>` | `403` |
| {guest} | `GET /claims`; `DELETE /claims/<id>` | `401` |
| subscriber | `admin.php?page=listora-claims` | not allowed |
| Moderator (Pro role) | delete | allowed only with the claims-manage cap (check the `delete_claim` permission callback) |

## Pass criteria
1. Pending claim becomes approved via the screen with the documented notice.
2. `post_author`, `_listora_is_claimed`, index `is_claimed`/`author_id` all reflect the claimant.
3. `claim_approved` email logged and sent; the claimant dashboard shows Approved and the listing.
4. Leads follow; reversal restores everything; duplicate/stale approvals are refused gracefully.
5. Delete has one path (REST and screen) with the documented codes and hook.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Author not transferred | `wp_update_post` skipped | `Claims_Controller::apply_approval_side_effects` |
| Index `author_id` stale | search index update missing | same, step 4 of the method |
| Pro badge missing (combo) | Pro listener not on `wb_listora_listing_claimed` | `wb-listora-pro/includes/features/class-verification.php` ~45 |
| Leads still with old owner | `Leads::follow_owner` not registered | `class-leads.php::init` |
| Delete fires hook twice / not at all | two delete paths | `includes/core/class-claims-model.php:106`, `class-claims-controller.php:145` |

## Not verified
The delete steps were run over REST only (screen delete not driven in a browser); 390px; Pro moderator role row.

## Cleanup
Restore `post_author` to ORIG_AUTHOR_ID and delete `_listora_is_claimed`, `_listora_pre_claim_author`; delete QA claims.
