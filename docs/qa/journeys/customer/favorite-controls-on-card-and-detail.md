---
journey: favorite-controls-on-card-and-detail
plugin: wb-listora
priority: high
roles: [owner, other, editor, guest, admin]
covers: [favorites, listing-detail, iapi, counter-vs-list, listora-card__favorite, cascade-order, all-unset-reset, dashboard-favorites, BC-10195604615]
prerequisites:
  - "$LISTING_FAV: a listing with > 0 favourites; $LISTING_ZERO: a published listing with exactly 0"
  - "{owner} a member who has NOT favourited either; {other} with 20+ favourites (2 dashboard pages)"
estimated_runtime_minutes: 9
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/favorite-count-updates-without-reload.md
  - ora:regression/favorite-heart-overlays-card-image.md
fails_on: unrecorded
site_profile: required
---

# The Save heart overlays the card image and the count beside Save moves when it is pressed

## Why
On the dashboard Favorites tab the heart sat below the image and the click hit the card anchor (two classes `listora-favorite-btn listora-card__favorite`, `templates/blocks/listing-card/card-image.php:76`; `assets/css/listora-base.css:380` resets with `all: unset`, `blocks/listing-card/style.css:137-138` positions, equal specificity so load order decided). On the detail page the count node must exist at zero and follow the toggle.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Detail Save button: `.listora-detail__actions button[data-wp-on--click="actions.toggleFavorite"]`, `aria-label="Save to favorites"`, `aria-pressed` bound to `state.isFavorited` (`blocks/listing-detail/render.php:524`); count `span.listora-detail__favorite-count[data-wp-text="state.favoriteCountDisplay"][data-wp-bind--hidden="!state.hasFavoriteCount"]`, always rendered, `hidden` at zero (`render.php:532`).
- Baseline: `SELECT COUNT(*) FROM ${PREFIX}listora_favorites WHERE user_id = <fav-member id>`.

## Steps

### A. Heart on the card (directory AND dashboard Favorites tab)
1. **It overlays, it does not stack**: `h = document.querySelector('.listora-card__favorite'); m = h.closest('.listora-card__media'); getComputedStyle(h).position === 'absolute' && h.getBoundingClientRect().top >= m.getBoundingClientRect().top && bottom <= m bottom`. **Expect** true. `position: static` is the regression. Assert the COMPUTED value; the rule existing proves nothing.
2. **Topmost at its centre**: `h.contains(document.elementFromPoint(r.x + r.width/2, r.y + r.height/2))` after scrolling into view. **Expect** true (clicks do not pass through to the card anchor).
3. **Clicking toggles, does not navigate**: as {owner} click the heart on the Favorites tab. **Expect**: `is-favorited` flips, `aria-label` follows `state.favoriteAriaLabel`, URL unchanged. Navigating to the listing is the regression.
4. **Positive control, logged out**: as {guest} click a heart on the Directory. **Expect**: login modal opens, not navigation; no favourite row written (`state.loginReason = 'favorite'`).

### B. Count on the detail page
5. **The node exists and is bound**: `.listora-detail__favorite-count` carries `data-wp-text="state.favoriteCountDisplay"`; on $LISTING_ZERO the same node exists with `hidden` (omitting it makes 0 -> 1 impossible).
6. **Non-zero listing: count follows the toggle**: as {owner} read the count on $LISTING_FAV, click Save, wait for the `POST /listora/v1/favorites` response. **Expect**: count +1 exactly, no reload, `aria-pressed="true"`; click again: both return.
7. **Zero listing appears and disappears**: before: node hidden; after Save: visible reading `1`; after un-Save: hidden reading `0`.
8. **The number matches the database**: `SELECT COUNT(*) FROM ${PREFIX}listora_favorites WHERE listing_id=%d` equals the displayed figure after each toggle and REST `favorite_count`.
9. **Arrival state**: `favoriteCountDisplay` = server count + (now ? 1 : 0) - (favoritedAtRender ? 1 : 0). A viewer who arrived already favourited sees a drop of 1 on un-save, not 2. Cover BOTH arrival states (the sign error hides if you test one).

### C. Layout
10. **390px**: heart stays inside the image box with a `--listora-tap-target` (44px) hit area; Save button >= 40px tall, count readable; `document.documentElement.scrollWidth <= 390`. Not verified live.

## Role ladder
| Actor | Expect |
|---|---|
| {owner} | steps 1-3 |
| {owner} / {other} (members) | steps 5-9; each sees their own set |
| {guest} | step 4; Save opens the login modal, count unchanged |
| {editor} | like a member |
| {admin} | control only |

## Pass criteria
1. The heart is `position: absolute` inside `.listora-card__media`, topmost at its centre, and toggles without navigating.
2. The count node exists at 0 and moves by exactly 1 per toggle in both arrival states, equal to the DB.
3. Logged-out click opens the login modal and writes nothing.

## Fail diagnostics
| Symptom | File |
|---|---|
| Heart static / below the image | `blocks/listing-card/style.css:137` selector lost `.listora-card__media` prefix (rule must be `.listora-card__media .listora-card__favorite, .listora-card__favorite`, 0,2,0) |
| Heart missing | `templates/blocks/listing-card/card-image.php:76` |
| Reset wins | `assets/css/listora-base.css:380` `all: unset` |
| Count never moves | `src/interactivity/store.js:611` `favoriteCountDisplay`, `:619` `hasFavoriteCount` |
| No node at zero | `blocks/listing-detail/render.php:532` always-render `<span>` |
| Wrong after toggle | `ctx.favoritedAtRender` / `favoriteCount` in the detail context |

## Not verified
Computed-style, click and toggle steps were not run in a browser (DOM/CSS read and REST only: listing with 3 favourites DB = REST = node text). Do not assume load order for any new rule that positions `.listora-favorite-btn`.

## Cleanup
Re-favourite anything un-favourited; compare `SELECT COUNT(*) FROM ${PREFIX}listora_favorites WHERE user_id=<fav-member id>` with the baseline.
