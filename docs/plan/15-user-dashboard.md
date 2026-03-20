# 15 — User Dashboard

## Scope

| | Free | Pro |
|---|---|---|
| My Listings tab | Yes | Yes |
| My Reviews tab | Yes | Yes |
| My Favorites tab | Yes | Yes |
| Edit/delete own listings | Yes | Yes |
| Listing status overview | Yes | Yes |
| Renew expired listings | Yes | Yes |
| My Analytics tab | — | Yes |
| My Payments tab | — | Yes |
| Saved searches | — | Yes |
| Profile settings | Yes | Yes |

---

## Overview

The user dashboard is a frontend page (not wp-admin) where listing owners and active visitors manage their directory activity.

---

## Dashboard Layout

```
┌─────────────────────────────────────────────────────────┐
│ My Dashboard                         Hello, John! [👤]  │
├──────┬──────────────────────────────────────────────────┤
│      │                                                  │
│ Tabs │  Tab Content                                     │
│      │                                                  │
│[List]│  ┌──────────────────────────────────────────┐   │
│[Revw]│  │                                          │   │
│[Favs]│  │  Content for selected tab                │   │
│[Prof]│  │                                          │   │
│      │  │                                          │   │
│Pro:  │  └──────────────────────────────────────────┘   │
│[Anly]│                                                  │
│[Pays]│                                                  │
│[Srch]│                                                  │
│      │                                                  │
└──────┴──────────────────────────────────────────────────┘
```

On mobile: tabs become horizontal scrollable pills at top.

---

## Tab: My Listings

### Summary Cards
```
┌─────────────────────────────────────────────────────┐
│ My Listings                        [+ Add Listing]  │
│                                                     │
│ ┌───────┐ ┌───────┐ ┌───────┐ ┌───────┐          │
│ │  12   │ │   3   │ │   1   │ │   2   │          │
│ │Active │ │Pending│ │Expired│ │Drafts │          │
│ └───────┘ └───────┘ └───────┘ └───────┘          │
│                                                     │
│ Filter: [All ▾] [Type ▾]     Search: [          ]  │
├─────────────────────────────────────────────────────┤
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ [img] Pizza Palace        🟢 Published         │ │
│ │       Restaurant · Italian                      │ │
│ │       ★4.5 (23) · 1,245 views                 │ │
│ │       Expires: Mar 15, 2027                     │ │
│ │       [Edit] [View] [⋮ More]                   │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ [img] Burger Joint        🟡 Pending Review    │ │
│ │       Restaurant · American                     │ │
│ │       Submitted: Mar 1, 2026                    │ │
│ │       [Edit] [Preview]                          │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ ┌─────────────────────────────────────────────────┐ │
│ │ [img] Old Café            🔴 Expired           │ │
│ │       Restaurant · Café                         │ │
│ │       Expired: Feb 28, 2026                     │ │
│ │       [Renew] [Edit] [Delete]                   │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ [1] [2] [→]                                         │
└─────────────────────────────────────────────────────┘
```

### Status Colors
| Status | Color | Actions |
|--------|-------|---------|
| Published | 🟢 Green | Edit, View, Deactivate |
| Pending Review | 🟡 Yellow | Edit, Preview |
| Draft | ⚪ Gray | Edit, Delete, Submit |
| Expired | 🔴 Red | Renew, Edit, Delete |
| Rejected | 🔴 Red | Edit (re-submit), View rejection reason |

### More Menu (⋮)
- Duplicate listing
- Deactivate (owner-initiated unpublish)
- Delete
- View analytics (Pro)

---

## Tab: My Reviews

```
┌─────────────────────────────────────────────────────┐
│ My Reviews                                          │
│                                                     │
│ Reviews I've Written                                │
│ ┌─────────────────────────────────────────────────┐ │
│ │ Pizza Palace  ★★★★★                           │ │
│ │ "Best pizza in town! The crust is..."          │ │
│ │ March 5, 2026 · [Edit] [Delete]                │ │
│ └─────────────────────────────────────────────────┘ │
│                                                     │
│ Reviews on My Listings                              │
│ ┌─────────────────────────────────────────────────┐ │
│ │ John D. reviewed Pizza Palace  ★★★★☆          │ │
│ │ "Good pizza but slow service..."               │ │
│ │ March 10, 2026                                 │ │
│ │ [Reply] [Report]                               │ │
│ └─────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────┘
```

Two sections:
1. **Reviews I wrote** — edit/delete own reviews
2. **Reviews on my listings** — reply to reviews (owner response)

---

## Tab: My Favorites

