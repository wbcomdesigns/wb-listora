# P2-13 — Reverse Listings / Post Your Need

## Scope: Pro Only (NEW -- Competitor Gap)

---

## Overview

Visitors can post "needs" -- structured requests describing what they're looking for. "Looking for a plumber in Brooklyn, budget $200-500." Business owners with matching listings can respond. This creates a two-sided marketplace dynamic: businesses find customers, and customers find businesses. A new CPT `listora_need` powers this, with its own moderation queue, submission form, and matching engine.

### Why It Matters

- Creates demand signals -- businesses see actual customers looking for their services
- Two-sided marketplace increases engagement for both supply and demand sides
- "Post Your Need" is how Thumbtack, Bark.com, and HomeAdvisor built billion-dollar businesses
- Increases listing owner retention -- they receive qualified leads, not just eyeballs
- Email notifications when needs match their type+location create a pull mechanism
- This is a **major competitor gap** -- only Directorist has this as a paid addon ($39/year)

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Homeowner | Post a need for a plumber in my area with budget range | Local plumbers can find me and respond |
| 2 | Business owner | See needs matching my listing type and location | I can proactively reach out to potential customers |
| 3 | Business owner | Respond to a need with my listing profile | The customer sees my business details, reviews, and contact info |
| 4 | Admin | Moderate needs before they go live | Spam and inappropriate requests are filtered out |
| 5 | Business owner | Get email notifications for matching needs | I don't have to check the platform daily |
| 6 | Visitor | Browse needs to see what people are looking for | I understand what services are in demand in my area |
| 7 | Need author | Mark a response as "accepted" when I find my provider | The need closes and stops generating notifications |

---

## Technical Design

### New CPT: `listora_need`

```php
register_post_type('listora_need', [
    'labels'       => [
        'name'          => __('Needs', 'wb-listora-pro'),
        'singular_name' => __('Need', 'wb-listora-pro'),
        'add_new_item'  => __('Post Your Need', 'wb-listora-pro'),
    ],
    'public'            => true,
    'has_archive'       => true,
    'rewrite'           => ['slug' => 'needs', 'with_front' => false],
    'supports'          => ['title', 'editor', 'author'],
    'show_in_rest'      => true,
    'capability_type'   => 'listora_need',
    'map_meta_cap'      => true,
    'taxonomies'        => ['listora_listing_cat', 'listora_listing_location'],
]);
```

### Need Meta Fields

```
post_title              -> "Looking for a plumber in Brooklyn"
post_content            -> "I have a leaking pipe in my kitchen..."
post_status             -> "pending" | "publish" | "closed" | "expired"
post_author             -> 7 (user ID)

Meta:
_listora_need_listing_type  -> "plumber" (which listing type this need targets)
_listora_need_location      -> "Brooklyn, NY" (text location)
_listora_need_lat           -> 40.6782
_listora_need_lng           -> -73.9442
_listora_need_budget_min    -> 200
_listora_need_budget_max    -> 500
_listora_need_currency      -> "USD"
_listora_need_deadline      -> "2026-04-15"
_listora_need_urgency       -> "normal" | "urgent" | "flexible"
_listora_need_response_count -> 3
_listora_need_status        -> "open" | "fulfilled" | "expired"
```

### Need Responses Table

```sql
CREATE TABLE {prefix}listora_need_responses (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    need_id      BIGINT(20) UNSIGNED NOT NULL,
    listing_id   BIGINT(20) UNSIGNED NOT NULL,
    user_id      BIGINT(20) UNSIGNED NOT NULL,
    message      TEXT NOT NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_need (need_id),
    KEY idx_listing (listing_id),
    KEY idx_user (user_id),
    UNIQUE KEY idx_need_listing (need_id, listing_id)
) {charset_collate};
```

**Statuses:** `pending`, `accepted`, `rejected`

**Unique constraint:** One response per listing per need (a business can only respond once).

### Need Submission Form

A simplified 2-step form:

