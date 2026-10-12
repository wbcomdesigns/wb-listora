---
journey: reviews-and-moderation-switches-take-effect
plugin: wb-listora
priority: critical
roles: [administrator, member-owner, member-other, anonymous, editor]
covers: [per-type-moderation, per-type-reviews, type-settings-use-site-setting, listing-types-editor, review-switch-surfaces, reviews-feature-toggle, reviews-display-gating, reviews-rest-403, upgrade-keeps-behaviour, 2.0.0-per-type, bc-10393672617, card-9895809632, card-10355736648, card-10355511224]
prerequisites:
  - "Site reachable at $SITE_URL; Settings > Submissions > Moderation = 'Require admin approval' and Settings > Features > Reviews & Ratings ON (the baseline the per-type choice overrides)"
  - "Submissions behave as Free: run with wb-listora-pro deactivated, or with Pro Pricing Plans/Credits off (with plans on a member submit first needs a plan/credits: 402 `insufficient_credits` / 400 `listora_plan_required`)"
  - "Two throwaway types created in step 1; two member accounts ({owner} submits, {other} reviews); a published {listing} with >= 1 approved review on a built-in type"
estimated_runtime_minutes: 14
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default, buddyx-pro light, buddyx-pro dark, one unknown default theme]
site_profile: required
supersedes: ["ora:admin/2.0-per-type-moderation-and-reviews-take-effect.md", "ora:regression/per-type-moderation-and-reviews.md", "ora:regression/reviews-feature-disabled.md (surface steps; sub-settings moved to customer/review-form-settings-take-effect.md)"]
fails_on: "2394e3c (partial update reset, step 6); d7aa3821 (per-route reviews checks)"
---

# A listing type's Moderation and Reviews choices, and the site Reviews switch, change what happens on every surface

## Why
Each type offers Moderation (Use the site setting / Manual / Auto-approve) and Reviews (Use the site setting / On / Off); the site has a master Reviews switch. A choice that saves but only one surface obeys leaves reviews visible, or listings pending, against the owner's decision.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). Stored value `global` means "Use the site setting". Code map: `Listing_Type::get_moderation` (`includes/core/class-listing-type.php` ~320), `Listing_Type::is_review_enabled` (~298), `wb_listora_listing_moderation` (`includes/helpers.php` ~838-843), `wb_listora_listing_reviews_enabled` (~864), `wb_listora_review_gate` (~899), `Migrator::migrate_1_9_1_1()` (`includes/db/class-migrator.php:558`), editor fields `includes/admin/class-type-editor.php:443-446`. Site toggle: option `wb_listora_features['reviews']`, Settings > Features > "Reviews & Ratings".
- Scratch types: "J20 Auto" (`j20-auto`) and "J20 NoReviews" (`j20-noreviews`), created in step 1 so built-in types stay untouched.

## Steps

### 1. Make two scratch types ({admin}, 1440) and check defaults
- **Action**: `$SITE_URL/wp-admin/admin.php?page=listora-listing-types&action=new&autologin=1`; create both, Active, Frontend submission ticked, Moderation and Reviews left at "Use the site setting".
- **Expect**: both appear in the Listing Types table and in Add Listing's type step; reopening shows `#listora-type-moderation` and `#listora-type-reviews` at "Use the site setting".
- **Read-back**: `GET /listora/v1/listing-types/j20-auto` returns `moderation:"global"`, `reviews:"global"`; `GET /listing-types` publishes `reviews` and `review_enabled` for every type.

### 2. Upgrade keeps behaviour (migration)
- **Action**: from data written by the previous version (every built-in type): read each type's values; for a type that stored `auto` before the upgrade, check the migrated value.
- **Expect**: every type reads `global` for both until the owner picks one; an explicit stored `auto` from before the upgrade is kept (`Migrator::migrate_1_9_1_1()`); `wb_listora_listing_moderation()` follows the site setting.
- **Read-back**: `wp eval 'echo \WBListora\Core\Listing_Type_Registry::instance()->get("restaurant")->get_moderation();'`. Not verified live on a previous-version dump.

### 3. Moderation: the type's choice beats the site's (baseline site = manual)
- **Action**: edit `j20-auto` Moderation = "Auto-approve", **Save Type**; as {owner} submit one listing of each scratch type and of a built-in type (Add Listing wizard, or `POST /listora/v1/submit {listing_type, title, description, agree_terms:true}`).
- **Expect**: J20 Auto is `publish` immediately and the success state says it is live; J20 NoReviews and the built-in type are `pending` and say they await review (only the changed type differs: positive control).
- **Action**: switch `j20-auto` to "Manual approval" and the SITE setting to Auto-approve.
- **Expect**: `j20-auto` is `pending` again (type beats site); `j20-noreviews` (global) now publishes. Restore the site setting.
- **Also**: the guest email-verification path and admin re-verification use the same answer: a guest listing of `j20-auto` (Auto-approve) publishes after the verification link, a manual type goes to pending.
- **Read-back**: `wp post get <id> --field=post_status`; `wp eval 'echo ...get("j20-auto")->get_moderation();'` returns `auto`/`manual`; setting back to "Use the site setting" makes it follow the site again.

