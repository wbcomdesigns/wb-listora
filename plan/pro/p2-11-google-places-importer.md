# P2-11 — Google Places Importer

## Scope: Pro Only (NEW -- Competitor Gap)

---

## Overview

Import businesses directly from Google Maps/Places API into the Listora directory. Admin enters a location, radius, and category, the plugin fetches matching places from Google, previews them, and imports selected results -- automatically mapping Google fields to Listora fields (name, address, coordinates, photos, rating, hours, phone, website). Respects Google API quotas and rate limits.

### Why It Matters

- Fastest way to seed a new directory -- import 50+ real businesses in minutes instead of hours
- Google Places data is comprehensive: verified addresses, photos, hours, ratings, phone numbers
- Removes the "empty directory" problem -- new directories launch with real, verified data
- Site owners can fill geographic gaps: "import all dentists within 10km of Brooklyn"
- This is a **major competitor gap** -- no WordPress directory plugin offers native Google Places import

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Site owner | Search for "restaurants near Manhattan" and import results | I seed my restaurant directory with real businesses |
| 2 | Site owner | Preview imported businesses before committing | I can exclude irrelevant results |
| 3 | Site owner | Have Google photos auto-imported as listing gallery images | My directory looks professional from day one |
| 4 | Site owner | Map Google's "opening_hours" to Listora's business hours | Hours are automatically structured and "Open Now" filter works |
| 5 | Admin | Set API key once and use for all imports | I don't have to re-enter credentials each time |
| 6 | Developer | Import via WP-CLI for bulk seeding | I can script directory population in deployment |

---

## Technical Design

### Field Mapping

| Google Places Field | Listora Field | Notes |
|--------------------|---------------|-------|
| `name` | `post_title` | Direct mapping |
| `editorial_summary` | `post_content` | Description text |
| `formatted_address` | `_listora_address` + geo table | Full address string |
| `geometry.location.lat` | geo table `lat` | Latitude |
| `geometry.location.lng` | geo table `lng` | Longitude |
| `address_components` | geo table (city, state, country, postal_code) | Parsed components |
| `photos[0]` | Featured image (media library) | Downloaded via Places Photo API |
| `photos[1-5]` | Gallery (media library) | Downloaded via Places Photo API |
| `rating` | `avg_rating` (search_index) | Display as initial rating |
| `user_ratings_total` | `review_count` (display only) | Not imported as individual reviews |
| `opening_hours.periods` | `listora_hours` table | Structured day/time parsing |
| `formatted_phone_number` | `_listora_phone` | Phone number |
| `website` | `_listora_website` | Website URL |
| `types[]` | `listora_listing_cat` | Map to Listora categories |
| `price_level` | `_listora_price_range` | 0-4 mapped to $-$$$$ |
| `place_id` | `_listora_google_place_id` | Prevent duplicate imports |
| `url` | `_listora_google_maps_url` | Link to Google Maps page |

### API Flow

```
Step 1: Admin enters search parameters
  -> Location (text input, geocoded)
  -> Radius (km slider, 1-50)
  -> Type/Category (Google type: restaurant, dentist, gym, etc.)

Step 2: Plugin calls Google Places Nearby Search API
  -> GET https://maps.googleapis.com/maps/api/place/nearbysearch/json
  -> ?location=40.7128,-74.0060&radius=5000&type=restaurant&key=API_KEY

Step 3: For each result, call Places Details API (for full data)
  -> GET https://maps.googleapis.com/maps/api/place/details/json
  -> ?place_id=ChIJxxx&fields=name,formatted_address,geometry,...&key=API_KEY

Step 4: Admin previews results, selects which to import

Step 5: Plugin creates listings with mapped data
```

### Rate Limiting

