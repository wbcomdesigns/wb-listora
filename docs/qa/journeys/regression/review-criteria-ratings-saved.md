---
journey: review-criteria-ratings-saved
plugin: wb-listora
priority: critical
roles: [member, admin]
covers: [reviews, review-criteria, type-editor, free-pro-seam, card-10355301658]
prerequisites:
  - "A listing type with Review Criteria configured in the Type Editor (for example food, service, value)"
  - "A published listing of that type and a member who has not reviewed it"
estimated_runtime_minutes: 5
---

# Per-criterion ratings a member gives are stored

Regression sentinel for `Reviews_Controller::create_item()`: Free validates `criteria_ratings` against the type's configured criteria and writes it with the review row.

## Background

Card 10355301658. Free's Type Editor configures the criteria and Free's review form renders a star picker for each, but only Pro's Multi-Criteria Reviews feature wrote the ratings. On Free alone, or with that Pro feature off, every per-criterion rating was discarded and only the overall rating was kept.

## Steps

### 1. Free alone (or Pro's multi-criteria feature off)
As the member, write a review and rate every criterion.
- **Expect**: the review row's `criteria_ratings` is JSON with each configured key and its 1-5 value; the scores show under the review.

### 2. Only valid input is stored
`POST /listora/v1/listings/{id}/reviews` with `criteria_ratings` = `{"food":9,"service":4,"value":3,"bogus_key":4}`.
- **Expect**: stored `{"service":4,"value":3}` - out-of-range values and keys the type does not have are dropped.

### 3. Combo
Same as step 1 with Pro active and the feature on.
- **Expect**: the same stored value (Pro's listener receives the validated set).

### 4. Restore
Delete the test reviews.

## Fail diagnostics
- `criteria_ratings` NULL after step 1 -> the insert in `create_item()` no longer carries the column.
- `bogus_key` stored -> the ratings are no longer checked against `wb_listora_get_review_criteria()`.
