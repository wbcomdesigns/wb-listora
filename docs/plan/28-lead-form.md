# 28 — Lead Form / Contact Owner (Pro)

## Scope: Pro Only

---

## Overview

Visitors can contact listing owners directly through a built-in contact form on the listing detail page. Every submission is tracked as a "lead" in analytics.

---

## Contact Form UI

```
┌─────────────────────────────────────────────────────┐
│ Contact Pizza Palace                                │
│                                                     │
│ Your Name *                                         │
│ [ Jane Smith                                    ]   │
│                                                     │
│ Your Email *                                        │
│ [ jane@example.com                              ]   │
│                                                     │
│ Phone (optional)                                    │
│ [ (555) 123-4567                                ]   │
│                                                     │
│ Message *                                           │
│ ┌───────────────────────────────────────────────┐   │
│ │ I'd like to book a table for 4 this Saturday  │   │
│ │ evening. Do you have availability?             │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│                              [Send Message]         │
└─────────────────────────────────────────────────────┘
```

## Flow
1. Visitor fills form on listing detail page
2. Email sent to listing owner (with reply-to set to visitor's email)
3. Lead recorded in analytics (listing_id, date, no PII stored long-term)
4. Confirmation shown to visitor
5. Listing owner sees lead count in dashboard

## Spam Prevention
- Honeypot field
- Rate limiting (3 messages per hour per IP)
- Optional reCAPTCHA via filter hook

## Privacy
- Message content NOT stored in database (only sent via email)
- Only aggregate lead count stored in analytics
- GDPR compliant — no visitor PII retained
