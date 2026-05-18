# P2-07 — Audit Log

## Scope: Pro Only

---

## Overview

A comprehensive audit trail recording who did what, when, to which object, and what changed. Every significant action in the directory — from listing approval to settings changes to credit adjustments — is logged with before/after values, user identity, and timestamp. Admins can filter, search, and export the log.

### Why It Matters

- Enterprise directories need accountability — "who approved this listing?"
- Debugging moderation disputes — "the listing owner says it was rejected without reason"
- Compliance requirements — GDPR, SOC2, and audit trails for regulated industries
- Team management — monitor moderator activity and identify patterns
- Change tracking — "what settings were changed last week that broke search?"

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Admin | See who approved a specific listing and when | I have accountability for moderation decisions |
| 2 | Admin | View what settings were changed last week | I can diagnose configuration issues |
| 3 | Admin | See before/after values for listing edits | I know exactly what was changed |
| 4 | Admin | Filter the log by moderator to see their activity | I can review moderator performance |
| 5 | Admin | Export the audit log as CSV | I can share it with compliance or management |
| 6 | Admin | Have old log entries auto-cleaned after 90 days | The database doesn't grow unbounded |
| 7 | Developer | Query audit log via REST API | I can build custom reporting dashboards |

---

## Technical Design

### Database Table

The table already exists in the database schema plan (`06-database.md`). Full definition:

```sql
CREATE TABLE {prefix}listora_audit_log (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    action       VARCHAR(50) NOT NULL,
    object_type  VARCHAR(30) NOT NULL,
    object_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    details      LONGTEXT DEFAULT NULL,
    ip_address   VARCHAR(45) NOT NULL DEFAULT '',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user (user_id),
    KEY idx_action (action),
    KEY idx_object (object_type, object_id),
    KEY idx_created (created_at DESC)
) {charset_collate};
```

### Actions to Log

| Action | Object Type | Details (JSON) |
|--------|------------|----------------|
| `listing_created` | `listing` | `{title, type, author_id}` |
| `listing_updated` | `listing` | `{changed_fields: {field: {before, after}}}` |
| `listing_approved` | `listing` | `{title, approved_by}` |
| `listing_rejected` | `listing` | `{title, rejected_by, reason}` |
| `listing_deleted` | `listing` | `{title, deleted_by}` |
| `listing_expired` | `listing` | `{title, expired_at}` |
| `listing_renewed` | `listing` | `{title, renewed_by, new_expiry}` |
| `review_approved` | `review` | `{listing_id, listing_title, rating, approved_by}` |
| `review_rejected` | `review` | `{listing_id, listing_title, rejected_by, reason}` |
| `review_deleted` | `review` | `{listing_id, listing_title, deleted_by}` |
| `claim_approved` | `claim` | `{listing_id, listing_title, approved_by, claimer_id}` |
| `claim_rejected` | `claim` | `{listing_id, listing_title, rejected_by, reason}` |
| `credits_added` | `user` | `{amount, source, balance_after}` |
| `credits_deducted` | `user` | `{amount, reason, balance_after}` |
| `settings_changed` | `settings` | `{changed: {key: {before, after}}}` |
| `moderator_assigned` | `listing`/`review` | `{moderator_id, moderator_name}` |
| `moderator_reassigned` | `listing`/`review` | `{from_mod, to_mod}` |
| `coupon_created` | `coupon` | `{code, type, value}` |
| `coupon_used` | `coupon` | `{code, user_id, plan_id, discount}` |
| `webhook_created` | `webhook` | `{url, events}` |
| `webhook_deleted` | `webhook` | `{url}` |
| `badge_assigned` | `listing` | `{badge_slug, badge_label, method}` |
| `badge_removed` | `listing` | `{badge_slug, badge_label}` |
| `user_role_changed` | `user` | `{from_role, to_role}` |

### Logger Class

