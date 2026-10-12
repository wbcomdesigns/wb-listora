---
journey: write-and-reply-to-a-review
plugin: wb-listora
priority: critical
roles: [subscriber, listing_owner, other-member, logged-out, moderator, admin]
covers: [reviews-flow, review-moderation-gate, helpful-vote, owner-reply, dashboard-reply-form, per-type-reviews-switch, block-reviewer, reviewer-name-member]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "Dev auto-login mu-plugin installed"
  - "Settings > Reviews > Auto-approve OFF (default; new reviews are `pending`), one review per listing ON, minimum length 20"
  - "One published listing owned by OWNER (fixture: QA-J1 Zephyrquill Cafe, listing type business). Below: REVIEWER = QA-J1-member, OTHER = QA-J1-other, OWNER = QA-J1-owner, moderator = qa_editor or admin"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Write and reply to a review

A member writes a review, it waits for moderation, a moderator approves it, another member marks it Helpful, the listing owner replies from My Dashboard, and the reply shows on the listing. Along the way: only the author may edit, nobody may vote or reply on an unpublished review, reviewers who never chose a display name show as "Member", and a type with Reviews switched off refuses every review action.

Verified via: Playwright (isolated context per role) for UI, curl REST with a nonce for gates and status codes, WP-CLI for DB.

## Setup

- Listing: `$SITE_URL/listing/qa-j1-zephyrquill-cafe/` (any published listing whose author is OWNER). Capture `LISTING_ID`.
- Dashboard: `$SITE_URL/my-listings/?tab=reviews`.
- DB clean: `DELETE FROM {prefix}listora_reviews WHERE listing_id=LISTING_ID; DELETE FROM {prefix}listora_review_votes WHERE review_id IN (...)`.
- One browser context per role (autologin is ignored while a session cookie exists).

## Steps

### 1. REVIEWER opens the Reviews tab
- **Action**: log in as REVIEWER, open the listing, click `#tab-reviews` ("Reviews", has `.listora-detail__tab-count`).
- **Expect**: `#tab-reviews[aria-selected="true"]`, `#panel-reviews` visible. With no review yet: `.listora-detail__reviews-empty` titled "No reviews yet" ("Be the first to share your experience with this listing.") and the button `.listora-reviews__write-btn` "Write a Review".
- **On fail**: `templates/blocks/listing-detail/tabs.php`, `src/interactivity/store.js:2849` (`toggleDetailReviewForm`)

### 2. NEGATIVE: too-short review is refused
- **Action**: click "Write a Review" (`#listora-detail-review-form` becomes visible). Choose `input[name="overall_rating"][value="4"]` (screen-reader text "4 stars"), `#listora-detail-review-title` = `QA-J1 great`, `#listora-detail-review-content` = `too short`; click "Submit Review".
- **Expect**: no request is sent; the browser validation message on `#listora-detail-review-content` is "Please lengthen this text to 20 characters or more (you are currently using 9 characters)." (field has `minlength="20"`). Server side the same body returns `400` `listora_review_too_short`.

### 3. Submit a valid 4-star review
- **Action**: set `#listora-detail-review-content` = `Great place, would visit again. QA-J1 journey review.`; click "Submit Review".
- **Expect**:
  - Network: `POST /wp-json/listora/v1/listings/LISTING_ID/reviews` body `{listing_id, overall_rating:4, title, content}` returns `201` `{"id": N, "status": "pending", "message": "Review submitted and pending approval."}`. Capture `REVIEW_ID`.
  - The panel shows "Awaiting approval" and "Review submitted and pending approval."; the review is NOT in the public list yet.
- **On fail**: `src/interactivity/store.js:2862` (`submitDetailReviewForm`), `includes/rest/class-reviews-controller.php:450` (`create_review`)

### 4. DB check
- **Action**: `SELECT id, status, overall_rating, helpful_count, owner_reply FROM {prefix}listora_reviews WHERE id=REVIEW_ID`
- **Expect**: one row, `status='pending'`, `overall_rating=4`, `helpful_count=0`, `owner_reply` NULL.

