# 25 — REST API

## Scope

| | Free | Pro |
|---|---|---|
| Full listings CRUD | Yes | Yes |
| Search endpoint | Yes | Yes + advanced |
| Reviews CRUD | Yes | Yes |
| Favorites CRUD | Yes | Yes |
| Claims | Yes | Yes |
| Listing types & fields | Yes | Yes |
| Submission endpoint | Yes | Yes |
| Dashboard endpoints | Yes | Yes + analytics |
| Settings (admin) | Yes | Yes |
| Webhook endpoints | — | Yes |
| Payment endpoints | — | Yes |

---

## Base URL

```
/wp-json/listora/v1/
```

---

## Authentication

| Method | Use Case |
|--------|----------|
| Cookie (nonce) | Frontend Interactivity API calls (same-origin) |
| Application Passwords | External integrations, mobile apps |
| OAuth 2.0 (future) | Third-party app integrations |

All authenticated endpoints require `X-WP-Nonce` header (cookie auth) or `Authorization: Basic` header (application passwords).

---

## Endpoints

### Listings

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/listings` | Public | List/search with filters |
| `POST` | `/listings` | Auth | Create listing |
| `GET` | `/listings/{id}` | Public | Single listing with meta |
| `PUT` | `/listings/{id}` | Author/Admin | Update listing |
| `PATCH` | `/listings/{id}` | Author/Admin | Partial update |
| `DELETE` | `/listings/{id}` | Author/Admin | Trash listing |
| `GET` | `/listings/{id}/related` | Public | Related listings |

**GET /listings query params:**
```
listing_type     string    Filter by type slug
category         int[]     Category term IDs
location         int[]     Location term IDs
features         int[]     Feature term IDs
tag              int[]     Tag term IDs
author           int       Author user ID
status           string    Post status (admin only)
search           string    Keyword search
orderby          string    date|rating|price|distance|relevance
order            string    asc|desc
per_page         int       1-100 (default: 20)
page             int       Page number
_fields          string    Sparse fieldsets
_embed           boolean   Embed linked resources
```

**GET /listings/{id} response:**
```json
{
  "id": 123,
  "title": {"rendered": "Pizza Palace"},
  "content": {"rendered": "<p>Best pizza...</p>"},
  "excerpt": {"rendered": "Best pizza in Manhattan..."},
  "slug": "pizza-palace",
  "status": "publish",
  "type": "listora_listing",
  "link": "https://site.com/listing/pizza-palace/",
  "author": 5,
  "featured_media": 456,
  "listing_type": "restaurant",
  "categories": [12, 15],
  "tags": [22, 23],
  "locations": [8, 34, 201],
  "features": [50, 51, 52],
  "meta": {
    "_listora_phone": "(212) 555-0123",
    "_listora_email": "info@pizzapalace.com",
    "_listora_website": "https://pizzapalace.com",
    "_listora_cuisine": ["Italian"],
    "_listora_price_range": "$$$",
    "_listora_address": {
      "address": "123 Main St, Manhattan, NY 10001",
      "lat": 40.7128,
      "lng": -74.006,
      "city": "Manhattan",
      "state": "NY",
      "country": "US",
      "postal_code": "10001"
    },
    "_listora_business_hours": [...],
    "_listora_gallery": [457, 458, 459],
    "_listora_social_links": [...]
  },
  "rating": {
    "average": 4.5,
    "count": 23
  },
  "is_featured": false,
  "is_verified": false,
  "is_claimed": true,
  "is_favorited": false,
  "_links": {...}
}
```

### Search

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/search` | Full search with geo + text + facets |
| `GET` | `/search/suggest` | Autocomplete suggestions |

**GET /search query params:**
```
keyword          string    FULLTEXT search
type             string    Listing type slug
category         int       Category ID
location         string    Location text (geocoded)
lat              float     Center latitude
lng              float     Center longitude
radius           int       Radius (km or mi)
radius_unit      string    "km" or "mi"
bounds[ne_lat]   float     NE latitude
bounds[ne_lng]   float     NE longitude
bounds[sw_lat]   float     SW latitude
bounds[sw_lng]   float     SW longitude
features[]       int[]     Feature IDs
open_now         boolean   Currently open filter
min_rating       float     Minimum rating
sort             string    relevance|newest|rating|distance|price_asc|price_desc
page             int       Page number
per_page         int       Results per page
facets           boolean   Include facet counts
{field_key}      mixed     Any filterable field
```

