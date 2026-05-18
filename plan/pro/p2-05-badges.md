# P2-05 — Custom Card Badges

## Scope: Pro Only

---

## Overview

Badges are small visual indicators displayed on listing cards and detail pages. Six built-in badges ship by default (Featured, Verified, New, Top Rated, Sponsored, Claimed), and admins can create custom badges with their own label, color, Lucide icon, and assignment method. Badges can be assigned manually, automatically via conditions, or as plan perks.

### Why It Matters

- Visual trust signals increase click-through rates on listing cards
- "Featured" and "Sponsored" badges justify premium plan pricing
- Automatic badges ("New", "Top Rated") add dynamic visual interest to the grid
- Custom badges let site owners differentiate ("Award Winner", "Local Favorite", "Eco-Friendly")
- Badges are a visible monetization lever — plans that include badges sell better

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Site owner | Show a "Featured" gold badge on premium listings | Paid listings stand out in search results |
| 2 | Visitor | See which listings are "Verified" at a glance | I trust those businesses more |
| 3 | Site owner | Auto-assign "New" badges to listings created in the last 7 days | Fresh listings get extra visibility |
| 4 | Site owner | Create a custom "Award Winner" badge | I can highlight listings that won local awards |
| 5 | Listing owner | See a "Top Rated" badge appear after getting 4.5+ average | I feel rewarded for great reviews |
| 6 | Site owner | Control badge display order and limit to 3 per card | Cards don't become cluttered with too many badges |
| 7 | Admin | Assign badges as plan perks (Gold plan = Sponsored badge) | Badges become a monetization tool |

---

## Technical Design

### Data Model

#### Badge Entity

Stored as custom post type `listora_badge` (not publicly queryable, admin-only).

```
post_title             -> "Featured"
post_status            -> "publish"
post_menu_order        -> 1 (display priority — lower = higher priority)

Meta:
_listora_badge_slug         -> "featured" (unique key)
_listora_badge_label        -> "Featured"
_listora_badge_color        -> "#F59E0B" (hex background color)
_listora_badge_text_color   -> "#FFFFFF" (hex text color)
_listora_badge_icon         -> "star" (Lucide icon name)
_listora_badge_assignment   -> "condition" | "manual" | "plan_perk"
_listora_badge_condition    -> JSON: {"field": "is_featured", "operator": "==", "value": true}
_listora_badge_plan_ids     -> JSON: [42, 43] (plan IDs that include this badge)
_listora_badge_max_display  -> 3 (max badges shown per card, global setting)
```

### Built-in Badges

| Badge | Color | Icon | Assignment | Condition |
|-------|-------|------|------------|-----------|
| Featured | Gold `#F59E0B` | `star` | Condition | `is_featured == true` |
| Verified | Blue `#3B82F6` | `shield-check` | Condition | `is_verified == true` |
| New | Green `#10B981` | `sparkles` | Condition | `created_at > (now - 7 days)` |
| Top Rated | Purple `#8B5CF6` | `trophy` | Condition | `avg_rating >= 4.5 AND review_count >= 5` |
| Sponsored | Orange `#F97316` | `megaphone` | Plan Perk | Gold plan includes this badge |
| Claimed | Teal `#14B8A6` | `badge-check` | Condition | `is_claimed == true` |

### Condition Engine

```php
class Badge_Condition_Evaluator {
    public function evaluate( int $listing_id, array $condition ): bool {
        $field    = $condition['field'];
        $operator = $condition['operator'];
        $value    = $condition['value'];

        $actual = match ($field) {
            'is_featured'  => (bool) get_post_meta($listing_id, '_listora_is_featured', true),
            'is_verified'  => (bool) get_post_meta($listing_id, '_listora_is_verified', true),
            'is_claimed'   => (bool) get_post_meta($listing_id, '_listora_is_claimed', true),
            'avg_rating'   => $this->get_rating($listing_id),
            'review_count' => $this->get_review_count($listing_id),
            'created_at'   => get_the_date('U', $listing_id),
            'listing_type' => get_post_meta($listing_id, '_listora_listing_type', true),
            default        => get_post_meta($listing_id, '_listora_' . $field, true),
        };

        return match ($operator) {
            '=='  => $actual == $value,
            '!='  => $actual != $value,
            '>'   => $actual > $value,
            '>='  => $actual >= $value,
            '<'   => $actual < $value,
            '<='  => $actual <= $value,
            'in'  => in_array($actual, (array) $value, true),
            'age_days_lt' => (time() - $actual) < ($value * DAY_IN_SECONDS),
            default => false,
        };
    }
}
```

