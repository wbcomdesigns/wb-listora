---
journey: grid-default-view-and-remembered-choice
plugin: wb-listora
priority: high
roles: [anonymous]
covers: [listing-grid, interactivity-store, card-10294600329]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "Directory page with a listing-grid block at its default (grid) view: /listings/"
  - "Permission to create and delete a temporary page"
estimated_runtime_minutes: 4
---

# The grid honours the block's Default View AND the visitor's remembered choice

Two bugs on one control. The grid/list choice was forgotten on reload, and the block's **Default
View** setting never applied: it reached the client only through `callbacks.onGridInit`, which no
`data-wp-init` directive ever called. A grid set to List painted `listora-grid--list` on the server,
then the `isListView` getter read an empty `viewMode` and hydration flipped it to grid.

The first fix added persistence (localStorage `listora_view_mode`) and edited the dead callback, so
Default View stayed broken. Now `render.php` seeds `defaultViewMode` into `listora/directory` state
and the getters read `viewMode || defaultViewMode`. The dead callback is gone.

**Build note:** the store bundles into `build/interactivity/store.js` and every `build/blocks/*/view.js`.

## Setup

```bash
wp post create --post_type=page --post_status=publish --post_name=qa-tmp-list-default \
  --post_title="QA tmp list default" \
  --post_content='<!-- wp:listora/listing-grid {"defaultView":"list","perPage":6} /-->' --porcelain
```

For each step read, after a ~1s hydration wait:
`document.querySelector('.listora-grid__results').classList.contains('listora-grid--list')`,
the List button's `aria-checked`, and `localStorage.getItem('listora_view_mode')`.

## Steps

### 1. Default View = List, no visitor choice
- **Action**: clear the key, load `$SITE_URL/qa-tmp-list-default/`
- **Expect**: list class present AFTER hydration, List `aria-checked="true"`, key null (this is the bounce)

### 2. Visitor choice outranks Default View
- **Action**: click Grid view, reload
- **Expect**: grid, key `grid`

### 3. Default grid directory, no choice
- **Action**: clear the key, load `$SITE_URL/listings/`
- **Expect**: grid, List `aria-checked="false"`

### 4. Remembered list on a default-grid directory
- **Action**: click List view, reload
- **Expect**: list, key `list`; zero console errors

## Teardown
Clear the key; `wp post delete <temp page id> --force`.
