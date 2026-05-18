# Listora Admin CSS / HTML Cleanup Plan

**Owner rule (constitutional):** No inline CSS, no inline JS — ever. All CSS via enqueued stylesheets, all JS via enqueued scripts; server data via `wp_localize_script()`.

**Reference target:** Jetonomy admin token system + single-file `admin.css` structure. We adopt Jetonomy's *patterns*, not its inline-style debt.

---

## Phase 0 — Land the rules and the safety net

Goal: stop new debt from accumulating *while* we clean.

1. **Add PHPCS sniff** for inline `<style>`, inline `<script>` (without `application/ld+json`), and non-empty `style="…"` attributes in PHP/HTML. WordPress Coding Standards already ships `WordPress.WP.EnqueuedResources` — enable it as `error` in `phpcs.xml` (free + pro). Add a custom sniff or `Generic.Files.LineLength`-style allowlist for the JSON-LD exception.
2. **Update `~/.claude/skills/wp-plugin-development/references/admin-ux-rulebook.md`** — see `SKILL_GAP.md` for the patch. Add a top-level "No-Inline Rule" section near the top, with the three forbidden patterns and the three allowed alternatives.
3. **Add `wppqa_check_plugin_dev_rules` extension** (the existing MCP rule-checker) to flag inline `<style>`/`<script>` in PHP. Today it only flags inline `onclick=`.
4. **Save the constitutional feedback memory** — done in `feedback_no_inline_css_js.md`.

Acceptance: PHPCS run on a fresh branch fails with current code, listing every offending file. (We then fix them in Phase 1–3.)

---

## Phase 1 — Strip inline `<style>` blocks (highest-leverage, lowest-risk)

**8 blocks total**, total ~10 KB. Each block already targets its own class prefix, so cut-and-paste into the right enqueued stylesheet is mechanical.

