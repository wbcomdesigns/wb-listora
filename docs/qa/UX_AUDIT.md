# WB Listora — UX Audit

> **Per-template surface check.** Every view × every persona × every viewport × every theme mode.
> Run this when a release touches UI, or at least once per minor version.

The goal: catch silent surface regressions (broken spacing, wrong color token, hover/focus/visited state stripped by the theme, dark-mode bleed, mobile overflow) before a customer notices.

## Axes

| Axis | Values |
|------|--------|
| **Persona** | Anonymous, Member, Moderator (if present), Admin |
| **Viewport** | Desktop 1440px, Tablet 1024px (spot), Mobile 390px |
| **Theme mode** | OS-Light, OS-Dark (via `emulateMedia({ colorScheme: "dark" })`), Site toggle if plugin ships one |
| **Browser** | Chromium primary, Firefox + Safari iOS in `manual_required[]` |

Every row below × every axis combination that applies = one audit cell.
Don't re-audit identical cells across releases — audit the ones that changed or the ones flagged in the last regression guard.

---

## Template surfaces

For each template listed in your plugin's main file (or discoverable via `grep -r "template_include\|locate_template\|load_template" /Users/varundubey/Local Sites/wb-listora/app/public/wp-content/plugins/wb-listora`), verify:

### Visual contract

- [ ] Primary layout renders at 1440px — no horizontal scrollbar
- [ ] At 390px — no horizontal scrollbar, no clipped text, no off-screen buttons
- [ ] Typography hierarchy intact (H1 > H2 > H3 > body) — use computed `font-size` spot check
- [ ] Spacing consistent with design tokens (`--jt-space-*`, `--bpx-spacing-*`, etc.)
- [ ] Color tokens used (no hardcoded `#ffffff` outside debug/print styles)
- [ ] Icons load (no broken `<img>`, no 404 on SVG sprite)
- [ ] Images `loading="lazy"` where appropriate, `alt` set on content images

### Interactive states

Every `<a>`, `<button>`, clickable `<div>`, form input:

- [ ] **default** — visible, legible, correct color
- [ ] **hover** — discoverable change (color, bg, border, underline)
- [ ] **focus-visible** — clear focus ring, meets contrast, not suppressed by theme
- [ ] **active** — visual feedback on click
- [ ] **disabled** — clearly distinguishable, cursor `not-allowed`
- [ ] **visited** (links only) — different from default where meaningful

**Common trap:** theme CSS overrides button states. Always verify against the live site with the plugin's active theme, not just in isolation.

### Dark mode

- [ ] `emulateMedia({ colorScheme: "dark" })` → page remains readable
- [ ] No bleed of light-mode tokens (e.g. `#fff` background inside a dark container)
- [ ] Images/illustrations have dark variants or sufficient contrast
- [ ] Form inputs visible (borders, placeholder text)
- [ ] Focus rings visible against dark bg
- [ ] Code blocks, callouts, badges — all have dark variants

### Accessibility (spot check)

- [ ] Tab order logical
- [ ] Skip links present on templates with heavy navigation
- [ ] ARIA labels on icon-only buttons
- [ ] Form inputs have `<label>` (or `aria-label`/`aria-labelledby`)
- [ ] Color contrast ≥ 4.5:1 for body text, ≥ 3:1 for large text
- [ ] `prefers-reduced-motion` respected (no auto-play animations)

---

## Plugin-specific template list

Populate this table once per plugin. The scaffold leaves it empty — fill from your plugin's actual templates.

| Template | Route / Selector | Personas | Audit cells |
|----------|------------------|----------|-------------|
| {{TEMPLATE_1}} | {{ROUTE_1}} | Anonymous, Member, Admin | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| {{TEMPLATE_2}} | {{ROUTE_2}} | Member, Admin | Desktop-L, Desktop-D, Mobile-L, Mobile-D |
| {{TEMPLATE_3}} | {{ROUTE_3}} | Admin | Desktop-L, Desktop-D |

(Delete or expand rows to match your plugin.)

---

## Block / component surfaces

If the plugin registers Gutenberg blocks or shortcodes, audit each one rendered:

| Block / shortcode | Block editor preview | Front-end render | Dark mode | Mobile |
|-------------------|----------------------|------------------|-----------|--------|
| {{BLOCK_1}} | ☐ | ☐ | ☐ | ☐ |

Block editor checks:
- [ ] Inspector controls render without PHP/JS errors
- [ ] Preview matches front-end render (no "frontend-only" CSS surprises)
- [ ] Block validates (no "block contains unexpected content" warning on reload)

---

## Admin surfaces

For each plugin admin page (`admin.php?page=listora`):

- [ ] Page renders without `Notice:` or `Warning:` in debug.log
- [ ] Every tab renders — iterate `.nav-tab` and click each
- [ ] Every settings section has a label, help text, and saves
- [ ] List tables (if any): search, filter, pagination, bulk actions
- [ ] Action buttons on list rows: view, edit, delete, custom actions
- [ ] Admin responsive: WP collapses sidebar at 782px — verify plugin pages still usable
- [ ] Screen options / Help tabs (if plugin adds them)

---

## Email surfaces (if plugin sends mail)

For each transactional email template the plugin registers:

- [ ] Rendered HTML opens in Mailpit/Mailhog without layout break
- [ ] Dark mode email client (Gmail dark, Apple Mail dark) — text readable, buttons visible
- [ ] Merge tags resolve (Varun not literal `Varun`)
- [ ] Unsubscribe / manage preferences link works
- [ ] Plain-text fallback present

---

## Dark mode protocol (MCP-specific)

```javascript
// Chromium
browser_run_code({
  code: `await page.emulateMedia({ colorScheme: "dark" })`
})
browser_take_screenshot({ filename: "dark-<template>.png" })

// Reset before exiting
browser_run_code({
  code: `await page.emulateMedia({ colorScheme: "light" })`
})
```

Every dark-mode screenshot in this audit is one snapshot to attach to the PR that changed the surface.

---

## Output

If invoked as part of an agent walk, append to `manual_required[]` anything that can only be verified on Firefox or Safari iOS. The Chromium walk can cover Chrome-mode + dark-mode + viewport matrix.

If invoked as a human audit, treat each unchecked row as a blocking issue, file a Basecamp card, and halt the release.

## Regression guard promotion

After two clean release cycles where a UX row passes without touching it, the row is stable and can be moved to the automated structural assertion in `AGENT_SMOKE_RUNBOOK.md`. The rest stay here as slower, human-verified surface checks.
