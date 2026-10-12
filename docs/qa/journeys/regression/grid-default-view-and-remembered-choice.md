---
journey: grid-default-view-and-remembered-choice
plugin: wb-listora
priority: high
roles: [anonymous, member, admin]
covers: [listing-grid, interactivity-store, card-10294600329]
prerequisites:
  - "Site reachable at http://wb-listora.local"
  - "Directory page with a listing-grid block at its default (grid) view: /listings/"
  - "Permission to create and delete a temporary page"
estimated_runtime_minutes: 4
last_verified: 2026-10-12
verified_against: free e4f9f093 / pro f32f87d
viewports: [1440, 390]
themes: [buddyx-pro light, buddyx-pro dark, one unknown default theme]
changed: true
---

# The grid honours the block's Default View AND the visitor's remembered choice

`blocks/listing-grid/render.php:144` seeds `defaultViewMode` (`'list'` or `'grid'`) into the `listora/directory` state; the store getters read `viewMode || defaultViewMode` (`src/interactivity/store.js:449`); the visitor's choice persists in `localStorage['listora_view_mode']` (`store.js:335`). The toggle is `div.listora-grid__view-toggle[role="radiogroup"][aria-label="View mode"]` with two `role="radio"` buttons, `aria-label` "Grid view" / "List view", `aria-checked` bound to `state.isGridView` / `state.isListView` (`templates/blocks/listing-grid/toolbar.php:53-84`). The server paints the class `listora-grid--list` on `.listora-grid__results` for a list default.

Verified via: server DOM of a temporary default-list page and `/listings/` (curl), 2026-10-12; localStorage and hydration steps need a browser and were not run.

## Setup

```bash
wp post create --post_type=page --post_status=publish --post_name=qa-j1-list-default \
  --post_title="QA-J1 list default" \
  --post_content='<!-- wp:listora/listing-grid {"defaultView":"list","perPage":6} /-->' --porcelain
```

After a ~1 s hydration wait read: `document.querySelector('.listora-grid__results').classList.contains('listora-grid--list')`, the radios' `aria-checked`, and `localStorage.getItem('listora_view_mode')`.

## Steps

### 1. Default View = List, no visitor choice
- **Action**: clear the key, load `/qa-j1-list-default/`.
- **Expect**: server HTML has `listora-grid--list` and the List radio `aria-checked="true"` (verified); after hydration still list; key null.

### 2. Visitor choice outranks Default View
- **Action**: click "Grid view", reload.
- **Expect**: grid, key `grid`, Grid radio checked.

### 3. Default grid directory, no choice (POSITIVE CONTROL: not stuck on list)
- **Action**: clear the key, load `/listings/`.
- **Expect**: `.listora-grid__results` has no `listora-grid--list`; Grid radio `aria-checked="true"`, List `"false"` (verified server-side); state `defaultViewMode` "grid".

### 4. Remembered list on a default-grid directory
- **Action**: click "List view", reload.
- **Expect**: list, key `list`, zero console errors.

### 5. Role ladder
- Pure presentation state held in the visitor's browser: anonymous, member, owner, editor and admin behave identically; there is no per-account setting (single-role by design). Admin is the control for the page create/delete.

### 6. 390px
- **Expect**: toggle visible, 40px tap targets, no horizontal scroll. Not run.

## Teardown

Clear the key; `wp post delete <temp page id> --force`.

## Fail diagnostics

| Symptom | File |
|---|---|
| List default flips to grid on hydration | `store.js:441-449` `isListView` getter, `blocks/listing-grid/render.php:144` seed |
| Choice not remembered | `store.js:335` `VIEW_MODE_KEY`, `actions.setViewMode` (toolbar.php:58,80) |
| Stale build | `build/interactivity/store.js` and `build/blocks/*/view.js` must be rebuilt |
