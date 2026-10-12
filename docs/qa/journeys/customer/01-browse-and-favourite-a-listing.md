---
journey: browse-and-favourite-a-listing
plugin: wb-listora
priority: critical
roles: [subscriber, logged-out, other-member, editor, admin]
covers: [favorites-flow, search-grid-render, listing-detail-render, modal-getter-pattern, login-gate, favorites-rest-isolation]
prerequisites:
  - "Site reachable at $SITE_URL (Local: http://wb-listora.local)"
  - "Dev auto-login mu-plugin installed (?autologin=<login>)"
  - "Features > Favorites ON (wb_listora_features.favorites = true)"
  - "A published listing with a type and a category; below it is called LISTING (fixture QA-J1 Zephyrquill Cafe, or any listing)"
  - "A subscriber with zero favourites (QA-J1-member), a second subscriber (QA-J1-other)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# Browse and favourite a listing

A member finds a listing in the Directory, opens it, taps Save, sees it under My Dashboard > Favorites, and un-saves it from there. A logged-out visitor gets the login modal instead. Another member never sees the first member's saved list.

Verified via: Playwright (isolated context per role) for the UI flow, curl for REST.

## Setup

- Site: `$SITE_URL`. Directory = `$SITE_URL/listings/`. Dashboard = `$SITE_URL/my-listings/` (the page titled "My Listings"; `/dashboard/` on this site is an unrelated Directorist page, do not use it). Detail URL = `$SITE_URL/listing/<slug>/`.
- Roles: `QA-J1-member` (subscriber, actor), `QA-J1-other` (subscriber), logged out, `qa_editor` (editor), `varundubey` (admin, control).
- Clean start (WP-CLI eval): `$wpdb->delete("{$wpdb->prefix}listora_favorites", ['user_id'=>$member_id]);`
- Browser tip: use one browser context per role. `?autologin=` is ignored while a session cookie exists.

## Steps

### 1. Log in as the member
- **Action**: open `$SITE_URL/?autologin=QA-J1-member`.
- **Expect**: lands on `/`, `body.logged-in` present.

### 2. Empty state is the starting point
- **Action**: open `$SITE_URL/my-listings/?tab=favorites`.
- **Expect**: `#dash-tab-favorites` has class `is-active`; `#dash-panel-favorites` is visible and contains `.listora-empty__title` "No saved listings", `.listora-empty__desc` "Save listings you like by clicking the heart icon." and a link "Browse the directory".
- **On fail**: `templates/blocks/user-dashboard/tab-favorites.php:22`

### 3. Find the listing in the Directory
- **Action**: open `$SITE_URL/listings/?listora_keyword=zephyrquill` (any keyword that matches LISTING).
- **Expect**:
  - `.listora-grid__count` reads "Showing 1-1 of 1 listings".
  - One `article.listora-card` (role `listitem`). Its `data-wp-context` JSON carries `listingId` and `listingUrl` (there is no `data-listing-id` attribute). Capture `LISTING_ID` from `listingId`.
  - The card heart `.listora-card__favorite` has `aria-label="Save to favorites"` and `aria-pressed="false"`.
- **On fail**: `blocks/listing-grid/render.php`, `templates/blocks/listing-card/card-image.php:76`, REST `GET /listora/v1/search?keyword=...` (response keys `listings,total,pages,has_more`)

### 4. Open the detail page, no modal stuck open
- **Action**: click `article.listora-card .listora-card__title a`.
- **Expect**: URL is `/listing/<slug>/`; `.wp-block-listora-listing-detail` present; `h1.listora-detail__title` is the listing title; `document.querySelectorAll('.listora-detail__modal.is-open').length === 0`.
- **On fail**: `blocks/listing-detail/render.php:951` (modal markup), `src/interactivity/store.js:512` (`isClaimModalOpen` getter, same pattern for login/report/share)

### 5. Tap Save
- **Action**: click `.listora-detail__actions button[data-wp-on--click="actions.toggleFavorite"]` (visible text "Save").
- **Expect**:
  - `aria-pressed` flips `false` to `true`, `aria-label` becomes "Remove from favorites", button gains class `is-favorited`.
  - Network: `POST /wp-json/listora/v1/favorites` body `{"listing_id": LISTING_ID}` returns `201` with `{"favorited":true}`.
  - `.listora-detail__favorite-count` is visible and reads `1`.
  - No login modal opens.
- **On fail**: `src/interactivity/store.js:1392` (`toggleFavorite`), `includes/rest/class-favorites-controller.php:212` (`add_favorite`)

### 6. DB check
- **Action**: `SELECT COUNT(*) FROM {prefix}listora_favorites WHERE user_id=<member> AND listing_id=LISTING_ID`
- **Expect**: `1`. Repeating the POST (or step 5 again) does not create a second row.

