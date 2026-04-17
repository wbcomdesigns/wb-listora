# 02 — Setup Wizard

## Scope

| | Free | Pro |
|---|---|---|
| Setup wizard | Yes | Enhanced (license activation step, Pro features config) |

---

## Why This Is Priority #1

Research shows 50%+ of directory plugin users abandon before configuring anything. The #1 complaint across GeoDirectory, Directorist, and HivePress is "too many settings, no idea what to do first."

**Goal:** Site owner goes from plugin activation to a credible, browsable directory in under 15 minutes.

---

## Trigger

- On activation, set transient: `set_transient('wb_listora_activation_redirect', true, 60)`
- On next admin page load, redirect to `admin.php?page=listora-setup`
- Show "Start Setup" banner on all admin pages until wizard is completed or dismissed
- Wizard can be re-run anytime from Settings → WB Listora → Run Setup Wizard

---

## Wizard Steps

### Step 1: Welcome & Directory Type

**UI:**
```
┌─────────────────────────────────────────────┐
│  Welcome to WB Listora                      │
│                                             │
│  What type of directory are you building?   │
│                                             │
│  ┌──────┐  ┌──────┐  ┌──────┐  ┌──────┐   │
│  │ 🏢   │  │ 🍽️   │  │ 🏠   │  │ 🏨   │   │
│  │Local │  │Food &│  │Real  │  │Hotel │   │
│  │Biz   │  │Dining│  │Estate│  │Travel│   │
│  └──────┘  └──────┘  └──────┘  └──────┘   │
│  ┌──────┐  ┌──────┐  ┌──────┐  ┌──────┐   │
│  │ 💼   │  │ 🎪   │  │ 🏥   │  │ 🎓   │   │
│  │Jobs  │  │Events│  │Health│  │Edu   │   │
│  └──────┘  └──────┘  └──────┘  └──────┘   │
│  ┌──────┐  ┌──────┐                        │
│  │ 📍   │  │ 📦   │  ☐ Multi-type          │
│  │Places│  │Class.│  (select multiple)      │
│  └──────┘  └──────┘                        │
│                                             │
│                          [Next →]           │
└─────────────────────────────────────────────┘
```

**Behavior:**
- Single click selects ONE type (most common)
- "Multi-type" checkbox allows selecting 2-5 types
- Each type card shows: icon, name, short description on hover
- Selection determines: default fields, categories, schema type, demo content

**Data captured:** Array of selected listing type slugs

---

### Step 2: Your Location

**UI:**
```
┌─────────────────────────────────────────────┐
│  Where is your directory based?             │
│                                             │
│  This sets your default map center and      │
│  helps with location-based features.        │
│                                             │
│  Country: [  United States        ▾ ]       │
│  City:    [  New York             ▾ ]       │
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │                                     │   │
│  │         [ Map Preview ]             │   │
│  │         (OSM/Leaflet)               │   │
│  │                                     │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  ☐ This is a global directory              │
│    (no default location)                    │
│                                             │
│  [← Back]                    [Next →]       │
└─────────────────────────────────────────────┘
```

**Behavior:**
- Country dropdown auto-narrows city options
- Map preview updates on selection
- "Global directory" skips default center, uses world view
- Uses Nominatim for geocoding the selected location

**Data captured:** Country, city, default lat/lng, zoom level

---

### Step 3: Map Provider

**UI:**
```
┌─────────────────────────────────────────────┐
│  Choose your map provider                   │
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │ ✓ OpenStreetMap (Free)              │   │
│  │   Free, no API key needed.          │   │
│  │   Works immediately.                │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │ ○ Google Maps (Pro)                 │   │
│  │   Requires API key + billing.       │   │
│  │   Advanced clustering, Street View. │   │
│  │   [Upgrade to Pro →]                │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  [← Back]                    [Next →]       │
└─────────────────────────────────────────────┘
```

**Behavior:**
- OSM selected by default (zero friction)
- Google Maps option visible but marked Pro
- If Pro is active, show API key field instead of upgrade link

---

### Step 4: Pages & Structure

**UI:**
```
┌─────────────────────────────────────────────┐
│  We'll create these pages for you           │
│                                             │
│  ✓ Directory Home        /listings          │
│  ✓ Search Results        /search            │
│  ✓ Add Listing           /add-listing       │
│  ✓ My Dashboard          /dashboard         │
│                                             │
│  Per listing type:                          │
│  ✓ Restaurants           /restaurants       │
│  ✓ Real Estate           /real-estate       │
│                                             │
│  Each page will have the right blocks       │
│  pre-configured. You can customize them     │
│  later in the block editor.                 │
│                                             │
│  Slug prefix: [ listing ] (optional)        │
│                                             │
│  [← Back]                    [Next →]       │
└─────────────────────────────────────────────┘
```

