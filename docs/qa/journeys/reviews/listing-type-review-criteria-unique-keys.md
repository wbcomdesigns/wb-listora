---
journey: listing-type-review-criteria-unique-keys
group: reviews
actor: admin
mode: mixed
plugin: wb-listora
priority: normal
roles: [admin, editor, owner]
covers: [review-criteria-unique-keys, listing-type-registry, card-10355318639]
prerequisites:
  - "A listing type {type} editable in Listing Types; a member {owner} who can review a listing of that type"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/directory-search-and-small-fixes.md
fails_on: unrecorded
site_profile: required
---

# Two review criteria with the same name get their own keys and their own star groups

## Why
Two criteria keyed `quality` collided, so one rating overwrote the other. `Listing_Type_Registry::unique_criteria()` (`includes/core/class-listing-type-registry.php:62`) suffixes duplicates (`quality`, `quality-2`).

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Note the type's current criteria to restore.

## Steps

1. **Save duplicates**: as {admin} edit Listing Types > {type} and save two criteria both named/keyed `quality`. **Expect**: stored as `quality` and `quality-2` (`get_review_criteria` / type option read-back via `wp eval`).
2. **Review form**: as {owner} open the review form on a {type} listing. **Expect**: each criterion has its own star group (two distinct `name` attributes), and rating one does not change the other.
3. **Round-trip**: submit a review rating the criteria 5 and 2. **Expect**: stored per-criterion values 5 and 2 (REST `GET /listings/{id}/reviews` criteria object), not 2 and 2. Not verified live.
4. **Boundary input** (added): a blank criterion name, a 200-character name and markup in the name are rejected or escaped; no fatal. Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {admin} / {editor} with the capability | can edit types (step 1) |
| {owner} (member) | sees the form (step 2) |

## Pass criteria
1. Duplicate criteria are stored with unique keys.
2. Each criterion keeps its own rating end to end.

## Fail diagnostics
| Symptom | File |
|---|---|
| Criteria collide | `includes/core/class-listing-type-registry.php:62` `unique_criteria()` |

## Not verified
Not exercised live; covered by `ReviewCriteriaKeysTest`.

## Cleanup
Restore the type's original criteria.
