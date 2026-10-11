---
journey: per-type-moderation-and-reviews
plugin: wb-listora
priority: high
roles: [admin, member-owner, anonymous]
covers: [per-type-moderation, per-type-reviews, type-settings-use-global]
prerequisites:
  - "Site reachable at $SITE_URL; global Settings moderation = Manual; Reviews feature on"
estimated_runtime_minutes: 10
---

# Per-type Moderation and Reviews

Cards 10355736648 and 10355511224 (owner decision 2026-10-11): each type offers "Use the site setting" (default) or its own value.

## Steps

### 1. Upgrade keeps behaviour
- **Expect**: after updating from 1.9.0, every type reads Moderation = "Use the site setting" (the old stored 'manual' default is converted; an explicit 'auto' is kept) and Reviews = "Use the site setting".

### 2. Moderation
- **Action**: Listing Types > Classified > Moderation = Auto-approve, Save. As a member submit a Classified and a Restaurant.
- **Expect**: Classified publishes at once; Restaurant goes to pending. Same for the email-verification path.

### 3. Reviews
- **Action**: Listing Types > Job > Reviews = Off for this type, Save.
- **Expect**: a Job listing has no Reviews tab, no rating, no aggregateRating in JSON-LD, no rating badge on Job cards; GET and POST /listings/{job}/reviews return 403 `listora_reviews_disabled`. Other types unchanged. Turning Reviews off in Settings turns them off everywhere.
- **On fail**: `Listing_Type::get_moderation()` / `is_review_enabled()`, `wb_listora_listing_moderation()` / `wb_listora_listing_reviews_enabled()`, `Migrator::migrate_1_9_1_1()`.

## Pass criteria

ALL of the following hold:
1. No change on upgrade until the owner picks a per-type value.
2. A per-type value wins over the site setting on every surface (page, cards, REST, schema, submission).
