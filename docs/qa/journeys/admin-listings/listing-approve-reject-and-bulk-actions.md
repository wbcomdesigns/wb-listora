---
journey: listing-approve-reject-and-bulk-actions
group: admin-listings
actor: admin
mode: mixed
plugin: wb-listora
priority: critical
roles: [administrator, editor, member-owner, member-other, anonymous]
covers: [listing-status-machine, status-changed-hook, rejected-needs-pending-first, owner-preview, 2.0.0-status-rules, listings-admin-list, row-action-moderation, "card 9910737903", bulk-edit, admin-columns, BC-10190576873, bulk-moderate-rest, moderation-transition-guard, edit-permission-admin-list]
prerequisites:
  - "Site reachable at $SITE_URL; {admin}, {editor}, {owner}, {other} sessions"
  - "Fixtures: wp eval-file docs/qa/fixtures/2.0-fixtures.php seed (prints pending and rejected ids), or create QA-prefixed listings: >= 3 pending (one per action), 1 rejected, 3 published of MIXED types (two registered listing types minimum)"
  - "Email baseline MAIL_BASE = SELECT COALESCE(MAX(id),0) FROM ${PREFIX}listora_email_log (emails themselves: emails-alerts/listing-status-emails.md)"
estimated_runtime_minutes: 12
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light for the owner preview]
supersedes:
  - ora:admin/01-approve-pending-listing.md
  - ora:regression/listing-approve-reject-row-actions.md
  - ora:regression/bulk-edit-listing-type-renders.md
  - ora:regression/one-rule-per-behaviour.md
fails_on: step 9 fails when the bulk control is bound to a core column (BC-10190576873, pre-fix sha unknown); step 5 sentinel for commit 0aa62ca is in emails-alerts/listing-status-emails.md
site_profile: required
---

# Admin approves, rejects and bulk-edits listings; a rejected listing cannot be published straight

## Why
Moderators move listings through pending, published and rejected from the Listings list. The status moves once, a rejected listing must go back to Pending Review first on EVERY entry point (Quick Edit, Bulk Edit, block editor, REST, WP-CLI), approving sets expiry only when expiry is on, and the Bulk Edit type control must actually render (WordPress skips core columns for `bulk_edit_custom_box`, so a control bound to `title` was never invoked and failed silently).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Row actions come from `Listing_Columns::row_actions()` (pending guard); links are `admin.php?action=listora_approve_listing|listora_reject_listing&post=<id>&_wpnonce=...` handled by `handle_moderation_action()` through `wp_update_post()` so the status-change chain runs.
- Capture `PENDING_ID`, `REJ_ID`.

## Steps

### 1. Row actions render on a pending listing only
- **Action**: `$SITE_URL/wp-admin/edit.php?post_type=listora_listing&post_status=pending` as {admin}; hover/focus the pending row.
- **Expect**: native list table under the hub tab row "All Listings | Leads"; row `tr#post-<PENDING_ID>` with "Approve" (green) and "Reject" (red, `.listora-reject-listing`) beside Edit / Quick Edit / Trash / Preview; "Pending (n)" count tab.
- **Positive control**: the same list with `post_status=publish` and `post_status=listora_rejected` shows neither action.

### 2. Approve moves pending to publish once
- **Action**: click "Approve".
- **Expect**: 302 to `edit.php?post_type=listora_listing&listora_approved=1`; notice "Listing approved and published." under the title; row gone from Pending; `wp post get <id> --field=post_status` = `publish`; if expiry is on `_listora_expiration_date` is set, if off it is empty.

### 3. Reject moves pending to rejected
- **Action**: click "Reject" on another pending listing.
- **Expect**: `listora_rejected=1`, notice "Listing rejected.", status `listora_rejected`.

### 4. Security
- **Action**: open the approve URL with a bad/missing `_wpnonce`.
- **Expect**: `wp_die( 'Invalid request.' )`. A user without `edit_post` gets "Permission denied.". A non-pending listing redirects to the list with no flag and unchanged status (the reject link of an already-approved listing).

### 5. Rejected listings cannot be published straight
- **Action**: Quick Edit the rejected listing, Status = Published, Update; repeat with Bulk Edit > Status Published; `wp post update <REJ_ID> --post_status=publish`; and REST `POST /listora/v1/listings/bulk-moderate {"ids":[<REJ_ID>],"action":"approve"}`.
- **Expect**: status stays `listora_rejected`; the screen shows "This listing is Rejected, so it was not published. Set it to Pending Review, then approve it." (a `listora-notice` warning); the CLI call leaves status unchanged; REST puts the id in `failed[]` with `error: listora_invalid_transition`.
- **Action**: set it to Pending Review (Quick Edit), then Approve.
- **Expect**: it publishes and (if expiry is on) `_listora_expiration_date` is set.
- **Negative control**: `bulk-moderate` approve of a pending listing succeeds (otherwise the above is vacuous).

