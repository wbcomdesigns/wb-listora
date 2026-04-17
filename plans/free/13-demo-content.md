# 13 — Demo Content & Onboarding

## Scope

| | Free | Pro |
|---|---|---|
| Demo content for all 10 types | Yes | Yes |
| Location-aware demo data | Yes | Yes |
| Demo images (royalty-free) | Yes | Yes |
| Demo reviews | Yes | Yes |
| One-click cleanup | Yes | Yes |
| Onboarding checklist | Yes | Yes |

---

## Demo Content Strategy

### Problem
An empty directory looks dead. Site owners need 20+ listings immediately to evaluate the plugin, show clients, and start building.

### Solution
Pre-built demo data that adapts to the site owner's chosen location and listing types. Not a static XML import — dynamically generated with plausible data.

---

## Per-Type Demo Sets

Each listing type includes **20 demo listings** with:
- Realistic title and description
- Featured image (bundled royalty-free or placeholder via API)
- Filled custom fields
- Assigned categories
- Location data (geocoded to owner's chosen city)
- 2-3 demo reviews per listing

### Example: Restaurant Demo Set
```
1. The Golden Fork — Italian, $$$, 4.7★
2. Sakura House — Japanese, $$, 4.5★
3. Casa Miguel — Mexican, $$, 4.3★
4. Dragon Palace — Chinese, $$, 4.1★
5. Spice Route — Indian, $$, 4.6★
...20 total, mixed categories and price ranges
```

### Location Adaptation
If site owner selects "London, UK":
- Addresses generated as London addresses (real street names, valid postcodes)
- Lat/lng within London bounding box
- Phone numbers in UK format
- Currency in GBP
- Timezone set to Europe/London

If "New York, US":
- Manhattan/Brooklyn addresses
- US phone format
- USD currency
- Eastern timezone

**How:** Demo data templates use variables:
```json
{
  "title": "The Golden Fork",
  "address": "{street_number} {street_name}, {city}",
  "phone": "{local_phone_format}",
  "lat": "{city_lat} + random(-0.05, 0.05)",
  "lng": "{city_lng} + random(-0.05, 0.05)"
}
```

Street names pulled from a small bundled dataset per major city. For unlisted cities, uses generic street names.

---

## Demo Images

### Option A: Bundled Placeholders (Default)
- 30 royalty-free images bundled with plugin (~2MB total, WebP)
- 3 per listing type (rotated across listings)
- Professional quality but generic

### Option B: Dynamic Placeholders
- Use a placeholder service (e.g., generic colored cards with type icons)
- Zero bandwidth, instant
- Less realistic but functional

### Recommendation
Option A for a polished first impression. Images stored in `assets/demo/` and imported to Media Library on demo install.

---

## Demo Content Markers

All demo content tagged for easy cleanup:
- Post meta: `_listora_demo_content = 1`
- Reviews: `status = 'demo'` (custom status, not shown in moderation queue)
- Demo users created: `listora_demo_reviewer_1`, `listora_demo_reviewer_2`

---

## One-Click Cleanup

Settings page: **"Remove Demo Content"** button

Deletes:
- All posts with `_listora_demo_content = 1`
- Associated media attachments
- Demo reviews
- Demo users (if they have no other content)

Does NOT delete:
- Pages created by setup wizard
- Settings/configuration
- Real listings added by the site owner

---

## Onboarding Checklist

After setup wizard completes, show a persistent admin notice with getting-started checklist:

```
┌─────────────────────────────────────────────────────────────────┐
│ 🎉 WB Listora Setup Complete! Here's what to do next:          │
│                                                                 │
│ ✅ Plugin activated and configured                              │
│ ✅ Demo content imported                                        │
│ ☐  Add your first real listing         [Add Listing →]          │
│ ☐  Customize your directory page       [Edit Page →]            │
│ ☐  Import listings from CSV            [Import →]               │
│ ☐  Configure email notifications       [Settings →]             │
│ ☐  Set up payments (Pro)               [Upgrade →]              │
│ ☐  Remove demo content when ready      [Remove Demo →]          │
│                                                                 │
│                                            [Dismiss Checklist]  │
└─────────────────────────────────────────────────────────────────┘
```

Checklist persists across admin pages until dismissed. State stored in user meta.