```php
class Google_Places_Client {
    private const NEARBY_COST    = 0.032;  // $0.032 per Nearby Search request
    private const DETAILS_COST   = 0.017;  // $0.017 per Details request
    private const PHOTO_COST     = 0.007;  // $0.007 per Photo request
    private const MAX_PER_MINUTE = 50;

    private int $request_count = 0;
    private float $last_reset  = 0;

    public function search_nearby( string $location, int $radius, string $type ): array {
        $this->throttle();

        $response = wp_remote_get(
            add_query_arg([
                'location' => $location,
                'radius'   => $radius,
                'type'     => $type,
                'key'      => $this->get_api_key(),
            ], 'https://maps.googleapis.com/maps/api/place/nearbysearch/json'),
            ['timeout' => 15]
        );

        $this->request_count++;
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public function get_place_details( string $place_id ): array {
        $this->throttle();

        $fields = implode(',', [
            'name', 'editorial_summary', 'formatted_address', 'geometry',
            'photos', 'rating', 'user_ratings_total', 'opening_hours',
            'formatted_phone_number', 'website', 'types',
            'price_level', 'place_id', 'url', 'address_components',
        ]);

        $response = wp_remote_get(
            add_query_arg([
                'place_id' => $place_id,
                'fields'   => $fields,
                'key'      => $this->get_api_key(),
            ], 'https://maps.googleapis.com/maps/api/place/details/json'),
            ['timeout' => 15]
        );

        $this->request_count++;
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function throttle(): void {
        if ($this->request_count >= self::MAX_PER_MINUTE) {
            $elapsed = microtime(true) - $this->last_reset;
            if ($elapsed < 60) {
                sleep((int) ceil(60 - $elapsed));
            }
            $this->request_count = 0;
        }
        $this->last_reset = microtime(true);
    }
}
```

### Photo Import

```php
class Google_Photo_Importer {
    public function import_photos( array $photo_references, int $listing_id ): array {
        $attachment_ids = [];
        $max_photos = 10; // Limit to avoid API cost explosion

        foreach (array_slice($photo_references, 0, $max_photos) as $ref) {
            $photo_url = add_query_arg([
                'photoreference' => $ref['photo_reference'],
                'maxwidth'       => 1200,
                'key'            => $this->get_api_key(),
            ], 'https://maps.googleapis.com/maps/api/place/photo');

            $attachment_id = $this->sideload_image($photo_url, $listing_id);
            if ($attachment_id) {
                $attachment_ids[] = $attachment_id;
            }
        }

        // Set first as featured image
        if (!empty($attachment_ids)) {
            set_post_thumbnail($listing_id, $attachment_ids[0]);
        }

        // Store rest as gallery
        update_post_meta($listing_id, '_listora_gallery', array_slice($attachment_ids, 1));

        return $attachment_ids;
    }
}
```

### Business Hours Parsing

```php
class Hours_Parser {
    /**
     * Convert Google opening_hours.periods to Listora hours table format.
     *
     * Google format:
     * [{"open": {"day": 0, "time": "1100"}, "close": {"day": 0, "time": "2200"}}]
     *
     * Listora format:
     * [listing_id, day_of_week, open_time, close_time, is_closed, is_24h]
     */
    public function parse_google_hours( array $periods ): array {
        $hours = [];

        for ($day = 0; $day < 7; $day++) {
            $hours[$day] = [
                'day_of_week' => $day,
                'open_time'   => null,
                'close_time'  => null,
                'is_closed'   => 1,
                'is_24h'      => 0,
            ];
        }

        foreach ($periods as $period) {
            $day = $period['open']['day'];

            if (!isset($period['close'])) {
                $hours[$day]['is_24h']    = 1;
                $hours[$day]['is_closed'] = 0;
                continue;
            }

            $open  = substr($period['open']['time'], 0, 2) . ':' . substr($period['open']['time'], 2);
            $close = substr($period['close']['time'], 0, 2) . ':' . substr($period['close']['time'], 2);

            $hours[$day]['open_time']  = $open;
            $hours[$day]['close_time'] = $close;
            $hours[$day]['is_closed']  = 0;
        }

        return $hours;
    }
}
```

### Duplicate Prevention

