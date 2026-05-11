# WB Listora Frontend — Target Architecture v2

**Status:** PLAN (no code yet). Replaces the patch-by-patch approach surfaced in `plan/frontend-ux-audit/`.

**Goal:** A frontend layer where a new contributor can find their bearings in 30 minutes, every block composes from a small shared vocabulary instead of inventing its own, and the next 14 customer-visible features ship without re-introducing the disorganization the audit surfaced.

---

## Guiding principles (these resolve every micro-decision)

### P1 — One source of truth for any visual property
Every spacing value, color, radius, shadow, font-size lives in EXACTLY ONE token file. Blocks reference the token. No block defines its own `:root` block. No block hardcodes a hex value.

### P2 — Primitives compose; blocks orchestrate
A block doesn't define what a card looks like — it uses `.listora-card`. A block doesn't define what an empty state looks like — it uses `.listora-empty`. Blocks own DATA + IAPI WIRING; primitives own VISUAL VOCABULARY.

### P3 — Directory shape mirrors customer mental model
Blocks live under the functionality group a customer cares about (`discovery/`, `listing/`, `submission/`, `member/`, `reviews/`). NOT a flat `blocks/` directory where listing-card and user-dashboard sit at the same level despite serving totally different jobs.

### P4 — Pro mirrors Free's shape exactly
The same `src/` structure exists in Pro. Pro blocks live under the same group dirs (`src/blocks/discovery/comparison/`, `src/blocks/needs/post/`). A contributor working on Pro doesn't have to re-learn the layout.

### P5 — Theme overrides survive the restructure
The `wb_listora_get_template()` lookup pattern continues to work. Theme override paths are versioned (v1 paths kept for 1 release, v2 paths active in parallel, v1 removed in subsequent release).

### P6 — Every refactor step is reversible until merged
No "big-bang" migrations. Old layer stays alive in parallel with new layer until each block individually migrates. Git history shows one block per commit. Smoke + journey runs validate each migration before the next starts.

---

## Target directory shape

```
wb-listora/
├── src/
│   ├── tokens/                          ← NEW. Single source of truth for ALL design tokens.
│   │   ├── colors.css                   ← --listora-primary, semantic colors, status colors
│   │   ├── spacing.css                  ← --listora-space-{1..12} (numeric, canonical)
│   │   ├── typography.css               ← --listora-text-size-* AND --listora-fg-* (separated)
│   │   ├── radius.css                   ← --listora-radius-{sm,md,lg,xl,full}
│   │   ├── shadow.css                   ← --listora-shadow-{xs,sm,md,lg,xl}
│   │   ├── motion.css                   ← --listora-transition-{fast,base,slow}, ease curves
│   │   └── index.css                    ← @import all
│   │
│   ├── primitives/                      ← NEW. Canonical UI vocabulary every block uses.
│   │   ├── page-shell.css               ← .listora-page--{single,list,dashboard,booking}
│   │   ├── card.css                     ← .listora-card + __head/body/foot + variants
│   │   ├── empty-state.css              ← .listora-empty + slots
│   │   ├── form-field.css               ← .listora-form-field + states
│   │   ├── button.css                   ← .listora-btn + variants
│   │   ├── badge.css                    ← .listora-badge + semantic variants
│   │   ├── modal.css                    ← .listora-modal + a11y wiring
│   │   ├── tabs.css                     ← .listora-tabs + role="tablist" pattern
│   │   ├── stepper.css                  ← .listora-stepper (used by wizard + need flow)
│   │   ├── tooltip.css                  ← .listora-tooltip
│   │   ├── table.css                    ← .listora-table (transactions, claims, reviews list)
│   │   └── index.css
│   │
│   ├── editor/                          ← was src/shared/components — Gutenberg-side
│   │   ├── controls/                    ← 7 editor controls (unchanged from today)
│   │   ├── hooks/                       ← useUniqueId, useResponsiveValue
│   │   └── utils/                       ← attributes.js, css.js (per-instance CSS gen)
│   │
│   ├── interactivity/                   ← shared IAPI store (unchanged from today)
│   │   └── store.js
│   │
│   ├── blocks/                          ← organized by FUNCTIONALITY GROUP
│   │   ├── discovery/                   ← G1 — find a listing
│   │   │   ├── search/
│   │   │   ├── grid/
│   │   │   ├── card/
│   │   │   ├── map/
│   │   │   ├── categories/
│   │   │   ├── featured/
│   │   │   └── calendar/
│   │   ├── listing/                     ← G2 — read a listing
│   │   │   └── detail/
│   │   │       ├── block.json
│   │   │       ├── render.php (orchestration ~250 lines)
│   │   │       ├── style.css (block-specific only — page-shell + cards from primitives)
│   │   │       ├── view.js
│   │   │       └── parts/
│   │   │           ├── gallery.php
│   │   │           ├── sidebar.php
│   │   │           ├── tabs/
│   │   │           │   ├── tab-overview.php
│   │   │           │   ├── tab-reviews.php
│   │   │           │   ├── tab-hours.php
│   │   │           │   ├── tab-services.php
│   │   │           │   └── tab-map.php
│   │   │           └── modals/
│   │   │               ├── claim.php
│   │   │               ├── share.php
│   │   │               └── login.php
│   │   ├── submission/                  ← G3 — create a listing
│   │   │   └── wizard/
│   │   │       ├── block.json
│   │   │       ├── render.php
│   │   │       ├── style.css
│   │   │       ├── view.js
│   │   │       └── parts/
│   │   │           ├── stepper.php
│   │   │           ├── navigation.php
│   │   │           ├── steps/
│   │   │           │   ├── step-type.php
│   │   │           │   ├── step-basic.php
│   │   │           │   ├── step-details.php
│   │   │           │   ├── step-media.php
│   │   │           │   ├── step-duplicate-review.php
│   │   │           │   └── step-preview.php
│   │   │           └── _field-display.php   ← shared between step-* AND step-preview
│   │   ├── member/                      ← G4 — manage own listings
│   │   │   └── dashboard/
│   │   │       ├── block.json
│   │   │       ├── render.php (~250 lines orchestration)
│   │   │       ├── style.css (only dashboard-specific shell, not per-tab)
│   │   │       ├── view.js
│   │   │       └── parts/
│   │   │           ├── nav.php
│   │   │           └── tabs/
│   │   │               ├── tab-listings/
│   │   │               │   ├── tab.php (wrapper)
│   │   │               │   ├── row.php (single listing row)
│   │   │               │   ├── actions.php (bulk action toolbar)
│   │   │               │   └── style.css
│   │   │               ├── tab-reviews/
│   │   │               │   ├── tab.php
│   │   │               │   └── style.css
│   │   │               ├── tab-claims/
│   │   │               ├── tab-credits/
│   │   │               │   ├── tab.php
│   │   │               │   ├── balance.php
│   │   │               │   ├── history.php
│   │   │               │   ├── buy.php
│   │   │               │   └── style.css
│   │   │               ├── tab-favorites/
│   │   │               ├── tab-profile/
│   │   │               └── tab-settings/
│   │   └── reviews/                     ← G5 — write + read reviews
│   │       └── list/                    ← the canonical reviews surface
│   │           ├── block.json (registers listora/listing-reviews)
│   │           ├── render.php
│   │           ├── style.css
│   │           ├── view.js
│   │           └── parts/
│   │               ├── review-card.php  ← SHARED with listing-detail's Reviews tab via include
│   │               ├── review-form.php  ← ALSO included by listing-detail's Reviews tab
│   │               └── reviews-list.php
│   │
│   └── emails/                          ← G8 — separate visual system (unchanged for now)
│       └── (existing templates/emails/ moves here OR stays — TBD)
│
├── blocks/                              ← COMPILED OUTPUT of src/blocks/ (was the source dir)
│   └── (mirrors src/blocks/ shape after build)
│
├── includes/                            ← unchanged
├── audit/                               ← unchanged
└── docs/qa/                             ← unchanged
```

