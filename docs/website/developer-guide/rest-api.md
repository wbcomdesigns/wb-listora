## REST API

WB Listora exposes 41 REST API routes under the `listora/v1` namespace.

### Authentication

Public endpoints (search, listing detail) require no authentication. Write endpoints require a logged-in user with appropriate capabilities. Use WordPress nonce authentication or application passwords.

### Listings

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/listora/v1/listings` | List listings with pagination |
| GET | `/listora/v1/listings/{id}` | Get single listing |
| POST | `/listora/v1/listings` | Create listing (requires `edit_listora_listings`) |
| PUT | `/listora/v1/listings/{id}` | Update listing |
| DELETE | `/listora/v1/listings/{id}` | Delete listing |

### Search

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/listora/v1/search` | Full-text search with facets, geo, filters |

**Parameters:** `keyword`, `type`, `category`, `location`, `lat`, `lng`, `radius`, `features[]`, `min_rating`, `sort`, `page`, `per_page`

### Reviews

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/listora/v1/listings/{id}/reviews` | List reviews for a listing |
| POST | `/listora/v1/listings/{id}/reviews` | Submit a review |
| POST | `/listora/v1/reviews/{id}/helpful` | Vote review as helpful |
| POST | `/listora/v1/reviews/{id}/report` | Report a review |
| POST | `/listora/v1/reviews/{id}/reply` | Owner reply to review |

### Favorites

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/listora/v1/favorites` | Get user's favorites |
| POST | `/listora/v1/favorites/{listing_id}` | Add to favorites |
| DELETE | `/listora/v1/favorites/{listing_id}` | Remove from favorites |

### Claims

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/listora/v1/claims` | Submit a business claim |
| GET | `/listora/v1/claims` | List claims (admin) |
| PUT | `/listora/v1/claims/{id}` | Approve/reject claim (admin) |

### Submission

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/listora/v1/submit` | Frontend listing submission |

### Listing Types

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/listora/v1/listing-types` | List all types with fields |
| GET | `/listora/v1/listing-types/{slug}` | Get single type |
| POST | `/listora/v1/listing-types` | Create type (admin) |
| PUT | `/listora/v1/listing-types/{slug}` | Update type (admin) |
| DELETE | `/listora/v1/listing-types/{slug}` | Delete type (admin) |

### Dashboard

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/listora/v1/dashboard` | User dashboard stats |
| GET | `/listora/v1/dashboard/listings` | User's listings |

### Settings (Admin)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/listora/v1/settings` | Get all settings |
| POST | `/listora/v1/settings` | Update settings |

### Example: Search Request

```bash
curl "https://example.com/wp-json/listora/v1/search?keyword=pizza&type=restaurant&lat=40.71&lng=-74.00&radius=5&sort=distance"
```

### Example: Submit Review

```bash
curl -X POST "https://example.com/wp-json/listora/v1/listings/99/reviews" \
  -H "X-WP-Nonce: YOUR_NONCE" \
  -H "Content-Type: application/json" \
  -d '{"overall_rating": 5, "title": "Amazing!", "content": "Best restaurant in town."}'
```
