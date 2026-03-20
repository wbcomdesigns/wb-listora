# 16 — Email Notifications

## Scope

| | Free | Pro |
|---|---|---|
| All notification events | Yes | Yes |
| HTML email templates | Yes | Yes |
| Toggle per notification | Yes | Yes |
| Template customization (text) | Yes | Yes + visual editor |
| Webhook notifications | — | Yes |

---

## Notification Events

| Event | Recipient | Email Subject (default) |
|-------|-----------|------------------------|
| `listing_submitted` | Admin | New listing submitted: {title} |
| `listing_approved` | Author | Your listing has been approved: {title} |
| `listing_rejected` | Author | Your listing needs changes: {title} |
| `listing_expiring_7d` | Author | Your listing expires in 7 days: {title} |
| `listing_expiring_1d` | Author | Your listing expires tomorrow: {title} |
| `listing_expired` | Author | Your listing has expired: {title} |
| `listing_renewed` | Admin | Listing renewed: {title} |
| `review_received` | Listing Author | New review on {title} |
| `review_reply` | Reviewer | Owner replied to your review on {title} |
| `claim_submitted` | Admin | New claim request for: {title} |
| `claim_approved` | Claimant | Your claim has been approved: {title} |
| `claim_rejected` | Claimant | Your claim was not approved: {title} |
| `payment_received` (Pro) | Admin | Payment received for: {title} |
| `payment_receipt` (Pro) | Author | Payment confirmation |

---

## Email Template System

### Template Structure
```
templates/emails/
├── header.php           # Common header (logo, site name)
├── footer.php           # Common footer (unsubscribe, site URL)
├── listing-submitted.php
├── listing-approved.php
├── listing-rejected.php
├── listing-expiring.php
├── listing-expired.php
├── review-received.php
├── review-reply.php
├── claim-submitted.php
├── claim-approved.php
└── claim-rejected.php
```

### Template Variables
Available in all templates:
```
{site_name}         → Site title
{site_url}          → Site URL
{listing_title}     → Listing title
{listing_url}       → Listing permalink
{listing_type}      → Listing type name
{listing_edit_url}  → Frontend edit URL
{author_name}       → Listing author display name
{author_email}      → Listing author email
{admin_url}         → WP admin URL
{dashboard_url}     → Frontend dashboard URL
{reviewer_name}     → Reviewer name (review events)
{review_rating}     → Star rating (review events)
{review_content}    → Review text (review events)
{rejection_reason}  → Reason text (rejection event)
{expiry_date}       → Expiration date (expiry events)
{renew_url}         → Renewal URL (expiry events)
{claim_url}         → Claim review URL (admin, claim events)
```

### HTML Email Design
```html
<!-- Minimal, clean, works in all email clients -->
<div style="max-width: 600px; margin: 0 auto; font-family: -apple-system, Arial, sans-serif;">
  <!-- Header -->
  <div style="padding: 20px; text-align: center; background: #f7f7f7;">
    <h2>{site_name}</h2>
  </div>

  <!-- Body -->
  <div style="padding: 30px 20px;">
    <p>Hi {author_name},</p>
    <p>Your listing <strong>{listing_title}</strong> has been approved!</p>
    <p>
      <a href="{listing_url}" style="
        display: inline-block;
        padding: 12px 24px;
        background: #0073aa;
        color: #fff;
        text-decoration: none;
        border-radius: 4px;
      ">View Your Listing</a>
    </p>
  </div>

  <!-- Footer -->
  <div style="padding: 20px; text-align: center; font-size: 12px; color: #999;">
    <p>This email was sent by {site_name}</p>
    <p><a href="{dashboard_url}">Manage your notifications</a></p>
  </div>
</div>
```

### Admin Settings
```
Notifications tab in Settings:

☑ New listing submitted (to admin)
☑ Listing approved (to author)
☑ Listing rejected (to author)
☑ Expiration reminders (to author)
☑ New review received (to listing author)
☑ Review reply (to reviewer)
☑ Claim notifications (to admin + claimant)

Admin notification email: [ admin@site.com ]
```

### Hooks
```php
// Customize recipients
apply_filters('wb_listora_notification_recipients', $recipients, $event, $data);

// Customize email content
apply_filters('wb_listora_email_subject', $subject, $event, $data);
apply_filters('wb_listora_email_content', $content, $event, $data);
apply_filters('wb_listora_email_headers', $headers, $event, $data);

// Disable specific notification
apply_filters('wb_listora_send_notification', true, $event, $data);
```

### Theme Override
Themes can override email templates by copying to:
```
theme/wb-listora/emails/listing-approved.php
```