```php
$existing = get_posts([
    'post_type'  => 'listora_listing',
    'meta_key'   => '_listora_google_place_id',
    'meta_value' => $place_id,
    'fields'     => 'ids',
]);

if (!empty($existing)) {
    $skipped[] = ['name' => $name, 'reason' => 'Already imported (Place ID match)'];
    continue;
}
```

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/google/class-google-places-client.php` | API client with rate limiting |
| `includes/google/class-google-photo-importer.php` | Photo download + media library upload |
| `includes/google/class-google-hours-parser.php` | Hours format conversion |
| `includes/google/class-google-field-mapper.php` | Google -> Listora field mapping |
| `includes/google/class-google-importer.php` | Orchestrator: search, preview, import |
| `includes/rest/class-google-import-controller.php` | REST endpoints |
| `includes/admin/class-google-import-page.php` | Admin import page |
| `includes/cli/class-google-import-cli.php` | WP-CLI command |

### API Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `POST` | `/listora/v1/import/google/search` | Admin | Search Google Places |
| `GET` | `/listora/v1/import/google/details/{place_id}` | Admin | Get place details |
| `POST` | `/listora/v1/import/google/import` | Admin | Import selected places |
| `GET` | `/listora/v1/import/google/status/{batch_id}` | Admin | Check import progress |

### Admin Settings

```
Settings -> Import/Export -> Google Places

Google Places API Key: [ AIzaSy...                        ]
                       [Test Connection]

Max photos per import: [ 10 ]
Default listing status: [ Pending Review ]
Import reviews as ratings: [x] (import avg_rating, not individual reviews)

Estimated API cost per import:
  Search: ~$0.03 per search (20 results)
  Details: ~$0.17 per 10 places
  Photos: ~$0.07 per 10 photos
  Total for 20 places with photos: ~$1.70
```

---

## UI Mockup

### Admin: Google Places Import (Listora > Import > Google Places)

```
+-------------------------------------------------------------+
| Import from Google Places                                   |
|                                                             |
| -- Search Parameters ----------------------------------------|
|                                                             |
| Location *                                                  |
| [ Manhattan, New York           ]  (address or coordinates) |
|                                                             |
| Radius                                                      |
| [ *========================== ]  5 km                      |
|                                                             |
| Business Type                                               |
| [ Restaurant              v ]                               |
|                                                             |
| Import as Listing Type                                      |
| [ Restaurant              v ]                               |
|                                                             |
|                                            [Search Google]  |
+-------------------------------------------------------------+
```

### Search Results

```
+-------------------------------------------------------------+
| Google Places Results                    20 places found    |
|                                                             |
| [x] Select All                                             |
|                                                             |
| +-----------------------------------------------------------+
| | [x] Pizza Palace                     4.5 stars (312)     |
| |   123 Main St, Manhattan, NY 10001                       |
| |   Phone: +1-555-0123  Web: pizzapalace.com               |
| |   Open 11 AM - 10 PM  |  6 photos available              |
| |   Status: Ready to import                                |
| +-----------------------------------------------------------+
| | [x] Sushi House                      4.2 stars (189)     |
| |   456 Oak Ave, Manhattan, NY 10002                       |
| |   Phone: +1-555-0456  Web: sushihouse.nyc                |
| |   Open 12 PM - 11 PM  |  8 photos available              |
| |   Status: Ready to import                                |
| +-----------------------------------------------------------+
| | [ ] Taco Shop                        3.1 stars (45)      |
| |   789 Elm Blvd, Manhattan, NY 10003                      |
| |   Phone: +1-555-0789                                     |
| |   Status: Ready to import                                |
| +-----------------------------------------------------------+
| | --- Burger Joint                     4.0 stars (267)     |
| |   321 Broadway, Manhattan, NY 10004                      |
| |   Status: Already imported (skipped)                     |
| +-----------------------------------------------------------+
|                                                             |
| Selected: 2 places                                          |
| Estimated API cost: ~$0.35                                  |
|                                                             |
| Import options:                                             |
| [x] Import photos (up to 10 per listing)                   |
| [x] Import business hours                                  |
| [ ] Import Google rating as listing rating                  |
| Status: [ Pending Review v ]                                |
|                                                             |
| [<- Search Again]                      [Import Selected ->] |
+-------------------------------------------------------------+
```

### Import Progress

```
+-------------------------------------------------------------+
| Importing from Google Places...                             |
|                                                             |
| ================----------  8 / 18 places                  |
|                                                             |
| Current: Fetching photos for "Sushi House"... (4/6)         |
|                                                             |
| OK  Pizza Palace -- imported with 6 photos                  |
| ... Sushi House -- importing photos...                      |
| ... Burger Barn -- queued                                   |
|                                                             |
| API calls used: 12 / ~40 estimated                          |
+-------------------------------------------------------------+
```

---

## WP-CLI Commands

```bash
# Search Google Places
wp listora import-google search \
    --location="New York" \
    --radius=5000 \
    --type=restaurant \
    --format=table

