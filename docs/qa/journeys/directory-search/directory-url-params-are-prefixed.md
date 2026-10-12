---
journey: directory-url-params-are-prefixed
group: directory-search
actor: customer
mode: mixed
plugin: wb-listora
priority: critical
roles: [guest, owner, other, editor, admin]
covers: [listing-search, listing-grid, listing-map, pagination, saved-searches, interoperability, card-10335750932, zoho-41828]
prerequisites:
  - "BuddyX Pro (or Reign); a temporary mu-plugin that claims the `category` query var (step 1)"
  - "For steps 4b/4c: Pro active (load-more, saved search)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/directory-url-params-prefixed.md
fails_on: unrecorded
site_profile: required
---

# Directory filters survive another plugin owning `category`, `type` or `page`

## Why
Filters once travelled as `?category=`, `?type=`, `?page=`, `?location=`, names WordPress or other plugins claim (a customer plugin owned `category` so a category choice 404'd before Listora ran; core also 301s `?page=2`). Every page-URL filter is now `listora_{name}`; bare names are still read, never written.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `wb_listora_url_arg()` (`includes/search/search-url-helpers.php:36`) reads `listora_{name}` first, then `{name}`. REST parameters are unchanged. Taxonomy is `listora_listing_cat`.
- `{type}` + `{cat}`: a type with a category that has >= 2 and < page-size listings; `$CAT_B`: a second category with a different count. `T` = `GET /search?type={type}&category={cat}` `total`.

## Steps

### 1. Reproduce the conflict
- **Action**: add `$WP_PATH/wp-content/mu-plugins/zz-qa-claim-category-TEMP.php` registering a public post type with `'query_var' => 'category'`.
- **Expect**: `/?category={cat}` and `{directory}?category={cat}` 404 (proves the conflict is live); `{directory}?test=1` is 200. Not verified live.

### 2. The customer's flow, logged out
- **Action**: on the Directory choose type `{type}` (type tab), open Filters, Category select (`aria-labelledby="listora-filter-category-label"`) = `{cat}`, Apply.
- **Expect**: URL `?listora_type={type}&listora_category={cat}`, HTTP 200, T results; reload keeps both selections.
- **Read-back**: totalResults in interactivity state equals T.

### 3. Old links on a clean site (positive control for the prefix winning)
- **Action**: remove the mu-plugin. Open `?type={type}&category={cat}` and `?category=$CAT_B&listora_category={cat}`.
- **Expect**: first gives T; second also gives T (`listora_category` wins over the bare name, which alone gives `GET /search?category=$CAT_B` `total`).

### 4. Every surface agrees
- **a.** Tag chips on cards and listing pages link to `?listora_tags=`; the map's markers match the grid count for `?listora_keyword=` and `?listora_category=`.
- **b.** Next page keeps the filter: `?listora_category=...&listora_page=2` (`templates/blocks/listing-grid/pagination.php:29-96`). A page past the end shows the last page: `?listora_page=99999` -> `currentPage == pages`.
- **c.** Pro load-more appends the next page; `rel=next` carries only `listora_` names; REST keeps its names (`/listora/v1/search?category=`). Pro saved search stores engine names (`category`, `type`) when saved from a `listora_` URL; its Run link uses `listora_` names. Not verified live.

### 5. 2000+ rows
- **Expect**: filter + page past last prove the clamp on a multi-page set; the large member list is covered by `dashboard-account/dashboard-tabs-paginate-every-row.md`.

## Role ladder
| Actor | Expect |
|---|---|
| {guest}, {other}, {owner}, {editor}, {admin} | identical results for the same URL (public read) |
| {owner} (member) | saved-search part (4c) needs a logged-in member |

## Pass criteria
1. A `listora_` URL returns 200 and filters while another plugin owns `category`.
2. Bare names still work on a clean site and the prefixed name wins when both are present.
3. Pagination, tag chips, map and saved search never write a bare name.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| 404 on a `listora_` URL | something writes a bare name | grep `params.set( '` in `src/interactivity/store.js` outside `buildSearchURL()`; `add_query_arg( '` in `templates/` |
| Filter ignored on a `listora_` URL | reader bypasses helper | `includes/search/search-url-helpers.php:36` |
| Pagination loses filter | link builder | `templates/blocks/listing-grid/pagination.php:29` |

## Not verified
Steps 1 and 4c (mu-plugin, Pro load-more, saved search, map), 390px.

## Cleanup
Delete the mu-plugin.
