# G7 Foundation — Audit

**Audited:** `assets/css/shared.css` (1,153 lines) + `assets/css/shared-rtl.css` (1,033 lines) + `src/shared/` (7 components + 2 hooks + 2 utils + base.css + theme-isolation.css) + per-block token adoption across 11 Free + 5 Pro blocks.

**Headline:** The foundation has THREE competing token systems coexisting in the same `:root` block. Each system was introduced cleanly but never finished — blocks adopt one and ignore the others. Net effect: a 1,153-line shared.css that looks comprehensive but operates with ~60% dead vocabulary, plus 233 hardcoded hex literals + 132 raw `px` values that should be tokenized.

---

## Issues — ranked by severity

### F-01 (BLOCK) — Three competing spacing/typography token systems

**Spacing** — two scales defined, only one adopted:

| Scale | Defined at | Token count | Block uses | Verdict |
|---|---|---|---|---|
| `--listora-gap-{xs,sm,md,lg,xl,2xl,3xl}` (named) | `shared.css:100-106` | 7 | **249** | adopted ✓ |
| `--listora-space-{1,2,3,4,5,6,8,10,12}` (numeric) | `shared.css:136-144` | 9 (with gaps — no 7/9/11) | **1** (single use in listing-detail:1178) | **dead** |

**Typography (size)** — two scales defined, only one adopted:

| Scale | Defined at | Token count | Block uses | Verdict |
|---|---|---|---|---|
| `--listora-text-{xs,sm,base,lg,xl,2xl,3xl}` (named, size, **values=0.7…2rem**) | `shared.css:27-33` | 7 | **130** | adopted ✓ |
| `--listora-font-size-{xs,sm,base,lg,xl,2xl,3xl,4xl}` (named, **values=0.75…2.25rem**) | `shared.css:147-154` | 8 | **0 blocks** (only shared.css:961 itself) | **dead** |

**Note:** the two size scales have **different values at the same name** (e.g. `text-base = 0.9rem` vs `font-size-base = 1rem`). Anything using `--listora-font-size-*` renders ~10% larger than blocks using `--listora-text-*`. This is real visual inconsistency, not just naming.

**Colors** — duplicate aliases:

| Pair | Value | Where |
|---|---|---|
| `--listora-error` = `--listora-danger` | `#dc2626` | both lines 64 + 71 |
| `--listora-error-text` = `--listora-danger-text` | `#b91c1c` | both defined |
| `--listora-error-bg` (none) vs `--listora-danger-bg` | only danger has a bg | partial overlap |

**Recommendation:** Pick one of each, delete the other. Suggested:
- Keep `--listora-gap-*` (named, dominant). Delete `--listora-space-*` from shared.css.
- Keep `--listora-text-{xs..3xl}` (named, dominant). Delete `--listora-font-size-*`.
- Keep `--listora-danger*` (matches portfolio convention). Alias `--listora-error*` → `--listora-danger*` for back-compat, then deprecate over 1-2 releases.
- One-line CSS change in shared.css preceded by a search-replace in any non-block consumers.

### F-02 (BLOCK) — `--listora-text-*` namespace overloaded for color AND size

`--listora-text-*` is used for both text COLOR variants (text, text-secondary, text-muted, text-strong, text-faint, text-inverse) AND text SIZE variants (text-xs, text-sm, text-base, text-lg, text-xl, text-2xl, text-3xl). Same prefix, two different semantic axes. A reader can't tell from the name which axis a token belongs to without checking the value.

**Recommendation:** Rename size axis to `--listora-text-size-*` OR rename color axis to `--listora-fg-*` (foreground). The portfolio standard (per block-quality-standard.md) uses `--{P}-font-size-*` for sizing — but that namespace is already taken here by the abandoned alternate scale. Either:
- (a) Resurrect `--listora-font-size-*` as the canonical size, deprecate `--listora-text-{xs..3xl}` (130 use migrations).
- (b) Rename color axis to `--listora-fg-*` (the color "foreground" prefix), keep size as `--listora-text-{xs..3xl}` (cheaper — fewer migrations).

(a) aligns with portfolio standard. (b) is cheaper.

### F-03 (BLOCK) — Canonical card primitive `.listora-ui-card` is unused

`shared.css:990-1045` defines a comprehensive card primitive: `.listora-ui-card__head/body/foot/title/desc`. Across all 11 Free blocks + 5 Pro blocks: **0 usages**. Every block rolls its own card (`.listora-card`, `.listora-grid__card`, `.listora-categories__card`, `.listora-featured__card`, etc.).