**Behavior:**
- Shows which pages will be created based on selected types
- Each page gets pre-inserted blocks (search + grid + map for type pages)
- Slug is editable if site owner wants custom URLs
- Checks for existing pages with same slug — offers to reuse or rename

---

### Step 5: Demo Content

**UI:**
```
┌─────────────────────────────────────────────┐
│  Want some sample listings to start?        │
│                                             │
│  ○ Yes, import demo listings (recommended)  │
│    20 sample listings per selected type     │
│    with images, descriptions, and reviews   │
│                                             │
│  ○ No, I'll add my own                     │
│                                             │
│  ○ I have a CSV file to import             │
│    [Choose File]                            │
│                                             │
│  [← Back]                    [Next →]       │
└─────────────────────────────────────────────┘
```

**Behavior:**
- Demo data is location-aware — uses city from Step 2 to generate plausible addresses
- Demo listings include: title, description, featured image, categories, fields, 2-3 reviews each
- CSV option leads to import mapping screen (simplified version)
- Demo data clearly marked as demo (can be bulk-deleted later)

---

### Step 6: Done!

**UI:**
```
┌─────────────────────────────────────────────┐
│  ✓ Your directory is ready!                 │
│                                             │
│  ┌─────────────────────────────────────┐   │
│  │                                     │   │
│  │     [ Preview Screenshot ]          │   │
│  │     of the directory homepage       │   │
│  │                                     │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  Quick Actions:                             │
│  → View your directory                      │
│  → Add your first listing                   │
│  → Customize your pages                     │
│  → Configure settings                       │
│                                             │
│  Next steps:                                │
│  1. Add real listings (or import CSV)       │
│  2. Set up submission form                  │
│  3. Configure payments (Pro)                │
│                                             │
│                          [Go to Dashboard]  │
└─────────────────────────────────────────────┘
```

---

## Technical Implementation

### Admin Page Registration
- `add_submenu_page()` under Listora menu, or standalone `admin.php?page=listora-setup`
- Only accessible to `manage_options` capability
- Uses WordPress admin styles + custom wizard CSS

### Data Flow
Each step saves to a temporary option (`wb_listora_setup_data`). On final step:
1. Create selected listing types (if not already default)
2. Create categories for each type
3. Set map provider and location settings
4. Create pages with pre-configured block content
5. Import demo content (if selected)
6. Set `wb_listora_setup_complete` option to true
7. Remove activation redirect transient

### Page Creation
Pages created with `wp_insert_post()` containing block markup:

**Type-specific page (e.g., /restaurants):**
```html
<!-- wp:listora/listing-search {"listingType":"restaurant"} /-->
<!-- wp:columns -->
<!-- wp:column {"width":"60%"} -->
<!-- wp:listora/listing-grid {"listingType":"restaurant","columns":2} /-->
<!-- /wp:column -->
<!-- wp:column {"width":"40%"} -->
<!-- wp:listora/listing-map {"listingType":"restaurant"} /-->
<!-- /wp:column -->
<!-- /wp:columns -->
```

**Dashboard page:**
```html
<!-- wp:listora/user-dashboard /-->
```

**Add Listing page:**
```html
<!-- wp:listora/listing-submission /-->
```

### Error Handling
- If page creation fails → show error, allow retry
- If demo import fails → show partial success, allow re-import
- If geocoding fails → skip map center, allow manual setting later
- Always allow "Skip" on every step

---

## Theme Adaptive UI

### Wizard Styles
- Uses WordPress admin color scheme (`wp-admin` CSS variables)
- Cards use `wp-block` styling patterns
- Responsive: works on tablet-sized screens (admin is rarely used on phones)
- No custom fonts — uses WP admin font stack

### Created Pages
- All blocks inherit active theme's `theme.json` design tokens
- No hardcoded colors, fonts, or spacing
- Works with any block theme (Twenty Twenty-Five, Flavor flavor, Flavor, etc.)
- Works with classic themes that support `align_wide`

---

## Edge Cases

| Scenario | Handling |
|----------|----------|
| Plugin re-activated after deactivation | Don't re-show wizard if `wb_listora_setup_complete` is true |
| User dismisses wizard | Set flag, show "Run Setup" button in settings |
| Pages with same slug exist | Offer to reuse existing page or create with `-2` suffix |
| No listing types selected | Default to "Business" type |
| Multisite | Each site runs its own wizard |
| Pro activated during wizard | Show Pro-specific options inline (Google Maps key, license) |