### Pro mirror

```
wb-listora-pro/
└── src/
    └── blocks/
        ├── discovery/                   ← Pro extensions of Discovery (none currently)
        ├── listing/                     ← Pro extensions of Listing detail (lead-form sidebar?)
        ├── comparison/                  ← Pro-only block category
        │   └── table/
        ├── credits/                     ← Pro-only block category
        │   └── purchase/
        ├── needs/                       ← Pro-only block category
        │   ├── post/
        │   ├── browse/                  (was needs-grid)
        │   └── detail/
        ├── moderator/                   ← Pro-only block category
        │   └── queue/
        └── reviews/                     ← Pro extensions of Reviews (multi-criteria editor, photo)
            └── (filter-based extensions, no new blocks)
```

---

## What stays the same

To bound the refactor, these don't change:

- ✅ Block names + REST paths + IAPI store namespace (`listora/directory`)
- ✅ Theme template override system (`wb_listora_get_template()`)
- ✅ `assets/` for built CSS + JS (output dir unchanged)
- ✅ Email templates location (G8 — separate visual system)
- ✅ Editor controls (the 7 components in src/editor/controls — unchanged)
- ✅ IAPI store + view.js (mostly unchanged, may pick up new selectors for primitive classes)
- ✅ All REST controllers + handler classes in `includes/`
- ✅ `audit/manifest.json` shape (counts will change as files move; v2.1 schema unchanged)

## What changes

- 🔄 `src/blocks/` is restructured into functionality groups (mechanical move)
- 🔄 `src/shared/` is split into `src/tokens/` + `src/primitives/` + `src/editor/` (clearer naming)
- 🆕 `src/primitives/` is new (canonical UI vocabulary used by every block)
- ❌ `--listora-gap-*` tokens deleted (replaced by `--listora-space-{1..12}`)
- ❌ `--listora-font-size-*` tokens deleted (replaced by `--listora-text-size-*`)
- ❌ `--listora-error-*` tokens deleted (alias to `--listora-danger-*`)
- ❌ `--listora-text-*` color tokens renamed to `--listora-fg-*` (namespace split)
- ❌ `.listora-ui-card` deleted (every block adopts `.listora-card` from primitives)
- ❌ Inline `<script>` in listing-detail/render.php removed (extracted to enqueued file)
- ❌ Inline `<script>` in Pro comparison/render.php removed (extracted to enqueued file)

---

## Effort sizing

Refactor takes **3-4 weeks of focused work** spread across phases (see `04-migration-phases.md`). Compared to the audit's 8-14 day patch sequence, the refactor is bigger upfront but resets the floor — every future block ships clean by default rather than fighting against the system.

The next 14 customer-visible features would otherwise each re-introduce some of the same disorganization (each new admin tab adding its own empty markup, each new block hardcoding hex). After refactor, they inherit the system automatically.

---

## Read next

1. `01-token-system-v2.md` — the canonical token vocabulary (resolves all 3 token conflicts)
2. `02-primitive-layer-v2.md` — every primitive's API + variants + a11y wiring
3. `03-directory-structure.md` — the file move plan + theme override compatibility
4. `04-migration-phases.md` — the safe, reversible sequence
5. `05-decisions.md` — the 3 product decisions resolved
6. `99-refactor-summary.md` — the single-page approval doc

No code touched until 99 is approved.
