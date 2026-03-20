# 14 — Frontend Submission Form

## Scope

| | Free | Pro |
|---|---|---|
| Multi-step submission form | Yes | Yes |
| Type-driven dynamic fields | Yes | Yes |
| Media upload (images, files) | Yes | Yes |
| Map/address picker | Yes | Yes + Places autocomplete |
| Preview before submit | Yes | Yes |
| Edit own listing | Yes | Yes |
| Draft save (resume later) | Yes | Yes |
| Submission moderation | Yes | Yes |
| Package/plan selection step | — | Yes |
| Payment step | — | Yes |
| Social login | — | Yes |

---

## Overview

The frontend submission form lets listing owners submit and edit listings without accessing wp-admin. It's a multi-step wizard that adapts fields based on the selected listing type.

**Key UX principle:** Progressive disclosure. Don't show 30 fields on one page. Guide the user step-by-step.

---

## Submission Steps

### Step 1: Choose Listing Type
```
┌─────────────────────────────────────────────────────┐
│ Add Your Listing                        Step 1 of 5 │
│                                                     │
│ What type of listing are you adding?                │
│                                                     │
│ ┌──────────┐ ┌──────────┐ ┌──────────┐            │
│ │ 🏢       │ │ 🍽️       │ │ 🏠       │            │
│ │ Business │ │Restaurant│ │Real      │            │
│ │          │ │          │ │Estate    │            │
│ └──────────┘ └──────────┘ └──────────┘            │
│ ┌──────────┐ ┌──────────┐                          │
│ │ 🏨       │ │ 💼       │                          │
│ │ Hotel    │ │ Job      │                          │
│ └──────────┘ └──────────┘                          │
│                                                     │
│                                     [Continue →]    │
└─────────────────────────────────────────────────────┘
```

**On type-specific pages:** This step is skipped, type is pre-selected.

### Step 2: Basic Information
```
┌─────────────────────────────────────────────────────┐
│ Basic Information                       Step 2 of 5 │
│                                                     │
│ Listing Title *                                     │
│ [ Pizza Palace                                  ]   │
│                                                     │
│ Category *                                          │
│ [ Italian                                    ▾ ]    │
│                                                     │
│ Tags                                                │
│ [ pizza, italian, downtown     ] (comma separated)  │
│                                                     │
│ Description *                                       │
│ ┌───────────────────────────────────────────────┐   │
│ │ B I U │ ≡ • │ 🔗                             │   │
│ ├───────────────────────────────────────────────┤   │
│ │                                               │   │
│ │ The best pizza in Manhattan since 1985...     │   │
│ │                                               │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ [← Back]                            [Continue →]    │
└─────────────────────────────────────────────────────┘
```

### Step 3: Details (Type-Specific Fields)
Fields dynamically generated from listing type's field configuration:

**Restaurant example:**
```
┌─────────────────────────────────────────────────────┐
│ Restaurant Details                      Step 3 of 5 │
│                                                     │
│ ┌─ Contact ─────────────────────────────────────┐   │
│ │ Phone:     [ (212) 555-0123               ]   │   │
│ │ Email:     [ info@pizzapalace.com          ]   │   │
│ │ Website:   [ https://pizzapalace.com       ]   │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ ┌─ Location ────────────────────────────────────┐   │
│ │ Address *: [ 123 Main St, Manhattan, NY    ]   │   │
│ │ ┌───────────────────────────────────────────┐ │   │
│ │ │         📍 (draggable pin)                │ │   │
│ │ │         [Leaflet Map]                     │ │   │
│ │ └───────────────────────────────────────────┘ │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ ┌─ Restaurant Info ─────────────────────────────┐   │
│ │ Cuisine:       ☑Italian ☐Chinese ☐Japanese   │   │
│ │ Price Range:   ○$ ○$$ ●$$$ ○$$$$             │   │
│ │ Delivery:      ☑ Yes                          │   │
│ │ Reservations:  [Online ▾]                     │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ ┌─ Business Hours ──────────────────────────────┐   │
│ │ Mon [09:00 ▾] - [21:00 ▾]  ☐ Closed         │   │
│ │ Tue [09:00 ▾] - [21:00 ▾]  ☐ Closed         │   │
│ │ ...                                           │   │
│ │ Sun [         Closed         ] ☑             │   │
│ │ Timezone: [America/New_York ▾]                │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ Features / Amenities:                               │
│ ☑ WiFi  ☑ Parking  ☐ Outdoor  ☑ Live Music       │
│                                                     │
│ Social Links:                                       │
│ [Facebook ▾] [https://facebook.com/pizzapalace  ]   │
│ [Instagram▾] [https://instagram.com/pizzapalace ]   │
│ [+ Add]                                             │
│                                                     │
│ [← Back]                            [Continue →]    │
└─────────────────────────────────────────────────────┘
```

