---
journey: listing-approve-reject-row-actions
plugin: wb-listora
priority: normal
roles: [administrator, editor, member, anonymous]
covers: [listings-admin-list, row-action-moderation]
prerequisites:
  - "A listing in `pending` status (create QA-J1 row action, author tester) per run"
estimated_runtime_minutes: 4
covers_card: 9910737903
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# One-click Approve / Reject row actions for pending listings

`Listing_Columns::row_actions()` (`includes/admin/class-listing-columns.php:666`, pending guard at `:690`) adds "Approve" (`listora_approve`, green) and "Reject" (`listora_reject`, red, class `listora-reject-listing`) for `pending` listings only. Links are `admin.php?action=listora_approve_listing|listora_reject_listing&post=<ID>&_wpnonce=...` handled by `handle_moderation_action()` (`:730`). They go through `wp_update_post()`, so the status-change chain (reindex + notification, `transition_allowed()`) runs.

Verified via: admin cookie jar + curl on two throwaway pending listings, 2026-10-12: approve link 302 -> `edit.php?post_type=listora_listing&listora_approved=1`, listing became `publish`; the notice strings exist.

## Steps

### 1. Row actions render on a pending listing
- **Action**: `/wp-admin/edit.php?post_type=listora_listing&post_status=pending`, hover the pending row.
- **Expect**: "Approve" and "Reject" in `.row-actions` beside Edit / Quick Edit / Trash / Preview. **POSITIVE CONTROL**: they do NOT render for a published or rejected row (same list with `post_status=publish`).

### 2. Approve transitions to publish
- **Action**: click Approve.
- **Expect**: redirect to the listings list with `listora_approved=1` and the notice "Listing approved and published."; status `publish`; the owner approval email is logged (`listing_approved`, see email-approval-send).

### 3. Reject transitions to listora_rejected
- **Action**: on another pending listing click Reject.
- **Expect**: redirect with `listora_rejected=1`; notice "Listing rejected."; status `listora_rejected`; a `listing_rejected` email row.

### 4. Security
- **Action**: open the approve URL with a bad/missing `_wpnonce`.
- **Expect**: `wp_die( 'Invalid request.' )` (`:736`). A user without `edit_post` on the listing gets "Permission denied." (`:740`). A non-pending listing redirects to the list without changing status (verified: the reject link of an already-approved listing redirected to the plain list URL with no flag).

### 5. Role ladder
- Administrator: steps 1-4. Editor (`qa_editor`, can edit others' listings): sees and uses the actions. Owner/member: no wp-admin list; opening the action URL gets "Permission denied." or the login redirect. Anonymous: login redirect. Admin is the actor; the member case is the negative control.

### 6. Large set
- The pending view is filtered by status in the query; with 2,716 drafts and 1 pending the list stays one page (50/page default). Not seeded further.

## Fail diagnostics

| Symptom | File |
|---|---|
| Actions missing | `includes/admin/class-listing-columns.php:690` `pending` guard |
| Status not changed | `:730` `handle_moderation_action()`, hooks at `:774` `handle_approve_listing()` |
| Notice missing | `:905` notice map |