### Badge Resolution (Which Badges Show)

```php
function get_listing_badges( int $listing_id ): array {
    $all_badges = get_all_active_badges(); // sorted by menu_order (priority)
    $matched    = [];

    foreach ($all_badges as $badge) {
        $assignment = get_post_meta($badge->ID, '_listora_badge_assignment', true);

        $qualifies = match ($assignment) {
            'condition' => $this->evaluator->evaluate($listing_id, $badge_condition),
            'manual'    => $this->has_manual_badge($listing_id, $badge->ID),
            'plan_perk' => $this->listing_plan_includes_badge($listing_id, $badge->ID),
            default     => false,
        };

        if ($qualifies) {
            $matched[] = $badge;
        }
    }

    // Limit to max display count (default 3)
    $max = (int) get_option('listora_max_badges_per_card', 3);
    return array_slice($matched, 0, $max);
}
```

### Badge Storage on Listings

For manual assignment:
```
_listora_manual_badges -> JSON: [badge_id_1, badge_id_2]
```

Condition-based and plan-perk badges are computed at render time (or cached in `search_index` for performance).

### Search Index Integration

Add column to `listora_search_index`:

```sql
ALTER TABLE {prefix}listora_search_index
ADD COLUMN badges VARCHAR(500) NOT NULL DEFAULT '';
```

