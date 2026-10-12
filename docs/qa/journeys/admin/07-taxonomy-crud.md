---
journey: admin-taxonomy-crud
plugin: wb-listora
priority: normal
roles: [administrator, qa_editor, anonymous]
covers: [taxonomy-admin, taxonomy-cap-map, location-hierarchy, feature-flat, term-pages, per-type-views, 2.0.0-term-pages]
prerequisites:
  - "Site reachable at $SITE_URL; admin ?autologin=1"
  - "Permalinks: the term base slugs are listing-category, listing-location, listing-feature, listing-tag, listing-type"
estimated_runtime_minutes: 6
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440]
themes: [wp-admin default; buddyx-pro light for the term page]
---

# Admin manages Categories, Locations, Features, Tags and Service Categories

The Categories hub has four tabs (Categories, Locations, Features, Service Categories); tags and listing types are managed through the same capability. Every taxonomy is managed with `manage_listora_types` (not a per-taxonomy capability). The category and feature screens show the listing types that use each term and a per-type view row (`?listora_type=<slug>`).

## Steps

### 1. Categories (hierarchical)
- **Action**: `$SITE_URL/wp-admin/edit-tags.php?taxonomy=listora_listing_cat&post_type=listora_listing&autologin=1`.
- **Expect**: WordPress's term screen with the Listora hub tab row (Categories | Locations | Features | Service Categories), the columns Icon, Listing types, Image, Color, and a Parent select in the add form; a view row above the table with each listing type and its count (`?listora_type=restaurant` scopes the table to that type's categories).
- **Action**: add `Smoke Cat` (slug `smoke-cat`), then `Smoke Cat Sub` with parent `Smoke Cat`.
- **Expect**: success; the child is nested under the parent.

### 2. Locations (hierarchical, three levels)
- **Action**: `edit-tags.php?taxonomy=listora_listing_location&post_type=listora_listing`: add `Smokeland`, `Smoke State` (parent Smokeland), `Smoke City` (parent Smoke State).
- **Expect**: three-level nesting; the URL base for the terms is `/listing-location/smokeland/smoke-state/smoke-city/`.

### 3. Features (flat)
- **Action**: `edit-tags.php?taxonomy=listora_listing_feature&post_type=listora_listing`.
- **Expect**: NO Parent field in the add form (`name="parent"` absent); add `Smoke WiFi` (slug `smoke-wifi`); the Listing types column shows which types offer it (empty until a type's Features picker ticks it; "None ticked means every feature is offered").

### 4. Tags and service categories
```bash
wp term create listora_listing_tag "Smoke Tag" --slug=smoke-tag
wp term create listora_service_cat "Smoke Service Cat" --slug=smoke-svc-cat
```
- **Expect**: both create; the tag is flat; the service category is hierarchical and non-public (no front-end URL) and appears as a checkbox in a listing's Services box (`wb_listora_services[...][categories][]`).

### 5. Assign and see the term page (the 2.0.0 contract)
- **Action**: edit a published listing: tick `Smoke Cat Sub` and `Smoke WiFi` (block editor Categories/Features panels or the Features & Amenities box) > Update.
- **Expect**: `$SITE_URL/listing-category/smoke-cat-sub/` and `/listing-feature/smoke-wifi/` render the Directory filtered to the term (title = term name, breadcrumb "Directory › <term>", count = 1) and include the listing. Parent pages roll children up for locations (on this site `/listing-location/us/` counts 78 and its child `/listing-location/us/ny/` 77); record what `/listing-category/smoke-cat/` shows for the child's listing.
- **Cross-ref**: the full term page contract is `customer/2.0-term-pages-show-directory.md`.

### 6. Capability and delete
- **Action**: `wp eval 'echo current_user_can("manage_listora_types") ? "YES":"NO";'` as admin; as qa_editor try the Categories URL.
- **Expect**: admin YES; qa_editor (Editor) "Sorry, you are not allowed to access this page." and the Categories hub is missing from his menu.
- **Action**: delete the smoke terms (`wp term delete listora_listing_cat smoke-cat-sub --by=slug`, etc.).
- **Expect**: the listing keeps its other terms; the deleted term's URL returns 404; no orphan `term_relationships`.

### 7. Role ladder
| Rung | Expect |
|---|---|
| administrator | steps 1-6 |
| qa_editor | no term management (needs `manage_listora_types`); can still moderate reviews/claims |
| anonymous | public term pages (steps 5) work; `edit-tags.php` redirects to login |

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
