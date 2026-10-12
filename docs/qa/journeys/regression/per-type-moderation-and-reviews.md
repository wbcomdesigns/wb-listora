---
journey: per-type-moderation-and-reviews
plugin: wb-listora
priority: high
roles: [admin, member-owner, anonymous]
covers: [per-type-moderation, per-type-reviews, type-settings-use-global]
prerequisites:
  - "Site reachable at $SITE_URL; Settings moderation = Manual; Reviews feature on"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Per-type Moderation and Reviews

Cards 10355736648 and 10355511224 (owner decision 2026-10-11): each listing type offers "Use the site setting" (default `global`)
or its own value. Resolved by `Listing_Type::get_moderation()` (`includes/core/class-listing-type.php:320`) and
`Listing_Type::is_review_enabled()` (`:298`); callers use `wb_listora_listing_moderation()` (`includes/helpers.php:843`) and
`wb_listora_listing_reviews_enabled()` (`:864`). Editor fields: Listing Types > <type> > **Moderation** and **Reviews**
(`includes/admin/class-type-editor.php:443-446`, label at `:670`). REST: `PUT /listora/v1/listing-types/{slug}` accepts
`moderation` (global|manual|auto) and `reviews` (global|on|off); `GET /listing-types` publishes `reviews` and `review_enabled`.

Role ladder: admin sets it; the member-owner submits (moderation) and reviews are read by anonymous visitors; a member
with the type's reviews off cannot post. Admin is also the control (admin POST is refused too, which proves the gate is on
the type, not on the caller).

## Steps

### 1. Upgrade keeps behaviour
- **Expect**: every type reads Moderation = "Use the site setting" and Reviews = "Use the site setting" until the owner
  picks one (verified: all 10 types report `reviews: "global"`; `wb_listora_listing_moderation()` follows the site setting).
  An explicit stored `auto` from before the upgrade is kept (`Migrator::migrate_1_9_1_1()`, `includes/db/class-migrator.php:558`).

### 2. Moderation
- **Action**: Listing Types > Classified > Moderation = Auto-approve, Save. As a member submit a Classified and a Restaurant.
- **Expect**: `wb_listora_listing_moderation('classified')` = `auto`, `('restaurant')` = `manual`. Classified publishes at
  once, Restaurant goes to pending (the email-verification path agrees). Verified via REST: after the PUT classified
  resolved `auto`, restaurant stayed `manual`; restored to `global` afterwards.
- **Positive control**: set it back to "Use the site setting"; Classified goes to pending again.

### 3. Reviews off for one type
- **Action**: Listing Types > Restaurant > Reviews = Off, Save (verified through the REST PUT, restored to `global`).
- **Expect** for a Restaurant listing: no Reviews tab or rating, no `aggregateRating` in the JSON-LD (`/detail` `schema`),
  `reviews_summary` absent, no rating badge on its card, `GET /listings/{id}/reviews` and `POST /listings/{id}/reviews`
  both 403 `listora_reviews_disabled` (admin included). Edits, owner replies, helpful votes and reports on its existing reviews are
  refused too (2.0.0). Other types unchanged.
- **Positive control**: the same listing with Reviews back on "Use the site setting" serves them again.
- **Site-wide**: Reviews feature off turns them off everywhere regardless of the type value (see `reviews-feature-disabled.md`).

### 4. A per-type value wins on every surface
- **Expect**: page, cards, REST, schema and submission agree for the same listing.

## Pass criteria
1. No change on upgrade until the owner picks a per-type value.
2. A per-type value wins over the site setting on every surface.
3. Restoring "Use the site setting" restores the site behaviour.

## Fail diagnostics
`Listing_Type::get_moderation()` / `is_review_enabled()`, helpers at `includes/helpers.php:843` / `:864`, gate
`wb_listora_review_gate()` at `includes/helpers.php:899`.

## Not verified
The member-facing submit (Classified publishes at once) and the rendered Reviews tab / JSON-LD in a browser were verified at
the function and REST level only.
