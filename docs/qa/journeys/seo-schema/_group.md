---
group: seo-schema
plugin: wb-listora
scope: What a single listing page tells search engines and social cards: JSON-LD schema, Open Graph/Twitter, canonical, breadcrumbs, sitemap and SEO-plugin hand-off
journeys: 3
site_profile: required
---
# SEO and schema: what an agent checks here

## Scope
Covers everything non-visual (and the one visual breadcrumb) that Free emits for a listing: head tags, the Listing JSON-LD per type, the BreadcrumbList and its visible twin, the four Features toggles (Schema, Open Graph + Twitter, Breadcrumbs, Sitemap), the REST `schema` field, sitemap membership, and silence when an SEO plugin owns the head.

Not covered here: the visual layout of the listing page (listing-detail); category/term archive pages and the Directory page (directory-search); Pro programmatic "type in location" landing pages and their sitemap provider (Pro seo-schema); analytics (analytics-audit). Pro group: `wb-listora-pro/docs/qa/journeys/seo-schema/_group.md`.

## Surfaces
| # | Surface | URL / how to reach | Roles that see it | Rendered by (file) |
|---|---|---|---|---|
| 1 | Listing page `<head>` (JSON-LD, OG, Twitter, canonical, breadcrumb JSON-LD) | `$SITE_URL/listing/{listing}/` (view source / `curl`) | guest, {admin} (admin bar must not change head) | `includes/class-plugin.php::output_schema`, `includes/schema/class-schema-generator.php` (`output_og_tags`, `output_breadcrumbs`, `output_canonical`, `init_seo`) |
| 2 | Visible breadcrumb on the listing page | surface 1, top of the page body | guest, {owner}, {admin} | `blocks/listing-detail/render.php` (~:426) |
| 3 | Category (term) page breadcrumb | `$SITE_URL/{category_base}/{cat}/` | guest | see breadcrumb journey step 5 (term template) |
| 4 | Core XML sitemap | `$SITE_URL/wp-sitemap.xml` and `wp-sitemap-posts-listora_listing-1.xml` | guest | `includes/class-plugin.php::filter_sitemap_post_types` (~:980) |
| 5 | REST listing `schema` field | `$SITE_URL/wp-json/listora/v1/listings/{id}` | guest (published), owner (own non-public) | `includes/rest/class-listings-controller.php` (~:1105) |
| 6 | Non-public listing as owner (draft/pending) | surface 1 as {owner} | {owner}, {admin} | same templates; schema/breadcrumb must not leak |
| 7 | Settings > Features toggles | wp-admin Listora > Settings > Features | {admin} | `includes/class-features.php`, option `wb_listora_features` |
| 8 | Filtered directory URL robots tag | `$SITE_URL/{directory}/?a=1&b=2&c=3` (3+ filter params) | guest | `Schema_Generator::init_seo` (`noindex, follow`) |

