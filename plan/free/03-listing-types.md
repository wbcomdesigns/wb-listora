# 03 — Listing Types

## Scope

| | Free | Pro |
|---|---|---|
| 10 default listing types | Yes | Yes |
| Create custom listing types | Yes | Yes |
| Type-specific fields | Yes | Yes |
| Type-specific categories | Yes | Yes |
| Type-specific search filters | Yes | Yes |
| Type-specific card layout | Yes | Yes + advanced layouts |
| Type-specific detail layout | Yes | Yes + advanced layouts |
| Type-specific Schema.org | Yes | Yes |
| Repeater fields per type | — | Yes |

---

## Architecture

Listing types are **taxonomy terms** in `listora_listing_type` taxonomy. Each term's meta defines the type's complete behavior — fields, categories, schema, layout, filters.

**Why taxonomy terms (not config files or custom tables):**
- Users can add types without code
- Familiar WP pattern (terms have slugs, names, descriptions, meta)
- REST API exposed automatically via `show_in_rest`
- Can be filtered, cached, and queried like any taxonomy

---

## Default Listing Types (Created on Activation)

### 1. Business (LocalBusiness)
**Use case:** General business directory, Yellow Pages style
**Default categories:** Retail, Services, Food & Drink, Health, Automotive, Home, Finance, Legal, Education, Other
**Key fields:**

| Field | Type | Required | Searchable | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----------:|:----:|-------------|
| address | map_location | Yes | Yes | Yes | Yes | address |
| phone | phone | No | No | No | Yes | telephone |
| website | url | No | No | No | No | url |
| email | email | No | No | No | No | email |
| business_hours | business_hours | No | No | Yes (open now) | Yes (badge) | openingHoursSpecification |
| price_range | select ($-$$$$) | No | No | Yes | Yes | priceRange |
| year_established | number | No | No | No | No | foundingDate |
| social_links | social_links | No | No | No | No | sameAs |
| gallery | gallery | No | No | No | No | image |

---

### 2. Restaurant (Restaurant)
**Use case:** Food & dining directories, Yelp-style
**Default categories:** Italian, Chinese, Japanese, Mexican, Indian, Thai, American, French, Mediterranean, Fast Food, Cafe, Bar, Bakery, Seafood, Vegan
**Key fields:**

| Field | Type | Required | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----:|-------------|
| address | map_location | Yes | Yes | Yes | address |
| phone | phone | No | No | Yes | telephone |
| cuisine | multiselect | No | Yes | Yes | servesCuisine |
| price_range | select ($-$$$$) | No | Yes | Yes | priceRange |
| business_hours | business_hours | No | Yes (open now) | Yes | openingHoursSpecification |
| menu_url | url | No | No | No | hasMenu |
| reservations | select (Yes/No/Online) | No | Yes | No | acceptsReservations |
| delivery | checkbox | No | Yes | Yes (badge) | — |
| takeout | checkbox | No | Yes | Yes (badge) | — |
| dietary_options | multiselect | No | Yes | No | — |
| gallery | gallery | No | No | No | image |
| social_links | social_links | No | No | No | sameAs |

---

### 3. Real Estate (RealEstateListing)
**Use case:** Property listing, Zillow-style
**Default categories:** House, Apartment, Condo, Townhouse, Villa, Land, Commercial, Industrial
**Key fields:**

| Field | Type | Required | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----:|-------------|
| address | map_location | Yes | Yes | Yes | address |
| listing_action | select (Sale/Rent) | Yes | Yes | Yes (badge) | — |
| price | price | Yes | Yes (range) | Yes | price |
| bedrooms | number | No | Yes | Yes | numberOfRooms |
| bathrooms | number | No | Yes | Yes | — |
| area_sqft | number | No | Yes (range) | Yes | floorSize |
| property_type | select | No | Yes | Yes | — |
| year_built | number | No | Yes (range) | No | yearBuilt |
| parking | select (0-5+) | No | Yes | No | — |
| lot_size | number | No | No | No | — |
| gallery | gallery | No | No | No | image |
| virtual_tour_url | url | No | No | No | — |

---

