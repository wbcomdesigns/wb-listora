---
journey: listing-renewal-flow
plugin: wb-listora
priority: high
roles: [subscriber]
covers: [listing-renewal-rest, expiration-reminder-cron, expiration-date-filter]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "tester user with at least 1 published listing"
  - "Listing's _listora_expiration_date is approaching (within reminder window)"
estimated_runtime_minutes: 3
---

# Owner renews an expiring listing

Listing owner gets the expiry-reminder email, opens dashboard, sees "Renew" affordance on the row, clicks it, verifies expiration date extends and listing remains published. With Pro + Credits, verifies the credit deduction.

## Setup

- Site: `$SITE_URL`
- User: `tester` (owner of a listing)
- Force the listing close to expiry:
  ```bash
  wp post meta update <LISTING_ID> _listora_expiration_date "$(date -v+3d +%Y-%m-%d)"
  ```
  (3 days from now — should be inside the reminder window)

## Steps

### 1. Trigger the expiry-reminder cron manually
- **Action**:
  ```bash
  wp action-scheduler run --hooks=wb_listora_send_expiry_reminders
  ```
- **Expect**: tester receives `listing-expiring-soon` email

### 2. Verify email log
- **Action**:
  ```sql
  SELECT template, recipient FROM wp_listora_email_log
  WHERE listing_id=$LISTING_ID AND template='listing-expiring-soon'
  ORDER BY created_at DESC LIMIT 1;
  ```
- **Expect**: 1 row recent

### 3. tester opens dashboard
- **Action**: `playwright_navigate $SITE_URL/dashboard/?autologin=tester#listings`
- **Expect**: My Listings tab shows the listing with "Expires in 3 days" badge + Renew CTA

### 4. Click Renew
- **Action**: click Renew on the row
- **Expect**:
  - `POST /wp-json/listora/v1/listings/$LISTING_ID/renew` returns 200
  - Success toast / inline message

### 5. Verify expiration extended
- **Action**:
  ```bash
  wp post meta get $LISTING_ID _listora_expiration_date
  ```
- **Expect**: a date significantly in the future (3 days + the configured renewal period). With Pro Pricing_Plans active, this is filtered via `wb_listora_listing_expiration_date`.

### 6. Verify status remains publish
- **Action**:
  ```bash
  wp post get $LISTING_ID --field=post_status
  ```
- **Expect**: `publish`

### 7. Combo only — verify credit deduction
- **Action**: capture credit balance before + after:
  ```bash
  wp eval 'echo \WBListoraPro\Credit_System::get_balance(<TESTER_ID>);'
  ```
- **Expect**: balance decreased by the documented renewal cost (per pricing plan)

### 8. Verify renewed-email log
- **Action**:
  ```sql
  SELECT template FROM wp_listora_email_log
  WHERE listing_id=$LISTING_ID AND template='listing-renewed'
  ORDER BY created_at DESC LIMIT 1;
  ```
- **Expect**: 1 row recent

## Pass criteria

1. Expiry reminder email reaches owner
2. Dashboard shows Renew CTA
3. POST /listings/{id}/renew extends `_listora_expiration_date`
4. Status stays publish (not expired)
5. Combo: credits deducted + renewed email sent

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| No reminder email | cron not registered or template missing | `class-expiration-cron.php`, `templates/emails/listing-expiring-soon.php` |
| Renew CTA missing | dashboard query / template gap | `Dashboard_Controller`, dashboard tab template |
| Renew POST 500 | renew controller crash | `Listings_Controller::renew_listing` |
| Expiry not extended | filter returning 0 | `wb_listora_listing_expiration_date` filter, Pro Pricing_Plans listener |
| Credits not deducted (combo) | Pro renewal listener missing | `class-credit-system.php` renewal cost flow |
