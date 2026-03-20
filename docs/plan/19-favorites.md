# 19 — Favorites / Bookmarks

## Scope

| | Free | Pro |
|---|---|---|
| Save/unsave listings | Yes | Yes |
| View saved listings | Yes | Yes |
| Favorite count per listing | Yes | Yes |
| Named collections | — | Yes |
| Share collections | — | Yes |

---

## UX Flow

### Save a Listing
1. Visitor sees heart icon on listing card or detail page
2. Click heart → if logged in: instant save (optimistic UI)
3. Click heart → if not logged in: show login prompt
4. Heart fills in, count increments
5. REST API: `POST /listora/v1/favorites` with listing_id

### Unsave a Listing
1. Click filled heart → instant removal (optimistic UI)
2. Heart empties, count decrements
3. REST API: `DELETE /listora/v1/favorites/{listing_id}`

### View Saved
- Dashboard → My Favorites tab
- Grid of saved listing cards
- Remove button on each card

---

## Pro: Collections

```
┌─────────────────────────────────────────────────────┐
│ My Favorites                                        │
│                                                     │
│ [All (18)] [Date Night (5)] [Weekend (8)] [+ New]  │
│                                                     │
│ Save to collection:                                 │
│ When clicking heart, popup asks:                    │
│ ┌───────────────────────────┐                      │
│ │ Save to:                  │                      │
│ │ ☑ All Favorites          │                      │
│ │ ☐ Date Night             │                      │
│ │ ☐ Weekend Brunch         │                      │
│ │ [ + New Collection     ] │                      │
│ │              [Save]       │                      │
│ └───────────────────────────┘                      │
└─────────────────────────────────────────────────────┘
```

---

## REST API

```
GET    /listora/v1/favorites              → user's favorites
POST   /listora/v1/favorites              → add favorite { listing_id, collection }
DELETE /listora/v1/favorites/{listing_id}  → remove favorite
GET    /listora/v1/favorites/collections   → list user's collections (Pro)
```

---

## Interactivity API

```javascript
state: {
  favorites: new Set(),     // Set of listing IDs
  get isFavorited() {       // Computed per card context
    return state.favorites.has(getContext().listingId);
  }
},
actions: {
  toggleFavorite: async () => {
    const { listingId } = getContext();
    // Optimistic update
    if (state.favorites.has(listingId)) {
      state.favorites.delete(listingId);
      await apiFetch({ path: `/listora/v1/favorites/${listingId}`, method: 'DELETE' });
    } else {
      state.favorites.add(listingId);
      await apiFetch({ path: '/listora/v1/favorites', method: 'POST', data: { listing_id: listingId } });
    }
  }
}
```

Initial favorites loaded in page context for logged-in users (avoid extra API call).

---

## Theme Adaptive

```css
.listora-card__favorite {
  color: var(--wp--preset--color--contrast-2, #999);
  transition: color 0.2s;
}
.listora-card__favorite.is-favorited {
  color: var(--wp--preset--color--vivid-red, #cf2e2e);
}
```

Heart icon uses SVG — filled when favorited, outline when not.
