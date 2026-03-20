# 36 — Status Lifecycle & Moderation Workflow

## Scope

| | Free | Pro |
|---|---|---|
| All listing statuses | Yes | Yes |
| Status transitions | Yes | Yes |
| Expiration cron | Yes | Yes |
| Date-based expiration (events) | Yes | Yes |
| Moderation queue | Yes | Yes + assignment |
| Rejection reasons | Yes | Yes |
| Notification digests | — | Yes |
| Moderator role | — | Yes |
| Audit log | — | Yes |

---

## Listing Statuses

| Status | WP Status | Visible in Search | Editable by Owner | Description |
|--------|-----------|:-:|:-:|-------------|
| Draft | `draft` | No | Yes | Saved but not submitted |
| Pending Review | `pending` | No | Yes (limited) | Submitted, awaiting approval |
| Published | `publish` | Yes | Yes | Live and visible |
| Rejected | `listora_rejected` | No | Yes | Admin rejected with reason |
| Expired | `listora_expired` | No | Yes | Past expiration date |
| Deactivated | `listora_deactivated` | No | Yes | Owner-initiated unpublish |
| Pending Payment | `listora_payment` | No | No | Awaiting payment (Pro) |

### Custom Statuses Registration
```php
register_post_status('listora_rejected', [
    'label'                     => __('Rejected', 'wb-listora'),
    'public'                    => false,
    'exclude_from_search'       => true,
    'show_in_admin_all_list'    => true,
    'show_in_admin_status_list' => true,
    'label_count'               => _n_noop('Rejected (%s)', 'Rejected (%s)', 'wb-listora'),
]);
// Same pattern for listora_expired, listora_deactivated, listora_payment
```

---

## Status Transition Map

```
                                    ┌──────────────┐
                                    │   Draft      │
                                    └──────┬───────┘
                                           │ Submit
                                           ▼
                              ┌────────────────────────┐
                              │   Pending Payment (Pro)│──── Payment ────┐
                              └────────────────────────┘                 │
                                           │ (Free or paid)              │
                                           ▼                             ▼
                              ┌────────────────────────┐    ┌───────────────┐
                    ┌─────────│    Pending Review       │    │  Published    │
                    │         └────────────┬────────────┘    └───────┬───────┘
                    │                      │                         │
              Reject│               Approve│                        │ Expiration
                    │                      │                        │ date passed
                    ▼                      ▼                        ▼
           ┌──────────────┐     ┌───────────────┐        ┌───────────────┐
           │  Rejected    │     │  Published    │        │   Expired     │
           └──────┬───────┘     └───────┬───────┘        └───────┬───────┘
                  │                     │                         │
            Re-submit                   │ Owner                   │ Renew
                  │                     │ deactivates             │ (or pay)
                  ▼                     ▼                         ▼
           ┌──────────────┐     ┌───────────────┐        ┌───────────────┐
           │Pending Review│     │ Deactivated   │        │  Published    │
           └──────────────┘     └───────────────┘        └───────────────┘
                                        │
                                  Reactivate
                                        │
                                        ▼
                                ┌───────────────┐
                                │  Published    │
                                └───────────────┘
```

### Auto-Approve Mode
When Settings → Moderation = "Auto-approve":
- `draft` → `publish` (skip pending)
- No admin review needed
- Still goes through payment step (Pro) if plans are configured

---

## Expiration System

### Time-Based Expiration (Default)
```
_listora_expiration_date  → DATETIME (calculated on publish: now + type's expiration_days)
_listora_expiration_days  → INT (from listing type config, or plan duration)
```

When a listing is published:
```php
$days = $plan ? $plan->duration_days : $type->expiration_days;
if ($days > 0) {
    update_post_meta($id, '_listora_expiration_date', date('Y-m-d H:i:s', strtotime("+{$days} days")));
}
```

### Date-Based Expiration (Events)
For Event listing type, expiration is driven by the event's end date, NOT creation date:
```php
if ($type->slug === 'event' || $type->has_end_date_field()) {
    $end_date = get_post_meta($id, '_listora_end_date', true);
    if ($end_date) {
        // Expire 24 hours after event ends (configurable)
        $grace = apply_filters('wb_listora_event_expiry_grace', DAY_IN_SECONDS, $id);
        update_post_meta($id, '_listora_expiration_date', date('Y-m-d H:i:s', strtotime($end_date) + $grace));
    }
}
```

