# Coverage: seo-schema

One row per behaviour, exactly one owner journey. Surfaces: F = frontend, A = wp-admin, R = REST, E = email, CLI. `ora:` = wb-listora, `pro:` = wb-listora-pro.

| Behaviour | Owner journey | Surfaces | Roles | 1440/390 | Light/dark | Gaps |
|---|---|---|---|---|---|---|
| Programmatic SEO pages render, route, defer, keep unrated; also: Programmatic SEO pages + sitemap provider | pro:seo-schema/seo-pages-render.md | F, A | guest, other, admin | 1440/390 | light/dark | double title and paged=99 expected-fail; Yoast run, singular/under-minimum on this site not live; past-end clamp (expected-fail); single-listing unverified combo not live |
| Breadcrumb trail equals JSON-LD BreadcrumbList | seo-schema/breadcrumb-trail-matches-json-ld.md | F, B | guest, owner, admin | both (390 n.v.) | n.v. | induced WP_Error, owner view n.v. |
| Schema generator survives malformed meta | seo-schema/listing-schema-content.md | F, C | system, guest | 1440 | light | empty PostalAddress for garbage address: expected-fail |
| JobPosting JSON-LD completeness | seo-schema/listing-schema-content.md | F | guest | 1440 | light | no published job on reference site; step 7 not live |
| Listing head: schema, OG, twitter, canonical, breadcrumbs, sitemap toggles; also: REST schema field follows the Schema toggle | seo-schema/listing-seo-head-output.md | F | guest, admin | 1440/390 | light/dark | breadcrumb at 390 and dark not screenshotted; sibling routes |
| SEO plugin ownership (Yoast, Rank Math, filter-only) | seo-schema/listing-seo-head-output.md | F | guest, admin | 1440 | light | AIOSEO/SEOPress only via filter; locale switch not run |
