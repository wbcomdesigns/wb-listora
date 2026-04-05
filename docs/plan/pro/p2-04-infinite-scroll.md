# P2-04 — Infinite Scroll + Load More

## Scope: Pro Only

---

## Overview

Three pagination modes for the listing grid block: traditional **Pagination** (free, default), **Load More** button (Pro), and **Infinite Scroll** (Pro). All three use the same REST search endpoint and differ only in trigger mechanism. The admin selects the preferred mode in settings.

### Why It Matters

- Traditional pagination breaks browsing flow — each click is a full page reload
- "Load More" is a middle ground — user controls when to load, no page reload
- Infinite scroll keeps users engaged on high-volume directories (1000+ listings)
- Real estate, job boards, and restaurant directories all benefit from continuous browsing
- SEO is preserved — `<noscript>` fallback to pagination, `rel="next"` in `<head>`

---

## User Stories

| # | As a... | I want to... | So that... |
|---|---------|-------------|-----------|
| 1 | Site owner | Choose between pagination, load more, or infinite scroll | I pick the browsing style that fits my directory's content volume |
| 2 | Visitor | Click "Load More" to see additional listings | I stay on the same page with my filters intact |
| 3 | Visitor | Have listings load automatically as I scroll | I can browse continuously like Instagram or Pinterest |
| 4 | Visitor | Press the browser back button and return to my scroll position | I don't lose my place after viewing a listing detail page |
| 5 | Search engine | Crawl all listing pages via traditional links | Directory content is fully indexed regardless of pagination mode |
| 6 | Visitor | See a loading indicator when more listings are loading | I know the app is working and haven't hit the end |

---

## Technical Design

### Admin Setting

```php
// Settings → Search & Filtering → Pagination
'pagination_type' => [
    'type'    => 'select',
    'label'   => 'Pagination Style',
    'options' => [
        'pagination'      => 'Page Numbers (default, SEO-friendly)',
        'load_more'       => 'Load More Button (Pro)',
        'infinite_scroll'  => 'Infinite Scroll (Pro)',
    ],
    'default' => 'pagination',
    'pro'     => ['load_more', 'infinite_scroll'],
]
```

### Three Modes — Same Endpoint

All modes call the same REST endpoint:

```
GET /listora/v1/search?keyword=pizza&type=restaurant&page=2&per_page=12
```

Response includes pagination headers:

```
X-WP-Total: 156
X-WP-TotalPages: 13
```

| Mode | Trigger | UI Change |
|------|---------|-----------|
| **Pagination** | User clicks page number | Full page navigation (default, free) |
| **Load More** | User clicks "Load More" button | Appends results to existing grid |
| **Infinite Scroll** | IntersectionObserver sentinel hits viewport | Appends results automatically |

### Interactivity API Store Extension

```js
// Pro extends the shared listora/directory store
const { state, actions } = store('listora/directory', {
    state: {
        pagination: {
            mode: 'pagination', // from server settings
            currentPage: 1,
            totalPages: 1,
            totalResults: 0,
            isLoadingMore: false,
            hasMore: true,
        },
    },
    actions: {
        loadMore: async () => {
            if (state.pagination.isLoadingMore || !state.pagination.hasMore) return;

            state.pagination.isLoadingMore = true;
            state.pagination.currentPage += 1;

            const params = new URLSearchParams(state.search.currentParams);
            params.set('page', state.pagination.currentPage);

            const response = await fetch(`/wp-json/listora/v1/search?${params}`);
            const listings = await response.json();
            const totalPages = parseInt(response.headers.get('X-WP-TotalPages'), 10);

            // Append to existing results (not replace)
            state.listings.items = [...state.listings.items, ...listings];
            state.pagination.hasMore = state.pagination.currentPage < totalPages;
            state.pagination.totalPages = totalPages;
            state.pagination.isLoadingMore = false;

            // Update URL for back button support
            const url = new URL(window.location);
            url.searchParams.set('page', state.pagination.currentPage);
            window.history.pushState({page: state.pagination.currentPage}, '', url);
        },
    },
});
```

### Infinite Scroll: IntersectionObserver

```js
// Sentinel element placed after last listing card in grid
// Observed via data-wp-init directive

callbacks: {
    initInfiniteScroll: () => {
        if (state.pagination.mode !== 'infinite_scroll') return;

        const sentinel = document.querySelector('.listora-scroll-sentinel');
        if (!sentinel) return;

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries[0].isIntersecting && state.pagination.hasMore) {
                    actions.loadMore();
                }
            },
            { rootMargin: '200px' } // Trigger 200px before bottom
        );

        observer.observe(sentinel);
    },
}
```

### SEO Considerations

| Concern | Solution |
|---------|----------|
| Crawlability | `<noscript>` block contains traditional `<a href="?page=2">` pagination links |
| `rel="next/prev"` | Added to `<head>` via `wp_head` hook for all modes |
| URL state | `pushState` updates URL with `?page=N` on each load |
| Back button | `popstate` listener restores scroll position and loaded pages |
| Google crawl | Search engines follow `<link rel="next">` regardless of JS mode |
| Thin content | `noindex` pages with < 3 results (handled by SEO module) |

### Files to Create (wb-listora-pro)

| File | Purpose |
|------|---------|
| `blocks/listing-grid/pagination-pro.js` | Load more + infinite scroll Interactivity API logic |
| `blocks/listing-grid/pagination-pro.css` | Styles for load more button + spinner + sentinel |

### Files to Modify (wb-listora-pro)

