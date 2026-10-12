---
journey: browse-and-favourite-a-listing
plugin: wb-listora
priority: critical
roles: [owner, other, guest, editor, admin]
covers: [favorites-flow, search-grid-render, listing-detail-render, modal-getter-pattern, login-gate, favorites-rest-isolation, favorites-feature-gate, dashboard-favorites-template-override, card-9977212895]
prerequisites:
  - "Site reachable at $SITE_URL; dev auto-login mu-plugin installed (?autologin=<login>)"
  - "Features > Favorites ON (wb_listora_features.favorites = true)"
  - "A published listing with a type and a category ({listing}); a member {owner} with zero favourites, a second member {other}"
estimated_runtime_minutes: 8
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - "ora:regression/dashboard-favorites-template-override.md"
site_profile: required
---

# A member finds a listing, saves it, sees it under Dashboard > Favorites (from an overridable template) and un-saves it

## Why
Favourites are the main retention loop: a Save that silently fails, leaks another member's list, or a Favorites tab a theme cannot override breaks members and theme builders. A logged-out visitor must get the login modal, not an error.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README). `{owner}` actor member, `{other}` second member, `{editor}`, `{admin}` control, `{listing}` fixture, `{keyword}` a word matching only `{listing}`.
- URLs: Directory `$SITE_URL/listings/`; Dashboard = `wb_listora_get_dashboard_url()` (`$DASH_URL`, e.g. the "My Listings" page; do not use an unrelated page also named dashboard); detail `$SITE_URL/listing/<slug>/`.
- Clean start: `wp --path=$WP_PATH eval 'global $wpdb; $wpdb->delete("{$wpdb->prefix}listora_favorites", ["user_id"=>(int)get_user_by("login","{owner}")->ID]);'`
- One browser context per role (`?autologin=` is ignored while a session cookie exists).

## Steps

### 1. Log in as the member
- **Action**: open `$SITE_URL/?autologin={owner}`.
- **Expect**: lands on `/`, `body.logged-in` present.

### 2. Empty state is the starting point
- **Action**: open `$DASH_URL?tab=favorites`.
- **Expect**: `#dash-tab-favorites` has `is-active`; `#dash-panel-favorites` (`role=tabpanel`, `aria-labelledby="dash-tab-favorites"`) contains `.listora-empty__title` "No saved listings", `.listora-empty__desc` "Save listings you like by clicking the heart icon." and a **Browse the directory** link to the Directory.
- **Read-back**: `GET /listora/v1/favorites` returns `favorites: []`, `total: 0`.

### 3. Find the listing in the Directory
- **Action**: open `$SITE_URL/listings/?listora_keyword={keyword}`.
- **Expect**: `.listora-grid__count` reads "Showing 1-1 of 1 listings"; one `article.listora-card` whose `data-wp-context` JSON carries `listingId` and `listingUrl` (capture `LISTING_ID`); heart `.listora-card__favorite` has `aria-label="Save to favorites"`, `aria-pressed="false"`.

### 4. Open the detail page, no modal stuck open
- **Action**: click `article.listora-card .listora-card__title a`.
- **Expect**: URL `/listing/<slug>/`; `.wp-block-listora-listing-detail` present; `h1.listora-detail__title` is the title; `document.querySelectorAll('.listora-detail__modal.is-open').length === 0`.

### 5. Tap Save
- **Action**: click `.listora-detail__actions button[data-wp-on--click="actions.toggleFavorite"]` (text "Save").
- **Expect**: `aria-pressed` flips to `true`, `aria-label` becomes "Remove from favorites", class `is-favorited`; `POST /wp-json/listora/v1/favorites` body `{"listing_id": LISTING_ID}` returns `201 {"favorited":true}`; `.listora-detail__favorite-count` visible and reads the new count; no login modal.

### 6. DB check and idempotence
- **Action**: `SELECT COUNT(*) FROM {prefix}listora_favorites WHERE user_id=<owner> AND listing_id=LISTING_ID`.
- **Expect**: `1`. Repeating the POST does not create a second row. Positive control: the same query for `{other}` is `0`, so the check can fail.

### 7. Dashboard Favorites tab lists it
- **Action**: open `$DASH_URL?tab=favorites`.
- **Expect**: `#dash-panel-favorites .listora-dashboard__favorites-grid.listora-grid` holds one `article.listora-card` with the title; `#dash-tab-favorites .listora-dashboard__nav-count` reads `1`; Overview "Saved" stat reads `1` (`?tab=overview`, `.listora-dashboard__stat-label` "Saved").

