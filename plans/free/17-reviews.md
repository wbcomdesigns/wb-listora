# 17 — Reviews & Ratings

## Scope

| | Free | Pro |
|---|---|---|
| Single overall rating (1-5 stars) | Yes | Yes |
| Text review with title | Yes | Yes |
| Owner reply | Yes | Yes |
| Review moderation | Yes | Yes |
| "Helpful" votes | Yes | Yes |
| Average rating on cards/detail | Yes | Yes |
| Review count | Yes | Yes |
| Multi-criteria ratings | — | Yes |
| Photo reviews | — | Yes |
| Verified visit badges | — | Yes |
| AI review summary | — | Yes |
| Review analytics | — | Yes |

---

## Review Display

### On Listing Detail Page (Reviews Tab)

```
┌─────────────────────────────────────────────────────┐
│ Reviews                                             │
│                                                     │
│ ┌───────────────────────────────────────────────┐   │
│ │ Rating Summary                                │   │
│ │                                               │   │
│ │ ★★★★½  4.5 average  ·  23 reviews           │   │
│ │                                               │   │
│ │ 5 ★ ████████████████████░░  14 (61%)         │   │
│ │ 4 ★ ████████░░░░░░░░░░░░░   6 (26%)         │   │
│ │ 3 ★ ███░░░░░░░░░░░░░░░░░░   2 (9%)          │   │
│ │ 2 ★ █░░░░░░░░░░░░░░░░░░░░   1 (4%)          │   │
│ │ 1 ★ ░░░░░░░░░░░░░░░░░░░░░   0 (0%)          │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ Sort: [Most Recent ▾]     [Write a Review]          │
│                                                     │
│ ┌───────────────────────────────────────────────┐   │
│ │ ★★★★★                          March 10, 2026│   │
│ │                                               │   │
│ │ "Best Pizza in Manhattan!"                    │   │
│ │                                               │   │
│ │ The crust is perfectly thin and crispy. The   │   │
│ │ sauce is homemade and you can tell. Service   │   │
│ │ was friendly and fast. Will definitely come   │   │
│ │ back!                                         │   │
│ │                                               │   │
│ │ — John D.         👍 Helpful (5)  [Report]   │   │
│ │                                               │   │
│ │ ┌─ Owner Response ──────────────────────────┐ │   │
│ │ │ Thank you John! We're glad you enjoyed    │ │   │
│ │ │ the pizza. See you next time!             │ │   │
│ │ │ — Pizza Palace · March 11, 2026           │ │   │
│ │ └──────────────────────────────────────────┘ │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ ┌───────────────────────────────────────────────┐   │
│ │ ★★★★☆                          March 5, 2026 │   │
│ │                                               │   │
│ │ "Good food, slow service"                     │   │
│ │                                               │   │
│ │ The pizza was great but we waited 45 minutes  │   │
│ │ for our order. The place was busy but still... │   │
│ │                                               │   │
│ │ — Sarah M.        👍 Helpful (2)  [Report]   │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ [Load More Reviews]                                 │
└─────────────────────────────────────────────────────┘
```

### Pro: Multi-Criteria Display
```
┌───────────────────────────────────────────────┐
│ ★★★★★  Overall: 4.5                         │
│                                               │
│ Pro: Criteria breakdown                       │
│ Food:      ★★★★★  5.0                       │
│ Service:   ★★★★☆  4.0                       │
│ Ambiance:  ★★★★☆  4.5                       │
│ Value:     ★★★★½  4.5                       │
│                                               │
│ "Best Pizza in Manhattan!"                    │
│ ...                                           │
│                                               │
│ Pro: Photos                                   │
│ [img1] [img2] [img3]                         │
└───────────────────────────────────────────────┘
```

---

## Review Submission Form

