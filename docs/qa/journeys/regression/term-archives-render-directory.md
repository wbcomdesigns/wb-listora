---
journey: term-archives-render-directory
plugin: wb-listora
priority: critical
roles: [anonymous, member, editor, admin]
covers: [term-archive-directory, listing-archive-redirect, term-archive-canonical]
prerequisites:
  - "Site reachable at $SITE_URL with demo content and a Directory page mapped (here /listings/)"
  - "Term slugs on this site: listing-category/business (6 published), listing-location/us (78), listing-tag/waterfront (5), listing-feature/wifi (38)"
estimated_runtime_minutes: 10
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 820, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Listing term archives show the Directory

Cards 10370410858 and 10355778337 (owner decision 2026-10-11). `Term_Archives` (`includes/core/class-term-archives.php`) renders the Directory layout filtered to the term for `listora_listing_cat`, `_location`, `_tag`, `_feature` and `_type` (filter map `FILTERS`, `:27-33`), and 301-redirects the bare listings archive to the Directory page.

## Steps

### 1. Term archives
- **Action**: open `/listing-category/business/`, `/listing-location/us/`, `/listing-tag/waterfront/`, `/listing-feature/wifi/` at 1440 and 390, light and dark.
- **Expect**: HTTP 200; the Directory layout (search, type chips, "Filters" button `.listora-search__toggle-btn` with `.listora-search__filter-count`, map, cards); `h1` and browser title are the term name (`Business – <site>`); breadcrumb `nav.listora-term-archive__crumbs[aria-label="Breadcrumb"]` reads "Directory › Business" with the first link to `/listings/`; the term description (when set) in `.listora-term-archive__desc`; no "Read More" and no blog widgets; `<link rel="canonical">` is the term URL; `body` has class `listora-term-archive`. All checked via curl 2026-10-12 for the four URLs.
- **Expect**: result count matches the term's published listings: `GET /listora/v1/search?category=business&per_page=1` -> `X-WP-Total: 6` (2026-10-12), and the page counter (`.listora-grid__count`, "Showing 1-N of N listings") agrees.

### 2. Unknown term and bare archive
- **Expect**: `/listing-category/no-such-term/` is HTTP 404 with the theme 404 and no `listora-term-archive` class; `/listing/` 301-redirects to the Directory page (`http://wb-listora.local/listings/`). Both verified.

### 3. Large data
- **Action**: open the largest archive (`/listing-location/us/`, 78 listings here; on a 2000+ listing term the same).
- **Expect**: page 1 renders the page size, pagination shows page numbers / Load More, the count matches `X-WP-Total`, and `?listora_page=` past the last page shows the last page (not "Showing 1,961-127 of 127"). The directory total site-wide is 2809 listings; no unbounded query.

### 4. Escape hatch
- **Action**: `add_filter( 'wb_listora_term_archive_filters', '__return_empty_array' );`
- **Expect**: term archives go back to the theme's loop (the dead-end crawler, local-CI stage 2.6, fails them).

### 5. Role ladder
Logged out (anonymous): steps 1-3. Logged-in member and editor: the same page (no admin chrome in the grid). Admin: same plus the admin bar (control). Archives never expose unpublished listings to any of them.

### 6. Mobile / dark
At 390: no horizontal scroll, breadcrumb wraps, Filters button >= 40px tall. Dark mode: breadcrumb and description readable (token colours).

## Pass criteria
1. Every listing term URL shows the Directory filtered to that term.
2. Canonical stays on the term URL.
3. Unknown term 404; bare archive redirects.

## Fail diagnostics
| Symptom | File |
|---|---|
| Archive shows the theme loop | `includes/core/class-term-archives.php:80` filter / `FILTERS` map `:27` |
| Heading shows the site title (BuddyX Pro) | the title-outside-the-loop override in the same class |
| Breadcrumb/desc missing | `class-term-archives.php:165-183` |
| Body class missing | `class-term-archives.php:194` |

## Not verified
Steps 1-2 were run with curl as an anonymous visitor (HTML/DOM, no browser): headings, titles, canonical, crumbs, body class, 404 and 301. The "1 active filter" badge value (client-rendered `state.activeFilterCount`), the description, 390/dark visuals, step 3 and 4 were not run.

Verified via: curl/DOM.
