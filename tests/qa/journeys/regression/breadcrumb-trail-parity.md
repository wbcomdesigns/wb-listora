---
journey: breadcrumb-trail-parity
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-detail-block, breadcrumbs, schema-breadcrumblist, json-ld-parity, page-registry-root, guarded-term-link]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "breadcrumbs feature enabled (default) AND no SEO plugin owning schema (Listora emits its own JSON-LD)"
  - "A published listing with a listing type AND a primary category exists (capture LISTING_URL)"
  - "The Directory page is mapped in the page registry (default /listings/)"
estimated_runtime_minutes: 5
covers_card: null
covers_commit: b348c4e
---

# Visible breadcrumb trail matches the JSON-LD BreadcrumbList exactly

Regression sentinel for M12 (`b348c4e`). The visual breadcrumb
(`blocks/listing-detail/render.php`) and the JSON-LD BreadcrumbList
(`Schema_Generator::output_breadcrumbs()`) were built from divergent sources:
different root label (Directory vs Home), different root URL (page-registry
permalink vs `home_url('/')`), and different type-URL resolution (real page
lookup vs a hardcoded `home_url('/'.$slug.'/')` pattern). Google saw a
BreadcrumbList that did not match the visible crumbs. The fix adds
`Schema_Generator::get_breadcrumb_items( $post_id )` as the single source of
truth that BOTH consumers call. Trail shape: **Directory root → listing type →
primary category → listing title (URL-less leaf)**. The root resolves via
`wb_listora_get_page_url('directory')` (falling back to `home_url('/')` only when
unmapped); the type resolves via `get_page_by_path( $type_slug )`; the category
link is guarded with `is_wp_error()` (H14) so a broken term link renders as
plain text, never a broken `href`.

## Setup

- Site: `$SITE_URL`. LISTING_URL = a listing-detail page with a type + primary category.
- Confirm the Directory page is mapped: the root crumb URL should equal `wb_listora_get_page_url('directory')`, not `home_url('/')`.

## Steps

### 1. Both consumers call the one canonical trail builder
- **Action**:
  ```
  grep -n "get_breadcrumb_items" includes/schema/class-schema-generator.php blocks/listing-detail/render.php
  ```
- **Expect**: `Schema_Generator::get_breadcrumb_items()` is defined (returns an ordered array of `{name, url}`); `render.php` builds its `$breadcrumbs` from `\WBListora\Schema\Schema_Generator::get_breadcrumb_items( $post_id )`; `output_breadcrumbs()` builds its JSON-LD from the same method. Neither rebuilds its own trail inline.
- **On fail**: `b348c4e` — a consumer reverted to a private trail.

### 2. The visible trail has the canonical 4 crumbs
- **Action**: open LISTING_URL (logged-out). Read the rendered breadcrumb nav.
- **Verify**:
  ```js
  const crumbs = [...document.querySelectorAll('.listora-detail__breadcrumb a, .listora-detail__breadcrumb span, .listora-breadcrumb *')]
    .map(n => n.textContent.trim()).filter(Boolean);
  // root label is exactly "Directory" (not "Home")
  crumbs[0] === 'Directory';
  // leaf is the listing title
  crumbs[crumbs.length - 1] === document.querySelector('.listora-detail__title, h1').textContent.trim();
  ```
- **Expect**: order = Directory, listing-type, primary-category, listing title. The root link's `href` equals `wb_listora_get_page_url('directory')` (the mapped Directory page) — NOT `home_url('/')`.
- **On fail**: root label "Home" or root href `home_url('/')` → trail not from the helper.

### 3. The JSON-LD BreadcrumbList matches the visible trail name-for-name, URL-for-URL
- **Action**: in page source, find the `<script type="application/ld+json">` block whose `@type` is `BreadcrumbList`.
- **Verify** (parse the JSON):
  ```js
  const ld = [...document.querySelectorAll('script[type="application/ld+json"]')]
    .map(s => JSON.parse(s.textContent))
    .flat()
    .find(o => o['@type'] === 'BreadcrumbList');
  const ldNames = ld.itemListElement.map(i => i.name);
  // first item name + url match the visible root crumb
  ldNames[0] === 'Directory';
  ld.itemListElement[0].item === <Directory page URL from step 2>;
  // every visible crumb name appears in the same order in the BreadcrumbList
  ```
- **Expect**: BreadcrumbList names == visible crumb names in the same order; the root `item` URL == the visible root href (the mapped Directory page); the type crumb URL == the real type page from `get_page_by_path()`. The leaf (listing) is the last position.
- **On fail**: any name/URL mismatch → the two consumers diverged (the exact M12 bug).

### 4. Broken category term link degrades to plain text (no broken href)
- **Action**: pick (or temporarily induce) a listing whose primary-category `get_term_link()` returns a `WP_Error` (e.g. a category whose term was deleted out from under a stale assignment). Reload its detail page.
- **Verify**:
  ```js
  // the category crumb renders as text, not an <a> with an empty/broken href
  const catCrumb = [...document.querySelectorAll('.listora-detail__breadcrumb *')]
    .find(n => n.textContent.trim() === '<category name>');
  catCrumb.tagName !== 'A' || !catCrumb.getAttribute('href');
  ```
- **Expect**: the category crumb is plain text (`url => ''` from the `is_wp_error( $cat_link ) ? '' : $cat_link` guard); the JSON-LD omits the `item` URL for that position too. Never an `<a href="">` or an `<a href="#">`.
- **On fail**: H14 guard missing in `get_breadcrumb_items()`.

### 5. Google Rich Results parity check
- **Action**: run the LISTING_URL through Google's Rich Results Test (or the schema validator).
- **Expect**: the BreadcrumbList parses with the root URL pointing at the mapped Directory page (not `home_url('/')`), no "URL not allowed"/"invalid item" errors. Names match the on-page crumbs.
- **On fail**: root URL drift → `get_breadcrumb_items()` directory-root resolution.

## Fail diagnostics
- Trail divergence → `includes/schema/class-schema-generator.php::get_breadcrumb_items()` / `output_breadcrumbs()`.
- Visible crumbs not from helper → `blocks/listing-detail/render.php`.
- Root URL wrong → page registry mapping for `directory` / `wb_listora_get_page_url()`.

## Notes
- Single-source-of-truth sentinel: adding/removing a crumb means editing ONLY `get_breadcrumb_items()`; this journey then covers both the visible trail and the structured data. Pairs with `regression/schema-rest-toggle-gate.md` and `regression/schema-yoast-rankmath-guard.md` (the schema-output gating sentinels).
