---
journey: admin-moderate-review
plugin: wb-listora
priority: critical
roles: [administrator, editor, member-owner, member-other, review-author, anonymous]
covers: [reviews-rest-status-transition, review-status-hook, review-aggregate-recompute, reviews-admin-table, review-detail-drawer, review-reply-gate, review-bulk-actions, 2.0.0-review-gate, card-10328137367]
prerequisites:
  - "Site reachable at $SITE_URL; {admin} with `moderate_listora_reviews`; {editor} (Editor also has it)"
  - "A pending review and an approved review with criteria_ratings (fixtures: `docs/qa/fixtures/2.0-fixtures.php seed`, keys `review_pending`, `review_published`; or QA-J1 rows). Review must not be by the listing {owner}"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light for the listing page]
site_profile: required
supersedes: ["ora:regression/admin-review-moderation-updates-rating.md", "ora:regression/review-moderation-full-view-and-reply-gate.md"]
---

# Admin moderates a review through the same REST path the API uses

## Why
The Reviews screen dispatches `PUT /listora/v1/reviews/{id}` server-side (`Admin::moderate_review()`, `includes/admin/class-admin.php:1541-1554`), so moderation gets the same hooks, cache busts and rating recompute as the API. A direct row write once left `avg_rating`/`review_count` stale so approved reviews never counted on cards, search or sort.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Capture `REVIEW_ID`, `LISTING_ID`:
  `SELECT id,listing_id,user_id,status FROM {prefix}listora_reviews WHERE status='pending' ORDER BY id DESC LIMIT 1`.
- Baseline aggregate: `SELECT review_count, avg_rating FROM {prefix}listora_search_index WHERE listing_id=LISTING_ID` (pending reviews do not count).
- Screen: Moderation hub > Reviews, `admin.php?page=listora-reviews[&status=pending|approved|all]`. Handler `Admin::handle_review_actions` (~1560), nonce `listora_review_action` (single) / `listora_review_bulk` (bulk), notices `class-admin.php:1784-1808`.

## Steps

### 1. Open the screen ({admin}, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-reviews&autologin=1`.
- **Expect**: shared header "Reviews" + "Approve reviews before they appear on listings, and see which ones visitors reported."; views All (n) / Pending (n) / Approved (n) / Rejected (n), Pending default when any pending; filters "Any rating", "Any listing type", "Any time", "Owner reply: any", "Reported or not"; columns Review, Listing, Author, Rating, Status, Date; the pending review with a "Pending" badge.
- **Read-back**: tab counts equal `SELECT status, COUNT(*) FROM {prefix}listora_reviews GROUP BY status`.

### 2. Read the whole review (Details drawer)
- **Action**: `...&status=pending`, click **Details** (`button.listora-action-link[data-listora-drawer="reviews-detail-<id>"]`).
- **Expect**: native `<dialog class="listora-drawer" id="reviews-detail-<id>">` showing rating (`.listora-rating[role=img][aria-label="Rated N out of 5"]`), reviewer name and email, title (`h3`), the full text and, under **Owner reply**, the muted line "You can reply once the review is approved."; no `.listora-reply-form` for pending rows; a row over 18 words shows a clamped excerpt (`.listora-clamp`); row actions Approve, Reject and a `...` menu with Delete.
- **Read-back**: drawer text equals the stored `content`.

### 3. Approve (single)
- **Action**: row action "Approve" (`review_action=approve&review_id=...`).
- **Expect**: redirect `...&listora_notice=approve&n=1&same=0`; notice "1 review approved. It now shows on the listing."; the review moves to Approved; no browser REST call (server-side dispatch). Repeat the same link from a stale tab: info notice "1 review was already in that state or no longer exists (perhaps changed in another tab or by another moderator) and was left as it was." (`same=1`).
- **Read-back**: step 4.

### 4. DB and aggregate
- **Action**: `SELECT status FROM {prefix}listora_reviews WHERE id=REVIEW_ID;` and the aggregate query.
- **Expect**: `approved`; aggregate equals count and average of the listing's approved reviews, rounded to one decimal (one public number across page, cards, REST and JSON-LD); baseline was `0.00`/`0`.

### 5. Reject, then approve again (aggregate is recomputed, not incremented)
- **Action**: Reject then Approve again.
- **Expect**: "1 review rejected. It no longer shows on the listing." and the index returns to `0.00`/`0`; approve again restores it. Each time the index matches the reviews table (positive control for "rejected reviews do not count").
- **Read-back**: aggregate after each click.

### 6. Bulk
- **Action**: tick one row, Bulk actions > Reject (or Approve), Apply (`form.listora-admin-table-form`).
- **Expect**: redirect `listora_notice=reject&n=1`, the notice above, that listing's aggregate matches (`0.00`/`0` after a bulk reject); already-in-state rows give the info notice with `same=1`. Multi-row: tick two, notice "2 reviews ...".
- **Read-back**: aggregate.

