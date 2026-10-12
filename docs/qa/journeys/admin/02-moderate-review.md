---
journey: admin-moderate-review
plugin: wb-listora
priority: critical
roles: [administrator, qa_editor, member-owner, member-other, anonymous]
covers: [reviews-rest-status-transition, review-status-hook, review-aggregate-recompute, reviews-admin-table, 2.0.0-review-gate]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Admin user (?autologin=1) with moderate_listora_reviews; qa_editor (Editor) also has it"
  - "At least 1 review with status='pending' in wp_listora_reviews (fixtures: key review_pending, listing 'J20 Owner Published')"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default; buddyx-pro light for the listing page]
---

# Admin moderates a review through the same REST path the API uses

The Reviews screen (Moderation hub > Reviews, `admin.php?page=listora-reviews`) dispatches `PUT /listora/v1/reviews/{id}` server-side (`Admin::moderate_review()`), so moderation gets the same hooks, cache busts and rating recompute as the API. A non-moderator cannot change a review's status through the API.

## Setup

- `wp eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed` (or any pending review). Capture `REVIEW_ID`, `LISTING_ID`:
  ```bash
  wp eval 'global $wpdb; print_r($wpdb->get_row("SELECT id,listing_id,user_id,status FROM {$wpdb->prefix}listora_reviews WHERE status=\"pending\" ORDER BY id DESC LIMIT 1", ARRAY_A));'
  ```
- Baseline aggregate: `SELECT review_count, avg_rating FROM wp_listora_search_index WHERE listing_id=LISTING_ID`.

## Steps

### 1. Open the screen (administrator, 1440)
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-reviews&autologin=1`.
- **Expect**: shared header "Reviews" with the line "Approve reviews before they appear on listings, and see which ones visitors reported."; view tabs All (n) / Pending (n) / Approved (n) / Rejected (n); with pending reviews the Pending view is the default; filters "Any rating", "Any listing type", "Any time", "Owner reply: any", "Reported or not"; columns Review, Listing, Author, Rating, Status, Date; the pending review is listed with a "Pending" badge.
- **On fail**: `Admin::render_reviews_page` (`includes/admin/class-admin.php` ~1618).

### 2. Approve
- **Action**: row action "Approve" (`action=approve&review_id=...` with the `listora_review_action` nonce), or tick the row and use the bulk "Approve".
- **Expect**: redirect back with a success notice "1 review approved. It now shows on the listing."; the review moves to the Approved view; no browser REST call (it is a server-side dispatch).
- **Repeat** the same link from a stale tab: info notice "1 review was already in that state or no longer exists (perhaps changed in another tab or by another moderator) and was left as it was." (concurrency).
- **On fail**: `Admin::handle_review_actions` (~1560), `moderate_review` (~1541).

### 3. DB and aggregate
- **Action**:
  ```sql
  SELECT status FROM wp_listora_reviews WHERE id=REVIEW_ID;
  SELECT review_count, avg_rating FROM wp_listora_search_index WHERE listing_id=LISTING_ID;
  ```
- **Expect**: `approved`; the aggregate equals count and average of the listing's approved reviews, rounded to one decimal (one public number across page, cards, REST and JSON-LD).

### 4. Status-changed hook fired once
- **Action**: a throwaway mu-plugin (a `wp eval` listener dies with the process):
  ```php
  <?php add_action( 'wb_listora_review_status_changed', function ( $id, $new, $listing ) { error_log( "STATUS_CHANGED:$id:$new:$listing" ); }, 10, 3 );
  ```
  Reject, then Approve again from the screen; tail `debug.log`.
- **Expect**: `STATUS_CHANGED:<id>:rejected:<listing>` then `...:approved:...`, once each; re-approving an approved review prints nothing. Delete the mu-plugin.

### 5. It renders publicly
- **Action**: `$SITE_URL/listing/<slug>/#tab-reviews` as anonymous.
- **Expect**: the review text is listed under the author's public name (or "Member" when the author has no display name); the summary shows "Rated N out of 5" for the new average; a review posted a minute ago reads "x minutes ago" (never hours).
- **Positive control**: before step 2 the same page did NOT show it (pending is visible only to its author and moderators).

### 6. Reject and delete
- **Action**: Reject then Delete the review.
- **Expect**: notices "1 review rejected. It no longer shows on the listing." and "1 review deleted."; aggregate recomputed each time.

### 7. Role ladder
| Rung | Action | Expect |
|---|---|---|
| administrator | steps 1-6 | pass |
| qa_editor (Editor) | open the screen, approve | allowed (Editor has `moderate_listora_reviews`) |
| member-owner of the LISTING (qa_vendor_01) | `PUT /reviews/<id> {"status":"approved"}` via `wp.apiFetch` | 403 `listora_forbidden` (owners reply, they do not moderate) |
| review author (dana) | `PUT /reviews/<id> {"status":"approved"}` | 403 `listora_forbidden_status` (authors can edit content, never status) |
| member-other (tester) | same | 403 `listora_forbidden` |
| anonymous | same | 401 `listora_unauthorized` |
| anyone, screen URL | subscriber opens `admin.php?page=listora-reviews` | "Sorry, you are not allowed to access this page." |

## Pass criteria

1. Approve/Reject/Delete work from the screen with the documented notices and once-only semantics.
2. `wp_listora_reviews.status` and the listing aggregate agree after each action.
3. `wb_listora_review_status_changed` fires once per real transition.
4. The review appears publicly only when approved.
5. Only moderators can change status; authors can edit content; everyone else is refused with the documented codes.

## Fail diagnostics

| Symptom | Likely cause | File |
|---|---|---|
| Approve button does nothing | handler not hooked on `admin_init` / nonce | `Admin::handle_review_actions` |
| Aggregate stale | recompute skipped on the admin path | `Listing_Data::recompute_rating_aggregate` |
| Hook not fired | transition guard / admin path bypassing REST | `Reviews_Controller::update_review` (~754) |
| Author can self-approve | cap check missing in the status branch | `Reviews_Controller` (~692, `listora_forbidden_status`) |
