# P2-01 — Outgoing Webhooks (Zapier/Make)

## Scope: Pro Only

---

## Overview

Outgoing webhooks allow directory owners to push events from their directory into external automation tools — Zapier, Make.com, n8n, Pabbly, or any HTTP endpoint. When something happens in the directory (listing approved, review posted, payment received), the plugin sends a signed HTTP POST to one or more configured URLs.

This is distinct from the **incoming** payment webhook (`POST /listora/v1/webhooks/payment`) documented in `21-payments.md`. Outgoing webhooks push data OUT of the directory.

### Why It Matters

- Zapier/Make integration is table-stakes for any SaaS-adjacent product
- Enables workflows: "When listing approved, add to Google Sheet + send Slack message + create CRM contact"
- Zero plugin code needed for each integration — one webhook endpoint, infinite automations
- Enterprise directories need event-driven architecture for internal systems

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Site owner | Send listing data to Zapier when a listing is approved | I can auto-post to social media and add the business to my CRM |
| 2 | Agency developer | Forward all events to my client's internal API | Their existing systems stay in sync without polling |
| 3 | Directory admin | See delivery logs for failed webhooks | I can debug integration issues without checking server logs |
| 4 | Site owner | Test a webhook URL before going live | I know the integration works before real events start flowing |
| 5 | Developer | Manage webhooks via WP-CLI | I can automate webhook setup in deployment scripts |
| 6 | Site owner | Pause a webhook temporarily | I can do maintenance on my Zapier workflow without losing events |

---

## Supported Events

| Event Key | Trigger | Payload Summary |
|-----------|---------|----------------|
| `listing_created` | Frontend or admin listing created (draft/pending) | Full listing data + author |
| `listing_updated` | Listing meta or content changed | Changed fields + listing ID |
| `listing_approved` | Status transitions to `publish` | Full listing data + approver |
| `listing_rejected` | Status transitions to `rejected` | Listing ID, title, reason, rejector |
| `listing_expired` | Expiration cron marks listing expired | Listing ID, title, expiry date, owner |
| `review_posted` | New review submitted (any status) | Review data + listing ID |
| `review_approved` | Review status transitions to `approved` | Review data + listing ID |
| `claim_submitted` | New claim request filed | Claim data + listing ID + claimer |
| `claim_approved` | Claim approved by admin/moderator | Claim data + listing + new owner |
| `payment_received` | Incoming payment webhook processed | User, amount, credits, plan |
| `credits_added` | Credits added to user (any source) | User, amount, source, balance |

---

## Technical Design

### Data Model

#### Webhook Entity

Stored as custom post type `listora_webhook` (not publicly queryable, admin-only).

```
post_title             -> "Zapier — New Listings"
post_status            -> "publish" (active) or "draft" (paused)

Meta:
_listora_wh_url        -> "https://hooks.zapier.com/hooks/catch/xxx/yyy"
_listora_wh_secret     -> "whsec_a1b2c3d4e5..." (HMAC signing key)
_listora_wh_events     -> JSON: ["listing_approved","review_posted"]
_listora_wh_created_by -> 1 (user ID)
```

#### Delivery Log Table

```sql
CREATE TABLE {prefix}listora_webhook_log (
    id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    webhook_id      BIGINT(20) UNSIGNED NOT NULL,
    event           VARCHAR(50) NOT NULL,
    payload         LONGTEXT NOT NULL,
    response_code   SMALLINT UNSIGNED DEFAULT NULL,
    response_body   TEXT DEFAULT NULL,
    attempt         TINYINT UNSIGNED NOT NULL DEFAULT 1,
    status          VARCHAR(20) NOT NULL DEFAULT 'pending',
    scheduled_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at    DATETIME DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_webhook (webhook_id),
    KEY idx_status (status),
    KEY idx_event (event),
    KEY idx_created (created_at DESC)
) {charset_collate};
```

**Statuses:** `pending`, `delivered`, `failed`, `retrying`

