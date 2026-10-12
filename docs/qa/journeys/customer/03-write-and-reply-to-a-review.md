---
journey: write-and-reply-to-a-review
plugin: wb-listora
priority: critical
roles: [subscriber, listing_owner, other-member, logged-out, moderator, admin]
covers: [reviews-flow, review-moderation-gate, helpful-vote, owner-reply, dashboard-reply-form, review-criteria-ratings, review-criteria-display, bc-10351338091, card-10355301658, card-10328137367]
prerequisites:
  - "Site reachable at $SITE_URL; dev auto-login mu-plugin installed"
  - "Settings > Reviews: Auto-approve OFF (default), one review per listing ON, minimum length 20, Enable replies ON"
  - "One published {listing} of {type} with Review Criteria configured (food, service, ambiance, value on the restaurant type), owned by {owner}; {other} is a member who has not reviewed it"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:regression/review-criteria-ratings-saved.md", "ora:regression/review-moderation-full-view-and-reply-gate.md (criteria display step)"]
fails_on: 4b95d7fc (step 14 only; per-step shas live in the step)
---

# A member writes a review, a moderator approves it, the owner replies

## Why
A review that is public before moderation, a vote or reply on an unpublished review, or per-criterion scores that are not stored, break trust for reviewers, owners and visitors alike.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). REVIEWER = {other}, OTHER2 = a second member, OWNER = {owner}, moderator = {editor}, control = {admin}.
- `LISTING_ID` = ID of the {listing}; page `$SITE_URL/listing/{listing}/`; dashboard `$SITE_URL/my-listings/?tab=reviews`.
- DB clean: `DELETE FROM ${PREFIX}listora_reviews WHERE listing_id=LISTING_ID` (and its `listora_review_votes`).
- One browser context per role (autologin is ignored while a session cookie exists).
- Code map: `src/interactivity/store.js` (`toggleDetailReviewForm`, `submitDetailReviewForm`, `voteReviewHelpful`, `openReplyForm`, `submitReply`), `includes/rest/class-reviews-controller.php` (`create_review`, vote, reply), `includes/helpers.php` (`wb_listora_review_gate`).

## Steps

### 1. Reviews tab, empty state
- **Action**: as REVIEWER open the listing, click `#tab-reviews`.
- **Expect**: `#tab-reviews[aria-selected="true"]`, `#panel-reviews` visible; with no review: `.listora-detail__reviews-empty` "No reviews yet" and `.listora-reviews__write-btn` "Write a Review".
- **Read-back**: `SELECT COUNT(*) FROM ${PREFIX}listora_reviews WHERE listing_id=LISTING_ID AND status='approved'` is 0.

### 2. Too-short review is refused
- **Action**: Write a Review, `input[name="overall_rating"][value="4"]`, `#listora-detail-review-title`=`QA-J1 great`, `#listora-detail-review-content`=`too short`, Submit.
- **Expect**: no request; browser message on the textarea "Please lengthen this text to 20 characters or more (you are currently using 9 characters)."
- **Read-back**: the same body over REST returns `400 listora_review_too_short`; row count unchanged.

### 3. Valid 4-star review
- **Action**: content `Great place, would visit again. QA-J1 journey review.`, Submit.
- **Expect**: `POST /wp-json/listora/v1/listings/LISTING_ID/reviews` returns `201 {"id":N,"status":"pending","message":"Review submitted and pending approval."}`; panel shows "Awaiting approval"; the review is not in the public list. (Form stays on the page: see `customer/review-form-settings-take-effect.md` for the no-reload and auto-approve contract.)
- **Read-back**: capture `REVIEW_ID`; `SELECT status, overall_rating, helpful_count, owner_reply FROM ${PREFIX}listora_reviews WHERE id=REVIEW_ID` = `pending`, 4, 0, NULL.

