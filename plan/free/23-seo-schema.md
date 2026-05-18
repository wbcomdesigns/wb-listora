# 23 — SEO & Schema.org

## Scope

| | Free | Pro |
|---|---|---|
| Schema.org for all 10 types | Yes | Yes |
| JSON-LD output | Yes | Yes |
| Breadcrumbs (JSON-LD) | Yes | Yes |
| Open Graph meta tags | Yes | Yes |
| Twitter Card meta tags | Yes | Yes |
| WordPress sitemap integration | Yes | Yes |
| Aggregate ratings in schema | Yes | Yes |
| SEO plugin compatibility | Yes | Yes |

---

## Schema.org Implementation

### Architecture

`Schema_Generator` factory returns the correct schema class based on listing type's `_listora_schema_type`:

```php
class Schema_Generator {
    public static function for_listing(int $post_id): Schema_Type {
        $type = get_listing_type($post_id);
        $schema_type = $type->get_schema_type(); // "Restaurant"

        return match($schema_type) {
            'Restaurant'        => new Schema_Restaurant($post_id),
            'Hotel'             => new Schema_Hotel($post_id),
            'RealEstateListing' => new Schema_Real_Estate($post_id),
            'Event'             => new Schema_Event($post_id),
            'JobPosting'        => new Schema_Job($post_id),
            'Physician'         => new Schema_Physician($post_id),
            'Course'            => new Schema_Course($post_id),
            'TouristAttraction' => new Schema_Place($post_id),
            'Product'           => new Schema_Product($post_id),
            default             => new Schema_Local_Business($post_id),
        };
    }
}
```

### Output
Rendered in `wp_head` via `wp_head` action:
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Restaurant",
  "name": "Pizza Palace",
  "image": "https://site.com/wp-content/uploads/pizza-palace.jpg",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "123 Main Street",
    "addressLocality": "Manhattan",
    "addressRegion": "NY",
    "postalCode": "10001",
    "addressCountry": "US"
  },
  "geo": {
    "@type": "GeoCoordinates",
    "latitude": "40.7128",
    "longitude": "-74.0060"
  },
  "telephone": "+12125550123",
  "url": "https://pizzapalace.com",
  "servesCuisine": "Italian",
  "priceRange": "$$$",
  "openingHoursSpecification": [
    {
      "@type": "OpeningHoursSpecification",
      "dayOfWeek": "Monday",
      "opens": "09:00",
      "closes": "21:00"
    }
  ],
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.5",
    "bestRating": "5",
    "ratingCount": "23"
  },
  "review": [{
    "@type": "Review",
    "author": {"@type": "Person", "name": "John D."},
    "datePublished": "2026-03-10",
    "reviewRating": {"@type": "Rating", "ratingValue": "5"},
    "reviewBody": "Best pizza in Manhattan!"
  }]
}
</script>
```

### Per-Type Schema Properties

| Type | Schema.org Type | Key Properties |
|------|----------------|----------------|
| Business | LocalBusiness | name, address, telephone, openingHours, priceRange, aggregateRating, sameAs |
| Restaurant | Restaurant | + servesCuisine, menu, acceptsReservations |
| Hotel | Hotel | + starRating, checkinTime, checkoutTime, amenityFeature, priceRange |
| Real Estate | RealEstateListing | price, numberOfRooms, floorSize, yearBuilt, address |
| Event | Event | startDate, endDate, location, performer, offers |
| Job | JobPosting | hiringOrganization, baseSalary, employmentType, datePosted, validThrough |
| Doctor | Physician | medicalSpecialty, availableService, hospitalAffiliation |
| Course | Course | provider, educationalLevel, courseCode, hasCourseInstance |
| Place | TouristAttraction | geo, openingHours, isAccessibleForFree, publicAccess |
| Classified | Product | price, availability, itemCondition, brand |

---

## Breadcrumbs

### JSON-LD Output
```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {"@type": "ListItem", "position": 1, "name": "Home", "item": "https://site.com"},
    {"@type": "ListItem", "position": 2, "name": "Restaurants", "item": "https://site.com/restaurants/"},
    {"@type": "ListItem", "position": 3, "name": "Italian", "item": "https://site.com/listing-category/italian/"},
    {"@type": "ListItem", "position": 4, "name": "Pizza Palace"}
  ]
}
```

### Visual Breadcrumbs (Optional Block)
```
Home > Restaurants > Italian > Pizza Palace
```

Rendered with semantic HTML:
```html
<nav aria-label="Breadcrumb" class="listora-breadcrumb">
  <ol>
    <li><a href="/">Home</a></li>
    <li><a href="/restaurants/">Restaurants</a></li>
    <li><a href="/listing-category/italian/">Italian</a></li>
    <li aria-current="page">Pizza Palace</li>
  </ol>
