# G4 Member Account — Audit

**Audited:** user-dashboard block (render.php **806** + style.css **1,641**) + 6 tab templates (1,186 lines) + nav.php (113).

**Total surface: 3,746 lines. The LARGEST single surface in the plugin.**

**Headline:** This is where the UX organization issue is most acute. user-dashboard ranks **worst-in-plugin** on hex literals (45), worst-in-plugin on px values (25), and **ZERO** canonical empty state usage across all 5 tab templates. Each tab rolls its own empty markup or has none.

---

## Tab-by-tab inventory

| Template | Lines | BEM `__` | Empty state? | Notes |
|---|---|---|---|---|
| nav.php | 113 | 7 | n/a | sidebar nav with 8 tabs |
| tab-listings.php | 412 | 53 | ✗ | largest tab; lists owned listings with bulk actions, edit, renew, deactivate, delete |
| tab-credits.php | 303 | 41 | ✗ | credit balance + history + buy CTA |
| tab-reviews.php | 149 | 14 | ✗ | reviews ON owned listings, inline reply |
| tab-claims.php | 123 | 16 | ✗ | claims submitted BY this user |
| tab-profile.php | 86 | 8 | ✗ | profile fields |

**Tabs NOT in templates (rendered inline in render.php):**
- Favorites tab (~50 lines inline)
- My Needs tab (Pro extension, ~80 lines)
- Analytics tab (Pro extension, ~120 lines)
- Settings / Notifications tab (small)

---

## Issues

### G4-01 (BLOCK) — 0 of 5 tab templates use the canonical empty-state primitive

Every tab can be empty: a new member has no listings, no reviews, no favorites, no claims, no credits. Yet zero tabs use `.listora-card--empty .listora-empty`. Each tab template either:
- (a) shows nothing when empty (silent → confusing UX), OR
- (b) inlines its own empty markup with custom class names

This is THE most acute symptom of "things are not organized well." A logged-in user with a fresh account opens 5 tabs and sees 5 different empty-state experiences.

**Recommendation:** add `<?php wb_listora_render_empty_state( $config ); ?>` helper that emits the canonical primitive. Then each tab template calls it with appropriate $config (icon, title, desc, CTA). 5 tabs × ~5 line update = ~25-line PR.

### G4-02 (BLOCK) — tab-listings.php at 412 lines + 53 BEM elements is doing too much

The biggest single template in the plugin. Renders: a row per listing with thumbnail + title + status badge + meta (created/expires) + row actions (View / Edit / Renew / Deactivate / Trash) + bulk action toolbar at top. The repeating row markup is ~80 lines × 1 per listing.

**Recommendation:** extract:
- `tab-listings-row.php` — single listing row (~80 lines)
- `tab-listings-actions.php` — bulk action toolbar (~40 lines)
- `tab-listings.php` becomes the wrapper that loops + includes the row (~80 lines)

Same pattern as listing-card → card-image + card-content + card-actions in G1.

### G4-03 (BLOCK) — tab-credits.php at 303 lines + 41 BEM elements

Second-largest tab. Renders: current balance card + transaction history table + Buy Credits CTA + (Pro) direct-pack purchase form. The table + form together hit 200+ lines.

**Recommendation:** extract:
- `tab-credits-balance.php` — top balance card (~50 lines)
- `tab-credits-history.php` — transaction table (~120 lines)
- `tab-credits-buy.php` — Buy CTA + (Pro) form (~80 lines)
- `tab-credits.php` becomes the wrapper

### G4-04 (BLOCK) — user-dashboard render.php at 806 lines

Largest render.php in the plugin. Why so large? Multiple inline tab renderings (Favorites + Settings) PLUS data prep for all 5+ tabs PLUS IAPI state hydration.

**Recommendation:**
- Move Favorites + Settings to template files (consistent with the 5 other tabs).
- Extract per-tab data-prep into `Dashboard_Data::for_tab($tab_key, $user_id)`.
- render.php becomes orchestration only: parse `$tab`, fetch data, include template. ~250 lines.

### G4-05 (BLOCK) — Worst-in-plugin token gap: 45 hex literals + 25 distinct px values

By far the highest in any block. Mostly status badges, owner-action buttons, transaction status indicators, profile avatar styling. Most should map to existing `--listora-{success,warning,danger,info}*` and `--listora-{space,text}-*` tokens.

### G4-06 (BLOCK) — style.css at 1,641 lines is bigger than shared.css's foundation chunk

For comparison: the entire `:root` token block + page shells + card primitives + empty states in shared.css = ~250 lines. user-dashboard style.css alone is 1,641 lines. That's 6.5x larger than the foundation.

**Recommendation:** split user-dashboard/style.css to match the tab template split (G4-02/03):
- `nav.css`
- `tab-listings.css`
- `tab-credits.css`
- `tab-reviews.css`
- `tab-claims.css`
- `tab-profile.css`
- `tab-favorites.css`
- `dashboard-shared.css` (shared dashboard primitives like the row hover state, status badges)

Then user-dashboard/style.css `@import`s the relevant pieces. Conditional loading by active tab (the inactive tabs don't need their CSS until tab-switch).

### G4-07 (ADVISORY) — Pro tabs (My Needs, Analytics) live in different files

Pro renders its own tabs via filter on `wb_listora_dashboard_sections`. The Pro tab markup lives in `wb-listora-pro/templates/dashboard/*` (not under Free's templates/blocks/user-dashboard). Visually, the customer can't tell — but template-override authors must remember to override in BOTH plugins.

**Recommendation:** Pro's dashboard tabs should follow the same naming convention (`tab-needs.php`, `tab-analytics.php`) and Free should document the tab-template contract in CLAUDE.md so Pro authors stay aligned.

### G4-08 (ADVISORY) — No tab-level loading state

When a customer switches tabs, the new tab's content fetches via REST (each tab has its own data). There's no skeleton/loading indicator — the previous tab's content stays until new content arrives. Looks like nothing happened for 200-500ms.

**Recommendation:** add `.listora-dashboard__skeleton` per tab. Show on REST request start, hide on success. Cheap visual fix that materially improves perceived performance.

---

## Summary

| # | Severity | Title | Effort |
|---|---|---|---|
| G4-01 | BLOCK | Canonical empty-state in all 5 (+ 3 Pro) tabs | 2-3 h |
| G4-02 | BLOCK | Split tab-listings.php (412 → 80+80+80+120) | 3-4 h |
| G4-03 | BLOCK | Split tab-credits.php (303 → 50+120+80) | 2-3 h |
| G4-04 | BLOCK | Refactor user-dashboard render.php (806 → ~250) | 4-6 h |
| G4-05 | BLOCK | 45 hex literals + 25 px values → tokens | 4-6 h |
| G4-06 | BLOCK | Split user-dashboard/style.css per-tab | 4-6 h |
| G4-07 | ADVISORY | Pro tab-template naming alignment | 1-2 h doc + Pro changes |
| G4-08 | ADVISORY | Loading skeleton per tab | 2-3 h |

**Total G4 effort: 2-3 days for the BLOCK items.** This is the largest single cleanup target in the plugin and where the user's "things are not organized well" frustration would most concretely improve.
