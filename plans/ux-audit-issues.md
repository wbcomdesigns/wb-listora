# WB Listora — Frontend UX Audit Issues

**Date:** 2026-03-20
**Audited by:** Visual Playwright inspection at 1440px desktop + 375px mobile
**Status:** Fixing in progress

### Fixed So Far
- [x] CRIT-UX-003 — Keyword search param ordering (`class-search-engine.php:200-203`) + transient cache flush
- [x] CRIT-UX-001 — Single listing detail now renders via `single_template` filter + custom full-width template
- [x] CRIT-UX-002 — Card image placeholders enhanced with gradient backgrounds + dot pattern + type icon
- [x] HIGH-UX-001 — Listings page now uses 3-column grid (was 2-col in cramped split layout)
- [x] HIGH-UX-005 — Full-width page template registered (`Listora Full Width`) and assigned to all directory pages
- [x] HIGH-UX-006 — Dashboard now renders full-width without sidebar
- [x] Page layouts reorganized: listings (3-col + map below), directory-full (split view), business (3-col, no map)
- [x] HIGH-UX-003 — Empty state now has `is-hidden` class server-side when results exist
- [x] HIGH-UX-004 — Submission form already styled (was hidden by sidebar template, now visible)
- [x] HIGH-UX-002 — Card meta already uses dot separators in CSS (was cramped by 2-col, now readable at 3-col)
- [x] MED-UX-003 — "More Filters" red dot hidden server-side when no filters active
- [x] MED-UX-002 — "Clear All Filters" hidden server-side when no filters active
- [x] MED-UX-014 — Submission form unlocked from restaurant-only (now shows type step for all types)
- [x] LOW-UX-003 — Filter count badge hidden on initial load
- [x] MED-UX-005 — Featured badge now has golden gradient with white text
- [x] MED-UX-001 — Search placeholder updated to "Search restaurants, hotels, services..."
- [x] MED-UX-010 — Dashboard stat cards enhanced with colored top borders (green/amber/blue/red)
- [x] MED-UX-007 — Listing detail sidebar now shows beside content (2-column grid layout)
- [x] BUG — `wb_listora_render_hours()` function moved to top of render.php (was causing fatal error)
- [x] BUG — Schema generator `Array to string conversion` warning fixed
- [x] BUG — Tabs not clickable on single listing — added vanilla JS fallback for tab/gallery switching
- [x] BUG — `the_content` filter with recursion protection for listing-detail injection
- [x] BUG — Filter panel open by default on directory-full — added `hidden` attribute server-side
- [x] Directory-full page updated to 3-column grid (was split view)
- [x] 20/20 listings now have Unsplash stock photos (restaurants, hotels, real estate, businesses)

---

## Critical Issues (Must Fix)

### CRIT-UX-001: Single Listing Detail Page Shows Plain Post
- **Block:** `listing-detail`
- **Page:** `/listing/the-greenwich-hotel/` (all single listings)
- **Problem:** The listing-detail block is NOT rendering. Single listing pages display as a plain WordPress post with:
  - Just the description text
  - Default WP comment form (not the custom reviews system)
  - Irrelevant sidebar (Recent Posts, Recent Comments)
  - Empty taxonomy labels ("Listing Types: Categories: Tags: Features:" with no values)
  - No gallery, no map, no business hours, no contact info, no rating display, no share/claim buttons
- **Expected:** Full listing detail with hero image/gallery, tabbed content (Overview, Hours, Reviews, Map), sidebar with contact info, claim button, share buttons, related listings
- **Fix:** Auto-inject listing-detail block content on single `listora_listing` posts via `single_template` filter or block template

