# 29 — Listing Comparison (Pro)

## Scope: Pro Only

---

## Overview

TripAdvisor-style side-by-side comparison of 2-4 listings. Helps visitors make decisions — especially valuable for hotels, real estate, and healthcare directories.

---

## UX Flow

### Adding to Compare
On listing cards and detail pages:
```
[⊞ Compare]  ← Button on card/detail
```

Click → listing added to comparison bar (sticky bottom):

```
┌─────────────────────────────────────────────────────────────┐
│ Compare (2/4): [Pizza Palace ×] [Sushi House ×]  [Compare →]│
└─────────────────────────────────────────────────────────────┘
```

### Comparison Table

```
┌────────────────┬──────────────┬──────────────┬──────────────┐
│                │ Pizza Palace │ Sushi House  │ Taco Shop    │
├────────────────┼──────────────┼──────────────┼──────────────┤
│ Image          │ [img]        │ [img]        │ [img]        │
│ Rating         │ ★★★★½ 4.5  │ ★★★★ 4.0    │ ★★★★★ 4.8  │
│ Reviews        │ 23           │ 12           │ 45           │
│ Price Range    │ $$$          │ $$           │ $            │
│ Cuisine        │ Italian      │ Japanese     │ Mexican      │
│ Delivery       │ ✓            │ ✗            │ ✓            │
│ Open Now       │ ✓ Open       │ ✗ Closed     │ ✓ Open       │
│ WiFi           │ ✓            │ ✓            │ ✗            │
│ Parking        │ ✓            │ ✗            │ ✓            │
│                │ [View →]     │ [View →]     │ [View →]     │
└────────────────┴──────────────┴──────────────┴──────────────┘
```

### Comparison Rules
- Max 4 listings
- Only compare same listing type (comparing a restaurant to a hotel makes no sense)
- Fields shown = union of both listings' type fields
- "Better" values subtly highlighted (higher rating, more features)
- Comparison stored in browser localStorage (no auth needed)

---

## Block: `listora/listing-comparison` (Pro)

Comparison page uses this block. Can be placed on any page.
Reads listing IDs from URL params: `?compare=123,456,789`

---

## Theme Adaptive

```css
.listora-comparison table {
  width: 100%;
  border-collapse: collapse;
  font-size: var(--wp--preset--font-size--small, 0.9rem);
}
.listora-comparison th,
.listora-comparison td {
  padding: var(--wp--preset--spacing--10, 0.5rem);
  border: 1px solid var(--wp--preset--color--contrast-3, #eee);
  text-align: start;
}
```
