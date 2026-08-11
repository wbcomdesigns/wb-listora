---
journey: favorite-count-updates-without-reload
plugin: wb-listora
priority: high
roles: [member]
covers: [favorites, listing-detail, iapi, counter-vs-list]
prerequisites:
  - "A listing with >0 favorites AND a listing with exactly 0"
  - "A logged-in member who has NOT favorited either"
estimated_runtime_minutes: 5
---

# The count beside Save moves when Save is pressed

The heart filled, `aria-pressed` flipped, the row persisted - and the number
beside the button kept its server-rendered value until a full reload. The span
had no binding at all.

It was also only RENDERED when the count was above zero, so on a listing with
no favourites there was no element to update: the first favourite could never
show a count, whatever the binding did. Render the node and hide it at zero;
omitting it leaves nothing to reveal.

## Steps

### 1. The node exists and is bound
`.listora-detail__favorite-count` carries `data-wp-text="state.favoriteCountDisplay"`.
- **Fails if** the count is server-rendered text with no binding.

### 2. Non-zero listing: count follows the toggle
Read the count, click Save, wait for the request.
- Count increments by exactly 1, with no reload.
- `aria-pressed` flips to `true`.
Click again: both return to their original values.

### 3. Zero listing: the count appears and disappears
On a listing with 0 favourites the node EXISTS but is hidden.
- After Save: computed-visible, reading `1`.
- After un-Save: hidden again, reading `0`.
- **Fails if** the node is absent at zero - then the 0 to 1 case can never render.

### 4. The number matches the database
After each toggle, `SELECT COUNT(*) FROM favorites WHERE listing_id = %d`
equals the displayed figure.

### 5. Another member's toggle does not shift this viewer's base
The displayed figure is the server count adjusted by THIS viewer's change only
(`favoritedAtRender` carries whether they were already counted).
- **Fails if** a viewer who arrived already-favourited sees the count jump by 2
  on un-favourite, or the count drifts on repeated toggling.

## Test-data trap

Testing only on a listing the viewer had already favourited hides a sign error:
base and delta cancel. Cover BOTH arrival states - already-favourited and not.