| # | Source | Destination | Notes |
|---|--------|-------------|-------|
| 1 | `wb-listora/includes/admin/class-settings-page.php:1701` (`.listora-features-list` block) | `assets/css/admin/settings.css` (new section "Features tab") | DELETE the block — already used by next block |
| 2 | `wb-listora/includes/admin/class-settings-page.php:1777` (duplicate of #1) | move to `settings.css`, then delete inline | both blocks emitted on same page → 3 KB wasted |
| 3 | `wb-listora/includes/admin/class-settings-page.php:2138` (`.listora-notification-test__status`) | `settings.css` (new "Notifications" section) | |
| 4 | `wb-listora/includes/admin/class-settings-page.php:2553` (`.listora-notification-log table`) | `settings.css` | |
| 5 | `wb-listora/includes/admin/class-admin.php:2061` | `assets/css/admin.css` (or per-page CSS depending on what it styles) | open file to confirm scope |
| 6 | `wb-listora/includes/admin/class-health-check.php:89` | `assets/css/admin/health.css` (new) — enqueued only on `?page=listora-health` | |
| 7 | `wb-listora-pro/includes/class-pro-plugin.php:1938` | `wb-listora-pro/assets/css/pro-admin.css` (new section) | |
| 8 | `wb-listora-pro/includes/features/class-coming-soon.php:83` | `wb-listora-pro/assets/css/admin/coming-soon.css` (new) | |

Each move = (a) cut the rules into the target file under a clearly-commented section, (b) delete the inline block from PHP, (c) verify the page in the browser at 1440 + 390 viewports, (d) commit one block at a time so reverts are surgical.

---

## Phase 2 — Strip inline `<script>` blocks

**11+ blocks** in admin PHP (excluding JSON-LD which stays).

For each block:
1. Move the JS body verbatim into a new `assets/js/admin/<feature>.js`.
2. Replace any PHP-interpolated values (`<?php echo … ?>` inside the script) with calls to `wp_localize_script( $handle, 'listora<Feature>', [ … ] )` and read them as `listoraFeature.foo` in the JS.
3. Enqueue the new script with the existing handle conditioner (only when the relevant admin page is active — see `class-assets.php`).
4. Delete the inline `<script>` block from PHP.

| Source | Block purpose | New JS file |
|--------|---------------|-------------|
| `class-settings-page.php:504` | (open file to confirm) | `assets/js/admin/settings-section-toggle.js` |
| `class-settings-page.php:1235` | beyond-limit radio toggle | `settings-features-toggle.js` |
| `class-settings-page.php:1482` | copy-button handler | already partly in `assets/js/shared/` — fold into `shared/copy-button.js` |
| `class-settings-page.php:1706, 1785` | recipients toggle (and dup) | `settings-notifications.js` (one file, dedupe) |
| `class-settings-page.php:2466` | notification-log fetcher + CSV export | `settings-notification-log.js` |
| `class-admin.php:794, 1306, 1712, 1974` | (open file to confirm — likely listings columns / inline filters) | per-feature handles |
| `class-badges.php:1340` (pro) | badge admin | `wb-listora-pro/assets/js/admin/badges.js` |
| `class-google-places.php:1012` (pro) | places importer | `pro-google-places.js` |
| `class-comparison.php:220, 801` (pro) | comparison admin | `pro-comparison.js` |
| `class-analytics.php:583` (pro) | analytics chart bootstrap | `pro-analytics.js` |

JSON-LD `<script type="application/ld+json">` in `class-plugin.php`, `class-schema-generator.php`, `class-seo-pages.php`: **leave alone** (these are server-rendered structured data, not behavior).

---

## Phase 3 — Strip inline `style=""` attributes

### 3a. Reviews reply form (single highest-leverage fix)

**125 inline styles vanish in one edit.** The Reviews list emits a hidden reply row per review:

```html
<tr class="listora-review-reply-row" style="display:none;">
  <td colspan="…" style="padding:0.75rem 1rem;background:#f9f9f9;">
    <div style="display:flex;gap:0.5rem;align-items:flex-start;">
      <textarea class="listora-reply-textarea" style="flex:1 1 0%;min-width:0;"></textarea>
      <button …>
    </div>
    <div class="listora-reply-status" style="margin-top:0.25rem;font-size:12px;"></div>
  </td>
</tr>
```

Move all five rules into `admin.css` (or a new `admin/reviews.css`):

```css
.listora-review-reply-row { display: none; }
.listora-review-reply-row.is-open { display: table-row; }
.listora-review-reply-row > td { padding: .75rem 1rem; background: var(--listora-bg-secondary); }
.listora-review-reply-row__form { display: flex; gap: .5rem; align-items: flex-start; }
.listora-reply-textarea { flex: 1 1 0%; min-width: 0; }
.listora-reply-status { margin-top: .25rem; font-size: 12px; }
```

Then change the JS toggle to `el.classList.add('is-open')` instead of `el.style.display = 'table-row'`. Net: −125 inline styles, +6 reusable rules.

### 3b. Other source-side `style="…"` clusters

Remaining ~130 attrs across:
- `includes/admin/class-admin.php` (36)
- `includes/admin/class-setup-wizard.php` (13)
- `includes/admin/class-taxonomy-fields.php` (11)
- `includes/admin/class-listing-columns.php` (10)
- `wb-listora-pro/includes/features/class-seo-pages.php` (28)
- `wb-listora-pro/includes/features/class-badges.php` (23)
- `wb-listora-pro/includes/features/class-google-places.php` (14)
- `wb-listora-pro/includes/features/class-coupons.php` (12)

Most are `style="display:none;"` (toggle pattern) and `style="display:flex;gap:.5rem;"` (one-off layout). Replacement strategy:

| Inline style pattern | Class to use |
|----------------------|--------------|
| `style="display:none;"` | `.is-hidden` (already used in some places — standardize) |
| `style="display:flex;…"` flex rows | `.listora-cluster` (gap utility — Jetonomy has `.jt-row`) |
| `style="margin-inline-start:auto;"` | `.listora-spacer-start` utility |
| `style="color:var(--listora-text-secondary);…"` | `.listora-text-muted` |
| `style="width:50%;"` etc on submission map picker | dedicated `.listora-submission__map-picker` class |

Add a small `assets/css/admin/utilities.css` with: `.is-hidden`, `.listora-cluster`, `.listora-cluster--start/end`, `.listora-stack`, `.listora-text-muted`, `.listora-spacer-start/end`. Replace 80% of `style="…"` attrs with these.

**Email templates (`templates/emails/*.php`)** are an explicit exception — most email clients strip `<style>`, so inline styles are required there. Document this exception in the rulebook; do NOT rewrite the email files.

---

## Phase 4 — Consolidate CSS file structure

Goal: from 12 admin stylesheets per page → 2 (free + pro).

### Target structure

```
wb-listora/assets/css/admin.css                      ← single concatenated admin stylesheet (built)
wb-listora/assets/css/admin/                         ← source partials (imported into admin.css at build)
  _tokens.css
  _base.css
  _header.css
  _buttons.css
  _cards.css
  _badges.css
  _toast.css
  _confirm.css
  _utilities.css
  _settings.css
  _list-page.css
  _setup-wizard.css
  _type-editor.css
  _migration.css
  _health.css
  _reviews.css
  _upsell.css        (was pro-promotion.css)
wb-listora-pro/assets/css/pro-admin.css              ← single concatenated pro admin stylesheet (built)
wb-listora-pro/assets/css/admin/_*.css               ← partials
```

Build with the existing `webpack.config.js` (use `mini-css-extract-plugin` with multiple entry points or a `postcss-import` chain). Alternative: keep partials but enqueue ONLY the per-page partial that's needed.

### Token expansion

Adopt Jetonomy's status-triad pattern:

```css
.wb-listora-admin {
  /* status colors — each status gets base, dark, deep, light, border */
  --listora-success: #2ea44f;          /* unchanged */
  --listora-success-dark: #166534;
  --listora-success-light: #dcfce7;
  --listora-success-border: #d6e9c6;

  --listora-warn: #d97706;
  --listora-warn-dark: #92400e;
  --listora-warn-light: #fef9c3;
  --listora-warn-border: #fde68a;

  --listora-danger: #d63638;
  --listora-danger-dark: #b91c1c;
  --listora-danger-light: #fee2e2;
  --listora-danger-border: #fca5a5;

  --listora-info: #1a73e8;
  --listora-info-dark: #1e40af;
  --listora-info-light: #e8f0fe;

  /* upsell / pro */
  --listora-pro: #8B5CF6;
  --listora-pro-dark: #7c3aed;
  --listora-pro-light: #f3e8fd;
  --listora-pro-bg: #F8F7FF;
  --listora-pro-border: #C4B5FD;

  /* WP-native palette anchors (for blending with core admin chrome) */
  --listora-wp-blue: #2271b1;
  --listora-wp-blue-dark: #135e96;
  --listora-wp-text: #1d2327;
  --listora-wp-text-2: #646970;
  --listora-wp-border: #c3c4c7;
  --listora-wp-surface: #f0f0f1;
}
```

Net add: ~25 tokens, bringing us to ~55 (parity with Jetonomy).

### Token scoping decision

Keep `.wb-listora-admin` scoping (current approach), don't move to `:root`. Reason: Wbcom plugins co-exist on the same admin and three of them already use scoped tokens — moving to `:root` would risk colliding with `--bp-*`, `--bn-*`, etc. Specificity cost is real but acceptable; we already pay it.

---

## Phase 5 — Layout pattern uniformity

1. **Pro Setup** — wrap in `<div class="wrap wb-listora-admin listora-setup">…</div>` and replace the centered-title custom chrome with the standard `.listora-page-header` (icon + title + desc on the left, optional "Skip" link on the right). Wizard stepper becomes a child of the wrap. Verify in browser.
2. **Settings page** — keep the sidebar pattern but rename `.listora-settings-wrap` → use `.wb-listora-admin.listora-pattern--sidebar` to make the pattern a modifier of the same root, not a different wrapper. Updates `class-settings-page.php` and `assets/css/admin/settings.css`.
3. **Move pro-promotion CSS** — currently 133 selectors loaded on every admin page. Enqueue it only when `class-pro-promotion.php` is actually rendering (most pages render at most a banner — split banner vs. full-page upsell).
4. **Drop the parallel `*-rtl.css` files** — wire `rtlcss` into the build (one-line postcss pipeline). The 8 hand-mirrored RTL files become a single derived `admin-rtl.css` per build.

---

## Phase 6 — Verification

For each page: load at 1440×900 and 390×844, screenshot, compare against the pre-cleanup screenshots in `listora-audit/`. Pages must look identical or strictly better. Pages to verify:

```
listora                          listora-badges
listora-listing-types            listora-transactions
listora-reviews                  listora-analytics
listora-claims                   listora-audit-log
listora-moderators               listora-tools
listora-coupons                  listora-settings (all 14 tabs)
listora-health                   listora-setup
listora-pro-setup
edit.php?post_type=listora_listing  (list table — class-listing-columns is in scope)
post.php (single listing edit — taxonomy-fields is in scope)
```

Per-page pass criteria:
- 0 inline `<style>` blocks emitted by our PHP (grep raw HTML response, exclude WP core blocks)
- 0 inline `<script>` blocks emitted by our PHP (excluding JSON-LD)
- 0 source-side `style="…"` attributes
- Visual parity with pre-screenshot
- PHPCS clean

---

## Sequencing & estimate

| Phase | Files touched | Risk | LOC delta | Suggested PR |
|-------|---------------|------|-----------|--------------|
| 0 — sniffs + skill update | 3 (phpcs.xml × 2, skill md) | none | +50 | one PR per plugin + skill PR separately |
| 1 — extract `<style>` blocks | 6 PHP, 4 CSS | low | -10 KB inline / +10 KB CSS | one PR for free, one for pro |
| 2 — extract `<script>` blocks | 7 PHP, 8 new JS | medium (JS data-passing) | -14 KB inline / +14 KB JS | one PR per plugin |
| 3a — reviews reply form | 1 PHP, 1 CSS | low | -125 attrs | small focused PR |
| 3b — other inline attrs | ~12 PHP, 1 CSS (utilities) | low–medium | -130 attrs | one PR per plugin |
| 4 — CSS consolidation | webpack + many files | medium | depends on build choice | feature branch, careful rollout |
| 5 — Pro Setup + sidebar pattern + RTL build | 4 PHP, build config | medium | +200 / -1500 (deleted RTL files) | dedicated PR |
| 6 — verification | n/a | n/a | n/a | runs on every PR |

Total: ~7 PRs, each independently reviewable.

---

## Out of scope (explicitly)

- Email templates (`templates/emails/*.php`) — inline styles required for email clients.
- Frontend block CSS (`blocks/*/style.css`, frontend `*-rtl.css`) — separate audit, separate skill.
- Schema/JSON-LD `<script type="application/ld+json">` blocks — these are data, not behavior, and stay inline.
- Site front-end of the directory (listings, single-listing template, etc.).