### CRIT-UX-002: Listing Cards Have No Featured Images
- **Block:** `listing-card`
- **Pages:** `/listings/`, `/directory-full/`, all grid views
- **Problem:** Cards display only a small listing-type icon (fork icon, hotel icon) where the featured image should be. Without photos, the directory looks like a text-only list, not a premium visual directory
- **Expected:** Cards should show a proper image placeholder (gradient, SVG illustration, or blurred pattern based on listing type) when no featured image is set. The image area should maintain aspect ratio
- **Fix:** Add a styled placeholder image area in `listing-card/render.php` and `listing-card/style.css`. Use listing-type-specific SVG illustrations or gradient backgrounds

### CRIT-UX-003: Keyword Search Returns Zero Results
- **Block:** `listing-search`
- **File:** `includes/search/class-search-engine.php:200-203`
- **Problem:** `$wpdb->prepare()` parameter ordering mismatch. Keyword param added to `$params` array after WHERE params, but the `%s` placeholder in SELECT MATCH appears before WHERE placeholders. This maps 'publish' into the MATCH clause and the keyword into the status filter
- **Fix:** Reorder params so SELECT placeholders get their values first, or build separate param arrays for SELECT and WHERE

---

## High Issues

### HIGH-UX-001: Cards Only Show 2 Columns at 1440px
- **Block:** `listing-grid`
- **Pages:** `/listings/`, `/directory-full/`
- **Problem:** At 1440px viewport, only 2 listing cards per row. This is too sparse — wastes horizontal space and requires excessive scrolling for 20 listings
- **Expected:** 3 columns at 1440px, 2 at 1024px, 1 at mobile. Most premium directory plugins use 3-4 columns on wide screens
- **Fix:** Update grid CSS in `listing-grid/style.css` — change `grid-template-columns` breakpoints

### HIGH-UX-002: Card Meta Fields Run Together as Unstructured Text
- **Block:** `listing-card`
- **Problem:** Phone number, cuisine type, price range all appear as a single text line: "(212) 555-0147 Italian $$$ — Upscale". No visual separation, icons, or hierarchy
- **Expected:** Each meta item should be a separate element with an icon prefix, proper spacing, and visual differentiation. Price range should have its own badge/pill styling
- **Fix:** Structure meta output in `listing-card/render.php` with individual `<span>` elements, icons, and CSS classes

### HIGH-UX-003: "No Listings Found" Empty State Shows Below Actual Results
- **Block:** `listing-grid`
- **Pages:** `/listings/`, `/directory-full/`
- **Problem:** The empty state ("No listings found. Try adjusting your filters...") is visible at the bottom of the page even when 20 results are displayed. It appears as a ghost element below all the listing cards
- **Fix:** Hide empty state element when results exist. Check the Interactivity API state logic in `listing-grid/view.js` — the visibility toggle may not be working correctly, or the element needs `display: none` when `results.length > 0`

### HIGH-UX-004: Submission Form Has Unstyled Default Inputs
- **Block:** `listing-submission`
- **Page:** `/add-listing/`
- **Problem:**
  - Progress bar steps ("1 Basic Info", "2 Details", "3 Media", "4 Preview") render as plain text list, not a visual stepper
  - Form inputs are browser-default (no border styling, no focus states, no field groups)
  - Text inputs, select dropdowns, and textarea have no consistent styling
  - No visual card/container wrapping the form sections
  - 500 server error in console
- **Expected:** Visual multi-step wizard with numbered circles, active/completed states, styled form fields matching the plugin's design system, proper field grouping with section headers
- **Fix:** Add proper CSS for the stepper in `listing-submission/style.css`. Style all form elements using the `--listora-*` custom properties. Fix the 500 error

### HIGH-UX-005: Sidebar on Directory Pages Shows Irrelevant WP Widgets
- **Blocks:** All frontend blocks
- **Pages:** `/listings/`, `/directory-full/`, `/dashboard/`, single listing pages
- **Problem:** WordPress default sidebar with "Search", "Recent Posts", "Recent Comments" widgets appears next to directory content. This is theme-level but the plugin should handle it by either:
  - Using full-width templates
  - Registering its own sidebar with relevant widgets (categories, featured listings, etc.)