### 7. Dashboard Favorites tab lists it
- **Action**: open `$SITE_URL/my-listings/?tab=favorites`.
- **Expect**: `#dash-panel-favorites .listora-dashboard__favorites-grid` holds one `article.listora-card` whose title is the listing; `#dash-tab-favorites .listora-dashboard__nav-count` reads `1`; the Overview tab stat "Saved" reads `1` (`?tab=overview`, `.listora-dashboard__stat-label` "Saved").
- **On fail**: `templates/blocks/user-dashboard/tab-favorites.php`, `includes/rest/class-dashboard-controller.php`

### 8. Un-save from the dashboard
- **Action**: click `.listora-card__favorite` on that card.
- **Expect**: network `DELETE /wp-json/listora/v1/favorites/LISTING_ID` returns `200` `{"favorited":false}`; the card is removed; because it was the last one the page reloads and shows "No saved listings".
- **On fail**: `src/interactivity/store.js:1419-1435`, `includes/rest/class-favorites-controller.php:324`

### 9. DB removal
- **Expect**: the row from step 6 is gone (`COUNT = 0`). A second `DELETE` still returns `200 {"favorited":false}` (idempotent).

### 10. ROLE LADDER
| Actor | Action | Expect |
|---|---|---|
| Logged out | open the detail page, click Save | `#listora-login-modal` gets `is-open`; heading `#listora-login-modal-title` "Log in to save listings"; text "Sign in to save this listing to your favorites and access it from any device."; links "Log in" and "Create account"; Esc closes it; `aria-pressed` stays `false`; no `POST /favorites` fired. `POST /favorites` via curl without a session returns `401`. |
| Other member (`QA-J1-other`) | `GET /listora/v1/favorites` after the member saved LISTING | `200`, `favorites: []`, `total: 0` (a member only ever sees their own list). Their dashboard Favorites tab shows "No saved listings". |
| Editor (`qa_editor`) | repeat steps 5-9 | same results (`201`, then `200`). |
| Admin (control) | repeat steps 5-9 | same results; proves the flow is not admin-only and not blocked for non-admins. |
| Listing owner | `POST /favorites` on own listing | `201` (owners may save their own listing). |
| Any member, unknown id | `POST /favorites` with `listing_id: 999999` | `404` code `listora_invalid_listing`. |

### 11. POSITIVE CONTROL for the feature gate
- **Action**: Settings > Features, turn Favorites OFF, reload a card and the detail page; then `POST /favorites` as the member. Turn it back ON.
- **Expect OFF**: no `.listora-card__favorite` and no Save button rendered; REST returns `403` code `listora_favorites_disabled` ("Favorites are not enabled."); dashboard has no `#dash-tab-favorites`. **Expect ON**: steps 3-5 behave as above (this is the control that proves the OFF assertions are not an unrelated outage).
- **Restore**: set the feature back to ON before ending.

### 12. Scale and layout
- **Scale**: the Directory is paginated (20 per page, `.listora-grid__pagination`); `GET /search?per_page=1` returns the real total in `total` and in the `X-WP-Total` header. On this site only about 100 listings are published (2,700+ are drafts, so the public Directory cannot be used for a 2,000-row check; use admin All Listings for that). Favourites are fetched per user and paginated (`GET /favorites` returns `total,pages,has_more`).
- **390px**: open the detail page and the Directory logged out and logged in. `document.documentElement.scrollWidth <= 390`; the Save button is at least 44px high (measured 311x44); the card heart is at least 46px high (measured 70x46 with count).

## Pass criteria

1. Directory renders cards with a heart that starts unpressed.
2. Detail page opens with no `.is-open` modal.
3. Save returns 201 `{favorited:true}`, flips `aria-pressed`, inserts exactly one row.
4. Dashboard Favorites lists it and the nav count and "Saved" stat agree.
5. Un-save returns 200 `{favorited:false}` and removes the row.
6. Logged out gets the login modal and a 401; another member sees an empty list; editor and admin behave like the member.
7. Feature OFF hides the controls and 403s the API; ON restores them.
8. No horizontal scroll at 390.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Grid renders zero cards though listings exist | search index empty or query drift | `blocks/listing-grid/render.php`, `includes/rest/class-search-controller.php:370` |
| Heart missing on every card | Favorites feature off | `templates/blocks/listing-card/card-image.php:73`, `includes/class-features.php:227` |
| Detail modal stuck open on load | getter regression | `src/interactivity/store.js:512-520` |
| Click on Save does nothing | handler unbound | `blocks/listing-detail/render.php:524`, `src/interactivity/store.js:1392` |
| POST returns 401 as a logged-in user | nonce missing / REST cookie auth | `includes/rest/class-favorites-controller.php:392` |
| POST returns 403 `listora_favorites_disabled` | feature toggle | `includes/rest/class-favorites-controller.php:224` |
| Dashboard Favorites stale after a save | dashboard cache not busted | `includes/rest/class-dashboard-controller.php`, `includes/core/class-favorites-cache.php` |
| Un-save leaves the card on screen | `#dash-panel-favorites` selector drift | `src/interactivity/store.js:1406` |
