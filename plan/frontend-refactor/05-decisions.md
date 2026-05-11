# Architectural Decisions

The 3 product decisions flagged in `frontend-ux-audit/99-summary.md`, resolved with rationale. These bake into the v2 architecture and are NOT revisited during refactor execution.

---

## Decision 1 — `.listora-ui-card` adopted; `.listora-card` becomes the canonical card

**Question:** Adopt the dead `.listora-ui-card` primitive (0 current uses) OR retire it and bless `.listora-card` (currently used heavily in listing-card block) as canonical?

**Decision: ADOPT — but as `.listora-card` (the existing widely-used class), not `.listora-ui-card`.**

The `.listora-ui-card` class name was a naming experiment that never landed. `.listora-card` already has the most adoption (63 usages in listing-card alone), the most intuitive name (no UI prefix), and the most mental model with the customer (it IS a card). The right move is to:

- Promote `.listora-card` to the canonical primitive in `src/primitives/card.css`
- Delete `.listora-ui-card` (and its `__head/__body/__foot` slot system) from shared.css
- Other blocks (listing-grid, listing-categories, etc.) refactor to use `.listora-card` directly instead of inventing their own (`.listora-grid__card`, `.listora-categories__card`).

Migration cost: ~30 minutes to delete `.listora-ui-card`. ~1-2 days to convert the 5-6 blocks that have their own card class to use `.listora-card`. Both fold into Phase 2.

---

## Decision 2 — Split `--listora-text-*` namespace; color axis becomes `--listora-fg-*`

**Question:** Resolve the overloaded `--listora-text-*` namespace (used for both color AND size). Three options:
- (a) Keep overloaded — accept the ambiguity.
- (b) Rename color axis to `--listora-fg-*` (foreground).
- (c) Rename size axis to `--listora-text-size-*`.

**Decision: BOTH (b) AND (c). Color goes to `--listora-fg-*`, size goes to `--listora-text-size-*`. The legacy `--listora-text-*` namespace is fully retired.**

The audit suggested (b) alone (cheaper, 30-50 color migrations). On reflection, doing only the color rename leaves `--listora-text-{xs..3xl}` still ambiguous — the reader sees `--listora-text-sm` and has to remember that's a SIZE (not a "small text" semantic color). The full split is clearer in 6 months.

Total migrations: ~130 size + ~40 color = ~170 mechanical search-replaces. Done as part of Phase 2 block-by-block, so each block touches ~10-15 tokens.

The renamed canonical names are:
- `--listora-text-{xs..3xl}` → `--listora-text-size-{xs..3xl}` (size)
- `--listora-text-secondary` → `--listora-fg-muted`
- `--listora-text-muted` → `--listora-fg-faint`
- `--listora-text-strong` → `--listora-fg-strong`
- `--listora-text-faint` → `--listora-fg-faint`
- `--listora-text-inverse` → `--listora-fg-inverse`
- `--listora-text` (default body color) → `--listora-fg-default`

This aligns with portfolio convention (`--{P}-fg-*` for foreground colors).

---

## Decision 3 — Dark mode: document "theme provides" for v1.0.5

**Question:** Should v1.0.5 ship dark-mode tokens in `shared.css` OR document that themes are responsible for providing dark variants?

**Decision: DOCUMENT for v1.0.5. Re-evaluate for v1.1 based on customer signals.**

Rationale:
1. WB Listora tokens already reference `--wp--preset--color--*` (theme.json color presets). Themes that ship dark variants in theme.json automatically provide dark mode to listings.
2. Shipping our own dark-mode token block means owning the entire dark palette + maintaining consistency with whatever theme the customer chose. That's a maintenance commitment.
3. Pro side has orphan dark tokens in `shared.css:165-167` (`--listora-card-bg: #1e1e1e`, etc.) that don't fire anywhere. Those get deleted in Phase 4 cleanup.
4. The right path for v1.1: add a `--listora-color-scheme: light` token that customers can override to `dark` via a single setting → triggers a `@media (prefers-color-scheme: dark)` token override block in shared.css. But shipping that needs:
   - A demo dark theme to verify against.
   - Customer signal that "directory looks bad on my dark site" is a real complaint.
   - Time for the WordPress block-theme ecosystem to standardize how dark mode works at the theme.json level.

For v1.0.5, document in CLAUDE.md: "WB Listora colors inherit from theme.json color presets. Themes that provide dark variants (via theme.json's `appearanceTools` or CSS custom properties) get dark mode automatically."

Effort: 5 minutes documentation. Re-open the question in v1.1 release planning.

---

## Resolved out-of-band decisions

These came up implicitly in the audit + plan and are resolved here for the record:

### Decision A — Inline `<script>` extraction policy

All inline `<script>` blocks in PHP move to enqueued files. The two current violations (listing-detail render.php fallback JS + Pro comparison floating-bar JS) become:
- `assets/js/listora-detail-fallback.js` (register-only, enqueue conditionally)
- `wb-listora-pro/assets/js/comparison-bar.js` (auto-enqueued when comparison block renders)

No inline `<script>` ships in v1.0.5 production code. This is a Part 8.0 production-rule alignment.

### Decision B — FSE template vs legacy classic template (G2-07)

Keep BOTH for v1.0.5 (FSE `single-listora_listing.html` for block themes, classic `single-listora_listing.php` for non-FSE themes). Document the dual-template policy in CLAUDE.md.

Plan v1.1 to deprecate the classic template (warn in admin if used; remove in v1.2).

### Decision C — Pro `src/shared/` policy

Pro doesn't get a `src/shared/` of its own. It imports from `wb-listora/src/{tokens,primitives,editor}/` at build time. The build pipeline knows to look across plugin boundary.

Document this pattern in `wp-plugin-development` skill (one paragraph) so other free+pro pairs adopt the same convention.

### Decision D — Block name vs directory name decoupled

Block names (`listora/listing-card`) stay flat — they're the customer-visible Gutenberg API. Directory names go group-organized (`src/blocks/discovery/card/`). This is the architecturally right way to organize since the directory shape is for developers; block names are for customers.

The link between them is `block.json` — each block's `name` field declares the public name regardless of where the directory lives.

### Decision E — Theme override path migration

Two-release transition. v1.0.5 ships both lookup paths active. v1.0.6 removes the old paths (with a release-note advance warning).

---

## Decisions NOT made in this refactor

These remain open and don't block Phase 0-4 execution:

- **Dark mode v1.1 implementation** (Decision 3 — deferred).
- **Whether email templates (G8) get migrated to the primitive layer.** Currently emails use inline-style HTML rules that diverge from web HTML — different visual system. Keep separate for now; revisit in a future email-template-overhaul project.
- **Whether to introduce a JavaScript component library** (similar to React component patterns). Current src/blocks/* view.js files are reasonably small. Wait for blocks to grow before adding component abstraction.
- **Whether Pro should mirror the same group-based directory shape**. Today Pro has its own block categories (comparison, credits, needs, moderator) which don't map 1:1 to Free's Discovery/Listing/Submission/Member groups. Decision: Pro keeps its OWN group names (comparison, credits, needs, moderator) as top-level dirs under `src/blocks/`. These are Pro-only concerns and don't belong nested under a Free group.