```
Step 1: What do you need?
  -> Title (short description)
  -> Category (select from listing types)
  -> Detailed description (textarea)

Step 2: Where and when?
  -> Location (text + optional map pin)
  -> Budget range (min/max, optional)
  -> Deadline (date picker, optional)
  -> Urgency (normal/urgent/flexible)
  -> Preview & Submit
```

### Matching Engine

```php
class Need_Matcher {
    public function find_matching_listings( int $need_id ): array {
        $listing_type = get_post_meta($need_id, '_listora_need_listing_type', true);
        $lat = (float) get_post_meta($need_id, '_listora_need_lat', true);
        $lng = (float) get_post_meta($need_id, '_listora_need_lng', true);

        global $wpdb;

        // Find listings of matching type within 25km
        return $wpdb->get_results($wpdb->prepare(
            "SELECT si.listing_id, si.title, si.author_id,
                    (6371 * acos(cos(radians(%f)) * cos(radians(g.lat))
                    * cos(radians(g.lng) - radians(%f))
                    + sin(radians(%f)) * sin(radians(g.lat)))) AS distance
             FROM {$wpdb->prefix}listora_search_index si
             JOIN {$wpdb->prefix}listora_geo g ON si.listing_id = g.listing_id
             WHERE si.listing_type = %s
               AND si.status = 'publish'
             HAVING distance < 25
             ORDER BY si.avg_rating DESC, distance ASC
             LIMIT 50",
            $lat, $lng, $lat, $listing_type
        ));
    }
}
```

### Email Notification: New Matching Need

```
Subject: New request near you: "Looking for a plumber in Brooklyn"

Hi John,

A potential customer is looking for a {listing_type} near your listing "{listing_title}":

  "{need_title}"
  {need_description}

  Budget: $200 - $500
  Deadline: April 15, 2026
  Location: Brooklyn, NY (3.2 km from your listing)

Respond to this need:
{need_url}

-- {site_name}
```

### Need Lifecycle

```
Visitor submits need -> status: pending
  -> Admin approves -> status: publish (open)
    -> Matching engine runs, notifies listing owners
    -> Business owners respond
    -> Need author accepts a response -> status: closed (fulfilled)
  -> Admin rejects -> status: rejected

Daily cron:
  -> Needs past deadline -> status: closed (expired)
```

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `includes/needs/class-need-post-type.php` | CPT registration |
| `includes/needs/class-need-matcher.php` | Matching engine (type + geo proximity) |
| `includes/needs/class-need-notifier.php` | Email notifications to matching owners |
| `includes/needs/class-need-response-manager.php` | Response CRUD + accept/reject |
| `includes/needs/class-need-expiration.php` | Deadline-based expiration cron |
| `includes/rest/class-needs-controller.php` | REST endpoints for needs |
| `includes/rest/class-need-responses-controller.php` | Response REST endpoints |
| `includes/admin/class-needs-page.php` | Admin moderation page (Pattern B) |
| `blocks/needs-grid/block.json` | Needs grid block |
| `blocks/needs-grid/render.php` | Needs grid server render |
| `blocks/needs-grid/view.js` | Interactivity API for needs browsing |
| `blocks/need-submission/block.json` | Need submission form block |
| `blocks/need-submission/render.php` | Submission form server render |
| `blocks/need-submission/view.js` | Form interactivity |
| `templates/email/need-match.php` | Email template for matching notification |

### API Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `GET` | `/listora/v1/needs` | Public | List published needs with filters |
| `POST` | `/listora/v1/needs` | Authenticated | Submit a new need |
| `GET` | `/listora/v1/needs/{id}` | Public | Get single need with details |
| `PUT` | `/listora/v1/needs/{id}` | Owner/Admin | Update need |
| `DELETE` | `/listora/v1/needs/{id}` | Owner/Admin | Delete need |
| `POST` | `/listora/v1/needs/{id}/close` | Owner | Close a need (fulfilled) |
| `POST` | `/listora/v1/needs/{id}/respond` | Listing Owner | Respond with listing + message |
| `GET` | `/listora/v1/needs/{id}/responses` | Need Owner | View responses |
| `PUT` | `/listora/v1/needs/{id}/responses/{resp_id}` | Need Owner | Accept/reject response |
| `GET` | `/listora/v1/needs/matching/{listing_id}` | Listing Owner | Find needs matching my listing |

