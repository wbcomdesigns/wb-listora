---
journey: review-moderation-full-view-and-reply-gate
plugin: wb-listora
priority: high
roles: [administrator, anonymous, owner, other-member]
covers: [reviews-admin, review-reply, multi-criteria-reviews]
prerequisites:
  - "At least one pending review and one approved review with criteria_ratings"
estimated_runtime_minutes: 6
covers_card: 10328137367
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Moderators can read the whole review, reply only to published ones, and visitors see the criteria

Listora > Reviews (`admin.php?page=listora-reviews`, filter `&status=pending|approved`) lists reviews in a table. Each row has
**Details** (`button.listora-action-link[data-listora-drawer="reviews-detail-<id>"]`) opening a native `<dialog class="listora-drawer"
id="reviews-detail-<id>">` with the whole review, criteria and reports, plus **Approve**, **Reject** and a row menu (`...`) with
**Delete**. Reply is offered only for approved reviews; `POST /listora/v1/reviews/{id}/reply` returns 403 `listora_review_not_approved`
for any other status. Free hooks `wb_listora_render_review_criteria()` on `wb_listora_review_after_content`
(`includes/class-plugin.php:277`) so both review templates show the criteria.

Role ladder: administrator/moderator uses the admin screen; the listing owner replies from the dashboard Reviews tab or the drawer;
another member and logged-out get no reply control (REST 403/401); anonymous reads the criteria. Admin is also the control for the
"approved review accepts a reply" step.

## Steps

### 1. Pending filter (verified)
- **Action**: as admin open `admin.php?page=listora-reviews&status=pending`, click **Details** on a row.
- **Expect**: the drawer shows rating (`.listora-rating[role=img][aria-label="Rated N out of 5"]`), reviewer name and e-mail, the title
  (`h3`), the full text, and under **Owner reply** the muted line "You can reply once the review is approved."; no
  `.listora-reply-form` exists for pending rows. A row with more than 18 words shows a clamped excerpt (`.listora-clamp`).

### 2. The route refuses a pending review (verified)
- **Action**: `POST /wp-json/listora/v1/reviews/<pending id>/reply` with the REST nonce and `content`.
- **Expect**: 403 `listora_review_not_approved` "This review is not published yet." (admin included). The same code is returned for
  `helpful` and `report` on a pending review.

### 3. Approved control (verified markup, reply not posted)
- **Expect**: an approved row's drawer has `.listora-reply-form[data-review-id]` with `textarea.listora-reply-textarea` and **Post reply**
  (`.listora-reply-submit`); a reply returns 200 and shows under the review on the listing. Clear the test reply afterwards.
  If Settings > Reviews "Enable replies" is off, the route returns 403 `listora_replies_disabled` and the forms are hidden.

### 4. Criteria on the frontend (verified markup in code)
- **Action**: logged out, open a listing whose approved review has `criteria_ratings`; open Reviews tab.
- **Expect**: under that review `ul.listora-review-criteria` with one `li.listora-review-criteria__item` per criterion: label + stars
  (`aria-label="Rated N out of 5"`). A review without criteria shows nothing extra. At 390 the rows wrap, no horizontal scroll.

### 5. Role ladder on the reply route
- logged out -> 401; another member (not the listing owner) -> 403; the listing owner -> 200 on an approved review; admin -> 200.

## Fail diagnostics
- Reply offered on pending: `includes/admin/class-admin.php:1944` (`'approved' !== $rev['status']` branch in `review_detail()`).
- 200 on pending: `includes/rest/class-reviews-controller.php:1197` (`owner_reply_permissions()` -> `wb_listora_review_gate( ..., true )`,
  `includes/helpers.php:899`).
- No criteria on the listing: `includes/class-plugin.php:277`, `includes/class-template-helpers.php:2569`.

## Not verified
Steps 3-5 reply POSTs, the criteria markup on the rendered listing page, the drawer opening in a browser and 390px layout.
