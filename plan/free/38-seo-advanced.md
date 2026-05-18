# 38 — Advanced SEO

## Scope

| | Free | Pro |
|---|---|---|
| Canonical URLs for filtered views | Yes | Yes |
| Custom meta for taxonomy pages | Yes | Yes |
| Sitemap optimization | Yes | Yes |
| Internal linking strategy | Yes | Yes |
| Noindex for thin/filtered pages | Yes | Yes |
| FAQ schema | — | Yes |
| Programmatic SEO pages | — | Yes |

---

## Canonical URLs for Filtered Views

### Problem
Search URLs like `/restaurants/?cuisine=italian&price=$$&sort=rating` create millions of URL combinations. Without canonicals, Google sees massive duplicate content.

### Solution

**Rule 1: Filtered search pages point canonical to the base page.**
```html
<!-- On /restaurants/?cuisine=italian&price=$$ -->
<link rel="canonical" href="https://site.com/restaurants/" />
```

**Rule 2: Paginated results use self-referencing canonical with page.**
```html
<!-- On /restaurants/?page=3 -->
<link rel="canonical" href="https://site.com/restaurants/?page=3" />
```

**Rule 3: Sort-only changes don't change canonical.**
```html
<!-- On /restaurants/?sort=rating -->
<link rel="canonical" href="https://site.com/restaurants/" />
```

**Rule 4: Type-specific pages ARE canonical targets.**
```
/restaurants/         → canonical: self (this IS the canonical page)
/real-estate/        → canonical: self
/listings/           → canonical: self (all types)
```

### Implementation
```php
add_action('wp_head', function() {
    if (is_listora_search_page()) {
        $canonical = get_permalink(); // base page URL, no query params
        $page = get_query_var('paged', 1);
        if ($page > 1) {
            $canonical = add_query_arg('page', $page, $canonical);
        }
        echo '<link rel="canonical" href="' . esc_url($canonical) . '" />';
    }
});
```

### Meta Robots for Filtered URLs
```php
// Add noindex to heavily filtered URLs (3+ filters)
add_action('wp_head', function() {
    if (is_listora_search_page()) {
        $filter_count = count(array_filter($_GET, fn($k) => !in_array($k, ['page', 'sort']), ARRAY_FILTER_USE_KEY));
        if ($filter_count >= 3) {
            echo '<meta name="robots" content="noindex, follow" />';
        }
    }
});
```

---

## Custom Meta for Taxonomy Pages

### Problem
Category pages (`/listing-category/italian/`) and location pages (`/listing-location/new-york/`) need unique, SEO-optimized meta descriptions — not auto-generated ones.

### Solution

**Term Meta Fields:**
```
_listora_seo_title       → Custom SEO title (overrides default)
_listora_seo_description → Custom meta description
_listora_seo_noindex     → Boolean (exclude from index)
```

### Admin UI (on category/location edit screen)
```
┌─────────────────────────────────────────────────────┐
│ SEO Settings                                        │
│                                                     │
│ SEO Title:                                          │
│ [ Best Italian Restaurants in NYC | Directory    ]  │
│ Characters: 48/60                                   │
│                                                     │
│ Meta Description:                                   │
│ [ Discover the top 23 Italian restaurants in New ]  │
│ [ York City. Read reviews, see menus, and find   ]  │
│ [ the perfect spot. Updated March 2026.          ]  │
│ Characters: 148/160                                 │
│                                                     │
│ ☐ Exclude this page from search engines            │
└─────────────────────────────────────────────────────┘
```

### Auto-Generated Defaults (if custom not set)
```php
// Category page
"Browse {count} {category_name} listings. Read reviews, compare options, and find the best {category_name} near you."

// Location page
"Discover {count} listings in {location_name}. Browse restaurants, hotels, businesses, and more in {location_name}."

// Type page
"Find the best {type_name} listings. Search {count} verified {type_name} listings with reviews, maps, and more."
```

### Output
```php
add_action('wp_head', function() {
    if (is_tax('listora_listing_cat') || is_tax('listora_listing_location')) {
        $term = get_queried_object();
        $description = get_term_meta($term->term_id, '_listora_seo_description', true);
        if (!$description) {
            $count = $term->count;
            $description = sprintf('Browse %d %s listings...', $count, $term->name);
        }
        echo '<meta name="description" content="' . esc_attr($description) . '" />';
    }
});
```

### SEO Plugin Compatibility
- If Yoast/Rank Math is active and has taxonomy meta description → defer to them
- Check: `has_action('wpseo_head')` or `has_action('rank_math/head')`
- Listora only outputs meta if no SEO plugin handles it

---

## Sitemap Optimization

### Listings Sitemap
WordPress core sitemaps auto-include `listora_listing` (public CPT). Additional optimization:

```php
add_filter('wp_sitemaps_posts_entry', function($entry, $post) {
    if ($post->post_type === 'listora_listing') {
        // Add lastmod based on last review or edit
        $last_review = get_last_review_date($post->ID);
        $last_edit = $post->post_modified_gmt;
        $entry['lastmod'] = max($last_review, $last_edit);
    }
    return $entry;
}, 10, 2);
```

