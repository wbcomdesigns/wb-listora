---
journey: directory-url-params-prefixed
plugin: wb-listora
priority: critical
roles: [anonymous, member-owner, admin]
covers: [listing-search, listing-grid, listing-map, pagination, saved-searches, interoperability]
prerequisites:
  - "BuddyX Pro (or Reign), WB Listora Pro active"
  - "A temporary mu-plugin that claims the `category` query var (step 1); delete it after"
estimated_runtime_minutes: 10
covers_card: 10335750932
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Directory filters survive another plugin owning `category`, `type` or `page`

## Background

Filters once travelled as `?category=`, `?type=`, `?page=`, `?location=`: names WordPress or other plugins claim (a customer plugin owned `category`, so a category choice 404'd before Listora ran, Zoho #41828; core also 301s `?page=2`). Every page-URL filter is `listora_{name}`; bare names are still read, never written. `wb_listora_url_arg()` (`includes/search/search-url-helpers.php:36`) reads `listora_{name}` first, then `{name}`. REST parameters are unchanged. The category taxonomy is `listora_listing_cat`; slugs below exist on this site (type `restaurant` has category `casual`, 6 listings).

Verified via: curl + interactivity state (`totalResults`, `currentPage`) on `/listings/`, 2026-10-12. Steps 1 and 4 (mu-plugin, Pro load-more, saved search) not run.

## Steps

### 1. Reproduce the conflict
- **Action**: add `wp-content/mu-plugins/zz-qa-j1-claim-category-TEMP.php` registering a public post type with `'query_var' => 'category'`.
- **Expect**: `/?category=casual` and `/listings/?category=casual` 404 (proves the conflict is live); `/listings/?test=1` is 200. Not run in this pass.

### 2. The customer's flow, logged out
- **Action**: on `/listings/` choose type Restaurant (type tab), open Filters, Category select (`aria-labelledby="listora-filter-category-label"`) "Casual", Apply.
- **Expect**: URL `?listora_type=restaurant&listora_category=casual`, HTTP 200, 6 results; reload keeps both selections. Verified server-side: `?listora_type=restaurant&listora_category=casual` -> totalResults 6, 6 cards.

### 3. Old links on a clean site (POSITIVE CONTROL for the prefix winning)
- **Action**: remove the mu-plugin. Open `?type=restaurant&category=casual` and `?category=bar-grill&listora_category=casual`.
- **Expect**: first gives the same 6; second shows 6 too (`listora_category=casual` wins over the bare `bar-grill` which alone gives 3: `?listora_category=bar-grill` -> 3). Verified.

### 4. Every surface agrees
- Tag chips on cards and listing pages link to `?listora_tags=`.
- Map markers match the grid count for `?listora_keyword=` and `?listora_category=`.
- Next page keeps the filter: `?listora_category=...&listora_page=2` (`templates/blocks/listing-grid/pagination.php:29-96`). A page past the end shows the last page: `/listings/?listora_page=999` -> currentPage 6 of 6 with 4 cards (verified).
- Pro load-more: Load More appends the next page; `rel=next` carries only `listora_` names. REST keeps its names (`/listora/v1/search?category=`).
- Pro saved search: saving from a `listora_` URL stores engine names (`category`, `type`); Run link uses `listora_` names.

### 5. Role ladder
- Anonymous, another member, owner, editor and admin all get identical results for the same URL (public read). Saved-search part needs a member; admin is the control only.

### 6. 2000+ rows
- The directory has 104 public listings (2,811 rows total incl. drafts). Filter + page past last prove the clamp on a 6-page set; the 2,644-row member list is covered by dashboard-tab-pagination.

## Fail diagnostics

- 404 on a `listora_` URL: something writes a bare name again. Grep `params.set( '` in `src/interactivity/store.js` outside `buildSearchURL()`, and `add_query_arg( '` with a bare filter name in `templates/`.
- Filter ignored on a `listora_` URL: a reader bypasses `wb_listora_url_arg()` (`includes/search/search-url-helpers.php:36`).
- Pagination loses filter: `templates/blocks/listing-grid/pagination.php:29`.

## Not verified

Steps 1, 4 (Pro load-more, saved search, map), 390px.