### 4. Hotel (Hotel)
**Use case:** Accommodation directories, Booking-style
**Default categories:** Hotel, Motel, Resort, B&B, Hostel, Boutique Hotel, Villa Rental, Guesthouse
**Key fields:**

| Field | Type | Required | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----:|-------------|
| address | map_location | Yes | Yes | Yes | address |
| phone | phone | No | No | Yes | telephone |
| star_rating | select (1-5 stars) | No | Yes | Yes | starRating |
| check_in_time | time | No | No | No | checkinTime |
| check_out_time | time | No | No | No | checkoutTime |
| price_per_night | price | No | Yes (range) | Yes | priceRange |
| rooms | number | No | No | No | numberOfRooms |
| amenities | multiselect | No | Yes | No | amenityFeature |
| booking_url | url | No | No | No | url |
| business_hours | business_hours | No | Yes (open now) | No | openingHoursSpecification |
| gallery | gallery | No | No | No | image |
| social_links | social_links | No | No | No | sameAs |

---

### 5. Event (Event)
**Use case:** Event directories, Eventbrite-style
**Default categories:** Concert, Conference, Workshop, Festival, Sports, Networking, Charity, Exhibition, Comedy, Theater
**Key fields:**

| Field | Type | Required | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----:|-------------|
| address | map_location | Yes | Yes | Yes | location |
| start_date | datetime | Yes | Yes | Yes | startDate |
| end_date | datetime | No | Yes | No | endDate |
| venue_name | text | No | No | Yes | location.name |
| ticket_price | price | No | Yes (range) | Yes | offers.price |
| ticket_url | url | No | No | No | offers.url |
| performers | text | No | Yes | No | performer |
| organizer | text | No | No | No | organizer |
| capacity | number | No | No | No | maximumAttendeeCapacity |
| event_recurrence | text | No | Yes (recurring) | Yes (badge) | — |
| gallery | gallery | No | No | No | image |

---

### 6. Job (JobPosting)
**Use case:** Job board directories, Indeed-style
**Default categories:** Technology, Healthcare, Finance, Marketing, Engineering, Education, Design, Sales, Admin, Legal, Hospitality, Manufacturing
**Key fields:**

| Field | Type | Required | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----:|-------------|
| company_name | text | Yes | Yes | Yes | hiringOrganization.name |
| company_logo | file | No | No | Yes | hiringOrganization.logo |
| address | map_location | No | Yes | Yes | jobLocation |
| salary_min | number | No | Yes (range) | Yes | baseSalary.minValue |
| salary_max | number | No | Yes (range) | Yes | baseSalary.maxValue |
| salary_type | select (hourly/monthly/yearly) | No | Yes | No | baseSalary.unitText |
| employment_type | select | Yes | Yes | Yes | employmentType |
| experience_level | select | No | Yes | No | experienceRequirements |
| remote_option | select (remote/hybrid/onsite) | No | Yes | Yes (badge) | jobLocationType |
| apply_url | url | No | No | No | url |
| deadline | date | No | Yes | Yes | validThrough |
| skills | multiselect | No | Yes | No | skills |

---

### 7. Doctor/Healthcare (Physician)
**Use case:** Healthcare provider directories, ZocDoc-style
**Default categories:** General Practitioner, Dentist, Dermatologist, Cardiologist, Pediatrician, Orthopedist, Ophthalmologist, Psychiatrist, Neurologist, Gynecologist
**Key fields:**

| Field | Type | Required | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----:|-------------|
| address | map_location | Yes | Yes | Yes | address |
| phone | phone | Yes | No | Yes | telephone |
| specialty | multiselect | Yes | Yes | Yes | medicalSpecialty |
| qualifications | text | No | No | No | qualification |
| insurance_accepted | multiselect | No | Yes | No | — |
| hospital_affiliation | text | No | Yes | No | hospitalAffiliation |
| appointment_url | url | No | No | No | — |
| consultation_fee | price | No | Yes (range) | Yes | — |
| languages_spoken | multiselect | No | Yes | No | knowsLanguage |
| experience_years | number | No | Yes | No | — |
| business_hours | business_hours | No | Yes (open now) | Yes | openingHoursSpecification |
| gallery | gallery | No | No | No | image |

