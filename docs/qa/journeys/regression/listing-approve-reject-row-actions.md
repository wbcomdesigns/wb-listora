---
journey: listing-approve-reject-row-actions
plugin: wb-listora
priority: normal
roles: [administrator]
covers: [listings-admin-list, row-action-moderation]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A listing in `pending` status exists (capture as LISTING_ID)"
estimated_runtime_minutes: 4
covers_card: 9910737903
---

# One-click Approve / Reject row actions for pending listings

Regression sentinel for the moderation-UX gap: the listings admin list only
offered "Mark verified" (for `pending_verification`); standard `pending`
listings had no inline Approve/Reject and needed Quick Edit or the editor.

Fix: `Listing_Columns::row_actions()` adds Approve + Reject for `pending`
status, transitioning to the canonical moderation statuses used by the
bulk-moderate REST endpoint (`publish` / `listora_rejected`). The status change
fires `transition_post_status` → search reindex + notification chain — no
manual dispatch. Both are nonce-protected and capability-gated (`edit_post`).

## Steps

### 1. Row actions render on a pending listing
- **Action**: `/wp-admin/edit.php?post_type=listora_listing&post_status=pending`,
  hover the pending row.
- **Expect**: row actions include "Approve" (green) and "Reject" (red), in
  addition to Edit / Quick Edit / Trash / Preview. They do NOT render for
  already-published or rejected listings.

### 2. Approve transitions to publish
- **Action**: click Approve (a nonce'd `admin.php?action=listora_approve_listing`
  link).
- **Expect**: redirect to the listings list with `listora_approved=1`; a
  success notice "Listing approved and published."; the post status is now
  `publish`. (Owner approval email fires via the normal status-change chain.)

### 3. Reject transitions to listora_rejected
- **Action**: on another pending listing, click Reject.
- **Expect**: redirect with `listora_rejected=1`; notice "Listing rejected.";
  status is now `listora_rejected`.

### 4. Security
- **Action**: hit the approve URL with a bad/missing `_wpnonce`.
- **Expect**: `wp_die('Invalid request.')`. A user without `edit_post` on the
  listing gets "Permission denied." A non-pending listing redirects without
  changing status.