### 5. POSITIVE CONTROL is step 8; first prove the gates while it is pending
- **Action** (curl with each role's nonce):
  - OTHER `POST /reviews/REVIEW_ID/helpful`
  - OWNER `POST /reviews/REVIEW_ID/reply` body `{"content":"Thanks for the review, we appreciate it!"}`
  - OWNER and anonymous `GET /listings/LISTING_ID/reviews`
  - REVIEWER again `POST /listings/LISTING_ID/reviews`
  - OWNER `POST /listings/LISTING_ID/reviews`
  - logged out `POST /listings/LISTING_ID/reviews`
- **Expect**: helpful and reply return `403` code `listora_review_not_approved` ("This review is not published yet."); both lists return `reviews: []`, `summary.total 0`; second review by REVIEWER `409 listora_already_reviewed`; owner reviewing own listing `403 listora_own_listing`; logged out `401 listora_unauthorized`. The owner's dashboard Reviews tab does not list a pending review.

### 6. Moderator approves
- **Action**: as `qa_editor` or admin: Moderation > Reviews > Pending > Approve (or `PUT /reviews/REVIEW_ID {"status":"approved"}`, returns `200 {"updated":true}`).
- **Expect**: DB `status='approved'`; `GET /listings/LISTING_ID/reviews` now returns the review with `summary` `{average: 4, total: 1}`; the listing page tab count shows `1`.

### 7. Public rendering
- **Action**: open the Reviews tab as OTHER.
- **Expect**: `.listora-detail__reviews-summary` shows `4.0`, `.listora-rating[role="img"]` with `aria-label="Rated 4 out of 5"`, "1 review"; one `.listora-detail__review#review-REVIEW_ID` with author `Member` (REVIEWER never set a display name; the login is NOT shown), no profile link, title, content, buttons `.listora-detail__review-helpful-btn` ("Helpful", `aria-label="Mark as helpful"`), `.listora-detail__review-block-btn` ("Block"), `.listora-detail__review-report-btn` ("Report").

### 8. Helpful vote (POSITIVE CONTROL for step 5's refusal)
- **Action**: OTHER clicks `#review-REVIEW_ID .listora-detail__review-helpful-btn`.
- **Expect**: `POST /wp-json/listora/v1/reviews/REVIEW_ID/helpful` returns `200 {"helpful_count":1}`; the button reads "Helpful (1)", gains `is-voted` and becomes disabled; `listora_review_votes` has one row for (OTHER, REVIEW_ID). A second vote returns `409 listora_already_voted`; REVIEWER voting on their own review returns `403 listora_own_review`.
- **On fail**: `src/interactivity/store.js:2265` (`voteReviewHelpful`), `includes/rest/class-reviews-controller.php:850`

### 9. OWNER replies inline from the dashboard
- **Action**: log in as OWNER, open `$SITE_URL/my-listings/?tab=reviews`.
- **Expect**: `#dash-panel-reviews` lists `.listora-dashboard__review-row` with the star rating (`aria-label="Rated 4 out of 5"`), the listing link, the date, the content and a `.listora-dashboard__reply-trigger` button "Reply".
- **Action**: click "Reply"; type `Thank you for the kind words!` in `.listora-dashboard__reply-textarea`; click "Submit reply".
- **Expect**: the form `.listora-dashboard__reply-form` (inline, not a modal) loses `is-hidden`; the submit button shows exactly one of "Submit reply" / "Submitting…" (the other span has `is-hidden`); `POST /wp-json/listora/v1/reviews/REVIEW_ID/reply` returns `200 {"replied":true}`; after the reload the row shows "Edit reply" and "Your reply: Thank you for the kind words!".
- **On fail**: `src/interactivity/store.js:2361` (`openReplyForm`), `:2380` (`submitReply`), `includes/rest/class-reviews-controller.php:940`

### 10. DB and public page
- **Expect**: `owner_reply = 'Thank you for the kind words!'`, `owner_reply_at` set. On the listing's Reviews tab (logged out) `#review-REVIEW_ID .listora-detail__review-reply` reads "Owner Response: Thank you for the kind words!".

### 11. ROLE LADDER on the same review
| Actor | Action | Expect |
|---|---|---|
| REVIEWER (author) | `PUT /reviews/REVIEW_ID {"content": "..."}` on an approved review | `200 {"updated":true,"status":"pending","message":"Your changes were saved. This review is awaiting approval again before it shows publicly."}`: an edit sends the review back to moderation, so the public total drops until a moderator approves again |
| OTHER member | same PUT | `403 listora_forbidden` ("You do not have permission to perform this action.") |
| Logged out | same PUT, `POST /reviews/REVIEW_ID/helpful` | `401 listora_unauthorized` |
| OWNER | `POST /reviews/REVIEW_ID/reply` | `200 {"replied":true}` |
| OTHER member | `POST /reviews/REVIEW_ID/reply` (review approved) | `403 listora_forbidden` |
| REVIEWER | `POST /reviews/REVIEW_ID/helpful` on own review | `403 listora_own_review` |
| `qa_editor` (moderator) / admin (control) | approve (step 6), reply | `200` / `200 {"replied":true}` |

### 12. Block a reviewer (does not change the public total)
- **Action**: as OTHER click "Block" on the review (or `POST /me/blocks {"user_id": REVIEWER_ID}` returns `201 {"blocked":true}`).
- **Expect**: OTHER's `GET /listings/LISTING_ID/reviews` returns `reviews: []` but `summary` still `{average: 4, total: 1}`; a logged-out request still returns the review. `DELETE /me/blocks/REVIEWER_ID` restores it (`200 {"blocked":false}`).

### 13. Type switch OFF refuses everything
- **Action**: admin `PUT /listing-types/business {"reviews":"off"}` (Listing Types editor > Reviews: Off). Then try helpful, reply, report, edit, new review, and `GET /listings/LISTING_ID/reviews`.
- **Expect**: every call returns `403 listora_reviews_disabled` ("Reviews are currently disabled on this site."); the listing page has no `#tab-reviews`. **Control**: set it back to `global`; `#tab-reviews` returns and the same calls work again (helpful by a fresh member returns 200). Restore the setting before ending.

### 14. Scale and layout
- **Scale**: `GET /listings/LISTING_ID/reviews` is paginated (`total, pages, has_more, cursor`); a listing with 100+ reviews returns one page and the tab count shows the total. On this site the busiest listing has about 5 reviews, so seed 100+ reviews (`wb_listora_reviews`, status approved) on a QA listing to run this check.
- **390px**: Reviews tab, review form and dashboard Reply form: `scrollWidth <= 390`; star radios, Helpful / Block / Report and Submit buttons are at least 40px tall (aspect stars in the form are thumb-sized).

## Pass criteria

1. Valid review returns 201 `pending`; short review is refused.
2. Pending review is invisible, and vote/reply return 403 `listora_review_not_approved`.
3. After approval it shows as "Member" with `Rated 4 out of 5`, and Helpful returns 200 with a `helpful_count`.
4. Owner reply is inline, returns 200, persists `owner_reply`, renders as "Owner Response".
5. Only the author edits; others get 403; logged out gets 401.
6. Blocking hides it for the blocker only; totals unchanged.
7. Type Reviews OFF returns 403 `listora_reviews_disabled` on every action and hides the tab; ON restores it.

## Not verified

- Rate limiting (`429` with `Retry-After`) for `review_create` / `review_vote` / `review_reply` was not exercised because the site IP is shared by parallel agents; see `includes/class-rate-limiter.php:66`.
- Review photos upload and the Report modal submit were not walked.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Review created as `approved` unexpectedly | `reviews.auto_approve` on | `includes/rest/class-reviews-controller.php:496-510`, settings key `wb_listora_settings.reviews.auto_approve` |
| Helpful/reply works on a pending review | gate removed | `includes/helpers.php:899` (`wb_listora_review_gate`), `includes/rest/class-reviews-controller.php:1183,1197` |
| Reviewer shows as login name | display-name fallback regression | `includes/rest/class-reviews-controller.php` (`user_name`), `templates/blocks/listing-reviews/review-card.php` |
| Reply opens a modal / both spans visible | dashboard template or CSS | `templates/blocks/user-dashboard/tab-reviews.php`, `blocks/user-dashboard/style.css` (`.is-hidden`) |
| Reply POST 403 for the real owner | permission callback | `includes/rest/class-reviews-controller.php:1197` |
| Type OFF still shows the tab / allows votes | type switch not consulted | `includes/helpers.php:864` (`wb_listora_listing_reviews_enabled`), `includes/core/class-listing-type.php:299` |
| Blocked reviewer still visible / total changes | block filter | `includes/rest/class-reviews-controller.php` (list), `/me/blocks` route in `includes/rest/class-account-controller.php` |
