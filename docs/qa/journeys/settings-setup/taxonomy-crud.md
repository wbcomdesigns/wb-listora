---
journey: taxonomy-crud
group: settings-setup
actor: admin
mode: browser
plugin: wb-listora
priority: normal
roles: [admin, editor, guest]
covers: [taxonomy-admin, taxonomy-cap-map, location-hierarchy, feature-flat, term-pages, per-type-views, 2.0.0-term-pages]
prerequisites:
  - "Site reachable at $SITE_URL; admin via `?autologin={admin}`"
  - "Permalinks: term bases are listing-category, listing-location, listing-feature, listing-tag, listing-type"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [wp-admin default, buddyx-pro light for the term page]
site_profile: required
supersedes: []
---

# Admin manages Categories, Locations, Features, Tags and Service Categories

## Why
The Categories hub has four tabs (Categories, Locations, Features, Service Categories); tags and listing types go through the same capability. Every taxonomy is managed with `manage_listora_types` (not a per-taxonomy capability). Wrong hierarchy, a widened capability or orphaned relationships corrupt the whole directory. The term-page contract is owned by `2.0-term-pages-show-directory`.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `LISTING` = a published listing you can edit ({listing}); note its current terms.
- Term baseline: `wp term list listora_listing_location --fields=slug,count` (counts are derived live below, never hard-coded).

## Steps

### 1. Categories (hierarchical)
- **Action**: `$SITE_URL/wp-admin/edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing`; add `Smoke Cat` (slug `smoke-cat`), then `Smoke Cat Sub` with parent `Smoke Cat`.
- **Expect**: the term screen with the Listora hub tab row (Categories | Locations | Features | Service Categories), columns Icon, Listing types, Image, Color, a Parent select in the add form, and a view row above the table with each listing type and its count (`?listora_type={type}` scopes the table to that type's categories). The child is nested under the parent.
- **Read-back**: `wp term get listora_listing_cat smoke-cat-sub --by=slug --field=parent` equals the parent's id.

### 2. Locations (hierarchical, three levels)
- **Action**: `edit-tags.php?taxonomy=listora_listing_location&post_type=listora_listing`: add `Smokeland`, `Smoke State` (parent Smokeland), `Smoke City` (parent Smoke State).
- **Expect**: three-level nesting; the URL base is `/listing-location/smokeland/smoke-state/smoke-city/`.
- **Read-back**: `wp term list listora_listing_location --search=smoke --fields=slug,parent`.

### 3. Features (flat)
- **Action**: `edit-tags.php?taxonomy=listora_listing_feature&post_type=listora_listing`; add `Smoke WiFi` (slug `smoke-wifi`).
- **Expect**: NO Parent field (`name="parent"` absent); the Listing types column shows which types offer it (empty until a type's Features picker ticks it; "None ticked means every feature is offered").
- **Read-back**: the term has `parent` 0.

### 4. Tags and service categories
- **Action**:
  ```bash
  wp term create listora_listing_tag "Smoke Tag" --slug=smoke-tag
  wp term create listora_service_cat "Smoke Service Cat" --slug=smoke-svc-cat
  ```
- **Expect**: both create; the tag is flat; the service category is hierarchical and non-public (no front-end URL) and appears as a checkbox in a listing's Services box (`wb_listora_services[...][categories][]`).
- **Read-back**: `wp term get ... --field=slug`; GET `$SITE_URL/listing-tag/smoke-tag/` works, the service-category URL 404s.

### 5. Boundary input
- **Action**: add terms with an empty name, whitespace-only, markup (`<b>x</b>`), a 300-char name, quotes/emoji, and a duplicate slug under the same parent.
- **Expect**: empty/whitespace refused by core; markup stripped or escaped; duplicates get `-2` or are refused; no PHP notice.
- **Read-back**: `wp term list` shows the stored names escaped. Not verified live.

### 6. Assign and see the term page (the 2.0.0 contract)
- **Action**: edit `LISTING`: tick `Smoke Cat Sub` and `Smoke WiFi` (block editor Categories/Features panels or the Features & Amenities box) > Update.
- **Expect**: `$SITE_URL/listing-category/smoke-cat-sub/` and `/listing-feature/smoke-wifi/` render the Directory filtered to the term (title = term name, breadcrumb "Directory › <term>", count = 1) and include the listing. Parent location pages roll children up: the parent's count is >= the child's.
- **Read-back**: `wp term get listora_listing_cat smoke-cat-sub --by=slug --field=count` is 1; record what `/listing-category/smoke-cat/` shows for the child's listing; `wp term get listora_listing_location {loc} --by=slug --field=count` >= its child's. Full term-page contract: `2.0-term-pages-show-directory`.

### 7. Capability and delete
- **Action**: `wp eval 'echo current_user_can("manage_listora_types") ? "YES":"NO";'` as {admin}; as {editor} open the Categories URL. Delete the smoke terms (`wp term delete listora_listing_cat smoke-cat-sub --by=slug`, etc.).
- **Expect**: {admin} YES; {editor} "Sorry, you are not allowed to access this page." and the Categories hub missing from the menu. After delete the listing keeps its other terms, the deleted term's URL returns 404.
- **Read-back**: no orphan rows: `SELECT COUNT(*) FROM ${PREFIX}term_relationships WHERE term_taxonomy_id NOT IN (SELECT term_taxonomy_id FROM ${PREFIX}term_taxonomy)` is 0.

### 8. Scale and 390
- **Action**: on a site with 500+ terms (seed via WP-CLI) load the Categories and Locations screens, search and page; repeat the hub at 390.
- **Expect**: WordPress pagination, no unbounded list, the hub tab row wraps with no horizontal scroll.
- **Read-back**: `wp term list ... --format=count` matches the screen total. Not verified live.

## Role ladder
| Rung | Expect |
|---|---|
| {admin} | steps 1-8 |
| {editor} | no term management (needs `manage_listora_types`); can still moderate reviews/claims |
| {guest} | public term pages (step 6) work; `edit-tags.php` redirects to login |

## Pass criteria
1. Hierarchical taxonomies nest; Features/Tags are flat.
2. Assigned terms resolve to working term pages with the right count.
3. Only `manage_listora_types` manages terms; deletion cleans relationships.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Parent select on Features | taxonomy registered hierarchical | `includes/core/class-taxonomies.php` (~148) |
| Term page is a blog archive | Directory page not mapped / filter off | `class-term-archives.php` |
| Editor can manage terms | capability map widened | `class-taxonomies.php` (`manage_terms`) |

## Not verified
Steps 5 and 8; the 390 hub row.

## Cleanup
Delete all `smoke-*` terms and restore `LISTING`'s terms.