**Retention:** Last 50 deliveries per webhook kept. Older entries purged by daily cron.

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/webhooks/class-webhook-manager.php` | Core manager: register events, dispatch, retry |
| `includes/webhooks/class-webhook-dispatcher.php` | Async delivery via WP cron (non-blocking) |
| `includes/webhooks/class-webhook-signer.php` | HMAC-SHA256 signing utility |
| `includes/webhooks/class-webhook-logger.php` | Log delivery attempts and responses |
| `includes/rest/class-webhooks-controller.php` | REST CRUD for webhooks + test endpoint |
| `includes/admin/class-webhooks-page.php` | Admin list + create/edit page (Pattern B) |
| `includes/cli/class-webhooks-cli.php` | WP-CLI commands |

### Files to Modify (wb-listora free)

| File | Change |
|------|--------|
| `includes/workflow/class-status-manager.php` | Add `do_action('wb_listora_listing_approved')` etc. (already exists for most) |
| `includes/rest/class-reviews-controller.php` | Ensure `wb_listora_review_submitted` fires |
| `includes/rest/class-claims-controller.php` | Ensure `wb_listora_claim_submitted` fires |

### API Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `GET` | `/listora/v1/webhooks` | Admin | List all webhooks |
| `POST` | `/listora/v1/webhooks` | Admin | Create webhook |
| `GET` | `/listora/v1/webhooks/{id}` | Admin | Get single webhook |
| `PUT` | `/listora/v1/webhooks/{id}` | Admin | Update webhook |
| `DELETE` | `/listora/v1/webhooks/{id}` | Admin | Delete webhook |
| `POST` | `/listora/v1/webhooks/{id}/test` | Admin | Send test payload |
| `GET` | `/listora/v1/webhooks/{id}/log` | Admin | Get delivery log |

### Payload Format

```json
{
  "event": "listing_approved",
  "timestamp": "2026-04-05T14:30:00Z",
  "site_url": "https://site.com",
  "data": {
    "id": 123,
    "title": "Pizza Palace",
    "type": "restaurant",
    "status": "publish",
    "url": "https://site.com/listing/pizza-palace/",
    "author": {
      "id": 5,
      "name": "John",
      "email": "john@example.com"
    },
    "meta": {
      "phone": "+1-555-0123",
      "address": "123 Main St, Manhattan, NY"
    }
  }
}
```

### Security: HMAC Signing

```
X-Listora-Signature: sha256=<HMAC-SHA256 of raw body using webhook secret>
X-Listora-Event: listing_approved
X-Listora-Delivery: uuid-of-this-delivery
X-Listora-Timestamp: 1712345678
```

Receiver verifies:
```php
$expected = hash_hmac('sha256', $raw_body, $secret);
$valid    = hash_equals($expected, $received_signature);
```

### Delivery Mechanism

```
Event fires → Hook listener queues delivery
  → wp_schedule_single_event(time(), 'listora_deliver_webhook', [$log_id])
  → Cron fires: POST to URL with signed payload
  → Success (2xx): mark delivered
  → Failure: schedule retry with exponential backoff

Retry schedule:
  Attempt 1: immediate
  Attempt 2: +60 seconds
  Attempt 3: +300 seconds (5 min)
  Attempt 4: +1800 seconds (30 min) — FINAL

