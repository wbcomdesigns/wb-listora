# 32 — Webhooks (Pro)

## Scope: Pro Only

---

## Overview

Outgoing webhooks allow directory owners to connect their directory to Zapier, Make.com, n8n, or custom applications. When events happen in the directory, HTTP POST requests are sent to configured URLs.

---

## Supported Events

| Event | Payload |
|-------|---------|
| `listing.created` | Listing data |
| `listing.updated` | Listing data (changed fields) |
| `listing.published` | Listing data |
| `listing.expired` | Listing ID, title, owner |
| `review.created` | Review data, listing ID |
| `claim.submitted` | Claim data |
| `claim.approved` | Claim data, listing ID, user |
| `payment.completed` | Payment data (Pro) |
| `favorite.added` | Listing ID, user ID |

---

## Admin Configuration

```
Settings → Webhooks (Pro)

┌─────────────────────────────────────────────────────┐
│ Webhooks                              [+ Add New]   │
│                                                     │
│ | URL                        | Events      | ⋮    │
│ |----------------------------|-------------|       │
│ | https://hooks.zapier.com/..| All         | ✎ 🗑 │
│ | https://hook.make.com/...  | listing.*   | ✎ 🗑 │
└─────────────────────────────────────────────────────┘

Add/Edit Webhook:
URL:    [ https://hooks.zapier.com/... ]
Secret: [ whsec_... ] (for signature verification)
Events: ☑ listing.created  ☑ listing.published
        ☐ listing.updated  ☐ listing.expired
        ☑ review.created   ☐ claim.*
        ☐ payment.*
[Test]  [Save]
```

---

## Payload Format

```json
{
  "event": "listing.published",
  "timestamp": "2026-03-15T10:30:00Z",
  "data": {
    "id": 123,
    "title": "Pizza Palace",
    "type": "restaurant",
    "url": "https://site.com/listing/pizza-palace/",
    "author": { "id": 5, "name": "John", "email": "john@example.com" }
  }
}
```

### Security
- HMAC-SHA256 signature in `X-Listora-Signature` header
- Retry: 3 attempts with exponential backoff (1s, 10s, 60s)
- Timeout: 10 seconds per attempt
- Failed webhooks logged, admin notified after 3 consecutive failures