```
┌─────────────────────────────────────────────────────┐
│ My Favorites                                (18)    │
│                                                     │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│ │  Card 1  │ │  Card 2  │ │  Card 3  │            │
│ │  [♥ ×]   │ │  [♥ ×]   │ │  [♥ ×]   │            │
│ └──────────┘ └──────────┘ └──────────┘            │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│ │  Card 4  │ │  Card 5  │ │  Card 6  │            │
│ └──────────┘ └──────────┘ └──────────┘            │
│                                                     │
│ [1] [2] [→]                                         │
└─────────────────────────────────────────────────────┘
```

**Pro:** Collections — organize favorites into named groups:
```
[All Favorites] [Date Night ▾] [Weekend Brunch ▾] [+ New Collection]
```

---

## Tab: Profile Settings

```
┌─────────────────────────────────────────────────────┐
│ Profile Settings                                    │
│                                                     │
│ Display Name:  [ John Doe                       ]   │
│ Email:         [ john@example.com               ]   │
│ Phone:         [ (212) 555-0123                 ]   │
│                                                     │
│ Avatar: [Current Avatar] [Change]                   │
│                                                     │
│ Bio:                                                │
│ [ Restaurant enthusiast and food blogger...     ]   │
│                                                     │
│ Email Notifications:                                │
│ ☑ New review on my listing                         │
│ ☑ Listing status changes                           │
│ ☑ Listing expiration reminders                     │
│ ☐ Weekly summary                                   │
│                                                     │
│ [Save Changes]                                      │
│                                                     │
│ [Change Password]  [Delete Account]                 │
└─────────────────────────────────────────────────────┘
```

---

## Tab: My Analytics (Pro)

```
┌─────────────────────────────────────────────────────┐
│ Analytics                       Last 30 days [▾]    │
│                                                     │
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐              │
│ │1,245 │ │  89  │ │  23  │ │ 4.5  │              │
│ │Views │ │Clicks│ │Leads │ │Rating│              │
│ │+12%  │ │+5%   │ │+18%  │ │=     │              │
│ └──────┘ └──────┘ └──────┘ └──────┘              │
│                                                     │
│ ┌───────────────────────────────────────────────┐   │
│ │ Views Chart (line graph)                      │   │
│ │ ╱╲    ╱╲╱╲                                   │   │
│ │╱  ╲╱╲╱    ╲╱╲                                │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ Top Performing Listings:                            │
│ 1. Pizza Palace — 456 views, 34 clicks             │
│ 2. Burger Joint — 312 views, 21 clicks             │
│ 3. Pasta House  — 234 views, 15 clicks             │
└─────────────────────────────────────────────────────┘
```

---

## Block: `listora/user-dashboard`

### Attributes
```json
{
  "attributes": {
    "defaultTab": { "type": "string", "default": "listings" },
    "showListings": { "type": "boolean", "default": true },
    "showReviews": { "type": "boolean", "default": true },
    "showFavorites": { "type": "boolean", "default": true },
    "showProfile": { "type": "boolean", "default": true }
  }
}
```

---

## Not Logged In State

```
┌─────────────────────────────────────────────────────┐
│                                                     │
│          Please log in to view your dashboard.      │
│                                                     │
│          [Log In]  [Create Account]                 │
│                                                     │
└─────────────────────────────────────────────────────┘
```

---

## REST API

```
GET  /listora/v1/dashboard/listings    → user's listings with status
GET  /listora/v1/dashboard/reviews     → reviews written + received
GET  /listora/v1/dashboard/stats       → summary counts
GET  /listora/v1/dashboard/favorites   → favorited listings
PUT  /listora/v1/dashboard/profile     → update profile settings
```

---

## Mobile Experience

- Tabs become horizontal scrollable pills
- Listing rows become cards (stacked)
- Summary stats in 2x2 grid
- Actions accessed via swipe-left or ⋮ menu
- Full-width layout

---

## Theme Adaptive CSS

```css
.listora-dashboard {
  max-width: var(--wp--style--global--wide-size, 1200px);
  margin: 0 auto;
}

.listora-dashboard__tabs {
  border-inline-end: 1px solid var(--wp--preset--color--contrast-3, #eee);
  padding-inline-end: var(--wp--preset--spacing--20, 1rem);
}

.listora-dashboard__tab--active {
  color: var(--wp--preset--color--primary, #0073aa);
  font-weight: 600;
}

.listora-dashboard__stat {
  background: var(--wp--preset--color--base, #fff);
  border: 1px solid var(--wp--preset--color--contrast-3, #eee);
  border-radius: var(--wp--custom--border-radius, 8px);
  padding: var(--wp--preset--spacing--20, 1rem);
  text-align: center;
}

.listora-dashboard__stat-value {
  font-size: var(--wp--preset--font-size--x-large, 2rem);
  font-family: var(--wp--preset--font-family--heading, inherit);
  color: var(--wp--preset--color--contrast, #1a1a1a);
}
```