### Free: Simple Rating
```
┌─────────────────────────────────────────────────────┐
│ Write a Review                                      │
│                                                     │
│ Your Rating *                                       │
│ ☆ ☆ ☆ ☆ ☆  (click to rate)                        │
│                                                     │
│ Review Title *                                      │
│ [ Best Pizza in Manhattan!                      ]   │
│                                                     │
│ Your Review *                                       │
│ ┌───────────────────────────────────────────────┐   │
│ │                                               │   │
│ │ The crust is perfectly thin and crispy...     │   │
│ │                                               │   │
│ └───────────────────────────────────────────────┘   │
│ Min 50 characters                                   │
│                                                     │
│                              [Submit Review]        │
└─────────────────────────────────────────────────────┘
```

### Pro: Multi-Criteria + Photos
```
┌─────────────────────────────────────────────────────┐
│ Write a Review                                      │
│                                                     │
│ Overall Rating *   ☆ ☆ ☆ ☆ ☆                      │
│                                                     │
│ Rate each aspect:                                   │
│ Food:     ☆ ☆ ☆ ☆ ☆                               │
│ Service:  ☆ ☆ ☆ ☆ ☆                               │
│ Ambiance: ☆ ☆ ☆ ☆ ☆                               │
│ Value:    ☆ ☆ ☆ ☆ ☆                               │
│                                                     │
│ Review Title *                                      │
│ [                                               ]   │
│                                                     │
│ Your Review *                                       │
│ [                                               ]   │
│                                                     │
│ Add Photos (optional, up to 5)                      │
│ [📷 Upload] or drag & drop                         │
│                                                     │
│                              [Submit Review]        │
└─────────────────────────────────────────────────────┘
```

---

## Rating Summary Calculation

Stored in `listora_search_index` for fast access:
- `avg_rating`: Average of all approved reviews' `overall_rating`
- `review_count`: Count of approved reviews

Updated on:
- New review approved
- Review deleted
- Review status changed

```sql
UPDATE listora_search_index
SET avg_rating = (
  SELECT AVG(overall_rating) FROM listora_reviews
  WHERE listing_id = ? AND status = 'approved'
),
review_count = (
  SELECT COUNT(*) FROM listora_reviews
  WHERE listing_id = ? AND status = 'approved'
)
WHERE listing_id = ?
```

---

## Review Moderation

### Admin Queue
`Listora → Reviews` admin page:

| Column | Content |
|--------|---------|
| Reviewer | User name + avatar |
| Listing | Link to listing |
| Rating | Stars |
| Excerpt | First 100 chars |
| Status | Pending / Approved / Spam |
| Date | Submission date |

Bulk actions: Approve, Mark as Spam, Delete