---

## UI Mockup

### Frontend: Needs Grid Block

```
+-------------------------------------------------------------+
| What People Are Looking For                                 |
|                                                             |
| Category: [ All v ]    Location: [ All v ]                 |
|                                                             |
| +-----------------------------------------------------------+
| | Looking for a plumber in Brooklyn                        |
| | [Plumber]  [Brooklyn, NY]  [$200-$500]                   |
| | Deadline: Apr 15  |  URGENT                              |
| | "Leaking pipe in kitchen, need someone ASAP..."          |
| | 3 responses                              [Respond ->]    |
| +-----------------------------------------------------------+
| | Need a family photographer for event                     |
| | [Photographer]  [Manhattan]  [$300-$800]                 |
| | Deadline: May 1  |  Flexible                             |
| | "Looking for a photographer for our family reunion..."   |
| | 0 responses                              [Respond ->]    |
| +-----------------------------------------------------------+
| | Best Italian restaurant for 20-person dinner             |
| | [Restaurant]  [Queens]  [$50-$100/person]                |
| | Deadline: Apr 20  |  Normal                              |
| | "Planning a birthday dinner for 20 people..."            |
| | 5 responses                              [Respond ->]    |
| +-----------------------------------------------------------+
|                                                             |
| [Post Your Need ->]                                         |
+-------------------------------------------------------------+
```

### Frontend: Post Your Need Form

```
+-------------------------------------------------------------+
| Post Your Need                                      Step 1/2|
|                                                             |
| What are you looking for? *                                 |
| [ Looking for a plumber in Brooklyn                     ]   |
|                                                             |
| Category *                                                  |
| [ Plumber                          v ]                      |
|                                                             |
| Describe your need *                                        |
| +-----------------------------------------------------------+
| | I have a leaking pipe under my kitchen sink.             |
| | The leak started yesterday and is getting worse.         |
| | I need someone who can come within the next few days.    |
| +-----------------------------------------------------------+
|                                                             |
|                                       [Next: Details ->]    |
+-------------------------------------------------------------+

+-------------------------------------------------------------+
| Post Your Need                                      Step 2/2|
|                                                             |
| Location *                                                  |
| [ Brooklyn, NY                                          ]   |
|                                                             |
| Budget Range (optional)                                     |
| Min: [ $200     ]    Max: [ $500     ]                     |
|                                                             |
| Deadline (optional)                                         |
| [ 2026-04-15   ]                                           |
|                                                             |
| Urgency                                                     |
| ( ) Flexible -- no rush                                    |
| (*) Normal -- within a week or two                         |
| ( ) Urgent -- ASAP                                         |
|                                                             |
| [<- Back]                             [Preview & Submit ->] |
+-------------------------------------------------------------+
```

### Business Owner: Respond to Need

```
+-------------------------------------------------------------+
| Respond to Need                                             |
|                                                             |
| "Looking for a plumber in Brooklyn"                         |
| Budget: $200-$500  |  Deadline: Apr 15                      |
|                                                             |
| Respond with your listing:                                  |
| [ Brooklyn Plumbing Co.           v ]  (your listings)     |
|                                                             |
| Message to the customer *                                   |
| +-----------------------------------------------------------+
| | Hi! I'm available to help with your leaking pipe.       |
| | I've been a licensed plumber for 15 years and can       |
| | come by as early as tomorrow. My rate for this type     |
| | of repair is typically $250-$350.                       |
| +-----------------------------------------------------------+
|                                                             |
| Your listing profile will be shared with the customer.      |
|                                                             |
|                               [Cancel]  [Send Response ->]  |
+-------------------------------------------------------------+
```

### Business Owner Dashboard: Matching Needs

