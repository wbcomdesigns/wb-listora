# P2-08 — Programmatic SEO Pages

## Scope: Pro Only

---

## Overview

Auto-generate SEO-optimized pages for every combination of listing type and location — `/restaurants-in-manhattan`, `/hotels-in-brooklyn`, `/dentists-in-san-francisco`. These pages are created programmatically from existing taxonomy data (listing types + locations), not manually by the admin. Each page renders a filtered listing grid, map, schema markup, and templated content. Pages with fewer than 3 listings are `noindex`-ed to avoid thin content penalties.

### Why It Matters

- Programmatic SEO is how large directories dominate search — Yelp, TripAdvisor, and Zillow rank for millions of "{type} in {location}" queries
- These pages rank for long-tail, high-intent search queries ("plumbers in Brooklyn", "Italian restaurants in Manhattan")
- Each page is a unique, content-rich landing page — not a thin filter page
- Zero manual content creation — pages auto-generate from existing directory data
- Internal linking between these pages builds topical authority and distributes PageRank

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Site owner | Auto-generate pages for every type+location combo | My directory ranks for thousands of long-tail keywords |
| 2 | SEO manager | Customize meta title/description templates | Each page has unique, keyword-rich meta tags |
| 3 | Site owner | Have pages with < 3 listings automatically noindexed | Google doesn't penalize me for thin content |
| 4 | Visitor | Find a page for "restaurants in Manhattan" | I see relevant listings, a map, and useful info for that specific combo |
| 5 | Search engine | Crawl programmatic pages via XML sitemap | All valuable pages are discovered and indexed |
| 6 | Site owner | Show "Browse by location" widgets on listing pages | Internal linking boosts SEO across all programmatic pages |

---

## Technical Design

### URL Pattern

Default: `/{type_slug}-in-{location_slug}`

Examples:
```
/restaurants-in-manhattan
/hotels-in-brooklyn
/dentists-in-san-francisco
/real-estate-in-miami-beach
/jobs-in-london
```

Configurable pattern via admin setting:
```
/{type}-in-{location}        (default)
/{type}/{location}            (hierarchical)
/find/{type}/{location}       (prefixed)
```

### Rewrite Rules

```php
add_action('init', function() {
    $pattern = get_option('listora_seo_url_pattern', '{type}-in-{location}');

    // Generate regex from pattern
    // e.g., /{type}-in-{location} → ^([a-z0-9-]+)-in-([a-z0-9-]+)/?$
    add_rewrite_rule(
        '^([a-z0-9-]+)-in-([a-z0-9-]+)/?$',
        'index.php?listora_seo_type=$matches[1]&listora_seo_location=$matches[2]',
        'top'
    );

    add_rewrite_tag('%listora_seo_type%', '([^&]+)');
    add_rewrite_tag('%listora_seo_location%', '([^&]+)');
});
```

### Page Generation Logic

These are not stored as posts — they are virtual pages rendered by intercepting the `template_redirect` hook when the rewrite tags match.

```php
add_action('template_redirect', function() {
    $type_slug     = get_query_var('listora_seo_type');
    $location_slug = get_query_var('listora_seo_location');

    if (!$type_slug || !$location_slug) return;

    // Validate type exists
    $type = Listing_Type_Registry::get_by_slug($type_slug);
    if (!$type) {
        global $wp_query;
        $wp_query->set_404();
        return;
    }

    // Validate location exists
    $location = get_term_by('slug', $location_slug, 'listora_listing_location');
    if (!$location) {
        global $wp_query;
        $wp_query->set_404();
        return;
    }

    // Count listings for this combo
    $count = $search_engine->count([
        'listing_type' => $type_slug,
        'location'     => $location->term_id,
        'status'       => 'publish',
    ]);

    // Render the programmatic page
    $template_data = [
        'type'           => $type,
        'location'       => $location,
        'listing_count'  => $count,
        'noindex'        => $count < 3,
    ];

    // Load template
    include plugin_dir_path(__FILE__) . 'templates/seo-page.php';
    exit;
});
```

### Page Template Content

Each programmatic page renders:

1. **Breadcrumbs:** Home > {Type Plural} > {Type Plural} in {Location}
2. **H1 Title:** "{Type Plural} in {Location}" (templated)
3. **Intro Paragraph:** Templated text with dynamic counts and location info
4. **Listing Grid:** Filtered grid showing listings of this type in this location
5. **Map:** Map with markers for all matching listings
6. **Schema Markup:** `ItemList` + `BreadcrumbList` JSON-LD
7. **Internal Links:** Related locations, related types, parent/child locations

### Meta Tags

```php
add_action('wp_head', function() {
    if (!is_listora_seo_page()) return;

    $type     = get_listora_seo_type();
    $location = get_listora_seo_location();
    $count    = get_listora_seo_count();

    // Title template (admin-configurable)
    $title_template = get_option(
        'listora_seo_title_template',
        '{type_plural} in {location} | {site_name}'
    );

    // Description template
    $desc_template = get_option(
        'listora_seo_desc_template',
        'Find the best {type_plural} in {location}. {count} listings with reviews, ratings, and maps.'
    );

    $title = strtr($title_template, [
        '{type_plural}' => $type->plural_label,
        '{type}'        => $type->label,
        '{location}'    => $location->name,
        '{site_name}'   => get_bloginfo('name'),
        '{count}'       => $count,
    ]);

    $description = strtr($desc_template, [
        '{type_plural}' => $type->plural_label,
        '{type}'        => $type->label,
        '{location}'    => $location->name,
        '{count}'       => $count,
    ]);

    echo '<title>' . esc_html($title) . '</title>';
    echo '<meta name="description" content="' . esc_attr($description) . '">';

    // Canonical
    echo '<link rel="canonical" href="' . esc_url(get_listora_seo_url($type, $location)) . '">';

    // Noindex thin pages
    if ($count < 3) {
        echo '<meta name="robots" content="noindex, follow">';
    }
});
```

### Schema Markup

```json
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": "Restaurants in Manhattan",
  "description": "Find the best restaurants in Manhattan. 47 listings with reviews, ratings, and maps.",
  "numberOfItems": 47,
  "itemListElement": [
    {
      "@type": "ListItem",
      "position": 1,
      "url": "https://site.com/listing/pizza-palace/"
    },
    {
      "@type": "ListItem",
      "position": 2,
      "url": "https://site.com/listing/sushi-house/"
    }
  ]
}
```

### XML Sitemap Integration

```php
add_filter('wp_sitemaps_posts_query_args', function($args, $post_type) {
    // Don't interfere with core sitemaps
    return $args;
}, 10, 2);

// Register custom sitemap provider for programmatic pages
add_filter('wp_sitemaps_add_provider', function($provider, $name) {
    if ($name === 'listora-seo') {
        return new Listora_SEO_Sitemap_Provider();
    }
    return $provider;
}, 10, 2);

class Listora_SEO_Sitemap_Provider extends WP_Sitemaps_Provider {
    public function get_url_list($page_num, $object_subtype = '') {
        $combos = $this->get_valid_combos($page_num); // type+location with >= 3 listings
        $urls = [];
        foreach ($combos as $combo) {
            $urls[] = [
                'loc'     => get_listora_seo_url($combo['type'], $combo['location']),
                'lastmod' => $combo['last_modified'],
            ];
        }
        return $urls;
    }
}
```

### Internal Linking

#### "Browse by Location" Widget

Displayed on listing detail pages and category pages:

```
┌─────────────────────────────────────────┐
│ Browse Restaurants by Location          │
│                                         │
│ Manhattan (47)  ·  Brooklyn (32)        │
│ Queens (18)  ·  Bronx (12)             │
│ Staten Island (5)                       │
│                                         │
│ [View all locations →]                  │
└─────────────────────────────────────────┘
```

#### "More {Type} in {Location}" Section

Displayed at the bottom of listing detail pages:

```
┌─────────────────────────────────────────┐
│ More Restaurants in Manhattan           │
│                                         │
│ ┌─────────┐ ┌─────────┐ ┌─────────┐   │
│ │ Card 1  │ │ Card 2  │ │ Card 3  │   │
│ └─────────┘ └─────────┘ └─────────┘   │
│                                         │
│ [View all 47 restaurants in Manhattan →]│
└─────────────────────────────────────────┘
```

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/seo/class-programmatic-pages.php` | Rewrite rules, virtual page rendering |
| `includes/seo/class-seo-meta.php` | Meta tags, title, description templates |
| `includes/seo/class-seo-schema.php` | ItemList + BreadcrumbList JSON-LD |
| `includes/seo/class-seo-sitemap.php` | XML sitemap provider |
| `includes/seo/class-seo-internal-links.php` | Browse by location widget, related links |
| `templates/seo-page.php` | Page template with grid + map + schema |
| `includes/admin/class-seo-settings.php` | Admin settings for URL pattern, templates |

### Files to Modify (wb-listora free)

| File | Change |
|------|--------|
| `includes/schema/class-schema-generator.php` | Add filter for Pro to inject ItemList schema |
| `blocks/listing-detail/render.php` | Add filter hook for "More in {Location}" section |

### Admin Settings

```
Settings → SEO → Programmatic Pages

Enable Programmatic Pages: ☑ (default: off)

URL Pattern: [ {type}-in-{location} ▾ ]

Title Template:
[ {type_plural} in {location} | {site_name} ]

Description Template:
[ Find the best {type_plural} in {location}. {count} listings with reviews, ratings, and maps. ]

Intro Text Template:
[ Looking for {type_plural} in {location}? Browse our directory of {count} verified {type_plural}
  with reviews, ratings, and contact information. ]

Minimum Listings (noindex below this): [ 3 ]

Include in XML Sitemap: ☑
Show "Browse by Location" widget: ☑
Show "More in {Location}" on detail pages: ☑
```

---

## UI Mockup

### Programmatic Page (Frontend)

```
┌─────────────────────────────────────────────────────────────┐
│ Home > Restaurants > Restaurants in Manhattan               │
│                                                             │
│ Restaurants in Manhattan                                    │
│ ═══════════════════════                                     │
│                                                             │
│ Looking for restaurants in Manhattan? Browse our directory   │
│ of 47 verified restaurants with reviews, ratings, and       │
│ contact information.                                        │
│                                                             │
│ ┌───────────────────────────────────────────────────────┐   │
│ │                                                       │   │
│ │                   [Map with markers]                   │   │
│ │                                                       │   │
│ └───────────────────────────────────────────────────────┘   │
│                                                             │
│ 47 restaurants found    Sort: [Rating ▾]                    │
│                                                             │
│ ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│ │ Card 1   │  │ Card 2   │  │ Card 3   │  │ Card 4   │   │
│ └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
│ ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│ │ Card 5   │  │ Card 6   │  │ Card 7   │  │ Card 8   │   │
│ └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
│                                                             │
│ ◄ 1 2 3 4 ►                                                │
│                                                             │
│ ── Browse Restaurants by Location ──────────────────────    │
│ Brooklyn (32)  ·  Queens (18)  ·  Bronx (12)               │
│ Staten Island (5)  ·  Jersey City (8)                       │
│                                                             │
│ ── Other Directories in Manhattan ─────────────────────     │
│ Hotels (23)  ·  Salons (15)  ·  Dentists (9)              │
└─────────────────────────────────────────────────────────────┘
```

### Admin: SEO Settings

```
┌─────────────────────────────────────────────────────────────┐
│ Settings → SEO → Programmatic Pages                         │
│                                                             │
│ Enable Programmatic Pages                                   │
│ ☑ Generate pages for type + location combinations          │
│                                                             │
│ URL Pattern                                                 │
│ (●) {type}-in-{location}     /restaurants-in-manhattan      │
│ ( ) {type}/{location}         /restaurants/manhattan         │
│ ( ) find/{type}/{location}    /find/restaurants/manhattan    │
│                                                             │
│ ── Templates ────────────────────────────────────────────── │
│                                                             │
│ Meta Title Template                                         │
│ [ {type_plural} in {location} | {site_name}            ]    │
│ Available: {type}, {type_plural}, {location}, {count},      │
│            {site_name}                                      │
│                                                             │
│ Meta Description Template                                   │
│ [ Find the best {type_plural} in {location}. {count}   ]   │
│ [ listings with reviews, ratings, and maps.             ]   │
│                                                             │
│ Intro Text Template                                         │
│ ┌───────────────────────────────────────────────────────┐   │
│ │ Looking for {type_plural} in {location}? Browse our  │   │
│ │ directory of {count} verified {type_plural} with     │   │
│ │ reviews, ratings, and contact information.            │   │
│ └───────────────────────────────────────────────────────┘   │
│                                                             │
│ ── Rules ────────────────────────────────────────────────── │
│                                                             │
│ Min. listings for index: [ 3 ]  (pages with fewer are      │
│                                  noindex, follow)           │
│                                                             │
│ ☑ Include in XML Sitemap                                   │
│ ☑ Show "Browse by Location" widget                         │
│ ☑ Show "More in {Location}" on detail pages                │
│                                                             │
│ ── Stats ────────────────────────────────────────────────── │
│                                                             │
│ Total possible pages: 234 (10 types x ~23 locations avg)    │
│ Indexable pages (>= 3 listings): 187                        │
│ Noindexed pages (< 3 listings): 47                          │
│                                                             │
│                                              [Save Changes] │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Custom rewrite rules + query vars for URL pattern | 3 |
| 2 | Virtual page renderer (template_redirect intercept) | 3 |
| 3 | Type + location validation (404 for invalid combos) | 1 |
| 4 | Listing count query + noindex logic | 1 |
| 5 | Meta title + description template engine | 2 |
| 6 | Canonical URL generation | 1 |
| 7 | Page template — breadcrumbs, H1, intro, grid, map | 5 |
| 8 | ItemList + BreadcrumbList JSON-LD schema | 3 |
| 9 | XML sitemap provider (enumerate valid combos) | 3 |
| 10 | "Browse by location" widget (type detail pages) | 2 |
| 11 | "More {type} in {location}" section (listing detail) | 2 |
| 12 | Admin settings page — URL pattern, templates, rules | 3 |
| 13 | Flush rewrite rules on settings save | 0.5 |
| 14 | SEO plugin compatibility (Yoast, Rank Math) — title/desc filters | 2 |
| 15 | Cache layer — cache page HTML for 1 hour | 2 |
| 16 | Automated tests + documentation | 3 |
| **Total** | | **37.5 hours** |