| File | Change |
|------|--------|
| `blocks/listing-grid/render.php` (Pro filter) | Add sentinel element + load more button markup |
| `blocks/listing-grid/view.js` (Pro extension) | Initialize IntersectionObserver |

### Files to Modify (wb-listora free)

| File | Change |
|------|--------|
| `includes/admin/class-settings-page.php` | Add `pagination_type` setting (Pro options grayed out) |
| `includes/rest/class-search-controller.php` | Ensure pagination headers are always returned |

### URL + History Management

```
User loads page:        /restaurants/
Loads more (page 2):    /restaurants/?page=2   (pushState)
Loads more (page 3):    /restaurants/?page=3   (pushState)
Clicks listing:         /listing/pizza-palace/
Clicks back:            /restaurants/?page=3   (popstate → restore 3 pages of results)
```

On `popstate`:
1. Read `page` from URL
2. If page > 1 and we don't have the results, re-fetch pages 1 through N
3. Restore scroll position from `sessionStorage`

---

## UI Mockup

### Load More Button Mode

```
┌─────────────────────────────────────────────────────────────┐
│ ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│ │ Card 1   │  │ Card 2   │  │ Card 3   │  │ Card 4   │   │
│ └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
│ ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│ │ Card 5   │  │ Card 6   │  │ Card 7   │  │ Card 8   │   │
│ └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
│ ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│ │ Card 9   │  │ Card 10  │  │ Card 11  │  │ Card 12  │   │
│ └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
│                                                             │
│                 Showing 12 of 156 listings                  │
│                                                             │
│              ┌────────────────────────┐                     │
│              │    Load More (144)     │                     │
│              └────────────────────────┘                     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Load More — Loading State

```
│              ┌────────────────────────┐                     │
│              │    ⟳ Loading...        │                     │
│              └────────────────────────┘                     │
```

### Infinite Scroll — End of Results

```
│ ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│ │ Card 153 │  │ Card 154 │  │ Card 155 │  │ Card 156 │   │
│ └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
│                                                             │
│             ─── You've seen all 156 listings ───            │
│                                                             │
```

### Infinite Scroll — Loading Indicator

```
│ ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐   │
│ │ Card 9   │  │ Card 10  │  │ Card 11  │  │ Card 12  │   │
│ └──────────┘  └──────────┘  └──────────┘  └──────────┘   │
│                                                             │
│                        ⟳ ⟳ ⟳                              │
│                  Loading more listings...                    │
│                                                             │
│ ┌ ─ ─ ─ ─ ┐  ┌ ─ ─ ─ ─ ┐  ┌ ─ ─ ─ ─ ┐  ┌ ─ ─ ─ ─ ┐   │  <-- Skeleton cards
│ │          │  │          │  │          │  │          │   │
│ └ ─ ─ ─ ─ ┘  └ ─ ─ ─ ─ ┘  └ ─ ─ ─ ─ ┘  └ ─ ─ ─ ─ ┘   │
```

### Noscript Fallback

```html
<noscript>
  <nav class="listora-pagination" aria-label="Listing pages">
    <a href="?page=1">1</a>
    <a href="?page=2">2</a>
    <span class="current">3</span>
    <a href="?page=4">4</a>
    ...
    <a href="?page=13">13</a>
  </nav>
</noscript>
```

---

## Implementation Steps

| # | Task | Est. Hours |
|---|------|-----------|
| 1 | Add `pagination_type` admin setting with Pro options | 1 |
| 2 | Load More button markup + CSS | 2 |
| 3 | Load More Interactivity API action — fetch + append results | 3 |
| 4 | Infinite Scroll — IntersectionObserver + sentinel element | 3 |
| 5 | Loading indicator (spinner + skeleton cards) | 2 |
| 6 | "End of results" messaging | 0.5 |
| 7 | URL pushState management (`?page=N`) | 2 |
| 8 | Back button support (popstate + scroll position restore) | 3 |
| 9 | `<noscript>` fallback with traditional pagination links | 1 |
| 10 | `rel="next"` / `rel="prev"` in `<head>` | 1 |
| 11 | Results counter ("Showing X of Y") | 0.5 |
| 12 | Mobile-specific styles (full-width button, touch-friendly) | 1 |
| 13 | Debounce infinite scroll trigger (prevent double-fetch) | 1 |
| 14 | Integration with map — sync new results to map markers | 2 |
| 15 | Automated tests + documentation | 2 |
| **Total** | | **25 hours** |

---

## Competitive Context

| Competitor | Load More / Infinite Scroll? | Our Advantage |
|-----------|----------------------------|---------------|
| GeoDirectory | AJAX load more (basic) | Three modes, SEO-friendly, history management |
| Directorist | AJAX pagination | Proper infinite scroll with IntersectionObserver |
| HivePress | Load more button | URL state management, back button support |
| ListingPro | Infinite scroll (jQuery) | Modern stack (no jQuery), skeleton loading |
| MyListing | Load more (Elementor) | No Elementor dependency, native WP integration |

**Our edge:** Three modes from one setting (no additional addon needed). SEO is preserved via `<noscript>` fallback and `rel="next"` links. Browser history management means users can navigate to a listing, press back, and return to their exact scroll position with all loaded results intact. The IntersectionObserver approach is modern and performant (no scroll event throttling like jQuery solutions).

---

## Effort Estimate

**Total: ~25 hours (3-4 dev days)**

- Admin setting: 1h
- Load More mode: 5h
- Infinite Scroll mode: 6h
- URL + history management: 5h
- SEO (noscript, rel links): 2h
- Map sync: 2h
- Mobile + UX polish: 2h
- Tests + docs: 2h
