---
journey: breadcrumb-trail-matches-json-ld
group: seo-schema
actor: customer
mode: mixed
plugin: wb-listora
priority: high
roles: [guest, owner, admin]
covers: [listing-detail-block, breadcrumbs, schema-breadcrumblist, json-ld-parity, page-registry-root, guarded-term-link, M12]
prerequisites:
  - "Breadcrumbs feature enabled (default) AND no SEO plugin owning schema (with Yoast / Rank Math / AIOSEO / SEOPress active Listora skips its BreadcrumbList by design, step 6)"
  - "A published listing {listing} with a listing type AND a primary category; the Directory page mapped in the page registry"
estimated_runtime_minutes: 7
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/breadcrumb-trail-parity.md
fails_on: unrecorded
site_profile: required
---

# The visible breadcrumb trail matches the JSON-LD BreadcrumbList exactly

## Why
The visible breadcrumb (`blocks/listing-detail/render.php`) and the JSON-LD BreadcrumbList (`Schema_Generator::output_breadcrumbs()`) were built from divergent sources (root label, root URL, type-URL resolution), so Google saw a trail that did not match the page. `Schema_Generator::get_breadcrumb_items( $post_id )` (`includes/schema/class-schema-generator.php:621`) is now the single source: `render.php:288` for the visible trail and `output_breadcrumbs()` (`:679-693`) for the JSON-LD.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Trail shape: **Directory root -> listing type -> primary category -> listing title (URL-less leaf)**. Root resolves via `wb_listora_get_page_url('directory')` (falls back to `home_url('/')` only when unmapped); the type crumb links only if a real page whose path equals the type slug exists (`get_page_by_path( $type_slug )`), else plain text; the category link is guarded with `is_wp_error()` so a broken term link renders as plain text, never a broken `href`.
- `LISTING_URL` = `$SITE_URL/listing/{listing}/`.

## Steps

### 1. Both consumers call the one trail builder
- **Action**: `grep -n "get_breadcrumb_items" includes/schema/class-schema-generator.php blocks/listing-detail/render.php`.
- **Expect**: defined once (`class-schema-generator.php:621`), called by `render.php:288` and by `output_breadcrumbs()` (`:693`). Neither rebuilds its own trail inline.

### 2. The visible trail has the canonical crumbs
- **Action**: open `LISTING_URL` logged out; read `nav.listora-detail__breadcrumb[aria-label="Breadcrumb"] ol > li`:
  `names = li.map(n => n.textContent.trim()); names[0] === 'Directory'; names[names.length-1] === document.querySelector('.listora-detail__title, h1').textContent.trim(); li[li.length-1].getAttribute('aria-current') === 'page';`
- **Expect**: order Directory, type, primary category, listing title; the root `href` equals the mapped Directory page, NOT `home_url('/')`; the leaf is `aria-current="page"` and not a link.

### 3. The JSON-LD matches name for name, URL for URL
- **Action**: parse the `script[type="application/ld+json"]` whose `@type` is `BreadcrumbList` (flatten arrays and `@graph`); map `itemListElement` to `[position, name, item || null]`.
- **Expect**: names equal the visible crumbs in order; `item` of position 1 equals the visible root href; a crumb that is plain text has NO `item`; the leaf is the last position without `item`.

### 4. A broken category term link degrades to plain text (positive control pair)
- **Control**: step 2 shows the category crumb as `<a href>` when `get_term_link()` works.
- **Action**: induce a `WP_Error` (`add_filter( 'term_link', fn() => new WP_Error() )` in a mu-plugin for one run).
- **Expect**: the category crumb is a `<span>` (or `<a>` without `href`), never `<a href="">` or `<a href="#">`; the JSON-LD omits `item` for that position too.

### 5. Category page breadcrumb (2.0.0)
- **Action**: follow the category crumb (`/listing-category/{cat}/`).
- **Expect**: 200, `h1` is the term title and the body carries `.listora-term-archive` with `.listora-term-archive__crumbs` (the Directory filtered to the term, with its own breadcrumb).

### 6. SEO plugin owns schema
- **Expect**: with Yoast/Rank Math/AIOSEO/SEOPress active, Listora emits NO BreadcrumbList JSON-LD (`output_breadcrumbs()` returns early) while the visible breadcrumb still renders from the same helper.

### 7. Non-public listing as its owner
- **Expect**: {owner} viewing their own pending/rejected listing still builds a trail without a fatal.

### 8. Mobile and Google parity
- **Expect** at 390: the breadcrumb wraps or scrolls inside its own container, no horizontal page scroll. Manual: run `LISTING_URL` through the Rich Results Test: BreadcrumbList parses, root URL is the mapped Directory page, no "invalid item" errors.

## Role ladder
| Actor | Expect |
|---|---|
| {guest} (control) | steps 2-3 |
| {owner}, {admin} | the same trail; nothing depends on the viewer; step 7 for the owner of a non-public listing |

## Pass criteria
1. One builder feeds both trails.
2. Visible names, order and URLs equal the JSON-LD; plain-text crumbs have no `item`.
3. A broken term link never produces an empty `href`.
4. With an SEO plugin active the JSON-LD is skipped but the visible trail remains.

## Fail diagnostics
| Symptom | File |
|---|---|
| Trail divergence | `includes/schema/class-schema-generator.php:621-675` (`get_breadcrumb_items()`), `:679-715` (`output_breadcrumbs()`) |
| Visible crumbs not from the helper | `blocks/listing-detail/render.php:288`, markup `:428` |
| Root URL wrong | page registry mapping for `directory` / `wb_listora_get_page_url()` |

## Not verified
Step 4's induced WP_Error, step 7, step 8 (390px, Rich Results) were not run. Adding or removing a crumb means editing ONLY `get_breadcrumb_items()`. Pairs with `seo-schema/listing-seo-head-output.md`.

## Cleanup
Remove the mu-plugin.
