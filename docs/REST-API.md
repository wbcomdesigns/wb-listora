# WB Listora REST API Reference

This reference is for **app developers** integrating with WB Listora and WB
Listora Pro over REST. Every plugin UI action is backed by an endpoint here —
if the web dashboard can do it, the app can do it too.

- **Namespace:** `listora/v1`
- **Base URL:** `https://yoursite.com/wp-json/listora/v1`
- **Free endpoints:** 39
- **Pro endpoints:** 21
- **Data format:** JSON for both requests and responses.

## Authentication

All write operations and user-scoped reads require an authenticated request.
Choose one:

| Mechanism | When to use |
|---|---|
| WordPress application passwords | Native apps, CLI tools, server-to-server |
| Nonce (`X-WP-Nonce` header) | Logged-in web contexts |
| OAuth 2.0 via JWT / Auth plugins | Third-party client apps |

Application password example:

```bash
curl -u "user:app_pass_xxxx-yyyy" \
  https://yoursite.com/wp-json/listora/v1/dashboard/stats
```

Permission callbacks always return a `WP_Error` with a status code (401 / 403),
never a bare `false` — apps can rely on HTTP status for auth failures.

## Response shape

### List endpoints

All list endpoints return a consistent envelope. Build your pagination UI
against these four fields — never against HTTP headers.

```json
{
  "items":   [ ... ],
  "total":   42,
  "pages":   3,
  "has_more": true
}
```

The array key depends on the resource: `listings`, `reviews`, `claims`,
`favorites`, `needs`, `coupons`, `services`, `results` (search). `total` is
the unfiltered collection size. `has_more` is computed as
`(offset + count(items)) < total` — reliable for infinite scroll.

### Single resource

Single-resource responses include a stable set of envelope fields:

```json
{
  "id":         123,
  "created_at": "2026-04-21 08:46:56",
  "updated_at": "2026-04-21 08:46:56",
  ...resource-specific fields
}
```

Timestamps use MySQL-style UTC format (`YYYY-MM-DD HH:MM:SS`). Use the site's
timezone from `/wp-json` to convert for display.

### Errors

All errors use the standard WP REST error envelope:

```json
{
  "code":    "listora_claim_pending",
  "message": "You already have a pending claim for this listing.",
  "data":    { "status": 409 }
}
```

HTTP status codes:

- `401` — not logged in
- `403` — logged in but lacks capability
- `404` — resource does not exist
- `409` — state conflict (e.g. already claimed)
- `422` — validation error
- `500` — unexpected server error

## Filterable responses

Every collection and resource response passes through a filter so extensions
(or your theme) can inject extra fields without forking the plugin.

```php
add_filter( 'wb_listora_rest_prepare_listing', function ( $data, $post_id, $request ) {
    $data['custom_score'] = get_post_meta( $post_id, '_my_score', true );
    return $data;
}, 10, 3 );
```

Available filters:

- `wb_listora_rest_prepare_listing`
- `wb_listora_rest_prepare_review`
- `wb_listora_rest_prepare_favorite`
- `wb_listora_rest_prepare_claim`
- `wb_listora_rest_prepare_search_result`
- `wb_listora_rest_prepare_dashboard_stats`
- `wb_listora_rest_prepare_listing_type`
- `wb_listora_rest_prepare_service`

---

## Free plugin endpoints

### Listings

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/listings` | List listings (paginated, filterable) | public |
| GET | `/listings/{id}` | Single listing | public |
| GET | `/listings/{id}/detail` | Enriched listing (for app detail view) | public |
| GET | `/listings/{id}/related` | Related listings | public |
| DELETE | `/listings/{id}` | Soft-delete owned listing | owner |
| POST | `/listings/{id}/feature` | Upgrade listing to Featured | owner |

### Search

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/search` | Full-text + facet search | public |
| GET | `/search/suggest` | Autocomplete suggestions | public |

Query params: `keyword`, `type`, `category`, `location`, `lat`, `lng`, `radius`,
`features[]`, `min_rating`, `sort`, `page`, `per_page`, `date_filter`,
`date_from`, `date_to`.

### Reviews

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/listings/{id}/reviews` | List reviews on a listing | public |
| POST | `/listings/{id}/reviews` | Post a review | logged-in |
| PUT | `/reviews/{id}` | Edit own review | owner |
| DELETE | `/reviews/{id}` | Delete own review | owner |
| POST | `/reviews/{id}/helpful` | Helpful vote | logged-in |
| POST | `/reviews/{id}/reply` | Owner reply | listing owner |
| POST | `/reviews/{id}/report` | Report review | logged-in |

### Favorites

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/favorites` | List user's favorites | logged-in |
| POST | `/favorites` | Add favorite | logged-in |
| DELETE | `/favorites/{listing_id}` | Remove favorite | logged-in |