---

### 8. Education/Course (Course)
**Use case:** Course/training directories, Coursera-style
**Default categories:** Online Course, University, Tutoring, Language School, Coding Bootcamp, Professional Certification, K-12, Graduate, Vocational
**Key fields:**

| Field | Type | Required | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----:|-------------|
| provider | text | Yes | Yes | Yes | provider.name |
| address | map_location | No | Yes | No | location |
| course_level | select (beginner/intermediate/advanced/all) | No | Yes | Yes | educationalLevel |
| duration | text | No | Yes | Yes | timeRequired |
| price | price | No | Yes (range) | Yes | offers.price |
| enrollment_url | url | No | No | No | url |
| start_date | date | No | Yes | Yes | courseInstance.startDate |
| format | select (online/in-person/hybrid) | No | Yes | Yes (badge) | deliveryMode |
| certification | checkbox | No | Yes | Yes (badge) | educationalCredentialAwarded |
| prerequisites | textarea | No | No | No | coursePrerequisites |
| gallery | gallery | No | No | No | image |

---

### 9. Place/Attraction (TouristAttraction)
**Use case:** Tourism/travel directories, TripAdvisor-style
**Default categories:** Museum, Park, Monument, Beach, Temple, Zoo, Amusement Park, Garden, Historic Site, Viewpoint, Market
**Key fields:**

| Field | Type | Required | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----:|-------------|
| address | map_location | Yes | Yes | Yes | address |
| phone | phone | No | No | Yes | telephone |
| admission_fee | price | No | Yes (free/paid) | Yes | isAccessibleForFree |
| business_hours | business_hours | No | Yes (open now) | Yes | openingHoursSpecification |
| accessibility | multiselect | No | Yes | No | accessibilityFeature |
| best_time_to_visit | text | No | No | No | — |
| duration_suggested | text | No | No | Yes | timeRequired |
| website | url | No | No | No | url |
| gallery | gallery | No | No | No | image |
| social_links | social_links | No | No | No | sameAs |

---

### 10. Classified (Product)
**Use case:** Classifieds marketplace, Craigslist-style
**Default categories:** Vehicles, Electronics, Furniture, Clothing, Sports, Books, Collectibles, Tools, Pets, Services, Other
**Key fields:**

| Field | Type | Required | Filterable | Card | Schema Prop |
|-------|------|:--------:|:----------:|:----:|-------------|
| price | price | Yes | Yes (range) | Yes | offers.price |
| condition | select (new/like-new/used/refurbished/for-parts) | No | Yes | Yes | itemCondition |
| availability | select (in-stock/sold/reserved) | No | Yes | Yes (badge) | availability |
| address | map_location | No | Yes | Yes | — |
| seller_name | text | No | No | No | seller.name |
| seller_phone | phone | No | No | No | seller.telephone |
| seller_email | email | No | No | No | seller.email |
| gallery | gallery | No | No | No | image |

---

## Listing Type Data Model

### Taxonomy Registration
```
register_taxonomy('listora_listing_type', 'listora_listing', [
    'hierarchical'      => false,
    'public'            => true,
    'show_in_rest'      => true,
    'rest_base'         => 'listing-types',
    'show_admin_column' => true,
    'rewrite'           => ['slug' => 'listing-type'],
    'labels'            => [...],
])
```

### Term Meta Structure

Each listing type term stores this metadata:

```
_listora_schema_type       → string: "Restaurant"
_listora_icon              → string: "utensils" (dashicon or custom icon name)
_listora_color             → string: "#E74C3C" (brand color for badges/pins)
_listora_field_groups      → JSON: array of field group definitions (see 04-custom-fields.md)
_listora_allowed_categories → JSON: array of category term IDs scoped to this type
_listora_card_fields       → JSON: array of field keys to show on card
_listora_card_layout       → string: "standard" | "horizontal" | "compact" (Pro adds more)
_listora_detail_layout     → string: "tabbed" | "sidebar" | "full-width" (Pro adds more)
_listora_search_filters    → JSON: array of field keys to use as search filters
_listora_map_enabled       → boolean: show map for this type
_listora_map_pin_icon      → string: custom map pin icon identifier
_listora_review_enabled    → boolean
_listora_review_criteria   → JSON: array of review criteria (Pro: multi-criteria)
_listora_submission_enabled → boolean: allow frontend submission for this type
_listora_moderation        → string: "auto_approve" | "manual_review"
_listora_expiration_days   → int: 0 = never expires
_listora_is_default        → boolean: created by plugin (can't be deleted)
```

