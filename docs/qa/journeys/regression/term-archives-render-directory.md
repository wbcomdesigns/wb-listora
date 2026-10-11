---
journey: term-archives-render-directory
plugin: wb-listora
priority: critical
roles: [anonymous]
covers: [term-archive-directory, listing-archive-redirect, term-archive-canonical]
prerequisites:
  - "Site reachable at $SITE_URL with demo content and a Directory page mapped"
estimated_runtime_minutes: 10
---

# Listing term archives show the Directory

Cards 10370410858 and 10355778337 (owner decision 2026-10-11).

## Steps

### 1. Term archives
- **Action**: open /listing-category/<slug>/, /listing-location/<slug>/, /listing-tag/<slug>/ at 1440 and 390, light and dark.
- **Expect**: the Directory page layout (search, chips, Filters showing 1 active filter, map, cards) filtered to the term; H1 and browser title = term name; breadcrumb "Directory › Term"; term description when set; no "Read More", no blog widgets; `<link rel=canonical>` = the term URL; body class `listora-term-archive`.
- **Expect**: result count matches the term's listing count.

### 2. Unknown term and bare archive
- **Expect**: /listing-category/no-such-term/ is a 404; /listing/ 301-redirects to the Directory page.

### 3. Escape hatch
- **Action**: `add_filter( 'wb_listora_term_archive_filters', '__return_empty_array' );`
- **Expect**: term archives go back to the theme (and the dead-end crawler, local-CI stage 2.6, fails them).
- **On fail**: `includes/core/class-term-archives.php`.

## Pass criteria

ALL of the following hold:
1. Every listing term URL shows the Directory filtered to that term.
2. Canonical stays on the term URL.