### 4. Reviews off for one type switches every surface off for THAT type
- **Action**: publish one listing of `j20-noreviews` and one of `j20-auto` (admin can create them in wp-admin); edit `j20-noreviews` Reviews = "Off for this type" > Save Type.
- **Expect (listing page, anonymous)**: no `#tab-reviews`, no `.listora-rating` header stars, no review summary, no "Write a review"; JSON-LD has no `aggregateRating`. **(cards)** no rating badge on that listing's card while the J20 Auto card still has one when it has reviews. **(REST)** `GET /listings/<noreviews id>/reviews` -> `403 listora_reviews_disabled` ("Reviews are currently disabled on this site."); `POST` as a member -> 403; `GET /listings/<id>?_fields=id,rating` -> `{"id":N}`; `/detail` has no `reviews_summary`. The J20 Auto tab and `GET .../reviews` still work (200).
- **Action**: `PUT /listora/v1/listing-types/j20-noreviews {"reviews":"on"}` (or the select -> "On for this type").
- **Expect**: tab and endpoints come back.
- **Read-back**: edit/vote/report/reply routes: `customer/review-actions-gated-on-every-route.md` step 4. Admin POST is refused too (the gate is on the type, not the caller).

### 5. The site switch wins over "On for this type"
- **Action (baseline ON)**: on a listing with >= 1 approved review: header `span.listora-rating[aria-label="Rated X out of 5 based on N reviews"]` (`blocks/listing-detail/render.php:502`), `#tab-reviews` + `#panel-reviews`, `aggregateRating` in JSON-LD; `GET /listings/<id>?_fields=id,rating` -> `{"id":..,"rating":{"average":X,"count":N}}`; `/detail` has `reviews_summary`; `GET /listings/<id>/reviews` 200 with rows (deleted users show "Former member").
- **Action**: Settings > Features untick "Reviews & Ratings", Save; set `j20-auto` Reviews = "On for this type".
- **Expect**: no `.listora-rating`, no Reviews tab/panel, no `listing-reviews` block output, no card rating badge, no `aggregateRating` anywhere, including `j20-auto`; REST `?_fields=id,rating` -> `{"id":..}`; `/detail` no `reviews_summary`; `GET .../reviews` and `POST` -> `403 listora_reviews_disabled` (also 403, not 401, for a guest); the select's hint says "Turning reviews off in Settings turns them off for every type".
- **Action**: re-enable.
- **Expect**: every baseline surface returns; other tabs unaffected.
- **Read-back**: `wb_listora_features['reviews']` false then true. Gated surfaces: `blocks/listing-detail/render.php:152`, `templates/blocks/listing-detail/tabs.php:703`, `blocks/listing-reviews/render.php`, `blocks/listing-card/render.php`, `includes/schema/class-schema-generator.php`, `includes/rest/class-listings-controller.php`, `class-reviews-controller.php:1074-1100`. Review sub-settings (min length, auto-approve, one per listing, replies): `customer/review-form-settings-take-effect.md`.

### 6. Partial updates do not reset the other switch
- **Action**: `PUT /listing-types/j20-auto {"label":"J20 Auto 2"}` as {admin}; then `PUT {"moderation":"manual"}`.
- **Expect**: `moderation` and `reviews` keep the values saved in steps 3-4 (not reset to `global`); the second PUT leaves `reviews` alone. Saving the built-in Restaurant type in the editor leaves it built in (`is_builtin` true) and keeps its field settings.
- **Read-back**: `GET /listing-types/j20-auto`. Code: `class-listing-types-controller.php` ~583.

### 7. 390px and dark
- **Expect**: with the feature off no empty gap where the tab was; baseline rating row and Reviews tab fit at 390. Not verified live.

### 8. Clean up
- **Action**: delete the three scratch listings and the two types (Listing Types > Delete moves any listings away first); restore the Settings.

## Role ladder
| Rung | Action | Expect |
|---|---|---|
| {admin} | editor + steps above | works |
| {editor} | `admin.php?page=listora-listing-types`; `PUT /listing-types/j20-auto` | blocked (needs `manage_listora_types`); `403` |
| {owner} | step 3 submissions | outcome follows the type |
| {other} | tries to review the no-reviews listing | `403`, form absent |
| {guest} | listing page + `GET .../reviews` | no tab; `403 listora_reviews_disabled` |

## Pass criteria
1. Default `global` on both selects; behaviour identical to the site setting until changed (including after upgrade).
2. A type's Moderation beats the site setting in both directions, only for that type.
3. Reviews Off removes tab, rating, card badge, JSON-LD, `reviews_summary` and refuses the endpoints for that type only; the site switch overrides "On" and removes every surface.
4. Updating one type setting through the API does not reset another.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Type "Auto-approve" still pending | submission path reads the site setting directly | `class-submission-controller.php`, `class-email-verification.php` |
| Reviews tab still shown | one surface not calling `wb_listora_listing_reviews_enabled` | grep tabs.php / listing-reviews render |
| `reviews` back to `global` after label edit | partial update building defaults | `class-listing-types-controller.php` ~583 |

## Not verified
The member-facing submit of a pre-upgrade `auto` type and a rendered Reviews tab/JSON-LD in a browser were verified at function and REST level only; REST sections of step 5 were run with curl (feature restored to ON); card badge and JSON-LD by code; step 7.

## Cleanup
Delete the scratch listings and types; restore Moderation to "Require admin approval" and the Reviews feature to ON.
