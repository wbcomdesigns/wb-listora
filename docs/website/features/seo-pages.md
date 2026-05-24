# Programmatic SEO Pages

> **Pro feature** — requires [WB Listora Pro](../getting-started/activating-pro.md).
Auto-generate hundreds of long-tail SEO landing pages like `/restaurants-in-mumbai/` or `/hotels-in-london/` from your existing listings — fully indexed by Google, with proper meta tags, Schema.org markup, and a sitemap entry. Each page is a real WordPress URL backed by a filtered listing grid, not a JavaScript-only view.

![SEO Pages — auto-generated city/type landing page on the modernized 1.0.5 UI](../images/seo-pages-landing.png)

## What it is

Most directory sites lose the entire "long-tail search" segment — people who Google *"plumbers in Manchester"* land on a competitor's dedicated city page, not a generic search-result URL. Programmatic SEO Pages closes that gap.

When enabled, WB Listora Pro:

- **Registers rewrite rules** that turn URL patterns like `/{type}-in-{location}/` (configurable) into real WordPress routes that resolve server-side.
- **Renders a filtered listing grid** at each URL, scoped to the type + location combination — so `/restaurants-in-mumbai/` shows exactly the Mumbai restaurants from your directory.
- **Emits meta tags, page titles, descriptions, Open Graph + Twitter Card markup** matched to the page's combination — every page is uniquely titled and crawlable.
- **Outputs JSON-LD structured data** (`ItemList` + `BreadcrumbList`) so Google understands the page as a directory listing, not a generic article.
- **Registers a sitemap provider** so Google discovers every generated page automatically — works with WP core sitemaps + Yoast SEO + Rank Math.
- **Integrates with Yoast / Rank Math title + description filters** so existing SEO workflows keep working.

Why this matters: directory plugins that don't ship this leave thousands of long-tail searches on the table. With it on, the same 500 listings produce hundreds of indexed landing pages.

## How you use it

### As a site owner — enable + configure

1. **Enable the feature:** WordPress admin → **Listora → Settings → Features** → toggle **Programmatic SEO Pages** to on. (It is on by default.)
2. **Configure the URL pattern:** Settings → **SEO Pages** tab. Choose the URL shape — common patterns:
   - `/{type}-in-{location}/` → `/hotels-in-london/`
   - `/{location}/{type}/` → `/london/hotels/`
   - `/find/{type}/{location}/` → `/find/hotels/london/`
3. **Flush rewrite rules:** WordPress admin → **Settings → Permalinks** → click Save. (One-time, required after any URL-pattern change.)
4. **Set page-title + meta-description templates** with the placeholders `{type}`, `{location}`, `{count}`, `{site_name}`. Example:
   - Title: `Best {type} in {location} ({count} listed) — {site_name}`
   - Description: `Browse {count} {type} in {location}. Read reviews, get directions, contact owners directly.`
5. **Save settings.** Every type × location combination is now a live URL.

### As a marketer — verify discoverability

- Visit one of the generated URLs in an incognito window — confirm the page renders the right listings + title.
- View page source — confirm the `<title>`, `<meta name="description">`, Open Graph tags, and JSON-LD `ItemList` are present.
- Check `https://yoursite.com/wp-sitemap.xml` (or your SEO plugin's sitemap) — generated pages appear automatically.
- Submit the sitemap to Google Search Console; pages typically begin getting impressions within 1–2 weeks.

## Settings & options

| Setting | Location | Default | Notes |
|---|---|---|---|
| Feature toggle | Settings → Features → Programmatic SEO Pages | On | Required for the rest to take effect. |
| URL pattern | Settings → SEO Pages → URL Pattern | `/{type}-in-{location}/` | Permalinks must be flushed after changing. |
| Title template | Settings → SEO Pages → Title Template | (placeholder) | Supports `{type}`, `{location}`, `{count}`, `{site_name}`. |
| Description template | Settings → SEO Pages → Description Template | (placeholder) | Same placeholders. |
| Sitemap provider | (auto) | On | Registers with WP core, Yoast, Rank Math automatically. |

Hook filters available for developers:

- `wb_listora_pro_seo_pages_title` — override the `<title>` per page.
- `wb_listora_pro_seo_pages_description` — override the meta description.
- `wb_listora_pro_seo_pages_schema` — modify the JSON-LD payload.

## Related

- [Search & Filters](search-and-filters.md) — the same query engine powers SEO Pages results.
- [Listing Types](../getting-started/listing-types.md) — the `{type}` placeholder uses your registered listing types.
- [Developer Reference: Hooks](../developer-guide/hooks-reference.md) — for SEO-page filter hook signatures.
