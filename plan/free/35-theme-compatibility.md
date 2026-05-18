# 35 — Theme Compatibility & Adaptive Design

## Scope: Free + Pro

---

## Design Philosophy

WB Listora must look great with ANY WordPress theme — block themes, classic themes, minimal themes, opinionated themes. The plugin should **inherit** the theme's design language, not **impose** its own.

---

## Core Principles

### 1. Inherit from `theme.json`

Every visual property reads from the theme's design tokens:

```css
/* Colors */
var(--wp--preset--color--primary)        /* Theme's primary color */
var(--wp--preset--color--contrast)       /* Text color */
var(--wp--preset--color--base)           /* Background color */
var(--wp--preset--color--contrast-2)     /* Secondary text */
var(--wp--preset--color--contrast-3)     /* Borders */

/* Typography */
var(--wp--preset--font-family--heading)  /* Heading font */
var(--wp--preset--font-family--body)     /* Body font */
var(--wp--preset--font-size--small)      /* Small text */
var(--wp--preset--font-size--medium)     /* Body text */
var(--wp--preset--font-size--large)      /* Headings */

/* Spacing */
var(--wp--preset--spacing--10)           /* 0.5rem equivalent */
var(--wp--preset--spacing--20)           /* 1rem equivalent */
var(--wp--preset--spacing--30)           /* 1.5rem equivalent */
var(--wp--preset--spacing--40)           /* 2rem equivalent */

/* Layout */
var(--wp--style--global--content-size)   /* Content width */
var(--wp--style--global--wide-size)      /* Wide width */
```

### 2. Fallback Chain

Every CSS custom property has a fallback for themes that don't define it:

```css
color: var(--wp--preset--color--contrast, #333);
font-size: var(--wp--preset--font-size--medium, 1rem);
padding: var(--wp--preset--spacing--20, 1rem);
```

### 3. CSS Custom Property API

Plugin exposes its own `--listora-*` properties that themes CAN override but don't HAVE to:

```css
/* Theme can override in theme.json or CSS */
.listora-card {
  --listora-card-radius: 12px;           /* Default: theme's border-radius or 8px */
  --listora-card-shadow: none;           /* Default: subtle shadow */
  --listora-card-image-ratio: 4/3;       /* Default: 16/10 */
}
```

### 4. No Fixed Dimensions

All components use:
- `max-width` not `width`
- Fluid responsive (no fixed pixel breakpoints)
- Container queries where supported (with fallback)
- `min()` / `max()` / `clamp()` for fluid sizing

### 5. CSS Logical Properties

For RTL support:
```css
/* Instead of */
margin-left: 1rem;
padding-right: 1rem;
text-align: left;
border-left: 3px solid blue;

/* Use */
margin-inline-start: 1rem;
padding-inline-end: 1rem;
text-align: start;
border-inline-start: 3px solid blue;
```

### 6. Semantic HTML

All output uses proper semantic elements:
```html
<article>    — listing card, review
<address>    — location information
<time>       — dates, business hours
<nav>        — breadcrumbs, pagination
<form>       — search, submission, review
<section>    — grouped content with heading
<aside>      — sidebar contact info
<figure>     — gallery images
<dl/dt/dd>   — field label-value pairs
```

### 7. Block Markup Patterns

Frontend output follows WordPress block markup conventions:
```html
<div class="wp-block-listora-listing-grid">
  <!-- Uses block alignment classes -->
  <!-- Uses block spacing classes -->
  <!-- Works with theme.json layout settings -->
</div>
```

---

## Theme Types Supported

### Block Themes (FSE)
- Full support via `templates/` and `parts/` directories
- Templates provided as `.html` files:
  - `templates/single-listora_listing.html`
  - `templates/archive-listora_listing.html`
  - `templates/taxonomy-listora_listing_cat.html`
- Themes can override by creating same templates
- Uses `theme.json` tokens exclusively

### Classic Themes
- PHP templates as fallbacks:
  - `templates/single-listora_listing.php`
  - `templates/archive-listora_listing.php`
- Detects classic theme and adjusts output
- Uses wrapper div with theme's content width
- Sidebar support where applicable