```php
class Audit_Logger {
    /**
     * Log an action.
     *
     * @param string      $action      Action key (e.g., 'listing_approved').
     * @param string      $object_type Object type (e.g., 'listing', 'review').
     * @param int         $object_id   Object ID.
     * @param array|null  $details     Additional context as associative array.
     */
    public function log(
        string $action,
        string $object_type,
        int $object_id = 0,
        ?array $details = null
    ): void {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'listora_audit_log',
            [
                'user_id'     => get_current_user_id(),
                'action'      => $action,
                'object_type' => $object_type,
                'object_id'   => $object_id,
                'details'     => $details ? wp_json_encode($details) : null,
                'ip_address'  => $this->get_ip(),
                'created_at'  => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Track field changes between old and new values.
     */
    public function log_changes(
        string $action,
        string $object_type,
        int $object_id,
        array $old_values,
        array $new_values
    ): void {
        $changes = [];
        foreach ($new_values as $key => $new_val) {
            $old_val = $old_values[$key] ?? null;
            if ($old_val !== $new_val) {
                $changes[$key] = ['before' => $old_val, 'after' => $new_val];
            }
        }

        if (!empty($changes)) {
            $this->log($action, $object_type, $object_id, ['changed_fields' => $changes]);
        }
    }

    private function get_ip(): string {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        // Respect proxy headers only if behind known proxy
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }
        return sanitize_text_field($ip);
    }
}
```

### Hook Integration

```php
// Example: Hook into listing approval
add_action('wb_listora_listing_approved', function(int $listing_id, int $approver_id) {
    $logger = new Audit_Logger();
    $logger->log('listing_approved', 'listing', $listing_id, [
        'title'       => get_the_title($listing_id),
        'approved_by' => get_userdata($approver_id)->display_name,
    ]);
}, 10, 2);

// Example: Hook into settings save with change tracking
add_action('wb_listora_settings_saved', function(array $old, array $new) {
    $logger = new Audit_Logger();
    $logger->log_changes('settings_changed', 'settings', 0, $old, $new);
});
```

### Auto-Cleanup

```php
// Daily cron: purge entries older than retention period
add_action('listora_daily_maintenance', function() {
    global $wpdb;
    $retention_days = (int) get_option('listora_audit_retention_days', 90);

    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}listora_audit_log
         WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
        $retention_days
    ));
});
```

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/audit/class-audit-logger.php` | Core logging class (log, log_changes) |
| `includes/audit/class-audit-hooks.php` | All action hooks → audit log calls |
| `includes/audit/class-audit-cleanup.php` | Daily cron for retention cleanup |
| `includes/rest/class-audit-log-controller.php` | REST endpoint for log queries |
| `includes/admin/class-audit-log-page.php` | Admin log viewer page |

### Files to Modify (wb-listora free)

| File | Change |
|------|--------|
| `includes/db/class-migrator.php` | Add `listora_audit_log` table creation to migration |

### API Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `GET` | `/listora/v1/audit-log` | Admin | Query log with filters |
| `GET` | `/listora/v1/audit-log/export` | Admin | Export as CSV download |
| `GET` | `/listora/v1/audit-log/stats` | Admin | Aggregate stats (actions per day) |

#### Query Parameters

```
GET /listora/v1/audit-log?
    user_id=5                   # Filter by actor
    action=listing_approved     # Filter by action type
    object_type=listing         # Filter by object type
    object_id=123               # Filter by specific object
    date_from=2026-03-01        # Date range start
    date_to=2026-04-01          # Date range end
    per_page=50                 # Pagination
    page=1
    orderby=created_at          # Sort field
    order=desc                  # Sort direction
