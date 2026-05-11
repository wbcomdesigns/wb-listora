# Directory Restructure Plan

**Mechanical file moves. Theme override paths preserved via backward-compatible lookup.**

---

## File move table — Free

### Blocks (`src/blocks/` reorganization)

```
src/blocks/listing-search/        →  src/blocks/discovery/search/
src/blocks/listing-grid/          →  src/blocks/discovery/grid/
src/blocks/listing-card/          →  src/blocks/discovery/card/
src/blocks/listing-map/           →  src/blocks/discovery/map/
src/blocks/listing-categories/    →  src/blocks/discovery/categories/
src/blocks/listing-featured/      →  src/blocks/discovery/featured/
src/blocks/listing-calendar/      →  src/blocks/discovery/calendar/

src/blocks/listing-detail/        →  src/blocks/listing/detail/
src/blocks/listing-submission/    →  src/blocks/submission/wizard/
src/blocks/user-dashboard/        →  src/blocks/member/dashboard/
src/blocks/listing-reviews/       →  src/blocks/reviews/list/
```

### Templates (`templates/blocks/`)

Mirror the new src/blocks/ shape:

```
templates/blocks/listing-search/      →  templates/blocks/discovery/search/
templates/blocks/listing-grid/        →  templates/blocks/discovery/grid/
templates/blocks/listing-card/        →  templates/blocks/discovery/card/
templates/blocks/listing-map/         →  templates/blocks/discovery/map/
templates/blocks/listing-categories/  →  templates/blocks/discovery/categories/
templates/blocks/listing-featured/    →  templates/blocks/discovery/featured/
templates/blocks/listing-calendar/    →  templates/blocks/discovery/calendar/

templates/blocks/listing-detail/      →  templates/blocks/listing/detail/
templates/blocks/listing-submission/  →  templates/blocks/submission/wizard/
templates/blocks/user-dashboard/      →  templates/blocks/member/dashboard/
templates/blocks/listing-reviews/     →  templates/blocks/reviews/list/
```

### Shared layer split

```
src/shared/components/             →  src/editor/controls/
src/shared/hooks/                  →  src/editor/hooks/
src/shared/utils/                  →  src/editor/utils/
src/shared/base.css                →  src/editor/base.css
src/shared/theme-isolation.css     →  src/editor/theme-isolation.css

assets/css/shared.css :root block  →  src/tokens/*.css  (split per concern)
assets/css/shared.css primitives   →  src/primitives/*.css  (split per concern)
assets/css/shared.css remaining    →  DELETE (everything was either tokens, primitives, or dead)
```

### New files (created during refactor)

```
src/tokens/colors.css          (new)
src/tokens/spacing.css         (new)
src/tokens/typography.css      (new)
src/tokens/radius.css          (new)
src/tokens/shadow.css          (new)
src/tokens/motion.css          (new)
src/tokens/index.css           (new)

src/primitives/page-shell.css  (extracted from shared.css)
src/primitives/card.css        (extracted from shared.css + listing-card style.css)
src/primitives/empty-state.css (extracted from shared.css)
src/primitives/form-field.css  (NEW — first time a canonical form primitive exists)
src/primitives/button.css      (NEW)
src/primitives/badge.css       (extracted from shared.css)
src/primitives/modal.css       (NEW — replaces 3 inline modal stylesheets)
src/primitives/tabs.css        (NEW)
src/primitives/stepper.css     (extracted from submission wizard)
src/primitives/tooltip.css     (NEW — small)
src/primitives/table.css       (NEW — for credit history, claims list, audit log)
src/primitives/index.css       (new)

includes/class-render-helpers.php  (new — wb_listora_render_empty_state, wb_listora_render_tabs)
```

---

## File move table — Pro

```
wb-listora-pro/blocks/comparison/         →  wb-listora-pro/src/blocks/comparison/table/
wb-listora-pro/blocks/credit-purchase/    →  wb-listora-pro/src/blocks/credits/purchase/
wb-listora-pro/blocks/moderator-queue/    →  wb-listora-pro/src/blocks/moderator/queue/
wb-listora-pro/blocks/needs-grid/         →  wb-listora-pro/src/blocks/needs/browse/
wb-listora-pro/blocks/post-need/          →  wb-listora-pro/src/blocks/needs/post/

wb-listora-pro/templates/blocks/needs-grid/      →  templates/blocks/needs/browse/
wb-listora-pro/templates/blocks/post-need/       →  templates/blocks/needs/post/
wb-listora-pro/templates/blocks/credit-purchase/ →  templates/blocks/credits/purchase/
wb-listora-pro/templates/blocks/need-detail/     →  templates/blocks/needs/detail/
```

Pro consumes Free's `src/tokens/` + `src/primitives/` + `src/editor/` directly — no Pro-side copies.

---

## Theme override backward compatibility

The plugin's `wb_listora_get_template()` lookup currently checks `{theme}/wb-listora/blocks/listing-card/card.php`. After the restructure, the new path is `{theme}/wb-listora/blocks/discovery/card/card.php`.

**Two-release transition:**

**v1.0.5 (refactor ships):**
- New paths active: `{theme}/wb-listora/blocks/discovery/card/card.php` ✓ checked first
- Old paths supported: `{theme}/wb-listora/blocks/listing-card/card.php` ✓ checked second (with `_doing_it_wrong` notice)
- Customers using theme overrides see a notice in their debug.log telling them to rename the override directory

**v1.0.6 (deprecation removes):**
- Only new paths checked
- Old paths ignored

This preserves theme override compatibility for one release cycle.

---

## block.json `name` field — UNCHANGED

The block name (`listora/listing-card`, etc.) is part of the customer-visible Gutenberg API. Renaming would break embedded blocks on customer pages. **The block name stays the same** — only the directory + file structure changes.

So:
- `src/blocks/discovery/card/block.json` still registers `"name": "listora/listing-card"`
- Gutenberg sees no change; customer pages continue to render

The directory rename is purely internal organization.

---

## Build pipeline impact

`@wordpress/scripts` reads `src/blocks/**/block.json` recursively and outputs to `build/blocks/{slug}/`. The current flat structure → group structure:

| Before (build output) | After (build output) |
|---|---|
| `build/blocks/listing-card/` | `build/blocks/listing-card/` (block slug determines output dir, not src path) |
| `build/blocks/user-dashboard/` | `build/blocks/user-dashboard/` |

So **build OUTPUT shape doesn't change** — `BlockRegistrar.php` still finds blocks in `build/blocks/{slug}/`. Only the `src/` layout reorganizes.

That keeps blocks/ flat (compiled output) but src/ organized (developer experience). Win-win.

---

## Net change

- 14 directories moved (under src/blocks/)
- 14 directories moved (under templates/blocks/)
- 5 directories moved (Pro src/blocks/)
- 4 directories moved (Pro templates/blocks/)
- 6 NEW files in src/tokens/
- 11 NEW files in src/primitives/
- 1 NEW file in includes/ (class-render-helpers.php)
- shared.css :root block DELETED (split into src/tokens/)
- shared.css primitives section DELETED (split into src/primitives/)

Customer-visible: ZERO (block names + REST paths + IAPI namespace all preserved). Visual: improvements only (primitives ensure consistency that was missing).
