# P2-06 — Moderator Role + Assignment

## Scope: Pro Only

---

## Overview

Creates a dedicated "Listora Moderator" WordPress role with fine-grained capabilities for managing directory content. Includes workload balancing via round-robin assignment of new submissions, reviews, and claims to moderators. Moderators see a filtered dashboard showing only their assigned items, and admins can track moderator performance.

### Why It Matters

- Large directories (100+ submissions/week) need multiple moderators
- WordPress's built-in roles don't cover directory-specific capabilities
- Without workload balancing, one moderator ends up doing everything
- Agencies managing directories for clients need non-admin users who can moderate
- Assignment tracking enables accountability — who approved what, when

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Admin | Assign the "Listora Moderator" role to team members | They can moderate listings/reviews without full admin access |
| 2 | Admin | Have new submissions auto-assigned to moderators round-robin | Work is distributed fairly across the team |
| 3 | Moderator | See only items assigned to me on my dashboard | I know exactly what I need to review |
| 4 | Moderator | Approve/reject listings and reviews | I can moderate content within my assigned scope |
| 5 | Admin | See how many items each moderator has processed | I can identify bottlenecks and redistribute work |
| 6 | Admin | Bulk reassign items from one moderator to another | I can handle moderator absences or departures |
| 7 | Admin | Add moderator capabilities to existing roles | Our editors can also moderate listings without a role change |

---

## Technical Design

### Custom Role

```php
add_role('listora_moderator', __('Listora Moderator', 'wb-listora-pro'), [
    // WordPress core caps (read-only base)
    'read'                      => true,
    'upload_files'              => true,

    // Listora moderator caps
    'moderate_listora_listings' => true,  // approve/reject pending listings
    'edit_listora_listings'     => true,  // edit listing content (not delete)
    'moderate_listora_reviews'  => true,  // approve/reject/spam reviews
    'manage_listora_claims'     => true,  // approve/reject claim requests
    'view_listora_reports'      => true,  // view flagged reviews/content
    'view_listora_analytics'    => false, // (optional, admin can grant)
    'manage_listora_settings'   => false, // never — admin only
]);
```

### Capability Map

| Capability | Description | Moderator | Admin |
|-----------|-------------|:---------:|:-----:|
| `moderate_listora_listings` | Approve, reject, request changes on pending listings | Yes | Yes |
| `edit_listora_listings` | Edit listing title, content, meta fields | Yes | Yes |
| `delete_listora_listings` | Permanently delete listings | No | Yes |
| `moderate_listora_reviews` | Approve, reject, spam reviews | Yes | Yes |
| `manage_listora_claims` | Approve, reject, revoke claims | Yes | Yes |
| `view_listora_reports` | View reported/flagged content | Yes | Yes |
| `view_listora_analytics` | View site-wide analytics | No* | Yes |
| `manage_listora_settings` | Change plugin settings | No | Yes |
| `manage_listora_moderators` | Assign/remove moderator role, bulk reassign | No | Yes |
| `manage_listora_coupons` | Create/edit coupons | No | Yes |
| `manage_listora_webhooks` | Manage webhooks | No | Yes |
| `manage_listora_badges` | Manage badges | No* | Yes |

*Admins can optionally grant these to moderators.

### Round-Robin Assignment

```php
class Moderator_Assigner {
    /**
     * Assign a new item to the next moderator in rotation.
     */
    public function assign_next( int $object_id, string $object_type ): int {
        $moderators = $this->get_active_moderators();
        if (empty($moderators)) return 0;

        // Get last assigned moderator index
        $last_index = (int) get_option('listora_last_moderator_index', -1);
        $next_index = ($last_index + 1) % count($moderators);

        $moderator_id = $moderators[$next_index];

        // Store assignment
        update_post_meta($object_id, '_listora_assigned_moderator', $moderator_id);
        update_post_meta($object_id, '_listora_assigned_at', current_time('mysql'));
        update_option('listora_last_moderator_index', $next_index);

        // Fire action for notification
        do_action('wb_listora_moderator_assigned', $moderator_id, $object_id, $object_type);

        // Log to audit log
        do_action('wb_listora_audit_log', 'moderator_assigned', $object_type, $object_id, [
            'moderator_id'   => $moderator_id,
            'moderator_name' => get_userdata($moderator_id)->display_name,
        ]);

        return $moderator_id;
    }

    /**
     * Get all users with moderator capabilities, sorted by current workload (ascending).
     */
    private function get_active_moderators(): array {
        $users = get_users([
            'role__in'    => ['listora_moderator', 'administrator'],
            'meta_key'    => '_listora_moderator_active',
            'meta_value'  => '1',
            'orderby'     => 'ID',
            'order'       => 'ASC',
        ]);

        return array_map(fn($u) => $u->ID, $users);
    }
}
```