### 7. Delete
- **Action**: row Delete.
- **Expect**: "1 review deleted."; row gone; aggregate recomputed (`0.00`/`0`).
- **Read-back**: `wb_listora_before_delete_review` runs and `wb_listora_after_delete_review` fires.

### 8. Status-changed hook fires once
- **Action**: a throwaway mu-plugin (a `wp eval` listener dies with the process): `add_action( 'wb_listora_review_status_changed', function ( $id, $new, $listing ) { error_log( "STATUS_CHANGED:$id:$new:$listing" ); }, 10, 3 );` Reject, then Approve; tail `debug.log`.
- **Expect**: `STATUS_CHANGED:<id>:rejected:<listing>` then `...:approved:...`, once each; re-approving an approved review prints nothing. Hook args from `class-reviews-controller.php:753-758`. Delete the mu-plugin.

### 9. Reply is offered only for approved reviews
- **Action**: open the approved review's drawer; `POST /wp-json/listora/v1/reviews/<pending id>/reply` with the REST nonce.
- **Expect**: approved: `.listora-reply-form[data-review-id]` with `textarea.listora-reply-textarea` and **Post reply** (`.listora-reply-submit`); a reply returns `200` and shows under the review on the listing (clear it afterwards); pending: `403 listora_review_not_approved` "This review is not published yet." even for {admin} (same code for `helpful` and `report`). If Settings > Reviews "Enable replies" is off, `403 listora_replies_disabled` and the forms are hidden.
- **Read-back**: `owner_reply` set only on the approved row. Full gate matrix: `customer/review-actions-gated-on-every-route.md`.

### 10. It renders publicly, and the criteria with it
- **Action**: `$SITE_URL/listing/{listing}/#tab-reviews` as {guest}.
- **Expect**: the review is listed under the author's public name (or "Member"); summary "Rated N out of 5" for the new average; a review posted a minute ago reads "x minutes ago"; criteria display per `customer/03-write-and-reply-to-a-review.md` step 12. Positive control: before step 3 the page did NOT show it (pending is visible only to its author and moderators).

### 11. Large list
- **Action**: seed a QA-prefixed 2000+ review set, open the screen, filter by status, rating, listing type, period, reply and reported.
- **Expect**: paged, filters use indexed columns, the page does not load every row; count line uses `COUNT(*)`. Not verified live.

### 12. 390px
- **Expect**: the table collapses lower-priority columns, no horizontal scroll, row actions >= 40px (wp-admin tap-target exception: `CLAUDE.md` admin buttons). Not verified live.

## Role ladder
| Rung | Action | Expect |
|---|---|---|
| {admin} (control) | steps 1-10 | pass |
| {editor} | open the screen, approve | allowed and the aggregate updates (via REST with the editor's cap); page-access matrix: `admin/moderation-and-settings-screen-capabilities.md` |
| Listing {owner} | `PUT /reviews/<id> {"status":"approved"}` via `wp.apiFetch` | `403 listora_forbidden` (owners reply, they do not moderate) |
| Review author | same | `403 listora_forbidden_status` (authors edit content, never status) |
| member-other | same | `403 listora_forbidden` |
| {guest} | same; wp-admin URL | `401 listora_unauthorized`; redirect to wp-login |
| subscriber | `admin.php?page=listora-reviews` | "Sorry, you are not allowed to access this page." |

## Pass criteria
1. Approve/Reject/Delete work single and bulk with the documented notices and once-only semantics.
2. `listora_reviews.status` and the listing aggregate agree after each action.
3. `wb_listora_review_status_changed` fires once per real transition.
4. Reply is offered and accepted only for approved reviews; the drawer shows the whole review.
5. The review appears publicly only when approved; only moderators change status.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Approve does nothing / no notice | handler not hooked on `admin_init` / nonce | `Admin::handle_review_actions` (~1560) |
| Aggregate stale | admin writes the row instead of `moderate_review()` | `class-admin.php:1541`, `Listing_Data::recompute_rating_aggregate` |
| Nothing happens, no error | REST permission refused (Reviews off or no `moderate_listora_reviews`) | `class-reviews-controller.php:684-700` |
| Hook not fired | transition guard / admin bypassing REST | `Reviews_Controller::update_review` (~754) |
| Author can self-approve | cap check missing | `class-reviews-controller.php` (~692) |
| Reply offered on pending | drawer branch | `class-admin.php:1944` (`'approved' !== $rev['status']`) |
| 200 reply on pending | permission callback | `class-reviews-controller.php:1197`, `includes/helpers.php:899` |

## Not verified
Editor/subscriber rows and the hook logger were not executed; drawer opening and 390 in a browser; steps 9-12 reply POSTs, public criteria and scale; steps verified by curl with real nonce'd links and a bulk POST, DB via wp eval.

## Cleanup
Delete the QA-J1 reviews/listing; rebuild the index if real listings were touched (Settings > Advanced > Rebuild Search Index, or `wp listora reindex`); `wp eval-file $WP_PATH/wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php clean`.
