---
journey: claim-audit-trail-both-paths
plugin: wb-listora
roles: [admin, member]
priority: high
covers: [wb_listora_after_update_claim, wb_listora_after_submit_claim, audit-log-claim-rows, admin-claim-approval, BC-10199419982]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "wb-listora AND wb-listora-pro both active (the audit log is Pro)"
  - "A published listing and a member who can claim it"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
changed: true
---

# The claim audit trail records the right listing, on both approval paths

The audit trail is a compliance record, so "mostly right" is not allowed. Every claim transition must write one audit row that carries the real listing, on every path that can change a claim: member submit (REST), admin single-row action, admin bulk action, and REST `PATCH`.

- Free fires `wb_listora_after_submit_claim( $claim_id, $listing_id, $request )` and `wb_listora_after_update_claim( $claim_id, $new_status, $request )` (scalars, `$request` is `null` on the wp-admin path).
- Pro's Audit_Log (`on_claim_submitted`, `on_claim_updated`) and Outgoing_Webhooks consume the same two hooks.
- The visible half (the approved email) fires from a different hook, so a missing audit row can hide behind correct customer-facing behaviour. Always read the table, not just the email.

Roles: claimant (a member, not the listing author), moderator/admin on the Claims screen, and a second member / logged-out visitor as negative controls.

## Setup

- Free + Pro both active (the audit log is Pro). Table `wp_listora_audit_log` (columns `id, user_id, action, object_type, object_id, details, ip_address, created_at`).
- `LISTING` = a published listing authored by someone else that has no claim row yet, e.g. `wp eval 'global $wpdb; echo $wpdb->get_var("SELECT p.ID FROM wp_posts p WHERE p.post_type=\"listora_listing\" AND p.post_status=\"publish\" AND p.post_author=1 AND p.ID NOT IN (SELECT listing_id FROM wp_listora_claims) LIMIT 1");'`. Note its `post_author` so you can restore it (approval transfers ownership and sets `_listora_is_claimed`).
- `CLAIMANT` = `qa_vendor_01`; second claimant `qa_vendor_02` for the reject path; a third member (`author1`) and a logged-out browser as negative controls.
- Helper to read the newest claim audit rows:
  ```bash
  wp eval 'global $wpdb; foreach($wpdb->get_results("SELECT id,action,object_id,details FROM {$wpdb->prefix}listora_audit_log WHERE object_type=\"claim\" ORDER BY id DESC LIMIT 4",ARRAY_A) as $r) echo json_encode($r),"\n";'
  ```
- Switching users: autologin is ignored while a session exists. Log out between roles (`/wp-login.php?action=logout`, confirm) or the second role silently tests as the first.

## Steps

### 1. Submitted claim records the listing it targets
- **Action**: as `CLAIMANT`, open the listing, click **Claim**, type proof text, click **Submit Claim** (or `POST /wp-json/listora/v1/claims` with `listing_id` and `proof_text`, REST nonce).
- **Expect**: 201 `{"id":N,"status":"pending","message":"Your claim has been submitted and is under review."}`. Newest audit row: `claim_submitted`, `object_type=claim`, `object_id=N`, `details` = `{"listing_id":<LISTING>,"listing_title":"<real title>","claimer_id":<CLAIMANT id>}`.
- **Fail**: `"listing_id":0` with an empty title is the regression (consumer read the wrong hook argument).

### 2. Admin single-row approval writes an audit row
- **Action**: as an administrator open **Listora > Claims** (`/wp-admin/admin.php?page=listora-claims`, Pending tab), click **Approve** on that row (not REST: the admin path is the one that was silent).
- **Expect**: redirect to `...page=listora-claims&listora_notice=approve&n=1&same=0&claimed=0`, the notice "1 claim approved. The listing now belongs to the claimant." (reject reads "1 claim rejected. The claimant has been told."). New audit row `claim_approved` with the real `listing_id`, `listing_title` and `"status":"approved"`. `{prefix}listora_claims.status = approved`, listing `post_author` = claimant, `_listora_is_claimed` = 1.
- **Fail**: no new row at all, or a row reading `claim_updated` instead of `claim_approved`.
- **Repeat**: re-open the same Approve link (or approve it from a second tab): the notice reads "1 claim was already decided (perhaps in another tab or by another moderator) and was left as it was." and NO second audit row is written.

### 3. Bulk approval behaves identically
- **Action**: with two fresh pending claims, tick both rows on the Claims screen, choose bulk **Approve**, apply.
- **Expect**: notice "2 claims approved. Each listing now belongs to its claimant." (n=2) and one `claim_approved` row per claim id, each with its own listing. Bulk and single path must not drift.

### 4. Rejection, both paths
- **Action**: reject one claim with the row **Reject**, another with bulk **Reject** (as `qa_vendor_02`'s claim on a different listing).
- **Expect**: one `claim_rejected` row each (`"status":"rejected"`, real listing).

### 5. Pro's webhooks see admin approvals too
- **Action**: configure an outgoing webhook subscribed to claim approval (Pro > Webhooks), approve a claim from wp-admin.
- **Expect**: a delivery row in `{prefix}listora_webhook_log` for the admin path as well as for REST `PATCH /listora/v1/claims/{id}`. A delivery on REST but not on admin means the hook is fired from one path only again.

### 6. Positive control and role ladder
- **Control**: step 1's row for the REST submit path proves the hook consumer works; step 2 proves the admin path. If step 1 passes and step 2 has no row, the defect is the admin path, not the consumer.
- **Another member** (`author1`, logged in): `PATCH /listora/v1/claims/{id}` with `{"status":"approved"}` returns 403, and no audit row is written.
- **Logged out**: `POST /listora/v1/claims` returns 401 `listora_unauthorized`; no row written.
- **Editor** (`qa_editor`): cannot open the Claims screen unless granted `manage_listora_claims`; no row from a refused attempt.

## Cleanup

Delete the probe claims (Claims screen **Delete**, or `DELETE /listora/v1/claims/{id}`), delete their audit rows (`object_type='claim'` and your claim ids), restore the listing's `post_author`, and delete `_listora_is_claimed` on it.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| `claim_submitted` row has `listing_id: 0` | consumer reads the wrong hook argument | `wb-listora-pro/includes/features/class-audit-log.php:467` (`on_claim_submitted`), hook wired at `:192` |
| `claim_updated` instead of `claim_approved` | consumer treats the status string as an array | `wb-listora-pro/includes/features/class-audit-log.php:573` |
| Admin approval writes no row | admin path not firing the hook | `includes/admin/class-admin.php:1975` (`fire_claim_updated`) and its callers in `handle_claim_actions()` at `:2025` |
| Webhook fires on REST only | hook fired from one path | `wb-listora-pro/includes/features/class-outgoing-webhooks.php:547`, `includes/rest/class-claims-controller.php:590` |

## Not verified

Step 3 (bulk approve) and step 5 (webhook delivery) were not run live; steps 1, 2 and 4 (single-row reject) were run on the live site with `QA-J1-` probe claims (row shapes and notice text copied from the run) and cleaned up. Verified via: curl + wp-cli (not a browser).