### Assignment Hooks

| Event | Hook | Action |
|-------|------|--------|
| New listing submitted | `wb_listora_listing_submitted` | Assign to next moderator |
| New review posted | `wb_listora_review_submitted` | Assign to next moderator |
| New claim filed | `wb_listora_claim_submitted` | Assign to next moderator |
| Report filed | `wb_listora_review_reported` | Assign to next moderator |

### Moderator Meta

```
User meta:
_listora_moderator_active        -> "1" (eligible for assignment)
_listora_moderator_started       -> "2026-01-15" (date role granted)
_listora_moderator_items_total   -> 156 (lifetime items processed)
_listora_moderator_items_month   -> 23 (current month)
```

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/moderation/class-moderator-role.php` | Role + capability registration |
| `includes/moderation/class-moderator-assigner.php` | Round-robin assignment engine |
| `includes/moderation/class-moderator-dashboard.php` | Filtered admin views for moderators |
| `includes/moderation/class-moderator-notifier.php` | Email notifications on assignment |
| `includes/moderation/class-moderator-stats.php` | Performance metrics |
| `includes/admin/class-moderators-page.php` | Admin moderator management page |
| `includes/rest/class-moderators-controller.php` | REST endpoints for moderator management |

### Files to Modify (wb-listora-pro)

| File | Change |
|------|--------|
| `includes/rest/class-submission-controller.php` (Pro hook) | Trigger moderator assignment on submission |
| `includes/rest/class-reviews-controller.php` (Pro hook) | Trigger assignment on new review |
| `includes/rest/class-claims-controller.php` (Pro hook) | Trigger assignment on new claim |

### API Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `GET` | `/listora/v1/moderators` | Admin | List moderators with stats |
| `POST` | `/listora/v1/moderators/{user_id}/activate` | Admin | Make user an active moderator |
| `POST` | `/listora/v1/moderators/{user_id}/deactivate` | Admin | Remove from rotation |
| `GET` | `/listora/v1/moderators/{user_id}/queue` | Moderator | Get assigned items |
| `POST` | `/listora/v1/moderators/reassign` | Admin | Bulk reassign items |
| `GET` | `/listora/v1/moderators/stats` | Admin | Performance metrics |

### Notification: Assignment Email

```
Subject: New listing assigned to you: Pizza Palace

Hi Sarah,

A new listing has been assigned to you for review:

  Title: Pizza Palace
  Type: Restaurant
  Submitted by: John Smith
  Submitted at: April 5, 2026 2:30 PM

Review this listing:
https://site.com/wp-admin/post.php?post=123&action=edit

Your current queue: 5 pending items