---

## SEO Compatibility

| SEO Plugin | Integration |
|-----------|-------------|
| Yoast SEO | Filter `wpseo_title` and `wpseo_metadesc` for programmatic pages |
| Rank Math | Filter `rank_math/frontend/title` and `rank_math/frontend/description` |
| SEOPress | Filter `seopress_titles_title` |
| The SEO Framework | Filter `the_seo_framework_title_from_generation` |
| None | Use native `wp_head` hooks (default) |

---

## Competitive Context

| Competitor | Programmatic SEO? | Our Advantage |
|-----------|-------------------|---------------|
| GeoDirectory | Location pages addon ($29) | Included in Pro, templated meta, auto-noindex |
| Directorist | No | Full programmatic SEO with sitemap integration |
| HivePress | Basic location pages | Templated content, schema markup, internal linking |
| ListingPro | Location pages (theme-tied) | Plugin-based, works with any theme |
| MyListing | Explore pages (manual) | Fully automatic, zero manual page creation |
| Yelp/TripAdvisor | Yes (inspiration) | Same pattern, WordPress-native implementation |

**Our edge:** Fully automatic page generation from existing taxonomy data — zero manual work. Template engine for meta tags means every page has unique, keyword-rich titles and descriptions. Auto-noindex for thin pages prevents Google penalties. XML sitemap integration ensures crawlability. Internal linking widgets build topical authority. The "browse by location" pattern is how Yelp and TripAdvisor dominate search, and we bring it to WordPress directories.

---

## Effort Estimate

**Total: ~37.5 hours (5 dev days)**

- Rewrite rules + virtual pages: 7h
- Meta + schema: 6h
- Page template: 5h
- Sitemap integration: 3h
- Internal linking: 4h
- Admin settings: 3h
- SEO plugin compatibility: 2h
- Caching: 2h
- Tests + docs: 3h
- QA: 2.5h