### 8. The Favorites tab loads from an overridable template (card 9977212895)
- **Action**: the tab renders `templates/blocks/user-dashboard/tab-favorites.php` via `wb_listora_get_template()` (`blocks/user-dashboard/render.php:1081`). Copy `<div id="qa-override-marker">override</div>` to `{active-theme}/wb-listora/blocks/user-dashboard/tab-favorites.php`; reload the tab.
- **Expect**: the marker renders in place of the default panel and the default grid is absent. Remove the file; the default panel returns (positive control: step 7 passes with the file absent, so a hard-coded inline panel - the original defect - is detected).
- **Action**: register listeners `add_action( 'wb_listora_before_dashboard_favorites', fn( $d ) => error_log( 'fav-before ' . wp_json_encode( array_keys( $d ) ) ) )` and the `after_` twin.
- **Expect**: each fires once per render with `$view_data` containing `user_id` and `favorite_ids`; the block's `$attributes` are not clobbered mid-render.

### 9. Un-save from the dashboard
- **Action**: click `.listora-card__favorite` on that card.
- **Expect**: `DELETE /wp-json/listora/v1/favorites/LISTING_ID` returns `200 {"favorited":false}`; the card is removed; because it was the last one the page reloads and shows "No saved listings". A second DELETE still returns `200 {"favorited":false}` (idempotent); step 6's row is gone (`COUNT = 0`).

### 10. Role ladder
| Actor | Action | Expect |
|---|---|---|
| guest | open detail, click Save | `#listora-login-modal` gets `is-open`; heading "Log in to save listings"; text "Sign in to save this listing to your favorites and access it from any device."; links "Log in" and "Create account"; Esc closes it; `aria-pressed` stays `false`; no `POST /favorites`. curl `POST /favorites` without session: `401` |
| `{other}` | `GET /listora/v1/favorites` after `{owner}` saved | `200`, `favorites: []`, `total: 0`; their Favorites tab shows "No saved listings" |
| `{editor}` | steps 5-9 | `201` then `200` |
| `{admin}` (control) | steps 5-9 | same results (flow is not admin-only) |
| listing owner | `POST /favorites` on own listing | `201` |
| any member, unknown id | `POST /favorites` `listing_id: 999999` | `404` `listora_invalid_listing` |

### 11. Feature gate
- **Action**: Settings > Features, turn Favorites OFF; reload a card and the detail page; `POST /favorites` as `{owner}`; turn it ON again.
- **Expect OFF**: no `.listora-card__favorite`, no Save button; REST `403 listora_favorites_disabled` ("Favorites are not enabled."); no `#dash-tab-favorites`. **Expect ON**: steps 3-5 behave as above.
- **Restore** the feature to ON.

### 12. Scale, paging and layout
- **Scale**: the Directory is paginated (20 per page); `GET /favorites` returns `total,pages,has_more`; a member with 20+ favourites shows "Page 1 of N" on the shared pagination nav (`favorites_page`, `wb_listora_render_pagination()`). Seed 25 favourites for `{owner}` (QA-prefixed listings) and confirm page 2 works; a count/badge read comes from `COUNT(*)`, not row loading.
- **390px**: detail page and Directory logged out and in: `scrollWidth <= 390`; Save button >= 40px high; card heart >= 40px high.
- **Add/remove round-trip**: add and remove a favourite from the Directory heart, reload: tab and badge follow (badge 1 -> 2 -> 1).

## Role ladder
See step 10.

## Pass criteria
1. Directory renders cards with a heart that starts unpressed; detail opens with no `.is-open` modal.
2. Save returns 201 `{favorited:true}`, flips `aria-pressed`, inserts exactly one row; un-save returns 200 `{favorited:false}` and removes it.
3. Dashboard Favorites lists it from the overridable template; nav count and "Saved" stat agree; before/after hooks fire once.
4. Guest gets the login modal and a 401; another member sees an empty list; editor and admin behave like the member.
5. Feature OFF hides the controls and 403s the API; ON restores.
6. No horizontal scroll at 390.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Grid renders zero cards though listings exist | search index empty or query drift | `blocks/listing-grid/render.php`, `includes/rest/class-search-controller.php:370` |
| Heart missing on every card | Favorites feature off | `templates/blocks/listing-card/card-image.php:73`, `includes/class-features.php:227` |
| Detail modal stuck open on load | getter regression | `src/interactivity/store.js:512-520` |
| Click on Save does nothing | handler unbound | `blocks/listing-detail/render.php:524`, `src/interactivity/store.js:1392` |
| POST 401 as logged-in user | nonce / REST cookie auth | `includes/rest/class-favorites-controller.php:392` |
| POST 403 `listora_favorites_disabled` | feature toggle | `includes/rest/class-favorites-controller.php:224` |
| Dashboard Favorites stale after save | cache not busted | `includes/rest/class-dashboard-controller.php`, `includes/core/class-favorites-cache.php` |
| Override ignored | panel inline again | `blocks/user-dashboard/render.php:1081` |
| Hooks missing / `$view_data` lacks keys | removed from template / view array changed | `templates/blocks/user-dashboard/tab-favorites.php:20,78`, `render.php:1074-1076` |

## Not verified
Step 8 override file and hook listeners, the empty-state browser rendering, and the 25-favourite paging seed in step 12 were not run live (the badge round-trip via REST was).

## Cleanup
Remove the theme override file and hook listeners; restore the Favorites feature switch; delete QA favourite seeds.