Thanks,
{site_name}
```

---

## UI Mockup

### Admin: Moderator Management (Listora > Moderators)

```
┌─────────────────────────────────────────────────────────────┐
│ Moderators                              [+ Add Moderator]   │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ ● Sarah Johnson (sarah@example.com)                     │ │
│ │   Role: Listora Moderator                               │ │
│ │   Queue: 5 pending  ·  Processed: 23 this month         │ │
│ │   Avg response: 2.4 hours                               │ │
│ │                          [View Queue] [Deactivate] [X]  │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ● Mike Chen (mike@example.com)                          │ │
│ │   Role: Administrator (+ moderator caps)                │ │
│ │   Queue: 3 pending  ·  Processed: 31 this month         │ │
│ │   Avg response: 1.8 hours                               │ │
│ │                          [View Queue] [Deactivate] [X]  │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ○ Alex Rivera (alex@example.com)          INACTIVE      │ │
│ │   Role: Listora Moderator                               │ │
│ │   Queue: 0 pending  ·  On leave since Apr 1             │ │
│ │                            [View Queue] [Activate] [X]  │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ Assignment Mode: (●) Round-Robin  ( ) Least Loaded          │
│                                                             │
│ ── Bulk Actions ──────────────────────────────────────────── │
│ Reassign all items from [ Alex Rivera ▾ ] to [ Sarah ▾ ]    │
│                                                [Reassign]   │
│                                                             │
│ ── This Month ────────────────────────────────────────────── │
│ Total items processed: 54                                   │
│ Average response time: 2.1 hours                            │
│ Items in queue: 8                                           │
└─────────────────────────────────────────────────────────────┘
```

### Admin: Add Moderator

```
┌─────────────────────────────────────────────────────────────┐
│ Add Moderator                                               │
│                                                             │
│ Select User                                                 │
│ [ Search users...                              ▾ ]          │
│                                                             │
│ Or Create New User                                          │
│ Username: [ moderator1   ]                                  │
│ Email:    [ mod@site.com ]                                  │
│                                                             │
│ ── Capabilities ──────────────────────────────────────────── │
│                                                             │
│ ☑ Moderate listings (approve/reject)                       │
│ ☑ Edit listings                                            │
│ ☑ Moderate reviews                                         │
│ ☑ Manage claims                                            │
│ ☑ View reports                                             │
│ ☐ View analytics                                           │
│ ☐ Manage badges                                            │
│                                                             │
│ ☑ Receive assignment email notifications                   │
│                                                             │
│                                        [Cancel]  [Add]      │
└─────────────────────────────────────────────────────────────┘
```

### Moderator: Filtered Dashboard

```
┌─────────────────────────────────────────────────────────────┐
│ My Queue                                         Sarah J.   │
│                                                             │
│ [Pending Listings (3)] [Pending Reviews (2)] [Claims (0)]   │
│                                                             │
│ ── Pending Listings ──────────────────────────────────────── │
│                                                             │
│ | Listing          | Type       | By          | Submitted  │
│ |------------------|------------|-------------|------------│
│ | Pizza Palace     | Restaurant | John Smith  | 2h ago     │
│ | Hair Studio NYC  | Salon      | Jane Doe    | 5h ago     │
│ | Brooklyn Yoga    | Fitness    | Mike Lee    | 1 day ago  │
│                                                             │
│ [Click listing to review → Approve / Reject / Request Edit] │
│                                                             │
│ ── Stats ──────────────────────────────────────────────────  │
│ Items processed this month: 23                              │
│ Average response time: 2.4 hours                            │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Register `listora_moderator` role + all capabilities | 2 |
| 2 | Capability check integration across all admin pages | 3 |
| 3 | Build `Moderator_Assigner` — round-robin engine | 3 |
| 4 | Hook assignment into submission, review, claim workflows | 2 |
| 5 | `_listora_assigned_moderator` meta on listings/reviews/claims | 1 |
| 6 | Moderator dashboard — filtered views showing assigned items only | 4 |
| 7 | Admin moderator management page (Pattern B) | 4 |
| 8 | Add/remove moderator flow (user search, capability toggles) | 3 |
| 9 | Bulk reassign functionality | 2 |
| 10 | Assignment notification email template | 2 |
| 11 | Performance metrics tracking (items processed, response time) | 3 |
| 12 | Stats display on admin page | 2 |
| 13 | REST endpoints for moderator management | 3 |
| 14 | Capability editor — grant individual caps to existing roles | 2 |
| 15 | Cleanup on role removal (reassign orphaned items) | 1 |
| 16 | Audit log integration (log assignment events) | 1 |
| 17 | Automated tests + documentation | 3 |
| **Total** | | **41 hours** |

---

## Competitive Context

| Competitor | Moderator System? | Our Advantage |
|-----------|-------------------|---------------|
| GeoDirectory | No moderator role | Full role + round-robin assignment |
| Directorist | Basic moderator role | Workload balancing, performance metrics |
| HivePress | No | Dedicated moderator dashboard |
| ListingPro | No | Assignment tracking + bulk reassign |
| MyListing | No | Fine-grained capabilities per moderator |

**Our edge:** Round-robin assignment is unique in the directory plugin space. Most competitors either have no moderator concept or provide a basic role without workload management. Our system includes performance tracking (items processed, response time), bulk reassignment for handling absences, and fine-grained capability control. The moderator-specific dashboard that shows only assigned items eliminates the noise of seeing everything.

---

## Effort Estimate

**Total: ~41 hours (5-6 dev days)**

- Role + capabilities: 5h
- Round-robin assigner: 5h
- Assignment hooks: 2h
- Moderator dashboard: 4h
- Admin management page: 6h
- Bulk reassign: 2h
- Notifications: 2h
- Performance metrics: 5h
- REST API: 3h
- Capability editor: 2h
- Tests + docs: 4h
- QA: 1h