**Response:**
```json
{
  "listings": [...],
  "total": 156,
  "pages": 8,
  "facets": {
    "cuisine": {"Italian": 23, "Chinese": 15},
    "price_range": {"$": 12, "$$": 34, "$$$": 18}
  },
  "geo_center": {"lat": 40.7128, "lng": -74.006}
}
```

### Listing Types

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/listing-types` | All types with field definitions |
| `GET` | `/listing-types/{slug}` | Single type with full schema |
| `GET` | `/listing-types/{slug}/fields` | Fields for type |
| `GET` | `/listing-types/{slug}/categories` | Categories scoped to type |

### Reviews

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| `GET` | `/listings/{id}/reviews` | Public | Reviews for listing |
| `POST` | `/listings/{id}/reviews` | Auth | Submit review |
| `PUT` | `/reviews/{id}` | Author | Edit own review |
| `DELETE` | `/reviews/{id}` | Author/Admin | Delete review |
| `POST` | `/reviews/{id}/helpful` | Auth | Vote helpful |
| `POST` | `/reviews/{id}/reply` | Listing Author | Owner reply |

### Favorites

| Method | Endpoint | Auth |
|--------|----------|------|
| `GET` | `/favorites` | Auth |
| `POST` | `/favorites` | Auth |
| `DELETE` | `/favorites/{listing_id}` | Auth |

### Claims

| Method | Endpoint | Auth |
|--------|----------|------|
| `POST` | `/claims` | Auth |
| `GET` | `/claims` | Admin |
| `PUT` | `/claims/{id}` | Admin |

### Submission

| Method | Endpoint | Auth |
|--------|----------|------|
| `POST` | `/submit` | Auth |
| `PUT` | `/submit/{id}` | Author |
| `POST` | `/submit/{id}/media` | Author |

### Dashboard

| Method | Endpoint | Auth |
|--------|----------|------|
| `GET` | `/dashboard/listings` | Auth |
| `GET` | `/dashboard/reviews` | Auth |
| `GET` | `/dashboard/stats` | Auth |
| `GET` | `/dashboard/favorites` | Auth |
| `PUT` | `/dashboard/profile` | Auth |

### Settings (Admin)

| Method | Endpoint | Auth |
|--------|----------|------|
| `GET` | `/settings` | Admin |
| `PUT` | `/settings` | Admin |
| `GET` | `/settings/maps` | Public (public key only) |

### Taxonomies (Standard WP)

Also available via standard WP REST:
```
/wp-json/wp/v2/listing-types
/wp-json/wp/v2/listing-categories
/wp-json/wp/v2/listing-locations
/wp-json/wp/v2/listing-features
/wp-json/wp/v2/listing-tags
```

---

## Controller Architecture

All controllers extend `WP_REST_Controller` (or `WP_REST_Posts_Controller` for listings):

```php
namespace WBListora\REST;

class Listings_Controller extends \WP_REST_Posts_Controller {
    protected $namespace = 'listora/v1';
    protected $rest_base = 'listings';

    public function register_routes() { ... }
    public function get_items($request) { ... }
    public function get_item($request) { ... }
    public function create_item($request) { ... }
    public function update_item($request) { ... }
    public function delete_item($request) { ... }
    public function get_item_schema() { ... }
    public function get_item_permissions_check($request) { ... }
}
```

### Custom Controllers (Not Extending Posts)
- `Search_Controller` — custom search logic
- `Reviews_Controller` — custom table queries
- `Favorites_Controller` — custom table queries
- `Claims_Controller` — custom table queries
- `Dashboard_Controller` — aggregation queries

---

## Response Standards

### Pagination Headers
```
X-WP-Total: 156
X-WP-TotalPages: 8
Link: <.../listings?page=2>; rel="next", <.../listings?page=8>; rel="last"
```

### Error Format
```json
{
  "code": "listora_validation_error",
  "message": "Title is required.",
  "data": {
    "status": 400,
    "errors": {
      "title": ["Title is required."],
      "address": ["Please provide a valid address."]
    }
  }
}
```

### Rate Limiting
- Public endpoints: 100 requests/minute per IP
- Authenticated endpoints: 200 requests/minute per user
- Search suggest: 60 requests/minute per IP
- Headers: `X-RateLimit-Limit`, `X-RateLimit-Remaining`

---

## Hooks for API Extension

```php
// Modify listing response
apply_filters('wb_listora_rest_listing_response', $response, $post, $request);

// Add custom fields to schema
apply_filters('wb_listora_rest_listing_schema', $schema);

// Modify search query
apply_filters('wb_listora_rest_search_args', $args, $request);

// Add custom endpoints
do_action('wb_listora_rest_api_init');
```