After 4 failures: mark as failed, no more retries.
```

**Timeout:** 10 seconds per request. Non-blocking (cron-based, never delays user request).

---

## UI Mockup

### Admin: Webhook List Page (Listora > Webhooks)

```
┌─────────────────────────────────────────────────────────────┐
│ Webhooks                                    [+ Add Webhook] │
│                                                             │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ ● Zapier — New Listings                     Active     │ │
│ │   https://hooks.zapier.com/hooks/catch/xxx/yyy         │ │
│ │   Events: listing_approved, listing_created            │ │
│ │   Last delivery: 2 hours ago (200 OK)                  │ │
│ │                              [Test] [Edit] [Pause] [X] │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ○ Make.com — Reviews                        Paused     │ │
│ │   https://hook.eu1.make.com/xxx                        │ │
│ │   Events: review_posted, review_approved               │ │
│ │   Last delivery: 3 days ago (200 OK)                   │ │
│ │                            [Test] [Edit] [Resume] [X]  │ │
│ ├─────────────────────────────────────────────────────────┤ │
│ │ ● Internal API                              Active     │ │
│ │   https://api.client.com/directory/webhook             │ │
│ │   Events: All events                                   │ │
│ │   Last delivery: 5 min ago (500 — FAILED, retrying)    │ │
│ │                              [Test] [Edit] [Pause] [X] │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ Showing 3 webhooks                                          │
└─────────────────────────────────────────────────────────────┘
```

### Admin: Create/Edit Webhook

```
┌─────────────────────────────────────────────────────────────┐
│ Add Webhook                                                 │
│                                                             │
│ Name                                                        │
│ [ Zapier — New Listings                                   ] │
│                                                             │
│ Payload URL *                                               │
│ [ https://hooks.zapier.com/hooks/catch/xxx/yyy            ] │
│                                                             │
│ Secret (for HMAC signing)                                   │
│ [ whsec_a1b2c3d4e5f6g7h8 ]           [Generate]            │
│                                                             │
│ Events                                                      │
│ ┌─────────────────────────────────────────────────────────┐ │
│ │ Listings                                                │ │
│ │ ☑ listing_created    ☑ listing_approved                │ │
│ │ ☐ listing_updated    ☐ listing_rejected                │ │
│ │ ☐ listing_expired                                      │ │
│ │                                                         │ │
│ │ Reviews                                                 │ │
│ │ ☑ review_posted      ☐ review_approved                 │ │
│ │                                                         │ │
│ │ Claims                                                  │ │
│ │ ☐ claim_submitted    ☐ claim_approved                  │ │
│ │                                                         │ │
│ │ Payments                                                │ │
│ │ ☐ payment_received   ☐ credits_added                   │ │
│ └─────────────────────────────────────────────────────────┘ │
│                                                             │
│ Status                                                      │
│ (●) Active   ( ) Paused                                     │
│                                                             │
│                               [Cancel]  [Test]  [Save]      │
└─────────────────────────────────────────────────────────────┘
```

### Admin: Delivery Log (per webhook)

```
┌─────────────────────────────────────────────────────────────┐
│ Delivery Log — Zapier — New Listings                        │
│                                                             │
│ | Event             | Status | Code | Time          | Att. │
│ |-------------------|--------|------|---------------|------│
│ | listing_approved  | ✓ 200  | 200  | 2 hours ago   | 1/1 │
│ | listing_created   | ✓ 200  | 200  | 5 hours ago   | 1/1 │
│ | review_posted     | ✗ 500  | 500  | 1 day ago     | 4/4 │
│ | listing_approved  | ✓ 200  | 200  | 1 day ago     | 1/1 │
│ | listing_approved  | ✓ 200  | 200  | 2 days ago    | 2/4 │
│                                                             │
│ [Click any row to expand payload + response body]           │
│                                                             │
│ Showing 5 of 47 deliveries                    [Load More]   │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Create `listora_webhook_log` table + migration | 2 |
| 2 | Build `Webhook_Manager` class — event registration, dispatch queue | 4 |
| 3 | Build `Webhook_Dispatcher` class — async delivery via cron, retry logic | 4 |
| 4 | Build `Webhook_Signer` class — HMAC-SHA256 + header generation | 1 |
| 5 | Build `Webhook_Logger` class — log writes, retention cleanup cron | 2 |
| 6 | Hook into all 11 free plugin events (`listing_approved`, etc.) | 3 |
| 7 | Build REST controller — full CRUD + test + log endpoints | 5 |
| 8 | Build admin list page (Pattern B) | 4 |
| 9 | Build admin create/edit form | 3 |
| 10 | Build delivery log viewer (expandable rows) | 3 |
| 11 | Test button — sends sample payload to URL | 1 |
| 12 | WP-CLI commands: `webhooks list`, `webhooks test <id>`, `webhooks log <id>` | 3 |
| 13 | Automated tests (PHPUnit) — dispatch, retry, signing, CRUD | 4 |
| 14 | Documentation | 2 |
| **Total** | | **41 hours** |

---

## WP-CLI Commands

```bash
wp listora webhooks list                      # List all webhooks with status
wp listora webhooks create --url=... --events=listing_approved,review_posted
wp listora webhooks test <id>                 # Send test payload
wp listora webhooks pause <id>                # Pause webhook
wp listora webhooks resume <id>               # Resume webhook
wp listora webhooks delete <id>               # Delete webhook
wp listora webhooks log <id> --limit=20       # Show delivery log
wp listora webhooks purge-log --older-than=30 # Purge old log entries
```

---

## Competitive Context

| Competitor | Webhooks? | Our Advantage |
|-----------|-----------|---------------|
| GeoDirectory | No native webhooks | First-class webhook system with delivery logs |
| Directorist | Zapier addon ($49 extra) | Included in Pro, no extra cost |
| HivePress | No | Fully supported with HMAC signing |
| ListingPro | No | Modern event-driven architecture |
| MyListing | No | Async delivery, retry with backoff |
| Business Directory Plugin | No | Full delivery log + test button |

**Our edge:** HMAC-signed payloads (security), async delivery (performance), delivery log with response inspection (debuggability), WP-CLI support (DevOps), and all 11 events covered. Most competitors either lack webhooks entirely or offer a bare-bones Zapier addon.

---

## Effort Estimate

**Total: ~41 hours (5-6 dev days)**

- Backend (manager, dispatcher, signer, logger): 11h
- Event hooks: 3h
- REST API: 5h
- Admin UI: 10h
- WP-CLI: 3h
- Tests + docs: 6h
- QA + edge cases: 3h