**This means:** An event on Dec 25 created on Dec 1 expires on Dec 26, NOT on Dec 1 + expiration_days.

### Job Expiration
Job listings also support date-based expiration via `_listora_deadline` field:
```php
if ($type->slug === 'job') {
    $deadline = get_post_meta($id, '_listora_deadline', true);
    if ($deadline) {
        update_post_meta($id, '_listora_expiration_date', $deadline);
    }
}
```

### Expiration Cron Job

**Hook:** `wb_listora_check_expirations`
**Schedule:** Twice daily (`twicedaily`)

```php
function check_expirations() {
    // 1. Find listings expiring soon (7 days)
    $expiring_soon = get_posts([
        'post_type'   => 'listora_listing',
        'post_status' => 'publish',
        'meta_query'  => [
            ['key' => '_listora_expiration_date', 'value' => [now(), now()+7days], 'compare' => 'BETWEEN', 'type' => 'DATETIME'],
            ['key' => '_listora_expiry_warned', 'compare' => 'NOT EXISTS'],
        ],
    ]);
    foreach ($expiring_soon as $post) {
        do_action('wb_listora_listing_expiring', $post->ID, 7);
        update_post_meta($post->ID, '_listora_expiry_warned', '7d');
    }

    // 2. Find listings expiring in 1 day
    // Similar, with '_listora_expiry_warned' != '1d'

    // 3. Find expired listings
    $expired = get_posts([
        'post_type'   => 'listora_listing',
        'post_status' => 'publish',
        'meta_query'  => [
            ['key' => '_listora_expiration_date', 'value' => now(), 'compare' => '<=', 'type' => 'DATETIME'],
        ],
    ]);
    foreach ($expired as $post) {
        wp_update_post(['ID' => $post->ID, 'post_status' => 'listora_expired']);
        do_action('wb_listora_listing_expired', $post->ID);
    }
}
```

### Expired Listing Behavior
| Aspect | Behavior |
|--------|----------|
| Search results | Hidden (status filter) |
| Direct URL | Shows "This listing has expired" message with renewal CTA |
| SEO | Returns 200 with noindex meta (not 404 — preserves URL for renewal) |
| Admin list | Shows in "Expired" filter tab |
| Owner dashboard | Shows with "Renew" button |
| Map | Markers removed |

---

## Moderation Queue

### Admin Page: `Listora → Reviews` (for reviews) + Listing Pending filter

**Listing Moderation:**
Standard WP post list filtered to `pending` status, with added columns:
- Listing type badge
- Submitted by (user)
- Submitted on (date)
- Quick Actions: [Approve] [Reject] [Preview]

**Reject with Reason:**
```
┌─────────────────────────────────────────┐
│ Reject Listing: "Fake Business Name"    │
│                                         │
│ Reason for rejection: *                 │
│ [ Incomplete listing        ▾ ]         │
│                                         │
│ Common reasons:                         │
│ • Incomplete listing information        │
│ • Duplicate of existing listing         │
│ • Inappropriate content                 │
│ • Unverifiable business                 │
│ • Wrong listing category                │
│ • Other (custom)                        │
│                                         │
│ Additional notes to author:             │
│ ┌───────────────────────────────────┐   │
│ │ Please add a valid phone number   │   │
│ │ and business hours.               │   │
│ └───────────────────────────────────┘   │
│                                         │
│ [Cancel]              [Reject Listing]  │
└─────────────────────────────────────────┘
```

Rejection reason stored in `_listora_rejection_reason` post meta.
Author sees the reason in their dashboard and in the rejection email.

---

## Moderator Role (Pro)

### Role Definition
```php
add_role('listora_moderator', 'Listora Moderator', [
    'read'                          => true,
    'edit_listora_listing'          => true,
    'edit_others_listora_listings'  => true,
    'publish_listora_listings'      => true,
    'delete_listora_listing'        => true,
    'moderate_listora_reviews'      => true,
    'manage_listora_claims'         => true,
    // No manage_listora_settings or manage_listora_types
]);
```

### Moderator Assignment (Pro)
Admin can assign listings to specific moderators for review:
- Dropdown on pending listings: "Assign to: [Moderator ▾]"
- Moderators see only their assigned items in queue
- Admin sees everything

