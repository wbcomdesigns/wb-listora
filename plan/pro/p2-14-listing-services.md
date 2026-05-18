# P2-14 — Listing Services (Free Basic + Pro Rich)

## Overview

Add a dedicated "Services" system to listings. Each listing can offer multiple services, each with its own title, description, price, duration, image, and category. This creates a second search dimension — visitors can search by SERVICE across all listings, not just by listing.

**No competitor has this built-in.** GeoDirectory, Directorist, BDP all lack structured services. This is a massive differentiator.

## User Stories

- **Dental clinic owner:** "I want to list all my procedures (cleaning, root canal, braces) with individual prices and photos so patients know what I offer."
- **Salon owner:** "Each of my services (haircut, coloring, manicure) has different prices and durations. I want to showcase them beautifully."
- **Law firm:** "We practice in 5 areas (personal injury, family law, criminal defense). Each needs its own description and team photo."
- **Visitor:** "Show me all teeth whitening services near me, regardless of which clinic offers them."
- **Directory admin:** "I want to categorize services (Hair, Nails, Skin) so visitors can browse by service type."

## Feature Split

### FREE (Basic Services)

| Feature | Description |
|---------|-------------|
| Services table | `listora_services` custom table |
| Add/edit services | Dashboard form: title, description, price, duration, 1 image |
| Service categories | `listora_service_cat` taxonomy |
| Detail page tab | "Services (N)" tab on listing detail with card grid |
| REST API | CRUD endpoints for services |
| Schema.org | Service markup on listing detail |

### PRO Extends

| Feature | Description |
|---------|-------------|
| Video per service | YouTube/Vimeo embed URL per service |
| Service gallery | Multiple images per service (slider) |
| Service-level reviews | Rate individual services, not just the listing |
| "Book this service" hook | `do_action('wb_listora_book_service', $service_id)` |
| Service comparison | Compare services across listings |
| Cross-listing search | "Find all [service] near [location]" search mode |
| Service packages | Bundle services into packages with bundle pricing |

## Technical Design

### Database Table

```sql
CREATE TABLE {prefix}listora_services (
    id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    listing_id      bigint(20) unsigned NOT NULL,
    title           varchar(500) NOT NULL DEFAULT '',
    description     text NOT NULL,
    price           decimal(15,2) DEFAULT NULL,
    price_type      varchar(20) NOT NULL DEFAULT 'fixed',  -- fixed, starting_from, hourly, free, contact
    duration_minutes int(11) DEFAULT NULL,
    image_id        bigint(20) unsigned DEFAULT NULL,
    video_url       varchar(500) NOT NULL DEFAULT '',
    gallery         text DEFAULT NULL,  -- JSON array of attachment IDs (Pro)
    sort_order      int(11) NOT NULL DEFAULT 0,
    status          varchar(20) NOT NULL DEFAULT 'active',
    created_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_listing (listing_id),
    KEY idx_status (listing_id, status),
    KEY idx_sort (listing_id, sort_order)
) {charset_collate};
```

### Taxonomy

```php
register_taxonomy( 'listora_service_cat', null, array(
    'labels'            => array( 'name' => 'Service Categories' ),
    'hierarchical'      => true,
    'show_in_rest'      => true,
    'show_admin_column' => false,
) );
```

Service-to-category relationship stored in a pivot table or term_relationships using the service ID.

### REST API Endpoints

```
GET    /listora/v1/listings/{id}/services          -- list services for a listing
POST   /listora/v1/listings/{id}/services          -- add service (owner or admin)
PUT    /listora/v1/services/{id}                   -- update service
DELETE /listora/v1/services/{id}                   -- delete service
GET    /listora/v1/services/search?category=hair&location=manhattan  -- cross-listing search (Pro)
```

### UI — Listing Detail Tab

```
┌─────────────────────────────────────────────────────┐
│ Overview │ Services (8) │ Reviews │ Map             │
├─────────────────────────────────────────────────────┤
│                                                      │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐          │
│  │ [photo]  │  │ [photo]  │  │ [photo]  │          │
│  │ Teeth    │  │ Root     │  │ Braces   │          │
│  │ Cleaning │  │ Canal    │  │          │          │
│  │ $100     │  │ $800     │  │ $3,000   │          │
│  │ 30 min   │  │ 2 hr     │  │ Ongoing  │          │
│  │ [Details]│  │ [Details]│  │ [Details]│          │
│  └──────────┘  └──────────┘  └──────────┘          │
│                                                      │
│  ┌──────────┐  ┌──────────┐  ...                    │
│  │ [photo]  │  │ [photo]  │                          │
│  │ Whitening│  │ Implants │                          │
│  │ $200     │  │ $2,500   │                          │
│  │ 1 hr     │  │ 3 hr     │                          │
│  │ [Details]│  │ [Details]│                          │
│  └──────────┘  └──────────┘                          │
│                                                      │
└─────────────────────────────────────────────────────┘
```

### UI — Dashboard "Manage Services"

```
┌─────────────────────────────────────────────────────┐
│ My Services for "Downtown Dental Clinic"            │
│                                         [+ Add New] │
├─────────────────────────────────────────────────────┤
│ ↑↓ │ [img] │ Teeth Cleaning │ $100  │ 30min │ ✏ 🗑 │
│ ↑↓ │ [img] │ Root Canal     │ $800  │ 2hr   │ ✏ 🗑 │
│ ↑↓ │ [img] │ Braces         │$3,000 │ 12mo  │ ✏ 🗑 │
│ ↑↓ │ [img] │ Whitening      │ $200  │ 1hr   │ ✏ 🗑 │
└─────────────────────────────────────────────────────┘
```

