# 39 — Niche-Specific Features (Events, Jobs, Healthcare)

## Scope

| | Free | Pro |
|---|---|---|
| Recurring events | Yes | Yes |
| Calendar view block | Yes | Yes |
| Event series linking | — | Yes |
| "Happening Now/Today/Weekend" filters | Yes | Yes |
| Job application tracking | — | Yes |
| Easy Apply (resume submit) | — | Yes |
| Company profiles | — | Yes |
| Date-based expiration for events/jobs | Yes | Yes |
| Conditional fields (context-aware forms) | Yes | Yes |

---

## Events

### Recurring Events

**Data Model:**
```
_listora_event_recurrence → JSON:
{
  "type": "weekly",              // none, daily, weekly, monthly, yearly
  "interval": 1,                 // every N weeks
  "days": [6],                   // day(s) of week (0=Sun, 6=Sat)
  "end_type": "date",            // date, count, never
  "end_date": "2026-12-31",      // if end_type = date
  "end_count": 52,               // if end_type = count
  "exceptions": ["2026-07-04"]   // skip these dates
}
```

**Occurrence Generation:**
- On save, generate next 12 occurrences as `_listora_next_occurrences` JSON
- Display: "Every Saturday, 8:00 AM - 1:00 PM"
- Search: "Next occurrence: Saturday, March 21"
- Cron job regenerates occurrences weekly (for ongoing recurring events)

**UI in Submission Form:**
```
┌─────────────────────────────────────────────────────┐
│ Event Schedule                                      │
│                                                     │
│ Start Date: [ March 21, 2026 ] Time: [ 08:00 ]     │
│ End Date:   [ March 21, 2026 ] Time: [ 13:00 ]     │
│ Timezone:   [ America/New_York ▾ ]                  │
│                                                     │
│ ☑ This event repeats                               │
│                                                     │
│ Frequency: [ Weekly ▾ ]                             │
│ Every [ 1 ] week(s) on:                             │
│ ☐M ☐T ☐W ☐T ☐F ☑S ☐S                            │
│                                                     │
│ Ends:                                               │
│ (•) On date: [ December 31, 2026 ]                  │
│ ( ) After [ 52 ] occurrences                        │
│ ( ) Never                                           │
└─────────────────────────────────────────────────────┘
```

### Calendar View Block: `listora/listing-calendar`

```
┌──────────────────────────────────────────────────────┐
│ ◀  March 2026  ▶                    [Month][Week]   │
├──────────────────────────────────────────────────────┤
│ Sun  │ Mon  │ Tue  │ Wed  │ Thu  │ Fri  │ Sat      │
├──────┼──────┼──────┼──────┼──────┼──────┼──────────┤
│      │      │      │      │      │      │ 1        │
│      │      │      │      │      │      │ 🟢Market│
├──────┼──────┼──────┼──────┼──────┼──────┼──────────┤
│ 2    │ 3    │ 4    │ 5    │ 6    │ 7    │ 8        │
│      │      │      │🔵Jazz│      │🔴Fest│ 🟢Market│
├──────┼──────┼──────┼──────┼──────┼──────┼──────────┤
│ ...                                                  │
└──────────────────────────────────────────────────────┘
```

**Attributes:**
```json
{
  "defaultView": "month",
  "listingType": "event",
  "showWeekView": true,
  "colorByCategory": true
}
```

**Data source:** REST endpoint `GET /listora/v1/search?type=event&date_from=2026-03-01&date_to=2026-03-31`

**Click event dot → popup with event card → link to detail page.**

### Date Filters for Events
Added to search when type = event:

```
┌─────────────────────────────────────────────────────┐
│ When:                                               │
│ [Today] [Tomorrow] [This Weekend] [This Week]       │
│ [This Month] [Custom Range]                         │
│                                                     │
│ Custom: [ From Date ] to [ To Date ]                │
└─────────────────────────────────────────────────────┘
```

**REST params:** `date_from`, `date_to`, `happening_now=1`

