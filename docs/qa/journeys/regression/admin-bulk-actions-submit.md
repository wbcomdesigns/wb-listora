---
journey: admin-bulk-actions-submit
plugin: wb-listora
priority: critical
roles: [administrator, editor]
covers: [reviews-admin, claims-admin, submit-lock]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least one review that can be moderated and one claim (create a QA-J1- review via POST /listora/v1/listings/{id}/reviews as a member, and delete it after)"
estimated_runtime_minutes: 5
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
changed: true
---

# Bulk Apply and Search buttons on Reviews and Claims actually submit

Sentinel for `assets/js/shared/submit-lock.js`. The lock used to disable a button inside its own click handler; a disabled submit button never submits, so Bulk Apply turned into "Processing..." and posted nothing. The lock now engages on the form's `submit` event (`event.submitter` carrying `data-listora-submit-lock`).

2.0.0 note: Reviews and Claims now render through the shared admin table (`Admin_Table`): a `form.listora-admin-table-form[method=post]` with `select[name=bulk_action]` ("Bulk actions") and a `button[type=submit]` "Apply" (`.listora-bulk-actions`), and a GET filter form `form.listora-filter-bar[role=search]` whose button reads "Search" (there is no longer a "Filter" button). Neither Apply nor Search carries `data-listora-submit-lock` today, so the original failure cannot recur on these two screens; this journey keeps the behaviour pinned (Apply must post, notice must show) and the lock helper itself is checked in step 5.

## Steps

### 1. Reviews bulk
- **Action**: as administrator open `wp-admin/admin.php?page=listora-reviews&status=pending` (hub tab "Reviews" under Moderation), tick one row checkbox, Bulk actions -> "Reject", click Apply.
- **Expect**: one POST to the page then a redirect carrying `listora_notice=reject&n=1`; the notice reads "1 review rejected. It no longer shows on the listing." (`.notice.listora-notice`, computed-visible) and the row is gone from Pending. Choose "Approve" on another: "1 review approved. It now shows on the listing."
- **Record**: `GET /wp-json/listora/v1/listings/{id}` rating/review count changes with the status (see `admin-review-moderation-updates-rating.md`).

### 2. Claims search
- **Action**: Listora > Claims (`page=listora-claims`), type a listing name in "Search by listing, name or email", click "Search".
- **Expect**: GET reload with `s=<term>` in the URL and only matching rows; clearing it restores the list.

### 3. Claims bulk
- **Action**: tick a pending claim, Bulk actions -> "Reject", Apply.
- **Expect**: notice "1 claim rejected. The claimant has been told." and the claim's status is `rejected` in the Rejected view.

### 4. Double-click guard
- **Action**: double-click Apply with a selection.
- **Expect**: exactly one POST (network tab), one notice.

### 5. The lock helper itself (positive control for "locks, but only on submit")
- **Action**: `grep -n "'submit'\|'click'" assets/js/shared/submit-lock.js`.
- **Expect**: the lock engages in a `submit` listener that reads `event.submitter`; the `click` listener returns early for `type="submit"` buttons and only handles non-submit buttons that carry `data-listora-submit-lock`.

### 6. Role ladder
- **Editor (qa_editor)**: steps 1-3 work (editor holds `moderate_listora_reviews` and `manage_listora_claims`).
- **Subscriber / logged out**: the pages answer 403 or the login screen; no bulk bar.
- **Administrator**: control for all of the above.

### 7. Restore anything you changed (re-approve/delete the QA-J1- review, restore claim status).

## Fail diagnostics
| Symptom | Where |
|---|---|
| "Processing..." with no request | lock back in a `click` handler on a `type="submit"` button: `assets/js/shared/submit-lock.js:27-31` |
| Apply posts but nothing changes | bulk handler: `includes/admin/class-admin.php:1572` (reviews), `:2006` (claims) |
| Notice missing | `render_review_notice()` `includes/admin/class-admin.php:1784`, `render_claim_notice()` near `:2150` |
| Bulk bar markup | `includes/admin/class-admin-table.php:205-217` |

## Not verified
Click-through in a browser (curl/DOM only): Apply button markup and form method confirmed live on both screens; the POST round trip and notices were verified in source, not executed (the site has 0 pending reviews). No Playwright in this pass.