### UI — Add/Edit Service Form

```
┌─────────────────────────────────────────┐
│ Service Name *     [Teeth Cleaning    ] │
│ Description        [Rich text area    ] │
│ Price              [$] [100.00       ] │
│ Price Type         [Fixed ▼          ] │
│ Duration           [30] minutes       │
│ Category           [Dental Cleaning ▼] │
│ Image              [Select Image]      │
│ Video URL (Pro)    [YouTube/Vimeo URL] │
│                                         │
│            [Cancel]  [Save Service]     │
└─────────────────────────────────────────┘
```

### Schema.org Markup

```json
{
  "@type": "Service",
  "name": "Teeth Cleaning",
  "description": "Professional dental cleaning...",
  "provider": { "@type": "Dentist", "name": "Downtown Dental" },
  "offers": {
    "@type": "Offer",
    "price": "100.00",
    "priceCurrency": "USD"
  },
  "image": "https://..."
}
```

### Files to Create/Modify

**FREE plugin:**
- `includes/core/class-services.php` — Services CRUD class
- `includes/rest/class-services-controller.php` — REST endpoints
- Migration: add `listora_services` table to `class-activator.php`
- `blocks/listing-detail/render.php` — add Services tab
- `blocks/user-dashboard/render.php` — add service management
- `src/blocks/listing-detail/view.js` — service card expand/collapse
- Register `listora_service_cat` taxonomy

**PRO plugin:**
- Video embed renderer
- Service gallery slider
- Cross-listing service search
- Service comparison
- Booking hook integration
- Service-level reviews

### Search Indexing — The Make-or-Break Feature

Without proper search/filter on services, the whole feature is wasted. A directory with services but no way to find "teeth whitening near me" is just a fancy list.

#### FREE: Service-Aware Listing Search

Services text is indexed INTO the existing `listora_search_index` for the parent listing:

```
search_index.meta_text = listing fields + service titles + service descriptions
```

This means searching "teeth whitening" finds the dental clinic listing because its services contain that text. The existing FULLTEXT search engine handles this — no new search infrastructure needed.

**Implementation:**
- `Search_Indexer::index_listing()` — append all service titles + descriptions to `meta_text` column
- When a service is added/edited/deleted, re-index the parent listing
- Service categories added to faceted search: "Filter by service type: Hair, Nails, Dental"

#### FREE: Service Filters on Search Block

Add a "Service Type" filter dropdown to the search block (populated from `listora_service_cat` taxonomy). When selected, only listings that offer services in that category appear.

```sql
-- Find listings that have services in category "dental-cleaning"
SELECT DISTINCT s.listing_id
FROM listora_services s
INNER JOIN wp_term_relationships tr ON s.id = tr.object_id
INNER JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
WHERE tt.taxonomy = 'listora_service_cat'
AND tt.term_id = %d
AND s.status = 'active'
```

#### PRO: Dedicated Service Search Engine

A completely separate search mode — "Search Services" instead of "Search Listings":

| Feature | Free | Pro |
|---------|------|-----|
| Search finds listings by service keywords | Yes | Yes |
| Filter listings by service category | Yes | Yes |
| **Search returns individual services** (not listings) | — | Yes |
| **Service price range filter** ($50-$200) | — | Yes |
| **Service comparison across listings** | — | Yes |
| **"Cheapest [service] near me" sort** | — | Yes |
| **Service-level rating & reviews** | — | Yes |
| **Dedicated service search REST endpoint** | — | Yes |

Pro adds: `GET /listora/v1/services/search?q=teeth+whitening&location=manhattan&price_min=50&price_max=200&sort=price_asc`

This returns individual services (not listings), each with their parent listing info. Visitors can compare the same service across multiple providers.

### Implementation Steps

1. Create `listora_services` table in activator
2. Create `Services` CRUD class
3. Register `listora_service_cat` taxonomy
4. Create REST controller with CRUD endpoints
5. **Update Search_Indexer to include service text in listing index**
6. **Add service category to faceted search filters**
7. **Add "Service Type" filter to search block render.php**
8. Add "Services" tab to listing detail render.php
9. Add service card grid with expand/collapse
10. Add "Manage Services" to user dashboard
8. Add service form (add/edit) in dashboard
9. Add Schema.org Service markup
10. Add to search indexer (service titles searchable)

### Competitive Context

| Competitor | Services Support |
|------------|-----------------|
| GeoDirectory | No built-in services. Uses custom fields as workaround. |
| Directorist | No services. Has "pricing plans" but for listing packages, not business services. |
| BDP | No services. |
| ListingPro | Has "Price Table" — name + price only. No description, no photo, no category. |
| HivePress | No services. |
| **Listora** | **Full services system: title, description, price, duration, image, video, categories, schema, cross-listing search** |

**This is the single biggest feature gap in the WordPress directory space.** Every business directory needs services, and no one has built it properly.

### Effort Estimate

| Phase | Hours |
|-------|-------|
| FREE: Table + CRUD + REST | 3hr |
| FREE: Search indexing + service category filter | 3hr |
| FREE: Detail tab + dashboard UI | 4hr |
| FREE: Taxonomy + schema | 2hr |
| PRO: Video + gallery + booking hook | 3hr |
| PRO: Dedicated service search engine + comparison | 5hr |
| PRO: Service-level reviews + price range filter | 3hr |
| **Total** | **23hr** |
