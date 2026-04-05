# P2-12 — BuddyPress/BuddyBoss Integration

## Scope: Pro Only (NEW -- Competitor Gap)

---

## Overview

Deep integration between WB Listora and BuddyPress/BuddyBoss. Links listings and reviews to member profiles, adds listing-related activity stream items, shows "My Listings" and "My Reviews" tabs on profile pages, displays listing counts in member directories, and integrates with BP notifications. Only loads when BuddyPress or BuddyBoss is active -- uses BP's component system.

Natural fit for Wbcom Designs -- we are THE BuddyPress company. This is a unique competitive advantage.

### Why It Matters

- BuddyPress/BuddyBoss powers 100K+ community sites -- huge addressable market
- Directories + communities = engagement multiplier (members add listings, review each other's businesses)
- No WordPress directory plugin has deep BuddyPress integration -- this is a unique selling point
- Activity stream integration makes the directory feel native to the community

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Community member | See my listings on my BuddyPress profile | Visitors to my profile can see my businesses |
| 2 | Community member | See my reviews on my profile | My contribution to the directory is visible |
| 3 | Community member | See "John added a new listing: Pizza Palace" in the activity feed | The community stays engaged with directory activity |
| 4 | Member directory browser | See how many listings each member has | I can identify active contributors |
| 5 | Listing owner | Receive BP notifications when someone reviews my listing | I get notified through the same system as friend requests and messages |
| 6 | Community admin | Have listing avatars link to BP profiles | The directory and community feel like one product |

---

## Technical Design

### BuddyPress Component Registration

```php
class Listora_BP_Component extends BP_Component {
    public function __construct() {
        parent::start(
            'listora',
            __('Directory', 'wb-listora-pro'),
            WB_LISTORA_PRO_PATH . 'includes/bp/',
            ['adminbar_myaccount_order' => 60]
        );
    }

    public function setup_globals( $args = [] ) {
        parent::setup_globals([
            'slug'                  => 'listings',
            'has_directory'         => false,
            'notification_callback' => 'listora_bp_format_notification',
        ]);
    }

    public function setup_nav( $main_nav = [], $sub_nav = [] ) {
        $count = listora_get_user_listing_count(bp_displayed_user_id());

        $main_nav = [
            'name'                => sprintf(__('Listings %s', 'wb-listora-pro'),
                                      '<span class="count">' . $count . '</span>'),
            'slug'                => 'listings',
            'position'            => 60,
            'screen_function'     => 'listora_bp_listings_screen',
            'default_subnav_slug' => 'my-listings',
        ];

        $sub_nav[] = [
            'name'            => __('My Listings', 'wb-listora-pro'),
            'slug'            => 'my-listings',
            'parent_slug'     => 'listings',
            'screen_function' => 'listora_bp_listings_screen',
            'position'        => 10,
        ];

        $sub_nav[] = [
            'name'            => __('My Reviews', 'wb-listora-pro'),
            'slug'            => 'my-reviews',
            'parent_slug'     => 'listings',
            'screen_function' => 'listora_bp_reviews_screen',
            'position'        => 20,
        ];

        parent::setup_nav($main_nav, $sub_nav);
    }
}

// Register component only if BP is active
add_action('bp_loaded', function() {
    buddypress()->listora = new Listora_BP_Component();
});
```

### Features

| Feature | Implementation |
|---------|---------------|
| Profile tab: "My Listings" | BP_Component nav, shows listing cards owned by displayed user |
| Profile tab: "My Reviews" | Sub-nav, shows reviews written by displayed user with ratings |
| Activity stream: listing published | `bp_activity_add()` on `wb_listora_listing_approved` |
| Activity stream: review posted | `bp_activity_add()` on `wb_listora_review_approved` |
| Activity stream: listing claimed | `bp_activity_add()` on `wb_listora_claim_approved` |
| BP notifications: new review | `bp_notifications_add_notification()` on `wb_listora_review_approved` |
| BP notifications: claim approved | `bp_notifications_add_notification()` on `wb_listora_claim_approved` |
| BP notifications: listing approved | `bp_notifications_add_notification()` on `wb_listora_listing_approved` |
| Member directory: listing count | Action hook `bp_directory_members_item`, show count badge |
| Member directory: "has listings" filter | Filter members by whether they have published listings |
| Avatar linking | `wb_listora_author_url` filter returns `bp_core_get_user_domain()` |
| Zero overhead guard | `function_exists('buddypress')` check before loading any BP code |

### Activity Stream Examples

```php
// Listing published
bp_activity_add([
    'user_id'           => $listing->post_author,
    'component'         => 'listora',
    'type'              => 'listing_published',
    'primary_link'      => get_permalink($listing_id),
    'action'            => sprintf(
        __('%s added a new %s listing: %s', 'wb-listora-pro'),
        bp_core_get_userlink($listing->post_author),
        $type_label,
        '<a href="' . get_permalink($listing_id) . '">' . esc_html($listing->post_title) . '</a>'
    ),
    'content'           => wp_trim_words($listing->post_content, 30),
    'secondary_item_id' => $listing_id,
]);

// Review posted
bp_activity_add([
    'user_id'           => $review->user_id,
    'component'         => 'listora',
    'type'              => 'review_posted',
    'primary_link'      => get_permalink($review->listing_id) . '#review-' . $review_id,
    'action'            => sprintf(
        __('%s reviewed %s: %s stars', 'wb-listora-pro'),
        bp_core_get_userlink($review->user_id),
        '<a href="' . get_permalink($review->listing_id) . '">' . esc_html($listing->post_title) . '</a>',
        $review->overall_rating
    ),
    'content'           => wp_trim_words($review->content, 20),
]);
```

### Notification Formatting

```php
function listora_bp_format_notification( $action, $item_id, $secondary_item_id, $total_items, $format = 'string' ) {
    switch ($action) {
        case 'new_review':
            $review  = get_listora_review($item_id);
            $listing = get_post($secondary_item_id);
            $text    = sprintf(__('New %d-star review on %s', 'wb-listora-pro'),
                              $review->overall_rating, $listing->post_title);
            $link    = get_permalink($secondary_item_id) . '#review-' . $item_id;
            break;

        case 'listing_approved':
            $listing = get_post($item_id);
            $text    = sprintf(__('Your listing "%s" has been approved', 'wb-listora-pro'),
                              $listing->post_title);
            $link    = get_permalink($item_id);
            break;

        case 'claim_approved':
            $listing = get_post($secondary_item_id);
            $text    = sprintf(__('Your claim on "%s" was approved', 'wb-listora-pro'),
                              $listing->post_title);
            $link    = get_permalink($secondary_item_id);
            break;

        default:
            return false;
    }

    return ($format === 'object')
        ? ['text' => $text, 'link' => $link]
        : '<a href="' . esc_url($link) . '">' . esc_html($text) . '</a>';
}
```

### Conditional Loading

```php
add_action('plugins_loaded', function() {
    if (!function_exists('buddypress') && !function_exists('buddyboss_platform_plugin_activate')) {
        return; // BP not active -- skip all BP code
    }

    require_once __DIR__ . '/includes/bp/class-listora-bp-component.php';
    require_once __DIR__ . '/includes/bp/class-bp-activity.php';
    require_once __DIR__ . '/includes/bp/class-bp-notifications.php';
    require_once __DIR__ . '/includes/bp/class-bp-profile-tabs.php';
    require_once __DIR__ . '/includes/bp/class-bp-member-directory.php';
    require_once __DIR__ . '/includes/bp/class-bp-avatar-links.php';
}, 20);
```

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/bp/class-listora-bp-component.php` | BP Component registration |
| `includes/bp/class-bp-activity.php` | Activity stream integration |
| `includes/bp/class-bp-notifications.php` | Notification formatting + triggers |
| `includes/bp/class-bp-profile-tabs.php` | My Listings + My Reviews tabs |
| `includes/bp/class-bp-member-directory.php` | Listing count in member cards |
| `includes/bp/class-bp-avatar-links.php` | Author avatar -> BP profile linking |
| `templates/bp/listings-loop.php` | Profile listings template |
| `templates/bp/reviews-loop.php` | Profile reviews template |

### Files to Modify (wb-listora free)

| File | Change |
|------|--------|
| `blocks/listing-detail/render.php` | Add `wb_listora_author_url` filter |
| `blocks/listing-reviews/render.php` | Add `wb_listora_review_author_url` filter |

---

## UI Mockup

### BP Profile: My Listings Tab

```
+-------------------------------------------------------------+
| [Avatar]  John Smith                                        |
|           @johnsmith  -  New York                           |
|                                                             |
| [Activity] [Profile] [Listings (3)] [Friends] [Messages]    |
|                                                             |
| [My Listings]  [My Reviews]                                 |
|                                                             |
|  +----------+  +----------+  +----------+                  |
|  |          |  |          |  |          |                  |
|  | [Image]  |  | [Image]  |  | [Image]  |                  |
|  |          |  |          |  |          |                  |
|  | Pizza    |  | Burger   |  | Taco     |                  |
|  | Palace   |  | Joint    |  | Shop     |                  |
|  | 4.5 star |  | 4.0 star |  | 3.5 star |                  |
|  | Restaur. |  | Restaur. |  | Restaur. |                  |
|  +----------+  +----------+  +----------+                  |
|                                                             |
| 3 listings                                                  |
+-------------------------------------------------------------+
```

### BP Profile: My Reviews Tab

```
+-------------------------------------------------------------+
| [My Listings]  [My Reviews]                                 |
|                                                             |
| +-----------------------------------------------------------+
| | 5 stars  on  Pizza Palace              March 10, 2026    |
| | "Best Pizza in Manhattan!"                                |
| | The crust is perfectly thin and crispy...                 |
| |                                     Helpful (5)          |
| +-----------------------------------------------------------+
| | 4 stars  on  Sushi House               March 5, 2026     |
| | "Good sushi, great ambiance"                              |
| | Fresh fish and a beautiful interior...                    |
| |                                     Helpful (2)          |
| +-----------------------------------------------------------+
|                                                             |
| 2 reviews                                                   |
+-------------------------------------------------------------+
```

### Activity Stream Entry

```
+-------------------------------------------------------------+
| [Avatar] John Smith added a new Restaurant listing:         |
|          Pizza Palace                                       |
|                                                             |
|          Authentic Neapolitan pizza in the heart of          |
|          Manhattan. Wood-fired oven, fresh ingredients...    |
|                                                             |
|          Like  -  Comment  -  2 hours ago                   |
+-------------------------------------------------------------+
| [Avatar] Sarah Johnson reviewed Pizza Palace: 5 stars       |
|                                                             |
|          Best pizza I've ever had! The crust is...          |
|                                                             |
|          Like  -  Comment  -  5 hours ago                   |
+-------------------------------------------------------------+
```

### Member Directory Card

```
+-----------------------------+-----------------------------+
| [Avatar] John Smith         | [Avatar] Sarah Johnson      |
| @johnsmith                  | @sarahj                     |
| New York, NY                | Brooklyn, NY                |
| 3 listings                  | 1 listing                   |
| [View Profile]              | [View Profile]              |
+-----------------------------+-----------------------------+
```

---

## BuddyBoss Compatibility

| BuddyPress | BuddyBoss | Our Approach |
|-----------|-----------|--------------|
| `BP_Component` | Same (extends BP) | Works natively |
| Activity stream | Same API | Works natively |
| Notifications | Same API | Works natively |
| Profile tabs | Profile Type system | Detect BB, use their nav API if present |
| Member directory | Custom cards | Add listing count via BB action hook |
| Avatars | Same API | Works natively |

Built on BuddyPress APIs (not BB-specific), ensuring compatibility with both.

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | BP Component class registration | 2 |
| 2 | Conditional loading (only when BP/BB active) | 1 |
| 3 | "My Listings" profile tab with listing cards | 4 |
| 4 | "My Reviews" profile tab with review cards | 3 |
| 5 | Activity stream: listing published | 2 |
| 6 | Activity stream: review posted | 2 |
| 7 | Activity stream: listing claimed | 1 |
| 8 | BP notifications: new review received | 2 |
| 9 | BP notifications: listing approved | 1 |
| 10 | BP notifications: claim approved | 1 |
| 11 | Notification formatting function | 1 |
| 12 | Author avatar -> BP profile linking (listings + reviews) | 1 |
| 13 | Member directory: listing count per member | 2 |
| 14 | Member directory: "has listings" filter | 1 |
| 15 | Profile tab templates (listings-loop.php, reviews-loop.php) | 3 |
| 16 | Listing count caching (object cache) | 1 |
| 17 | BuddyBoss compatibility testing + adjustments | 3 |
| 18 | Admin toggle: enable/disable BP integration | 0.5 |
| 19 | Automated tests + documentation | 3 |
| **Total** | | **35.5 hours** |

---

## Competitive Context

| Competitor | BuddyPress Integration? | Our Advantage |
|-----------|------------------------|---------------|
| GeoDirectory | Paid BP addon ($39/year) | Included in Pro, deeper integration |
| Directorist | No BP integration | Full BP component with activity + notifications |
| HivePress | No | Profile tabs, activity stream, member directory |
| ListingPro | No | Native BP notifications |
| MyListing | No | Works with both BP and BuddyBoss |

**Our edge:** Wbcom Designs has deep BuddyPress expertise. Our integration uses BP's proper component system (not hacks), which means it shows up correctly in the admin bar, integrates with notifications, and works with both BuddyPress and BuddyBoss. The combination of profile tabs, activity stream, notifications, and member directory integration makes the directory feel like a native part of the community.

---

## Effort Estimate

**Total: ~35.5 hours (4-5 dev days)**

- BP Component: 3h
- Profile tabs: 7h
- Activity stream: 5h
- Notifications: 5h
- Avatar linking: 1h
- Member directory: 3h
- Templates: 3h
- BuddyBoss compat: 3h
- Caching: 1h
- Tests + docs: 3h
- QA: 1.5h
