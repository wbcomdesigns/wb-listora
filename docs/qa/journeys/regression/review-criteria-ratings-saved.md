---
journey: review-criteria-ratings-saved
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [reviews, review-criteria, type-editor, free-pro-seam, card-10355301658]
prerequisites:
  - "A listing type with Review Criteria configured (restaurant: food, service, ambiance, value)"
  - "A published listing of that type and a member who has not reviewed it"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Per-criterion ratings a member gives are stored

`Reviews_Controller::create_review()` (`includes/rest/class-reviews-controller.php:450`, criteria block `:540-553`, insert `:563`) validates `criteria_ratings` against
`wb_listora_get_review_criteria( <type slug> )` and writes JSON with the review row; Free alone stores it, Pro's Multi-Criteria feature
reads the same validated set. Criteria per type live in the Type Editor (restaurant here: `food`, `service`, `ambiance`, `value`).

Role ladder: a member (not the owner, not already reviewed) posts; the owner cannot review their own listing; logged out is 401;
admin posts the same way (control) and sees the scores in the Reviews drawer "Ratings by criterion".

## Steps

### 1. Member rates every criterion (UI)
- **Action**: as the member, listing Reviews tab > **Write a Review** (`button.listora-reviews__write-btn`), pick stars on every
  "Rate each aspect" row (`input[name="criteria_ratings[<key>]"]`), submit.
- **Expect**: row `criteria_ratings` is JSON of each key with its 1-5 value; after approval the scores show under the review as
  `ul.listora-review-criteria` rows (`aria-label="Rated N out of 5"`).

### 2. Only valid input is stored (verified over REST as a fresh member, then deleted)
- **Action**: `POST /listora/v1/listings/{id}/reviews` with `criteria_ratings` = `{"food":9,"service":4,"value":3,"bogus_key":4}`.
- **Expect**: 201 `{"status":"pending","message":"Review submitted and pending approval.","criteria_ratings":{"service":4,"value":3}}`; DB
  `{"service":4,"value":3}`: the out-of-range `food` and the unknown `bogus_key` are dropped.

### 3. Combo
- Same as step 1 with Pro active and Multi-Criteria on (this site: on). Stored value identical.
- Free alone / Pro feature off: not run.

### 4. Restore
Delete the test reviews and user, recompute the aggregate (`Listing_Data::recompute_rating_aggregate( $listing_id )`).

## Fail diagnostics
- `criteria_ratings` NULL: the insert in `create_review()` no longer carries the column (`class-reviews-controller.php:563`).
- `bogus_key` stored: the loop at `:546` no longer checks `wb_listora_get_review_criteria()` (`includes/class-template-helpers.php:2475`).

## Not verified
Step 1 in a browser (star pickers, 390px), step 3 with Pro's feature off, and the Free-alone path.