### Claims

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/claims` | Submit a claim | logged-in |
| GET | `/claims/mine` | List current user's claims | logged-in |
| GET | `/claims` | List all claims | admin |
| PUT | `/claims/{id}` | Approve/reject | admin |

### Submission

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/submit` | Frontend listing submission | logged-in |
| PUT | `/submit/{id}` | Edit existing listing | owner |
| POST | `/submit/check-duplicate` | Pre-submit duplicate check | logged-in |

### Services

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/listings/{id}/services` | List services on a listing | public |
| POST | `/listings/{id}/services` | Add a service | owner |
| GET | `/services/{id}` | Single service | public |
| PUT | `/services/{id}` | Update service | owner |
| DELETE | `/services/{id}` | Remove service | owner |
| POST | `/listings/{id}/services/reorder` | Persist drag-order | owner |

### Dashboard

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/dashboard/stats` | Counts + trends for the current user | logged-in |
| GET | `/dashboard/listings` | User's listings | logged-in |
| GET | `/dashboard/reviews` | User's reviews (written + received) | logged-in |
| GET | `/dashboard/claims` | User's claims | logged-in |
| GET | `/dashboard/profile` | User profile payload | logged-in |
| PUT | `/dashboard/profile` | Update profile | logged-in |
| GET | `/dashboard/notifications` | User notifications | logged-in |
| PUT | `/dashboard/notifications/read` | Mark notifications as read | logged-in |

### Listing types

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/listing-types` | All available listing types | public |
| GET | `/listing-types/{slug}/fields` | Fields for a specific type | public |

### Settings (admin only)

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/settings` | Current plugin settings | admin |
| PUT | `/settings` | Update settings | admin |
| DELETE | `/settings` | Reset to defaults | admin |
| GET | `/settings/export` | Export settings JSON | admin |
| POST | `/settings/import` | Import settings JSON | admin |
| GET | `/settings/stats` | Global dashboard stats | admin |
| POST | `/settings/maps` | Verify Google Maps API key | admin |

---

## Pro plugin endpoints

### Credits & plans

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/credits/balance` | Current credit balance | logged-in |
| GET | `/credits/history` | Ledger history | logged-in |
| POST | `/credits/purchase-plan` | Activate a pricing plan | logged-in |
| POST | `/credits/admin-add` | Grant credits | admin |
| GET | `/credit-packs` | Catalog of credit packs | public |

### Webhooks

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/webhooks/payment` | Payment gateway ingress | shared secret |

### Needs

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/needs` | List open needs | public |
| POST | `/needs` | Post a need | logged-in |
| GET | `/needs/{id}` | Single need | public |
| PUT | `/needs/{id}` | Update own need | owner |
| DELETE | `/needs/{id}` | Close + delete need | owner |
| POST | `/needs/{id}/close` | Mark fulfilled | owner |
| GET | `/needs/matching/{listing_id}` | Needs matching a listing | listing owner |

### Coupons

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/coupons` | List coupons | admin |
| POST | `/coupons` | Create coupon | admin |
| GET | `/coupons/{id}` | Single coupon | admin |
| PUT | `/coupons/{id}` | Update coupon | admin |
| DELETE | `/coupons/{id}` | Delete coupon | admin |
| POST | `/coupons/validate` | Validate against a plan | logged-in |
| POST | `/coupons/generate-code` | Suggest a random code | admin |

### Badges

| Method | Path | Purpose | Auth |
|---|---|---|---|
| GET | `/badges` | List badges | public |
| POST | `/badges` | Create | admin |

### Imports (admin)

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/import/google` | Import from Google Places | admin |
| POST | `/import/visual` | Visual mapper import | admin |

### Lead form

| Method | Path | Purpose | Auth |
|---|---|---|---|
| POST | `/listings/{id}/contact` | Submit contact lead | public (nonce) |

---

## Example flows

### Submit a claim, then poll status

```bash
# 1. Submit
curl -X POST https://site/wp-json/listora/v1/claims \
  -H "X-WP-Nonce: $NONCE" \
  -d 'listing_id=42&proof_text=I own this business'

# -> 201 { "id": 7, "status": "pending", "message": "..." }

# 2. Poll user's claims
curl https://site/wp-json/listora/v1/claims/mine \
  -H "X-WP-Nonce: $NONCE"

# -> { "claims": [...], "total": 1, "pages": 1, "has_more": false }
```

### Search with infinite scroll

```bash
curl 'https://site/wp-json/listora/v1/search?keyword=pizza&per_page=20&page=1'
# -> { "results": [...], "total": 87, "pages": 5, "has_more": true }

curl 'https://site/wp-json/listora/v1/search?keyword=pizza&per_page=20&page=2'
# -> { "results": [...], "total": 87, "pages": 5, "has_more": true }
```

## Versioning

The `v1` namespace is stable. Breaking changes go into `v2`. Non-breaking
additions (new fields, new optional params) happen in-place and are announced
in `CHANGELOG.md`.

## Feedback

If you hit an inconsistency, file an issue. The REST surface is the contract
the app and third-party integrations depend on — we treat it as a public API.
