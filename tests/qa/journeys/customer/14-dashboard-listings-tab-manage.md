---
journey: dashboard-listings-tab-manage
plugin: wb-listora
priority: high
roles: [subscriber, contributor]
covers: [user-dashboard, listing-lifecycle, deactivate-reactivate, delete-listing, owner-feature]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A logged-in user who owns >=2 published listings (capture LISTING_A, LISTING_B)"
estimated_runtime_minutes: 6
covers_card: null
---

# Owner manages their listings from the dashboard Listings tab

Covers the owner listing-lifecycle actions reachable from the dashboard
Listings tab — previously only the renewal flow was journey-covered. Exercises
REST `POST /listings/{id}/deactivate`, `POST /listings/{id}/reactivate`,
`DELETE /listings/{id}`, and `POST /listings/{id}/feature`.

## Steps

### 1. Listings tab renders the owner's listings
- **Action**: `/dashboard/?autologin=LISTING_A_owner#listings` (or click Listings tab).
- **Expect**: `GET /listora/v1/dashboard/listings` returns the owner's listings; each row shows status + manage actions. LISTING_A + LISTING_B present.

### 2. Deactivate a published listing
- **Action**: click Deactivate on LISTING_A (confirm via `listoraConfirm` modal) → `POST /listings/{id}/deactivate`.
- **Expect**: row status flips to inactive/draft; the public detail page for LISTING_A no longer renders the "View" affordance / returns the deactivated state. `wb_listora_after_*` lifecycle hook fires.

### 3. Reactivate it
- **Action**: click Reactivate → `POST /listings/{id}/reactivate`.
- **Expect**: status returns to publish; public page live again.

### 4. Feature a listing (owner-initiated)
- **Action**: click Feature on LISTING_B → `POST /listings/{id}/feature`.
- **Expect**: with credits/permission, `_listora_is_featured` set + `featured_until` honored; Featured::is_featured() true. Without credits (Pro gating), a clear "not enough credits" response — not a silent failure.

### 5. Delete a listing
- **Action**: click Delete on LISTING_B (confirm) → `DELETE /listings/{id}`.
- **Expect**: 200; listing trashed/removed; row leaves the tab; public page 404s. Only the owner (or a cap-holder) can delete — a non-owner gets 403.

### 6. Permission gate
- **Action**: as a different non-owner user, call `POST /listings/LISTING_A/deactivate`.
- **Expect**: 403 (ownership enforced).