### Moderation Analytics (Pro)
```
Moderation Dashboard:
- Pending: 12 listings, 8 reviews
- Avg approval time: 4.2 hours
- Rejection rate: 15%
- Top rejection reasons: Incomplete (45%), Duplicate (30%), Spam (25%)
```

---

## Notification Digests (Pro)

Instead of 100 individual "New listing submitted" emails:

### Daily Digest Email
```
Subject: Daily Directory Update — 12 new submissions, 5 reviews

Hi Admin,

Here's your daily summary for NYC Directory:

New Submissions (12):
• Pizza Palace (Restaurant) by John — [Review →]
• Grand Hotel (Hotel) by Sarah — [Review →]
• ...10 more

New Reviews (5):
• ★★★★★ on Pizza Palace by Mike — [Review →]
• ★★☆☆☆ on Grand Hotel by Lisa — [Review →]
• ...3 more

Expiring Soon (3):
• Old Café expires in 2 days — [View →]

Claims Pending (1):
• Burger Joint claimed by Tom — [Review →]

[Go to Dashboard →]
```

### Settings
```
Notification mode:
( ) Instant (individual emails per event)
(•) Digest (daily summary at [09:00 ▾])
( ) Digest + urgent (digest + instant for spam/reports)
```

---

## Audit Log (Pro)

### What's Logged

| Event | Data Stored |
|-------|-------------|
| Listing created | user, listing_id, type, timestamp |
| Listing status changed | user, listing_id, old_status, new_status, reason |
| Listing edited | user, listing_id, changed_fields |
| Review approved/rejected | user, review_id, listing_id, action |
| Claim approved/rejected | user, claim_id, listing_id, action |
| Settings changed | user, setting_key, old_value, new_value |
| User role changed | admin_user, target_user, old_role, new_role |

### Storage
```sql
CREATE TABLE {prefix}listora_audit_log (
    id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id    BIGINT(20) UNSIGNED NOT NULL,
    action     VARCHAR(50) NOT NULL,
    object_type VARCHAR(30) NOT NULL,
    object_id  BIGINT(20) UNSIGNED DEFAULT NULL,
    details    TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_action (action),
    KEY idx_object (object_type, object_id),
    KEY idx_created (created_at DESC)
) {charset_collate};
```

### Admin View
```
Listora → Activity Log (Pro)

| Time           | User    | Action              | Details                    |
|----------------|---------|---------------------|----------------------------|
| 10:23 AM today | Admin   | listing.approved    | "Pizza Palace" (#123)      |
| 10:15 AM today | John    | listing.submitted   | "Burger Joint" (pending)   |
| 09:45 AM today | Sarah   | review.created      | ★★★★★ on "Grand Hotel"   |
| Yesterday      | Admin   | claim.approved      | "Taco Shop" → Tom          |

Filter: [All Actions ▾] [All Users ▾] [Date Range]
```

---

## Duplicate Detection

### On Submission
Before saving a new listing, check for potential duplicates:
1. Title similarity: Levenshtein distance < 3 OR SOUNDEX match
2. Address proximity: listings within 50m of same address
3. Phone number match: exact match after normalization

### Response
If potential duplicate found:
```
┌─────────────────────────────────────────┐
│ ⚠ Similar listing found                │
│                                         │
│ "Pizza Palace" at 123 Main St           │
│ already exists in our directory.        │
│                                         │
│ Is this the same business?              │
│ [Yes, claim it →]  [No, continue →]    │
└─────────────────────────────────────────┘
```

If user continues → listing flagged for admin review with `_listora_possible_duplicate` meta.

---

## "Coming Soon" / Pre-Launch Mode

### Settings
```
Directory visibility:
(•) Public (anyone can browse)
( ) Coming Soon (shows landing page, admin can still manage)
( ) Private (login required to browse)
```

### Coming Soon Page
When enabled, all directory pages show:
```
┌─────────────────────────────────────────┐
│                                         │
│      Our Directory is Coming Soon!      │
│                                         │
│   We're building the best directory     │
│   for [city]. Be the first to know.     │
│                                         │
│   Email: [              ] [Notify Me]   │
│                                         │
└─────────────────────────────────────────┘
```

Admin sees the real directory (logged-in admin bypass).
Collected emails stored for launch notification.