# Import from Google Places
wp listora import-google import \
    --location="New York" \
    --radius=5000 \
    --type=restaurant \
    --listing-type=restaurant \
    --status=pending \
    --photos \
    --max=50 \
    --dry-run

# Import specific place by Place ID
wp listora import-google place ChIJxxx \
    --listing-type=restaurant \
    --status=publish \
    --photos
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Google Places API client with rate limiting | 4 |
| 2 | Nearby Search integration + pagination (next_page_token) | 3 |
| 3 | Place Details fetcher (full data for selected places) | 2 |
| 4 | Field mapper (Google -> Listora fields) | 3 |
| 5 | Photo importer (download + sideload to media library) | 4 |
| 6 | Business hours parser (Google periods -> listora_hours table) | 2 |
| 7 | Address component parser (city, state, country, postal) | 1 |
| 8 | Duplicate detection (Place ID check) | 1 |
| 9 | Listing creator (create post, meta, geo, hours, search index) | 3 |
| 10 | Admin search page UI | 3 |
| 11 | Search results preview with checkboxes | 3 |
| 12 | Import progress page with live updates | 3 |
| 13 | REST endpoints for search, details, import, status | 4 |
| 14 | API key settings page + connection test | 1 |
| 15 | API cost estimator (show estimated cost before import) | 1 |
| 16 | WP-CLI commands (search, import, place) | 3 |
| 17 | Automated tests + documentation | 3 |
| **Total** | | **44 hours** |

---

## Google API Costs

| API Call | Cost | Per Import (20 places) |
|----------|------|----------------------|
| Nearby Search | $0.032 / request | $0.032 (1 request) |
| Place Details | $0.017 / request | $0.34 (20 requests) |
| Place Photo | $0.007 / request | $1.40 (20 places x 10 photos) |
| **Total** | | **~$1.77** |

With $200/month free Google Maps credit, site owners can import ~2,200 places per month at zero cost.

---

## Competitive Context

| Competitor | Google Places Import? | Our Advantage |
|-----------|----------------------|---------------|
| GeoDirectory | Paid addon ($49/year) | Included in Pro bundle |
| Directorist | Does not have this | Native integration with full field mapping |
| HivePress | No | One-click import with photo download |
| ListingPro | No | WP-CLI support for bulk scripting |
| MyListing | No | API cost estimator + rate limiting |

**Our edge:** This is a category-defining feature. The combination of automatic field mapping (hours, photos, address components), duplicate prevention (Place ID), WP-CLI support, and cost transparency (estimated API cost shown before import) makes this a powerful competitive differentiator. Site owners can launch a directory with 100+ real, verified businesses in under 10 minutes.

---

## Effort Estimate

**Total: ~44 hours (5-6 dev days)**

- Google API client: 7h
- Field mapping + parsing: 6h
- Photo import: 4h
- Listing creation: 3h
- Admin UI: 9h
- REST API: 4h
- WP-CLI: 3h
- Cost estimation: 1h
- Duplicate detection: 1h
- Tests + docs: 3h
- QA: 3h
