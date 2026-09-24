---
journey: rate-limit-counts-members-by-account
plugin: wb-listora
priority: high
roles: [subscriber, anonymous]
covers: [rate-limiter, submission, reviews, claims, favorites]
prerequisites:
  - "Site reachable at $SITE_URL"
estimated_runtime_minutes: 6
covers_card: 10332343411
---

# Members on one network are limited by their own account

Regression sentinel for the third 1.8.0 QA pass.

## Background

The per-IP counter also counted signed-in members, so everyone behind one office, campus or carrier address shared one budget: the 11th listing from an office was refused although each member had submitted four, and the same applied to reviews, claims, reports and favorites. Members are now limited by their account; the IP counter applies to guests and to actions without a per-user limit. `wb_listora_rate_limit_ip_counts_members` restores the old behaviour.

## Steps

### 1. Office
- **Action**: three members on one IP create 4 listings each.
- **Expect**: all 12 succeed.

### 2. One member
- **Expect**: the 31st listing in an hour from one account returns 429 (user scope).

### 3. Guests
- **Expect**: an anonymous action (e.g. 16 review attempts) still hits the IP limit.

### 4. Escape hatch
- **Action**: `add_filter( 'wb_listora_rate_limit_ip_counts_members', '__return_true' );`
- **Expect**: members share the IP budget again.