---

## Listing Type Registry (`class-listing-type-registry.php`)

### Purpose
In-memory cache of all listing types with their complete config. Avoids querying term meta repeatedly.

### API
```php
$registry = Listing_Type_Registry::instance();

$registry->get_all();                    // All types as Listing_Type objects
$registry->get($slug);                   // Single type by slug
$registry->get_field_groups($slug);      // Field groups for a type
$registry->get_search_filters($slug);    // Filterable fields for a type
$registry->get_card_fields($slug);       // Fields shown on card
$registry->get_schema_type($slug);       // Schema.org type name
$registry->flush();                      // Clear cache (after type edit)
```

### Caching
- Loaded once per request from object cache
- Flushed when any listing type term meta is updated
- Object cache key: `wb_listora_type_registry`

---

## Admin UI: Listing Type Manager

### List View
Classic WP List Table at `Listora → Listing Types`

| Column | Content |
|--------|---------|
| Icon + Name | Type icon and clickable name |
| Slug | URL slug |
| Fields | Count of custom fields |
| Categories | Count of assigned categories |
| Listings | Count of published listings |
| Schema | Schema.org type |

Bulk actions: Delete (non-default types only)

### Add/Edit Screen

Tabbed interface using WP admin postbox/metabox patterns:

**Tab 1: General**
```
┌─────────────────────────────────────────────┐
│ General Settings                            │
│                                             │
│ Name:        [ Restaurant               ]   │
│ Slug:        [ restaurant               ]   │
│ Description: [ Food and dining listings ]   │
│ Icon:        [ 🍽️ ] [Choose Icon]           │
│ Color:       [ #E74C3C ] [Pick]             │
│ Schema Type: [ Restaurant            ▾ ]    │
│                                             │
│ ☑ Enable map for this type                 │
│ ☑ Enable reviews                           │
│ ☑ Enable frontend submission               │
│ Moderation:  ( ) Auto-approve (•) Manual    │
│ Expiration:  [ 365 ] days (0 = never)       │
└─────────────────────────────────────────────┘
```

**Tab 2: Fields**
See `04-custom-fields.md` for the field builder UI

**Tab 3: Categories**
```
┌─────────────────────────────────────────────┐
│ Categories for this Type                    │
│                                             │
│ These categories will be available when     │
│ creating/filtering listings of this type.   │
│                                             │
│ ☑ Italian          ☑ Chinese               │
│ ☑ Japanese         ☑ Mexican               │
│ ☑ Indian           ☐ Thai                  │
│ ☑ American         ☐ French                │
│                                             │
│ [+ Add New Category]                        │
│                                             │
│ Quick add: [ New category name ] [Add]      │
└─────────────────────────────────────────────┘
```

**Tab 4: Search Filters**
```
┌─────────────────────────────────────────────┐
│ Search Filters                              │
│                                             │
│ Which fields should appear as filters?      │
│ Drag to reorder.                            │
│                                             │
│ ≡ ☑ Category        (dropdown)             │
│ ≡ ☑ Location        (location picker)      │
│ ≡ ☑ Cuisine         (multi-checkbox)       │
│ ≡ ☑ Price Range     (dropdown)             │
│ ≡ ☑ Open Now        (toggle)               │
│ ≡ ☐ Delivery        (checkbox)             │
│ ≡ ☐ Reservations    (dropdown)             │
│                                             │
│ Filter display: (•) Sidebar  ( ) Horizontal │
└─────────────────────────────────────────────┘
```