```
+-------------------------------------------------------------+
| Dashboard > Matching Needs                                  |
|                                                             |
| These needs match your listing type and location:           |
|                                                             |
| +-----------------------------------------------------------+
| | "Looking for a plumber in Brooklyn"                      |
| |    $200-$500  |  3.2 km away  |  Urgent                  |
| |    0 responses yet                       [Respond ->]    |
| +-----------------------------------------------------------+
| | "Need bathroom renovation quote"                         |
| |    $2,000-$5,000  |  5.8 km away  |  Flexible            |
| |    2 responses                           [Respond ->]    |
| +-----------------------------------------------------------+
|                                                             |
| 2 matching needs in your area                               |
+-------------------------------------------------------------+
```

### Admin: Needs Moderation Queue

```
+-------------------------------------------------------------+
| Needs Moderation                                            |
|                                                             |
| [Pending (5)] [Published (23)] [Closed (12)] [Expired (8)]  |
|                                                             |
| | Need                              | Type    | By      |  |
| |-----------------------------------|---------|---------|  |
| | Looking for a plumber in Brooklyn | Plumber | Jane D. | A R |
| | Need photographer for event      | Photo.  | Mike L. | A R |
| | Best Italian for 20 people       | Rest.   | Alex R. | A R |
|                                                             |
| A = Approve   R = Reject                                    |
+-------------------------------------------------------------+
```

---

## Privacy Considerations

- Need author's contact info is NOT shown publicly -- responses go through the platform
- Business owners respond with their listing profile (already public)
- Need owner sees the responding business's listing, rating, and reviews
- Direct contact happens only after need owner accepts a response
- Closed/expired needs are delisted from search but not deleted

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Register `listora_need` CPT + capabilities | 2 |
| 2 | Create `listora_need_responses` table + migration | 1 |
| 3 | Need meta fields registration | 1 |
| 4 | Need submission form block (2-step simplified form) | 5 |
| 5 | Needs grid block (listing view with filters) | 4 |
| 6 | Need detail page template | 3 |
| 7 | Matching engine (type + location + radius query) | 3 |
| 8 | Email notification to matching listing owners | 3 |
| 9 | Response system -- listing owner responds with listing + message | 4 |
| 10 | Need owner views responses + accepts/rejects | 3 |
| 11 | Business owner dashboard: matching needs section | 3 |
| 12 | Admin moderation queue for needs (Pattern B) | 3 |
| 13 | Need expiration cron (deadline-based) | 1 |
| 14 | Need closure (owner marks as fulfilled) | 1 |
| 15 | REST endpoints -- needs CRUD, responses, matching | 5 |
| 16 | Spam prevention (honeypot + rate limit on need submission) | 1 |
| 17 | Response notification to need owner | 1 |
| 18 | Need-related activity in BuddyPress (if BP integration active) | 1 |
| 19 | Automated tests + documentation | 4 |
| **Total** | | **49 hours** |

---

## Competitive Context

| Competitor | Reverse Listings? | Our Advantage |
|-----------|-------------------|---------------|
| GeoDirectory | No | Category-defining feature |
| Directorist | "Post Your Need" addon ($39/year) | Included in Pro bundle, matching engine, email notifications |
| HivePress | "Requests" addon ($39) | Included in Pro, full response lifecycle |
| ListingPro | No | Two-sided marketplace dynamics |
| MyListing | No | Location-based matching, budget ranges |
| Thumbtack | Yes (inspiration) | No per-lead fees, unlimited responses |
| Bark.com | Yes (inspiration) | WordPress-native, no SaaS dependency |

**Our edge:** Most competitors either lack this entirely or sell it as a separate addon. We include it in the Pro bundle. The matching engine automatically finds relevant business owners by type and proximity, and email notifications create a pull mechanism that keeps listing owners engaged. Combined with BuddyPress integration (P2-12), this creates a true community marketplace -- not just a directory. Unlike Thumbtack/Bark which charge per lead ($15-$100+), our system has zero per-use fees.

---

## Effort Estimate

**Total: ~49 hours (6-7 dev days)**

- CPT + data model: 4h
- Submission form block: 5h
- Needs grid block: 4h
- Need detail page: 3h
- Matching engine: 3h
- Notifications: 4h
- Response system: 7h
- Dashboard integration: 3h
- Admin moderation: 3h
- REST API: 5h
- Expiration + closure: 2h
- Privacy + spam: 2h
- Tests + docs: 4h
