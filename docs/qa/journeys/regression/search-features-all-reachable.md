---
journey: search-features-all-reachable
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [search-features-filter]
prerequisites:
  - "Demo content with more than 8 feature terms in use"
estimated_runtime_minutes: 5
---

# Every feature can be filtered on from the Directory

UX audit 2026-10-11, screen 2.

## Steps

### 1. Panel closed list
- **Action**: Directory, All types, open Filters at 1440 and 390.
- **Expect**: the first 8 features are the most used (WiFi, Air Conditioning, Parking, ...), then "Show all features (N)" where N is every feature in use.

### 2. Open and apply
- **Action**: open "Show all features", tick one from the hidden part, Apply Filters.
- **Expect**: URL carries `listora_features=<slug>`, results narrow, and after the reload the list is already open with the box ticked. At 1440 the open list spans the panel width; no horizontal scroll at 390.

### 3. Type narrowing
- **Action**: switch type to Restaurant.
- **Expect**: only that type's features stay visible, including ones inside "Show all".
- **On fail**: `templates/blocks/listing-search/filters.php`, filter `wb_listora_search_features_visible`.