## Components on each surface
1. Head, in the order emitted: `<script type="application/ld+json">` Listing object (`@type` = the listing type's Schema.org type; name, description, image, url, address `PostalAddress`, `geo` `GeoCoordinates`, telephone, `openingHoursSpecification`, `aggregateRating`, `hasOfferCatalog`/`Offer`, JobPosting shaping for jobs); OG tags: `og:type` (place), `og:title`, `og:description`, `og:url`, `og:site_name`, `og:locale` (underscore form), `og:image`, `place:location:latitude|longitude`; Twitter: `twitter:card` (summary_large_image), `twitter:title`, `twitter:description`, `twitter:image`; breadcrumb JSON-LD `BreadcrumbList` with `ListItem` position/name/item; `<link rel="canonical">` (core `rel_canonical` removed so only one exists).
2. Visible breadcrumb: `nav.listora-detail__breadcrumb[aria-label="Breadcrumb"] > ol > li`; last `li` has `aria-current="page"` and is plain text; earlier crumbs are links, or plain text when the URL is empty (broken term link). Crumbs: Directory, listing type, primary category, listing title.
3. Category page: breadcrumb crumbs per step 5 of the breadcrumb journey.
4. Sitemap: `<url><loc>` for each published listing; absent for draft/pending/trashed; whole listora_listing provider absent when Sitemap toggle is off.
5. REST: `schema` key present (object) when Schema toggle on and `null`/absent when off.
7. Features toggles: Schema.org, Open Graph + Twitter Cards, Breadcrumbs, Sitemap (XML), each a switch with description.

## States to force
| Surface | State | How |
|---|---|---|
| 1 | each toggle on/off, all off | `wp option` edit of `wb_listora_features` keys `schema`, `opengraph`, `breadcrumbs`, `sitemap` (record first) |
| 1 | SEO plugin owns head | `define('WPSEO_VERSION',..)` / `RANK_MATH_VERSION` in a QA mu-plugin; or `add_filter('wb_listora_seo_plugin_active','__return_true')` for AIOSEO/SEOPress-style |
| 1 | malformed meta (string social_links, string address, JSON-string arrays) | set `_listora_*` meta on a QA listing (schema-content journey) |
| 1 | per type: business, restaurant, job (published, not expired), event, real estate | fixtures / QA listings of each type |
| 1 | locale with hyphen (`en-GB`, `pt_BR`) | switch site language; `og:locale` must use underscores |
| 1 | no image, no address, no hours, no rating | blank those fields on a QA listing |
| 2 | broken category term link (`WP_Error` from `get_term_link`) | delete the term's rewrite or use the induced-error step |
| 2/6 | owner views own draft/pending | log in as {owner} |
| 4 | listing trashed/draft, sitemap toggle off | change status; toggle |
| 8 | 0, 2, 3, 5 filter params; paging/sort params only | edit query string |

## Settings that change this group
| Setting (option key / admin location) | Values | What must visibly change, where |
|---|---|---|
| `wb_listora_features.schema` (Settings > Features: Schema.org) | on / off | Listing JSON-LD in head; REST `schema` field |
| `wb_listora_features.opengraph` (Open Graph + Twitter Cards) | on / off | every `og:*`, `twitter:*`, `place:*` tag in head |
| `wb_listora_features.breadcrumbs` (Breadcrumbs) | on / off | visible `nav.listora-detail__breadcrumb` AND breadcrumb JSON-LD, together |
| `wb_listora_features.sitemap` (Sitemap XML) | on / off | listora_listing provider in `wp-sitemap.xml` |
| Active SEO plugin / `wb_listora_seo_plugin_active` filter | none / Yoast / Rank Math / AIOSEO / SEOPress / filter | Listora emits no schema/OG/canonical; core canonical stays; visible breadcrumb still renders |
| Site language | hyphen/underscore locales | `og:locale` underscore form |
| Directory page assignment (Settings > Pages) | page set / none | first breadcrumb `item` URL (falls back to home) |

## Fixtures and setup
- `wp --path=$WP_PATH eval-file wp-content/plugins/wb-listora/docs/qa/fixtures/2.0-fixtures.php seed` (and `clean`).
- Fetch head with `curl -s $SITE_URL/listing/{listing}/ | grep -nE 'ld\+json|og:|twitter:|canonical'`; parse JSON-LD with `jq`.
- Record `wb_listora_features` and the site language before any toggle; restore both. Remove any QA mu-plugin that defines SEO-plugin constants.
- Validate output against the Google Rich Results / Schema.org shape by field presence only (no external call required).

## Run order
1. `listing-seo-head-output.md` - api, high: baseline head, four toggles independent, og:locale, SEO plugin silence, guard order.
2. `listing-schema-content.md` - api, high: per-type JSON-LD completeness, malformed meta never fatals, JobPosting.
3. `breadcrumb-trail-matches-json-ld.md` - mixed, high: visible trail equals BreadcrumbList, broken term degrades, owner view.
Then run: `wb-listora-pro/docs/qa/journeys/seo-schema/_group.md`

## Group-specific micro checks
- G-01 Exactly one `ld+json` Listing script, one `BreadcrumbList` script, one `og:title`, one `og:url`, one `twitter:card` and one `rel="canonical"` in the head (count each; more than one is a fail, also with the admin bar).
- G-02 `og:url`, canonical href and the JSON-LD `url` are the same string for the listing (same trailing slash, scheme and host).
- G-03 `og:title` equals `twitter:title` and `og:description` equals `twitter:description`; none is empty or contains raw HTML/shortcode text.
- G-04 `og:locale` contains no hyphen (`en_GB`, not `en-GB`).
- G-05 `og:image` and `twitter:image` are absolute URLs that return 200 with an image content type; absent (not empty `content=""`) when the listing has no image.
- G-06 `place:location:latitude|longitude` equal the stored lat/lng to the same precision as `geo.latitude|longitude` in the JSON-LD.
- G-07 JSON-LD `aggregateRating.ratingValue` and `reviewCount` equal the rating and review count shown on the page and in REST `rating`; the block is absent when there are zero reviews.
- G-08 `openingHoursSpecification` is non-empty for a listing with hours in each of the three stored shapes (canonical, day-keyed single range, day-keyed `ranges`) and absent when no hours.
- G-09 BreadcrumbList positions run 1..n with no gap; the last item has a name and no `item` URL (or the listing URL), and its name equals the visible last crumb.
- G-10 Visible crumb count equals `itemListElement` length; crumb text equals `name` char for char (including `&`, quotes, emoji, non-Latin).
- G-11 With a crumb URL that cannot be built, the visible crumb is plain text (no `<a href="">`) and the JSON-LD item has no empty `item`.
- G-12 JSON-LD is valid JSON with slashes and unicode unescaped (`JSON_UNESCAPED_SLASHES|UNICODE`): a title containing `</script>` or `&` cannot break out of the script element.
- G-13 Turning Breadcrumbs off removes both the visible nav and the BreadcrumbList script; turning only Schema off leaves the visible nav and BreadcrumbList in place.
- G-14 A draft or pending listing viewed by its owner emits no `og:*`/schema pointing at a public URL as indexable and carries the core noindex handling; an anonymous request gets a 404, not a head.
- G-15 `wp-sitemap-posts-listora_listing-1.xml` entry count equals `wp post list --post_type=listora_listing --post_status=publish --format=count` (up to 2000 per page; page 2 exists beyond that).
- G-16 A filtered directory URL with 3 or more non-paging query params carries `<meta name="robots" content="noindex, follow">`; with only `listora_page`/`listora_sort` it does not.
- G-17 With a SEO plugin signalled, Listora emits none of G-01's tags, yet core `rel="canonical"` (from core or the SEO plugin) is still present exactly once.

## Known defects (expected-fail)
| Journey | Step | Defect |
|---|---|---|
| listing-schema-content.md | 3 | A garbage string `address` emits an empty `PostalAddress` (all fields `""`) instead of omitting `address` (`postal_address()` / type-property mapper) |

## Not covered yet
- Directory page, category/term archive pages and search pages: no journey checks their title, canonical, OG or robots (only the filtered-URL noindex branch exists in code, `Schema_Generator::init_seo`, with no journey).
- `og:image` fallback chain (featured image vs first gallery image vs none) and image dimensions tags.
- Twitter card tags when OG is off but Twitter is expected (they share one toggle: not asserted).
- Schema for non-listing singulars: Needs (Pro) and reviews; `Review` entities inside the Listing JSON-LD.
- Sitemap `lastmod`, paging at more than 2000 listings, and the sitemap when the Directory is Private/Coming Soon.
- Translated site (WPML/Polylang): hreflang and per-language canonical.
