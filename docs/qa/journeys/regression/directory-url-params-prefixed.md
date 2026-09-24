---
journey: directory-url-params-prefixed
plugin: wb-listora
priority: critical
roles: [anonymous, member-owner]
covers: [listing-search, listing-grid, listing-map, pagination, saved-searches, interoperability]
prerequisites:
  - "Reign or BuddyX, WB Listora Pro active"
  - "A temporary mu-plugin that claims the `category` query var (step 1); delete it after"
estimated_runtime_minutes: 10
covers_card: 10335750932
---

# Directory filters survive another plugin owning `category`, `type` or `page`

## Background

Directory filters travelled as `?category=`, `?type=`, `?page=`, `?location=`. Those are WordPress query vars or names other plugins claim. On a customer site a plugin owned `category`, so choosing a category 404'd the whole page before Listora ran (Zoho #41828). Core also 301s `?page=2` back to page 1. Since 1.9.0 every page-URL filter is `listora_{name}`; bare names are still read, never written. REST parameters are unchanged.

## Steps

### 1. Reproduce the conflict
- **Action**: add `wp-content/mu-plugins/zz-qa-claim-category-TEMP.php` registering a public post type with `'query_var' => 'category'`.
- **Expect**: `/?category=x` and `/listings/?category=x` are 404 (that is the other plugin's doing, and proves the conflict is live); `/listings/?test=1` is 200.

### 2. The customer's flow, logged out
- **Action**: on the directory, choose type Business, open Filters, choose category Accommodation, Apply.
- **Expect**: the URL is `?listora_type=business&listora_category=accommodation`, status 200, results filtered; reload keeps both selections.

### 3. Old links on a clean site
- **Action**: delete the mu-plugin. Open `?type=business&category=accommodation` and `?tags=<slug>`.
- **Expect**: same counts as the `listora_` URLs; with both `?category=a&listora_category=b`, `b` wins.

### 4. Every surface agrees
- Tag chips on cards and listing pages link to `?listora_tags=`.
- Map markers match the grid count for `?listora_keyword=` and `?listora_category=`.
- Next page keeps the filter: `?listora_category=…&listora_page=2`.
- Pro, load-more mode: Load More appends the next page; `rel=next` is `?listora_category=…&listora_page=2`, and from an old `?category=` URL it carries only the `listora_` name. The REST call keeps its own names (`/listora/v1/search?category=`).
- Pro saved search: saving from a `listora_` URL stores engine names (`category`, `type`); the Run link uses `listora_` names and the configured directory URL.

## Fail diagnostics
- 404 on a `listora_` URL → something writes a bare name again: grep `params.set( '` in `src/interactivity/store.js` outside `buildSearchURL()`, and `add_query_arg( '` with a bare filter name in templates.
- Filter ignored on a `listora_` URL → a reader bypasses `wb_listora_url_arg()`.
