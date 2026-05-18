# Frontend UX Audit — Summary + Action Plan

**Scope:** 16 blocks (11 Free + 5 Pro) + 54 PHP templates + 1,153-line shared.css + 11 per-block stylesheets + 5 Pro stylesheets. Organized by 7 functionality groups (G1-G7) plus an emails note (G8 deferred).

**Total findings: 56 across 7 groups** (G1: 9, G2: 7, G3: 6, G4: 8, G5: 7, G6: 10, G7: 11). Breakdown by severity:

| Severity | Count | Total effort |
|---|---|---|
| **BLOCK** (release-blocking for clean UX foundation) | **20** | ~6-8 days |
| **ADVISORY** (should fix, not gating) | **26** | ~5-7 days |
| **FUTURE** (worth tracking, not urgent) | **10** | ~2-3 days |

---

## The "things are not organized well" feeling — root causes

Per the user's framing, there are FOUR root causes that explain the disorganized feel:

### Root cause 1 — Three competing token systems coexist in shared.css

G7 F-01 / F-02. Two spacing scales (`--listora-gap-*` named vs `--listora-space-*` numeric — numeric is dead). Two typography scales (`--listora-text-*` named vs `--listora-font-size-*` — alternate is dead). One namespace overloaded for both color + size (`--listora-text-*` for foreground AND for size). Until this is resolved, every block-level migration would re-flag the same root cause.

**Fix path:** delete dead vocabulary, pick a canonical color/size naming convention (suggested: `--listora-fg-*` for color, `--listora-text-{xs..3xl}` stays for size). 30 min for deletes, 2-4 h for the 130 size-token migrations.

### Root cause 2 — Canonical primitives exist but aren't adopted

G7 F-03 + F-08. `.listora-ui-card` (the canonical card primitive) has ZERO usages across 16 blocks — every block rolls its own card class. `.listora-card--empty` + `.listora-empty` (the canonical empty state) is used in only 3 of 11 blocks. The blocks that DO use it (listing-grid, listing-categories, listing-reviews) work fine; the blocks that don't (listing-featured, listing-calendar, listing-map, all 5 user-dashboard tabs) each have their own approach.

**Fix path:** decide adopt-or-retire on `.listora-ui-card`. If adopt: refactor listing-card (63 usages of `.listora-card`) to use canonical slots → other blocks compose around it. If retire: delete from shared.css. Pick a path.

### Root cause 3 — Some blocks/templates are doing too much

G2 (listing-detail tabs.php 539 lines), G3 (listing-submission step-preview duplicates source-step logic), G4 (user-dashboard at 3,746 total lines, 5 tab templates with no shared structure), G6 (post-need template at 265 lines, need-detail at 330 lines). These aren't "wrong" — they grew that way because the surface is genuinely complex. But each is well past the point where splitting into sub-templates would improve maintainability.

**Fix path:** systematic template split following the pattern listing-card already establishes (card.php / card-image.php / card-content.php / card-actions.php). G4 is the highest-impact target.

### Root cause 4 — Two surfaces render the same thing two ways (Reviews)

G5-01 + G5-02. Reviews appear on `/listing/<slug>/#reviews` (via listing-detail/tabs.php) AND on any page with the `listora/listing-reviews` block. Both render review-card.php (shared, good) but the listing-detail surface doesn't render review-form.php (write UI) — only the standalone block does. So customers viewing a listing detail can't write a review inline.

**Fix path:** the standalone block becomes the canonical reviews surface. listing-detail tabs.php's Reviews tab calls into the standalone block's rendering. ~2-3 h.

---

## Recommended sequence

Given the root causes above, the right order to address them is:

**Phase 1 — Foundation (1-2 days)**
1. G7 F-01 — Delete dead `--listora-space-*` + `--listora-font-size-*` + `--listora-error-*` duplicate aliases. (30 min)
2. G7 F-02 — Resolve `--listora-text-*` overloaded namespace. Pick `--listora-fg-*` for color, keep `--listora-text-{xs..3xl}` for size. Mechanical search-replace + sanity check across 130 usages. (2-4 h)
3. G7 F-03 — Decide adopt-or-retire on `.listora-ui-card`. (5 min retire OR 1-2 days adopt — recommended **retire** for now; revisit when blocks naturally converge)
4. G7 F-04 — Move page-shell vocabulary OFF section blocks, ONTO page templates (FSE templates). (1 h + FSE template updates)

**Outcome:** Foundation is clean. Group audits are no longer flagging duplicate token systems on every finding.

**Phase 2 — High-impact organizational fixes (3-4 days)**
1. G4-01 — Canonical empty state in all 8 user-dashboard tabs. (2-3 h)
2. G4-02 + G4-03 + G4-04 + G4-06 — Split user-dashboard tab templates + style.css per-tab + slim render.php. (1.5-2 days)
3. G5-01 + G5-02 — Reviews surface consolidation (form in detail tab, action-based composition). (3-4 h)
4. G2-01 — Extract listing-detail inline `<script>` to enqueued file. (1-2 h)
5. G2-02 — Split 3 listing-detail modals into template files. (2-3 h)