### Step 4: Media
```
┌─────────────────────────────────────────────────────┐
│ Photos & Media                          Step 4 of 5 │
│                                                     │
│ Featured Image *                                    │
│ ┌───────────────────────────────────────────────┐   │
│ │                                               │   │
│ │     📷 Drag & drop or click to upload         │   │
│ │        Max 5MB, JPG/PNG/WebP                  │   │
│ │                                               │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ Gallery (up to 20 photos)                           │
│ ┌──────┐ ┌──────┐ ┌──────┐ ┌──────┐              │
│ │ img1 │ │ img2 │ │ img3 │ │  +   │              │
│ │  ×   │ │  ×   │ │  ×   │ │ Add  │              │
│ └──────┘ └──────┘ └──────┘ └──────┘              │
│ Drag to reorder                                     │
│                                                     │
│ Video URL (optional)                                │
│ [ https://youtube.com/watch?v=...             ]     │
│                                                     │
│ [← Back]                            [Continue →]    │
└─────────────────────────────────────────────────────┘
```

**Upload handling:**
- Uses WordPress media upload AJAX (`wp_handle_upload()`)
- Progress bar per file
- Client-side validation: file type, size
- Server-side validation: mime type, dimensions
- Images auto-thumbnailed by WP
- Drag-to-reorder gallery images

### Step 5: Preview & Submit
```
┌─────────────────────────────────────────────────────┐
│ Preview Your Listing                    Step 5 of 5 │
│                                                     │
│ ┌───────────────────────────────────────────────┐   │
│ │                                               │   │
│ │  [Preview renders the listing detail page]    │   │
│ │  exactly as visitors will see it              │   │
│ │                                               │   │
│ │  Pizza Palace                                 │   │
│ │  ★★★★★ (no reviews yet)                     │   │
│ │  📍 123 Main St, Manhattan, NY               │   │
│ │  ...                                          │   │
│ │                                               │   │
│ └───────────────────────────────────────────────┘   │
│                                                     │
│ ☑ I agree to the Terms of Service                  │
│                                                     │
│ [← Back]     [Save Draft]     [Submit Listing →]    │
└─────────────────────────────────────────────────────┘
```

### Step 5b: Package Selection (Pro)
Inserted before final submit when pricing plans are enabled:
```
┌─────────────────────────────────────────────────────┐
│ Choose a Package                                    │
│                                                     │
│ ┌───────────┐ ┌───────────┐ ┌───────────┐         │
│ │ Free      │ │ Basic     │ │ Featured  │         │
│ │           │ │ $9.99/mo  │ │ $24.99/mo │         │
│ │ ✓ Listed │ │ ✓ Listed │ │ ✓ Listed │         │
│ │ ✗ Badge  │ │ ✓ Badge  │ │ ✓ Badge  │         │
│ │ ✗ Top    │ │ ✗ Top    │ │ ✓ Top    │         │
│ │ 30 days  │ │ 90 days  │ │ 365 days │         │
│ │          │ │           │ │           │         │
│ │ [Select] │ │ [Select]  │ │ [Select]  │         │
│ └───────────┘ └───────────┘ └───────────┘         │
│                                                     │
│ [← Back]                            [Continue →]    │
└─────────────────────────────────────────────────────┘
```

---

## Block: `listora/listing-submission`

### Attributes
```json
{
  "attributes": {
    "listingType": { "type": "string", "default": "" },
    "showTypeStep": { "type": "boolean", "default": true },
    "requireLogin": { "type": "boolean", "default": true },
    "showTerms": { "type": "boolean", "default": true },
    "termsPageId": { "type": "number", "default": 0 },
    "redirectAfterSubmit": { "type": "string", "default": "dashboard" }
  }
}
```

---

## Login Requirement

If user is not logged in:
```
┌─────────────────────────────────────────────────────┐
│ Add Your Listing                                    │
│                                                     │
│ Please log in or create an account to submit        │
│ a listing.                                          │
│                                                     │
│ [Log In]  [Create Account]                          │
│                                                     │
│ Pro: [Login with Google] [Login with Facebook]       │
└─────────────────────────────────────────────────────┘
```

Registration creates a WordPress user with `subscriber` role + `edit_listora_listing` capability.

---

## User Registration

### Registration Form
When user clicks "Create Account" on submission page:

```
┌─────────────────────────────────────────────────────┐
│ Create Your Account                                 │
│                                                     │
│ I am a:                                             │
│ (•) Business Owner (list my business)               │
│ ( ) Visitor (browse and review)                     │
│                                                     │
│ Full Name *:    [ John Smith                    ]   │
│ Email *:        [ john@example.com              ]   │
│ Password *:     [ ••••••••                      ]   │
│                                                     │
│ ☑ I agree to the Terms of Service and Privacy Policy│
│                                                     │
│ [Create Account]                                    │
│                                                     │
│ Already have an account? [Log In]                   │
│                                                     │
│ Pro: ─── OR ───                                     │
│ [Continue with Google] [Continue with Facebook]     │
└─────────────────────────────────────────────────────┘
```

