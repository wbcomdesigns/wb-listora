---
journey: favorite-heart-overlays-card-image
plugin: wb-listora
roles: [member, anonymous, admin]
priority: high
covers: [listora-card__favorite, cascade-order, all-unset-reset, dashboard-favorites, BC-10195604615]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "A member with favourites: subscriber3 (25 favourites, 2 dashboard pages)"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The favourite heart overlays the card image, on every surface

On the dashboard Favorites tab the heart once sat below the image in normal flow and the click landed on the card anchor. Cause: the button carries two classes (`listora-favorite-btn listora-card__favorite`, `templates/blocks/listing-card/card-image.php:76`); `assets/css/listora-base.css:380` resets `.listora-favorite-btn` with `all: unset` (incl. `position`) and `blocks/listing-card/style.css:137-138` positions it, at equal specificity, so sheet load order decided. The block rule is now `.listora-card__media .listora-card__favorite, .listora-card__favorite` (0,2,0), so it wins by specificity. Do not assume load order for any new rule that positions `.listora-favorite-btn`.

Verified via: DOM of `/listings/` and subscriber3's `/my-listings/?tab=favorites` (curl): 20 hearts, each inside `.listora-card__media`; CSS read. Computed-style and click steps need a browser and were not run.

## Steps

Run on `/listings/` (directory) AND the dashboard Favorites tab.

### 1. It overlays, it does not stack
```js
const h = document.querySelector('.listora-card__favorite');
const m = h.closest('.listora-card__media');
const r = h.getBoundingClientRect(), mr = m.getBoundingClientRect();
getComputedStyle(h).position === 'absolute' && r.top >= mr.top && r.bottom <= mr.bottom
```
- **Expect**: true. `position: static` is the regression. Assert the COMPUTED value; the rule existing in the stylesheet proves nothing.

### 2. It is the topmost thing at its centre
```js
const at = document.elementFromPoint(r.x + r.width/2, r.y + r.height/2);
h.contains(at)
```
- **Expect**: true (scroll into view first). The card anchor means clicks pass through.

### 3. Clicking toggles, does not navigate
- **Action**: click the heart on the dashboard Favorites tab as subscriber3.
- **Expect**: `is-favorited` flips, `aria-label` follows `state.favoriteAriaLabel`, URL unchanged. Navigating to the listing is the regression.

### 4. POSITIVE CONTROL for logged out
- **Action**: as anonymous click a heart on `/listings/`.
- **Expect**: login modal opens, not navigation; no favourite row written.

### 5. Role ladder
- Owner of the favourite (subscriber3): steps 1-3. Another member sees their own set; logged out step 4; editor like a member; admin is the control only.

### 6. 390px
- **Expect**: heart stays inside the image box with `--listora-tap-target` (44px) hit area, body `scrollWidth <= 390`.

## Cleanup

Re-favourite anything un-favourited; compare `SELECT COUNT(*) FROM wp_listora_favorites WHERE user_id=5` with the baseline 25.

## Fail diagnostics

| Symptom | File |
|---|---|
| Heart static / below the image | `blocks/listing-card/style.css:137` selector lost `.listora-card__media` prefix |
| Heart missing | `templates/blocks/listing-card/card-image.php:76` |
| Reset wins | `assets/css/listora-base.css:380` `all: unset` |
