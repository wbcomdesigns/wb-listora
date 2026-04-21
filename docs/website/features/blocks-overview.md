# Blocks Overview

## What it does

WB Listora provides 11 WordPress blocks built with the Interactivity API. They work in the block editor like any native WordPress block. Combine them on any page to build your directory layout — no coding required.

## Why you'd use it

- Every block is reactive: search results update without page reloads.
- Block settings are configured visually in the editor sidebar — no shortcodes.
- All blocks work with any block theme and include responsive controls for desktop, tablet, and mobile.
- Per-instance styling (spacing, shadow, border radius, colors) means each block on each page can look different.

## The 11 blocks

### Listing Search

Renders a command-palette-style search bar. Visitors type a keyword and see autocomplete suggestions immediately. Includes a location field with a **"Near Me"** geolocation button, type filter tabs, and an advanced filters panel (category, feature amenities, price range, rating).

**Block settings:** Layout (Horizontal / Stacked), show/hide type tabs, show/hide advanced filters.

### Listing Grid

Displays listing cards in a responsive grid. Visitors can switch between **Grid view** (1–4 columns) and **List view** (horizontal cards). A toolbar shows the result count, a view toggle, and a sort dropdown. Pagination uses numbered pages with prev/next links. Skeleton placeholders show during loading.

**Block settings:** Default columns (1–4), items per page, default sort, pre-filter by listing type.

### Listing Detail

Used automatically on single listing pages — you don't need to add this block manually. Shows the hero gallery with thumbnails, tabbed content (Details, Reviews, Map, Contact), a sidebar with contact info, business hours, and social links, and a related listings section. Outputs Schema.org JSON-LD for rich snippets.

**Action buttons on detail page:** Share, Favorite, Claim, Compare.

### Listing Reviews

Displays the review summary (average rating, distribution chart), the review list, and the submission form. Includes helpful vote buttons, review reporting, and owner reply. Multi-criteria ratings (Pro) appear on the same block.

### Listing Map

An interactive map with marker clustering. Free uses OpenStreetMap (no API key needed). Pro upgrades this to Google Maps with custom styles. The **Search on drag** option re-runs the query as the user pans the map.

**Block settings:** Map height, default zoom level, clustering on/off.

### Listing Submission

The multi-step frontend form for submitting a new listing. Logged-in users select a type, fill in basic info, answer type-specific fields, choose categories, then preview and submit. Includes a pre-submit duplicate check to prevent identical listings.

### User Dashboard

The full user dashboard panel (see [User Dashboard](user-dashboard.md)). Place this block on any page to give logged-in users their management interface.

### Listing Categories

An icon grid showing all active categories with listing counts. Clicking a category filters the directory automatically. Displays an empty state if no categories exist.

**Block settings:** Number of columns, show/hide counts, icon size.

### Listing Featured

A featured listings carousel or grid showing listings marked as featured. Useful on homepage sections or category landing pages.

**Block settings:** Layout (carousel / grid), number of listings, listing type filter.

### Listing Calendar

An event calendar view for listings of type **Event**. Shows recurring events, supports date-range filters, and links each event to its listing detail page.

### Listing Card

A standalone single-listing card for custom layouts. Use this block when you want to manually highlight a specific listing anywhere on your site — a sidebar, a landing page section, or a blog post.

## Tips

- Combine **Listing Search** + **Listing Grid** + **Listing Map** in a two-column layout for a split search-and-map experience.
- Set both **Listing Search** and **Listing Grid** to **Wide** alignment for a full-width directory page.
- Use **Listing Featured** on your homepage to promote top listings without affecting the main search results.
- Each block has a **Device Visibility** setting — hide the map on mobile to save screen space.
- The **Listing Grid** block's **Listing Type** setting lets you create a page that shows only restaurants, only hotels, etc., without building a custom query.

## Common issues

| Symptom | Fix |
|---------|-----|
| Block not appearing in editor | Verify WB Listora is active under **Plugins** |
| Search results don't update reactively | The page must load the Interactivity API — check that WordPress 6.4+ is installed |
| Map shows no markers | Ensure listings have an address saved; geocoding runs on save |
| Calendar shows no events | Confirm at least one listing is assigned the **Event** type with a future date |

## Related features

- [Search and Filters](search-and-filters.md)
- [Creating Your Directory Page](../getting-started/creating-directory-page.md)
- [Listing Types](../getting-started/listing-types.md)
