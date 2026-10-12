---
journey: search-features-filter-reaches-every-feature
group: directory-search
actor: customer
mode: browser
plugin: wb-listora
priority: high
roles: [guest, owner, admin]
covers: [search-features-filter, ux-audit-2026-10-11-screen-2]
prerequisites:
  - "More than 8 feature terms in use on published listings"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes: []
site_profile: required
---

# Every feature in use can be filtered on from the Directory

## Why
A Features group that showed only the first few terms made the rest unfilterable for visitors. The panel shows the 8 most used and folds every other feature into "Show all features (N)".

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- `N` = number of feature terms with published listings: `wp term list listora_listing_feature --hide_empty=1 --format=count` (or REST facets). `{feature}` = a feature that is NOT in the top 8 (the 9th by count); `$TOP_FEATURE` = the most used.

## Steps

### 1. Closed list
- **Action**: open `$SITE_URL{directory}` (All types), click **Filters** (`actions.toggleFiltersPanel`).
- **Expect**: Features group (`.listora-search__filter-group`, label "Features") shows 8 `.listora-search__filter-checkbox` visible, most used first, then `<details class="listora-search__filter-more">` with summary "Show all features (N)"; N checkboxes exist in the DOM.

### 2. Open and apply a hidden one
- **Action**: open "Show all features (N)", tick `{feature}`, press **Apply Filters**.
- **Expect**: URL carries `listora_features={feature}`; results narrow; after the reload the `<details>` is `open` and the box is ticked. At 1440 the open list spans the panel width; no horizontal scroll at 390.
- **Read-back**: result count equals `GET /search?features={feature}` `total`.
- **Positive control**: `?listora_features=$TOP_FEATURE` leaves the `<details>` closed.

### 3. Type narrowing
- **Action**: choose `{type}` (or `?listora_type={type}`).
- **Expect**: only that type's features remain, also inside "Show all" ("Show all features (M)", M <= N); a type with a "Selected only" list shows exactly that list.

### 4. Large set
- **Expect**: feature counts come from published listings only; the term query is cached by `wb_listora_get_terms_for_listing_type()` (`includes/helpers.php:212`), one query per term type, no per-term count loop (count queries with the `query` filter on 2000+ listings). Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {guest}, {owner}, {admin} | identical count; features used only by drafts do not appear (`hide_empty` true) |

## Pass criteria
1. 8 visible + "Show all (N)" where N equals every feature in use.
2. A hidden feature filters, and the panel reopens with it ticked.
3. Type narrowing applies inside "Show all".

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Fewer than N boxes | query / chunking | `templates/blocks/listing-search/filters.php:132-176` |
| Visible count wrong | filter default 8 | `wb_listora_search_features_visible` (`:161`) |
| Feature not filterable | allow-list | `wb_listora_get_feature_filter_map()` (`:125`) |

## Not verified
The panel open/close and 1440/390 layout were not clicked in a browser; verified by curl of the server-rendered Directory. Step 4 added in consolidation.

## Cleanup
None.