### Hybrid Themes
- Themes that support some block features
- Detect `add_theme_support('editor-styles')`, `align-wide`, etc.
- Graceful feature adaptation

---

## Testing Matrix

### Must-Work Themes

| Theme | Type | Reason |
|-------|------|--------|
| Twenty Twenty-Five | Block | Current default |
| Twenty Twenty-Four | Block | Previous default |
| Twenty Twenty-Three | Block | Popular |
| Flavor flavor flavor | Block | Popular community |
| flavor flavor flavor | Block | Popular community |
| Flavor flavor | Classic + Block | Hybrid |
| flavor flavor | Classic | Still widely used |
| flavor flavor flavor | Classic | Page builder popular |
| flavor flavor flavor flavor | Classic | WooCommerce default |

### Visual QA Checklist Per Theme
- [ ] Listing card renders correctly
- [ ] Search form inherits theme styles
- [ ] Grid respects theme content width
- [ ] Map fits container properly
- [ ] Detail page reads well
- [ ] Submission form matches theme forms
- [ ] Dashboard looks integrated
- [ ] Dark mode (if theme supports)
- [ ] RTL layout (if applicable)
- [ ] Mobile responsive
- [ ] No CSS conflicts

---

## Conflict Prevention

### CSS Specificity
- Use BEM naming: `.listora-card__title` (not `.card .title`)
- Never use element selectors without class: `h3` → `.listora-card__title`
- Never use `!important` (except to override plugin's own reset)
- Namespace all CSS: prefix with `listora-`

### JavaScript
- No global variables
- Interactivity API namespaced: `listora/directory`
- No jQuery on frontend (admin only for sortable)
- No conflict with Elementor, Divi, Beaver Builder

### PHP
- All functions/classes namespaced: `WBListora\`
- No generic function names (no `get_listings()`, use `wb_listora_get_listings()`)
- Hooks prefixed: `wb_listora_*`
- No output buffering (use proper template system)

---

## How a Theme Customizes Listora

### Method 1: theme.json (Easiest)
```json
{
  "settings": {
    "custom": {
      "listora": {
        "card-radius": "16px",
        "card-shadow": "0 4px 20px rgba(0,0,0,0.1)",
        "card-image-ratio": "1/1"
      }
    }
  }
}
```

### Method 2: CSS in theme
```css
/* Override card appearance */
.listora-card {
  --listora-card-radius: 0;
  --listora-card-shadow: none;
  border: 2px solid var(--wp--preset--color--contrast-3);
}

/* Override search bar */
.listora-search {
  --listora-search-bg: var(--wp--preset--color--contrast-4);
}
```

### Method 3: Template override
Copy `templates/single-listora_listing.html` to theme and modify.

### Method 4: PHP hooks
```php
// Change card HTML structure
add_filter('wb_listora_card_template', function($template, $type) {
    return 'my-custom-card.php';
}, 10, 2);
```

---

## Dark Mode

Plugin respects dark mode via:

1. **Theme-driven:** If theme has dark mode tokens, plugin inherits them automatically via `var(--wp--preset--color--*)`
2. **System-driven:** `@media (prefers-color-scheme: dark)` with adjusted defaults
3. **No forced dark mode** — plugin never overrides theme's color scheme

```css
/* Only apply dark overrides if theme doesn't handle it */
@media (prefers-color-scheme: dark) {
  :root:not(.has-custom-dark-mode) {
    --listora-card-bg: #1a1a1a;
    --listora-card-border: 1px solid #333;
  }
}
```

---

## Minimal CSS Footprint

### Size Budget
- Base CSS (all components): < 15KB gzipped
- Per-block CSS: < 2KB gzipped each
- Total loaded CSS per page: < 20KB gzipped

### What Plugin CSS Covers
- Structural layout (grid, flexbox, positioning)
- Component-specific styles (card shape, tab navigation, form layout)
- Interactive states (hover, focus, active, loading)
- Status indicators (badges, colors)

### What Plugin CSS Does NOT Cover
- Base typography (from theme)
- Button styles (uses `wp-element-button` class)
- Form input styles (inherits from theme)
- Link colors (from theme)
- Content width (from theme layout settings)