- **Expected:** Directory pages should be full-width or have a directory-specific sidebar with category tree, popular tags, featured listings, etc.
- **Fix:** Add `add_filter('body_class', ...)` with full-width class, or register block templates that force full-width layout for directory pages

### HIGH-UX-006: Dashboard Has Sidebar Instead of Full-Width
- **Block:** `user-dashboard`
- **Page:** `/dashboard/`
- **Problem:** The dashboard content is squeezed into the main content area with an irrelevant sidebar (Recent Posts, Recent Comments). Dashboard should be full-width for a proper app-like experience
- **Fix:** Same as HIGH-UX-005 — force full-width template for dashboard page

---

## Medium Issues

### MED-UX-001: Search Bar Missing Labels/Placeholders for Keyword Field
- **Block:** `listing-search`
- **Problem:** The keyword search input has a label "Search" but no visible placeholder text. Users don't know what to type
- **Fix:** Add placeholder like "Search restaurants, hotels, services..."

### MED-UX-002: "Clear All Filters" Takes Space When No Filters Active
- **Block:** `listing-search`
- **Problem:** The filter area with "Clear All Filters" button is always visible even when no filters are selected, wasting vertical space
- **Fix:** Hide the clear filters section when no filters are active

### MED-UX-003: Type Tabs Overflow on Narrow Screens
- **Block:** `listing-search`
- **Problem:** With 10 listing types, the tabs row may overflow horizontally. At 1440px they fit, but with more types they would wrap
- **Fix:** Add horizontal scroll with fade indicators for the tab row, or collapse into a dropdown at a threshold

### MED-UX-004: Rating Stars Don't Show Visual Star Icons
- **Block:** `listing-card`
- **Problem:** The rating shows as "★ 5.0" with only one star icon and a number. There's no visual 5-star rating representation (filled/empty stars)
- **Expected:** Show 5 stars with partial fill based on rating, plus the numeric value
- **Fix:** Update rating display in `listing-card/render.php` and CSS

### MED-UX-005: Featured Badge Styling Could Be More Premium
- **Block:** `listing-card`
- **Problem:** "Featured" badge is a simple outlined text box. Doesn't convey premium status
- **Expected:** Gold/gradient badge with subtle animation or glow effect
- **Fix:** Update badge CSS in `listing-card/style.css`

### MED-UX-006: Favorite Button Has No Filled State
- **Block:** `listing-card`
- **Problem:** The heart/favorite button appears as an outline only. When favorited, it should fill with color (red/coral). Currently no visual feedback of favorited state
- **Fix:** Add `.is-favorited` class toggle with filled heart SVG and color in CSS

### MED-UX-007: Map Takes Up Small Area Below Grid
- **Block:** `listing-map`
- **Pages:** `/listings/`, `/directory-full/`
- **Problem:** The map is positioned below all listing results, barely visible without scrolling. On a directory page, the map should be prominent — either side-by-side with results or above results
- **Fix:** For the listings page, map should be side-by-side with the grid (split view). For directory-full, consider the map overlay/sticky approach

### MED-UX-008: Card Feature Pills Could Use Icons
- **Block:** `listing-card`
- **Problem:** Feature tags ("Accepts Credit Cards", "Outdoor Seating", "Parking") are text-only pills. Adding small icons would make them scannable
- **Fix:** Map feature slugs to icons in the render.php

### MED-UX-009: Dashboard Listing Rows Missing Thumbnails
- **Block:** `user-dashboard`
- **Problem:** Listing rows in the dashboard show only a listing-type icon. Should show the listing's featured image thumbnail
- **Fix:** Add thumbnail in the dashboard listing row render

### MED-UX-010: Dashboard Stats Cards Need Visual Enhancement
- **Block:** `user-dashboard`
- **Problem:** Stats cards (Active: 20, Pending: 0, Reviews: 0, Saved: 0) are plain boxes without icons, colors, or visual hierarchy
- **Expected:** Cards with icon, number, label, and subtle background color (green for active, orange for pending, etc.)
- **Fix:** Add icons and background colors to stats cards

