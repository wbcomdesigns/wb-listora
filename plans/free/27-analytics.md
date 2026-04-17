# 27 — Analytics (Pro)

## Scope: Pro Only

---

## Overview

Listing owners want to know: "Is my listing working?" Analytics tracks views, clicks, and engagement — giving listing owners Airbnb-style insights and site owners data to sell premium plans.

---

## Events Tracked

| Event | Trigger | Storage |
|-------|---------|---------|
| `view` | Listing detail page loaded | Aggregated daily |
| `search_impression` | Listing appeared in search results | Aggregated daily |
| `phone_click` | Phone number clicked/tapped | Individual + daily |
| `website_click` | Website link clicked | Individual + daily |
| `email_click` | Email link clicked | Individual + daily |
| `direction_click` | "Get Directions" clicked | Aggregated daily |
| `favorite` | Listing favorited | Aggregated daily |
| `share` | Share button used | Aggregated daily |

### Tracking Method
- **Views:** Server-side via `template_redirect` hook (no JS needed, no cookie issues)
- **Clicks:** Interactivity API `data-wp-on--click` → REST API call
- **Search impressions:** Counted in search response handler
- **Aggregation:** `INSERT ON DUPLICATE KEY UPDATE count = count + 1` for daily buckets

### Privacy
- No personal data stored (no IP, no user agent, no cookies)
- Only aggregate counts per listing per day
- GDPR compliant — no tracking consent needed for aggregate analytics
- Bot filtering: skip if `is_bot()` check (common user agents)

---

## Listing Owner Dashboard (Pro Tab)

```
┌─────────────────────────────────────────────────────┐
│ Analytics                     [Last 30 days ▾]      │
│                                                     │
│ ┌───────┐ ┌───────┐ ┌───────┐ ┌───────┐          │
│ │ 1,245 │ │   89  │ │   34  │ │  4.5  │          │
│ │ Views │ │Clicks │ │Leads  │ │Rating │          │
│ │ +12%↑ │ │ +5%↑  │ │ +18%↑ │ │  =    │          │
│ └───────┘ └───────┘ └───────┘ └───────┘          │
│                                                     │
│ ┌───────────────────────────────────────────────┐   │
│ │ Views Over Time                               │   │
│ │ 50│    ╱╲                                     │   │
│ │ 40│   ╱  ╲  ╱╲                               │   │
│ │ 30│  ╱    ╲╱  ╲╱╲                            │   │
│ │ 20│ ╱              ╲                          │   │
│ │   └──────────────────────────────             │   │
│ │   Mar 1     Mar 10      Mar 20                │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ Click Breakdown:                                    │
│ 📞 Phone:    45 clicks (51%)                       │
│ 🌐 Website:  28 clicks (31%)                       │
│ ✉️ Email:     12 clicks (13%)                       │
│ 📍 Directions: 4 clicks (5%)                       │
│                                                     │
│ Top Performing Listings:                            │
│ 1. Pizza Palace — 456 views, 34 clicks             │
│ 2. Burger Joint — 312 views, 21 clicks             │
└─────────────────────────────────────────────────────┘
```

---

## Admin Analytics

Site-wide analytics for the directory owner:

```
┌─────────────────────────────────────────────────────┐
│ Directory Analytics                [Last 30 days ▾] │
│                                                     │
│ Total Views: 45,678     Unique Visitors: 12,345     │
│ Total Clicks: 3,456     Avg Rating: 4.2             │
│                                                     │
│ Top Listings by Views:                              │
│ 1. Pizza Palace (1,245 views)                       │
│ 2. Grand Hotel (987 views)                          │
│                                                     │
│ Top Categories:                                     │
│ 1. Restaurants (45% of traffic)                     │
│ 2. Hotels (23% of traffic)                          │
│                                                     │
│ Search Trends:                                      │
│ 1. "pizza" (234 searches)                           │
│ 2. "hotel near me" (189 searches)                   │
└─────────────────────────────────────────────────────┘
```

---

## REST API

```
GET /listora/v1/analytics/listing/{id}   → listing analytics (author/admin)
GET /listora/v1/analytics/overview       → site-wide analytics (admin)

Query params: period=7d|30d|90d|1y, group_by=day|week|month
```