### 6. A published listing cannot be rejected
- **Expect**: `bulk-moderate {"action":"reject"}` on a `publish` listing returns `listora_invalid_transition`; reject is Pending-only.

### 7. The owner's view
- **Action**: as {owner} open the pending listing from My Dashboard before step 2 and after.
- **Expect**: before: 200 with the owner-bar chip "Pending review"; after: chip reads "Published". {other} gets 404 before and 200 after.

### 8. Bulk Edit: the type control renders and is computed-visible
- **Action**: as {admin} tick 2+ rows, Bulk actions > Edit > Apply.
- **Expect**: `select[name="listora_bulk_listing_type"]` inside `#bulk-edit` with `getComputedStyle(el).display !== 'none'`; first option "— No Change —" (value `""`) then every registered type; nonce `_wb_listora_bulk_type_nonce`. Static check: `grep -n "bulk_edit_custom_box\|'listora_type' !== \$column_name" includes/admin/class-listing-bulk-actions.php` shows the callback bound to the plugin-owned column `listora_type`, never `cb`, `title`, `author`, `date`, `comments`. There is no Quick Edit type control (single listing: Listing Type metabox, or Bulk Edit with one row).

### 9. Applying a bulk type changes the data
- **Action**: select rows of MIXED types, choose type {type}, Update.
- **Expect**: redirect `updated=N&skipped=0`; each listing's `listora_listing_type` term equals the chosen type (assert each individually); `wb_listora_listing_type_changed` fired per changed listing; field values saved under the previous type are kept.
- **Test-data trap**: rows already sharing one type pass without the save path working.

### 10. "— No Change —" changes nothing
- **Action**: re-run with the control at its default over fresh MIXED-type rows.
- **Expect**: every listing keeps its own type (`assign_type()` returns without writing for `""`). The 2026-10-12 run used rows that already shared a type: not exercised mixed.

### 11. Large site and bounds
- **Expect**: select-all on a page (20/50) and a filtered view work; the control renders once per panel, not per row; `bulk-moderate` is bounded to 100 ids per request and reports `requested`/`succeeded`/`failed`; the pending view is filtered by status in the query and stays one page with many drafts. Seed 2000+ drafts for the scale check. Not verified beyond the reference site.

### 12. 390px (added)
- **Expect**: row actions wrap, each >= 40 px; Bulk Edit panel does not overflow. Not verified live.

## Role ladder

| Rung | Action | Expect |
|---|---|---|
| {admin} | steps 1-12 | pass |
| {editor} (has `edit_others_listora_listings`) | same Approve link | allowed; email sent; Bulk Edit panel has NO type control (needs `manage_listora_settings`) although status bulk-edit works; their Bulk Edit saves must not touch types |
| {owner} (Subscriber) | request the Approve URL directly | "Permission denied." (`edit_post`), status unchanged; bulk-moderate 403 `rest_forbidden` |
| {other} | same | same |
| {guest} | same | login redirect; REST 401 |

## Pass criteria
1. Approve moves pending to `publish` once; Reject to `listora_rejected`; actions show for pending only.
2. A rejected listing cannot be published without going through Pending Review, on every entry point.
3. The bulk type control renders, applies per row, and "No Change" is inert.
4. Non-moderators cannot approve; owners can preview their pending listing.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Actions missing | pending guard | `includes/admin/class-listing-columns.php` `row_actions` |
| Approve does nothing / status not changed | cap or nonce | `Listing_Columns::handle_moderation_action` / `handle_approve_listing` |
| Notice missing | notice map | `class-listing-columns.php` |
| Rejected publishes via Quick Edit | `wp_insert_post_data` guard missing | `includes/workflow/class-status-manager.php` `keep_status_on_invalid_move`, `transition_allowed()` |
| Panel has no type control | column binding / capability | `includes/admin/class-listing-bulk-actions.php` `render_bulk_edit_type_field` |
| Posted type ignored | nonce / `bulk_edit` in `$_REQUEST` | `class-listing-bulk-actions.php` save handler |
| All rows reset on no-op | `assign_type('')` writes | `includes/admin/class-listing-type-metabox.php` |

## Not verified
Steps 7, 10 (mixed no-op), 11 scale, 12; the editor role-ladder rung for bulk-moderate.

## Cleanup
Delete the QA-prefixed listings; restore any type you bulk-changed; remove email-log rows with `id > MAIL_BASE` created by the run.
