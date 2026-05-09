---
journey: categories-block
plugin: wb-listora
priority: normal
roles: [anonymous]
covers: [listing-categories-block, category-card-data-filter, empty-state]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "At least 5 listora_listing_cat terms with assigned listings"
  - "A page hosting the listora/listing-categories block"
estimated_runtime_minutes: 2
---

# Anonymous visitor browses the categories block

Visit a page with the listing-categories block, verifies category cards render with counts, click navigates to filtered listings.

## Setup

- Site: `$SITE_URL`
- Categories page URL: `$CAT_URL`

## Steps

### 1. Open categories page
- **Action**: `playwright_navigate $CAT_URL`
- **Expect**: grid of category cards. Each card shows label + count + icon (or thumbnail).

### 2. Verify counts match DB
- **Action**: pick a category card showing "12 listings" — verify in DB:
  ```sql
  SELECT COUNT(*) FROM wp_term_relationships tr
  JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id=tt.term_taxonomy_id
  JOIN wp_terms t ON tt.term_id=t.term_id
  JOIN wp_posts p ON tr.object_id=p.ID
  WHERE t.slug='<category-slug>' AND p.post_status='publish';
  ```
- **Expect**: count matches the card's badge

### 3. Click a category card
- **Action**: click on the card
- **Expect**: navigates to `/listings/?listora_listing_cat=<slug>`. Listings filtered correctly.

### 4. Empty-state for category with no listings
- **Action**: create a category with 0 listings (or pick an existing one). Visit the categories page.
- **Expect**: card either hidden OR shown with "0" count badge. Per documented behavior — verify which is configured.
- **On fail**: empty-state branch in `class-listing-categories.php`

### 5. Verify `wb_listora_category_card_data` filter
- **Action**: register a temporary filter that adds a custom field, then refresh the categories page
  ```bash
  wp eval 'add_filter("wb_listora_category_card_data", function($d, $cat) { $d["smoke_marker"] = "X"; return $d; }, 10, 2);'
  ```
- **Expect**: the filter receives the data array (no fatal). Pro/themes use this to add their own data.

## Pass criteria

1. Category cards render with counts matching DB
2. Click navigates to filtered listings
3. Empty-category behavior is documented + consistent
4. `wb_listora_category_card_data` filter is hooked

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Cards show 0 always | count query broken | `blocks/listing-categories/render.php` |
| Filter URL doesn't narrow | rewrite rules stale | `wp rewrite flush` |
| Filter not callable | filter not fired | `apply_filters('wb_listora_category_card_data')` site |
