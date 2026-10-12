---
journey: admin-approve-pending-listing
plugin: wb-listora
priority: critical
roles: [administrator, qa_editor, member-owner, member-other]
covers: [listing-status-machine, status-changed-hook, notification-email, email-log, rejected-needs-pending-first, owner-preview, 2.0.0-status-rules]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1); qa_editor (Editor); a Subscriber owner (here tester, one pending listing) and another member (dana_whitfield)"
  - "At least 1 listora_listing with post_status='pending' (the fixtures script seeds 'J20 Owner Pending' owned by qa_vendor_01) and one 'J20 Owner Rejected'"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light for the owner preview]
---

# Admin approves a pending listing (and cannot publish a rejected one straight)

An admin or editor opens the Listings list, finds a pending listing, clicks Approve. The status moves to `publish` once, `wb_listora_listing_status_changed` fires once, the "Your listing has been approved" email reaches the author and is logged in Tools > Email Log. 2.0.0 adds: approving sets the expiry only when expiry is on, and a rejected listing can no longer be published straight from Quick Edit / Bulk Edit / the block editor / API / WP-CLI (it must go to Pending Review first). Sentinel for commit `0aa62ca` (Notifications once hooked typo'd hook names, so approve/reject/expire emails never sent).

## Setup

- Seed: `wp eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed` (prints `pending`, `rejected` IDs; or use any pending listing).
- Capture `LISTING_ID` (pending), `REJ_ID` (rejected), the author's email `AUTHOR_EMAIL`.
- Email baseline (the table is `{prefix}listora_email_log`, columns `event_key, recipient, subject, success, sent_at`):
  ```bash
  wp eval 'global $wpdb; echo $wpdb->get_var("SELECT COALESCE(MAX(id),0) FROM {$wpdb->prefix}listora_email_log");'   # MAIL_BASE
  ```

## Steps

### 1. Open the pending list (administrator, 1440)
- **Action**: `$SITE_URL/wp-admin/edit.php?post_type=listora_listing&post_status=pending&autologin=1`.
- **Expect**: the native list table (shared hub tab row "All Listings | Leads" above it) with the pending row `tr#post-<LISTING_ID>`; row actions include "Approve" and "Reject" (hover or keyboard focus). The status count tabs say Pending (n).
- **On fail**: `Listing_Columns::row_actions` (`includes/admin/class-listing-columns.php` ~666).

### 2. Approve with the row action
- **Action**: click "Approve" (link `admin.php?action=listora_approve_listing&post=<id>&_wpnonce=...`).
- **Expect**: back on the list with a success notice "Listing approved and published." under the screen title; the row is no longer under Pending; `wp post get <id> --field=post_status` = `publish`.
- **On fail**: `Listing_Columns::handle_approve_listing` (~772) / `handle_moderation_action` (~716).

### 3. Exactly one approval email, logged
- **Action**:
  ```bash
  wp eval 'global $wpdb; print_r($wpdb->get_results($wpdb->prepare("SELECT event_key,recipient,subject,success FROM {$wpdb->prefix}listora_email_log WHERE id > %d ORDER BY id", MAIL_BASE), ARRAY_A));'
  ```
- **Expect**: one row `event_key=listing_approved`, `recipient=AUTHOR_EMAIL`, `subject` "Your listing has been approved: <title>", `success=1`. Not two (the old typo'd-hook bug was zero; a double-fire is two).
- **On fail**: `Notifications` listeners on `wb_listora_listing_status_changed` (`includes/workflow/class-notifications.php` ~171 region), `Status_Manager::on_transition`.

### 4. It shows in Tools > Email Log
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-email-log`.
- **Expect**: shared header "Email Log", views All / Sent / Failed, top row Email "Listing approved", To = AUTHOR_EMAIL, Result "Sent"; the row action "Send again" is offered; opening the row shows the full email body in an iframe.

### 5. Re-saving does not re-fire
- **Action**: open the listing's edit screen and click Update with no change.
- **Expect**: still ONE `listing_approved` row for this listing (count rows with that subject): the hook fires on a real transition only.

### 6. Rejected listings cannot be published straight (2.0.0)
- **Action**: Listings > filter "Rejected" > Quick Edit the rejected listing > Status = Published > Update. Repeat with Bulk Edit > Status Published, and `wp post update <REJ_ID> --post_status=publish`.
- **Expect**: status stays `listora_rejected`; the screen shows "This listing is Rejected, so it was not published. Set it to Pending Review, then approve it." (a warning notice with `listora-notice`); the CLI call leaves the status unchanged.
- **Action**: set it to Pending Review (Quick Edit), then Approve.
- **Expect**: it publishes; if expiry is on, `_listora_expiration_date` is set, and the approval email is sent.
- **On fail**: `Status_Manager::keep_status_on_invalid_move` (`class-status-manager.php` ~245), `transitions()` map (~120).

### 7. The owner's view (member-owner, 1440)
- **Action**: before step 2, as the pending listing's owner open its link from My Dashboard; after step 2 reload.
- **Expect**: before: 200 with the owner bar chip "Pending review" (preview); after: no chip change needed, chip reads "Published"; a different member (dana_whitfield) gets 404 before and 200 after.

### 8. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-6 | pass |
| qa_editor (has `edit_others_listora_listings`) | same Approve link | allowed; the email is sent |
| member-owner (Subscriber) | request the Approve URL directly | "Permission denied." (`edit_post` cap) and status unchanged |
| member-other | same | same |
| anonymous | same | redirected to login |

## Pass criteria

1. Approve moves a pending listing to `publish` once.
2. One `listing_approved` email row is logged with the author as recipient and success 1; none on a no-op save.
3. The Email Log screen shows it.
4. A rejected listing cannot be published without going through Pending Review, on every entry point.
5. Non-moderators cannot approve; owners can preview their pending listing.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Approve does nothing | capability or nonce failing | `Listing_Columns::handle_moderation_action` |
| Status flips but no email | listener on wrong hook name (regression of `0aa62ca`) | `class-notifications.php` hook registrations |
| Two email rows | hook firing on every `save_post` | `Status_Manager::on_transition` transition guard |
| Rejected publishes via Quick Edit | `wp_insert_post_data` guard missing | `Status_Manager::keep_status_on_invalid_move` |
