# Listora Admin CSS / HTML Audit

**Date:** 2026-04-28
**Scope:** wb-listora + wb-listora-pro admin UI
**Reference target:** Jetonomy admin (`/Users/varundubey/Local Sites/forums/app/public/wp-content/plugins/jetonomy/assets/css/admin.css`)
**Method:** Browser inspection (Playwright, every Listora admin tab) + raw HTML diff + grep across PHP source.

---

## TL;DR

The visual layer is roughly OK on most pages. The **source layer is messy**:

1. **5 inline `<style>` blocks (~8.2 KB) and 12 inline `<script>` blocks** are emitted from `class-settings-page.php` and `class-admin.php`. The features-form CSS is even duplicated verbatim across two of those `<style>` blocks. Strict rule from owner: **no inline CSS, no inline JS — ever.**
2. **Source-level inline `style=""` attributes** are real but localized — Reviews page emits **125 of them** (5 styles × 25 review rows for the hidden reply form), Badges 22, Listing Types 14. Most other pages 4–8 (mostly admin chrome).
3. The 240 *empty* `style=""` attrs we initially saw on `?page=listora-settings` are NOT in the server HTML — they are added at runtime by some script touching `el.style` on every input. Not a server-side fix; we can ignore it once we strip our own inline emissions and audit our admin JS.
4. CSS files are over-fragmented: **8 stylesheets enqueued per admin page** (`admin.css` + 7 `admin/*.css`) plus 3 `shared/*.css` plus `pro-admin.css` = 12 CSS files. Jetonomy ships **one** `admin.css` of 2,384 lines.
5. Token coverage is thin (30 tokens vs Jetonomy's 56) — missing pro-purple, info, pink, WP-blue variants, and several text/surface scales.
6. Layout primitives are inconsistent — most pages use `.wb-listora-admin > .listora-page-header`, but **Settings** wraps in `.listora-settings-wrap.wb-listora-admin` and **Pro Setup** ditches the page-header pattern entirely (centered title, custom wizard chrome).

---

## 1. Source-side inline emissions

### 1a. Inline `<style>` blocks (must be moved to enqueued CSS)

| File | Lines | Block content |
|------|-------|---------------|
| `wb-listora/includes/admin/class-settings-page.php` | 1701, 1777 | `.listora-features-list / -item / -toggle` — **duplicated** in both blocks (~3 KB each, identical) |
| `wb-listora/includes/admin/class-settings-page.php` | 2138 | `.listora-notification-test__status` (~216 B) |
| `wb-listora/includes/admin/class-settings-page.php` | 2553 | `.listora-notification-log table` (~454 B) |
| `wb-listora/includes/admin/class-admin.php` | 2061 | TBD — open file |
| `wb-listora/includes/admin/class-health-check.php` | 89 | Health card grid styles |
| `wb-listora-pro/includes/class-pro-plugin.php` | 1938 | Pro chrome |
| `wb-listora-pro/includes/features/class-coming-soon.php` | 83 | Coming soon notice |

### 1b. Inline `<script>` blocks (must be moved to enqueued JS)

| File | Lines | Purpose |
|------|-------|---------|
| `wb-listora/includes/admin/class-settings-page.php` | 504, 1235, 1482, 1706, 1785, 2466 | Beyond-limit radio toggle, copy-button handler, recipients toggle, notification log fetcher, CSV export |
| `wb-listora/includes/admin/class-admin.php` | 794, 1306, 1712, 1974 | TBD — open file |
| `wb-listora-pro/includes/features/class-badges.php` | 1340 | Badge admin |
| `wb-listora-pro/includes/features/class-google-places.php` | 1012 | Places importer |
| `wb-listora-pro/includes/features/class-comparison.php` | 220, 801 | Comparison block |
| `wb-listora-pro/includes/features/class-analytics.php` | 583 | Analytics chart bootstrap |
| `wb-listora-pro/blocks/comparison/render.php` | 86 | Frontend block (separate concern, not admin) |

JSON-LD `<script type="application/ld+json">` blocks in `class-plugin.php`, `class-schema-generator.php`, and `class-seo-pages.php` are legitimate — keep.

### 1c. Real `style="…"` attributes (top files)

**Free plugin:**

| File | Count |
|------|-------|
| `includes/admin/class-admin.php` | 36 |
| `includes/admin/class-setup-wizard.php` | 13 |
| `includes/admin/class-taxonomy-fields.php` | 11 |
| `includes/admin/class-listing-columns.php` | 10 |
| `includes/workflow/class-notifications.php` | 12 |
| `includes/submission-field-renderer.php` | (frontend, separate) |
| `templates/emails/*.php` | 12 across files (email markup — relax this rule for emails: many email clients strip `<style>` so inline is sometimes required) |

**Pro plugin:**

| File | Count |
|------|-------|
| `includes/features/class-seo-pages.php` | 28 |
| `includes/features/class-badges.php` | 23 |
| `includes/features/class-google-places.php` | 14 |
| `includes/features/class-coupons.php` | 12 |

### 1d. Hot pages (browser-observed source-side inline styles)

| Admin page | Inline style attrs from our source |
|------------|------------------------------------|
| Reviews | **129** — 25 rows × 5 inline styles each on the hidden reply form (`.listora-review-reply-row`, the wrapping `td`, the flex container, the textarea, the status div) |
| Badges | 22 |
| Listing Types | 14 |
| Settings, Health, Tools, Claims, Moderators, Coupons, Transactions, Analytics, Audit Log, Dashboard | 4–8 each (mostly WP admin chrome, not our code) |

---

## 2. CSS file structure — what's loaded today

```
wb-listora/assets/css/
├── admin.css                       140 selectors  ← tokens + page-header + buttons
├── admin/
│   ├── dashboard.css                49
│   ├── icons.css                     4
│   ├── list-page.css                33
│   ├── migration.css                37
│   ├── pro-promotion.css           133  ← largest, sales chrome
│   ├── settings.css                104
│   ├── setup-wizard.css             49
│   └── type-editor.css              68
└── shared/
    ├── confirm.css
    ├── pro-cta.css
    └── toast.css

wb-listora-pro/assets/css/
└── pro-admin.css                   381 selectors
```

Plus all of the above have parallel `*-rtl.css` siblings (which doubles maintenance — see §6).

**Problem**: 8 admin CSS files + 3 shared + 1 pro = **12 stylesheets per admin page**. Many declarations duplicated (e.g., card, button, badge styles redefined per file). No build step concatenates them.

---

## 3. Design tokens

### Listora today (30, scoped to `.wb-listora-admin`)
`bg`, `bg-secondary`, `bg-tertiary`, `text`, `text-secondary`, `text-tertiary`, `accent`, `accent-hover`, `success`, `warn`, `danger`, `border`, `border-heavy`, `shadow-sm`, `shadow-md`, `radius-sm`, `radius-md`, `radius-lg`, `font` (and a handful more in admin/*.css).

### Jetonomy (56, at `:root`)
Adds: success-wp, success-wp-2, success-dark, success-deep, success-light, success-border; danger-wp, danger-dark, danger-deep, danger-google, danger-light, danger-border; warn-wp, warn-dark, warn-light; info, info-wp, info-dark, info-light; pro, pro-2, pro-dark, pro-light, pro-bg, pro-border; pink-dark, pink-light; blue-wp, blue-wp-2, blue-wp-dark, blue-surface; surface-1, surface-2, surface-wp; border-strong, border-wp; text-2/3/4, text-muted, text-wp/wp-2/wp-3, text-step, text-arrow; white; msg-success, msg-error.

**Gap to close:** every status colour needs a *light/border/deep* triad (used by alerts, badges, callouts), the pro/upsell purple needs its own scale, and we need WP-native palette anchors so we can blend with native admin chrome instead of fighting it.

---

## 4. Layout / wrapper inconsistencies

| Page | Top wrapper | Header pattern | Notes |
|------|-------------|----------------|-------|
| Dashboard, Listing Types, Reviews, Claims, Moderators, Coupons, Badges, Transactions, Analytics, Audit Log, Tools, Health | `.wrap.wb-listora-admin` | `.listora-page-header` (icon + title + desc + actions) | ✅ canonical pattern |
| Settings | `.listora-settings-wrap.wb-listora-admin` | sidebar nav + content area, NO `.listora-page-header` | breaks pattern but intentionally — sidebar is the nav |
| Setup Wizard | `.wrap.wb-listora-admin` | step header | acceptable |
| **Pro Setup** | `<div>` with no `wb-listora-admin` class wrapping | centered `<h1>` + custom stepper at top + sidebar inside | **breaks every convention** — different chrome, layout, button styling |
| Health | `.wrap.wb-listora-admin.listora-health-page` | `.listora-page-header` | ✅ uses block modifier correctly |

---

## 5. Where Listora diverges from Jetonomy

| Concern | Jetonomy | Listora |
|---------|----------|---------|
| Number of admin CSS files | 1 (`admin.css`) | 12 (admin + admin/* + shared/* + pro-admin) |
| Token scope | `:root` (global, but namespaced as `--jt-admin-*`) | `.wb-listora-admin` (scoped — safer for collisions but adds specificity weight) |
| Token count | 56 | 30 |
| Page-header pattern | `.jetonomy-admin .wrap > h1 { … }` (uses native `.wrap > h1`) | `.listora-page-header` custom block |
| Inline `<style>` in admin PHP | 2 (setup-wizard, header partial) | 5 (4 in settings-page, 1 in admin, 1 in health-check) |
| Inline `<script>` in admin PHP | 7 across views | 11+ across admin/settings-page |
| Source `style="…"` attrs | similar amount (~127 across files) | comparable, plus the duplicated Reviews row pattern |

Jetonomy is **not** itself a paragon — it has its own inline-style debt — but its **design-token system and single-file admin.css** are the parts we want to copy. Our owner's "no inline css/js" rule is **stricter than Jetonomy's actual implementation** and we'll need to enforce it via PHPCS, not just convention.

---

## 6. Other audit findings worth fixing in the same pass

- **Hand-maintained `*-rtl.css` siblings** for every admin stylesheet. Replace with a build step (e.g., `rtlcss` in webpack) so RTL is a derived artefact, not 8 manually-mirrored files.
- **`includes/admin/class-pro-promotion.php`** is the largest single admin CSS file (133 selectors) and lives in the free plugin. Move sales/upsell chrome to its own file under `assets/css/admin/upsell.css` so non-pro-promotion pages don't load it.
- **Settings page `<style>` block at line 1701 and 1777 are duplicated.** First fix — delete one of them.
- **Reviews row inline-style cluster** (5 styles × 25 rows = 125) is the single largest source-side inline-style hot spot. One template change fixes 125 violations at once.
- **Pro Setup page** doesn't use `.wb-listora-admin` wrapper at all — its CSS isn't scoped under our token system, so the page can't pick up the variables. Fix the wrapper, and the wizard's centered-title chrome can be deleted in favor of the canonical `.listora-page-header`.
- **`.wb-listora-admin` token scope** loses on specificity to most third-party plugin admin styles. Either move tokens to `:root` (Jetonomy approach) or accept the higher specificity cost. Jetonomy's pattern is safer in our context — most Wbcom plugins co-exist on the same admin.

---

## 7. Skill / process gap

The `wp-plugin-development` skill at `~/.claude/skills/wp-plugin-development/references/admin-ux-rulebook.md` has *one* bullet on line 1131:

> - Do NOT use inline styles in PHP

That's the entire enforcement. There's nothing about:
- Inline `<style>` blocks (which is most of our debt)
- Inline `<script>` blocks
- A PHPCS sniff to catch them in CI
- A `wp_localize_script` / `wp_add_inline_script` data-only pattern for server-emitted variables
- A reference admin.css token list to mirror

That's why agents keep introducing them — the rule exists but is buried, lacks examples, lacks an enforcement mechanism, and the data we want them to copy (Jetonomy's token list) isn't in the skill.

See `SKILL_GAP.md` for the proposed patch.