### Sitemap Pagination (100K+ Listings)
WordPress core sitemaps paginate at 2,000 URLs per file (configurable):
```php
add_filter('wp_sitemaps_max_urls', function($max, $type) {
    if ($type === 'post') return 2000; // default
    if ($type === 'term') return 2000;
    return $max;
}, 10, 2);
```

For 100K listings: 50 sitemap files, linked from sitemap index. This is handled by WP core.

### Priority Hints
WordPress core sitemaps don't support `<priority>` (deprecated in sitemap spec). For search engines that still read it, use a sitemap plugin hook:
```php
// If Yoast is active, set priorities:
// Category/Location pages: 0.8
// Listing detail pages: 0.6
// Type archive pages: 0.7
```

### Additional Sitemaps
- Category sitemap: `/wp-sitemap-taxonomies-listora_listing_cat-1.xml`
- Location sitemap: `/wp-sitemap-taxonomies-listora_listing_location-1.xml`
- Both auto-generated by WP core via `public => true` taxonomies

---

## Internal Linking Strategy

### Automatic Internal Links

**1. Breadcrumbs (already planned in doc 23)**
```
Home > Restaurants > Italian > Pizza Palace
```
Each breadcrumb level is a link → distributes link equity through the hierarchy.

**2. Related Listings (already planned in doc 12)**
3-4 related listings on each detail page → cross-links between related content.

**3. Category Listing on Type Pages**
Type pages (`/restaurants/`) show category links above the grid:
```
Browse by Cuisine: Italian (23) | Chinese (15) | Japanese (8) | Mexican (12) | View All →
```
Each links to `/listing-category/italian/` → category pages get internal links from every type page.

**4. Location Links**
Type pages show location links:
```
Popular Areas: Manhattan (45) | Brooklyn (28) | Queens (16) | View All →
```

**5. Listing Footer Links**
On each listing detail page, below content:
```
More {category} in {city}: [Link] [Link] [Link]
More listings in {city}: [Link] [Link] [Link]
```

---

## Programmatic SEO Pages (Pro)

### Category + Location Combo Pages
Auto-generate pages for high-value keyword combinations:

**Pattern:** `/{type}-in-{location}/` or `/{category}-{type}-in-{location}/`

**Examples:**
- `/restaurants-in-manhattan/`
- `/italian-restaurants-in-new-york/`
- `/hotels-in-london/`
- `/real-estate-in-miami/`

**Implementation:**
- Rewrite rules detect `{type}-in-{location}` pattern
- Page content: auto-generated intro + search block filtered to type + location
- Unique meta description per page
- Schema.org `CollectionPage` markup
- Only generated for combinations with 5+ listings (avoid thin content)

### Generation Rules
```php
// Settings → SEO → Programmatic Pages
☑ Enable programmatic pages
Minimum listings per page: [ 5 ]
URL pattern: [ {type}-in-{location} ▾ ]
  Options: {type}-in-{location}
           {category}-in-{location}
           {category}-{type}-in-{location}
```

### Intro Text Templates
```
"Discover the best {category} {type} in {location}. Browse {count} verified listings
with reviews, photos, and contact information. Updated {month} {year}."
```

Configurable per type. Auto-generated but admin can override per page.

---

## Expired/Deleted Listing URL Strategy

| Scenario | HTTP Status | Behavior |
|----------|-------------|----------|
| Listing expired | 200 + noindex | Show "Expired" message with renewal CTA. Keep URL alive for renewal. |
| Listing trashed by owner | 301 redirect | Redirect to type archive page. |
| Listing permanently deleted | 410 Gone | Tell search engines URL is permanently gone. |
| Category with 0 listings | 200 + noindex | Show empty state with "No listings yet" and nearby category links. |

### Redirect Management
```php
add_action('template_redirect', function() {
    if (is_singular('listora_listing')) {
        $status = get_post_status();
        if ($status === 'trash') {
            $type = get_listing_type(get_the_ID());
            wp_redirect(get_type_page_url($type->slug), 301);
            exit;
        }
    }
});
```

---

## FAQ Schema (Pro)

### On Listing Detail Pages
If listing has a Q&A section or common questions in content:
```json
{
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "What are the opening hours?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Monday-Friday 9AM-9PM, Saturday 10AM-10PM, Sunday Closed."
      }
    },
    {
      "@type": "Question",
      "name": "Is parking available?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Yes, free parking is available on-site."
      }
    }
  ]
}
```

**Auto-generated from fields:**
- "What are the opening hours?" → from business_hours field
- "What is the price range?" → from price_range field
- "Is [feature] available?" → from features taxonomy

---

## SEO Checklist for Site Owners

Added to admin dashboard and docs:

```
SEO Health Check (Listora → Dashboard widget):

✅ Schema.org enabled — structured data on all listings
✅ Breadcrumbs enabled — navigation hierarchy for Google
✅ Sitemap active — 12,345 listings indexed
⚠  45 categories missing meta descriptions [Fix →]
⚠  12 listings have no featured image [View →]
⚠  3 listings have descriptions under 50 words [View →]
✅ Canonical URLs configured
✅ Open Graph tags active
```
