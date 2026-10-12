---
journey: favorite-count-updates-without-reload
plugin: wb-listora
priority: high
roles: [member, anonymous, admin]
covers: [favorites, listing-detail, iapi, counter-vs-list]
prerequisites:
  - "A listing with >0 favourites (id 17 /listing/the-golden-fork/ has 3) and a published listing with exactly 0"
  - "A logged-in member who has NOT favourited either (qa_vendor_02, or subscriber2)"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The count beside Save moves when Save is pressed

The Save button in `.listora-detail__actions` (`blocks/listing-detail/render.php:524`) is `button[data-wp-on--click="actions.toggleFavorite"]`, `aria-label="Save to favorites"`, with `aria-pressed` bound to `state.isFavorited`. The count is `span.listora-detail__favorite-count[data-wp-text="state.favoriteCountDisplay"][data-wp-bind--hidden="!state.hasFavoriteCount"]`, always rendered and `hidden` at zero. There is no `.listora-detail__favorite` class any more.

Verified via: DOM of listing 17 (curl): node present with text `3`, bound as above. Toggle steps (2, 3, 5) need a browser and were not run.

## Steps

### 1. The node exists and is bound
- **Expect**: `.listora-detail__favorite-count` carries `data-wp-text="state.favoriteCountDisplay"`; on a 0-count listing the same node exists with the `hidden` attribute (omitting it makes 0 -> 1 impossible).

### 2. Non-zero listing: count follows the toggle
- **Action**: read the count, click Save, wait for the `POST /listora/v1/favorites` response.
- **Expect**: count +1 exactly, no reload, `aria-pressed="true"`. Click again: both return.

### 3. Zero listing: appears and disappears
- **Expect**: before: node hidden; after Save: visible reading `1`; after un-Save: hidden again reading `0`.

### 4. The number matches the database
- **Action**: `SELECT COUNT(*) FROM wp_listora_favorites WHERE listing_id=%d`.
- **Expect**: equals the displayed figure after each toggle. Listing 17: DB 3 = REST `favorite_count` 3 (verified).

### 5. Another member's toggle does not shift this viewer's base
- **Expect**: `favoriteCountDisplay` = server count + (now ? 1 : 0) - (favoritedAtRender ? 1 : 0). A viewer who arrived already-favourited sees a drop of 1 on un-save, not 2. Cover BOTH arrival states (the sign error hides if you only test one).

### 6. Role ladder
- Owner of the favourite (member): steps 2-5. Another member: same. Logged out: Save opens the login modal (`state.loginReason = 'favorite'`), count unchanged. Editor and admin behave as members; admin is the control only.

### 7. 390px
- **Expect**: Save button >= 40px tall, count readable, no horizontal scroll. Not run.

## Fail diagnostics

| Symptom | File |
|---|---|
| Count never moves | `src/interactivity/store.js:611` `favoriteCountDisplay`, `:619` `hasFavoriteCount` |
| No node at zero | `blocks/listing-detail/render.php:532` always-render `<span>` |
| Wrong after toggle | `ctx.favoritedAtRender` / `favoriteCount` set in the detail context |
