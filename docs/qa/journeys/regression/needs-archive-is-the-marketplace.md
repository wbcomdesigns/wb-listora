---
journey: needs-archive-is-the-marketplace
plugin: wb-listora-pro
priority: normal
roles: [anonymous, administrator]
covers:
  - BC 10208510069
  - needs-slug-ownership
likely_files:
  - ../wb-listora-pro/templates/archive-listora_need.php
  - ../wb-listora-pro/includes/frontend/class-need-single-template.php
  - ../wb-listora-pro/includes/needs/class-needs-slug.php
prerequisites:
  - "Free + Pro active, Reverse Listings (needs) feature on"
  - "A page containing the listora-pro/needs-grid block (demo: page 790, slug 'needs')"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# /needs/ is the marketplace, whichever way it is served

Since Pro 1.7 `Needs_Slug` (`wb-listora-pro/includes/needs/class-needs-slug.php`) decides what lives at `/needs/`: when a live page holds the slug `needs` the page IS the URL and the CPT archive is switched off (`has_archive` false, `uses_page()` true); when no page holds it the archive is on and renders the needs-grid BLOCK through `archive-listora_need.php` (`Need_Single_Template::archive_template()` `class-need-single-template.php:78`), canonical pointing at the marketplace page when one exists elsewhere (`needs_archive_canonical()` `:105`). Never a redirect, never a hand-written loop.

Verified via: curl on both configurations (page 790's slug renamed to `qa-j1-needs-market` then restored; rewrite rules flushed both ways).

## Steps

### A. The Needs page holds the slug (the demo site)
1. `GET /needs/` anonymously: HTTP 200; the needs-grid block markup present (3 `needs-grid` markers, a search input, a `<select>`); `<link rel="canonical" href="http://wb-listora.local/needs/" />` (from WordPress, the page's own permalink); no "Written by" archive text.
2. `get_post_type_object( 'listora_need' )->has_archive` is false; `wb_listora_get_page_url( 'browse_needs' )` is `/needs/`.
3. `/needs/feed/` is 404 (the CPT registers `feeds => false`). This replaces the old "feed still works" expectation.

### B. The marketplace page lives on another slug (archive on)
4. Rename the page's slug (e.g. `qa-j1-needs-market`), flush rewrites. `GET /needs/` is HTTP 200 (NO redirect) and renders the same block: equal counts of the grid marker and `<select>` to the page's own URL (observed 3 markers, 1 select on both).
5. `<link rel="canonical">` on `/needs/` equals the marketplace page URL (observed `/qa-j1-needs-market/`).
   - **Expect ABSENT** when an SEO plugin owns canonical (`wb_listora_seo_plugin_active()` true; use the `wb_listora_seo_plugin_active` filter), and absent when the marketplace page is a draft (`wb_listora_get_public_page_url()` returns '').
6. `/needs/feed/` is 200 `application/rss+xml` in this configuration.
7. Theme override: copy `archive-listora_need.php` into `{theme}/wb-listora/`; the theme copy wins.
8. Restore the slug to `needs`, flush rewrites, confirm `/needs/` 200 and `/qa-j1-needs-market/` 404. Remove any `_wp_old_slug` meta the rename added.

### Role ladder
| Actor | Result |
|---|---|
| Anonymous | steps 1, 4, 5 |
| Member | identical; sees "Post a Need" in the dashboard (see needs-ctas journey) |
| Moderator / admin | identical front end; admin has the CPT screens under Listora |

### 390px
Grid cards single column, filters usable, no horizontal scroll.

## Fail diagnostics
`needs-slug.php:191` (`has_archive()`), `class-need-single-template.php:78` / `:105`, `templates/archive-listora_need.php`, `class-pro-plugin.php:287-302` (page registration, default slug `needs`).
