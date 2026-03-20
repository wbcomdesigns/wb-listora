# 04 — Custom Fields

## Scope

| | Free | Pro |
|---|---|---|
| All field types (text, select, gallery, etc.) | Yes | Yes |
| Field groups with drag-drop ordering | Yes | Yes |
| Per-type field configuration | Yes | Yes |
| Searchable/filterable flags | Yes | Yes |
| REST API exposure | Yes | Yes |
| Repeater field (dynamic sub-groups) | — | Yes |

---

## Architecture

Fields are defined **per listing type** (stored in the type's term meta). They are NOT global — each type has its own independent field configuration.

### Three-Layer System

1. **Field Registry** — Knows all available field types (text, select, gallery, etc.)
2. **Field Group** — A named collection of fields (e.g., "Basic Info", "Location", "Media")
3. **Field** — A single data point (e.g., "price_range", "phone", "address")

### Data Flow
```
Listing Type (term meta) → Field Groups → Fields → Post Meta Values
     ↓                                                    ↓
  Defines structure                               Stores actual data
```

---

## Field Types

### Basic Fields

| Type | Input UI | Storage | Sanitize | REST Schema |
|------|----------|---------|----------|-------------|
| `text` | `<input type="text">` | string | `sanitize_text_field` | `string` |
| `textarea` | `<textarea>` | string | `sanitize_textarea_field` | `string` |
| `wysiwyg` | WP Editor (simplified) | string | `wp_kses_post` | `string` |
| `number` | `<input type="number">` | int/float | `floatval` | `number` |
| `url` | `<input type="url">` | string | `esc_url_raw` | `string, format: uri` |
| `email` | `<input type="email">` | string | `sanitize_email` | `string, format: email` |
| `phone` | `<input type="tel">` | string | `sanitize_text_field` | `string` |

### Choice Fields

| Type | Input UI | Storage | REST Schema |
|------|----------|---------|-------------|
| `select` | `<select>` | string | `string, enum: [...]` |
| `multiselect` | Multi-checkbox or multi-select | JSON array | `array of string` |
| `checkbox` | Single `<input type="checkbox">` | boolean (1/0) | `boolean` |
| `radio` | `<input type="radio">` group | string | `string, enum: [...]` |

### Date/Time Fields

| Type | Input UI | Storage | REST Schema |
|------|----------|---------|-------------|
| `date` | Date picker | `Y-m-d` string | `string, format: date` |
| `time` | Time picker | `H:i` string | `string` |
| `datetime` | DateTime picker | `Y-m-d H:i:s` string | `string, format: date-time` |

### Money Fields

| Type | Input UI | Storage | REST Schema |
|------|----------|---------|-------------|
| `price` | Number input + currency selector | JSON `{"amount": 150, "currency": "USD"}` | `object` |

### Media Fields

| Type | Input UI | Storage | REST Schema |
|------|----------|---------|-------------|
| `gallery` | WP Media Library multi-select | JSON array of attachment IDs | `array of integer` |
| `file` | WP Media Library single-select | attachment ID | `integer` |
| `video` | URL input (YouTube/Vimeo/direct) | string URL | `string, format: uri` |

### Location Fields

| Type | Input UI | Storage | REST Schema |
|------|----------|---------|-------------|
| `map_location` | Address input + map picker | JSON `{"address": "...", "lat": 40.7, "lng": -74.0, "city": "...", "state": "...", "country": "...", "postal_code": "..."}` | `object` |

**Map Location UI (Frontend):**
```
┌─────────────────────────────────────────────┐
│ Address                                     │
│ [ 123 Main Street, New York, NY 10001    ]  │
│                                             │
│ ┌─────────────────────────────────────┐     │
│ │                                     │     │
│ │         [ Leaflet Map ]             │     │
│ │         📍 Draggable pin            │     │
│ │                                     │     │
│ └─────────────────────────────────────┘     │
│                                             │
│ Lat: [ 40.7128 ]  Lng: [ -74.0060 ]        │
│ City: [ New York ] State: [ NY ]            │
│ Country: [ US ]    Zip: [ 10001 ]           │
└─────────────────────────────────────────────┘
```

- Address input triggers geocoding → map updates → lat/lng populate
- Pin is draggable → reverse geocodes → address updates
- City/state/country auto-fill from geocoding result
- Pro: Google Places autocomplete on address input

### Structured Fields

| Type | Input UI | Storage | REST Schema |
|------|----------|---------|-------------|
| `business_hours` | 7-day schedule builder | JSON array | `array of object` |
| `social_links` | Platform + URL pairs | JSON array | `array of object` |
| `rating` | 1-5 star input | integer (1-5) | `integer, min: 1, max: 5` |
| `color` | Color picker | hex string | `string` |

**Business Hours UI:**
```
┌─────────────────────────────────────────────┐
│ Business Hours                              │
│                                             │
│ Monday    [09:00 ▾] - [17:00 ▾]  ☐ Closed │
│ Tuesday   [09:00 ▾] - [17:00 ▾]  ☐ Closed │
│ Wednesday [09:00 ▾] - [17:00 ▾]  ☐ Closed │
│ Thursday  [09:00 ▾] - [17:00 ▾]  ☐ Closed │
│ Friday    [09:00 ▾] - [21:00 ▾]  ☐ Closed │
│ Saturday  [10:00 ▾] - [22:00 ▾]  ☐ Closed │
│ Sunday    [         Closed         ] ☑     │
│                                             │
│ Timezone: [ America/New_York ▾ ]            │
│ ☐ Open 24 hours (apply to all)             │
└─────────────────────────────────────────────┘
```

**Social Links UI:**
```
┌─────────────────────────────────────────────┐
│ Social Links                                │
│                                             │
│ ≡ [Facebook  ▾] [ https://facebook.com/... ]│
│ ≡ [Instagram ▾] [ https://instagram.com/.. ]│
│ ≡ [Twitter   ▾] [ https://x.com/...        ]│
│ [+ Add Social Link]                         │
└─────────────────────────────────────────────┘
```

### Pro-Only Fields

| Type | Input UI | Storage | REST Schema |
|------|----------|---------|-------------|
| `repeater` | Dynamic sub-group (add/remove rows) | JSON array of objects | `array of object` |

**Repeater example (Menu Sections):**
```
┌─────────────────────────────────────────────┐
│ Menu Sections                               │
│                                             │
│ ┌─────────────────────────────────────────┐ │
│ │ Section: [ Appetizers ]                 │ │
│ │ Items:                                  │ │
│ │   Name: [Spring Rolls] Price: [$8.99]   │ │
│ │   Name: [Soup]         Price: [$6.99]   │ │
│ │   [+ Add Item]                          │ │
│ └─────────────────────────────────────────┘ │
│ ┌─────────────────────────────────────────┐ │
│ │ Section: [ Main Course ]                │ │
│ │ ...                                     │ │
│ └─────────────────────────────────────────┘ │
│ [+ Add Section]                             │
└─────────────────────────────────────────────┘
```

---

## Field Definition Structure

```json
{
  "key": "price_range",
  "label": "Price Range",
  "type": "select",
  "description": "How expensive is this restaurant?",
  "placeholder": "Select price range",
  "default_value": "",
  "options": [
    {"value": "$", "label": "$ — Budget"},
    {"value": "$$", "label": "$$ — Moderate"},
    {"value": "$$$", "label": "$$$ — Upscale"},
    {"value": "$$$$", "label": "$$$$ — Fine Dining"}
  ],
  "required": false,
  "searchable": true,
  "filterable": true,
  "show_in_card": true,
  "show_in_detail": true,
  "show_in_rest": true,
  "show_in_admin": true,
  "schema_prop": "priceRange",
  "filter_type": "dropdown",
  "css_class": "",
  "width": "50",
  "pro_only": false,
  "conditional": null,
  "order": 3
}
```

### Filter Type Options
When `filterable: true`, the filter UI depends on field type:

| Field Type | Default Filter UI | Alternative |
|-----------|-------------------|-------------|
| select | Dropdown | Radio buttons |
| multiselect | Multi-checkbox | Tag pills |
| checkbox | Toggle switch | — |
| number | Number input | Range slider (Pro) |
| price | Number input | Range slider (Pro) |
| rating | Star buttons | Min-star dropdown |
| date | Date picker | Date range picker |
| business_hours | "Open Now" toggle | — |
| map_location | Location input + radius | — |

---

## Field Groups

Fields are organized into **groups** that render as sections/tabs on the detail page and as fieldsets in forms.

```json
{
  "key": "basic_info",
  "label": "Basic Information",
  "description": "Essential details about this listing",
  "icon": "info",
  "order": 1,
  "fields": [
    { "key": "phone", ... },
    { "key": "email", ... },
    { "key": "website", ... }
  ]
}
```

**Default groups per type:**
1. **Basic Information** — Core fields (phone, email, website, etc.)
2. **Location** — Address, map
3. **Details** — Type-specific fields (cuisine, bedrooms, salary, etc.)
4. **Hours & Availability** — Business hours, events dates
5. **Media** — Gallery, video
6. **Social & Web** — Social links, website

---

## Meta Storage

### Convention
All field values stored as post meta with `_listora_` prefix:
```
Field key: "price_range"
Meta key:  "_listora_price_range"
```

### Registration
All fields registered via `register_post_meta()`:
```php
register_post_meta('listora_listing', '_listora_price_range', [
    'type'              => 'string',
    'single'            => true,
    'show_in_rest'      => true,
    'sanitize_callback' => 'sanitize_text_field',
    'auth_callback'     => function() { return current_user_can('edit_listora_listings'); },
]);
```

### Complex Types
Gallery, business_hours, social_links, map_location store JSON:
```php
register_post_meta('listora_listing', '_listora_gallery', [
    'type'         => 'array',
    'single'       => true,
    'show_in_rest' => [
        'schema' => [
            'type'  => 'array',
            'items' => ['type' => 'integer'],
        ],
    ],
    'sanitize_callback' => function($value) {
        return array_map('absint', (array) $value);
    },
]);
```

---

## Admin Field Builder UI

Located in the **Fields tab** of the Listing Type editor:

```
┌─────────────────────────────────────────────┐
│ Fields for: Restaurant                      │
│                                             │
│ ┌─ Basic Information ──────────────────┐   │
│ │ ≡ Phone        phone     Required    │   │
│ │ ≡ Email        email                 │   │
│ │ ≡ Website      url                   │   │
│ │ [+ Add Field]                        │   │
│ └──────────────────────────────────────┘   │
│                                             │
│ ┌─ Location ───────────────────────────┐   │
│ │ ≡ Address      map_location Required │   │
│ │ [+ Add Field]                        │   │
│ └──────────────────────────────────────┘   │
│                                             │
│ ┌─ Details ────────────────────────────┐   │
│ │ ≡ Cuisine      multiselect Filterable│   │
│ │ ≡ Price Range  select     Filterable │   │
│ │ ≡ Delivery     checkbox   Filterable │   │
│ │ [+ Add Field]                        │   │
│ └──────────────────────────────────────┘   │
│                                             │
│ [+ Add Field Group]                         │
└─────────────────────────────────────────────┘
```

**Clicking a field expands inline editor:**
```
┌─────────────────────────────────────────────┐
│ ≡ Price Range ▾                    [Delete] │
│                                             │
│ Label:  [ Price Range ]                     │
│ Key:    [ price_range ] (auto-generated)    │
│ Type:   [ Select ▾ ]                        │
│                                             │
│ Options:                                    │
│   ≡ [ $ ]  — [ Budget ]                    │
│   ≡ [ $$ ] — [ Moderate ]                  │
│   ≡ [ $$$ ]— [ Upscale ]                   │
│   [+ Add Option]                            │
│                                             │
│ ☐ Required  ☑ Searchable  ☑ Filterable    │
│ ☑ Show on card  ☑ Show in detail           │
│ Schema.org property: [ priceRange ]         │
│ Width: [50]%                                │
│                                             │
│ [Done]                                      │
└─────────────────────────────────────────────┘
```

### Drag & Drop
- jQuery UI Sortable for field reordering within groups
- Fields can be dragged between groups
- Groups can be reordered
- No React — lightweight admin JS

---

## How Fields Render

### In Admin (Edit Listing Screen)
Standard WP metabox with fields rendered by `Meta_Handler`:
- Fields appear in a metabox titled by field group
- Multiple metaboxes for multiple groups
- Group tabs within a single metabox (optional)
- Conditional fields hide/show based on other field values

### On Frontend (Listing Detail)
- Field groups render as tabs or sections (depending on detail layout setting)
- Each field has a label + formatted value
- Empty fields are hidden
- `map_location` renders an embedded map
- `gallery` renders a lightbox gallery
- `business_hours` renders formatted schedule with "Open Now" badge
- `social_links` render as icon links

### On Card (Listing Card)
- Only fields with `show_in_card: true`
- Compact format: icon + value (no label)
- Max 4-5 fields on card to prevent clutter
- Configurable per listing type

### In Search Filters
- Only fields with `filterable: true`
- Filter UI type determined by field type (dropdown, checkbox, range, etc.)
- Filters scoped to selected listing type

### In REST API
- Only fields with `show_in_rest: true`
- Exposed in `meta` object of listing response
- Full JSON Schema generated from field definitions

---

## Theme Adaptive Rendering

### CSS Custom Properties
```css
/* Field layout */
--listora-field-gap: var(--wp--preset--spacing--20, 1rem);
--listora-field-label-color: var(--wp--preset--color--contrast, #333);
--listora-field-value-color: var(--wp--preset--color--base, #666);
--listora-field-border-color: var(--wp--preset--color--contrast-2, #ddd);

/* Input styling */
--listora-input-bg: var(--wp--preset--color--base, #fff);
--listora-input-border: var(--wp--preset--color--contrast-3, #ccc);
--listora-input-radius: var(--wp--custom--border-radius, 4px);
--listora-input-padding: var(--wp--preset--spacing--10, 0.5rem);
```

### HTML Structure
```html
<div class="listora-field listora-field--select" data-field="price_range">
  <dt class="listora-field__label">Price Range</dt>
  <dd class="listora-field__value">$$$ — Upscale</dd>
</div>
```

- Uses `<dl>/<dt>/<dd>` for semantic field display
- BEM-style CSS classes for targeted styling
- Field type as modifier class for type-specific styling
- No inline styles — everything via classes and custom properties

---

## Validation

### Client-Side
- HTML5 validation attributes (`required`, `type`, `min`, `max`, `pattern`)
- Interactivity API validates before form submission
- Inline error messages below each field

### Server-Side
- `sanitize_callback` on `register_post_meta()`
- Additional validation in REST API controller `prepare_item_for_database()`
- Required field check before publishing
- Type-specific validation (email format, URL format, number range, etc.)
- Returns `WP_Error` with field-specific error messages

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Field key collision across types | Keys use `_listora_{key}` prefix globally. Same key across types shares the same meta row. Collision is allowed when field type and sanitization match (e.g., both Restaurant and Hotel can have `phone` as a `phone` type field). Collision is prevented when field definitions differ (e.g., one type has `price` as `select` and another has `price` as `number`). |
| Changing field type on existing data | Show warning. Attempt type coercion. If incompatible, set to default value. |
| Deleting a field with existing data | Warn user. Data remains in postmeta but is orphaned. Can be cleaned via WP-CLI. |
| 50+ fields on one type | Fields tab gets long — use collapsible groups. Performance-wise, post meta handles this fine. |
| Required field left empty on draft | Allow saving as draft. Only enforce required on publish. |
| Gallery with 100 images | Cap at configurable max (default 20). Show warning if exceeded. |
| Business hours with timezone | Store timezone per listing in `_listora_timezone` meta. "Open Now" uses listing's timezone. |
