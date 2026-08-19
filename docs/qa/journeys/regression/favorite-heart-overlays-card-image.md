---
journey: favorite-heart-overlays-card-image
plugin: wb-listora
roles: [member]
priority: high
covers: [listora-card__favorite, cascade-order, all-unset-reset, dashboard-favorites, BC-10195604615]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "A member with at least one favorited listing"
estimated_runtime_minutes: 4
---

# The favorite heart overlays the card image, on every surface

On the dashboard Favorites tab the heart sat **below** the image in normal flow, made the card
taller than its neighbours, and could not be clicked — the click landed on the card's own anchor and
navigated to the listing instead. The same card on the directory behaved perfectly.

**It was never a missing stylesheet.** The rule is present on both pages. The button carries two
classes, and two rules of **equal specificity** (0,1,0) fight over `position`:

| Rule | Where | Declares |
|---|---|---|
| `.listora-favorite-btn` | `assets/css/listora-base.css` | `all: unset` — which includes `position` |
| `.listora-card__favorite` | `blocks/listing-card/style.css` | `position: absolute` |

At equal specificity the winner is whichever sheet the page loads **last**, and that differs per
page: the directory *links* the block stylesheet after `listora-base.css`, so the heart positioned
correctly; the dashboard *inlines* it before, so `all: unset` won and the heart fell out of the
corner.

That is why "works here, broken there" was the shape of the report, and why enqueueing something
would not have fixed it.

The fix scopes the positioning rule to `.listora-card__media`, making it (0,2,0) — so the cascade
decides on specificity rather than on sheet order, and it holds on any surface that renders a card,
including ones that do not exist yet.

> A component reset that includes `all: unset` will keep colliding with component rules at equal
> specificity. Any new rule that positions a `.listora-favorite-btn` must out-specify it rather than
> assume load order.

## Steps

Run **on both** `/listings/` (directory) and the dashboard **Favorites** tab.

### 1 — It overlays, it does not stack

```js
const h  = document.querySelector( '.listora-card__favorite' );
const m  = h.closest( '.listora-card__media' );
const r  = h.getBoundingClientRect(), mr = m.getBoundingClientRect();
getComputedStyle( h ).position === 'absolute'          // not 'static'
r.top >= mr.top && r.bottom <= mr.bottom               // inside the image box
```

`position: static` is the regression. Assert the **computed** value — the rule being present in the
stylesheet proves nothing here, since that was true while the bug was live.

### 2 — It is the topmost thing at its own centre

```js
const at = document.elementFromPoint( r.x + r.width / 2, r.y + r.height / 2 );
h.contains( at ) || at === h                            // true
```

If this returns the card anchor, clicks pass through to the link — the user-visible half of the bug.
Scroll the element into view first; `elementFromPoint` returns null off-viewport.

### 3 — Clicking toggles, and does not navigate

Click the heart on the dashboard Favorites tab.

- **Expect** `is-favorited` flips and the URL does **not** change.
- Navigating to the listing is the regression.

### 4 — 390px

Repeat 1-3 at a 390px viewport. The heart must stay inside the image box and keep its 44px tap
target; the body must not scroll horizontally.

## Cleanup

Re-favorite anything unfavorited during the run — check the row count in
`{prefix}listora_favorites` against the baseline, because a toggle test that lands on a different
card after the list re-renders will leave a listing favorited that was not before.
