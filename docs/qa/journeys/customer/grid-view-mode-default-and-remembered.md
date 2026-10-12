---
journey: grid-view-mode-default-and-remembered
plugin: wb-listora
priority: high
roles: [guest, owner, admin]
covers: [listing-grid, interactivity-store, card-10294600329]
prerequisites:
  - "Directory page with a listing-grid block at its default (grid) view"
  - "Permission to create and delete a temporary page"
estimated_runtime_minutes: 5
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
supersedes:
  - ora:regression/grid-default-view-and-remembered-choice.md
fails_on: unrecorded
site_profile: required
---

# The grid honours the block's Default View and the visitor's remembered choice

## Why
`blocks/listing-grid/render.php:144` seeds `defaultViewMode` into the `listora/directory` state; getters read `viewMode || defaultViewMode` (`src/interactivity/store.js:449`); the visitor's choice lives in `localStorage['listora_view_mode']` (`store.js:335`). A list default flipped to grid on hydration.

## Setup
- Resolve placeholders from docs/qa/sites/<site>.json (see README).
- Toggle: `div.listora-grid__view-toggle[role="radiogroup"][aria-label="View mode"]` with two `role="radio"` buttons (`aria-label` "Grid view" / "List view", `aria-checked` bound to `state.isGridView` / `state.isListView`, `templates/blocks/listing-grid/toolbar.php:53-84`). The server paints `listora-grid--list` on `.listora-grid__results` for a list default.
- `wp post create --post_type=page --post_status=publish --post_name=qa-list-default --post_title="QA list default" --post_content='<!-- wp:listora/listing-grid {"defaultView":"list","perPage":6} /-->' --porcelain`.
- After a ~1 s hydration wait read `document.querySelector('.listora-grid__results').classList.contains('listora-grid--list')`, the radios' `aria-checked`, and `localStorage.getItem('listora_view_mode')`.

## Steps

### 1. Default View = List, no visitor choice
- **Action**: clear the key, load `$SITE_URL/qa-list-default/`.
- **Expect**: server HTML has `listora-grid--list` and the List radio `aria-checked="true"`; after hydration still list; key null.

### 2. Visitor choice outranks Default View
- **Action**: click "Grid view", reload.
- **Expect**: grid, key `grid`, Grid radio checked.

### 3. Default grid directory, no choice (positive control: not stuck on list)
- **Action**: clear the key, load `$SITE_URL/{directory-path}/`.
- **Expect**: `.listora-grid__results` has no `listora-grid--list`; Grid radio `aria-checked="true"`, List `"false"`; state `defaultViewMode` "grid".

### 4. Remembered list on a default-grid directory
- **Action**: click "List view", reload.
- **Expect**: list, key `list`, zero console errors.

### 5. 390px
- **Expect**: toggle visible, 40px tap targets, no horizontal scroll. Not verified live.

## Role ladder
Presentation state held in the visitor's browser: {guest}, {owner}, {editor} and {admin} behave identically; there is no per-account setting. {admin} is the control for the page create/delete.

## Pass criteria
1. A list default paints list server-side and after hydration.
2. A stored choice outranks the block default and survives reload.
3. A grid default is not stuck on list.

## Fail diagnostics
| Symptom | File |
|---|---|
| List default flips to grid on hydration | `store.js:441-449` `isListView` getter, `blocks/listing-grid/render.php:144` seed |
| Choice not remembered | `store.js:335` `VIEW_MODE_KEY`, `actions.setViewMode` (`toolbar.php:58,80`) |
| Stale build | rebuild `build/interactivity/store.js` and `build/blocks/*/view.js` |

## Not verified
Hydration and localStorage steps need a browser and were only verified on server DOM (curl).

## Cleanup
Clear the key; `wp post delete <temp page id> --force`.