**Tab 5: Display**
```
┌─────────────────────────────────────────────┐
│ Card Display                                │
│                                             │
│ Fields shown on listing card:               │
│ ≡ ☑ Rating                                 │
│ ≡ ☑ Cuisine                                │
│ ≡ ☑ Price Range                            │
│ ≡ ☑ Open Now (badge)                       │
│ ≡ ☐ Phone                                  │
│                                             │
│ Card layout:                                │
│ [Standard ▾]                                │
│ ┌─────┐ ┌─────────┐ ┌───┐                 │
│ │     │ │         │ │   │                  │
│ │Std  │ │Horizntl │ │Cmp│                  │
│ │     │ │         │ │   │                  │
│ └─────┘ └─────────┘ └───┘                 │
│                                             │
│ Detail layout:                              │
│ [Tabbed ▾]                                  │
│ ┌─────┐ ┌─────┐ ┌─────┐                   │
│ │Tabs │ │Side │ │Full │                    │
│ └─────┘ └─────┘ └─────┘                   │
└─────────────────────────────────────────────┘
```

---

## Adding a New Listing Type (Site Owner Flow)

### Simple Path (From Wizard or Templates)
1. Click "Add New Listing Type"
2. Choose from template gallery: Business, Restaurant, Real Estate, Hotel, Event, Job, Healthcare, Education, Place, Classified, **or Blank**
3. Template pre-fills: name, icon, fields, categories, schema type, filters, card config
4. Site owner tweaks name/slug and categories
5. Save → page auto-created with blocks

### Custom Path (From Blank)
1. Click "Add New Listing Type" → choose "Blank"
2. Fill in name, slug, icon, schema type
3. Add field groups and fields via drag-drop builder
4. Create or assign categories
5. Configure search filters
6. Set card display fields
7. Save → page auto-created with blocks

---

## Multi-Type Interactions

### Admin: Creating a Listing
1. Click "Add New Listing"
2. **First thing**: select listing type from dropdown (or radio buttons if < 5 types)
3. On type selection → metabox dynamically loads type-specific fields via AJAX
4. Category metabox filters to show only this type's categories
5. Feature metabox shows only relevant features

### Frontend: Submitting a Listing
1. Step 1 of submission form: choose listing type (visual cards)
2. Remaining steps show type-specific fields
3. Categories scoped to selected type

### Search: Filtering by Type
1. Search block shows type selector (tabs or dropdown)
2. On type change → filter fields update to match type
3. "All Types" shows common filters only (keyword, location, category)
4. Type-specific page → type is pre-selected, filters are type-specific

### Archive/Browse
- `/listings/` → all types, with type tabs or type cards at top
- `/restaurants/` → auto-created page with search+grid filtered to restaurant type
- `/listing-type/restaurant/` → taxonomy archive (also works, redirects to page)

---

## REST API

| Endpoint | Method | Auth | Response |
|----------|--------|------|----------|
| `/listora/v1/listing-types` | GET | Public | All types with field definitions |
| `/listora/v1/listing-types/{slug}` | GET | Public | Single type with full schema |
| `/listora/v1/listing-types/{slug}/fields` | GET | Public | Field groups for type |
| `/listora/v1/listing-types/{slug}/categories` | GET | Public | Categories scoped to type |

---

## Theme Compatibility

- Type icons use dashicons or SVG sprites (no emoji in production)
- Type color used for: map pins, badges, category cards — via CSS custom property `--listora-type-color`
- Admin UI uses WP admin components (no custom UI framework)
- Frontend type selector inherits theme button/card styles

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Delete a listing type with existing listings | Warn user, require confirmation, move listings to "Uncategorized" type |
| Two types with same field key | Field keys use `_listora_{key}` meta prefix. Keys must be unique across all types sharing the CPT. If a Restaurant has 'phone' and a Hotel has 'phone', they share the same `_listora_phone` meta key — this is intentional since the listing can only be one type. The Listing Type Registry validates key uniqueness on type creation and warns if a key conflicts with a different field definition in another type. |
| Type has no fields | Show only title, content, featured image (standard WP fields) |
| Type has no categories | Category filter hidden in search for this type |
| 20+ listing types | List table pagination, searchable type selector (select2-style) |
| Rename a type | Slug stays the same, name updates. Auto-created page title updates. |