**Recommendation:** Pick the path:
- (a) Adopt — refactor `listing-card` (63 usages of `.listora-card`) to use `.listora-ui-card` slots. Other blocks compose around it.
- (b) Retire — delete `.listora-ui-card` from shared.css. Document that each block owns its own card BEM root.

(a) is the right long-term choice but a 1-2 day refactor. (b) is a 5-min delete.

### F-04 (BLOCK) — Page shell adoption is inconsistent (3 of 11 blocks)

Per the 2026-05-08 same-family migration:

| Block | Has page shell? | Note |
|---|---|---|
| listing-detail | ✓ `.listora-page--single` | applied at render.php:228 |
| listing-submission | ✓ `.listora-page--booking` | applied |
| user-dashboard | ✗ (reverted) | revert documented at render.php:344 — was breaking grid layout |
| listing-grid | ✗ | grid is embedded in pages, doesn't own the page wrapper |
| listing-search | ✗ | search is embedded |
| listing-card | n/a — sub-component | not a page-owning block |
| listing-map | ✗ | map is embedded |
| listing-categories | ✗ | categories block doesn't wrap the page |
| listing-featured | ✗ | featured block is a section, not a page |
| listing-calendar | ✗ | calendar is a section |
| listing-reviews | ✗ | reviews is embedded in detail or standalone |

**The right answer is that page shells belong on the PAGE TEMPLATE, not the block** — most blocks are sections that compose into pages, and the customer's page (Directory / Add Listing / Dashboard / Listing Detail) owns the shell. Only listing-detail wraps its OWN page, which is why it carries the shell.

**Recommendation:** Either:
- (a) Make this explicit in the foundation doc — page shells are page-owners, not section blocks. Drop `.listora-page--list` from shared.css if no page renders it.
- (b) Add page-shell wrappers to the FSE templates (`templates/single-listora_listing.html`, etc.) AND keep them off section blocks.

(b) is the correct portfolio move. The shell vocabulary is currently half-applied.

### F-05 (ADVISORY) — 233 hardcoded hex literals across 16 blocks

| Worst offenders | hex count |
|---|---|
| user-dashboard | 45 |
| needs-grid (Pro) | 40 |
| post-need (Pro) | 27 |
| listing-submission | 24 |
| credit-purchase (Pro) | 22 |
| comparison (Pro) | 18 |
| moderator-queue (Pro) | 14 |
| listing-detail | 13 |
| listing-card | 10 |
| listing-map | 10 |

Most should map to existing tokens (`--listora-primary`, `--listora-surface-elevated`, `--listora-text`, etc.). Per-block migration is the right path — each block has 5-45 literals.

### F-06 (ADVISORY) — 132 hardcoded px values >2px across 11 Free blocks