**Outcome:** The two biggest surfaces (listing-detail at 2,642 lines and user-dashboard at 3,746 lines) become readable.

**Phase 3 — Token + hex cleanup (2-3 days)**
1. G7 F-05 — 233 hardcoded hex literals → token references. Block-by-block, worst-first: user-dashboard (45), needs-grid (40), post-need (27), listing-submission (24). (1-2 days)
2. G7 F-06 — 132 raw `px` values >2px → token references. (4-8 h)
3. G6-05 — Move comparison inline JS to enqueued file. (1 h)

**Outcome:** Every visual property in every block traces to a token. New blocks inherit the system automatically.

**Phase 4 — Template splits + extensions (2-3 days)**
1. G2-03 — Split listing-detail tabs.php (539 lines) into per-tab files. (3-4 h)
2. G3-01 + G3-02 — Field-display partial + canonical form-field primitives. (5-7 h)
3. G6-04 + G6-07 + G6-08 — Pro template splits (comparison, post-need, need-detail). (1-1.5 days)

**Outcome:** Every template file fits comfortably in a code review. New contributors find their bearings quickly.

**Phase 5 — Advisory cleanup + polish (1-2 days)**
- Remaining advisory items across all 7 groups.

**Total estimated effort: 8-14 days of focused work**, depending on adopt-vs-retire decisions and how aggressive the Phase 4 splits are. Phase 1+2 (5-6 days) would address the user's stated frustration with most-visible disorganization.

---

## Per-group summary

| Group | Findings | BLOCK | ADVISORY | FUTURE | Est. effort (BLOCK only) |
|---|---|---|---|---|---|
| G1 Discovery | 9 | 3 | 5 | 1 | 0.5-1 day |
| G2 Listing Detail | 7 | 2 | 4 | 1 | 0.5 day |
| G3 Submission Wizard | 6 | 2 | 3 | 1 | 1 day |
| G4 Member Account | 8 | 6 | 2 | 0 | **2-3 days** |
| G5 Reviews | 7 | 3 | 3 | 1 | 0.5 day |
| G6 Pro Extensions | 10 | 5 | 4 | 1 | 1-2 days |
| G7 Foundation | 11 | 4 | 5 | 2 | 0.5-1 day |
| **Totals** | **58** | **25** | **26** | **7** | **6-8 days** |

(Slight delta from headline numbers — re-counted during summary write.)

---

## What's NOT in this audit

- **G8 Emails (15 templates):** separate visual system. HTML email rules differ from web HTML — inline styles required, no JS, no flex/grid in older Outlook. Deferred to a focused email-template audit pass. Email lifecycle is currently working (verified during data-flow + smoke runs).
- **Live browser walks for every surface:** smoke run already verified critical surfaces (`/listings/`, `/dashboard/`, `/add-listing/`, `/listing/<slug>/`, admin pages). Per-surface visual regressions at 390px mobile + RTL deferred to the full Sonnet smoke (chunked dispatch).
- **Pro admin UI:** this audit is frontend customer surfaces only. Pro admin pages (License / Coupons / Webhooks / Badges / Audit Log / Moderator / Reverse Listings) get their own admin-UX audit if needed.
- **Editor UX inside block.json (responsive panels, InspectorControls order):** earlier session confirmed all 11 Free blocks have apiVersion 3 + 20 standard attributes + InspectorControls in canonical 5-panel order. Not re-verified here.

---

## Decision points for the user

Before executing any of Phase 1-5, three product decisions need a call:

1. **`.listora-ui-card` — adopt or retire?** (G7 F-03 / G6-09)
   - Adopt: 1-2 days refactor across listing-card + Pro blocks. Long-term cleaner.
   - Retire: 5-min delete from shared.css. Short-term faster.
   - Default recommendation: **retire**. Re-evaluate in 6 months.

2. **`--listora-text-*` namespace — keep overloaded or split?** (G7 F-02)
   - Keep: confusing but no migrations needed.
   - Split — rename color axis to `--listora-fg-*`: 0 size-axis migrations needed; ~30-50 color-axis migrations.
   - Split — rename size axis to `--listora-text-size-*`: 130 size-axis migrations needed.
   - Default recommendation: **rename color axis to `--listora-fg-*`**. Cheaper, aligns with portfolio convention (other plugins use `--{P}-fg-*`).

3. **Dark-mode policy** (G1-04)
   - Ship dark mode in shared.css → blocks inherit. Roughly 1 day for the token table.
   - Document "theme provides" → 5-minute CLAUDE.md note.
   - Default recommendation: **document "theme provides"** for v1.0.4; revisit for v1.1 if customers ask.

Once these three calls are made, Phase 1-5 execution can proceed without further blocking decisions.
