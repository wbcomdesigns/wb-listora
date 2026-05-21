# REST API

WB Listora exposes **55 REST endpoints** under the `listora/v1` namespace. Every customer-facing surface (frontend listing UI, submission wizard, user dashboard, search, reviews, claims, favorites) is REST-driven; AJAX is reserved for admin-only operations (per the plugin's REST-first architecture rule).

**Base URL:** `<your-site>/wp-json/listora/v1/`

**Auth model:**

- **Public** — `GET` reads (listings, search, single listing). No authentication required.
- **Auth** — requires a valid user session (cookies + nonce) OR a [WordPress Application Password](https://make.wordpress.org/core/2020/11/05/application-passwords-integration-guide/).
- **Owner** — only the listing's author (or a user with the listing's edit capability) can modify.
- **Admin** — requires `manage_options` or `manage_listora_settings`.

**Nonce header** for browser clients: `X-WP-Nonce: <wp_create_nonce("wp_rest")>`. Apps using Application Passwords don't need this.

**Response envelope (lists):**

```json
{
  "listings": [ /* array of resource objects */ ],
  "total": 247,
  "pages": 21,
  "has_more": true,
  "cursor": "WyJsaXN0aW5nIiwxMjM0XQ==",
  "next_cursor": "WyJsaXN0aW5nIiwxNDQ0XQ=="
}
```

**Error contract:**

```json
{
  "code": "listora_invalid_field",
  "message": "Field 'price' is required",
  "data": { "status": 400 }
}
```

*Generated from `audit/manifest.json`. Re-run `/wp-plugin-onboard --refresh` after non-trivial commits to regenerate.*

## Listings (12)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET` | `/listora/v1/dashboard/listings` | `logged_in_permissions` | `Dashboard_Controller::get_listings` | User's listings (cursor pagination) |
| `GET` | `/listora/v1/listings` | Public | `Listings_Controller::get_items` | List published listings (cursor pagination) |
| `POST` | `/listora/v1/listings/bulk` | Public | `Listings_Controller::get_bulk` | Fetch up to 50 listings by ID (offline cache) |
| `DELETE` | `/listora/v1/listings/{id}` | `delete_listing_permissions` | `Listings_Controller::delete_listing` | Owner soft-delete |
| `POST` | `/listora/v1/listings/{id}/deactivate` | `deactivate_listing_permissions` | `Listings_Controller::deactivate_listing` | Owner hides their listing from the directory (sets listor… |
| `GET` | `/listora/v1/listings/{id}/detail` | Public | `Listings_Controller::get_listing` | Single listing detail (card or full) |
| `POST` | `/listora/v1/listings/{id}/feature` | `feature_listing_permissions` | `Listings_Controller::feature_listing` | Upgrade listing to Featured |
| `GET` | `/listora/v1/listings/{id}/related` | Public | `Listings_Controller::get_related` | Related listings |
| `POST` | `/listora/v1/listings/{id}/renew` | `renew_listing_permissions` | `Listings_Controller::renew_listing` | Renew expired listing |
| `GET` | `/listora/v1/listings/{id}/renewal-quote` | `renew_listing_permissions` | `Listings_Controller::get_renewal_quote` | Renewal pricing/status |
| `GET, POST` | `/listora/v1/listings/{listing_id}/services` | `__return_true / create_service_permissions` | `Services_Controller::get_listing_services / create_service` | Listing services list/create |
| `POST` | `/listora/v1/listings/{listing_id}/services/reorder` | `create_service_permissions` | `Services_Controller::reorder_services` | Reorder services |

## Listings — Lifecycle (1)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `POST` | `/listora/v1/listings/{id}/reactivate` | `reactivate_listing_permissions` | `Listings_Controller::reactivate_listing` | Owner restores a deactivated listing back to its prior pu… |

## Listings — Moderation (1)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `POST` | `/listora/v1/listings/bulk-moderate` | Owner | `WBListora\REST\Listings_Controller::bulk_moderate` | Bulk moderation — approve/reject/feature/unfeature/trash … |

## Listings — Contact (1)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `POST` | `/listora/v1/listings/(?P<id>[\d]+)/contact-form` | `__return_true (anonymous allowed; nonce + honeypot + Anti_Spam pipeline gate inside handler)` | `WBListora\Contact_Form::handle_rest_submission` | Free's listing contact form. Per-IP-per-listing 3/hour ca… |

## Reviews (5)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET` | `/listora/v1/dashboard/reviews` | `logged_in_permissions` | `Dashboard_Controller::get_reviews` | User's reviews received/written |
| `PUT, DELETE` | `/listora/v1/reviews/{id}` | `update_review_permissions / delete_review_permissions` | `Reviews_Controller::update_review / delete_review` | Update/delete review |
| `POST` | `/listora/v1/reviews/{id}/helpful` | `logged_in_permissions` | `Reviews_Controller::vote_helpful` | Helpful vote |
| `POST` | `/listora/v1/reviews/{id}/reply` | `owner_reply_permissions` | `Reviews_Controller::owner_reply` | Listing owner reply |
| `POST` | `/listora/v1/reviews/{id}/report` | `logged_in_permissions` | `Reviews_Controller::report_review` | Report inappropriate review |

## Reviews (per-listing) (1)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET, POST` | `/listora/v1/listings/{listing_id}/reviews` | Auth | `Reviews_Controller::get_listing_reviews / create_review` | List reviews / submit new review |

## Search (2)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET` | `/listora/v1/search` | Public | `Search_Controller::search` | Filtered/geo/fulltext/facet search |
| `GET` | `/listora/v1/search/suggest` | Public | `Search_Controller::suggest` | Autocomplete suggestions |

## Submission (2)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `POST` | `/listora/v1/submission/resend-verification` | Public | `Submission_Controller::resend_verification_endpoint` | Resend email verification |
| `GET` | `/listora/v1/submission/verify` | Public | `Submission_Controller::verify_endpoint` | REST mirror of email verify URL |

## Claims (3)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET, POST` | `/listora/v1/claims` | `admin_permissions / logged_in_permissions` | `Claims_Controller::get_claims / submit_claim` | List all claims (admin) / submit claim |
| `PUT` | `/listora/v1/claims/{id}` | `admin_permissions` | `Claims_Controller::update_claim` | Approve/reject claim |
| `GET` | `/listora/v1/dashboard/claims` | `logged_in_permissions` | `Dashboard_Controller::get_my_claims` | User's claim requests |

## Favorites (2)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET, POST` | `/listora/v1/favorites` | `logged_in_permissions` | `Favorites_Controller::get_favorites / add_favorite` | List/add favorites |
| `DELETE` | `/listora/v1/favorites/{listing_id}` | `logged_in_permissions` | `Favorites_Controller::remove_favorite` | Remove favorite |

## Services (1)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET, PUT, DELETE` | `/listora/v1/services/{id}` | `scoped` | `Services_Controller::get_service / update_service / delete_service` | CRUD single service |

## User Dashboard (4)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET` | `/listora/v1/dashboard/notifications` | `logged_in_permissions` | `Dashboard_Controller::get_notifications` | In-app notifications |
| `PUT` | `/listora/v1/dashboard/notifications/read` | `logged_in_permissions` | `Dashboard_Controller::mark_notifications_read` | Mark notifications read |
| `GET, PUT` | `/listora/v1/dashboard/profile` | `logged_in_permissions` | `Dashboard_Controller::get_profile / update_profile` | Dashboard profile (name, bio) |
| `GET` | `/listora/v1/dashboard/stats` | `logged_in_permissions` | `Dashboard_Controller::get_stats` | User dashboard stats (60s transient) |

## Listing Types (4)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET, POST` | `/listora/v1/listing-types` | `__return_true / create_item_permissions_check` | `Listing_Types_Controller::get_items / create_item` | List/create listing types |
| `GET, PUT, DELETE` | `/listora/v1/listing-types/{slug}` | `scoped` | `Listing_Types_Controller::get_item / update_item / delete_item` | CRUD single listing type |
| `GET` | `/listora/v1/listing-types/{slug}/categories` | Public | `Listing_Types_Controller::get_categories` | Categories scoped to a listing type |
| `GET` | `/listora/v1/listing-types/{slug}/fields` | Public | `Listing_Types_Controller::get_fields` | Type fields schema |

## Settings (9)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET, PUT, DELETE` | `/listora/v1/settings` | Admin | `Settings_Controller::get_all_settings / update_settings / reset_settings` | Plugin settings CRUD |
| `GET` | `/listora/v1/settings/app-config` | Public | `Settings_Controller::get_app_config` | Public bootstrap config (app/frontend) |
| `GET` | `/listora/v1/settings/export` | Admin | `Settings_Controller::export_settings` | Export settings JSON |
| `POST` | `/listora/v1/settings/import` | Admin | `Settings_Controller::import_settings` | Import settings JSON |
| `GET` | `/listora/v1/settings/maps` | Public | `Settings_Controller::get_map_settings` | Public map config |
| `GET, DELETE` | `/listora/v1/settings/notifications/log` | Admin | `Settings_Controller::get_notification_log / clear_notification_log` | View/clear notification log |
| `GET` | `/listora/v1/settings/notifications/log/export` | Admin | `Settings_Controller::export_notification_log` | Download notification log as CSV/JSON for archiving |
| `POST` | `/listora/v1/settings/notifications/log/retention` | Admin | `Settings_Controller::set_notification_retention` | Set notification log retention policy (days) |
| `POST` | `/listora/v1/settings/notifications/test` | Admin | `Settings_Controller::send_test_notification` | Send test notification email |

## Miscellaneous (7)

| Method | Route | Auth | Handler | Purpose |
|---|---|---|---|---|
| `GET` | `/listora/v1/export/csv` | Admin | `Import_Export_Controller::export_csv` | Export listings CSV |
| `POST` | `/listora/v1/import/csv` | Admin | `Import_Export_Controller::import_csv` | Import CSV |
| `POST` | `/listora/v1/import/geojson` | Admin | `Import_Export_Controller::import_geojson` | Import GeoJSON with geo |
| `POST` | `/listora/v1/import/json` | Admin | `Import_Export_Controller::import_json` | Import JSON |
| `POST` | `/listora/v1/submit` | `submit_listing_permissions` | `Submission_Controller::submit_listing` | Frontend listing submission |
| `POST` | `/listora/v1/submit/check-duplicate` | `logged_in_permissions` | `Submission_Controller::check_duplicate_endpoint` | Pre-submit duplicate check |
| `PUT` | `/listora/v1/submit/{id}` | Owner | `Submission_Controller::edit_listing` | Owner edit listing |

---

## Authentication examples

### Cookie + nonce (logged-in browser session)

WordPress core localizes the REST nonce automatically. Read it from `wp.apiFetch` (when using `@wordpress/api-fetch`) or the page's localized `wpApiSettings.nonce`:

```js
// In a block's view.js (uses the apiFetch helper):
import apiFetch from '@wordpress/api-fetch';
const data = await apiFetch( { path: '/listora/v1/listings?per_page=12' } );

// Plain fetch with manual nonce:
const res = await fetch( '/wp-json/listora/v1/favorites', {
  method: 'POST',
  credentials: 'include',
  headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': window.wpApiSettings.nonce },
  body: JSON.stringify( { listing_id: 42 } )
} );
```

### Application Password (apps / scripts)

```bash
# Generate an Application Password under wp-admin → Users → Your Profile → Application Passwords
curl -u "username:xxxx xxxx xxxx xxxx xxxx xxxx" \
     -H "Content-Type: application/json" \
     -d '{"title":"My Listing","type":"restaurant"}' \
     https://yoursite.com/wp-json/listora/v1/submissions
```

## Filters & extensibility

Every endpoint that returns a resource also fires a `wb_listora_rest_prepare_{resource}` filter so Pro / themes / third-party code can inject custom fields without forking the controller:

```php
add_filter( 'wb_listora_rest_prepare_listing', function ( $data, $post, $request ) {
    $data['my_field'] = get_post_meta( $post->ID, '_my_field', true );
    return $data;
}, 10, 3 );
```

See [Hooks Reference → REST Response Filters](hooks-reference.md) for the full list of `wb_listora_rest_prepare_*` filters.

## Rate limits

Public-write endpoints (`POST /submissions`, `POST /listings/{id}/reviews`, `POST /claims`, `POST /listings/{id}/contact-form`) are rate-limited per IP via sliding-window counters. See [Rate Limiting & Abuse Controls](../features/rate-limiting.md) for the default caps + per-endpoint windows + how to tune.

## Related

- [Hooks Reference](hooks-reference.md) — every action + filter fired by the controllers above.
- [Custom Fields & Field Types](custom-fields.md) — how to define your own field types that REST will accept + serialize.
- [Extending with WB Listora Pro](extending-with-pro.md) — how Pro layers on top.
- [Outgoing Webhooks (Pro)](../features/outgoing-webhooks.md) — push REST events to external systems.