Same pattern as F-05 but for spacing/typography. Should map to `--listora-gap-*` and `--listora-text-*` tokens (per F-01's recommended canonical names). user-dashboard + listing-submission worst again.

### F-07 (ADVISORY) — Inline `<script>` violation in `listing-detail/render.php`

Vanilla JS fallback for tab/gallery switching when IAPI unavailable. Documented as an intentional fallback in a comment. Per Part 8.0 (production-readiness checklist row F2): "No inline `<script>` in PHP unless OAuth callback exception." This is NOT an OAuth callback case.

**Options:**
- (a) Move to a tiny enqueued vanilla file that loads only when the block renders + `data-no-iapi` attr is present (~12 lines, cleaner).
- (b) Document this as a sanctioned exception in CLAUDE.md if the IAPI-down fallback is intentional architecture.
- (c) Trust the IAPI store (no fallback) — if IAPI fails, the page shows static tabs anyway (it's progressive enhancement).

Same pattern exists in Pro `blocks/comparison/render.php` (likely same fallback intent).

### F-08 (ADVISORY) — Empty-state primitive used in only 3 of 11 blocks

`.listora-card--empty` + `.listora-empty/__icon/__title/__desc/__actions` is the canonical empty state. Used by:

| Block | Uses canonical empty? |
|---|---|
| listing-grid (templates/grid.php) | ✓ |
| listing-reviews (templates/reviews.php) | ✓ |
| listing-categories (render.php) | ✓ |
| listing-featured | ✗ (no empty state shown) |
| listing-calendar | ✗ (no empty state at all) |
| user-dashboard tabs | ✗ (each tab has its own empty markup) |
| listing-search | ✗ (uses listing-grid's empty state indirectly) |
| listing-map | ✗ (no empty state — silent when zero markers) |

User-dashboard has the worst inconsistency: each of its 5 tab templates (tab-listings, tab-reviews, tab-claims, tab-credits, tab-profile) likely rolls its own empty state. Audit confirms in G4.

### F-09 (ADVISORY) — `src/shared/` is missing `design-tokens.css`

Per block-quality-standard.md Section 15:

```
src/shared/
├── design-tokens.css            ← --{P}-* CSS variables
├── base.css                     ← Block reset + responsive visibility
├── ...
```

WB Listora's `src/shared/` has `base.css` + `theme-isolation.css` but NO `design-tokens.css`. Tokens live in `assets/css/shared.css` instead. Both files load on the frontend so there's no functional gap, but the editor's `src/shared/` won't see tokens — editor previews of blocks render without the design tokens unless `assets/css/shared.css` is also enqueued in editor context.

**Recommendation:** Move the `:root` token block from `assets/css/shared.css` into `src/shared/design-tokens.css`, then have `shared.css` `@import` it. Editor context also imports `design-tokens.css` for accurate previews.

### F-10 (FUTURE) — Pro side has no `src/shared/` at all

Per Section 15 of block-quality-standard.md: "Every Wbcom plugin with Gutenberg blocks MUST have a `src/shared/` directory with identical architecture." Pro has 5 blocks but `src/shared/` doesn't exist.

This is the right choice ARCHITECTURALLY — Pro should consume Free's `src/shared/` via JS imports rather than duplicating it. But it's a divergence from the standard, which assumes every plugin owns its shared layer.

**Recommendation:** Either:
- (a) Update the standard to allow "consume from sibling plugin" pattern (cleaner for free+pro pairs).
- (b) Add an empty `src/shared/` to Pro that just re-exports from `../../wb-listora/src/shared/`.

### F-11 (FUTURE) — wb-listora not registered in the Section 0 Prefix Convention table

block-quality-standard.md Section 0 lists: wbcom-essential / wp-career-board / wpmediaverse. WB Listora is missing. The plugin uses `--listora-*` (not `--{first-letter-of-name}*` like other plugins). Should be formally registered:

```markdown
| wb-listora | `listora` | `--listora-*` | `.listora-{block}` | `listora` |
```

---

## What's GOOD

Worth calling out so the audit isn't all gaps:

- ✓ All 11 Free blocks ship with `uniqueId`, `paddingTablet/Mobile`, `hideOnTablet/Mobile` per the responsive standard (verified earlier in the session).
- ✓ Per-instance CSS scoping pattern exists via `class-block-css.php`.
- ✓ Theme-isolation rules exist (`src/shared/theme-isolation.css`).
- ✓ RTL parity (`shared-rtl.css` is 1,033 lines — 90% of LTR).
- ✓ 7 editor controls + 2 hooks + 2 utils all present in `src/shared/`.
- ✓ Lucide icons system inline (21 icons, `Lucide_Icons::render()`).
- ✓ `.listora-page--{single,booking}` shells properly applied where they belong.
- ✓ Empty state primitive correctly used on listing-grid (the highest-traffic empty state surface).
- ✓ Zero inline `<style>` in PHP (F1 rule clean).
- ✓ Color tokens reference `--wp--preset--color--*` (theme-color integration via theme.json).

---

## Summary table

| # | Severity | Title | Effort |
|---|---|---|---|
| F-01 | BLOCK | Delete dead `--listora-space-*` + `--listora-font-size-*` + `--listora-error-*` duplicate aliases | 30 min |
| F-02 | BLOCK | Resolve `--listora-text-*` overloaded namespace (color + size collision) | 2-4 h (130 migrations) |
| F-03 | BLOCK | Decide adopt-or-retire on `.listora-ui-card` | 5 min retire OR 1-2 days adopt |
| F-04 | BLOCK | Page shell ownership rule — section blocks don't carry shells; pages do | 1 h + FSE template updates |
| F-05 | ADVISORY | 233 hex literals → token references | 4-8 h |
| F-06 | ADVISORY | 132 raw `px` values → token references | 4-8 h |
| F-07 | ADVISORY | Inline `<script>` in listing-detail (+ Pro comparison) — move to enqueued file or sanction | 1 h |
| F-08 | ADVISORY | Use canonical empty-state in remaining 8 blocks | 2-3 h |
| F-09 | ADVISORY | Move tokens to `src/shared/design-tokens.css` for editor preview parity | 30 min |
| F-10 | FUTURE | Pro has no `src/shared/` (intentional but undocumented) | 15 min doc OR 1 h scaffold |
| F-11 | FUTURE | Register wb-listora in portfolio prefix table | 5 min |

**Total BLOCK-severity effort: ~half a day to a couple of days depending on F-03 decision.**

The 3 BLOCK items (F-01 dead tokens, F-02 namespace collision, F-04 page-shell ownership) are the foundation cleanup that everything else (G1-G6 audits) depends on. Recommend tackling these before drilling into per-group audits — otherwise the group audits will keep re-flagging the same root cause.