`badges` stores comma-separated badge slugs for the listing, computed on reindex. This allows filtering/sorting by badge without computing at query time.

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/badges/class-badge-manager.php` | Badge resolution, condition evaluation, caching |
| `includes/badges/class-badge-post-type.php` | CPT registration + default badges |
| `includes/badges/class-badge-condition-evaluator.php` | Condition engine |
| `includes/rest/class-badges-controller.php` | REST CRUD for badges |
| `includes/admin/class-badges-page.php` | Admin list + create/edit page (Pattern B) |

### Files to Modify (wb-listora-pro)

| File | Change |
|------|--------|
| `blocks/listing-card/render.php` (Pro filter) | Render badge pills on card |
| `blocks/listing-detail/render.php` (Pro filter) | Render badges on detail page header |
| `includes/search/class-search-indexer.php` (Pro filter) | Add badge slugs to search index |

### API Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `GET` | `/listora/v1/badges` | Public | List all active badges (for frontend rendering) |
| `POST` | `/listora/v1/badges` | Admin | Create badge |
| `GET` | `/listora/v1/badges/{id}` | Admin | Get single badge |
| `PUT` | `/listora/v1/badges/{id}` | Admin | Update badge |
| `DELETE` | `/listora/v1/badges/{id}` | Admin | Delete badge |
| `POST` | `/listora/v1/listings/{id}/badges` | Admin | Manually assign badge |
| `DELETE` | `/listora/v1/listings/{id}/badges/{badge_id}` | Admin | Remove manual badge |

---

## UI Mockup

### Admin: Badge Manager (Listora > Badges)

```
┌─────────────────────────────────────────────────────────────┐
│ Badges                                      [+ Add Badge]   │
│                                                             │
│ Drag to reorder display priority                            │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ ≡ [★ Featured]  Gold     Condition: is_featured        │ │
│ │                          Assignment: Auto               │ │
│ │                                       [Edit] [Disable] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ≡ [✓ Verified]  Blue     Condition: is_verified        │ │
│ │                          Assignment: Auto               │ │
│ │                                       [Edit] [Disable] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ≡ [✦ New]       Green    Condition: created < 7 days   │ │
│ │                          Assignment: Auto               │ │
│ │                                       [Edit] [Disable] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ≡ [🏆 Top Rated] Purple  Condition: rating >= 4.5      │ │
│ │                          Assignment: Auto               │ │
│ │                                       [Edit] [Disable] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ≡ [📣 Sponsored] Orange  Plans: Gold, Premium          │ │
│ │                          Assignment: Plan Perk          │ │
│ │                                       [Edit] [Disable] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ≡ [✓ Claimed]   Teal     Condition: is_claimed         │ │
│ │                          Assignment: Auto               │ │
│ │                                       [Edit] [Disable] │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ Max badges per card: [ 3 ▾ ]                                │
│                                                             │
│ 6 badges configured                                         │
└─────────────────────────────────────────────────────────────┘
```

### Admin: Create/Edit Badge

```
┌─────────────────────────────────────────────────────────────┐
│ Edit Badge                                                  │
│                                                             │
│ Label *                                                     │
│ [ Award Winner                                  ]           │
│                                                             │
│ Slug                                                        │
│ [ award-winner ] (auto-generated, editable)                 │
│                                                             │
│ Icon (Lucide)                                               │
│ [ award    ▾ ]    Preview: [🏆 Award Winner]               │
│                                                             │
│ Colors                                                      │
│ Background: [#DC2626]  Text: [#FFFFFF]                      │
│ Preview: [■ Award Winner]                                   │
│                                                             │
│ ── Assignment Method ──────────────────────────────────────  │
│                                                             │
│ ( ) Automatic (condition-based)                             │
│ (●) Manual (admin assigns per listing)                      │
│ ( ) Plan Perk (included in specific plans)                  │
│                                                             │
│ ── Condition (if automatic) ─────────────────────────────── │
│                                                             │
│ When [ avg_rating ▾ ]  [ >= ▾ ]  [ 4.5 ]                   │
│ AND  [ review_count ▾] [ >= ▾ ]  [ 5   ]                   │
│                                                 [+ Add AND] │
│                                                             │
│ ── Plan Perk (if plan perk) ─────────────────────────────── │
│                                                             │
│ Include with plans:                                         │
│ ☐ Basic   ☐ Standard   ☑ Premium   ☑ Gold                 │
│                                                             │
│                                        [Cancel]  [Save]     │
└─────────────────────────────────────────────────────────────┘
```

### Card Badge Display

```
┌─────────────────────────────┐
│ ┌─────────────────────────┐ │
│ │                         │ │
│ │    [Listing Image]      │ │
│ │                         │ │
│ │ [★ Featured] [✓ Verif.] │ │  <-- Badges overlaid on image
│ └─────────────────────────┘ │
│ Pizza Palace                │
│ ★★★★½  ·  Restaurant       │
│ 123 Main St, Manhattan      │
└─────────────────────────────┘
```

### Badge Pill HTML

```html
<span class="listora-badge listora-badge--featured"
      style="--badge-bg: #F59E0B; --badge-text: #FFFFFF;">
    <svg class="listora-badge__icon" ...><!-- Lucide star --></svg>
    <span class="listora-badge__label">Featured</span>
</span>
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Register `listora_badge` CPT + meta fields | 2 |
| 2 | Create 6 default badges on Pro activation | 2 |
| 3 | Build `Badge_Condition_Evaluator` — all operators + field lookups | 4 |
| 4 | Build `Badge_Manager` — resolution, priority ordering, max display | 3 |
| 5 | Search index integration — badges column, reindex hook | 2 |
| 6 | Badge rendering on listing cards (Pro filter on card template) | 3 |
| 7 | Badge rendering on listing detail page header | 1 |
| 8 | Build REST controller — CRUD + manual assign/remove | 4 |
| 9 | Build admin badge list page (Pattern B) with drag-to-reorder | 4 |
| 10 | Build admin create/edit form with condition builder | 4 |
| 11 | Lucide icon picker integration | 1 |
| 12 | Color picker for background + text | 1 |
| 13 | Plan perk integration — link badges to plans | 2 |
| 14 | Manual badge assignment on listing edit screen | 2 |
| 15 | Badge CSS — pill styles, responsive, color variables | 2 |
| 16 | Caching — cache resolved badges per listing (invalidate on change) | 2 |
| 17 | Automated tests + documentation | 3 |
| **Total** | | **42 hours** |

---

## Competitive Context

| Competitor | Badges? | Our Advantage |
|-----------|---------|---------------|
| GeoDirectory | Basic featured badge only | 6 built-in + unlimited custom badges |
| Directorist | "Badges" addon ($49) | Included in Pro, condition engine, plan perks |
| HivePress | Verified badge only | Full badge system with auto-assignment rules |
| ListingPro | Featured + Verified | Custom badges with color/icon/condition |
| MyListing | Basic badges (theme-tied) | Plugin-based, works with any theme |

**Our edge:** Condition-based auto-assignment means badges update automatically as listings change (new listing gets "New" badge, loses it after 7 days; listing reaches 4.5 rating, gains "Top Rated"). The plan perk integration turns badges into a monetization lever. Custom badges with Lucide icons and color pickers give site owners full creative control. Priority ordering and max-per-card prevents visual clutter.

---

## Effort Estimate

**Total: ~42 hours (5-6 dev days)**

- Data model + CPT: 4h
- Condition engine: 4h
- Badge resolution + caching: 5h
- Card/detail rendering: 4h
- REST API: 4h
- Admin UI (list + form): 8h
- Icon/color pickers: 2h
- Plan perk integration: 2h
- Search index: 2h
- CSS + responsive: 2h
- Tests + docs: 3h
- QA: 2h