### How It Works
1. Creates WordPress user with `subscriber` role
2. Adds `submit_listora_listing` capability
3. Sets `_listora_user_type` meta based on selection
4. Sends WordPress default email verification
5. Auto-logs in user after registration
6. Redirects back to submission form

### Anti-Spam for Registration
- Honeypot field (hidden)
- Rate limiting: max 3 registrations per IP per hour
- WordPress `pre_user_login` filter for additional validation
- Optional: reCAPTCHA via filter `apply_filters('wb_listora_registration_fields', $fields)`

### REST API
Uses WordPress default REST registration if enabled, or custom:
```
POST /listora/v1/register
Body: { name, email, password, user_type }
Response: { user_id, redirect_url }
```

---

## Edit Flow

When listing owner edits their own listing:
- Same multi-step form, pre-populated with existing data
- "Submit" button changes to "Update Listing"
- If moderation is on: edit goes back to "pending" (configurable)
- Owner can delete their own listing (moves to trash)

URL: `/add-listing/?edit={listing_id}` or `/dashboard/edit/{listing_id}`

---

## Draft Save

- Each step auto-saves to a draft post via REST API
- If user leaves and returns, resume from last completed step
- Drafts visible in user dashboard under "My Drafts"
- Drafts auto-deleted after 30 days (configurable)

---

## Validation

### Client-Side (Step-by-Step)
- Required fields checked before "Continue" button enables
- Inline error messages below each field
- Invalid fields highlighted with red border
- Focus moves to first error

### Server-Side
- REST API validates all fields on submit
- Returns field-specific error messages
- Form re-displays at the step with errors
- Files validated: type, size, dimensions

### Spam Prevention
- Honeypot field (hidden field that bots fill)
- Time-based check (form submitted < 3 seconds = bot)
- Rate limiting: max 5 submissions per hour per user
- Optional: reCAPTCHA integration via filter hook

---

## REST API

```
POST /listora/v1/submit
  Body: { type, title, content, meta: {...}, terms: [...], images: [...] }
  Auth: Required
  Response: { id, status, message, redirect_url }

PUT /listora/v1/submit/{id}
  Body: { title, content, meta: {...} }
  Auth: Author only
  Response: { id, status, message }

POST /listora/v1/submit/{id}/media
  Body: FormData with file
  Auth: Author only
  Response: { attachment_id, url, thumbnail_url }
```

---

## Theme Adaptive UI

### Form Styling
```css
.listora-submission {
  max-width: var(--wp--style--global--content-size, 720px);
  margin: 0 auto;
}

.listora-submission__step {
  background: var(--wp--preset--color--base, #fff);
  border: 1px solid var(--wp--preset--color--contrast-3, #eee);
  border-radius: var(--wp--custom--border-radius, 8px);
  padding: var(--wp--preset--spacing--40, 2rem);
}

.listora-submission input,
.listora-submission select,
.listora-submission textarea {
  width: 100%;
  font-family: inherit;
  font-size: var(--wp--preset--font-size--medium, 1rem);
  padding: var(--wp--preset--spacing--10, 0.5rem);
  border: 1px solid var(--wp--preset--color--contrast-3, #ccc);
  border-radius: var(--wp--custom--border-radius, 4px);
  background: var(--wp--preset--color--base, #fff);
  color: var(--wp--preset--color--contrast, #333);
}
```

### Progress Bar
```css
.listora-submission__progress {
  display: flex;
  gap: var(--wp--preset--spacing--10, 0.5rem);
}

.listora-submission__step-indicator {
  flex: 1;
  height: 4px;
  background: var(--wp--preset--color--contrast-3, #ddd);
  border-radius: 2px;
}

.listora-submission__step-indicator--completed {
  background: var(--wp--preset--color--primary, #0073aa);
}

.listora-submission__step-indicator--current {
  background: var(--wp--preset--color--primary, #0073aa);
  opacity: 0.5;
}
```

---

## Mobile Experience

- Steps stack vertically (single column)
- Media upload uses native file picker
- Map picker is full-width
- Business hours use compact layout
- "Continue" button fixed at bottom of viewport
- Swipe between steps (optional)

---

## Accessibility

| Element | A11y Feature |
|---------|-------------|
| Step progress | `aria-label="Step 2 of 5: Basic Information"` |
| Form fields | `<label>` linked to inputs, `aria-required` |
| Error messages | `role="alert"`, `aria-describedby` on field |
| File upload | Keyboard accessible, drag-and-drop as enhancement |
| Type cards | Keyboard navigable, `role="radiogroup"` |
| Preview | Full page preview accessible to screen readers |
| Submit button | `aria-disabled` when validation fails |
