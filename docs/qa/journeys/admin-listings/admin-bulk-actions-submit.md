---
journey: admin-bulk-actions-submit
group: admin-listings
actor: admin
mode: browser
plugin: wb-listora
priority: critical
roles: [administrator, editor, subscriber, guest]
covers: [reviews-admin, claims-admin, submit-lock]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one pending review (create `QA-J1-` review via `POST /listora/v1/listings/{id}/reviews` as {owner}, delete after) and one pending claim"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default]
site_profile: required
supersedes: []
fails_on: []
---

# Bulk Apply and Search buttons on Reviews and Claims actually submit

## Why
The submit lock used to disable a button inside its own click handler; a disabled submit button never submits, so Bulk Apply turned into "Processing..." and posted nothing. Moderators could not approve or reject.

Sentinel for `assets/js/shared/submit-lock.js`: the lock now engages on the form's `submit` event (`event.submitter` carrying `data-listora-submit-lock`). Reviews and Claims render through the shared admin table (`Admin_Table`): a `form.listora-admin-table-form[method=post]` with `select[name=bulk_action]` and `button[type=submit]` "Apply" (`.listora-bulk-actions`), and a GET filter form `form.listora-filter-bar[role=search]` whose button reads "Search". Neither carries `data-listora-submit-lock` today, so the original failure cannot recur on these screens; the journey pins the behaviour (Apply must post, notice must show) and checks the helper in step 5.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Review moderation rating effects: `admin-review-moderation-updates-rating.md` (cross-area).
- Restore anything changed (re-approve/delete the `QA-J1-` review, restore claim status).

## Steps

### 1. Reviews bulk
- **Action**: as {admin} open `admin.php?page=listora-reviews&status=pending`, tick one row, Bulk actions -> "Reject", Apply.
- **Expect**: one POST then a redirect carrying `listora_notice=reject&n=1`; the notice reads "1 review rejected. It no longer shows on the listing." (`.notice.listora-notice`, computed-visible) and the row is gone from Pending. Choose "Approve" on another: "1 review approved. It now shows on the listing."
- **Read-back**: `GET /wp-json/listora/v1/listings/{id}` rating/review count changes with the status.

### 2. Claims search
- **Action**: Listora > Claims (`page=listora-claims`), type a listing name in "Search by listing, name or email", click "Search".
- **Expect**: GET reload with `s=<term>` in the URL and only matching rows; clearing restores the list.

### 3. Claims bulk
- **Action**: tick a pending claim, Bulk actions -> "Reject", Apply.
- **Expect**: notice "1 claim rejected. The claimant has been told." and the claim's status is `rejected` in the Rejected view.

### 4. Double-click guard
- **Action**: double-click Apply with a selection.
- **Expect**: exactly one POST (network tab), one notice.

### 5. The lock helper itself
- **Action**: `grep -n "'submit'\|'click'" assets/js/shared/submit-lock.js`.
- **Expect**: the lock engages in a `submit` listener that reads `event.submitter`; the `click` listener returns early for `type="submit"` buttons and only handles non-submit buttons carrying `data-listora-submit-lock`.

### 6. Empty and boundary selection
- **Action**: click Apply with nothing ticked and with no action chosen; search with an empty string, 300 characters and `<script>`.
- **Expect**: no POST (or a "choose an action" notice) for the empty cases; the search term is escaped in the input and results, never executed. Not verified live (added in consolidation).

### 7. Large lists and 390px
- **Action**: with 2000+ reviews, bulk-select the first page; at 390 view the table.
- **Expect**: the bulk applies to the selected page only and the count in the notice equals the ticked rows; the table scrolls inside its wrapper with no page-level horizontal scroll. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {editor} | steps 1-3 work (holds `moderate_listora_reviews` and `manage_listora_claims`) |
| subscriber / {guest} | the pages answer 403 or the login screen; no bulk bar |
| {admin} | control for all of the above |

## Pass criteria
1. Apply posts and the notice shows with the right count; Search reloads with `s=`.
2. A double click posts once.
3. The lock engages only on `submit`, never on the click of a submit button.

## Fail diagnostics
| Symptom | Where |
|---|---|
| "Processing..." with no request | lock back in a `click` handler on a `type="submit"` button: `assets/js/shared/submit-lock.js:27-31` |
| Apply posts but nothing changes | bulk handler: `includes/admin/class-admin.php:1572` (reviews), `:2006` (claims) |
| Notice missing | `render_review_notice()` `class-admin.php:1784`, `render_claim_notice()` near `:2150` |
| Bulk bar markup | `includes/admin/class-admin-table.php:205-217` |

## Not verified
- Click-through in a browser (curl/DOM only): Apply markup and form method confirmed live on both screens; the POST round trip and notices were verified in source, not executed (no pending reviews were available); steps 6 and 7 added in consolidation.

## Cleanup
- Re-approve or delete the `QA-J1-` review created through `POST /listora/v1/listings/{id}/reviews` (`wp comment delete <id> --force`); step 1 rejects or approves it, so check its status first.
- Restore the claim step 3 rejected to its prior status (`pending`) from the Claims screen, or delete the QA claim row.
- Clear the search term typed in step 2 and the `<script>` probe from step 6 (they live only in the URL, nothing is stored).
