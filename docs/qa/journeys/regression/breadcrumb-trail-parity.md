---
journey: breadcrumb-trail-parity
plugin: wb-listora
priority: high
roles: [anonymous, subscriber, administrator]
covers: [listing-detail-block, breadcrumbs, schema-breadcrumblist, json-ld-parity, page-registry-root, guarded-term-link]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "breadcrumbs feature enabled (default) AND no SEO plugin owning schema (Listora emits its own BreadcrumbList; with Yoast / Rank Math / AIOSEO / SEOPress active Listora skips it by design)"
  - "A published listing with a listing type AND a primary category (capture LISTING_URL, e.g. /listing/fitlab-performance-gym/)"
  - "The Directory page is mapped in the page registry (reference site: /listings/)"
estimated_runtime_minutes: 6
covers_card: null
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Visible breadcrumb trail matches the JSON-LD BreadcrumbList exactly

Sentinel for M12. The visual breadcrumb (`blocks/listing-detail/render.php`) and the JSON-LD BreadcrumbList (`Schema_Generator::output_breadcrumbs()`) were built from divergent sources: different root label (Directory vs Home), different root URL (page-registry permalink vs `home_url('/')`) and different type-URL resolution. Google saw a BreadcrumbList that did not match the visible crumbs. `Schema_Generator::get_breadcrumb_items( $post_id )` (`includes/schema/class-schema-generator.php:621`) is the single source both consumers call: `render.php:288` for the visible trail and `output_breadcrumbs()` (`:679-693`) for the JSON-LD.

Trail shape: **Directory root -> listing type -> primary category -> listing title (URL-less leaf)**. The root resolves via `wb_listora_get_page_url('directory')` (falls back to `home_url('/')` only when unmapped); the type crumb links only if a real page whose path equals the type slug exists (`get_page_by_path( $type_slug )`), otherwise it is plain text; the category link is guarded with `is_wp_error()` so a broken term link renders as plain text, never a broken `href`.

Verified live 2026-10-12 on `/listing/fitlab-performance-gym/`: visible `Directory (/listings/) > Business (plain text, no page "business") > Health & Wellness (/listing-category/health-wellness/) > FitLab Performance Gym (aria-current)`; JSON-LD positions 1-4 with the same names; `item` present for 1 and 3, absent for 2 and 4.

## Setup
- Site: `$SITE_URL`. LISTING_URL = a listing page with a type and a primary category. Confirm the Directory page mapping: the root crumb URL equals `wb_listora_get_page_url('directory')`, not `home_url('/')`.

## Steps

### 1. Both consumers call the one trail builder
- **Action**: `grep -n "get_breadcrumb_items" includes/schema/class-schema-generator.php blocks/listing-detail/render.php`
- **Expect**: the method is defined once (`class-schema-generator.php:621`), called by `render.php:288` and by `output_breadcrumbs()` (`:693`). Neither rebuilds its own trail inline.

### 2. The visible trail has the canonical crumbs
- **Action**: open LISTING_URL logged out; read `nav.listora-detail__breadcrumb[aria-label="Breadcrumb"] ol > li`.
- **Verify**:
  ```js
  const li = [...document.querySelectorAll('nav.listora-detail__breadcrumb ol > li')];
  const names = li.map(n => n.textContent.trim());
  names[0] === 'Directory';
  names[names.length - 1] === document.querySelector('.listora-detail__title, h1').textContent.trim();
  li[li.length - 1].getAttribute('aria-current') === 'page';
  ```
- **Expect**: order Directory, listing type, primary category, listing title; the root link `href` equals the mapped Directory page (`/listings/` here), NOT `home_url('/')`; the leaf is `aria-current="page"` and is not a link.

### 3. The JSON-LD BreadcrumbList matches name for name, URL for URL
- **Action**: parse the `script[type="application/ld+json"]` block whose `@type` is `BreadcrumbList`.
- **Verify**:
  ```js
  const ld = [...document.querySelectorAll('script[type="application/ld+json"]')]
    .flatMap(s => { const j = JSON.parse(s.textContent); return Array.isArray(j) ? j : (j['@graph'] || [j]); })
    .find(o => o['@type'] === 'BreadcrumbList');
  ld.itemListElement.map(i => [i.position, i.name, i.item || null]);
  ```
- **Expect**: names equal the visible crumb names in the same order; `item` of position 1 equals the visible root href; a crumb that is plain text on the page has NO `item` in the JSON-LD; the leaf is the last position without `item`.

### 4. A broken category term link degrades to plain text (POSITIVE CONTROL pair)
- **Control**: step 2 shows the category crumb as an `<a href>` when `get_term_link()` works.
- **Action**: pick (or temporarily induce) a listing whose primary-category `get_term_link()` returns a `WP_Error` (e.g. `add_filter( 'term_link', fn() => new WP_Error() )` in a mu-plugin for one run).
- **Expect**: the category crumb is a `<span>` (or `<a>` without `href`), never `<a href="">` or `<a href="#">`; the JSON-LD omits `item` for that position too.

### 5. Category page breadcrumb (2.0.0)
- **Action**: follow the category crumb (`/listing-category/<slug>/`).
- **Expect**: 200, `h1` is the term title and the page body carries `.listora-term-archive` with `.listora-term-archive__crumbs` (the Directory filtered to the term, with its own breadcrumb), verified live for `health-wellness`.

### 6. SEO plugin owns schema
- **Expect**: with Yoast/Rank Math/AIOSEO/SEOPress active, Listora emits NO BreadcrumbList JSON-LD (`output_breadcrumbs()` returns early) while the visible breadcrumb still renders from the same helper.

### 7. Role ladder
- **Logged out (control)**: steps 2-3.
- **Subscriber, listing owner, administrator**: the same trail; nothing in it depends on the viewer. Check once as the owner to confirm a pending/rejected listing (visible to its owner only) still builds a trail without a fatal.

### 8. Mobile
- **Expect** at 390: the breadcrumb wraps or scrolls inside its own container, no horizontal page scroll.

### 9. Google Rich Results parity (manual)
- Run LISTING_URL through the Rich Results Test: BreadcrumbList parses, root URL is the mapped Directory page, no "invalid item" errors.

## Fail diagnostics
- Trail divergence: `includes/schema/class-schema-generator.php:621-675` (`get_breadcrumb_items()`), `:679-715` (`output_breadcrumbs()`).
- Visible crumbs not from the helper: `blocks/listing-detail/render.php:288`, markup `:428`.
- Root URL wrong: page registry mapping for `directory` / `wb_listora_get_page_url()`.

## Notes
- Adding or removing a crumb means editing ONLY `get_breadcrumb_items()`. Pairs with `regression/schema-rest-toggle-gate.md` and `regression/schema-yoast-rankmath-guard.md`.

Verified via: curl/DOM + JSON-LD parse of the live listing page and the category page as a logged-out visitor. Step 4's induced WP_Error, the owner view of non-public listings and 390px were not run.