```

---

## UI Mockup

### Admin: Audit Log Page (Listora > Audit Log)

```
┌─────────────────────────────────────────────────────────────┐
│ Audit Log                                     [Export CSV]  │
│                                                             │
│ ── Filters ──────────────────────────────────────────────── │
│ User: [ All Users        ▾ ]  Action: [ All Actions    ▾ ] │
│ Type: [ All Types        ▾ ]  From: [2026-03-01]           │
│                               To:   [2026-04-05]           │
│                                              [Apply Filter] │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ 14:30  listing_approved           Sarah J.             │ │
│ │        Pizza Palace (#123)                              │ │
│ │        → Listing approved and published                 │ │
│ │                                          [View Details] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ 14:15  listing_updated            John Smith            │ │
│ │        Pizza Palace (#123)                              │ │
│ │        → Changed: phone, hours                          │ │
│ │                                          [View Details] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ 13:50  review_approved            Mike Chen             │ │
│ │        Review on Pizza Palace (#456)                    │ │
│ │        → 5-star review approved                         │ │
│ │                                          [View Details] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ 13:22  settings_changed           Admin                 │ │
│ │        Settings                                         │ │
│ │        → Changed: map_provider, default_zoom            │ │
│ │                                          [View Details] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ 12:00  credits_added              System (webhook)      │ │
│ │        User: Jane Doe (#7)                              │ │
│ │        → +25 credits via Stripe (balance: 50)           │ │
│ │                                          [View Details] │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ ◄ 1 2 3 4 5 ... 28 ►       Showing 1-50 of 1,384 entries  │
│                                                             │
│ Retention: 90 days  ·  Auto-cleanup: daily                  │
└─────────────────────────────────────────────────────────────┘
```

### Detail Expansion (Settings Change)

```
┌─────────────────────────────────────────────────────────────┐
│ 13:22  settings_changed           Admin                     │
│        Settings                                             │
│                                                             │
│ Changed fields:                                             │
│ ┌──────────────┬──────────────┬──────────────┐              │
│ │ Field        │ Before       │ After        │              │
│ ├──────────────┼──────────────┼──────────────┤              │
│ │ map_provider │ osm          │ google       │              │
│ │ default_zoom │ 12           │ 14           │              │
│ └──────────────┴──────────────┴──────────────┘              │
│                                                             │
│ IP: 192.168.1.100                                           │
└─────────────────────────────────────────────────────────────┘
```

### Detail Expansion (Listing Update)

```
┌─────────────────────────────────────────────────────────────┐
│ 14:15  listing_updated            John Smith                │
│        Pizza Palace (#123)                                  │
│                                                             │
│ Changed fields:                                             │
│ ┌──────────┬──────────────────┬──────────────────┐          │
│ │ Field    │ Before           │ After            │          │
│ ├──────────┼──────────────────┼──────────────────┤          │
│ │ phone    │ +1-555-0100      │ +1-555-0123      │          │
│ │ hours    │ 10 AM - 9 PM     │ 11 AM - 10 PM    │          │
│ └──────────┴──────────────────┴──────────────────┘          │
│                                                             │
│ IP: 73.45.123.89                                            │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Create `listora_audit_log` table + migration | 1 |
| 2 | Build `Audit_Logger` class (log, log_changes, get_ip) | 3 |
| 3 | Build `Audit_Hooks` — wire all 24 actions to logger | 5 |
| 4 | Settings change tracking (before/after on save) | 2 |
| 5 | Listing field change tracking (diff old vs new meta) | 3 |
| 6 | Daily cleanup cron (configurable retention period) | 1 |
| 7 | REST endpoint — query with filters, pagination, sorting | 4 |
| 8 | CSV export endpoint | 2 |
| 9 | Admin log viewer page with filters | 5 |
| 10 | Expandable detail rows (before/after table) | 3 |
| 11 | Admin setting: retention period (30/60/90/180/365 days) | 0.5 |
| 12 | User display (avatar + name + link to profile) | 1 |
| 13 | Object links (click listing title to go to listing) | 1 |
| 14 | Action label localization (human-readable action names) | 1 |
| 15 | Automated tests + documentation | 3 |
| **Total** | | **35.5 hours** |

---

## Performance Considerations

- **Write performance:** `INSERT` only, no locks on other tables
- **Read performance:** Indexed on `user_id`, `action`, `object_type + object_id`, `created_at`
- **Size management:** 90-day default retention. At 100 events/day = ~9,000 rows. Negligible disk usage
- **No blocking:** Logging never blocks the user action. If insert fails, silently continue
- **Bulk operations:** Batch delete during cleanup (`DELETE WHERE created_at < X LIMIT 1000` in loop)

---

## Competitive Context

| Competitor | Audit Log? | Our Advantage |
|-----------|-----------|---------------|
| GeoDirectory | No | Full audit trail with before/after diffs |
| Directorist | No | 24 tracked actions, filterable viewer |
| HivePress | No | CSV export, auto-cleanup, REST API |
| ListingPro | No | Settings change tracking with field diffs |
| MyListing | No | Moderator activity tracking |
| WordPress (core) | Basic post revisions only | Directory-specific actions, credit/payment tracking |

**Our edge:** No major directory plugin has a built-in audit log. WordPress core tracks post revisions but not directory-specific actions like claim approvals, credit adjustments, or settings changes. Our audit log covers the full lifecycle of every directory object with before/after diffs, making it the first enterprise-grade audit trail in the directory plugin space.

---

## Effort Estimate

**Total: ~35.5 hours (4-5 dev days)**

- Core logger: 3h
- Hook wiring (24 actions): 5h
- Change tracking (diffs): 5h
- Cleanup cron: 1h
- REST API + export: 6h
- Admin UI: 9h
- Localization + UX: 2.5h
- Tests + docs: 3h
- QA: 1h