</nav>
```

---

## Open Graph & Twitter Cards

Added to `wp_head` for listing pages:

```html
<!-- Open Graph -->
<meta property="og:type" content="place">
<meta property="og:title" content="Pizza Palace — Italian Restaurant">
<meta property="og:description" content="Best pizza in Manhattan since 1985. Rated 4.5/5.">
<meta property="og:image" content="https://site.com/.../pizza-palace.jpg">
<meta property="og:url" content="https://site.com/listing/pizza-palace/">
<meta property="og:site_name" content="NYC Directory">
<meta property="place:location:latitude" content="40.7128">
<meta property="place:location:longitude" content="-74.0060">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Pizza Palace — Italian Restaurant">
<meta name="twitter:description" content="Best pizza in Manhattan since 1985.">
<meta name="twitter:image" content="https://site.com/.../pizza-palace.jpg">
```

---

## WordPress Sitemap Integration

Hook into WordPress core sitemap (WP 5.5+):

```php
// Add listings to sitemap
add_filter('wp_sitemaps_post_types', function($post_types) {
    // listora_listing is already public, auto-included
    return $post_types;
});

// Add listing-specific sitemap metadata
add_filter('wp_sitemaps_posts_entry', function($entry, $post) {
    if ($post->post_type === 'listora_listing') {
        // Add images
        $gallery = get_post_meta($post->ID, '_listora_gallery', true);
        if ($gallery) {
            $entry['image:image'] = array_map(fn($id) => wp_get_attachment_url($id), $gallery);
        }
    }
    return $entry;
}, 10, 2);
```

Taxonomies (categories, locations) also included in sitemap automatically via `public => true`.

---

## SEO Plugin Compatibility

### Yoast SEO / Rank Math
- Don't output duplicate schema if SEO plugin is active
- Check: `defined('WPSEO_VERSION')` or `defined('RANK_MATH_VERSION')`
- Option to disable Listora's schema when SEO plugin handles it
- Listora's schema is more specific (Restaurant vs generic Article) — offer to keep Listora's

### Fallback
If no SEO plugin: Listora handles all schema, OG tags, breadcrumbs.
If SEO plugin active: Listora adds listing-specific schema only (not breadcrumbs/OG if plugin handles those).

### Filter Hook
```php
apply_filters('wb_listora_schema_data', $schema, $post_id);
apply_filters('wb_listora_enable_schema', true, $post_id);
apply_filters('wb_listora_enable_og_tags', true, $post_id);
```

---

## Category/Location SEO Pages

Taxonomy archive pages also get schema:

**Category page** (`/listing-category/italian/`):
```json
{
  "@type": "CollectionPage",
  "name": "Italian Restaurants",
  "description": "Browse Italian restaurants near you",
  "numberOfItems": 23,
  "mainEntity": {
    "@type": "ItemList",
    "itemListElement": [/* listing summaries */]
  }
}
```

**Location page** (`/listing-location/new-york/`):
```json
{
  "@type": "CollectionPage",
  "name": "Listings in New York",
  "about": {
    "@type": "City",
    "name": "New York",
    "geo": {"@type": "GeoCoordinates", "latitude": "40.7128", "longitude": "-74.006"}
  }
}
```
