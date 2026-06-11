---
journey: listing-extra-rest
plugin: wb-listora
priority: normal
roles: [anonymous, subscriber, administrator]
covers: [related-listings, listings-bulk, renewal-quote, services-reorder, settings-app-config]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Several published listings of the same type/category (capture LISTING_ID + 2-3 sibling IDs)"
  - "A listing with >=2 services (capture SVC_LISTING_ID)"
estimated_runtime_minutes: 5
covers_card: null
---

# Sentinel for the lesser-exercised listing REST routes

Re-locks five REST routes that had no journey: related listings, batch fetch,
renewal-quote preview, services reorder, and the frontend app-config bootstrap.

## Steps

### 1. Related listings
- **Action**: `GET /listora/v1/listings/{LISTING_ID}/related`.
- **Expect**: 200; returns same-type/category neighbours (excluding LISTING_ID itself); honors a sensible limit. Used by the detail-page "related" widget.

### 2. Batch fetch by IDs
- **Action**: `GET`/`POST /listora/v1/listings/bulk` with a set of IDs.
- **Expect**: 200; returns the requested listings in one response (distinct from `/listings/bulk-moderate`, which is the admin moderation action). Only published/visible listings returned to anonymous callers.

### 3. Renewal quote preview
- **Action**: as the owner, `GET /listora/v1/listings/{LISTING_ID}/renewal-quote`.
- **Expect**: 200; returns the cost/credits + new expiration the renew would apply — without mutating anything (the mutation is `POST /renew`, covered by 06-listing-renewal.md).

### 4. Services reorder
- **Action**: as the owner, `POST /listora/v1/listings/{SVC_LISTING_ID}/services/reorder` with a new order array.
- **Expect**: 200; the services render in the new order on the detail page; ownership-gated (non-owner → 403).

### 5. Frontend app-config bootstrap
- **Action**: `GET /listora/v1/settings/app-config`.
- **Expect**: 200; returns the public block-config bundle (map provider, feature flags, labels) the frontend blocks read at hydration — no secrets, no admin-only settings leaked.
