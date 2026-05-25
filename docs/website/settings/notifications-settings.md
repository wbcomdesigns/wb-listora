# Notifications Settings

The **Notifications** tab in **Listora → Settings** controls which transactional emails Listora sends, the test-send tool for verifying your SMTP setup, and the retention window for the [Email Log](../features/email-log.md). Every event in the system can be individually toggled.

![Notifications Settings - Send Test Email + Listings + Reviews + Claims groups](../images/settings-notifications.png)

## Where it lives

**WP Admin → Listora → Settings → Notifications** (`?page=listora-settings&tab=notifications`)

Requires the `manage_listora_settings` capability.

## Sections

### Send Test Email

The top block lets you dispatch a sample of any notification template to any address - useful for confirming your site's outgoing email actually delivers before going live.

| Field | What it does |
|---|---|
| **Email type** | Dropdown grouped by category (Listings / Reviews / Claims). Pick which template the test should use. |
| **Recipient** | Defaults to your account email. Override to send to a customer or test inbox. |
| **Send Test Email** button | Fires `wp_mail()` with the chosen template + a test data payload. Inline status appears next to the button on send. |

If the test arrives, every Listora event email will too. If it doesn't, the issue is in your site's mail stack (SMTP plugin, transactional service like SendGrid / Postmark / Mailgun) - not in Listora.

### Listings (8 events)

Emails sent at every step of a listing's lifecycle.

| Event | Sent to | When |
|---|---|---|
| `listing_submitted` | Admin | A new listing is submitted for review |
| `listing_pending_admin` | Admin | A listing enters the moderation queue |
| `listing_approved` | Listing owner | Their listing transitions to `publish` |
| `listing_rejected` | Listing owner | A listing is rejected (with admin feedback) |
| `listing_expired` | Listing owner | A listing expires and is unpublished |
| `listing_expiring_soon` | Listing owner | 7 days and 1 day before expiration |
| `listing_renewed` | Listing owner | A listing is renewed |
| `draft_reminder` | Listing owner | Nudge for listings still in draft 48+ hours (cron-driven) |

### Reviews (3 events)

| Event | Sent to | When |
|---|---|---|
| `review_received` | Listing owner | A new review is left |
| `review_reply` | Reviewer | The listing owner publicly responds |
| `review_helpful` | Reviewer | The review reaches a helpful-vote milestone (1, 5, 10, 25, 50, 100) |

### Claims (3 events)

| Event | Sent to | When |
|---|---|---|
| `claim_submitted` | Admin | A claim is filed on a listing |
| `claim_approved` | Claimant | Their claim is accepted (`post_author` transfers to them) |
| `claim_rejected` | Claimant | Their claim is denied |

## How to use

### Turn an email off

1. Open the tab.
2. Find the event in its group.
3. Uncheck **Enabled**.
4. Click **Save Changes** at the bottom of the page.

The event still fires internally (so [Audit Log](../features/audit-log.md) entries, [Webhooks](../features/outgoing-webhooks.md), and [Digest Notifications](../features/digest-notifications.md) still see the action) - only the per-event transactional email suppresses.

### Customize email content

Per-event subject + body templates live as overrideable theme files. Copy `wp-content/plugins/wb-listora/templates/emails/{event}.php` to `{your-theme}/wb-listora/emails/{event}.php` and edit. See [Email Templates](../features/email-templates.md) for the full template variable list per event.

### Tune Email Log retention

The Email Log page (Listora → Email Log) records every outbound notification attempt with delivery status. The retention dropdown lives at the top of that page (not on this Settings tab) - choose 7, 15, 30, or 0 (lifetime) days. Older entries are pruned automatically by the `wb_listora_email_log_prune` cron.

## Pro: Digest Notifications

When the [Digest Notifications](../features/digest-notifications.md) feature is on, individual emails per event get bundled into one daily / weekly digest per recipient. The per-event toggles on this page still apply - turning an event off here suppresses both the individual email AND the digest entry.

## Programmatic access

```php
// Read every toggle state.
$settings = get_option( 'wb_listora_settings', array() );
$notif = $settings['notifications'] ?? array();
$enabled = ! empty( $notif['review_received'] );

// Or use the helper.
$is_on = wb_listora_notification_enabled( 'listing_approved' );

// Filter per-event default before option lookup.
add_filter( 'wb_listora_notification_default', function ( $default, $event_key ) {
return 'draft_reminder' === $event_key ? false : $default;
}, 10, 2 );
```

## Related

- [Email Log](../features/email-log.md) - recent outbound send activity with delivery status.
- [Email Templates](../features/email-templates.md) - per-event template overrides.
- [Email Verification](../features/email-verification.md) - guest submission verification flow.
- [Digest Notifications (Pro)](../features/digest-notifications.md) - daily / weekly bundled emails.
- [Notifications hooks](../developer-guide/hooks-reference.md) - filter senders, recipients, subjects, bodies.
