---
journey: browse-and-favourite-a-listing
plugin: wb-listora
priority: critical
roles: [subscriber]
covers: [favorites-flow, search-grid-render, listing-detail-render, modal-getter-pattern]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Dev auto-login mu-plugin installed (?autologin=<user>)"
  - "At least 1 published listora_listing exists with a category"
  - "Test user 'tester' exists (subscriber role) and has zero favorites"
estimated_runtime_minutes: 4
---

# Browse and favourite a listing

A logged-in customer searches the directory, opens a listing's detail page, taps the heart icon to favourite it, and verifies the favourite shows up in their dashboard. Verifies the search grid render, the detail-page modal-getter pattern (commit 63411c8), the favourite REST round-trip, and the user-dashboard refresh. If any layer regresses — search returns empty, detail modal stays stuck, favourite POST 401s, dashboard count doesn't bump — the journey fails at the exact step.

## Setup

- Site: `$SITE_URL`
- Test user: `tester` (autologin via `?autologin=tester`)
- Fixtures needed:
  - 1+ published `listora_listing` posts with a category and at least 1 image
- DB clean (start state):
  ```sql
  DELETE FROM wp_listora_favorites WHERE user_id = (SELECT ID FROM wp_users WHERE user_login='tester');
  ```

## Steps

### 1. Auto-login as tester
- **Action**: `playwright_navigate $SITE_URL/?autologin=tester`
- **Expect**: 302 redirect to `/`; admin bar shows "Howdy, tester" OR `wp_get_current_user()->user_login === 'tester'`
- **On fail**: `wp-content/mu-plugins/dev-auto-login.php` — auto-login mu-plugin missing or broken

### 2. Open the directory page
- **Action**: `playwright_navigate $SITE_URL/listings` (or whichever page hosts the `listora/listing-grid` block)
- **Expect**: DOM contains `.wp-block-listora-listing-grid` with at least 1 `.listora-card` child; `state.results.length > 0` in the IAPI store on first hydration
- **Capture**: `LISTING_ID` ← `document.querySelector('.listora-card').dataset.listingId`
- **On fail**: `blocks/listing-grid/render.php` (server render) or `templates/blocks/listing-grid/grid.php` (commit d5d03dd preserve view_data) or REST `/listora/v1/search` returning empty

### 3. Click into the listing detail page
- **Action**: `playwright_click '.listora-card[data-listing-id="$LISTING_ID"] a.listora-card__title-link'`
- **Expect**: URL becomes `/listing/<slug>`; DOM contains `.wp-block-listora-listing-detail`; `state.activeModal === null` (no modal stuck open)
- **On fail**: `blocks/listing-detail/render.php` — modal-getter regression of commit 63411c8 (boolean getters `isClaimModalOpen`/`isShareModalOpen`/`isLoginModalOpen` not firing)

### 4. Tap the favourite (heart) icon
- **Action**: `playwright_click 'button.listora-detail__favorite'`
- **Expect**:
  - Button gains `is-favorited` class
  - Network shows `POST /wp-json/listora/v1/favorites` returning `200 OK` with `{ favorited: true, listing_id: $LISTING_ID }`
- **On fail**: `includes/rest/class-favorites-controller.php::add_favorite` (REST handler) or `src/interactivity/store.js` `toggleFavorite` action

### 5. Verify DB write
- **Action**: `mysql_query "SELECT user_id, listing_id FROM wp_listora_favorites WHERE user_id=(SELECT ID FROM wp_users WHERE user_login='tester') AND listing_id=$LISTING_ID"`
- **Expect**: exactly 1 row
- **On fail**: REST handler did not call `Favorites::add()` or DB constraint failed

### 6. Open the user dashboard, Favorites tab
- **Action**: `playwright_navigate $SITE_URL/dashboard?tab=favorites`
- **Expect**: DOM contains `.listora-dashboard__favorites-list` with at least 1 `.listora-card` whose `data-listing-id` equals `$LISTING_ID`
- **On fail**: `templates/blocks/user-dashboard/tab-favorites.php` rendering or `Dashboard_Controller::get_favorites` REST endpoint

### 7. Un-favourite from dashboard
- **Action**: `playwright_click '.listora-dashboard__favorites-list .listora-card[data-listing-id="$LISTING_ID"] button.listora-card__favorite'`
- **Expect**:
  - Card disappears from the list (or gains `is-removing`)
  - Network shows `DELETE /wp-json/listora/v1/favorites/$LISTING_ID` returning `200 OK`

### 8. Verify DB removal
- **Action**: `mysql_query "SELECT COUNT(*) AS n FROM wp_listora_favorites WHERE user_id=(SELECT ID FROM wp_users WHERE user_login='tester') AND listing_id=$LISTING_ID"`
- **Expect**: `n = 0`
- **On fail**: `Favorites_Controller::remove_favorite` did not delete the row

## Pass criteria

ALL of the following hold:
1. Search grid renders with at least 1 card
2. Detail page opens with `activeModal === null` (no stuck modal)
3. Favourite POST returns 200 and inserts a row in `wp_listora_favorites`
4. Dashboard Favorites tab lists the favourited listing
5. Un-favourite DELETE returns 200 and removes the row

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Grid renders zero cards despite `wp_listora_listing` posts existing | search params or include-loop drift | `blocks/listing-grid/render.php`, `templates/blocks/listing-grid/grid.php`, commit d5d03dd |
| Detail page renders but modal stays stuck on first navigation | modal-getter regression | `src/interactivity/store.js:89-98`, `blocks/listing-detail/render.php` (commit 63411c8) |
| Heart click does nothing | `toggleFavorite` action not bound or `data-wp-on--click` missing | `src/interactivity/store.js`, `templates/blocks/listing-detail/header.php` |
| 401 on favourite POST | nonce expired or REST cookie auth broken | `includes/rest/class-favorites-controller.php::permissions_check` |
| Dashboard Favorites tab empty after favourite | dashboard cache transient stale | `Dashboard_Controller::get_favorites`, 60s cache-busting hook |