### Moderation Settings
- Auto-approve reviews: Yes / No
- Require minimum character count: configurable (default 50)
- One review per user per listing (enforced by unique key)
- Self-reviews blocked (can't review own listing)

---

## Owner Reply

- Listing owner (or admin) can reply to any review on their listing
- One reply per review (no thread)
- Reply appears visually nested under the review
- Reply triggers email notification to reviewer

```
POST /listora/v1/reviews/{id}/reply
Body: { content: "Thank you for your review!" }
Auth: Listing author or admin
```

---

## "Helpful" Votes

- Any logged-in user can vote a review as "helpful"
- One vote per user per review
- Count displayed: "👍 Helpful (5)"
- Reviews can be sorted by helpful_count

```
POST /listora/v1/reviews/{id}/helpful
Auth: Required (not review author)
Response: { helpful_count: 6 }
```

---

## Report Review Flow

### Why
Users need to flag inappropriate, fake, or spam reviews. This is essential at scale.

### UI
On each review:
```
— John D.         👍 Helpful (5)  [Report]
```

Clicking [Report] shows:
```
┌─────────────────────────────────────────┐
│ Report this review                      │
│                                         │
│ Why are you reporting?                  │
│ ( ) Spam or advertising                 │
│ ( ) Fake review (not a real customer)   │
│ ( ) Inappropriate language              │
│ ( ) Conflicts of interest               │
│ ( ) Other                               │
│                                         │
│ Additional details (optional):          │
│ [                                   ]   │
│                                         │
│ [Cancel]              [Submit Report]   │
└─────────────────────────────────────────┘
```

### Data Storage
Report stored in review meta or a simple table:
```
_listora_review_reports → JSON array:
[
  {"user_id": 5, "reason": "spam", "details": "...", "date": "2026-03-15"},
  {"user_id": 8, "reason": "fake", "details": "", "date": "2026-03-16"}
]
```

### Admin Queue
Reviews with 2+ reports get flagged in moderation queue:
- Yellow warning icon next to review
- "2 reports" badge
- Quick action: [Dismiss Reports] [Remove Review] [Ban User]

### REST API
```
POST /listora/v1/reviews/{id}/report
Body: { reason: "spam", details: "..." }
Auth: Required
```

One report per user per review (prevent abuse).

---

## Block: `listora/listing-reviews`

### Attributes
```json
{
  "attributes": {
    "showSummary": { "type": "boolean", "default": true },
    "showForm": { "type": "boolean", "default": true },
    "perPage": { "type": "number", "default": 10 },
    "defaultSort": { "type": "string", "default": "newest" }
  }
}
```

Can be used standalone (pass listing ID) or inside detail block (auto-detects listing).

---

## REST API

```
GET    /listora/v1/listings/{id}/reviews     → list reviews
POST   /listora/v1/listings/{id}/reviews     → submit review
PUT    /listora/v1/reviews/{id}              → edit own review
DELETE /listora/v1/reviews/{id}              → delete own review
POST   /listora/v1/reviews/{id}/helpful      → vote helpful
POST   /listora/v1/reviews/{id}/reply        → owner reply
GET    /listora/v1/reviews/{id}/summary      → rating breakdown (Pro)
```

---

## Schema.org Integration

Reviews contribute to listing's aggregate rating:
```json
{
  "@type": "Restaurant",
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.5",
    "bestRating": "5",
    "worstRating": "1",
    "ratingCount": "23"
  },
  "review": [
    {
      "@type": "Review",
      "reviewRating": {
        "@type": "Rating",
        "ratingValue": "5"
      },
      "author": { "@type": "Person", "name": "John D." },
      "datePublished": "2026-03-10",
      "reviewBody": "Best Pizza in Manhattan!..."
    }
  ]
}
```

---

## Theme Adaptive CSS

```css
.listora-review {
  padding: var(--wp--preset--spacing--20, 1rem) 0;
  border-block-end: 1px solid var(--wp--preset--color--contrast-3, #eee);
}

.listora-review__stars {
  color: var(--wp--preset--color--luminous-vivid-amber, #fcb900);
}

.listora-review__author {
  font-weight: 600;
  color: var(--wp--preset--color--contrast, #333);
}

.listora-review__content {
  color: var(--wp--preset--color--contrast-2, #555);
  line-height: var(--wp--custom--line-height--body, 1.6);
}

.listora-review__owner-reply {
  margin-inline-start: var(--wp--preset--spacing--20, 1rem);
  padding: var(--wp--preset--spacing--20, 1rem);
  background: var(--wp--preset--color--contrast-4, #f5f5f5);
  border-inline-start: 3px solid var(--wp--preset--color--primary, #0073aa);
  border-radius: var(--wp--custom--border-radius, 4px);
}

.listora-review__bar {
  background: var(--wp--preset--color--contrast-4, #eee);
  border-radius: 2px;
  height: 8px;
}

.listora-review__bar-fill {
  background: var(--wp--preset--color--luminous-vivid-amber, #fcb900);
  border-radius: 2px;
  height: 100%;
}
```

---

## Accessibility

| Element | A11y Feature |
|---------|-------------|
| Star rating input | `role="radiogroup"`, individual stars as `role="radio"` |
| Star display | `aria-label="Rated 4.5 out of 5"` |
| Review form | `<form>` with `<label>` for all fields |
| Helpful button | `aria-label="Mark as helpful"`, `aria-pressed` |
| Review list | `<ol>` ordered list |
| Owner reply | `aria-label="Owner response"`, visually indented |
| Loading more | `aria-live="polite"` on new reviews |
| Sort control | `<select>` with `<label>` |