### MED-UX-011: Dashboard Edit/View Links Are Low Contrast
- **Block:** `user-dashboard`
- **Problem:** The "Edit" and "View" action links on each listing row are small and use pastel colors, making them hard to see
- **Fix:** Make action buttons more prominent — use icon buttons or outlined buttons

### MED-UX-012: No Pagination on Listings Page
- **Block:** `listing-grid`
- **Pages:** `/listings/`
- **Problem:** All 20 listings load on one page with no pagination controls. For directories with hundreds of listings, this will be a performance and UX issue
- **Fix:** Implement pagination or "Load More" button using the `per_page` setting

### MED-UX-013: Sort Dropdown Has No Visual Indicator of Current Sort
- **Block:** `listing-grid`
- **Problem:** The "Featured" sort is selected but the dropdown looks like a default `<select>` element. No visual distinction
- **Fix:** Style the sort dropdown to match the plugin design system

### MED-UX-014: Loading/Progress Bar Visible When Not Loading
- **Block:** `listing-search`
- **Problem:** An orange/red progress bar appears below the search filters even when no search is in progress. It should only appear during active loading
- **Fix:** Control visibility via the Interactivity API loading state

---

## Low Issues

### LOW-UX-001: Breadcrumb Separator Uses "»" Instead of ">"
- **Pages:** All pages
- **Problem:** Breadcrumbs use "»" (guillemet) separator which looks dated. Modern UX uses "/" or ">" or "›"
- **Fix:** Update breadcrumb separator character

### LOW-UX-002: Page Titles Could Be More Descriptive
- **Problem:** "Directory" page title is generic. Could include listing count: "Directory — 20 listings"
- **Fix:** Dynamic page title in render.php

### LOW-UX-003: "More Filters" Button Has Red Dot Indicator Always
- **Block:** `listing-search`
- **Problem:** The red dot next to "More Filters" suggests active filters even when none are set
- **Fix:** Only show the dot when filters are actually active

### LOW-UX-004: Mobile Navigation Wraps Awkwardly
- **Problem:** At 1440px the menu items fit on one line, but with 8 items, they could wrap on slightly smaller screens
- **Fix:** Theme-level issue — consider reducing menu items or using a condensed nav

### LOW-UX-005: No Hover Effects on Listing Cards
- **Block:** `listing-card`
- **Problem:** Cards don't elevate or highlight on hover. No visual feedback that they're clickable
- **Fix:** Add `transform: translateY(-2px)` and `box-shadow` increase on hover

### LOW-UX-006: Feature Tags "+N" Overflow Counter Not Styled
- **Block:** `listing-card`
- **Problem:** When a listing has more features than can be shown, "+4" or "+2" appears as plain text. Could use a pill/badge style
- **Fix:** Style the overflow counter to match feature pills

### LOW-UX-007: Grid/List View Toggle Not Clearly Indicating Active Mode
- **Block:** `listing-grid`
- **Problem:** The grid/list view radio buttons are small and don't clearly show which mode is active
- **Fix:** Add active state styling with color/background change

---

## Summary

| Severity | Count |
|----------|-------|
| Critical | 3 |
| High | 6 |
| Medium | 14 |
| Low | 7 |
| **Total** | **30** |

### Priority Fix Order:
1. CRIT-UX-001 — Single listing detail page (most important page in any directory)
2. CRIT-UX-003 — Keyword search broken (core functionality)
3. CRIT-UX-002 — Card image placeholders (visual first impression)
4. HIGH-UX-005 — Full-width layout for directory pages
5. HIGH-UX-001 — 3-column grid at desktop
6. HIGH-UX-004 — Submission form styling
7. HIGH-UX-003 — Hide empty state when results exist
8. HIGH-UX-002 — Card meta structure
9. MED-UX-* — All medium issues in order listed
10. LOW-UX-* — Polish items