**"Happening Now":** Events where `start_date <= NOW() <= end_date` (using event's timezone).

### Event Series (Pro)

Link related events together:
```
_listora_event_series_id → INT (parent event ID)
```

Display on event detail:
```
Part of: Summer Concert Series
→ March 1 — NYC (this event)
→ March 5 — Los Angeles
→ March 10 — Chicago
→ March 15 — Miami
```

---

## Jobs

### Company Profiles (Pro)

**Data Model:**
New taxonomy: `listora_company` (Pro only)

```
Term: Google
  _listora_company_logo     → attachment ID
  _listora_company_website  → "https://careers.google.com"
  _listora_company_size     → "10,001+"
  _listora_company_industry → "Technology"
  _listora_company_description → "Google is..."
  _listora_company_location → "Mountain View, CA"
```

**Company Profile Page:**
Auto-generated at `/company/google/`:
```
┌─────────────────────────────────────────────────────┐
│ [Logo] Google                                       │
│ Technology · 10,001+ employees · Mountain View, CA  │
│ 🌐 careers.google.com                              │
│                                                     │
│ About Google:                                       │
│ Google is a multinational technology company...     │
│                                                     │
│ Open Positions (23):                                │
│ ┌──────────────────────────────────────────────┐   │
│ │ Senior Engineer — $150K-200K — Remote        │   │
│ │ Product Manager — $130K-170K — NYC           │   │
│ │ Designer — $100K-140K — SF                   │   │
│ └──────────────────────────────────────────────┘   │
│                                                     │
│ Company Rating: ★★★★☆ 4.2 (based on 15 reviews)  │
└─────────────────────────────────────────────────────┘
```

**Linking:** Job listings have `_listora_company` taxonomy term. One company → many jobs.

### Application Tracking (Pro)

**Data Model:**
```sql
CREATE TABLE {prefix}listora_applications (
    id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    listing_id   BIGINT(20) UNSIGNED NOT NULL,
    user_id      BIGINT(20) UNSIGNED DEFAULT NULL,
    applicant_name  VARCHAR(200) NOT NULL,
    applicant_email VARCHAR(200) NOT NULL,
    applicant_phone VARCHAR(30) DEFAULT NULL,
    resume_id    BIGINT(20) UNSIGNED DEFAULT NULL,
    cover_letter TEXT DEFAULT NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'applied',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_listing (listing_id),
    KEY idx_status (status)
) {charset_collate};
```

**Statuses:** `applied`, `reviewed`, `shortlisted`, `interviewed`, `offered`, `rejected`, `withdrawn`

**Easy Apply Form (on job detail page):**
```
┌─────────────────────────────────────────────────────┐
│ Apply for: Senior Engineer at Google                │
│                                                     │
│ Full Name *:    [ Jane Smith                    ]   │
│ Email *:        [ jane@example.com              ]   │
│ Phone:          [ (555) 123-4567                ]   │
│                                                     │
│ Resume/CV *:    [📎 Upload PDF/DOC] max 5MB        │
│                                                     │
│ Cover Letter (optional):                            │
│ ┌───────────────────────────────────────────────┐   │
│ │                                               │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│                              [Submit Application]   │
└─────────────────────────────────────────────────────┘
```

**Employer Dashboard (My Listings → Job → Applications):**
```
Applications for: Senior Engineer (23 total)
| Name       | Date    | Status      | Resume | ⋮    |
|------------|---------|-------------|--------|      |
| Jane Smith | Mar 15  | 🟡 New     | [PDF]  | ✎   |
| John Doe   | Mar 14  | 🟢 Short.  | [PDF]  | ✎   |
| Sarah Lee  | Mar 13  | 🔴 Reject. | [PDF]  | ✎   |
```

### Job-Specific Features

**Salary Display:**
```php
// Format salary range
"$80,000 - $100,000 / year"
"$40 - $60 / hour"
"Competitive"
```

**Job Closing (not just expiration):**
- Owner can mark job as "Position Filled" → listing stays visible with "Closed" badge
- Different from expiration: closed jobs show "This position has been filled"
- REST: `POST /listora/v1/listings/{id}/close`

---

## Conditional Fields

### Definition
```json
{
  "key": "price_per_month",
  "label": "Monthly Rent",
  "type": "price",
  "conditional": {
    "field": "listing_action",
    "operator": "equals",
    "value": "rent"
  }
}
```

### Supported Operators
| Operator | Example |
|----------|---------|
| `equals` | Show if `listing_action == "rent"` |
| `not_equals` | Show if `listing_action != "sale"` |
| `contains` | Show if `cuisine` contains "Italian" |
| `not_empty` | Show if `phone` is filled |
| `empty` | Show if `phone` is empty |
| `greater_than` | Show if `bedrooms > 2` |
| `less_than` | Show if `price < 1000` |

### Rendering
- **Admin metabox:** jQuery toggles field visibility on controlling field change
- **Frontend submission:** Interactivity API `data-wp-class--is-hidden` directive
- **Listing detail:** Server-side — only render if condition met
- **REST API:** All fields returned regardless of conditions (client decides display)

---

## User Types (Pro)

### Problem
Job boards need employers vs job seekers. Real estate needs agents vs buyers. The current plan treats all users the same.

### Solution: User Meta, Not Roles
```
_listora_user_type → "listing_owner" | "visitor" | "employer" | "seeker"
```

Set during registration or on first submission/review.

**Impact:**
- Dashboard tabs change based on user type
- "Employer" sees: My Jobs, Applications, Analytics
- "Seeker" sees: Saved Jobs, My Applications, My Reviews
- Site owner configures which user types exist per directory type

**Registration form adds:**
```
I am a:
(•) Business Owner (I want to list my business)
( ) Visitor (I want to browse and review)
```

This is lightweight — no separate roles, just a meta flag that customizes the UX.

---

## Healthcare-Specific

### Insurance Filtering
Healthcare listing type has `insurance_accepted` as multiselect field. But insurance networks have hundreds of options.

**Solution:** `insurance_accepted` uses an autocomplete input (not checkbox list):
```
Insurance Accepted:
[ Type to search... ]
☑ Aetna PPO
☑ Blue Cross Blue Shield
☑ UnitedHealthcare
[+ Add more]
```

Data source: Pre-populated list stored in plugin options (admin can add/remove). ~200 common US insurers bundled.

### Appointment Booking Hooks
```php
// Free plugin provides the hook point
do_action('wb_listora_appointment_button', $listing_id);

// Pro or third-party booking plugin hooks in
add_action('wb_listora_appointment_button', function($id) {
    // Render "Book Appointment" button
    // Link to external booking system or inline widget
});
```

### HIPAA Note
Plugin does NOT store patient data. Contact forms (lead form) only email the provider — no PII stored in database. This is documented for healthcare directory operators.