### 4. Create gates
- **Action** (curl with each role's nonce): REVIEWER again; OWNER on own listing; logged out; each `POST /listings/LISTING_ID/reviews`.
- **Expect**: `409 listora_already_reviewed`; `403 listora_own_listing`; `401 listora_unauthorized`.
- **Read-back**: review row count for LISTING_ID still 1.

### 5. Pending review is invisible
- **Action**: OWNER and anonymous `GET /listings/LISTING_ID/reviews`; open OWNER's dashboard Reviews tab.
- **Expect**: `reviews: []`, `summary.total 0`; the owner's dashboard does not list it. (Vote/report/reply refusal on a pending review is asserted once in `customer/review-actions-gated-on-every-route.md`.)

### 6. Moderator approves
- **Action**: as {editor}: Moderation > Reviews > Pending > Approve (full screen contract: `admin/02-moderate-review.md`), or `PUT /reviews/REVIEW_ID {"status":"approved"}`.
- **Expect**: `200 {"updated":true}`; `GET /listings/LISTING_ID/reviews` returns it with `summary` `{average:4,total:1}`; tab count `1`.
- **Read-back**: DB `status='approved'`.

### 7. Public rendering
- **Action**: open the Reviews tab as OTHER2.
- **Expect**: `.listora-detail__reviews-summary` "4.0", `.listora-rating[role="img"]` `aria-label="Rated 4 out of 5"`, "1 review"; `.listora-detail__review#review-REVIEW_ID` with title, content and buttons Helpful (`aria-label="Mark as helpful"`), Block, Report. Author naming rule is asserted in `customer/reviewer-name-member-no-login-leak.md`.

### 8. Helpful vote (positive control for step 5's refusal)
- **Action**: OTHER2 clicks `.listora-detail__review-helpful-btn`.
- **Expect**: `POST /reviews/REVIEW_ID/helpful` `200 {"helpful_count":1}`; button "Helpful (1)", `is-voted`, disabled; second vote `409 listora_already_voted`; REVIEWER voting own review `403 listora_own_review`.
- **Read-back**: one `listora_review_votes` row for (OTHER2, REVIEW_ID).

### 9. Owner replies inline from the dashboard
- **Action**: as OWNER open the dashboard Reviews tab; the row shows `Rated 4 out of 5` and `.listora-dashboard__reply-trigger`; click Reply, type `Thank you for the kind words!`, Submit reply.
- **Expect**: inline `.listora-dashboard__reply-form` (not a modal); the submit button shows exactly one of "Submit reply" / "Submitting..."; `POST /reviews/REVIEW_ID/reply` `200 {"replied":true}`; after reload "Edit reply" and "Your reply: Thank you for the kind words!".
- **Read-back**: `owner_reply='Thank you for the kind words!'`, `owner_reply_at` set; logged-out Reviews tab `#review-REVIEW_ID .listora-detail__review-reply` reads "Owner Response: Thank you for the kind words!".

### 10. Edit sends the review back to moderation
- **Action**: REVIEWER `PUT /reviews/REVIEW_ID {"content":"<20+ chars>"}`.
- **Expect**: `200 {"updated":true,"status":"pending","message":"Your changes were saved. This review is awaiting approval again before it shows publicly."}`; public total drops to 0 until re-approved.
- **Read-back**: DB `status='pending'`.

### 11. Per-criterion ratings are validated and stored
- **Action**: as a member who has not reviewed a restaurant {listing}: UI form rows `input[name="criteria_ratings[<key>]"]` (Rate each aspect), then REST `POST /listings/<id>/reviews` with `criteria_ratings={"food":9,"service":4,"value":3,"bogus_key":4}`.
- **Expect**: UI row stores each key with its 1-5 value. REST: `201 {...,"criteria_ratings":{"service":4,"value":3}}`; out-of-range `food` and unknown `bogus_key` are dropped.
- **Read-back**: `SELECT criteria_ratings FROM ${PREFIX}listora_reviews WHERE id=<id>` equals `{"service":4,"value":3}`; positive control: the UI row stored all four.
- **Combo**: with Pro Multi-Criteria on the stored value is identical (Pro display: `pro:customer/05-multi-criteria-photo-review.md`). Free alone / Pro feature off: Not verified live.

### 12. Criteria display on the published review
- **Action**: approve the step 11 review; logged out open a listing whose approved review has `criteria_ratings`, then one whose review has none.
- **Expect**: `ul.listora-review-criteria` with one `li.listora-review-criteria__item` per criterion (label + stars `aria-label="Rated N out of 5"`); a review without criteria shows nothing extra; at 390 rows wrap, no horizontal scroll.
- **Read-back**: `li` count equals the keys stored. Both review templates (Reviews block and detail tab) show it (hooked on `wb_listora_review_after_content`).

### 13. Role ladder on one review
See the table below; run every row against the same `REVIEW_ID` after step 6.

### 14. Review textarea placeholder is clean text aligned with the counter (card 10351338091)
- **Action**: as REVIEWER open the Reviews tab form at 1440 and 390.
- **Expect**: `t=document.querySelector('.listora-review-form textarea')`; `t.placeholder === t.placeholder.trim()` and `!/\s{2,}|\n/.test(t.placeholder)`; the counter's left edge is within 1px of the textarea content-box and inside the form rect; the placeholder renders on one line at 1440. Wording per setting value: `customer/review-form-settings-take-effect.md`.
- **Read-back**: the raw server HTML (curl, not DOM) has the same `placeholder` attribute.
- **fails_on**: 4b95d7fc (parent of fc9728ad). Not verified live.

### 15. Scale and layout
- **Scale**: `GET /listings/LISTING_ID/reviews` is paginated (`total, pages, has_more, cursor`); seed 100+ approved reviews on a QA-prefixed {listing} and confirm one page is returned and the tab count shows the total (N=0 step 1, N=1 step 6, N>1 here). Not verified live.
- **390px**: Reviews tab, review form and dashboard Reply form: `scrollWidth <= 390`; star radios, Helpful / Block / Report and Submit are at least 40px tall.

## Role ladder
| Actor | Action | Expect |
|---|---|---|
| REVIEWER (author) | `PUT /reviews/REVIEW_ID` on approved review | `200`, status back to `pending` (step 10) |
| OTHER2 | same PUT | `403 listora_forbidden` |
| Logged out | same PUT, helpful | `401 listora_unauthorized` |
| OWNER | reply | `200 {"replied":true}` |
| OTHER2 | reply (approved review) | `403 listora_forbidden` |
| REVIEWER | helpful on own review | `403 listora_own_review` |
| {editor} / {admin} (control) | approve (step 6), reply | `200` / `200 {"replied":true}` |

## Pass criteria
1. Valid review returns 201 `pending`; short review is refused; create gates return 409/403/401.
2. Pending review is invisible; after approval it shows `Rated 4 out of 5` and Helpful returns 200.
3. Owner reply is inline, persists `owner_reply`, renders as "Owner Response".
4. Only the author edits (and an edit returns it to moderation).
5. Invalid criteria are dropped, valid ones stored and displayed.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Review created `approved` | `reviews.auto_approve` on | `includes/rest/class-reviews-controller.php:496-510` |
| Helpful/reply works on pending | gate removed | `includes/helpers.php:899`, controller `:1183,1197` |
| Reply opens a modal / both spans visible | dashboard template or CSS | `templates/blocks/user-dashboard/tab-reviews.php`, `blocks/user-dashboard/style.css` |
| `criteria_ratings` NULL / `bogus_key` stored | insert dropped column / loop lost validation | `class-reviews-controller.php:540-563`, `includes/class-template-helpers.php:2475` |
| No criteria under review | hook not fired | `includes/class-plugin.php:277`, `class-template-helpers.php:2569` |

## Not verified
- Rate limiting (`429` with `Retry-After`) for `review_create` / `review_vote` / `review_reply` (shared IP across parallel agents): `includes/class-rate-limiter.php:66`.
- Review photos upload (Pro) and the Report modal (`customer/flag-a-review-dialog-and-reasons.md`).
- Steps 11 (Free-alone), 12 at 390, 14, 15 scale seed.

## Cleanup
Delete the QA review rows, votes and the reply; restore any settings changed; `Listing_Data::recompute_rating_aggregate( LISTING_ID )`.
