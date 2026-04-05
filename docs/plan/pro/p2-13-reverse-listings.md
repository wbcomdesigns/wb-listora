# P2-13 — Reverse Listings / Post Your Need (Pro)

## Overview

Visitors post "needs" — "Looking for a plumber in Brooklyn, budget $200-500" — and businesses can respond with their listing. Creates a two-sided marketplace dynamic that no other WordPress directory plugin offers.

## User Stories

- **Homeowner:** "I need a plumber who can fix a leaky pipe this weekend. Budget $200-500."
- **Business owner:** "I see 3 plumbing requests in my area. I can respond with my listing and a custom quote."
- **Directory admin:** "Reverse listings increase engagement — visitors come back to check responses."

## Technical Design

### New CPT: `listora_need`
```php
register_post_type( 'listora_need', array(
    'labels'       => array( 'name' => 'Needs', 'singular_name' => 'Need' ),
    'public'       => true,
    'show_in_rest' => true,
    'supports'     => array( 'title', 'editor', 'author' ),
    'has_archive'  => true,
    'rewrite'      => array( 'slug' => 'needs' ),
) );
```

### Need Fields (post meta)
- `_listora_need_category` — listing category (what type of service)
- `_listora_need_location` — location text + lat/lng
- `_listora_need_budget_min` / `_listora_need_budget_max` — budget range
- `_listora_need_deadline` — when they need it by
- `_listora_need_status` — open / fulfilled / expired
- `_listora_need_responses` — count of responses

### Response System
- Business owners click "Respond" on a need → links their listing + adds a message
- Stored in `listora_need_responses` table: id, need_id, listing_id, user_id, message, created_at
- Need author gets email notification for each response
- Need author can mark a response as "accepted" → need status = fulfilled

### Frontend Blocks
- `listora/needs-grid` — shows open needs with filters (category, location, budget)
- `listora/post-need` — submission form for visitors to post a need

### REST API
```
GET    /listora/v1/needs              — list open needs (public)
POST   /listora/v1/needs              — create a need (auth)
GET    /listora/v1/needs/{id}         — single need with responses
POST   /listora/v1/needs/{id}/respond — respond with listing (business owner)
PUT    /listora/v1/needs/{id}         — update / mark fulfilled
DELETE /listora/v1/needs/{id}         — delete own need
```

### Matching Engine
- When a need is posted, auto-find listings matching the category + location
- Email those listing owners: "Someone in your area is looking for {category}!"
- Dashboard tab for business owners: "Matching Needs (3)" with respond buttons

### Admin Moderation
- Needs go through moderation (same as listings)
- Admin can approve/reject/delete needs
- Pattern B list page in admin

### Competitive Context
- **Directorist** has this as a paid addon ("Post Your Need" — $39/year)
- **No other competitor** has this built-in
- We include it in the Pro bundle — another reason our Pro is better value

### Effort: ~6hr
