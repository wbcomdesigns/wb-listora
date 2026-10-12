---
journey: review-actions-gated-on-every-route
plugin: wb-listora
priority: critical
roles: [member-owner, member-other, anonymous, administrator, editor]
covers: [review-gate, review-edit-min-length, helpful-report-reply-on-pending, reviews-off-per-type, reviews-off-site, review-delete-still-allowed, 2.0.0-security, bc-10393575233, bc-10393672671, bc-10393672617]
prerequisites:
  - "Site reachable at $SITE_URL; Settings > Reviews: Minimum length 20, Auto-approve OFF, Enable replies ON"
  - "Fixtures seeded (docs/qa/fixtures/2.0-fixtures.php seed): a {listing} owned by {owner} with an APPROVED review by {other} (R_PUB) and a PENDING review by {author2} (R_PEND); IDs printed by the seed script"
  - "Browser session or wp.apiFetch as each rung (cookie + X-WP-Nonce); every call is /wp-json/listora/v1<route>"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [buddyx-pro light]
site_profile: required
supersedes: ["ora:customer/2.0-review-gate-across-routes.md"]
fails_on: 2394e3c (steps 1, 2); d7aa3821 (step 4)
---

# One gate for every action on an existing review

## Why
Edit, helpful vote, report and owner reply (and Pro photo upload) all go through `wb_listora_review_gate()`: the review must exist, reviews must be on for site AND listing type, and pending reviews take no votes, reports or replies. Weaker per-route checks let people act on content nobody can see. Own-review DELETE is deliberately not gated.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `R_PUB`, `R_PEND`, `L` = review/listing IDs from the seed output (`review_published`, `review_pending`). `{author2}` = author of R_PEND, a member who is neither {owner} nor the author of R_PUB; a THIRD member for step 2.
- Code map: `wb_listora_review_gate` (`includes/helpers.php` ~905); permission callbacks `class-reviews-controller.php` ~1068-1215; `validate_review_content` ~1031.

## Steps

### 1. Edit checks the same minimum as create ({author2}, author of R_PEND)
- **Action**: `PUT /reviews/R_PEND` with `content`: `short`; 19 x `é`; 20 x `é`; `J20 edited review text that is long enough to pass`.
- **Expect**: `400 listora_review_too_short` "Review must be at least 20 characters."; `400` (characters, not bytes: 19 < 20 though 38 bytes); `200 {"updated":true}`; `200`.
- **Read-back**: stored `content` unchanged after each 400. Positive control: the last call succeeds, so the 400s are the length rule and not a broken route.
- **fails_on**: 2394e3c (update skipped the minimum; card 10393672617). Not verified live.

### 2. Votes, reports and replies are refused on a pending review
- **Action**: THIRD member `POST /reviews/R_PEND/helpful` and `/report {"reason":"spam"}`; {owner} `POST /reviews/R_PEND/reply {"content":"thanks"}`; {admin} `POST /reviews/R_PEND/reply`; then `POST /reviews/R_PUB/helpful`; `POST /reviews/999999/helpful`.
- **Expect**: `403 listora_review_not_approved` "This review is not published yet." for the first four (admin included); `200 {"helpful_count":1}` for R_PUB (positive control); `404 listora_not_found` "Review not found."
- **Read-back**: `SELECT COUNT(*) FROM {prefix}listora_review_votes WHERE review_id=R_PEND` = 0; no `_listora_review_reports_R_PEND` option row. Run as a THIRD member (not author, not owner) so the refusal is the pending state and not ownership.
- **fails_on**: 2394e3c (pending reviews took votes and reports; card 10393672671). Not verified live.

### 3. A pending review is invisible to everyone but its author and moderators
- **Action**: as {guest} and {other} open the {listing} Reviews tab; then as {author2} and {admin} (Moderation > Reviews).
- **Expect**: only R_PUB listed; pending text absent for guest/other; author and admin see it with a Pending badge.
- **Read-back**: `GET /listings/L/reviews` as {guest}: R_PEND id absent.

### 4. Reviews off for the listing's TYPE closes every route
- **Action**: set the type Reviews = "Off for this type" (see `admin/reviews-and-moderation-switches-take-effect.md` for the scratch-type setup), then call as a member: `GET /listings/L/reviews`; `POST /listings/L/reviews`; `PUT /reviews/R_PEND` (valid text); `POST /reviews/R_PUB/helpful`; `/report`; `/reply` (owner); `DELETE /reviews/R_PEND` ({author2}).
- **Expect**: first six `403 listora_reviews_disabled`; DELETE `200` (members can still remove what they wrote); the listing page has no `#tab-reviews`.
- **Read-back**: restore the type to "Use the site setting" and re-run `PUT /reviews/R_PEND` (200), and a fresh member's helpful on R_PUB (200). Review surfaces other than routes: same file as above.
- **fails_on**: d7aa3821 (edit, reply, helpful and report each had a weaker check; card 10393575233). Not verified live. Strict-type half of that card: `customer/02-submit-a-listing-wizard-end-to-end.md` step 22.

### 5. Site switch
- **Action**: Settings > Features > "Reviews & Ratings" off; repeat step 4's calls on a listing of any type.
- **Expect**: same `403 listora_reviews_disabled` for every listing and type. Turn it back on.
- **Read-back**: `wb_listora_features['reviews']` false then true.

### 6. Authorisation still applies after the gate
- **Action**: calls per the role ladder below.
- **Expect**: as in the table.

### 7. Vote rules behind the gate
- **Action**: {other} (author of R_PUB) `POST /reviews/R_PUB/helpful`; then the THIRD member twice.
- **Expect**: `403 listora_own_review`; first vote `200 {"helpful_count":1}`, second `409 listora_already_voted`; none are `429` (`review_vote` is 300 per hour per account, so one member's votes never throttle another's on the same IP).
- **Read-back**: one vote row for the third member.

### 8. Replies switch
- **Action**: Settings > Reviews "Enable replies" OFF, owner replies to R_PUB; ON again.
- **Expect**: `403 listora_replies_disabled` then `200`. (Admin reply is also refused on pending, step 2.)

## Role ladder
| Rung | Call | Expect |
|---|---|---|
| anonymous | `PUT /reviews/R_PEND` | `401 listora_unauthorized` |
| {other} (not author of R_PEND) | `PUT /reviews/R_PEND` | `403 listora_forbidden` |
| {author2} (author) | `PUT /reviews/R_PEND {"status":"approved"}` | `403 listora_forbidden_status` (no self-approval) |
| {editor} (moderator) | `PUT /reviews/R_PEND {"status":"approved"}` | `200` |
| {admin} | any of the above | `200` (control) |

## Pass criteria
1. Edit enforces the same character minimum as create; a failed edit changes nothing.
2. Helpful, report and reply on a pending review: `403 listora_review_not_approved`, nothing stored; missing review `404`.
3. Reviews off (type or site) returns `403 listora_reviews_disabled` on read, create, edit, vote, report, reply, but DELETE of an own review still works.
4. 401/403/`listora_forbidden_status` still apply on top of the gate.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Edit of a 3-character review succeeds | update path skipped `validate_review_content` | `class-reviews-controller.php::update_review` |
| Vote on pending returns 200 | `review_interaction_permissions` not passing `approved_only = true` | ~1188 |
| Reply works with reviews off | `owner_reply_permissions` bypass | ~1200 |
| Delete refused when reviews off | gate added to `delete_review_permissions` | ~1180 |

## Not verified
Steps 1, 2, 4 fails_on runs; step 8; logged-out rows beyond 401.

## Cleanup
`wp eval-file $WP_PATH/wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php clean`; restore type and site Reviews settings.
